<?php

namespace App\Support;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Converte fotos de produto para WebP em dois tamanhos:
 * - principal (até 1200px no lado maior) para a página do produto;
 * - miniatura (até 480px) para cards da vitrine.
 * Corrige a rotação EXIF de fotos de celular e nunca amplia imagens pequenas.
 */
class ImageOptimizer
{
    public const FULL = 1200;

    public const THUMB = 480;

    public const QUALITY = 82;

    /**
     * @return array{path: string, thumb_path: string}
     */
    public function storeProductImage(UploadedFile $file, string $directory, string $disk = 'public'): array
    {
        $source = $this->load($file);

        try {
            $name = Str::uuid()->toString();
            $path = "{$directory}/{$name}.webp";
            $thumb = "{$directory}/{$name}-thumb.webp";

            Storage::disk($disk)->put($path, $this->encode($source, self::FULL));
            Storage::disk($disk)->put($thumb, $this->encode($source, self::THUMB));

            return ['path' => $path, 'thumb_path' => $thumb];
        } finally {
            imagedestroy($source);
        }
    }

    private function load(UploadedFile $file): GdImage
    {
        $contents = file_get_contents($file->getRealPath());
        $image = $contents !== false ? @imagecreatefromstring($contents) : false;

        if (! $image instanceof GdImage) {
            throw new RuntimeException('Não foi possível ler a imagem enviada.');
        }

        return $this->applyExifOrientation($image, $file);
    }

    private function applyExifOrientation(GdImage $image, UploadedFile $file): GdImage
    {
        if (! function_exists('exif_read_data') || ! in_array($file->getMimeType(), ['image/jpeg', 'image/jpg'], true)) {
            return $image;
        }

        $orientation = (int) (@exif_read_data($file->getRealPath())['Orientation'] ?? 1);

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => null,
        };

        if ($rotated instanceof GdImage) {
            imagedestroy($image);

            return $rotated;
        }

        return $image;
    }

    private function encode(GdImage $source, int $maxSide): string
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, $maxSide / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        ob_start();
        imagewebp($canvas, null, self::QUALITY);
        $data = (string) ob_get_clean();
        imagedestroy($canvas);

        return $data;
    }
}
