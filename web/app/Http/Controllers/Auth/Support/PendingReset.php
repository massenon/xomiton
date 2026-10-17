<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth\Support;

use App\Application\Auth\OtpService;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Carbon;

/**
 * Réinitialisation par code (Q8 c) : l'adresse saisie sur « Mot de passe oublié », puis la preuve signée obtenue à
 * l'écran OTP (`proof`, jeton SignedResetToken : le code à 6 chiffres n'est jamais conservé), attendent dans la session
 * jusqu'au formulaire « Nouveau mot de passe », qui la consomme.
 *
 * Le délai de renvoi (`sent_at`) est tenu ici et non dans `otp_codes` : il doit être identique que le compte existe
 * ou non, sinon le compte à rebours du bouton « Renvoyer le code » révélerait l'existence du compte (FR-AUTH-04).
 */
final class PendingReset
{
    public const KEY = 'auth.pending_reset';

    public function __construct(private readonly Session $session) {}

    public function start(string $email): void
    {
        $this->session->put(self::KEY, [
            'email' => OtpService::normalizeEmail($email),
            'proof' => null,
            'sent_at' => now()->getTimestamp(),
            'expires_at' => now()->addMinutes(OtpService::ttlMinutes())->getTimestamp(),
        ]);
    }

    /** Un nouvel e-mail vient d'être demandé : le délai de renvoi et la validité repartent. */
    public function markSent(): void
    {
        $state = $this->state();

        if ($state !== null) {
            $this->session->put(self::KEY, [
                'sent_at' => now()->getTimestamp(),
                'expires_at' => now()->addMinutes(OtpService::ttlMinutes())->getTimestamp(),
            ] + $state);
        }
    }

    public function prove(string $proof): void
    {
        $state = $this->state();

        if ($state !== null) {
            $this->session->put(self::KEY, ['proof' => $proof] + $state);
        }
    }

    public function email(): ?string
    {
        return $this->state()['email'] ?? null;
    }

    public function proof(): ?string
    {
        $proof = $this->state()['proof'] ?? null;

        return is_string($proof) && $proof !== '' ? $proof : null;
    }

    /** Instant à partir duquel « Renvoyer le code » est possible ; null si c'est déjà le cas. */
    public function resendAvailableAt(): ?Carbon
    {
        $state = $this->state();

        if ($state === null) {
            return null;
        }

        $at = Carbon::createFromTimestamp((int) ($state['sent_at'] ?? 0))->addSeconds(OtpService::resendAfterSeconds());

        return $at->isFuture() ? $at : null;
    }

    public function forget(): void
    {
        $this->session->forget(self::KEY);
    }

    /**
     * @return array{email: string, proof: ?string, sent_at: int, expires_at: int}|null
     */
    private function state(): ?array
    {
        $state = $this->session->get(self::KEY);

        if (! is_array($state) || ! isset($state['email'], $state['expires_at'])) {
            return null;
        }

        if ((int) $state['expires_at'] <= now()->getTimestamp()) {
            $this->forget();

            return null;
        }

        /** @var array{email: string, proof: ?string, sent_at: int, expires_at: int} $state */
        return $state;
    }
}
