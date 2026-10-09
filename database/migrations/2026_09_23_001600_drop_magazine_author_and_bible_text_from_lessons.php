<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Comentarista da revista e "versículos em destaque" saíram do formulário.
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn(['magazine_author', 'bible_text']);
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->text('bible_text')->nullable()->after('bible_reference');
            $table->string('magazine_author', 120)->nullable()->after('bible_text');
        });
    }
};
