<?php

namespace App\Http\Requests\Admin;

use App\Enums\ContentAudience;
use App\Enums\LessonBlockKind;
use App\Enums\Weekday;
use App\Models\Lesson;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LessonBlockRequest extends FormRequest
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
            'kind' => ['required', Rule::enum(LessonBlockKind::class)],
            'audience' => ['nullable', Rule::enum(ContentAudience::class)],
            'title' => ['nullable', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:60000'],
            // Só conteúdo de aluno pode ser liberado em "Minha semana".
            'drip_weekday' => ['nullable', Rule::enum(Weekday::class), 'prohibited_if:audience,teacher'],
        ];
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

    /**
     * Sem público informado, usa o padrão do tipo (roteiro = professor, curiosidade = aluno...).
     *
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): mixed
    {
        $data = parent::validated($key, $default);

        if ($key === null && is_array($data)) {
            $data['audience'] ??= LessonBlockKind::from($data['kind'])->defaultAudience()->value;

            if ($data['audience'] === ContentAudience::Teacher->value) {
                $data['drip_weekday'] = null;
            }
        }

        return $data;
    }
}
