<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Texto bíblico (uma única versão, ver config/ebd.php > bible). Preenchida
        // por `php artisan bible:import`, nunca por migration: o texto não fica no
        // repositório. `book` segue a ordem canônica protestante (App\Enums\BibleBook).
        Schema::create('bible_verses', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('book');
            $table->unsignedSmallInteger('chapter');
            $table->unsignedSmallInteger('verse');
            $table->text('text');

            $table->unique(['book', 'chapter', 'verse']);
        });

        DB::statement('ALTER TABLE bible_verses ADD CONSTRAINT bible_verses_book_check CHECK (book BETWEEN 1 AND 66)');
    }

    public function down(): void
    {
        Schema::dropIfExists('bible_verses');
    }
};
