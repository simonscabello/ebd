<?php

namespace App\Actions\Lessons;

use App\Models\Classroom;
use App\Models\Lesson;
use Illuminate\Support\Str;

/**
 * Estratégia de slug das lições (URL pública /licoes/{slug}):
 *
 * 1. slug do título:                   a-santidade-de-deus
 * 2. se já existir, sufixo da classe:  a-santidade-de-deus-adultos
 * 3. se ainda existir, sufixo numérico: a-santidade-de-deus-adultos-2, -3...
 *
 * Lições excluídas (soft delete) continuam reservando o slug, para que um link
 * antigo nunca passe a apontar para outro conteúdo.
 */
class GenerateLessonSlug
{
    public function handle(string $title, Classroom $classroom, ?int $ignoreLessonId = null): string
    {
        $base = Str::of(Str::slug($title))->limit(150, '')->trim('-')->value() ?: 'licao';

        $candidates = [$base, "{$base}-{$classroom->slug}"];

        foreach ($candidates as $candidate) {
            if (! $this->exists($candidate, $ignoreLessonId)) {
                return $candidate;
            }
        }

        $suffix = 2;

        while ($this->exists("{$candidates[1]}-{$suffix}", $ignoreLessonId)) {
            $suffix++;
        }

        return "{$candidates[1]}-{$suffix}";
    }

    private function exists(string $slug, ?int $ignoreLessonId): bool
    {
        return Lesson::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreLessonId, fn ($query) => $query->whereKeyNot($ignoreLessonId))
            ->exists();
    }
}
