<?php

namespace App\Console\Commands;

use App\Actions\Lessons\SaveLessonCover;
use App\Models\Lesson;
use App\Support\Covers\PaperCutout;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Gera o recorte (capa sem o fundo papel) das capas que já existem. Rodar
 * depois do deploy; rodar de novo só refaz os recortes.
 *
 * Com --dry-run não mexe nas lições: grava os recortes em PNG em
 * storage/app/private/recortes-preview/ para conferir.
 */
#[Signature('ebd:capas-recorte {--serie= : id ou slug da série} {--licao= : id da lição} {--dry-run : Só gera as prévias}')]
#[Description('Gera o recorte sem fundo papel das capas das lições')]
class GenerateCoverCutouts extends Command
{
    public function handle(PaperCutout $cutout): int
    {
        $series = $this->option('serie');
        $lessons = Lesson::query()->whereNotNull('cover_path')
            ->when($series, fn ($q) => $q->whereHas('series', fn ($s) => is_numeric($series) ? $s->whereKey((int) $series) : $s->where('slug', $series)))
            ->when($this->option('licao'), fn ($q, $id) => $q->whereKey((int) $id))
            ->orderBy('series_id')->inSequence()->get();

        foreach ($lessons as $lesson) {
            $bytes = Storage::disk((string) $lesson->cover_disk)->get((string) $lesson->cover_path);
            $image = $bytes ? @imagecreatefromstring($bytes) : false;
            $result = $image === false
                ? ['image' => null, 'note' => 'capa ilegível']
                : $cutout->make($image, max(1, (int) config('ebd.covers.cutout_size')));

            if ($this->option('dry-run')) {
                if ($result['image'] !== null) {
                    ob_start();
                    imagepng($result['image'], null, 9);
                    Storage::disk('local')->put("recortes-preview/{$lesson->id}-{$lesson->slug}.png", (string) ob_get_clean());
                }
            } elseif ($image !== false) {
                $saved = $result['image'] !== null && SaveLessonCover::storeCutout($result['image'], (string) $lesson->cover_disk, (string) $lesson->cover_path);

                if (! $saved) {
                    Storage::disk((string) $lesson->cover_disk)->delete(SaveLessonCover::cutoutPath((string) $lesson->cover_path));
                }
                // Sem mexer em updated_at: a lição não mudou para quem estuda.
                Lesson::query()->whereKey($lesson->id)->update(['cover_cutout' => $saved]);
            }

            $this->line(sprintf('%d %s: %s (%s)', $lesson->id, $lesson->displayTitle(), $result['image'] ? 'recorte ok' : 'sem recorte', $result['note']));
        }

        return self::SUCCESS;
    }
}
