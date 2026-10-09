<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\AddManagedStudent;
use App\Mcp\Tools\CancelMeetingTool;
use App\Mcp\Tools\FinishMeetingTool;
use App\Mcp\Tools\GetClassroomOverview;
use App\Mcp\Tools\GetLesson;
use App\Mcp\Tools\GetStudentProgress;
use App\Mcp\Tools\ListClassrooms;
use App\Mcp\Tools\ListLessons;
use App\Mcp\Tools\ListMeetings;
use App\Mcp\Tools\ListStudents;
use App\Mcp\Tools\MoveStudent;
use App\Mcp\Tools\PlanMeetingsTool;
use App\Mcp\Tools\RecordAttendanceTool;
use App\Mcp\Tools\RemoveLessonItem;
use App\Mcp\Tools\SaveLessonBlockTool;
use App\Mcp\Tools\SaveLessonDraft;
use App\Mcp\Tools\SaveLessonMaterial;
use App\Mcp\Tools\SaveLessonReading;
use App\Mcp\Tools\SaveMeetingTool;
use App\Mcp\Tools\UpdateStudent;
use Laravel\Mcp\Server;

/**
 * Servidor MCP do EBD (rota /mcp, ver routes/ai.php).
 *
 * Agentes de IA (Claude no navegador, no celular ou no Claude Code) operam o
 * app em nome de um professor ou administrador, com as mesmas permissões das
 * telas de gestão. Publicar lições e apagar dados ficam de fora de propósito.
 */
class EbdServer extends Server
{
    protected string $name = 'EBD';

    protected string $version = '1.0.0';

    /** Todas as ferramentas numa página só: nem todo cliente pede a página seguinte. */
    public int $defaultPaginationLength = 50;

    protected string $instructions = <<<'MARKDOWN'
        Servidor da Escola Bíblica Dominical (EBD). Você age em nome da pessoa logada
        (professor ou administrador) e só enxerga as classes que ela gerencia.

        Como trabalhar:
        - Comece com list_classrooms para saber as classes (use o slug nas outras ferramentas).
        - Nomes de alunos podem ser ambíguos: se uma ferramenta devolver candidatos, pergunte à
          pessoa qual é o certo e use o id.
        - Datas sempre em AAAA-MM-DD, no fuso da igreja. Os encontros da EBD são aos domingos.
        - Lições criadas ou editadas por aqui ficam como rascunho. Publicar (o que avisa a
          classe inteira por notificação) é sempre feito pela pessoa no app; ofereça o link.
        - Antes de alterar dados que a pessoa não pediu explicitamente, confirme com ela.
        - Toda alteração fica registrada em nome da pessoa.
    MARKDOWN;

    protected array $tools = [
        // Consultas
        ListClassrooms::class,
        GetClassroomOverview::class,
        ListLessons::class,
        GetLesson::class,
        ListMeetings::class,
        ListStudents::class,
        GetStudentProgress::class,

        // Lições (sempre rascunho)
        SaveLessonDraft::class,
        SaveLessonBlockTool::class,
        SaveLessonReading::class,
        SaveLessonMaterial::class,
        RemoveLessonItem::class,

        // Agenda e presença
        PlanMeetingsTool::class,
        SaveMeetingTool::class,
        RecordAttendanceTool::class,
        FinishMeetingTool::class,
        CancelMeetingTool::class,

        // Alunos
        AddManagedStudent::class,
        UpdateStudent::class,
        MoveStudent::class,
    ];
}
