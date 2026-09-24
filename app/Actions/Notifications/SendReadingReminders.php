<?php

namespace App\Actions\Notifications;

use App\Enums\LessonStatus;
use App\Enums\ReminderSlot;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\ReadingCheckin;
use App\Models\User;
use App\Queries\CurrentLessonQuery;
use App\Support\Bible\Bible;
use App\Support\ChurchCalendar;
use App\Support\Push\PushMessage;
use App\Support\Push\PushSender;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Lembrete da leitura do dia, por classe (9h e 20h no fuso da igreja).
 *
 * Segue a mesma regra de "Minha semana": a leitura de hoje é a do plano da
 * lição da semana; sem plano, de segunda a sábado é reler o texto base. À
 * noite só recebe quem ainda não marcou a leitura de hoje. Sem lição da semana
 * (ou lição ainda em rascunho) não há lembrete.
 */
final class SendReadingReminders
{
    public function __construct(
        private readonly CurrentLessonQuery $current,
        private readonly PushSender $sender,
    ) {}

    /**
     * @return int aparelhos avisados
     */
    public function handle(ReminderSlot $slot, ?CarbonImmutable $today = null): int
    {
        $today ??= ChurchCalendar::today();
        $weekday = $today->dayOfWeekIso;
        $sent = 0;

        foreach (Classroom::query()->active()->get() as $classroom) {
            $recipients = self::subscribedMembers($classroom);

            if ($recipients->isEmpty()) {
                continue;
            }

            // Todos os destinatários são membros, então enxergam as mesmas lições
            // publicadas; a checagem de status evita lembrar um rascunho que só
            // o professor vê.
            $current = $this->current->for($classroom, $recipients->first(), $today);
            $lesson = $current->lesson;

            if ($lesson === null || $current->isFallback || $lesson->status !== LessonStatus::Published) {
                continue;
            }

            $reading = self::todaysReading($lesson, $weekday);

            if ($reading === null) {
                continue;
            }

            if ($slot === ReminderSlot::Evening) {
                $done = ReadingCheckin::query()
                    ->where('lesson_id', $lesson->id)
                    ->where('weekday', $weekday)
                    ->pluck('user_id')
                    ->all();

                $recipients = $recipients->reject(fn (User $user) => in_array($user->id, $done, true));
            }

            $sent += $this->sender->send(
                $recipients->flatMap->pushSubscriptions->values(),
                $this->message($slot, $classroom, $lesson, $reading),
            );
        }

        return $sent;
    }

    /**
     * Membros da classe com pelo menos um aparelho inscrito.
     *
     * @return Collection<int, User>
     */
    public static function subscribedMembers(Classroom $classroom): Collection
    {
        return $classroom->members()
            ->whereHas('pushSubscriptions')
            ->with('pushSubscriptions')
            ->get();
    }

    /**
     * @return array{reference: string, notes: string|null}|null
     */
    private static function todaysReading(Lesson $lesson, int $weekday): ?array
    {
        $lesson->loadMissing('readings');

        $today = $lesson->readings
            ->filter(fn ($reading) => $reading->weekday?->value === $weekday)
            ->sortBy('position')
            ->values();

        if ($today->isNotEmpty()) {
            return [
                'reference' => $today->pluck('reference')->implode(' · '),
                'notes' => $today->first()->notes,
            ];
        }

        $hasPlan = $lesson->readings->contains(fn ($reading) => $reading->weekday !== null);

        if ($hasPlan || $weekday === 7 || ! $lesson->bible_reference) {
            return null;
        }

        return ['reference' => $lesson->bible_reference, 'notes' => 'Releia o texto base da lição.'];
    }

    /**
     * @param  array{reference: string, notes: string|null}  $reading
     */
    private function message(ReminderSlot $slot, Classroom $classroom, Lesson $lesson, array $reading): PushMessage
    {
        $url = route('my-week', ['classe' => $classroom->slug]);
        $tag = "reading:{$lesson->id}:".ChurchCalendar::today()->dayOfWeekIso;

        if ($slot === ReminderSlot::Evening) {
            return new PushMessage(
                title: "Ainda dá tempo: {$reading['reference']}",
                body: 'Marque a leitura de hoje antes de dormir.',
                url: $url,
                tag: $tag,
            );
        }

        $body = $reading['notes'];

        if (! $body) {
            $verse = Bible::passage($reading['reference'])['verses'][0]['text'] ?? null;
            $body = $verse ? Str::limit($verse, 110) : 'Abra o app e marque quando ler.';
        }

        return new PushMessage(
            title: "Leitura de hoje: {$reading['reference']}",
            body: $body,
            url: $url,
            tag: $tag,
        );
    }
}
