<?php

namespace App\Http\Controllers;

use App\Enums\LessonVisibility;
use App\Models\Church;
use App\Models\Lesson;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Feed RSS de podcast da igreja (Spotify, Apple Podcasts...): as lições
 * públicas que têm áudio do estudo. Lição privada fica de fora, porque o
 * agregador não tem login para baixar o áudio.
 */
class PodcastController extends Controller
{
    public function __invoke(Church $church): Response
    {
        abort_if($church->is_official, 404);

        $lessons = Lesson::query()
            ->visible()
            ->where('visibility', LessonVisibility::Public)
            ->whereNotNull('audio_path')
            ->whereIn('classroom_id', $church->classrooms()->select('id'))
            ->with('series')
            ->latest('published_at')
            ->get();

        return response()
            ->view('podcast.feed', [
                'church' => $church,
                'lessons' => $lessons,
                // O agregador exige o tamanho do arquivo; sem o arquivo, 0 em vez de quebrar o feed.
                'sizes' => $lessons->mapWithKeys(fn (Lesson $lesson) => [
                    $lesson->id => rescue(fn () => Storage::disk((string) $lesson->audio_disk)->size((string) $lesson->audio_path), 0, false),
                ]),
            ])
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
