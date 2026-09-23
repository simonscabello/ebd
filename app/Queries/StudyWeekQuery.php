<?php

namespace App\Queries;

use App\Enums\ContentAudience;
use App\Enums\QuestionKind;
use App\Enums\Weekday;
use App\Http\Resources\LessonBlockResource;
use App\Http\Resources\LessonReadingResource;
use App\Models\Classroom;
use App\Models\LessonBlock;
use App\Models\User;
use App\Support\ChurchCalendar;
use App\Support\StudyStreak;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "Minha semana": a semana de estudo (segunda a domingo) da lição atual da classe.
 *
 * - leitura de cada dia (lesson_readings.weekday) e se a pessoa marcou "Li";
 * - conteúdo do dia (curiosidade/conceito): blocos com dia definido ou, sem
 *   dia, distribuídos automaticamente de segunda a sábado;
 * - checklist "Prepare-se para domingo" e sequência de dias.
 */
class StudyWeekQuery
{
    public function __construct(
        private readonly CurrentLessonQuery $current,
        private readonly StudyStreak $streak,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function for(User $user, Classroom $classroom, Request $request): array
    {
        $today = ChurchCalendar::today();
        $monday = $today->startOfWeek(CarbonImmutable::MONDAY);
        $current = $this->current->for($classroom, $user);
        $lesson = $current->lesson;

        $base = [
            'today' => $today->toDateString(),
            'weekday' => $today->dayOfWeekIso,
            'streak' => $this->streak->for($user, $today),
            'meeting' => $current->meeting && ! $current->isFallback ? [
                'held_on' => $current->meeting->held_on->toDateString(),
                'date_label' => ChurchCalendar::formatLong($current->meeting->held_on),
                'days_until' => ChurchCalendar::daysUntil($current->meeting->held_on),
                'index' => $current->meetingIndex,
                'total' => $current->meetingTotal,
            ] : null,
            'preparing' => $current->preparing,
        ];

        if ($lesson === null) {
            return [...$base, 'lesson' => null];
        }

        $lesson->load([
            'readings',
            'questions' => fn ($q) => $q->where('kind', QuestionKind::Review),
            'blocks' => fn ($q) => $q->where('audience', ContentAudience::Student),
        ]);

        $checkins = DB::table('reading_checkins')
            ->where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->pluck('read_on')
            ->map(fn ($d) => substr((string) $d, 0, 10))
            ->all();

        $blocksByDay = $this->dripSchedule($lesson->blocks);
        $days = [];

        foreach (Weekday::cases() as $weekday) {
            $date = $monday->addDays($weekday->value - 1);
            $days[] = [
                'date' => $date->toDateString(),
                'weekday' => $weekday->value,
                'label' => $weekday->label(),
                'short' => $weekday->shortLabel(),
                'is_today' => $date->isSameDay($today),
                'is_future' => $date->gt($today),
                'done' => in_array($date->toDateString(), $checkins, true),
                'readings' => LessonReadingResource::collection(
                    $lesson->readings->filter(fn ($r) => $r->weekday === $weekday)->values()
                )->resolve($request),
                'blocks_count' => count($blocksByDay[$weekday->value] ?? []),
            ];
        }

        // Conteúdo já liberado nesta semana (hoje e dias anteriores).
        $unlocked = collect($blocksByDay)
            ->filter(fn ($blocks, $day) => $day <= $today->dayOfWeekIso)
            ->flatten(1)
            ->values();

        $attempts = DB::table('question_attempts')
            ->where('user_id', $user->id)
            ->whereIn('lesson_question_id', $lesson->questions->pluck('id'))
            ->count();

        $readingDays = $lesson->readings->filter(fn ($r) => $r->weekday !== null && $r->weekday !== Weekday::Sunday)
            ->pluck('weekday')->unique()->count();
        $weekDone = collect($days)->filter(fn ($d) => $d['done'] && $d['weekday'] <= 6)->count();
        $hasNote = DB::table('lesson_notes')->where('user_id', $user->id)->where('lesson_id', $lesson->id)->exists();

        return [
            ...$base,
            'lesson' => [
                'id' => $lesson->id,
                'slug' => $lesson->slug,
                'url' => route('lessons.show', $lesson->slug),
                'display_title' => $lesson->displayTitle(),
                'number' => $lesson->number,
                'title' => $lesson->title,
                'bible_reference' => $lesson->bible_reference,
                'key_verse' => $lesson->key_verse,
                'general_readings' => LessonReadingResource::collection(
                    $lesson->readings->filter(fn ($r) => $r->weekday === null)->values()
                )->resolve($request),
            ],
            'days' => $days,
            'todayBlocks' => LessonBlockResource::collection($blocksByDay[$today->dayOfWeekIso] ?? [])->resolve($request),
            'unlockedBlocks' => LessonBlockResource::collection($unlocked)->resolve($request),
            'progress' => [
                'days_done' => $weekDone,
                'days_total' => max($readingDays, 1),
            ],
            'checklist' => [
                ['key' => 'read', 'label' => 'Ler o texto base ('.($lesson->bible_reference ?? 'da lição').')', 'done' => $checkins !== []],
                ['key' => 'week', 'label' => "Fazer as leituras da semana ({$weekDone}/".max($readingDays, 1).')', 'done' => $weekDone >= max($readingDays, 1)],
                ['key' => 'review', 'label' => 'Responder a revisão ('.$attempts.'/'.$lesson->questions->count().')', 'done' => $lesson->questions->isNotEmpty() && $attempts >= $lesson->questions->count(), 'hidden' => $lesson->questions->isEmpty()],
                ['key' => 'note', 'label' => 'Anotar o que Deus falou com você', 'done' => $hasNote],
                ['key' => 'magazine', 'label' => 'Levar a revista e a Bíblia no domingo', 'done' => null],
            ],
            'review' => ['answered' => $attempts, 'total' => $lesson->questions->count()],
        ];
    }

    /**
     * Distribui os blocos pelos dias: dia definido pelo professor ou, para
     * curiosidades/conceitos sem dia, um por dia de segunda a sábado.
     *
     * @param  Collection<int, LessonBlock>  $blocks
     * @return array<int, list<LessonBlock>>
     */
    private function dripSchedule(Collection $blocks): array
    {
        $schedule = [];
        $auto = 0;

        foreach ($blocks as $block) {
            if ($block->drip_weekday !== null) {
                $schedule[$block->drip_weekday->value][] = $block;
            } elseif ($block->kind->isDrippable() && in_array($block->kind->value, ['curiosity', 'concept'], true)) {
                $schedule[($auto % 6) + 1][] = $block;
                $auto++;
            }
        }

        ksort($schedule);

        return $schedule;
    }
}
