<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\AddManagedStudent;
use App\Mcp\Tools\AdvanceLessonTool;
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
use App\Mcp\Tools\RecordAttendanceTool;
use App\Mcp\Tools\RemoveLessonItem;
use App\Mcp\Tools\SaveLessonBlockTool;
use App\Mcp\Tools\SaveLessonCoverTool;
use App\Mcp\Tools\SaveLessonDraft;
use App\Mcp\Tools\SaveLessonMaterial;
use App\Mcp\Tools\SaveLessonReading;
use App\Mcp\Tools\SaveMeetingTool;
use App\Mcp\Tools\SetCurrentLessonTool;
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
    protected string $name = 'Didaché';

    protected string $version = '1.0.0';

    /** Todas as ferramentas numa página só: nem todo cliente pede a página seguinte. */
    public int $defaultPaginationLength = 50;

    protected string $instructions = <<<'MARKDOWN'
        Servidor do Didaché, o app das classes de ensino da igreja (Escola Bíblica Dominical,
        discipulado e outros programas). Você age em nome da pessoa logada (professor,
        coordenador ou administrador) e só enxerga as classes que ela gerencia.

        Como trabalhar:
        - Comece com list_classrooms para saber as classes (use o slug nas outras ferramentas).
        - Nomes de alunos podem ser ambíguos: se uma ferramenta devolver candidatos, pergunte à
          pessoa qual é o certo e use o id.
        - Datas sempre em AAAA-MM-DD, no fuso da igreja. Cada classe se reúne num dia da semana e pertence a um programa (veja list_classrooms); encontros em datas fora desse dia são recusados.
        - A classe tem uma lição atual, que fica quantos encontros precisar. Para trocar, use
          set_current_lesson; quando terminar, advance_lesson (ou finish_meeting com continues=false).
          A agenda não é planejada: só o próximo encontro existe adiantado, com a lição atual.
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
        SaveLessonCoverTool::class,
        RemoveLessonItem::class,

        // Agenda e presença
        SetCurrentLessonTool::class,
        AdvanceLessonTool::class,
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
