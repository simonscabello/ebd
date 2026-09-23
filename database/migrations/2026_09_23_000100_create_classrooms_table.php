<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classrooms', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 120)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        // Vínculo usuário x classe. O papel é contextual: a mesma pessoa pode ser
        // professora em "Jovens" e aluna em "Adultos".
        Schema::create('classroom_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->timestamps();

            $table->unique(['classroom_id', 'user_id']);
            $table->index(['user_id', 'role']);
        });

        DB::statement("ALTER TABLE classroom_user ADD CONSTRAINT classroom_user_role_check CHECK (role IN ('teacher', 'student'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_user');
        Schema::dropIfExists('classrooms');
    }
};
