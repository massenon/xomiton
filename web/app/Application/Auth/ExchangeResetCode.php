<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Domain\Auth\Exceptions\OtpInvalid;
use App\Enums\OtpPurpose;
use App\Models\OtpCode;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Str;

/**
 * Échange du code `reset` saisi contre une preuve signée (API `otp/verify`, Q8 c) : le code est vérifié et consommé par
 * OtpService (essais comptés), puis le même enregistrement `otp_codes` est transformé en preuve : secret aléatoire
 * jamais communiqué, attempts remis à zéro, même expiration. Aucune ligne supplémentaire : les plafonds d'envoi
 * (30 s, 5/h) ne bougent pas, et le code à 6 chiffres ne circule qu'une fois. SignedResetToken désigne cette preuve ;
 * ResetPassword la consomme comme un lien magique.
 */
final class ExchangeResetCode
{
    public function __construct(private readonly OtpService $otp) {}

    /**
     * @throws OtpInvalid
     */
    public function __invoke(User $user, string $code): OtpCode
    {
        $record = $this->otp->currentCode($user->email, OtpPurpose::Reset);

        if ($record === null || ! $this->otp->verify($user->email, OtpPurpose::Reset, $code)) {
            throw new OtpInvalid;
        }

        // verify() a consommé l'enregistrement sur sa propre instance : on relit l'état en base avant de le rouvrir.
        $record->refresh()->forceFill([
            'code_hash' => OtpCode::hash(Str::random(40)),
            'attempts' => 0,
            'consumed_at' => null,
        ])->save();

        Audit::log('auth.otp_verified', $user, 'usage=reset', null);

        return $record;
    }
}
