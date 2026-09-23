<?php

namespace App\Http\Controllers;

use App\Actions\Engagement\RecordReadingCheckin;
use App\Http\Controllers\Concerns\CelebratesBadges;
use App\Models\Lesson;
use App\Models\LessonReading;
use App\Models\User;
use App\Support\ChurchCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReadingCheckinController extends Controller
{
    use CelebratesBadges;

    public function store(Request $request, Lesson $lesson, RecordReadingCheckin $record): RedirectResponse
    {
        $data = $request->validate([
            'reading_id' => ['nullable', 'integer'],
            'yesterday' => ['boolean'],
        ]);

        $reading = isset($data['reading_id']) ? LessonReading::query()->whereKey((int) $data['reading_id'])->first() : null;

        /** @var User $user */
        $user = $request->user();

        $badges = $record->handle($user, $lesson, $reading, (bool) ($data['yesterday'] ?? false));

        $this->celebrate($badges);

        return back();
    }

    /**
     * Desfazer só vale para hoje ou ontem, e só os próprios registros.
     */
    public function destroy(Request $request, Lesson $lesson): RedirectResponse
    {
        $date = $request->validate(['date' => ['required', 'date_format:Y-m-d']])['date'];
        $today = ChurchCalendar::today();

        abort_unless(in_array($date, [$today->toDateString(), $today->subDay()->toDateString()], true), 404);

        DB::table('reading_checkins')
            ->where('user_id', $request->user()?->id)
            ->where('lesson_id', $lesson->id)
            ->where('read_on', $date)
            ->delete();

        return back();
    }
}
