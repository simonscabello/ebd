<?php

use App\Support\OfficialLibrary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Biblioteca oficial: uma organização própria (is_official), administrada
 * pelo dono do sistema, com a turma "Estudos oficiais" onde ele escreve os
 * estudos com as mesmas telas e o mesmo MCP de sempre. Ela nunca é a igreja
 * principal e não aparece no painel das igrejas. Banco vazio não ganha a
 * biblioteca agora: ela é criada quando for usada (OfficialLibrary).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('churches', function (Blueprint $table) {
            $table->boolean('is_official')->default(false);
        });

        DB::statement('CREATE UNIQUE INDEX churches_one_official ON churches (is_official) WHERE is_official');

        if (DB::table('churches')->exists()) {
            OfficialLibrary::classroom();
        }
    }

    public function down(): void
    {
        $library = DB::table('churches')->where('is_official', true)->value('id');

        if ($library !== null) {
            $classrooms = DB::table('classrooms')->where('church_id', $library)->pluck('id');
            DB::table('classrooms')->whereIn('id', $classrooms)->delete();
            DB::table('programs')->where('church_id', $library)->delete();
            DB::table('churches')->where('id', $library)->delete();
        }

        DB::statement('DROP INDEX IF EXISTS churches_one_official');

        Schema::table('churches', function (Blueprint $table) {
            $table->dropColumn('is_official');
        });
    }
};
