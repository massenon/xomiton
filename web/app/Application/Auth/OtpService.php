<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Application\Mail\EmailTemplateService;
use App\Domain\Auth\Exceptions\OtpInvalid;
use App\Domain\Auth\Exceptions\OtpRateLimited;
use App\Domain\Auth\Exceptions\OtpTooSoon;
use App\Enums\OtpPurpose;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Codes de vérification par e-mail (P2-04, LOGIN §5) : 6 chiffres, hachés SHA-256, valables 10 min, 5 essais,
 * renvoi après 30 s (le nouveau code annule l'ancien), 5 envois par heure et par couple (adresse, usage).
 * Les durées viennent de config/xomiton.php (`otp.*`).
 */
class OtpService
{
    /** Slug du template système d'envoi du code (RG-10). */
    public const TEMPLATE = 'code-verification';

    /**
     * Génère un code et l'envoie avec le template `code-verification`.
     *
     * @throws OtpTooSoon|OtpRateLimited
     */
    public function issue(string $email, OtpPurpose $purpose, ?string $ip = null): OtpCode
    {
        $issued = $this->generate($email, $purpose, $ip);

        EmailTemplateService::send(self::TEMPLATE, $issued->record->email, [
            'otp_code' => $issued->code,
            'otp_expiry' => self::expiryLabel(),
            'user_first_name' => $this->displayNameFor($issued->record->email),
        ], 'otp:'.$purpose->value);

        return $issued->record;
    }

    /**
     * Génère un code sans l'envoyer : l'appelant compose lui-même l'e-mail (réinitialisation du mot de passe, Q8 c).
     * Les codes encore valables du même couple sont annulés.
     *
     * @throws OtpTooSoon|OtpRateLimited
     */
    public function generate(string $email, OtpPurpose $purpose, ?string $ip = null): IssuedOtp
    {
        $email = self::normalizeEmail($email);
        $this->assertCanSend($email, $purpose);

        OtpCode::query()->forEmail($email, $purpose)->valid()->update(['consumed_at' => now()]);

        $code = $this->randomCode();

        $record = OtpCode::query()->create([
            'email' => $email,
            'purpose' => $purpose,
            'code_hash' => OtpCode::hash($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(self::ttlMinutes()),
            'ip' => $ip === null ? null : Str::limit($ip, 45, ''),
        ]);

        return new IssuedOtp($record, $code);
    }

    /**
     * Vérifie un code : true et le consomme s'il correspond ; false sinon (essai compté) ; OtpInvalid quand les
     * essais sont épuisés (le code est alors annulé : il faut en demander un nouveau).
     *
     * @throws OtpInvalid
     */
    public function verify(string $email, OtpPurpose $purpose, string $code): bool
    {
        $record = $this->currentCode($email, $purpose);

        if ($record === null) {
            return false;
        }

        $code = preg_replace('/\D/', '', $code) ?? '';

        if (! $record->matches($code)) {
            $record->attempts++;

            if ($record->attempts >= self::maxAttempts()) {
                $record->consumed_at = now();
                $record->save();

                throw new OtpInvalid(exhausted: true);
            }

            $record->save();

            return false;
        }

        $record->forceFill(['consumed_at' => now()])->save();

        return true;
    }

    /**
     * Instant à partir duquel un nouvel envoi est possible ; null si c'est déjà le cas.
     */
    public function canResendAt(string $email, OtpPurpose $purpose): ?Carbon
    {
        $email = self::normalizeEmail($email);

        $retryAt = $this->rateLimitRetryAt($email, $purpose) ?? $this->resendDelayRetryAt($email, $purpose);

        return $retryAt !== null && $retryAt->isFuture() ? $retryAt : null;
    }

    /** Dernier code encore utilisable du couple (adresse, usage). */
    public function currentCode(string $email, OtpPurpose $purpose): ?OtpCode
    {
        return OtpCode::query()->forEmail(self::normalizeEmail($email), $purpose)->valid()->latest('id')->first();
    }

    /** Libellé de la durée de validité pour les e-mails : « 10 minutes ». */
    public static function expiryLabel(): string
    {
        return (string) __('auth.otp.expiry', ['minutes' => self::ttlMinutes()]);
    }

    public static function ttlMinutes(): int
    {
        return max(1, (int) config('xomiton.otp.ttl_minutes', 10));
    }

    public static function maxAttempts(): int
    {
        return max(1, (int) config('xomiton.otp.max_attempts', 5));
    }

    public static function resendAfterSeconds(): int
    {
        return max(0, (int) config('xomiton.otp.resend_after_seconds', 30));
    }

    public static function maxSendsPerHour(): int
    {
        return max(1, (int) config('xomiton.otp.max_sends_per_hour', 5));
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /**
     * @throws OtpTooSoon|OtpRateLimited
     */
    private function assertCanSend(string $email, OtpPurpose $purpose): void
    {
        $limitedUntil = $this->rateLimitRetryAt($email, $purpose);

        if ($limitedUntil !== null && $limitedUntil->isFuture()) {
            throw new OtpRateLimited($limitedUntil);
        }

        $tooSoonUntil = $this->resendDelayRetryAt($email, $purpose);

        if ($tooSoonUntil !== null && $tooSoonUntil->isFuture()) {
            throw new OtpTooSoon($tooSoonUntil);
        }
    }

    private function rateLimitRetryAt(string $email, OtpPurpose $purpose): ?Carbon
    {
        $windowStart = now()->subHour();

        $sends = OtpCode::query()
            ->forEmail($email, $purpose)
            ->where('created_at', '>', $windowStart)
            ->orderBy('created_at')
            ->pluck('created_at');

        if ($sends->count() < self::maxSendsPerHour()) {
            return null;
        }

        /** @var Carbon $oldest */
        $oldest = $sends->first();

        return $oldest->copy()->addHour();
    }

    private function resendDelayRetryAt(string $email, OtpPurpose $purpose): ?Carbon
    {
        /** @var Carbon|null $last */
        $last = OtpCode::query()->forEmail($email, $purpose)->max('created_at');

        if ($last === null) {
            return null;
        }

        return Carbon::parse($last)->addSeconds(self::resendAfterSeconds());
    }

    private function randomCode(): string
    {
        $length = max(4, (int) config('xomiton.otp.length', 6));

        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
    }

    /** Prénom (jamais public ailleurs, RG-02) ou pseudonyme pour la formule d'appel de l'e-mail. */
    private function displayNameFor(string $email): string
    {
        $user = User::query()->where('email', $email)->first(['name', 'pseudo']);

        if ($user === null) {
            return '';
        }

        $firstName = trim(Str::before(trim((string) $user->name), ' '));

        return $firstName !== '' ? $firstName : (string) $user->pseudo;
    }
}
