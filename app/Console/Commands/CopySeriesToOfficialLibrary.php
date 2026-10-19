<?php

namespace App\Console\Commands;

use App\Actions\Series\CreateSeries;
use App\Models\Series;
use App\Models\User;
use App\Support\Library\LessonContentCopier;
use App\Support\Library\SeriesSnapshot;
use App\Support\OfficialLibrary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Leva um estudo de uma turma para a biblioteca oficial, por cópia: a
 * original continua onde está. Todas as lições chegam em rascunho, para o
 * dono revisar antes de publicar a versão 1.
 */
class CopySeriesToOfficialLibrary extends Command
{
    protected $signature = 'didache:copiar-para-biblioteca {series : id da série}';

    protected $description = 'Copia uma série de uma turma para a biblioteca oficial (lições em rascunho)';

    public function handle(CreateSeries $createSeries, LessonContentCopier $copier): int
    {
        $series = Series::query()->with('classroom.church')->findOrFail((int) $this->argument('series'));

        if ($series->classroom->church->is_official) {
            $this->error('Esta série já está na biblioteca oficial.');

            return self::FAILURE;
        }

        $owner = User::query()->where('is_admin', true)->orderBy('id')->first();

        if ($owner === null) {
            $this->error('Não há dono do sistema (is_admin) para assinar as lições.');

            return self::FAILURE;
        }

        $snapshot = SeriesSnapshot::of($series, includeDrafts: true);
        $count = count($snapshot['lessons']);

        $this->warn('Antes de publicar a versão, confira se o texto não cola versículos da NAA nem trechos da revista: a biblioteca é aberta a todas as igrejas.');

        if (! $this->confirm("Copiar \"{$series->title}\" ({$count} ".($count === 1 ? 'lição' : 'lições').') para a biblioteca oficial?')) {
            return self::FAILURE;
        }

        $library = OfficialLibrary::classroom();

        try {
            $copy = DB::transaction(function () use ($createSeries, $copier, $snapshot, $library, $owner) {
                $copy = $createSeries->handle($library, ['title' => $snapshot['title'], 'description' => $snapshot['description']]);

                foreach ($snapshot['lessons'] as $lesson) {
                    $copier->copy($lesson, $copy, $library, $owner, null);
                }

                return $copy;
            });
        } catch (Throwable $e) {
            $copier->discardFiles();

            throw $e;
        }

        $this->info("Série copiada para a biblioteca (id {$copy->id}). Revise, publique as lições e depois publique a versão 1.");

        return self::SUCCESS;
    }
}
