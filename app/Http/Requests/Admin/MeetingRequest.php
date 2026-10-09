<?php

namespace App\Http\Requests\Admin;

use App\Concerns\MeetingValidationRules;
use App\Models\ClassMeeting;
use App\Models\Classroom;
use Illuminate\Foundation\Http\FormRequest;

class MeetingRequest extends FormRequest
{
    use MeetingValidationRules;

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
        return $this->meetingRules($this->classroom(), $this->meeting());
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->meetingAttributes();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->meetingMessages();
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
