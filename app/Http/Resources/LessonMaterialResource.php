<?php

namespace App\Http\Resources;

use App\Enums\MaterialType;
use App\Models\LessonMaterial;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Number;

/**
 * @mixin LessonMaterial
 */
class LessonMaterialResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $hasFile = $this->hasFile();

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'audience' => $this->audience->value,
            'title' => $this->title,
            'description' => $this->description,
            'url' => $hasFile ? null : $this->url,
            'embed_url' => $this->type === MaterialType::Video ? self::videoEmbedUrl($this->url) : null,
            'is_primary' => $this->is_primary,
            'position' => $this->position,
            'file' => $hasFile ? [
                'name' => $this->original_name,
                'extension' => strtoupper(pathinfo((string) $this->original_name, PATHINFO_EXTENSION)),
                'mime_type' => $this->mime_type,
                'size' => $this->size_bytes ? Number::fileSize($this->size_bytes, precision: 1) : null,
                'open_url' => route('materials.file', $this->resource),
                'download_url' => route('materials.file', [$this->resource, 'download' => 1]),
            ] : null,
        ];
    }

    /**
     * Converte links do YouTube em URL de incorporação (domínio sem cookies).
     * Apenas o ID validado é reaproveitado, nunca a URL original.
     */
    public static function videoEmbedUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $pattern = '~^https?://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~';

        if (preg_match($pattern, $url, $matches) === 1) {
            return "https://www.youtube-nocookie.com/embed/{$matches[1]}";
        }

        return null;
    }
}
