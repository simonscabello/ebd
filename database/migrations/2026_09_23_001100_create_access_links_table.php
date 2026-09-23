<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Alunos "gerenciados" entram por link pessoal (enviado no WhatsApp) e não
        // precisam de e-mail nem senha. O e-mail continua único quando informado.
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
            // Só dígitos, com DDI (ex.: 5511999998888), para montar o link wa.me.
            $table->string('phone', 20)->nullable()->after('email');
        });

        // Links pessoais de acesso. Guardamos só o hash (sha256) do token: o link
        // em claro aparece uma única vez para o professor.
        Schema::create('access_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained()->nullOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('use_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });

        // No máximo um link ativo por pessoa.
        DB::statement('CREATE UNIQUE INDEX access_links_one_active_per_user ON access_links (user_id) WHERE revoked_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('access_links');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone');
        });
    }
};
