<?php

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;

class ChurchSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('own-platform') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'helper_enabled' => ['required', 'boolean'],
            'helper_daily_limit' => ['required', 'integer', 'between:1,100'],
            'audio_enabled' => ['required', 'boolean'],
            'audio_monthly_limit' => ['required', 'integer', 'between:1,100'],
            'shows_bible_text' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'helper_daily_limit' => 'limite de dúvidas por dia',
            'audio_monthly_limit' => 'limite de áudios por mês',
        ];
    }
}
