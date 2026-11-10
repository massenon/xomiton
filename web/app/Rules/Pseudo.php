<?php

declare(strict_types=1);

namespace App\Rules;

use App\Application\Account\Settings\PseudonymChanges;
use App\Application\Feed\BadWordFilter;
use App\Domain\Feed\Exceptions\ContentBlocked;
use App\Exceptions\ApiExceptionRenderer;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Pseudonyme (LOGIN §4) : 3 à 40 caractères, lettres, chiffres, `_`, `.` et `-`, unique sans tenir compte de la casse
 * (comptes supprimés inclus : l'index unique les retient aussi).
 */
final class Pseudo implements ValidationRule
{
    public const PATTERN = '/^[\p{L}\p{N}_.-]+$/u';

    public function __construct(private readonly ?int $ignoreUserId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('validation.pseudo.format')->translate();

            return;
        }

        $min = (int) config('xomiton.accounts.pseudo_min_length', 3);
        $max = (int) config('xomiton.accounts.pseudo_max_length', 40);
        $length = mb_strlen($value);

        if ($length < $min || $length > $max) {
            $fail('validation.pseudo.length')->translate(['min' => $min, 'max' => $max]);

            return;
        }

        if (preg_match(self::PATTERN, $value) !== 1) {
            $fail('validation.pseudo.format')->translate();

            return;
        }

        // Filtre minimal de contenus (D-IOS-9) : pseudonyme injurieux ou portant des coordonnées. API : 422 `content_rejected` ;
        // formulaire web : erreur de champ.
        try {
            app(BadWordFilter::class)->assertAllowed(['pseudo' => $value], 'pseudo');
        } catch (ContentBlocked $e) {
            if (ApiExceptionRenderer::wants(request())) {
                throw $e;
            }

            $fail('validation.pseudo.rejected')->translate();

            return;
        }

        if ($this->isTaken($value)) {
            $fail('validation.pseudo.taken')->translate();
        }
    }

    private function isTaken(string $pseudo): bool
    {
        // P8-09 (Q81) : l'ancien pseudonyme d'un compte lui reste réservé un temps après un changement.
        $reservedFor = app(PseudonymChanges::class)->reservedFor($pseudo);

        if ($reservedFor !== null && $reservedFor !== $this->ignoreUserId) {
            return true;
        }

        return User::withTrashed()
            ->whereRaw('LOWER(pseudo) = ?', [mb_strtolower($pseudo)])
            ->when($this->ignoreUserId !== null, fn ($query) => $query->whereKeyNot($this->ignoreUserId))
            ->exists();
    }
}
