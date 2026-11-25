<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Natal em Lucas: um registro por pessoa, ano e capítulo (ver LukeAdvent).
        Schema::create('advent_checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('day');
            $table->date('read_on');
            $table->timestamp('created_at')->nullable();

            $table->unique(['user_id', 'year', 'day']);
            $table->index(['year', 'day']);
        });

        DB::statement('ALTER TABLE advent_checkins ADD CONSTRAINT advent_checkins_day_check CHECK (day BETWEEN 1 AND 24)');

        DB::statement('ALTER TABLE user_badges DROP CONSTRAINT user_badges_badge_check');
        DB::statement("ALTER TABLE user_badges ADD CONSTRAINT user_badges_badge_check CHECK (badge IN ('first_full_week', 'streak_7', 'streak_30', 'faithful_reader', 'perfect_attendance', 'first_step', 'note_taker', 'berean', 'comeback', 'night_owl', 'early_bird', 'luke_advent'))");
    }

    public function down(): void
    {
        DB::table('user_badges')->where('badge', 'luke_advent')->delete();
        DB::statement('ALTER TABLE user_badges DROP CONSTRAINT user_badges_badge_check');
        DB::statement("ALTER TABLE user_badges ADD CONSTRAINT user_badges_badge_check CHECK (badge IN ('first_full_week', 'streak_7', 'streak_30', 'faithful_reader', 'perfect_attendance', 'first_step', 'note_taker', 'berean', 'comeback', 'night_owl', 'early_bird'))");

        Schema::dropIfExists('advent_checkins');
    }
};
