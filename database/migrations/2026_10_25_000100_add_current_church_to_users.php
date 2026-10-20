<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Igreja escolhida por quem administra mais de uma. Só uma preferência:
 * User::currentChurch() confere a cada leitura se a pessoa ainda administra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_church_id')->nullable()->constrained('churches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_church_id');
        });
    }
};
