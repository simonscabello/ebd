<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De qual turma veio um estudo da biblioteca oficial (didache:copiar-para-biblioteca).
 * O caminho contrário de source_series_id: aqui a série da biblioteca aponta
 * para a original de uma igreja. Serve para não copiar a mesma série duas vezes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('series', function (Blueprint $table) {
            $table->foreignId('library_origin_series_id')->nullable()->constrained('series')->nullOnDelete();
            $table->index('library_origin_series_id');
        });
    }

    public function down(): void
    {
        Schema::table('series', function (Blueprint $table) {
            $table->dropConstrainedForeignId('library_origin_series_id');
        });
    }
};
