<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Li hoje": um registro por pessoa, lição e dia (data no fuso da igreja).
        Schema::create('reading_checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_reading_id')->nullable()->constrained()->nullOnDelete();
            $table->date('read_on');
            $table->timestamp('created_at')->nullable();

            $table->unique(['user_id', 'lesson_id', 'read_on']);
            $table->index(['user_id', 'read_on']);
            $table->index(['lesson_id', 'read_on']);
        });

        // Autoavaliação nas perguntas de revisão. A resposta digitada não é
        // guardada (fica só no aparelho): só "acertei / em parte / errei".
        Schema::create('question_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_question_id')->constrained()->cascadeOnDelete();
            $table->string('self_assessment', 10);
            $table->timestamps();

            $table->unique(['user_id', 'lesson_question_id']);
            $table->index('lesson_question_id');
        });

        DB::statement("ALTER TABLE question_attempts ADD CONSTRAINT question_attempts_self_assessment_check CHECK (self_assessment IN ('correct', 'partial', 'wrong'))");

        // Anotação pessoal por lição. Privada: nem professor nem admin leem.
        Schema::create('lesson_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->unique(['user_id', 'lesson_id']);
        });

        // Selos conquistados. series_id preenchido nos selos "por trimestre".
        Schema::create('user_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('badge', 40);
            $table->foreignId('series_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamp('awarded_at');

            $table->index('user_id');
        });

        DB::statement("ALTER TABLE user_badges ADD CONSTRAINT user_badges_badge_check CHECK (badge IN ('first_full_week', 'streak_7', 'streak_30', 'faithful_reader', 'review_master', 'perfect_attendance'))");
        DB::statement('CREATE UNIQUE INDEX user_badges_unique ON user_badges (user_id, badge, COALESCE(series_id, 0))');
    }

    public function down(): void
    {
        Schema::dropIfExists('user_badges');
        Schema::dropIfExists('lesson_notes');
        Schema::dropIfExists('question_attempts');
        Schema::dropIfExists('reading_checkins');
    }
};
