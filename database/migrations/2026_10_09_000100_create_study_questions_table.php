<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dúvidas que o aluno tirou com a IA na lição ("Tirar dúvida"). Servem
        // para o limite diário e, depois, para o professor ver as dúvidas da turma.
        Schema::create('study_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->text('question');
            $table->text('answer');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['lesson_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_questions');
    }
};
