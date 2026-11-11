<?php

declare(strict_types=1);

namespace App\Application\Shared\Exceptions;

use RuntimeException;

/**
 * Téléversement refusé par MediaUploader (type réel hors liste blanche, poids dépassé, image illisible…).
 * Le message est déjà traduit (lang/fr/uploads.php) : il peut être affiché tel quel ou renvoyé par une règle de validation.
 * `reason` est un code stable pour les tests et les journaux.
 */
final class MediaRejected extends RuntimeException
{
    public const REASON_UNREADABLE = 'unreadable';

    public const REASON_TYPE = 'type';

    public const REASON_TOO_LARGE = 'too_large';

    public const REASON_CORRUPT_IMAGE = 'corrupt_image';

    public const REASON_TOO_MANY_PIXELS = 'too_many_pixels';

    public const REASON_NOT_A_FILE = 'not_a_file';

    /**
     * @param  array<string, string|int>  $replace
     */
    private function __construct(
        public readonly string $reason,
        array $replace = [],
        public readonly ?string $detectedMime = null,
    ) {
        parent::__construct(__('uploads.rejected.'.$reason, $replace));
    }

    public static function unreadable(): self
    {
        return new self(self::REASON_UNREADABLE);
    }

    public static function notAFile(): self
    {
        return new self(self::REASON_NOT_A_FILE);
    }

    /**
     * @param  list<string>  $allowedMimes
     */
    public static function type(string $detectedMime, array $allowedMimes): self
    {
        $labels = array_values(array_unique(array_map(
            static fn (string $mime): string => (string) __('uploads.types.'.$mime) === 'uploads.types.'.$mime ? $mime : (string) __('uploads.types.'.$mime),
            $allowedMimes,
        )));

        return new self(self::REASON_TYPE, ['types' => implode(', ', $labels)], $detectedMime);
    }

    public static function tooLarge(int $sizeBytes, int $maxKb): self
    {
        return new self(self::REASON_TOO_LARGE, [
            'size' => self::humanSize($sizeBytes),
            'max' => self::humanSize($maxKb * 1024),
        ]);
    }

    public static function corruptImage(): self
    {
        return new self(self::REASON_CORRUPT_IMAGE);
    }

    public static function tooManyPixels(int $width, int $height): self
    {
        return new self(self::REASON_TOO_MANY_PIXELS, ['width' => $width, 'height' => $height]);
    }

    /**
     * « 4,8 Mo » ou « 512 Ko », au format français.
     */
    public static function humanSize(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            $value = $bytes / (1024 * 1024);

            return (string) __('uploads.units.mb', ['value' => self::formatNumber($value)]);
        }

        return (string) __('uploads.units.kb', ['value' => self::formatNumber($bytes / 1024)]);
    }

    private static function formatNumber(float $value): string
    {
        $rounded = round($value, 1);

        return $rounded === floor($rounded)
            ? number_format($rounded, 0, ',', ' ')
            : number_format($rounded, 1, ',', ' ');
    }
}
