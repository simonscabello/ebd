<?php

namespace App\Enums;

enum MaterialType: string
{
    case Pdf = 'pdf';
    case File = 'file';
    case Link = 'link';
    case Video = 'video';
    case Audio = 'audio';
    case Reference = 'reference';

    public function label(): string
    {
        return match ($this) {
            self::Pdf => 'PDF',
            self::File => 'Arquivo',
            self::Link => 'Link',
            self::Video => 'Vídeo',
            self::Audio => 'Áudio',
            self::Reference => 'Referência bibliográfica',
        };
    }

    /** Tipos em que o arquivo enviado é obrigatório. */
    public function requiresUpload(): bool
    {
        return in_array($this, [self::Pdf, self::File], true);
    }

    /** Tipos que aceitam arquivo enviado (como alternativa à URL, no caso do áudio). */
    public function acceptsUpload(): bool
    {
        return in_array($this, [self::Pdf, self::File, self::Audio], true);
    }

    /** Tipos em que a URL é obrigatória. */
    public function requiresUrl(): bool
    {
        return in_array($this, [self::Link, self::Video], true);
    }

    /**
     * Extensões aceitas no upload. A validação também confere o MIME real do arquivo.
     *
     * @return list<string>
     */
    public function allowedExtensions(): array
    {
        return match ($this) {
            self::Pdf => ['pdf'],
            self::File => ['pdf', 'doc', 'docx', 'odt', 'ppt', 'pptx', 'odp', 'xls', 'xlsx', 'txt', 'epub', 'jpg', 'jpeg', 'png', 'webp'],
            self::Audio => ['mp3', 'm4a', 'ogg', 'oga', 'wav'],
            default => [],
        };
    }

    /**
     * MIME types aceitos (detectados pelo conteúdo do arquivo, não pelo nome).
     *
     * @return list<string>
     */
    public function allowedMimeTypes(): array
    {
        return match ($this) {
            self::Pdf => ['application/pdf'],
            self::File => [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.oasis.opendocument.text',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'application/vnd.oasis.opendocument.presentation',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'text/plain',
                'application/epub+zip',
                'image/jpeg',
                'image/png',
                'image/webp',
                // Documentos Office são ZIPs e alguns ambientes os detectam assim.
                'application/zip',
            ],
            self::Audio => ['audio/mpeg', 'audio/mp4', 'audio/x-m4a', 'audio/ogg', 'audio/wav', 'audio/x-wav', 'audio/vnd.wave'],
            default => [],
        };
    }

    /** Tamanho máximo do upload em KB. */
    public function maxUploadKilobytes(): int
    {
        return match ($this) {
            self::Audio => (int) config('ebd.materials.max_audio_kb'),
            default => (int) config('ebd.materials.max_upload_kb'),
        };
    }
}
