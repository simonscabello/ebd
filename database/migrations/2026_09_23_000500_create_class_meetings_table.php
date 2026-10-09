<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Encontros da classe (os domingos). Lição e data são coisas diferentes:
        // uma lição pode ocupar vários encontros e há domingos sem EBD.
        Schema::create('class_meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            // Nulo = encontro ainda sem lição definida (ou evento especial com título).
            $table->foreignId('lesson_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('held_on');
            $table->string('status', 20)->default('planned');
            // Rótulo para encontros sem lição ou cancelados ("Culto de Missões").
            $table->string('title', 120)->nullable();
            // Anotação do professor ("paramos no tópico II.2"). Nunca vai para alunos.
            $table->text('notes')->nullable();
            $table->timestamp('attendance_taken_at')->nullable();
            $table->unsignedSmallInteger('visitors_count')->default(0);
            $table->timestamps();

            $table->unique(['classroom_id', 'held_on']);
            $table->index('lesson_id');
        });

        DB::statement("ALTER TABLE class_meetings ADD CONSTRAINT class_meetings_status_check CHECK (status IN ('planned', 'held', 'cancelled'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('class_meetings');
    }
};
