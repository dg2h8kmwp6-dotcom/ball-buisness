<?php

namespace App\Support;

use App\Models\BallType;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Stores admin-uploaded photos of real balls.
 *
 * The admin crops to a square in the browser and sends the crop rectangle in
 * source-image pixels; this crops and downsizes with GD, which ships with PHP,
 * so no image library dependency is needed.
 *
 * The stored photo is a plain square. Rounding is left to the presentation
 * layer — CSS on the storefront, a canvas clip in the bag animation — so the
 * same file works everywhere and the ball shape can change later.
 */
class BallPhoto
{
    /** Stored photos are downsized to this many pixels square. */
    public const SIZE = 512;

    /** Smallest crop we will accept, to keep uploads from looking mushy. */
    public const MIN_CROP = 160;

    /**
     * Crop, downsize, and store a photo for the given ball type, replacing
     * any photo it already had.
     *
     * @param  array{x: int, y: int, size: int}  $crop  in source-image pixels
     * @return string the path on the image disk
     */
    public function store(BallType $ballType, UploadedFile $file, array $crop): string
    {
        $source = $this->decode($file);

        try {
            $crop = $this->clampCrop($crop, imagesx($source), imagesy($source));
            $square = $this->cropToSquare($source, $crop);

            try {
                $path = $this->put($ballType, $square);
            } finally {
                imagedestroy($square);
            }
        } finally {
            imagedestroy($source);
        }

        $this->delete($ballType);

        $ballType->update(['image_path' => $path]);

        return $path;
    }

    /**
     * Remove the ball type's photo from disk, leaving the column alone.
     */
    public function delete(BallType $ballType): void
    {
        $existing = $ballType->getOriginal('image_path');

        if (filled($existing)) {
            $this->disk()->delete($existing);
        }
    }

    /**
     * @return \GdImage
     */
    protected function decode(UploadedFile $file)
    {
        $contents = file_get_contents($file->getRealPath());

        if ($contents === false) {
            throw new RuntimeException('The uploaded photo could not be read.');
        }

        $image = @imagecreatefromstring($contents);

        if ($image === false) {
            throw new RuntimeException('The uploaded photo is not a readable image.');
        }

        // Honour the camera's rotation flag, or phone photos arrive sideways.
        return $this->applyExifRotation($image, $file);
    }

    /**
     * @param  \GdImage  $image
     * @return \GdImage
     */
    protected function applyExifRotation($image, UploadedFile $file)
    {
        if (! function_exists('exif_read_data') || ! in_array($file->getMimeType(), ['image/jpeg', 'image/tiff'], true)) {
            return $image;
        }

        $exif = @exif_read_data($file->getRealPath());
        $orientation = $exif['Orientation'] ?? null;

        $degrees = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($degrees === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $degrees, 0);

        if ($rotated === false) {
            return $image;
        }

        imagedestroy($image);

        return $rotated;
    }

    /**
     * Keep the requested crop square and inside the image.
     *
     * @param  array{x: int, y: int, size: int}  $crop
     * @return array{x: int, y: int, size: int}
     */
    protected function clampCrop(array $crop, int $width, int $height): array
    {
        $maximum = min($width, $height);

        $size = (int) min(max($crop['size'], min(self::MIN_CROP, $maximum)), $maximum);
        $x = (int) min(max($crop['x'], 0), $width - $size);
        $y = (int) min(max($crop['y'], 0), $height - $size);

        return ['x' => $x, 'y' => $y, 'size' => $size];
    }

    /**
     * @param  \GdImage  $source
     * @param  array{x: int, y: int, size: int}  $crop
     * @return \GdImage
     */
    protected function cropToSquare($source, array $crop)
    {
        $side = min(self::SIZE, $crop['size']);

        $square = imagecreatetruecolor($side, $side);

        if ($square === false) {
            throw new RuntimeException('The photo could not be resized.');
        }

        // Preserve transparency for sources that have it (PNG cut-outs).
        imagealphablending($square, false);
        imagesavealpha($square, true);
        imagefill($square, 0, 0, imagecolorallocatealpha($square, 0, 0, 0, 127));
        imagealphablending($square, true);

        imagecopyresampled(
            $square,
            $source,
            0, 0,
            $crop['x'], $crop['y'],
            $side, $side,
            $crop['size'], $crop['size'],
        );

        return $square;
    }

    /**
     * @param  \GdImage  $square
     */
    protected function put(BallType $ballType, $square): string
    {
        $path = sprintf('ball-types/%s-%s.webp', $ballType->slug, Str::lower(Str::random(8)));

        $temporary = tempnam(sys_get_temp_dir(), 'ball');

        if ($temporary === false) {
            throw new RuntimeException('A temporary file could not be created for the photo.');
        }

        try {
            imagesavealpha($square, true);

            if (! imagewebp($square, $temporary, 88)) {
                throw new RuntimeException('The photo could not be encoded.');
            }

            $stream = fopen($temporary, 'rb');

            if ($stream === false) {
                throw new RuntimeException('The encoded photo could not be read back.');
            }

            try {
                $this->disk()->put($path, $stream);
            } finally {
                fclose($stream);
            }
        } finally {
            @unlink($temporary);
        }

        return $path;
    }

    protected function disk(): Filesystem
    {
        return Storage::disk(config('ballpit.image_disk'));
    }
}
