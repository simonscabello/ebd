<?php

namespace App\Http\Requests\Admin;

use App\Enums\Weekday;
use App\Models\Lesson;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LessonReadingRequest extends FormRequest
{
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
        return [
            'weekday' => ['nullable', Rule::enum(Weekday::class)],
            'reference' => ['required', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['weekday' => 'dia', 'reference' => 'leitura', 'notes' => 'orientação'];
    }
}
