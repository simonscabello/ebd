<?php

namespace App\Http\Requests\Admin;

use App\Concerns\LessonValidationRules;
use App\Models\Lesson;
use Illuminate\Foundation\Http\FormRequest;

class LessonBlockRequest extends FormRequest
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
        $rules = $this->lessonBlockRules();

        // Só conteúdo de aluno pode ser liberado em "Minha semana".
        $rules['drip_weekday'][] = 'prohibited_if:audience,teacher';

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'kind' => 'tipo',
            'audience' => 'público',
            'title' => 'título',
            'body' => 'conteúdo',
            'drip_weekday' => 'dia da semana',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['drip_weekday.prohibited_if' => 'Conteúdo só do professor não aparece em "Minha semana".'];
    }
}
