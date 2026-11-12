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
 *
 * `$maxKb` : plafond plus strict que la collection ; `$strictExtension` : l'extension déclarée doit correspondre au type
 * réel (vidéo mp4 / webm du fil : un `.mp4` qui contient du WebM, ou un `.exe`, est refusé).
 */
final class UploadAllowed implements ValidationRule
{
    /** Extension attendue par type réel, quand l'extension déclarée doit être cohérente. */
    private const EXTENSIONS = ['video/mp4' => ['mp4', 'm4v'], 'video/webm' => ['webm']];

    public function __construct(
        private readonly string $collection,
        private readonly ?int $maxKb = null,
        private readonly bool $strictExtension = false,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail((string) __('uploads.rejected.not_a_file'));

            return;
        }

        try {
            $mime = app(MediaUploader::class)->inspect($value, $this->collection, $this->maxKb);

            if ($this->strictExtension && ! in_array(strtolower($value->getClientOriginalExtension()), self::EXTENSIONS[$mime] ?? [], true)) {
                $fail((string) __('uploads.rejected.extension'));
            }
        } catch (MediaRejected $rejected) {
            $fail($rejected->getMessage());
        }
    }
}
