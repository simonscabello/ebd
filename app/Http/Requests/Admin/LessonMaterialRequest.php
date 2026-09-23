<?php

namespace App\Http\Requests\Admin;

use App\Enums\MaterialType;
use App\Models\Lesson;
use App\Models\LessonMaterial;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validação de materiais. Para uploads conferimos:
 * - extensão do nome enviado (extensions),
 * - MIME real detectado pelo conteúdo (mimetypes),
 * - tamanho máximo por tipo (config/ebd.php).
 */
class LessonMaterialRequest extends FormRequest
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
        $type = $this->materialType();
        $creating = $this->existingMaterial() === null;

        $fileRules = ['prohibited'];

        if ($type?->acceptsUpload()) {
            $fileRules = [
                $creating && $type->requiresUpload() ? 'required' : 'nullable',
                'file',
                'max:'.$type->maxUploadKilobytes(),
                'extensions:'.implode(',', $type->allowedExtensions()),
                'mimetypes:'.implode(',', $type->allowedMimeTypes()),
            ];
        }

        return [
            'type' => [$creating ? 'required' : 'prohibited', Rule::enum(MaterialType::class)],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'url' => [
                $type?->requiresUrl() ? 'required' : 'nullable',
                'url:http,https',
                'max:2048',
            ],
            'file' => $fileRules,
            'is_primary' => ['boolean'],
        ];
    }

    /**
     * Áudio aceita arquivo OU link, mas precisa de um dos dois.
     */
    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $material = $this->existingMaterial();

                if ($this->materialType() === MaterialType::Audio
                    && ! $this->hasFile('file')
                    && blank($this->input('url'))
                    && ! $material?->hasFile()) {
                    $validator->errors()->add('file', 'Envie um arquivo de áudio ou informe um link.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => 'tipo',
            'title' => 'título',
            'description' => 'descrição',
            'url' => 'link',
            'file' => 'arquivo',
        ];
    }

    public function materialType(): ?MaterialType
    {
        $material = $this->existingMaterial();

        if ($material !== null) {
            return $material->type;
        }

        return MaterialType::tryFrom((string) $this->input('type'));
    }

    private function existingMaterial(): ?LessonMaterial
    {
        $material = $this->route('material');

        return $material instanceof LessonMaterial ? $material : null;
    }
}
