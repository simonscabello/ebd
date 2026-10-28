<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A capa tem recorte sem o fundo papel (arquivo "-r.webp" ao lado dela).
        // Fica na lição para o Início não perguntar ao disco a cada visita.
        Schema::table('lessons', function (Blueprint $table) {
            $table->boolean('cover_cutout')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn('cover_cutout');
        });
    }
};
