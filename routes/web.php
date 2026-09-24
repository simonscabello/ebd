<?php

use App\Http\Controllers\AccessLinkController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\LessonNoteController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\MaterialFileController;
use App\Http\Controllers\MyProgressController;
use App\Http\Controllers\MyWeekController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\ReadingCheckinController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Área pública
|--------------------------------------------------------------------------
| Lições publicadas e públicas abrem sem login (link do WhatsApp -> conteúdo).
| As policies decidem o que cada pessoa pode ver; as rotas não exigem auth.
*/

Route::get('/', HomeController::class)->name('home');

Route::get('licoes/{lesson:slug}', [LessonController::class, 'show'])->name('lessons.show');
Route::get('licoes/{lesson:slug}/domingo', [LessonController::class, 'sunday'])->name('lessons.sunday');

/*
| Estudo do aluno (exige login): semana de estudo, leituras marcadas e anotações.
*/
Route::middleware(['auth', 'throttle:engagement'])->group(function () {
    Route::get('minha-semana', MyWeekController::class)->name('my-week');
    Route::get('meu-progresso', MyProgressController::class)->name('my-progress');

    Route::post('licoes/{lesson:slug}/leituras', [ReadingCheckinController::class, 'store'])->name('lessons.checkins.store');
    Route::delete('licoes/{lesson:slug}/leituras', [ReadingCheckinController::class, 'destroy'])->name('lessons.checkins.destroy');
    Route::put('licoes/{lesson:slug}/anotacao', [LessonNoteController::class, 'update'])->name('lessons.note.update');

    // Lembretes push: o aparelho se inscreve/desinscreve.
    Route::post('notificacoes/inscricao', [PushSubscriptionController::class, 'store'])->name('push.store');
    Route::delete('notificacoes/inscricao', [PushSubscriptionController::class, 'destroy'])->name('push.destroy');
});

Route::get('materiais/{material}/arquivo', MaterialFileController::class)
    ->middleware('throttle:downloads')
    ->name('materials.file');

// Link pessoal de acesso dos alunos: /entrar#token (ver AccessLinkController).
Route::get('entrar', [AccessLinkController::class, 'show'])->name('access-link.show');
Route::post('entrar', [AccessLinkController::class, 'store'])
    ->middleware('throttle:access-link')
    ->name('access-link.store');

// Tutorial para instalar o app (PWA) no Android e no iPhone.
Route::inertia('instalar', 'install')->name('install');

Route::get('biblioteca', LibraryController::class)
    ->middleware('throttle:library')
    ->name('library');

/*
|--------------------------------------------------------------------------
| Gestão de conteúdo (professores e administradores)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'can:access-admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', Admin\DashboardController::class)->name('dashboard');
        Route::get('biblia/previa', Admin\BiblePreviewController::class)->name('bible.preview');

        Route::resource('classes', Admin\ClassroomController::class)
            ->parameters(['classes' => 'classroom'])
            ->names('classrooms')
            ->except(['show', 'destroy']);

        Route::get('classes/{classroom}/agenda', [Admin\ClassMeetingController::class, 'index'])->name('classrooms.meetings.index');
        Route::post('classes/{classroom}/agenda', [Admin\ClassMeetingController::class, 'store'])->name('classrooms.meetings.store');
        Route::post('classes/{classroom}/agenda/planejar', [Admin\ClassMeetingController::class, 'plan'])->name('classrooms.meetings.plan');
        Route::put('encontros/{meeting}', [Admin\ClassMeetingController::class, 'update'])->name('meetings.update');
        Route::delete('encontros/{meeting}', [Admin\ClassMeetingController::class, 'destroy'])->name('meetings.destroy');
        Route::post('encontros/{meeting}/cancelar', [Admin\MeetingStatusController::class, 'cancel'])->name('meetings.cancel');
        Route::post('encontros/{meeting}/continuar', [Admin\MeetingStatusController::class, 'continue'])->name('meetings.continue');
        Route::post('encontros/{meeting}/realizado', [Admin\MeetingStatusController::class, 'held'])->name('meetings.held');
        Route::put('encontros/{meeting}/chamada', [Admin\AttendanceController::class, 'update'])->name('meetings.attendance.update');
        Route::post('encontros/{meeting}/encerrar', [Admin\AttendanceController::class, 'finish'])->name('meetings.finish');

        Route::get('classes/{classroom}/evolucao', Admin\ClassroomInsightsController::class)->name('classrooms.insights');
        Route::get('classes/{classroom}/alunos/{user}', Admin\StudentProgressController::class)->name('classrooms.students.show');
        Route::get('series/{series}/relatorio', Admin\SeriesReportController::class)->name('series.report');

        Route::get('classes/{classroom}/membros', [Admin\ClassroomMemberController::class, 'index'])->name('classrooms.members.index');
        Route::post('classes/{classroom}/membros', [Admin\ClassroomMemberController::class, 'store'])->name('classrooms.members.store');
        Route::delete('classes/{classroom}/membros/{user}', [Admin\ClassroomMemberController::class, 'destroy'])->name('classrooms.members.destroy');
        Route::post('classes/{classroom}/alunos', [Admin\StudentAccessController::class, 'store'])->name('classrooms.students.store');
        Route::post('classes/{classroom}/membros/{user}/link', [Admin\StudentAccessController::class, 'issue'])->name('classrooms.members.link.store');
        Route::delete('classes/{classroom}/membros/{user}/link', [Admin\StudentAccessController::class, 'revoke'])->name('classrooms.members.link.destroy');

        Route::resource('series', Admin\SeriesController::class)
            ->parameters(['series' => 'series'])
            ->except(['show']);

        Route::resource('licoes', Admin\LessonController::class)
            ->parameters(['licoes' => 'lesson'])
            ->names('lessons')
            ->except(['show']);

        Route::post('licoes/{lesson}/status', Admin\LessonStatusController::class)->name('lessons.status');
        Route::put('licoes/{lesson}/ordem/{relation}', Admin\LessonOrderController::class)
            ->whereIn('relation', ['materials', 'readings', 'blocks'])
            ->name('lessons.reorder');

        Route::scopeBindings()->group(function () {
            Route::post('licoes/{lesson}/materiais', [Admin\LessonMaterialController::class, 'store'])->name('lessons.materials.store');
            // POST (e não PUT) para permitir envio de arquivo via multipart.
            Route::post('licoes/{lesson}/materiais/{material}', [Admin\LessonMaterialController::class, 'update'])->name('lessons.materials.update');
            Route::delete('licoes/{lesson}/materiais/{material}', [Admin\LessonMaterialController::class, 'destroy'])->name('lessons.materials.destroy');

            Route::post('licoes/{lesson}/blocos', [Admin\LessonBlockController::class, 'store'])->name('lessons.blocks.store');
            Route::put('licoes/{lesson}/blocos/{block}', [Admin\LessonBlockController::class, 'update'])->name('lessons.blocks.update');
            Route::delete('licoes/{lesson}/blocos/{block}', [Admin\LessonBlockController::class, 'destroy'])->name('lessons.blocks.destroy');

            Route::post('licoes/{lesson}/leituras', [Admin\LessonReadingController::class, 'store'])->name('lessons.readings.store');
            Route::put('licoes/{lesson}/leituras/{reading}', [Admin\LessonReadingController::class, 'update'])->name('lessons.readings.update');
            Route::delete('licoes/{lesson}/leituras/{reading}', [Admin\LessonReadingController::class, 'destroy'])->name('lessons.readings.destroy');
        });
    });

require __DIR__.'/settings.php';
