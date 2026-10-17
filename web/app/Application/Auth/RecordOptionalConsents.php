<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Domain\Auth\ConsentVersion;
use App\Enums\ConsentKind;
use App\Models\Consent;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Phase 4 de l'inscription : consentements facultatifs (données sensibles, newsletter). Chaque réponse, acceptée ou
 * refusée, devient une ligne `consents` horodatée : on sait ce qui a été proposé et répondu, et quand.
 */
final class RecordOptionalConsents
{
    public function __invoke(User $user, bool $sensitiveData, bool $newsletter, ?string $ip = null, ?string $userAgent = null): void
    {
        $answers = [
            ConsentKind::SensitiveData->value => $sensitiveData,
            ConsentKind::Newsletter->value => $newsletter,
        ];

        foreach ($answers as $kind => $granted) {
            Consent::query()->create([
                'user_id' => $user->getKey(),
                'email' => $granted && $kind === ConsentKind::Newsletter->value ? $user->email : null,
                'kind' => ConsentKind::from($kind),
                'version' => ConsentVersion::current(),
                'granted' => $granted,
                'ip' => $ip === null ? null : Str::limit($ip, 45, ''),
                'user_agent' => $userAgent === null ? null : Str::limit($userAgent, 255, ''),
            ]);
        }
    }
}
