<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Perguntas de reflexão (discussão) x revisão (com gabarito, para o aluno
        // conferir o que aprendeu).
        Schema::table('lesson_questions', function (Blueprint $table) {
            $table->string('kind', 20)->default('reflection')->after('lesson_id');
            $table->text('answer')->nullable()->after('body');
        });

        DB::statement("ALTER TABLE lesson_questions ADD CONSTRAINT lesson_questions_kind_check CHECK (kind IN ('reflection', 'review'))");
        DB::statement("ALTER TABLE lesson_questions ADD CONSTRAINT lesson_questions_review_answer_check CHECK (kind = 'reflection' OR answer IS NOT NULL)");
    }

    public function down(): void
    {
        Schema::table('lesson_questions', function (Blueprint $table) {
            $table->dropColumn(['kind', 'answer']);
        });
    }
};
