<?php

namespace App\Http\Requests\Admin;

use App\Models\Classroom;
use App\Models\Series;
use Illuminate\Foundation\Http\FormRequest;

class SeriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $series = $this->route('series');

        if ($series instanceof Series) {
            return $this->user()->can('update', $series);
        }

        $classroom = Classroom::find($this->integer('classroom_id'));

        // Classe inexistente: deixa a validação responder com a mensagem adequada.
        return $classroom === null || $this->user()->can('manageContent', $classroom);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'classroom_id' => [$this->route('series') ? 'prohibited' : 'required', 'integer', 'exists:classrooms,id'],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'classroom_id' => 'classe',
            'title' => 'título',
            'description' => 'descrição',
            'starts_on' => 'início',
            'ends_on' => 'término',
        ];
    }
}
