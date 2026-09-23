<?php

namespace App\Http\Requests\Admin;

use App\Models\ClassMeeting;
use App\Models\Classroom;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $meeting = $this->meeting();

        return $meeting
            ? $this->user()->can('update', $meeting)
            : $this->user()->can('manageContent', $this->classroom());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $classroom = $this->classroom();

        return [
            'held_on' => [
                'required', 'date',
                Rule::unique('class_meetings', 'held_on')
                    ->where('classroom_id', $classroom->id)
                    ->ignore($this->meeting()?->id),
            ],
            'lesson_id' => ['nullable', 'integer', Rule::exists('lessons', 'id')->where('classroom_id', $classroom->id)->whereNull('deleted_at')],
            'title' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'held_on' => 'data',
            'lesson_id' => 'lição',
            'title' => 'título',
            'notes' => 'anotações',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['held_on.unique' => 'Já existe um encontro desta classe nesta data.'];
    }

    public function meeting(): ?ClassMeeting
    {
        $meeting = $this->route('meeting');

        return $meeting instanceof ClassMeeting ? $meeting : null;
    }

    public function classroom(): Classroom
    {
        $classroom = $this->route('classroom');

        if ($classroom instanceof Classroom) {
            return $classroom;
        }

        return $this->meeting()->classroom ?? abort(404);
    }
}
