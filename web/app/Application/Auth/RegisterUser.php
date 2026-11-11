<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Application\Admin\Roles\TeamInvitations;
use App\Application\Auth\Support\RegistrationFlags;
use App\Application\Auth\Support\RegistrationGate;
use App\Application\Auth\Support\RegistrationOptions;
use App\Application\Shared\Countries;
use App\Domain\Account\SensitiveProfile;
use App\Domain\Auth\ConsentVersion;
use App\Domain\Auth\Exceptions\AgeConfirmationRequired;
use App\Domain\Auth\Exceptions\ConsentRequired;
use App\Domain\Auth\Exceptions\InvitationInvalid;
use App\Domain\Auth\Exceptions\OtpException;
use App\Domain\Auth\Exceptions\RegistrationsClosed;
use App\Enums\AgeRange;
use App\Enums\AllyRole;
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
use App\Rules\RegistrationPhone;
use App\Support\Audit;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Inscription en 3 étapes obligatoires, un seul envoi à la fin (décision client du 11/11/2026) : le compte n'existe
 * qu'à la fin. Il est créé et la session ouverte aussitôt, mais reste `en attente` (UserStatus::Pending) jusqu'à la
 * validation de l'administration (réglage `reg_manual_validation = all` par défaut ; en mode `peers`, seul un compte
 * SIGNALÉ, D-MOB-17, attend). La « vérification de l'e-mail » est faite par l'administration à la validation
 * (ReviewRegistration) : aucun code OTP ni e-mail de bienvenue n'est envoyé à un compte en attente (l'OTP reste en place
 * pour la connexion et la réinitialisation). Un compte actif d'emblée (mode `peers`, non signalé) garde l'ancien
 * parcours : code `signup` puis e-mail de bienvenue.
 *
 * Rôle `membre` (jamais un autre : pair et admin ne s'obtiennent que par invitation ou attribution de l'administration) ;
 * `profiles.member_type` = membre ou allié·e selon le choix. Profil et réglages par défaut ; consentements CGU, charte et
 * confidentialité horodatés, confirmation d'âge (l'âge saisi ≥ `min_age` en tient lieu), consentement `sensitive_data`
 * pour un membre. Les champs sensibles (identité de genre, orientation, statut d'allié·e et leurs précisions) sont
 * chiffrés par le modèle Profile. L'événement UserRegistered prévient les validateurs (NotifyValidatorsOfPendingAccount).
 */
final class RegisterUser
{
    public const ROLE = 'membre';

    public function __construct(
        private readonly OtpService $otp,
        private readonly TeamInvitations $invitations,
    ) {}

    /**
     * @throws RegistrationsClosed|ConsentRequired|AgeConfirmationRequired|InvitationInvalid
     */
    public function __invoke(RegisterData $data): User
    {
        $code = $data->invitationCode === null || trim($data->invitationCode) === '' ? null : $data->invitationCode;

        // Une invitation valide ouvre l'inscription même fermée au public ; elle ne change jamais le rôle de départ (membre).
        if ($code !== null) {
            $this->invitations->peek($code, $data->email);
        } elseif (! RegistrationGate::isOpen()) {
            throw new RegistrationsClosed(RegistrationGate::closedMessage());
        }

        if (! $data->acceptsTerms || ! $data->acceptsPrivacy) {
            throw new ConsentRequired;
        }

        // L'âge saisi tient lieu de confirmation : sous l'âge minimal (config), pas de compte.
        if ($data->age < (int) config('xomiton.accounts.min_age', 15)) {
            throw new AgeConfirmationRequired;
        }

        $user = DB::transaction(function () use ($data, $code): User {
            $user = $this->createAccount($data);

            if ($code !== null) {
                $this->invitations->redeem($code, $user);
            }

            return $user;
        });

        if ($user->status === UserStatus::Pending) {
            // L'équipe confirme l'adresse en validant le compte : pas de code, pas de bienvenue prématurée.
            Audit::log('auth.registered', $user, $this->auditDetails($user, $data), $user);
            UserRegistered::dispatch($user);

            return $user;
        }

        try {
            $this->otp->issue($user->email, OtpPurpose::Signup, $data->ip);
        } catch (OtpException $e) {
            // Le compte existe : la personne pourra redemander un code depuis le bandeau « Confirme ton e-mail ».
            Log::warning('Code d’inscription non envoyé : '.$e->getMessage(), ['user_id' => $user->getKey()]);
        }

        $user->notify(new UserRegisteredNotification);

        Audit::log('auth.registered', $user, $this->auditDetails($user, $data), $user);
        UserRegistered::dispatch($user);

        return $user;
    }

    private function auditDetails(User $user, RegisterData $data): string
    {
        // Jamais d'identité de genre, d'orientation ni de statut d'allié·e dans le journal.
        return sprintf(
            'pseudo=%s ; type=%s%s',
            $user->pseudo,
            $data->memberType->value,
            $user->registration_flags === null ? '' : ' ; signalements='.$user->registration_flags,
        );
    }

    private function createAccount(RegisterData $data): User
    {
        $email = OtpService::normalizeEmail($data->email);
        // D-MOB-17 : seules les inscriptions signalées (e-mail jetable, inscriptions répétées) attendent l'équipe en mode
        // `peers` ; Q26 / décision du 11/11/2026 : `reg_manual_validation = all` (défaut) fait attendre tous les nouveaux comptes.
        $flags = RegistrationFlags::evaluate($email, $data->ip, $data->userAgent);
        $manual = RegistrationGate::manualValidation() === 'all' || $flags !== [];
        $firstName = trim($data->firstName);
        $lastName = trim($data->lastName);
        $isBenin = $data->countryCode === Countries::BENIN;

        $user = User::query()->create([
            'pseudo' => trim($data->pseudo),
            'first_name' => $firstName,
            'last_name' => $lastName,
            // `name` reste « Prénom Nom » (affichages et e-mails) ; jamais public (RG-02).
            'name' => trim($firstName.' '.$lastName),
            'email' => $email,
            'phone' => $data->phone === null ? null : RegistrationPhone::format($data->phone, $data->countryCode),
            'password' => $data->password,
            'status' => $manual ? UserStatus::Pending : UserStatus::Active,
            'registration_flags' => $flags === [] ? null : implode(',', $flags),
            // La vérification de l'adresse est faite par l'administration à la validation du compte.
            'email_verified_at' => null,
            // Zone de routage SOS : Bénin seulement.
            'zone_id' => $isBenin ? $data->zoneId : null,
            'commune_id' => $isBenin ? $data->communeId : null,
        ]);

        // Le rôle vient du seeder (idempotent) ; findOrCreate évite un échec si la base n'a pas encore été seedée.
        $user->assignRole(Role::findOrCreate(self::ROLE, RolesAndPermissionsSeeder::GUARD));

        $this->createProfile($user, $data);

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

        // Consentement aux données sensibles : demandé (et obligatoire) aux membres seulement.
        if (! $data->isAlly()) {
            $this->recordConsent($user, ConsentKind::SensitiveData, $data->acceptsSensitiveData, $data->ip, $data->userAgent);
        }

        return $user;
    }

    private function createProfile(User $user, RegisterData $data): void
    {
        $ally = $data->isAlly();
        $other = RegistrationOptions::OTHER;
        // « Je préfère ne pas le dire » n'est pas enregistré : le groupe du profil progressif est traité, la valeur reste vide.
        $genderIdentity = $ally || $data->genderIdentity === SensitiveProfile::PREFER_NOT ? null : $data->genderIdentity;
        $orientation = $ally || $data->orientation === SensitiveProfile::PREFER_NOT ? null : $data->orientation;
        $allyRole = $ally ? $data->allyRole : null;

        $profile = new Profile([
            'user_id' => $user->getKey(),
            'member_type' => $ally ? MemberType::Ally : MemberType::Member,
            'pronouns' => $data->pronouns,
            'age' => $data->age,
            'age_range' => AgeRange::fromAge($data->age),
            'country_code' => $data->countryCode,
            'city' => $data->city,
            'gender_identity' => $genderIdentity,
            'gender_identity_other' => $genderIdentity === $other ? $data->genderIdentityOther : null,
            'orientation' => $orientation,
            'orientation_other' => $orientation === $other ? $data->orientationOther : null,
            'ally_role' => $allyRole?->value,
            'ally_role_other' => $allyRole === AllyRole::Other ? $data->allyRoleOther : null,
        ]);

        // Tout ce que le formulaire a demandé est traité : le bandeau « Complète ton profil » ne le repropose pas.
        $profile->markAnswered('pronouns', 'member_type', 'ally_status', 'age', 'location');

        if (! $ally) {
            $profile->markAnswered('gender_identity', 'orientation');
        }

        $profile->completion = CompleteRegistrationProfile::completion($user, $profile);
        $profile->save();
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
