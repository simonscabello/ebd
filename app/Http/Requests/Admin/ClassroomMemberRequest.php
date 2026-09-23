<?php

namespace App\Http\Requests\Admin;

use App\Enums\ClassroomRole;
use App\Models\Classroom;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClassroomMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Classroom $classroom */
        $classroom = $this->route('classroom');

        if ($this->input('role') === ClassroomRole::Teacher->value) {
            return $this->user()->can('assignTeachers', $classroom);
        }

        return $this->user()->can('manageMembers', $classroom);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', Rule::enum(ClassroomRole::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['email' => 'e-mail', 'role' => 'papel'];
    }
}
