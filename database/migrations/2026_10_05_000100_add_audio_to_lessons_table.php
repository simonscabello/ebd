<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Áudio do estudo narrado pela OpenAI. O arquivo fica no disco dos
        // materiais; audio_source_hash diz de qual versão do texto ele saiu
        // (texto alterado depois = áudio desatualizado).
        Schema::table('lessons', function (Blueprint $table) {
            $table->string('audio_disk')->nullable();
            $table->string('audio_path')->nullable();
            $table->unsignedInteger('audio_duration')->nullable();
            $table->char('audio_source_hash', 40)->nullable();
            $table->timestamp('audio_generated_at')->nullable();
            // "generating" enquanto a OpenAI trabalha; "failed" com audio_error.
            $table->string('audio_status', 20)->nullable();
            $table->string('audio_error')->nullable();
            $table->timestamp('audio_requested_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn([
                'audio_disk', 'audio_path', 'audio_duration', 'audio_source_hash',
                'audio_generated_at', 'audio_status', 'audio_error', 'audio_requested_at',
            ]);
        });
    }
};
