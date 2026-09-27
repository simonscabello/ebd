<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Classrooms\MoveStudentToClassroom;
use App\Actions\Classrooms\UpdateStudentProfile;
use App\Concerns\ProfileValidationRules;
use App\Concerns\StudentValidationRules;
use App\Enums\ClassroomRole;
use App\Enums\Gender;
use App\Http\Controllers\Controller;
use App\Http\Resources\ClassroomResource;
use App\Models\Classroom;
use App\Models\StudentNote;
use App\Models\User;
use App\Queries\AttendanceBookQuery;
use App\Queries\ClassroomOverviewQuery;
use App\Queries\CurrentLessonQuery;
use App\Queries\Data\Period;
use App\Queries\StudentProgressQuery;
use App\Queries\StudentsNeedingAttention;
use App\Support\ChurchCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Alunos da classe: a lista (com frequência e avisos) e a ficha de cada um,
 * com histórico, estudo em casa, anotações do professor e acesso ao app.
 */
class StudentController extends Controller
{
    use ProfileValidationRules, StudentValidationRules;

    /** Aniversário "chegando": nos próximos dias. */
    private const BIRTHDAY_SOON_DAYS = 7;

    public function index(
        Request $request,
        Classroom $classroom,
        AttendanceBookQuery $books,
        CurrentLessonQuery $current,
        ClassroomOverviewQuery $overview,
    ): Response {
        Gate::authorize('manageMembers', $classroom);

        $today = ChurchCalendar::todayString();
        $week = $current->for($classroom, $request->user());
        $book = $books->for($classroom);
        $period = Period::current($week->isFallback ? null : $week->lesson?->series);
        $attention = collect($overview->attention($classroom, $book, $overview->currentStudy($week, $book)))->keyBy('id');

        $users = User::query()
            ->whereKey($book->students->pluck('id'))
            ->with(['accessLinks' => fn ($query) => $query->usable()])
            ->get()
            ->keyBy('id');

        return Inertia::render('admin/classrooms/students/index', [
            'classroom' => ClassroomResource::make($classroom),
            'today' => $today,
            'period' => ['label' => $period->label, 'series_id' => $period->seriesId],
            'students' => $book->students->map(function (object $row) use ($book, $period, $attention, $users, $today) {
                /** @var User $user */
                $user = $users->get($row->id);
                $link = $user->accessLinks->first();
                $all = $book->student($row->id);

                return [
                    'id' => $row->id,
                    'name' => $row->name,
                    'phone' => $row->phone,
                    'frequency' => $book->student($row->id, $period),
                    'last_present_on' => $all['last_present_on'],
                    'needs_attention' => $attention->has($row->id),
                    'reasons' => ($attention->get($row->id) ?? ['reasons' => []])['reasons'],
                    'is_new' => ChurchCalendar::daysBetween($row->joined_on, $today) < StudentsNeedingAttention::NEW_STUDENT_DAYS,
                    'birthday' => $this->birthday($row->birth_date, $today),
                    'access' => match (true) {
                        $user->hasCompleteProfile() => 'ok',
                        $user->isManaged() && ($link === null || $link->use_count === 0) => 'never_entered',
                        default => 'pending_profile',
                    },
                ];
            })->values(),
            'teachers' => $classroom->teachers()->orderBy('name')->get(['users.id', 'users.name'])
                ->map(fn (User $teacher) => ['id' => $teacher->id, 'name' => $teacher->name]),
            'canAssignTeachers' => $request->user()->can('assignTeachers', $classroom),
        ]);
    }

