<?php

namespace App\Models;

use App\Enums\BibleBook;
use Illuminate\Database\Eloquent\Model;

/**
 * Um versículo do texto bíblico importado (`php artisan bible:import`).
 *
 * @property int $id
 * @property BibleBook $book
 * @property int $chapter
 * @property int $verse
 * @property string $text
 */
class BibleVerse extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = ['book', 'chapter', 'verse', 'text'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'book' => BibleBook::class,
            'chapter' => 'integer',
            'verse' => 'integer',
        ];
    }
}
