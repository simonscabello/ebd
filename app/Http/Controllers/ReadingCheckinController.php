<?php

namespace App\Http\Controllers;

use App\Actions\Engagement\RecordReadingCheckin;
use App\Enums\Weekday;
use App\Http\Controllers\Concerns\CelebratesBadges;
use App\Models\Lesson;
use App\Models\LessonReading;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReadingCheckinController extends Controller
{
    use CelebratesBadges;

    public function store(Request $request, Lesson $lesson, RecordReadingCheckin $record): RedirectResponse
    {
        $data = $request->validate([
            'weekday' => ['required', Rule::enum(Weekday::class)],
            'reading_id' => ['nullable', 'integer'],
        ]);

        $reading = isset($data['reading_id']) ? LessonReading::query()->whereKey((int) $data['reading_id'])->first() : null;

        /** @var User $user */
        $user = $request->user();

        $badges = $record->handle($user, $lesson, Weekday::from((int) $data['weekday']), $reading);

        $this->celebrate($badges);

        return back();
    }

    /**
     * Desmarca um dia do plano de leitura (só os próprios registros).
     */
    public function destroy(Request $request, Lesson $lesson): RedirectResponse
    {
        $weekday = $request->validate(['weekday' => ['required', Rule::enum(Weekday::class)]])['weekday'];

        DB::table('reading_checkins')
            ->where('user_id', $request->user()?->id)
            ->where('lesson_id', $lesson->id)
            ->where('weekday', (int) $weekday)
            ->delete();

        return back();
    }
}
