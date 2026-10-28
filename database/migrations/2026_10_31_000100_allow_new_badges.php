<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Primeiro passo, Anotador, Bereano e os surpresa (De volta, Coruja noturna, Madrugador).
        DB::statement('ALTER TABLE user_badges DROP CONSTRAINT user_badges_badge_check');
        DB::statement("ALTER TABLE user_badges ADD CONSTRAINT user_badges_badge_check CHECK (badge IN ('first_full_week', 'streak_7', 'streak_30', 'faithful_reader', 'perfect_attendance', 'first_step', 'note_taker', 'berean', 'comeback', 'night_owl', 'early_bird'))");
    }

    public function down(): void
    {
        DB::table('user_badges')->whereIn('badge', ['first_step', 'note_taker', 'berean', 'comeback', 'night_owl', 'early_bird'])->delete();
        DB::statement('ALTER TABLE user_badges DROP CONSTRAINT user_badges_badge_check');
        DB::statement("ALTER TABLE user_badges ADD CONSTRAINT user_badges_badge_check CHECK (badge IN ('first_full_week', 'streak_7', 'streak_30', 'faithful_reader', 'perfect_attendance'))");
    }
};
