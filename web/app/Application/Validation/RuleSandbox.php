<?php

declare(strict_types=1);

namespace App\Application\Validation;

use App\Application\Cases\ValidationCatalog;
use App\Domain\Cases\ValidationRuleKey;
use App\Enums\AgeRange;
use App\Rules\AgeRangeWithinBounds;
use App\Rules\CaseCodeFormat;
use App\Rules\EmailAddress;
use App\Rules\MinimumLength;
use App\Rules\NotFutureDate;
use App\Rules\PhoneBj;
use App\Rules\PositiveInteger;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Bac à sable de validation (P11-05, prototype admin/validation.php « Bac à sable de validation ») : essaie une valeur
 * contre une règle du catalogue avec **le même code que les formulaires** (règles App\Rules, état réel du catalogue). Rien
 * n'est enregistré ni journalisé. Ne se testent pas ici : `commune_in_zone` (elle compare deux champs, département et
 * commune) et `password_strong` (un mot de passe ne transite pas par un champ de test).
 *
 * Résultat : `status` = `valid` | `invalid` | `disabled` (règle désactivée : la valeur serait acceptée sans contrôle) |
 * `empty` (valeur vide, champ facultatif) | `unsupported`, et `message` pour `invalid`.
 */
final class RuleSandbox
{
    /** Règles qui ne se testent pas sur une seule valeur. */
    public const UNSUPPORTED = [ValidationRuleKey::CommuneInZone, ValidationRuleKey::PasswordStrong, ValidationRuleKey::Plausibility, ValidationRuleKey::Duplicates];

    public function __construct(private readonly ValidationCatalog $catalog) {}

    /**
     * @return list<ValidationRuleKey> règles proposées dans le bac à sable
     */
    public static function testable(): array
    {
        return array_values(array_filter(ValidationRuleKey::cases(), static fn (ValidationRuleKey $key): bool => ! in_array($key, self::UNSUPPORTED, true)));
    }

    /**
     * @return array{status: string, message: string|null}
     */
    public function check(string $ruleKey, string $value): array
    {
        $key = ValidationRuleKey::tryFrom($ruleKey);

        if ($key === null || in_array($key, self::UNSUPPORTED, true)) {
            return ['status' => 'unsupported', 'message' => null];
        }

        if (! $this->catalog->isActive($key)) {
            return ['status' => 'disabled', 'message' => null];
        }

        if (trim($value) === '' && $key !== ValidationRuleKey::Required) {
            return ['status' => 'empty', 'message' => null];
        }

        $validator = Validator::make(['value' => $value], ['value' => $this->rulesFor($key)], [], ['value' => (string) __('admin_data.validation.sandbox.attribute')]);

        return $validator->fails()
            ? ['status' => 'invalid', 'message' => (string) $validator->errors()->first('value')]
            : ['status' => 'valid', 'message' => null];
    }

    /**
     * @return list<mixed>
     */
    private function rulesFor(ValidationRuleKey $key): array
    {
        return match ($key) {
            ValidationRuleKey::Required => ['required', 'string'],
            ValidationRuleKey::Email => [new EmailAddress],
            ValidationRuleKey::PhoneBj => [new PhoneBj],
            ValidationRuleKey::DateNotFuture => ['date_format:Y-m-d', new NotFutureDate],
            ValidationRuleKey::AgeRange => [Rule::enum(AgeRange::class), new AgeRangeWithinBounds($this->catalog->plausibility())],
            ValidationRuleKey::MinLen20 => [new MinimumLength],
            ValidationRuleKey::PositiveInt => [new PositiveInteger],
            ValidationRuleKey::CaseCode => [new CaseCodeFormat],
            default => [],
        };
    }
}
