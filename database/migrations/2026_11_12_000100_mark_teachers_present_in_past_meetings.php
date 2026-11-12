<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // O professor não aparecia na chamada e ficava com falta: presente em
        // todo encontro com chamada desde que entrou na classe.
        DB::statement("
            INSERT INTO attendances (class_meeting_id, user_id, created_at)
            SELECT class_meetings.id, classroom_user.user_id, NOW()
            FROM class_meetings
            JOIN classroom_user ON classroom_user.classroom_id = class_meetings.classroom_id AND classroom_user.role = 'teacher'
            WHERE class_meetings.status = 'held'
              AND class_meetings.attendance_taken_at IS NOT NULL
              AND class_meetings.held_on >= classroom_user.created_at::date
            ON CONFLICT (class_meeting_id, user_id) DO NOTHING
        ");
    }

    public function down(): void {}
};
