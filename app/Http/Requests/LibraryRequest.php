<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Parâmetros em português na URL (?q=&classe=&serie=&ano=) para links amigáveis.
 */
class LibraryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'classe' => ['nullable', 'integer', 'min:1'],
            'serie' => ['nullable', 'integer', 'min:1'],
            'ano' => ['nullable', 'integer', 'between:2000,2100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array{q: string|null, classroom: int|null, series: int|null, year: int|null}
     */
    public function filters(): array
    {
        return [
            'q' => $this->validated('q'),
            'classroom' => $this->integerOrNull('classe'),
            'series' => $this->integerOrNull('serie'),
            'year' => $this->integerOrNull('ano'),
        ];
    }

    private function integerOrNull(string $key): ?int
    {
        $value = $this->validated($key);

        return $value === null ? null : (int) $value;
    }
}
