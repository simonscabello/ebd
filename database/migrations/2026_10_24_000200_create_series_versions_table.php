<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versões publicadas de um estudo oficial: um retrato fixo da série (as
 * lições publicadas, com blocos, leituras e materiais), que as igrejas
 * copiam e com o qual comparam a cópia quando sai uma versão nova.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('series_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('series_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->text('notes')->nullable();
            $table->jsonb('snapshot');
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');

            $table->unique(['series_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('series_versions');
    }
};
