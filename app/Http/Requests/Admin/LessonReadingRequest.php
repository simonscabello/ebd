<?php

namespace App\Http\Requests\Admin;

use App\Concerns\LessonValidationRules;
use App\Models\Lesson;
use Illuminate\Foundation\Http\FormRequest;

class LessonReadingRequest extends FormRequest
{
    use LessonValidationRules;

    public function authorize(): bool
    {
        /** @var Lesson $lesson */
        $lesson = $this->route('lesson');

        return $this->user()->can('update', $lesson);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->lessonReadingRules();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['weekday' => 'dia', 'reference' => 'leitura', 'notes' => 'orientação'];
    }
}
