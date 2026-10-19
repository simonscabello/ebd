<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De onde veio uma cópia de estudo oficial: a série e a versão copiadas, e
 * a lição de origem de cada lição. Base do aviso e da comparação de versão
 * nova. Apagar o original não apaga a cópia (só solta o vínculo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('series', function (Blueprint $table) {
            $table->foreignId('source_series_id')->nullable()->constrained('series')->nullOnDelete();
            $table->unsignedInteger('source_version')->nullable();
            $table->index('source_series_id');
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->foreignId('source_lesson_id')->nullable()->constrained('lessons')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_lesson_id');
        });

        Schema::table('series', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_series_id');
            $table->dropColumn('source_version');
        });
    }
};
