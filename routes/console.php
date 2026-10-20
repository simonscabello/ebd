<?php

use App\Actions\Meetings\FillSundays;
use App\Models\AuditLog;
use App\Models\Classroom;
use App\Support\ChurchCalendar;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Symfony\Component\Console\Output\ConsoleOutput;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Lembretes push, na hora local de cada igreja: rodam a cada 15 minutos e só
| avisam as igrejas em que agora é a hora certa (--due, ver Church::atLocalHour). Em produção um serviço
| separado roda `php artisan schedule:work`; ver README > Deploy no Railway.
| As limpezas seguem no fuso da configuração (config/ebd.php).
*/
$timezone = (string) config('ebd.timezone');

// Os lembretes rodam dentro do próprio `schedule:run`, não num processo filho:
// o filho teria stdout e stderr jogados em /dev/null, e com eles a contagem de
// aparelhos e os avisos do push (LOG_CHANNEL=stderr). Daqui, `schedule:work`
// repassa tudo para o stdout do container, que é o que o Railway mostra.
foreach ([
    'ebd:remind-readings morning' => ['ebd:remind-readings', ['slot' => 'morning', '--due' => true]],
    'ebd:remind-readings evening' => ['ebd:remind-readings', ['slot' => 'evening', '--due' => true]],
    // Véspera de cada turma (8h): só avisa quem tem encontro amanhã.
    'ebd:remind-lesson' => ['ebd:remind-lesson', ['--due' => true]],
] as $name => [$command, $arguments]) {
    Schedule::call(fn () => Artisan::call($command, $arguments, new ConsoleOutput))
        ->name($name)
        ->everyFifteenMinutes()
        ->timezone($timezone)
        ->onOneServer()
        ->withoutOverlapping();
}

// Limpeza: histórico de ações dos agentes com mais de um ano e tokens OAuth vencidos ou revogados.
Schedule::command('model:prune', ['--model' => [AuditLog::class]])->dailyAt('03:30')->timezone($timezone)->onOneServer();
Schedule::command('passport:purge')->dailyAt('03:40')->timezone($timezone)->onOneServer();

// Todo domingo existe na agenda de cada classe ativa, algumas semanas à frente.
Schedule::call(function () {
    foreach (Classroom::query()->active()->get() as $classroom) {
        ChurchCalendar::using($classroom->church, fn () => app(FillSundays::class)->handle($classroom));
    }
})->name('ebd:fill-sundays')->dailyAt('03:50')->timezone($timezone)->onOneServer();
