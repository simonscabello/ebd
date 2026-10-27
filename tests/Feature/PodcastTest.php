<?php

namespace Tests\Feature;

use App\Enums\LessonVisibility;
use App\Models\Church;
use App\Models\Classroom;
use App\Models\Lesson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Feed RSS do podcast da igreja: só lições públicas com áudio, da própria igreja.
 */
class PodcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_lists_public_lessons_with_audio_of_the_church(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('audio/jonas.mp3', str_repeat('x', 1234));

        $classroom = Classroom::factory()->create();
        $audio = ['audio_disk' => 'local', 'audio_path' => 'audio/jonas.mp3', 'audio_duration' => 600, 'audio_generated_at' => now()];

        Lesson::factory()->for($classroom)->published()->create(['number' => 3, 'title' => 'O sinal de Jonas', ...$audio]);
        Lesson::factory()->for($classroom)->published()->create(['title' => 'Sem áudio']);
        Lesson::factory()->for($classroom)->published()->create(['title' => 'Só da classe', 'visibility' => LessonVisibility::Members, ...$audio]);
        Lesson::factory()->for($classroom)->create(['title' => 'Rascunho', ...$audio]);
        Lesson::factory()->for(Classroom::factory()->for(Church::factory()))->published()->create(['title' => 'Outra igreja', ...$audio]);

        $response = $this->get(route('churches.podcast', $classroom->church))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');

        $xml = simplexml_load_string($response->getContent());
        $this->assertSame(['Lição 3 — O sinal de Jonas'], array_map('strval', $xml->xpath('//item/title')));
        $this->assertSame('1234', (string) $xml->channel->item->enclosure['length']);
        $this->assertSame($classroom->church->name, (string) $xml->channel->title);
    }

    public function test_official_library_has_no_feed(): void
    {
        $church = Church::factory()->create();
        $church->forceFill(['is_official' => true])->save();

        $this->get(route('churches.podcast', $church))->assertNotFound();
    }
}
