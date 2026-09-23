<?php

namespace App\Http\Resources;

use App\Models\LessonBlock;
use App\Support\Markdown;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LessonBlock
 */
class LessonBlockResource extends JsonResource
{
    private bool $withSource = false;

    /** Inclui o Markdown original (formulários de edição). */
    public function withSource(): static
    {
        $this->withSource = true;

        return $this;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'kind_label' => $this->kind->label(),
            'audience' => $this->audience->value,
            'title' => $this->title,
            'display_title' => $this->displayTitle(),
            'body_html' => Markdown::toHtml($this->body),
            'drip_weekday' => $this->drip_weekday?->value,
            'drip_weekday_label' => $this->drip_weekday?->label(),
            'position' => $this->position,
            $this->mergeWhen($this->withSource, fn () => [
                'body' => $this->body,
            ]),
        ];
    }

    /**
     * @param  iterable<LessonBlock>  $blocks
     * @return list<array<int|string, mixed>>
     */
    public static function editable(iterable $blocks, Request $request): array
    {
        $items = [];

        foreach ($blocks as $block) {
            $items[] = self::make($block)->withSource()->resolve($request);
        }

        return $items;
    }
}
