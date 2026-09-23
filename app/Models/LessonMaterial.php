<?php

namespace App\Models;

use App\Enums\MaterialType;
use App\Models\Concerns\HasPosition;
use Database\Factories\LessonMaterialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $lesson_id
 * @property MaterialType $type
 * @property string $title
 * @property string|null $description
 * @property string|null $url
 * @property string|null $disk
 * @property string|null $path
 * @property string|null $original_name
 * @property string|null $mime_type
 * @property int|null $size_bytes
 * @property bool $is_primary
 * @property int $position
 * @property-read Lesson $lesson
 */
#[Fillable(['type', 'title', 'description', 'url', 'is_primary', 'position'])]
#[Hidden(['disk', 'path'])]
class LessonMaterial extends Model
{
    /** @use HasFactory<LessonMaterialFactory> */
    use HasFactory, HasPosition;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MaterialType::class,
            'is_primary' => 'boolean',
            'position' => 'integer',
            'size_bytes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function hasFile(): bool
    {
        return $this->disk !== null && $this->path !== null;
    }
}
