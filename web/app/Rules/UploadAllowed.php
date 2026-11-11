<?php

declare(strict_types=1);

namespace App\Rules;

use App\Application\Shared\Exceptions\MediaRejected;
use App\Application\Shared\MediaUploader;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Fichier téléversé accepté dans une collection de config/xomiton.php `uploads.collections` (P3-04) : type réel lu
 * dans le contenu, poids maximal, image lisible. Le message vient de MediaUploader (lang/fr/uploads.php).
 *
 *   'preuve' => ['nullable', 'file', new UploadAllowed('images')]
 */
final class UploadAllowed implements ValidationRule
{
    public function __construct(private readonly string $collection) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail((string) __('uploads.rejected.not_a_file'));

            return;
        }

        try {
            app(MediaUploader::class)->inspect($value, $this->collection);
        } catch (MediaRejected $rejected) {
            $fail($rejected->getMessage());
        }
    }
}