    public function show(
        Request $request,
        Classroom $classroom,
        User $user,
        AttendanceBookQuery $books,
        StudentProgressQuery $progress,
        CurrentLessonQuery $current,
    ): Response {
        Gate::authorize('viewStudentProgress', [$classroom, $user]);

        $actor = $request->user();
        $week = $current->for($classroom, $actor);
        $period = Period::current($week->isFallback ? null : $week->lesson?->series);
        $book = $books->for($classroom);
        $link = $user->accessLinks()->usable()->first();
        $studentProgress = $progress->for($user, $classroom);
        $lessons = $studentProgress['lessons'];
        $since = $book->since[$user->id] ?? null;

        return Inertia::render('admin/classrooms/students/show', [
            'classroom' => ClassroomResource::make($classroom),
            'today' => ChurchCalendar::todayString(),
            'student' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'birth_date' => $user->birth_date?->toDateString(),
                'age' => $user->age(),
                'gender' => $user->gender?->value,
                'gender_label' => $user->gender?->label(),
                'counts_since' => $since,
                'has_password' => ! $user->isManaged(),
                'profile_complete' => $user->hasCompleteProfile(),
            ],
            'stats' => [
                'period' => ['label' => $period->label, 'series_id' => $period->seriesId],
                'frequency' => $book->student($user->id, $period),
                'overall' => $book->student($user->id),
                'lessons_studied' => $lessons->where('days_read', '>', 0)->count(),
                'lessons_total' => $lessons->count(),
                'streak' => $studentProgress['streak'],
            ],
            'sundays' => $book->history($user->id),
            'lessons' => $studentProgress['lessons'],
            'badges' => $studentProgress['badges'],
            'access' => [
                'link' => $link ? [
                    'use_count' => $link->use_count,
                    'last_used_at' => $link->last_used_at?->toIso8601String(),
                    'created_at' => $link->created_at->toIso8601String(),
                ] : null,
                'devices' => $user->pushSubscriptions()->count(),
            ],
            'notes' => StudentNote::query()
                ->whereBelongsTo($classroom)
                ->where('user_id', $user->id)
                ->with('author:id,name')
                ->latest()
                ->get()
                ->map(fn (StudentNote $note) => [
                    'id' => $note->id,
                    'body' => $note->body,
                    'author' => $note->author?->name,
                    'created_at' => $note->created_at->toIso8601String(),
                    'can_edit' => $actor->can('update', $note),
                ]),
            'moveTargets' => Classroom::query()
                ->when($actor->manageableClassroomIds() !== null, fn ($query) => $query->whereIn('id', (array) $actor->manageableClassroomIds()))
                ->whereKeyNot($classroom->id)
                ->active()
                ->ordered()
                ->get(['id', 'name', 'slug'])
                ->map(fn (Classroom $c) => ['slug' => $c->slug, 'name' => $c->name]),
            'canEditEmail' => $user->isManaged() || $actor->isAdmin(),
            'genders' => Gender::options(),
        ]);
    }

    public function update(Request $request, Classroom $classroom, User $user, UpdateStudentProfile $update): RedirectResponse
    {
        Gate::authorize('viewStudentProgress', [$classroom, $user]);

        $data = $request->validate([
            'name' => $this->nameRules(),
            'phone' => $this->phoneRules(),
            'birth_date' => $this->birthDateRules(),
            'gender' => $this->genderRules(),
            'email' => ['sometimes', 'nullable', 'string', 'email', 'max:255', 'unique:users,email,'.$user->id],
        ], $this->studentMessages(), $this->studentAttributes());

        if (isset($data['email'])) {
            $data['email'] = mb_strtolower(trim($data['email']));
        }

        $update->handle($user, $data, $request->user());

        $this->toast('Dados do aluno atualizados.');

        return back();
    }

    public function move(Request $request, Classroom $classroom, User $user, MoveStudentToClassroom $move): RedirectResponse
    {
        Gate::authorize('manageMembers', $classroom);
        abort_unless($user->roleIn($classroom) === ClassroomRole::Student, 404);

        $data = $request->validate(['to' => ['required', 'string', 'exists:classrooms,slug']], [], ['to' => 'classe de destino']);
        $to = Classroom::query()->where('slug', $data['to'])->firstOrFail();
        Gate::authorize('manageMembers', $to);

        $move->handle($user, $classroom, $to);

        $this->toast("{$user->name} agora é da classe {$to->name}.");

        return to_route('admin.classrooms.students.index', $classroom);
    }

    /**
     * 'today' no dia, 'soon' nos próximos dias, null no resto do ano.
     */
    private function birthday(?string $birthDate, string $today): ?string
    {
        if ($birthDate === null) {
            return null;
        }

        $year = (int) substr($today, 0, 4);
        $next = sprintf('%04d%s', $year, substr($birthDate, 4));

        if ($next < $today) {
            $next = sprintf('%04d%s', $year + 1, substr($birthDate, 4));
        }

        $days = ChurchCalendar::daysBetween($today, $next);

        return match (true) {
            $days === 0 => 'today',
            $days <= self::BIRTHDAY_SOON_DAYS => 'soon',
            default => null,
        };
    }
}
