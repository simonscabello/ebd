<?php

namespace App\Http\Requests\Admin;

use App\Enums\Weekday;
use App\Models\Classroom;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClassroomRequest extends FormRequest
{
    public function authorize(): bool
    {
        $classroom = $this->route('classroom');

        return $classroom instanceof Classroom
            ? $this->user()->can('update', $classroom)
            : $this->user()->can('create', Classroom::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
            'position' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'meeting_weekday' => ['sometimes', Rule::enum(Weekday::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'nome', 'description' => 'descrição', 'position' => 'ordem', 'meeting_weekday' => 'dia do encontro'];
    }
}
