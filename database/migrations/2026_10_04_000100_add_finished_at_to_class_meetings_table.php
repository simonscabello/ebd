<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Encerrar aula": a chamada sozinha marca o domingo como realizado,
        // mas só o encerramento diz que a aula terminou.
        Schema::table('class_meetings', function (Blueprint $table) {
            $table->timestamp('finished_at')->nullable()->after('attendance_taken_at');
        });
    }

    public function down(): void
    {
        Schema::table('class_meetings', function (Blueprint $table) {
            $table->dropColumn('finished_at');
        });
    }
};
