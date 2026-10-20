<?php

namespace App\Actions\Library;

use App\Models\Series;
use App\Models\SeriesVersion;
use App\Models\User;
use App\Support\Library\SeriesSnapshot;
use App\Support\OfficialLibrary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Publica a próxima versão de um estudo oficial. Os arquivos dos materiais
 * são copiados para a pasta da versão: apagar ou trocar o original depois
 * não quebra a versão nem as cópias feitas a partir dela.
 */
class PublishSeriesVersion
{
    /**
     * @throws ValidationException
     */
    public function handle(User $by, Series $series, ?string $notes): SeriesVersion
    {
        abort_unless(OfficialLibrary::isOfficial($series->classroom), 404);

        return DB::transaction(function () use ($by, $series, $notes) {
            // Trava a série: duas publicações ao mesmo tempo não pegam o mesmo
            // número, e o retrato é tirado já com a trava.
            Series::query()->whereKey($series->id)->lockForUpdate()->first();

            $snapshot = SeriesSnapshot::of($series);

            if ($snapshot['lessons'] === []) {
                throw ValidationException::withMessages(['version' => 'Publique ao menos uma lição antes.']);
            }

            $version = (int) SeriesVersion::query()->where('series_id', $series->id)->max('version') + 1;

            $copied = [];

            try {
                foreach ($snapshot['lessons'] as $l => $lesson) {
                    foreach ($lesson['materials'] as $m => $material) {
                        if ($material['file'] === null) {
                            continue;
                        }

                        $disk = (string) $material['file']['disk'];
                        $path = "library/{$series->id}/v{$version}/".Str::random(16).'-'.basename((string) $material['file']['path']);

                        if (! Storage::disk($disk)->copy((string) $material['file']['path'], $path)) {
                            throw ValidationException::withMessages(['version' => "Não foi possível copiar o arquivo \"{$material['title']}\"."]);
                        }

                        $copied[] = [$disk, $path];
                        $snapshot['lessons'][$l]['materials'][$m]['file']['path'] = $path;
                    }
                }

                return SeriesVersion::query()->create([
                    'series_id' => $series->id,
                    'version' => $version,
                    'notes' => $notes,
                    'snapshot' => $snapshot,
                    'published_by' => $by->id,
                ]);
            } catch (Throwable $e) {
                foreach ($copied as [$disk, $path]) {
                    Storage::disk($disk)->delete($path);
                }

                throw $e;
            }
        });
    }
}
