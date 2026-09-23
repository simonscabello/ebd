<?php

namespace App\Actions\Lessons;

use App\Enums\ContentAudience;
use App\Models\Lesson;
use App\Models\LessonBlock;

/**
 * Atualiza lessons.blocks_text (indexado na busca) com o texto dos blocos
 * voltados ao aluno. Blocos do professor nunca entram: o trecho destacado da
 * biblioteca os revelaria.
 */
class SyncLessonSearchText
{
    public function handle(Lesson $lesson): void
    {
        $text = $lesson->blocks()
            ->where('audience', ContentAudience::Student)
            ->get(['title', 'body'])
            ->map(fn (LessonBlock $b) => trim(($b->title ? $b->title."\n" : '').$b->body))
            ->implode("\n\n");

        $lesson->forceFill(['blocks_text' => $text !== '' ? $text : null])->saveQuietly();
    }
}
