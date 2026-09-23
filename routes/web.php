<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\MaterialFileController;
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

Route::get('materiais/{material}/arquivo', MaterialFileController::class)
    ->middleware('throttle:downloads')
    ->name('materials.file');

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

        Route::resource('classes', Admin\ClassroomController::class)
            ->parameters(['classes' => 'classroom'])
            ->names('classrooms')
            ->except(['show', 'destroy']);

        Route::get('classes/{classroom}/membros', [Admin\ClassroomMemberController::class, 'index'])->name('classrooms.members.index');
        Route::post('classes/{classroom}/membros', [Admin\ClassroomMemberController::class, 'store'])->name('classrooms.members.store');
        Route::delete('classes/{classroom}/membros/{user}', [Admin\ClassroomMemberController::class, 'destroy'])->name('classrooms.members.destroy');

        Route::resource('series', Admin\SeriesController::class)
            ->parameters(['series' => 'series'])
            ->except(['show']);

        Route::resource('licoes', Admin\LessonController::class)
            ->parameters(['licoes' => 'lesson'])
            ->names('lessons')
            ->except(['show']);

        Route::post('licoes/{lesson}/status', Admin\LessonStatusController::class)->name('lessons.status');
        Route::put('licoes/{lesson}/ordem/{relation}', Admin\LessonOrderController::class)
            ->whereIn('relation', ['materials', 'questions', 'readings'])
            ->name('lessons.reorder');

        Route::scopeBindings()->group(function () {
            Route::post('licoes/{lesson}/materiais', [Admin\LessonMaterialController::class, 'store'])->name('lessons.materials.store');
            // POST (e não PUT) para permitir envio de arquivo via multipart.
            Route::post('licoes/{lesson}/materiais/{material}', [Admin\LessonMaterialController::class, 'update'])->name('lessons.materials.update');
            Route::delete('licoes/{lesson}/materiais/{material}', [Admin\LessonMaterialController::class, 'destroy'])->name('lessons.materials.destroy');

            Route::post('licoes/{lesson}/perguntas', [Admin\LessonQuestionController::class, 'store'])->name('lessons.questions.store');
            Route::put('licoes/{lesson}/perguntas/{question}', [Admin\LessonQuestionController::class, 'update'])->name('lessons.questions.update');
            Route::delete('licoes/{lesson}/perguntas/{question}', [Admin\LessonQuestionController::class, 'destroy'])->name('lessons.questions.destroy');

            Route::post('licoes/{lesson}/leituras', [Admin\LessonReadingController::class, 'store'])->name('lessons.readings.store');
            Route::put('licoes/{lesson}/leituras/{reading}', [Admin\LessonReadingController::class, 'update'])->name('lessons.readings.update');
            Route::delete('licoes/{lesson}/leituras/{reading}', [Admin\LessonReadingController::class, 'destroy'])->name('lessons.readings.destroy');
        });
    });

require __DIR__.'/settings.php';
