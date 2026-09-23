<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Materiais só do professor (ex.: manual completo, roteiro em PDF).
        Schema::table('lesson_materials', function (Blueprint $table) {
            $table->string('audience', 20)->default('student')->after('type');
        });

        DB::statement("ALTER TABLE lesson_materials ADD CONSTRAINT lesson_materials_audience_check CHECK (audience IN ('teacher', 'student'))");
    }

    public function down(): void
    {
        Schema::table('lesson_materials', function (Blueprint $table) {
            $table->dropColumn('audience');
        });
    }
};
