<?php

namespace App\Http\Controllers;

use App\Actions\Engagement\AwardBadges;
use App\Http\Controllers\Concerns\CelebratesBadges;
use App\Models\User;
use App\Support\Bible\Bible;
use App\Support\ChurchCalendar;
use App\Support\LukeAdvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Natal em Lucas: o calendário dos 24 capítulos e a marcação de cada um.
 */
class AdventController extends Controller
{
    use CelebratesBadges;

    public function show(Request $request): Response
    {
        $user = $this->user($request);
        $church = $user->currentChurch();

        abort_unless(LukeAdvent::isOpen() || LukeAdvent::isUpcoming(), 404);

        $read = LukeAdvent::readDays($user);
        $readers = LukeAdvent::readers($church);
        $today = LukeAdvent::dayFor();
        $unlocked = LukeAdvent::unlockedUntil();
        $open = (int) $request->query('dia');

        return Inertia::render('advent', [
            'upcoming' => LukeAdvent::isUpcoming(),
            'startsLabel' => ChurchCalendar::formatLong(LukeAdvent::startsOn()),
            'today' => $today,
            'blocks' => LukeAdvent::BLOCKS,
            'days' => array_map(fn (int $day) => [
                'day' => $day,
                'reference' => LukeAdvent::reference($day),
                'read' => in_array($day, $read, true),
                'locked' => $day > $unlocked,
                'readers' => $readers[$day] ?? 0,
            ], range(1, LukeAdvent::DAYS)),
            // O capítulo aberto (?dia=N). O texto é licenciado: só sai para
            // igreja com licença, como no BibleController.
            'reading' => $open >= 1 && $open <= $unlocked ? [
                'day' => $open,
                'reference' => LukeAdvent::reference($open),
                'passage' => $church?->shows_bible_text ? Bible::passage(LukeAdvent::reference($open)) : null,
            ] : null,
        ]);
    }

    public function store(Request $request, int $day, AwardBadges $badges): RedirectResponse
    {
        $user = $this->user($request);

        // Dias passados ficam abertos para pôr em dia; os futuros, não.
        abort_unless($day >= 1 && $day <= LukeAdvent::unlockedUntil(), 422);

        DB::table('advent_checkins')->insertOrIgnore([
            'user_id' => $user->id,
            'year' => LukeAdvent::year(),
            'day' => $day,
            'read_on' => ChurchCalendar::todayString(),
            'created_at' => now(),
        ]);

        $this->celebrate($badges->handle($user));

        return back();
    }

    public function destroy(Request $request, int $day): RedirectResponse
    {
        DB::table('advent_checkins')
            ->where('user_id', $this->user($request)->id)
            ->where('year', LukeAdvent::year())
            ->where('day', $day)
            ->delete();

        return back();
    }

    /** Quem pede, já com o calendário no fuso da igreja dela. */
    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        ChurchCalendar::setTimezone($user->currentChurch());

        return $user;
    }
}
