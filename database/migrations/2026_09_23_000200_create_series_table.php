<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('series', function (Blueprint $table) {
            $table->id();
            // Uma série pertence a uma classe (cada classe estuda sua própria revista).
            $table->foreignId('classroom_id')->constrained()->restrictOnDelete();
            $table->string('title', 160);
            $table->string('slug', 180);
            $table->text('description')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamps();

            $table->unique(['classroom_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('series');
    }
};
