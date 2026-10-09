<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Perguntas (reflexão e revisão com gabarito) saíram do produto.
        Schema::dropIfExists('question_attempts');
        Schema::dropIfExists('lesson_questions');

        DB::table('user_badges')->where('badge', 'review_master')->delete();
        DB::statement('ALTER TABLE user_badges DROP CONSTRAINT user_badges_badge_check');
        DB::statement("ALTER TABLE user_badges ADD CONSTRAINT user_badges_badge_check CHECK (badge IN ('first_full_week', 'streak_7', 'streak_30', 'faithful_reader', 'perfect_attendance'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE user_badges DROP CONSTRAINT user_badges_badge_check');
        DB::statement("ALTER TABLE user_badges ADD CONSTRAINT user_badges_badge_check CHECK (badge IN ('first_full_week', 'streak_7', 'streak_30', 'faithful_reader', 'review_master', 'perfect_attendance'))");

        Schema::create('lesson_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20)->default('reflection');
            $table->text('body');
            $table->text('answer')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['lesson_id', 'position']);
        });

        Schema::create('question_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_question_id')->constrained()->cascadeOnDelete();
            $table->string('self_assessment', 10);
            $table->timestamps();

            $table->unique(['user_id', 'lesson_question_id']);
        });
    }
};
