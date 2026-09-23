<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Configuração de busca em português que ignora acentos
        // ("licao" encontra "lição"). É IMMUTABLE, então pode ser usada em coluna gerada.
        DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');
        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_ts_config WHERE cfgname = 'ebd_portuguese') THEN
                    CREATE TEXT SEARCH CONFIGURATION ebd_portuguese (COPY = portuguese);
                    ALTER TEXT SEARCH CONFIGURATION ebd_portuguese
                        ALTER MAPPING FOR hword, hword_part, word WITH unaccent, portuguese_stem;
                END IF;
            END
            $$;
            SQL);

        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained()->restrictOnDelete();
            $table->foreignId('series_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('title', 180);
            // Slug global: a URL pública é /licoes/{slug}. Colisões recebem sufixo
            // da classe e, se preciso, numérico (ver App\Actions\Lessons\GenerateLessonSlug).
            $table->string('slug', 200)->unique();
            $table->text('summary')->nullable();
            $table->date('scheduled_for')->nullable();
            $table->string('bible_reference', 120)->nullable();
            $table->text('bible_text')->nullable();
            $table->text('content')->nullable();
            $table->text('teacher_notes')->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('visibility', 20)->default('public');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            // Lições são o acervo de longo prazo da EBD: exclusão é lógica.
            $table->softDeletes();

            $table->index(['classroom_id', 'status', 'scheduled_for']);
            $table->index(['series_id', 'scheduled_for']);
            $table->index('scheduled_for');
        });

        DB::statement("ALTER TABLE lessons ADD CONSTRAINT lessons_status_check CHECK (status IN ('draft', 'published', 'completed'))");
        DB::statement("ALTER TABLE lessons ADD CONSTRAINT lessons_visibility_check CHECK (visibility IN ('public', 'members'))");
        DB::statement("ALTER TABLE lessons ADD CONSTRAINT lessons_published_requires_date_check CHECK (status = 'draft' OR scheduled_for IS NOT NULL)");

        DB::statement(<<<'SQL'
            ALTER TABLE lessons ADD COLUMN search_vector tsvector GENERATED ALWAYS AS (
                setweight(to_tsvector('ebd_portuguese', coalesce(title, '')), 'A') ||
                setweight(to_tsvector('ebd_portuguese', coalesce(bible_reference, '')), 'A') ||
                setweight(to_tsvector('ebd_portuguese', coalesce(summary, '')), 'B') ||
                setweight(to_tsvector('ebd_portuguese', coalesce(content, '')), 'C')
            ) STORED
            SQL);
        DB::statement('CREATE INDEX lessons_search_vector_index ON lessons USING GIN (search_vector)');

        Schema::create('lesson_authors', function (Blueprint $table) {
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->primary(['lesson_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_authors');
        Schema::dropIfExists('lessons');
        DB::statement('DROP TEXT SEARCH CONFIGURATION IF EXISTS ebd_portuguese');
    }
};
