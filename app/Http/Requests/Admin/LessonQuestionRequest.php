<?php

namespace App\Http\Requests\Admin;

use App\Enums\QuestionKind;
use App\Models\Lesson;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LessonQuestionRequest extends FormRequest
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
            'kind' => ['nullable', Rule::enum(QuestionKind::class)],
            'body' => ['required', 'string', 'max:1000'],
            // Pergunta de revisão precisa de gabarito.
            'answer' => ['nullable', 'required_if:kind,review', 'string', 'max:4000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['body' => 'pergunta', 'answer' => 'gabarito', 'kind' => 'tipo'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['answer.required_if' => 'Informe o gabarito da pergunta de revisão.'];
    }

    /**
     * Reflexão não guarda gabarito; tipo ausente vira reflexão.
     *
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): mixed
    {
        $data = parent::validated($key, $default);

        if ($key === null && is_array($data)) {
            // Na edição sem tipo informado, mantém o tipo atual.
            if (! isset($data['kind']) && $this->route('question') !== null) {
                return $data;
            }

            $data['kind'] ??= QuestionKind::Reflection->value;

            if ($data['kind'] === QuestionKind::Reflection->value) {
                $data['answer'] = null;
            }
        }

        return $data;
    }
}
