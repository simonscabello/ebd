<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Campos que espelham a revista física: "Lição 11", comentarista,
        // versículo-chave e alvo da lição.
        Schema::table('lessons', function (Blueprint $table) {
            $table->unsignedSmallInteger('number')->nullable()->after('series_id');
            $table->string('magazine_author', 120)->nullable()->after('bible_text');
            $table->text('key_verse')->nullable()->after('magazine_author');
            $table->text('goal')->nullable()->after('key_verse');
            // Texto dos blocos de conteúdo voltados ao aluno, mantido pela aplicação
            // (App\Actions\Lessons\SyncLessonSearchText) porque colunas geradas não
            // podem ler outras tabelas. Blocos do professor nunca entram aqui.
            $table->text('blocks_text')->nullable();
        });

        DB::statement('CREATE UNIQUE INDEX lessons_series_number_unique ON lessons (series_id, number) WHERE deleted_at IS NULL AND number IS NOT NULL');

        $this->rebuildSearchVector(<<<'SQL'
            setweight(to_tsvector('ebd_portuguese', coalesce(title, '')), 'A') ||
            setweight(to_tsvector('ebd_portuguese', coalesce(bible_reference, '')), 'A') ||
            setweight(to_tsvector('ebd_portuguese', coalesce(summary, '')), 'B') ||
            setweight(to_tsvector('ebd_portuguese', coalesce(key_verse, '')), 'B') ||
            setweight(to_tsvector('ebd_portuguese', coalesce(goal, '')), 'B') ||
            setweight(to_tsvector('ebd_portuguese', coalesce(content, '')), 'C') ||
            setweight(to_tsvector('ebd_portuguese', coalesce(blocks_text, '')), 'D')
            SQL);
    }

    public function down(): void
    {
        $this->rebuildSearchVector(<<<'SQL'
            setweight(to_tsvector('ebd_portuguese', coalesce(title, '')), 'A') ||
            setweight(to_tsvector('ebd_portuguese', coalesce(bible_reference, '')), 'A') ||
            setweight(to_tsvector('ebd_portuguese', coalesce(summary, '')), 'B') ||
            setweight(to_tsvector('ebd_portuguese', coalesce(content, '')), 'C')
            SQL);

        DB::statement('DROP INDEX IF EXISTS lessons_series_number_unique');

        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn(['number', 'magazine_author', 'key_verse', 'goal', 'blocks_text']);
        });
    }

    private function rebuildSearchVector(string $expression): void
    {
        DB::statement('ALTER TABLE lessons DROP COLUMN search_vector');
        DB::statement("ALTER TABLE lessons ADD COLUMN search_vector tsvector GENERATED ALWAYS AS ({$expression}) STORED");
        DB::statement('CREATE INDEX lessons_search_vector_index ON lessons USING GIN (search_vector)');
    }
};
