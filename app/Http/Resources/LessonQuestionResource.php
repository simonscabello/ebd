<?php

namespace App\Http\Resources;

use App\Models\LessonQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LessonQuestion
 */
class LessonQuestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'body' => $this->body,
            // Gabarito de perguntas de revisão: a página esconde até a pessoa responder.
            'answer' => $this->isReview() ? $this->answer : null,
            'position' => $this->position,
        ];
    }
}
