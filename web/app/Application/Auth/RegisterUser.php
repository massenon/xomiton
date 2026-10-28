<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Application\Auth\Support\RegistrationGate;
use App\Domain\Auth\ConsentVersion;
use App\Domain\Auth\Exceptions\AgeConfirmationRequired;
use App\Domain\Auth\Exceptions\ConsentRequired;
use App\Domain\Auth\Exceptions\OtpException;
use App\Domain\Auth\Exceptions\RegistrationsClosed;
use App\Enums\ConsentKind;
use App\Enums\MemberType;
use App\Enums\OtpPurpose;
use App\Enums\UserStatus;
use App\Events\Auth\UserRegistered;
use App\Models\Consent;
use App\Models\Profile;
use App\Models\User;
use App\Models\UserSetting;
use App\Notifications\UserRegistered as UserRegisteredNotification;
use App\Rules\PhoneBj;
use App\Support\Audit;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Phase 1 de l'inscription (P2-03, Q12 c) : compte créé et actif dès maintenant, e-mail non vérifié (accès limité),
 * rôle `membre`, profil et réglages par défaut, consentements CGU + charte et confidentialité horodatés, code OTP
 * `signup` envoyé aussitôt puis e-mail `bienvenue` (notification UserRegistered, canal mail par template : un seul
 * envoi, Q44 a). La session est ouverte par le contrôleur appelant.
 */
final class RegisterUser
{
    public const ROLE = 'membre';

    public function __construct(private readonly OtpService $otp) {}

    /**
     * @throws RegistrationsClosed|ConsentRequired|AgeConfirmationRequired
     */
    public function __invoke(RegisterData $data): User
    {
        if (! RegistrationGate::isOpen()) {
            throw new RegistrationsClosed(RegistrationGate::closedMessage());
        }

        if (! $data->acceptsTerms || ! $data->acceptsPrivacy) {
            throw new ConsentRequired;
        }

        if (! $data->confirmsAge) {
            throw new AgeConfirmationRequired;
        }

        $user = DB::transaction(fn (): User => $this->createAccount($data));

        try {
            $this->otp->issue($user->email, OtpPurpose::Signup, $data->ip);
        } catch (OtpException $e) {
            // Le compte existe : la personne pourra redemander un code depuis le bandeau « Confirme ton e-mail ».
            Log::warning('Code d’inscription non envoyé : '.$e->getMessage(), ['user_id' => $user->getKey()]);
        }

        $user->notify(new UserRegisteredNotification);

        Audit::log('auth.registered', $user, sprintf('pseudo=%s', $user->pseudo), $user);
        UserRegistered::dispatch($user);

        return $user;
    }

    private function createAccount(RegisterData $data): User
    {
        $user = User::query()->create([
            'pseudo' => trim($data->pseudo),
            'email' => OtpService::normalizeEmail($data->email),
            'phone' => $data->phone === null ? null : PhoneBj::format($data->phone),
            'password' => $data->password,
            // Q26 : validation manuelle de tous les nouveaux comptes (P12-02) ; sinon actif dès maintenant.
            'status' => RegistrationGate::manualValidation() === 'all' ? UserStatus::Pending : UserStatus::Active,
            'email_verified_at' => null,
        ]);

        // Le rôle vient du seeder (idempotent) ; findOrCreate évite un échec si la base n'a pas encore été seedée.
        $user->assignRole(Role::findOrCreate(self::ROLE, RolesAndPermissionsSeeder::GUARD));

        Profile::query()->create([
            'user_id' => $user->getKey(),
            'member_type' => MemberType::Member,
            'completion' => 0,
        ]);

        UserSetting::query()->create([
            'user_id' => $user->getKey(),
            'theme' => 'auto',
            'language' => (string) config('app.locale', 'fr'),
            'notification_prefs' => UserSetting::defaultNotificationPrefs(),
            'notification_digest' => UserSetting::DIGEST_NEVER,
        ]);

        foreach ([ConsentKind::Terms, ConsentKind::Privacy, ConsentKind::AgeConfirmed] as $kind) {
            $this->recordConsent($user, $kind, true, $data->ip, $data->userAgent);
        }

        return $user;
    }

    private function recordConsent(User $user, ConsentKind $kind, bool $granted, ?string $ip, ?string $userAgent): void
    {
        Consent::query()->create([
            'user_id' => $user->getKey(),
            'kind' => $kind,
            'version' => ConsentVersion::current(),
            'granted' => $granted,
            'ip' => $ip === null ? null : Str::limit($ip, 45, ''),
            'user_agent' => $userAgent === null ? null : Str::limit($userAgent, 255, ''),
        ]);
    }
}
