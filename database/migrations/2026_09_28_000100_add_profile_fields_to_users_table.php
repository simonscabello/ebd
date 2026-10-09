<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cadastro do aluno: aniversários na classe e relatório por gênero.
        Schema::table('users', function (Blueprint $table) {
            $table->date('birth_date')->nullable()->after('phone');
            $table->string('gender', 10)->nullable()->after('birth_date');
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_gender_check CHECK (gender IN ('male', 'female'))");
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['birth_date', 'gender']);
        });
    }
};
