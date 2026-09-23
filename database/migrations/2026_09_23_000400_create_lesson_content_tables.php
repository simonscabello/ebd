<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Materiais de tipos variados em uma única tabela: o "type" define como
        // o material é exibido e validado; a origem é um arquivo (disk/path) ou uma URL.
        Schema::create('lesson_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('disk', 40)->nullable();
            $table->string('path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            // Material principal da lição (ex.: a revista/lição em PDF).
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['lesson_id', 'position']);
        });

        DB::statement("ALTER TABLE lesson_materials ADD CONSTRAINT lesson_materials_type_check CHECK (type IN ('pdf', 'file', 'link', 'video', 'audio', 'reference'))");
        DB::statement("ALTER TABLE lesson_materials ADD CONSTRAINT lesson_materials_source_check CHECK (type = 'reference' OR url IS NOT NULL OR (disk IS NOT NULL AND path IS NOT NULL))");

        Schema::create('lesson_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['lesson_id', 'position']);
        });

        // Leituras da semana. weekday segue ISO-8601 (1 = segunda ... 7 = domingo);
        // nulo significa uma leitura geral, sem dia definido.
        Schema::create('lesson_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('weekday')->nullable();
            $table->string('reference', 160);
            $table->text('notes')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['lesson_id', 'position']);
        });

        DB::statement('ALTER TABLE lesson_readings ADD CONSTRAINT lesson_readings_weekday_check CHECK (weekday IS NULL OR weekday BETWEEN 1 AND 7)');
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_readings');
        Schema::dropIfExists('lesson_questions');
        Schema::dropIfExists('lesson_materials');
    }
};
