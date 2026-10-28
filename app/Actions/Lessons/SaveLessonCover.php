<?php

namespace App\Actions\Lessons;

use App\Models\Lesson;
use GdImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Capa da lição, enviada pela gestão ou pelo servidor MCP. Qualquer imagem
 * vira um JPEG quadrado (recorte no centro) do tamanho de config/ebd.php, com
 * cerca de 100 KB, mais uma miniatura de ~15 KB para os cards; as duas ficam
 * no disco dos materiais e a capa anterior é apagada.
 */
class SaveLessonCover
{
    /** @param  string  $bytes  conteúdo do arquivo (JPEG, PNG ou WebP) */
    public function handle(Lesson $lesson, string $bytes, string $field = 'cover'): Lesson
    {
        $source = $this->read($bytes, $field);

        $disk = (string) config('ebd.materials.disk');
        $path = config('ebd.covers.directory').'/'.$lesson->id.'/'.Str::random(32).'.jpg';
        $options = ['visibility' => 'private', 'ContentType' => 'image/jpeg'];

        if (! Storage::disk($disk)->put($path, $this->squareJpeg($source, max(1, (int) config('ebd.covers.size'))), $options)
            || ! Storage::disk($disk)->put(self::thumbnailPath($path), $this->squareJpeg($source, max(1, (int) config('ebd.covers.thumbnail_size'))), $options)) {
            Storage::disk($disk)->delete([$path, self::thumbnailPath($path)]);

            throw ValidationException::withMessages([$field => 'Não foi possível salvar a capa. Tente de novo.']);
        }

        $previous = $lesson->hasCover() ? [$lesson->cover_disk, $lesson->cover_path] : null;

        $lesson->forceFill(['cover_disk' => $disk, 'cover_path' => $path, 'cover_updated_at' => now()])->save();

        if ($previous !== null) {
            self::deleteFiles((string) $previous[0], (string) $previous[1]);
        }

        return $lesson;
    }

    public function remove(Lesson $lesson): Lesson
    {
        if ($lesson->hasCover()) {
            self::deleteFiles((string) $lesson->cover_disk, (string) $lesson->cover_path);
            $lesson->forceFill(['cover_disk' => null, 'cover_path' => null, 'cover_updated_at' => now()])->save();
        }

        return $lesson;
    }

    /** A miniatura fica ao lado da capa, com o mesmo nome e "-p". */
    public static function thumbnailPath(string $path): string
    {
        return (string) preg_replace('/\.jpg$/', '-p.jpg', $path);
    }

    /**
     * Copia a capa e a miniatura (biblioteca oficial). Devolve os caminhos
     * copiados, para apagar se algo falhar depois.
     *
     * @return list<string>|null null quando a cópia falhou
     */
    public static function copyFiles(string $disk, string $from, string $to): ?array
    {
        $storage = Storage::disk($disk);

        if (! $storage->copy($from, $to)) {
            return null;
        }

        // Capa sem miniatura não impede a cópia: o card usa a capa grande.
        if ($storage->exists(self::thumbnailPath($from))) {
            $storage->copy(self::thumbnailPath($from), self::thumbnailPath($to));
        }

        return [$to, self::thumbnailPath($to)];
    }

    private static function deleteFiles(string $disk, string $path): void
    {
        Storage::disk($disk)->delete([$path, self::thumbnailPath($path)]);
    }

    private function read(string $bytes, string $field): GdImage
    {
        $info = @getimagesizefromstring($bytes);

        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw ValidationException::withMessages([$field => 'Envie uma imagem JPEG, PNG ou WebP.']);
        }

        [$width, $height] = $info;

        // Imagem pequena demais fica borrada no Spotify e na página da lição.
        if (min($width, $height) < 600) {
            throw ValidationException::withMessages([$field => 'A imagem precisa ter pelo menos 600 × 600 pixels.']);
        }

        // Um PNG de poucos KB pode descomprimir em gigabytes na memória.
        if (max($width, $height) > 6000) {
            throw ValidationException::withMessages([$field => 'A imagem pode ter no máximo 6000 pixels de lado.']);
        }

        $source = imagecreatefromstring($bytes);

        if ($source === false) {
            throw ValidationException::withMessages([$field => 'Não foi possível ler a imagem.']);
        }

        return $source;
    }

    /** @param  positive-int  $size */
    private function squareJpeg(GdImage $source, int $size): string
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);
        $target = imagecreatetruecolor($size, $size);

        // Fundo branco sob PNG transparente (o JPEG não tem transparência).
        imagefill($target, 0, 0, (int) imagecolorallocate($target, 255, 255, 255));
        imagecopyresampled($target, $source, 0, 0, intdiv($width - $side, 2), intdiv($height - $side, 2), $size, $size, $side, $side);

        // Progressivo e qualidade 80: cerca de 100 KB a 1400 px, sem perda visível.
        imageinterlace($target, true);
        ob_start();
        imagejpeg($target, null, 80);

        return (string) ob_get_clean();
    }
}
