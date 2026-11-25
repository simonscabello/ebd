<?php

namespace App\Actions\Notifications;

use App\Models\Church;
use App\Models\User;
use App\Support\ChurchCalendar;
use App\Support\LukeAdvent;
use App\Support\Push\PushMessage;
use App\Support\Push\PushSender;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Lembrete do Natal em Lucas (7h no fuso de cada igreja, antes do lembrete
 * da leitura da lição): o capítulo de hoje, para quem ainda não marcou. Um
 * aviso por pessoa, mesmo em duas turmas. Fora dos 24 dias não sai nada.
 */
final class SendAdventReminders
{
    public const HOUR = 7;

    public function __construct(
        private readonly PushSender $sender,
    ) {}

    /**
     * @param  iterable<Church>|null  $churches  só estas igrejas (padrão: todas)
     * @return int aparelhos avisados
     */
    public function handle(?CarbonImmutable $today = null, ?iterable $churches = null): int
    {
        $sent = 0;

        foreach ($churches ?? Church::query()->orderBy('id')->get() as $church) {
            $sent += ChurchCalendar::using($church, fn () => $this->remindChurch($church, $today ?? ChurchCalendar::today()));
        }

        return $sent;
    }

    private function remindChurch(Church $church, CarbonImmutable $today): int
    {
        $day = LukeAdvent::dayFor($today);

        if ($day === null) {
            return 0;
        }

        $done = DB::table('advent_checkins')->where('year', LukeAdvent::year($today))->where('day', $day)->select('user_id');

        $recipients = User::query()
            ->whereHas('pushSubscriptions')
            ->whereHas('classrooms', fn ($query) => $query->where('church_id', $church->id)->active())
            ->whereNotIn('id', $done)
            ->with('pushSubscriptions')
            ->get();

        if ($recipients->isEmpty()) {
            return 0;
        }

        return $this->sender->send($recipients->flatMap->pushSubscriptions->values(), new PushMessage(
            title: "Natal em Lucas · dia {$day}",
            body: 'Hoje é '.LukeAdvent::reference($day).'. Um capítulo por dia até o Natal.',
            url: route('advent.show', ['dia' => $day]),
            tag: 'advent:'.LukeAdvent::year($today).":{$day}",
        ));
    }
}
