<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Lessons\CreateLesson;
use App\Actions\Lessons\UpdateLesson;
use App\Enums\ClassroomRole;
use App\Enums\ContentAudience;
use App\Enums\LessonBlockKind;
use App\Enums\LessonStatus;
use App\Enums\LessonVisibility;
use App\Enums\MaterialType;
use App\Enums\MeetingStatus;
use App\Enums\QuestionKind;
use App\Enums\Weekday;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LessonRequest;
use App\Http\Resources\ClassMeetingResource;
use App\Http\Resources\ClassroomResource;
use App\Http\Resources\LessonBlockResource;
use App\Http\Resources\LessonMaterialResource;
use App\Http\Resources\LessonQuestionResource;
use App\Http\Resources\LessonReadingResource;
use App\Http\Resources\LessonResource;
use App\Http\Resources\SeriesResource;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Series;
use App\Models\User;
use App\Support\ChurchCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LessonController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Lesson::class);

        $filters = $request->validate([
            'classe' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(LessonStatus::class)],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $manageable = $request->user()->manageableClassroomIds();

        $lessons = Lesson::query()
            ->when($manageable !== null, fn ($q) => $q->whereIn('classroom_id', $manageable))
            ->when($filters['classe'] ?? null, fn ($q, $id) => $q->where('classroom_id', $id))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->whereRaw('unaccent(title) ILIKE unaccent(?)', ['%'.addcslashes($term, '%_\\').'%']))
            ->with(['classroom', 'series'])
            ->withCount(['materials', 'questions'])
            ->orderByRaw('scheduled_for IS NULL DESC, scheduled_for DESC')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/lessons/index', [
            'lessons' => LessonResource::collection($lessons),
            'filters' => [
                'classe' => $filters['classe'] ?? null,
                'status' => $filters['status'] ?? null,
                'q' => $filters['q'] ?? '',
            ],
            'classrooms' => $this->manageableClassrooms($request->user()),
            'statuses' => collect(LessonStatus::cases())->map(fn (LessonStatus $s) => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('viewAny', Lesson::class);

        $classrooms = $this->manageableClassrooms($request->user());
        $classroomIds = $classrooms->collection->pluck('id');

        return Inertia::render('admin/lessons/create', [
            'classrooms' => $classrooms,
            'series' => SeriesResource::collection(
                Series::query()->whereIn('classroom_id', $classroomIds)->orderByDesc('starts_on')->orderBy('title')->get()
            ),
            'defaults' => [
                'classroom_id' => $request->integer('classe') ?: $classroomIds->first(),
                'series_id' => $request->integer('serie') ?: null,
                'meeting_on' => ChurchCalendar::nextSunday()->toDateString(),
            ],
            'visibilities' => $this->visibilityOptions(),
        ]);
    }

    public function store(LessonRequest $request, CreateLesson $create): RedirectResponse
    {
        /** @var Classroom $classroom */
        $classroom = $request->classroom();

        $lesson = $create->handle($request->user(), $classroom, $request->validated());

        $this->toast('Lição criada como rascunho. Agora adicione leituras, materiais e perguntas.');

        return to_route('admin.lessons.edit', $lesson);
    }

    public function edit(Request $request, Lesson $lesson): Response
    {
        Gate::authorize('update', $lesson);

        $lesson->load(['classroom', 'series', 'authors', 'materials', 'readings', 'questions', 'blocks', 'meetings']);

        return Inertia::render('admin/lessons/edit', [
            'lesson' => [
                'id' => $lesson->id,
                'classroom' => ClassroomResource::make($lesson->classroom),
                'series_id' => $lesson->series_id,
                'number' => $lesson->number,
                'title' => $lesson->title,
                'slug' => $lesson->slug,
                'url' => route('lessons.show', $lesson->slug),
                'sunday_url' => route('lessons.sunday', $lesson->slug),
                'summary' => $lesson->summary,
                'bible_reference' => $lesson->bible_reference,
                'bible_text' => $lesson->bible_text,
                'magazine_author' => $lesson->magazine_author,
                'key_verse' => $lesson->key_verse,
                'goal' => $lesson->goal,
                'content' => $lesson->content,
                'visibility' => $lesson->visibility->value,
                'status' => $lesson->status->value,
                'status_label' => $lesson->status->label(),
                'slug_locked' => $lesson->status !== LessonStatus::Draft,
                'transitions' => collect($lesson->status->allowedTransitions())->map(fn (LessonStatus $s) => $s->value),
                'author_ids' => $lesson->authors->pluck('id'),
                'materials' => LessonMaterialResource::collection($lesson->materials),
                'readings' => LessonReadingResource::collection($lesson->readings),
                'questions' => LessonQuestionResource::collection($lesson->questions),
                'blocks' => LessonBlockResource::editable($lesson->blocks, $request),
                'meetings' => ClassMeetingResource::collection($lesson->meetings),
                'agenda_url' => route('admin.classrooms.meetings.index', $lesson->classroom),
            ],
            'series' => SeriesResource::collection($lesson->classroom->series()->orderByDesc('starts_on')->orderBy('title')->get()),
            'authors' => $lesson->classroom->members()
                ->wherePivot('role', ClassroomRole::Teacher->value)
                ->orderBy('name')
                ->get(['users.id', 'users.name'])
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name]),
            'visibilities' => $this->visibilityOptions(),
            'materialTypes' => collect(MaterialType::cases())->map(fn (MaterialType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'accepts_upload' => $type->acceptsUpload(),
                'requires_upload' => $type->requiresUpload(),
                'requires_url' => $type->requiresUrl(),
                'accept' => collect($type->allowedExtensions())->map(fn ($ext) => ".{$ext}")->implode(','),
                'max_mb' => (int) floor($type->maxUploadKilobytes() / 1024),
            ]),
            'weekdays' => collect(Weekday::cases())->map(fn (Weekday $d) => ['value' => $d->value, 'label' => $d->label()]),
            'blockKinds' => collect(LessonBlockKind::cases())->map(fn (LessonBlockKind $k) => [
                'value' => $k->value,
                'label' => $k->label(),
                'default_audience' => $k->defaultAudience()->value,
            ]),
            'audiences' => collect(ContentAudience::cases())->map(fn (ContentAudience $a) => ['value' => $a->value, 'label' => $a->label()]),
            'questionKinds' => collect(QuestionKind::cases())->map(fn (QuestionKind $k) => ['value' => $k->value, 'label' => $k->label()]),
        ]);
    }

    public function update(LessonRequest $request, Lesson $lesson, UpdateLesson $update): RedirectResponse
    {
        $update->handle($lesson, $request->validated());

        $this->toast('Lição salva.');

        return back();
    }

    public function destroy(Lesson $lesson): RedirectResponse
    {
        Gate::authorize('delete', $lesson);

        DB::transaction(function () use ($lesson) {
            // Domingos planejados ficam livres; encontros já realizados continuam
            // como histórico (a lição excluída pode ser restaurada).
            $lesson->meetings()->where('status', MeetingStatus::Planned)->update(['lesson_id' => null]);
            $lesson->delete();
        });

        $this->toast('Lição excluída.');

        return to_route('admin.lessons.index');
    }

    /**
     * @return AnonymousResourceCollection
     */
    private function manageableClassrooms(User $user)
    {
        $ids = $user->manageableClassroomIds();

        return ClassroomResource::collection(
            Classroom::query()->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))->ordered()->get()
        );
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function visibilityOptions(): array
    {
        return array_map(
            fn (LessonVisibility $v) => ['value' => $v->value, 'label' => $v->label()],
            LessonVisibility::cases(),
        );
    }
}
