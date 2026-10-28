<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Capa da lição (imagem quadrada), no disco dos materiais. Vale para
        // os cards, o episódio do podcast e a prévia do link.
        Schema::table('lessons', function (Blueprint $table) {
            $table->string('cover_disk')->nullable();
            $table->string('cover_path')->nullable();
            $table->timestamp('cover_updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn(['cover_disk', 'cover_path', 'cover_updated_at']);
        });
    }
};
