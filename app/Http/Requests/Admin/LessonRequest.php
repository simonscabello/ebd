<?php

namespace App\Http\Requests\Admin;

use App\Enums\ClassroomRole;
use App\Enums\LessonVisibility;
use App\Models\Classroom;
use App\Models\Lesson;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        $lesson = $this->route('lesson');

        if ($lesson instanceof Lesson) {
            return $this->user()->can('update', $lesson);
        }

        $classroom = $this->classroom();

        return $classroom === null || $this->user()->can('manageContent', $classroom);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $lesson = $this->route('lesson');
        $classroom = $this->classroom();

        return [
            'classroom_id' => [$lesson ? 'prohibited' : 'required', 'integer', 'exists:classrooms,id'],
            'series_id' => ['nullable', 'integer', Rule::exists('series', 'id')->where('classroom_id', $classroom?->id)],
            'title' => ['required', 'string', 'max:180'],
            'slug' => [
                'nullable', 'string', 'max:200', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('lessons', 'slug')->ignore($lesson?->id),
            ],
            'summary' => ['nullable', 'string', 'max:2000'],
            'scheduled_for' => ['nullable', 'date'],
            'bible_reference' => ['nullable', 'string', 'max:120'],
            'bible_text' => ['nullable', 'string', 'max:10000'],
            'content' => ['nullable', 'string', 'max:100000'],
            'teacher_notes' => ['nullable', 'string', 'max:20000'],
            'visibility' => ['required', Rule::enum(LessonVisibility::class)],
            'author_ids' => ['nullable', 'array', 'max:10'],
            'author_ids.*' => ['integer', Rule::in($this->eligibleAuthorIds($classroom))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'classroom_id' => 'classe',
            'series_id' => 'série',
            'title' => 'título',
            'summary' => 'resumo',
            'scheduled_for' => 'data da aula',
            'bible_reference' => 'texto bíblico',
            'bible_text' => 'texto da passagem',
            'content' => 'conteúdo',
            'teacher_notes' => 'notas do professor',
            'visibility' => 'visibilidade',
            'author_ids.*' => 'autor',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'Use apenas letras minúsculas, números e hífens no endereço.',
        ];
    }

    public function classroom(): ?Classroom
    {
        $lesson = $this->route('lesson');

        if ($lesson instanceof Lesson) {
            return $lesson->classroom;
        }

        return Classroom::find($this->integer('classroom_id'));
    }

    /**
     * Autores possíveis: professores da classe e a própria pessoa que edita.
     *
     * @return list<int>
     */
    private function eligibleAuthorIds(?Classroom $classroom): array
    {
        $ids = $classroom
            ? $classroom->members()->wherePivot('role', ClassroomRole::Teacher->value)->pluck('users.id')->all()
            : [];

        $ids[] = $this->user()->id;

        return array_values(array_unique(array_map('intval', $ids)));
    }
}
