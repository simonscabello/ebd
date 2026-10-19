<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Programas de ensino da igreja (Escola Bíblica Dominical, discipulado,
 * pequenos grupos...). Toda turma pertence a um programa. As turmas que já
 * existem entram no programa "Escola Bíblica Dominical" da própria igreja.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            // Nome nos textos do dia a dia: "Amanhã tem EBD!", "Sem EBD".
            $table->string('short_name', 30);
            $table->string('slug', 120);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['church_id', 'slug']);
        });

        // Coordenadores: gerenciam todas as turmas do programa.
        Schema::create('program_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['program_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::table('classrooms', function (Blueprint $table) {
            $table->foreignId('program_id')->nullable()->after('church_id')->constrained()->restrictOnDelete();
        });

        foreach (DB::table('classrooms')->distinct()->pluck('church_id') as $churchId) {
            $programId = DB::table('programs')->insertGetId([
                'church_id' => $churchId,
                'name' => 'Escola Bíblica Dominical',
                'short_name' => 'EBD',
                'slug' => 'ebd',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('classrooms')->where('church_id', $churchId)->update(['program_id' => $programId]);
        }

        Schema::table('classrooms', function (Blueprint $table) {
            $table->foreignId('program_id')->nullable(false)->change();
            $table->index('program_id');
        });
    }

    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('program_id');
        });

        Schema::dropIfExists('program_user');
        Schema::dropIfExists('programs');
    }
};
