<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * A igreja vira entidade. Toda classe passa a pertencer a uma igreja, e a
 * igreja tem seus administradores (church_user).
 *
 * As classes que já existem são da igreja que hoje está na configuração
 * (EBD_CHURCH_NAME e EBD_TIMEZONE): ela nasce daqui, com as classes e os
 * admins atuais. Banco vazio (testes, instalação nova) não ganha igreja.
 * Nesta etapa, além do nome exibido, nada lê da igreja ainda: fuso, agenda e
 * IA continuam vindo da configuração.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('churches', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->string('timezone', 64);
            $table->timestamps();
        });

        // Administradores da igreja. Professor e aluno continuam por classe.
        Schema::create('church_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['church_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::table('classrooms', function (Blueprint $table) {
            $table->foreignId('church_id')->nullable()->after('id')->constrained()->restrictOnDelete();
        });

        $hasData = DB::table('classrooms')->exists() || DB::table('users')->where('is_admin', true)->exists();

        if ($hasData) {
            $name = (string) config('ebd.church_name');

            $churchId = DB::table('churches')->insertGetId([
                'name' => $name,
                'slug' => Str::slug($name) ?: 'igreja',
                'timezone' => (string) config('ebd.timezone'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('classrooms')->update(['church_id' => $churchId]);

            DB::table('church_user')->insertUsing(
                ['church_id', 'user_id', 'created_at', 'updated_at'],
                DB::table('users')->where('is_admin', true)->selectRaw('?, id, now(), now()', [$churchId]),
            );
        }

        Schema::table('classrooms', function (Blueprint $table) {
            $table->foreignId('church_id')->nullable(false)->change();
            $table->index('church_id');
        });
    }

    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('church_id');
        });

        Schema::dropIfExists('church_user');
        Schema::dropIfExists('churches');
    }
};
