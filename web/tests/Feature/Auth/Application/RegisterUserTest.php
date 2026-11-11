<?php

declare(strict_types=1);

use App\Application\Account\Profile\ProgressiveProfileState;
use App\Application\Admin\Users\ReviewRegistration;
use App\Application\Auth\OtpService;
use App\Application\Auth\RegisterUser;
use App\Application\Auth\VerifyEmail;
use App\Domain\Auth\ConsentVersion;
use App\Domain\Auth\Exceptions\AgeConfirmationRequired;
use App\Domain\Auth\Exceptions\ConsentRequired;
use App\Domain\Auth\Exceptions\OtpInvalid;
use App\Domain\Auth\Exceptions\RegistrationsClosed;
use App\Enums\AgeRange;
use App\Enums\AllyRole;
use App\Enums\ConsentKind;
use App\Enums\MemberType;
use App\Enums\OtpPurpose;
use App\Enums\UserStatus;
use App\Events\Auth\EmailVerified;
use App\Events\Auth\UserRegistered;
use App\Models\AuditLog;
use App\Models\Commune;
use App\Models\OtpCode;
use App\Models\User;
use App\Models\Zone;
use App\Support\Settings;
use Database\Seeders\EmailTemplatesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Support\FeedFixtures as F;
use Tests\Support\RegistrationFixtures as R;

/*
 * Inscription en 3 étapes obligatoires (décision client du 11/11/2026) : le cas d'usage reçoit TOUTES les données d'un
 * coup ; le compte est créé en attente de validation (`reg_manual_validation = all` par défaut), sans code OTP ni e-mail
 * de bienvenue ; l'e-mail est confirmé par l'administration à la validation.
 */

beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, EmailTemplatesSeeder::class, SettingsSeeder::class]);
    Mail::fake();
    config(['mail.default' => 'array']);
});

it('crée le compte en attente : membre, identité, profil, réglages, consentements, ni code ni bienvenue', function (): void {
    Event::fake([UserRegistered::class]);

    $user = app(RegisterUser::class)(R::data());

    expect($user->pseudo)->toBe('Awa_S')
        ->and($user->first_name)->toBe('Awa')
        ->and($user->last_name)->toBe('Sossou')
        ->and($user->name)->toBe('Awa Sossou')
        ->and($user->email)->toBe('awa@exemple.bj')
        ->and($user->status)->toBe(UserStatus::Pending)
        ->and($user->email_verified_at)->toBeNull()
        ->and(Hash::check('Xomiton2026!', $user->password))->toBeTrue()
        ->and($user->hasRole('membre'))->toBeTrue()
        ->and($user->settings?->language)->toBe('fr');

    $profile = $user->profile;
    expect($profile?->member_type)->toBe(MemberType::Member)
        ->and($profile?->age)->toBe(24)
        ->and($profile?->age_range)->toBe(AgeRange::From18To24)
        ->and($profile?->country_code)->toBe('BJ')
        ->and($profile?->pronouns)->toBe('elle')
        ->and($profile?->gender_identity)->toBe('femme_cis')
        ->and($profile?->orientation)->toBe('lesbienne')
        ->and($profile?->ally_role)->toBeNull();

    // 4 consentements : CGU, confidentialité, âge (l'âge saisi en tient lieu) et données sensibles (membre).
    expect($user->consents()->count())->toBe(4)
        ->and($user->consents()->ofKind(ConsentKind::AgeConfirmed)->granted()->exists())->toBeTrue()
        ->and($user->consents()->ofKind(ConsentKind::SensitiveData)->granted()->exists())->toBeTrue();

    $terms = $user->consents()->ofKind(ConsentKind::Terms)->granted()->firstOrFail();
    expect($terms->version)->toBe(ConsentVersion::current())
        ->and($terms->getAttribute('ip'))->toBe('41.79.1.1')
        ->and($terms->getAttribute('user_agent'))->toBe('Mozilla/5.0 (test)');

    // L'équipe confirme l'e-mail en validant : aucun code OTP, aucun e-mail (ni code ni bienvenue prématurée).
    expect(OtpCode::query()->forEmail('awa@exemple.bj', OtpPurpose::Signup)->count())->toBe(0);
    Mail::assertNothingSent();
    Mail::assertNothingQueued();

    Event::assertDispatched(UserRegistered::class, fn (UserRegistered $event): bool => $event->user->is($user));
    $audit = AuditLog::query()->ofAction('auth.registered')->forSubject($user)->firstOrFail();
    expect((string) $audit->detail)->toContain('type=membre')->not->toContain('lesbienne')->not->toContain('femme_cis');
});

it('chiffre les données sensibles au repos et garde « Prénom Nom » dans name', function (): void {
    $user = app(RegisterUser::class)(R::data(['gender_identity' => 'autre', 'gender_identity_other' => 'Genderfluid', 'orientation' => 'autre', 'orientation_other' => 'Demi']));

    $raw = DB::table('profiles')->where('user_id', $user->id)->first();

    expect($raw->gender_identity)->not->toContain('autre')
        ->and($raw->gender_identity_other)->not->toContain('Genderfluid')
        ->and($raw->orientation_other)->not->toContain('Demi')
        ->and($user->profile?->gender_identity_other)->toBe('Genderfluid')
        ->and($user->profile?->orientation_other)->toBe('Demi');
});

it('inscrit une personne ressource / allié·e : type allié·e, statut (+ précision), aucune donnée de membre, pas de consentement sensible', function (): void {
    $user = app(RegisterUser::class)(R::data(R::allyPayload(['ally_role' => 'autre', 'ally_role_other' => 'Formatrice'])));

    expect($user->profile?->member_type)->toBe(MemberType::Ally)
        ->and($user->profile?->ally_role)->toBe(AllyRole::Other->value)
        ->and($user->profile?->ally_role_other)->toBe('Formatrice')
        ->and($user->profile?->gender_identity)->toBeNull()
        ->and($user->profile?->orientation)->toBeNull()
        ->and($user->consents()->count())->toBe(3)
        ->and($user->consents()->ofKind(ConsentKind::SensitiveData)->exists())->toBeFalse()
        ->and(DB::table('profiles')->where('user_id', $user->id)->value('ally_role'))->not->toContain('autre');

    $avocate = app(RegisterUser::class)(R::data(R::allyPayload(['pseudo' => 'Maitre_B', 'email' => 'maitre@exemple.bj', 'ally_role' => 'avocat', 'ally_role_other' => 'ignorée'])));
    expect($avocate->profile?->ally_role)->toBe('avocat')->and($avocate->profile?->ally_role_other)->toBeNull();
});

it('ne garde rien de « je préfère ne pas le dire » et traite les groupes du profil progressif', function (): void {
    $user = app(RegisterUser::class)(R::data(['gender_identity' => 'prefer_not', 'orientation' => 'prefer_not']));

    expect($user->profile?->gender_identity)->toBeNull()->and($user->profile?->orientation)->toBeNull();

    $state = app(ProgressiveProfileState::class)->for($user->fresh());
    $answered = array_column(array_filter($state['groups'], fn (array $g): bool => $g['answered']), 'key');

    // Tout ce que le formulaire a demandé compte comme renseigné : pronoms, type, âge, lieu, genre, orientation, statut.
    expect($answered)->toContain('pronouns', 'member_type', 'age', 'location', 'gender_identity', 'orientation', 'ally_status')
        ->and($state['next'])->toBe('interests');
});

it('garde la zone et la commune du pays choisi (ignorées si elles sont d’un autre pays), le téléphone au format du pays', function (): void {
    $zone = Zone::factory()->create(['is_active' => true]);
    $commune = Commune::factory()->create(['zone_id' => $zone->id, 'is_active' => true]);

    $benin = app(RegisterUser::class)(R::data(['zone_id' => $zone->id, 'commune_id' => $commune->id, 'city' => 'Cotonou', 'phone' => '97 38 88 47']));
    expect($benin->zone_id)->toBe($zone->id)->and($benin->commune_id)->toBe($commune->id)
        ->and($benin->phone)->toBe('+229 01 97 38 88 47')->and($benin->profile?->city)->toBe('Cotonou');

    $france = app(RegisterUser::class)(R::data(['pseudo' => 'Awa_Paris', 'email' => 'paris@exemple.fr', 'country_code' => 'FR', 'zone_id' => $zone->id, 'commune_id' => $commune->id, 'phone' => '+33 6 12 34 56 78']));
    expect($france->zone_id)->toBeNull()->and($france->commune_id)->toBeNull()
        ->and($france->phone)->toBe('+33612345678')->and($france->profile?->country_code)->toBe('FR');
});

it('refuse une inscription sans les deux consentements obligatoires, cases distinctes', function (): void {
    expect(fn () => app(RegisterUser::class)(R::data(['privacy' => false])))->toThrow(ConsentRequired::class)
        ->and(fn () => app(RegisterUser::class)(R::data(['cgu' => false])))->toThrow(ConsentRequired::class)
        ->and(User::query()->count())->toBe(0);
});

it('lit l’âge minimal (18 ans) dans la configuration : 17 refusé, 18 accepté, tranche de la fiche cohérente avec AgeRange', function (): void {
    expect(config('xomiton.accounts.min_age'))->toBe(18);

    foreach ([14, 15, 17] as $age) {
        expect(fn () => app(RegisterUser::class)(R::data(['age' => $age])))->toThrow(AgeConfirmationRequired::class);
    }
    expect(User::query()->count())->toBe(0);

    $adult = app(RegisterUser::class)(R::data(['age' => 18]));
    expect($adult->profile?->age)->toBe(18)
        ->and($adult->profile?->age_range)->toBe(AgeRange::fromAge(18))->toBe(AgeRange::From18To24);

    $older = app(RegisterUser::class)(R::data(['pseudo' => 'Aînée_B', 'email' => 'ainee@exemple.bj', 'age' => 52]));
    expect($older->profile?->age_range)->toBe(AgeRange::FiftyPlus);
});

it('suit la configuration si elle change (source unique xomiton.accounts.min_age)', function (): void {
    config(['xomiton.accounts.min_age' => 21]);

    expect(fn () => app(RegisterUser::class)(R::data(['age' => 20])))->toThrow(AgeConfirmationRequired::class)
        ->and(app(RegisterUser::class)(R::data(['age' => 21]))->profile?->age)->toBe(21);
});

it('refuse l’inscription quand l’administration l’a fermée, avec le message configuré', function (): void {
    Settings::set('registrations_open', false);
    Settings::set('reg_closed_msg', 'Fermé pour les fêtes, retour le 3 janvier.');

    expect(fn () => app(RegisterUser::class)(R::data()))->toThrow(RegistrationsClosed::class, 'Fermé pour les fêtes, retour le 3 janvier.');

    try {
        app(RegisterUser::class)(R::data());
    } catch (RegistrationsClosed $e) {
        expect($e->userMessage())->toBe('Fermé pour les fêtes, retour le 3 janvier.')
            ->and($e->status())->toBe(403);
    }

    Settings::set('reg_closed_msg', '');

    try {
        app(RegisterUser::class)(R::data());
    } catch (RegistrationsClosed $e) {
        expect($e->userMessage())->toBe(__('auth.registrations_closed'));
    }
});

it('ne prévient pas par e-mail à l’inscription : ni code signup, ni bienvenue, quel que soit le profil (compte toujours en attente)', function (): void {
    foreach ([['pseudo' => 'Awa_S', 'email' => 'awa@exemple.bj'], ['pseudo' => 'Jetable', 'email' => 'j@mailinator.com']] as $overrides) {
        $user = app(RegisterUser::class)(R::data($overrides));

        expect($user->status)->toBe(UserStatus::Pending)
            ->and(OtpCode::query()->forEmail($user->email, OtpPurpose::Signup)->count())->toBe(0);
    }
    Mail::assertNothingSent();
});

it('confirme l’e-mail avec un code signup (VerifyEmail) et refuse un mauvais code', function (): void {
    Event::fake([EmailVerified::class]);
    $user = app(RegisterUser::class)(R::data());
    app(OtpService::class)->issue($user->email, OtpPurpose::Signup, '10.0.0.1');
    OtpCode::query()->forEmail($user->email, OtpPurpose::Signup)->update(['code_hash' => OtpCode::hash('654321')]);

    expect(fn () => app(VerifyEmail::class)($user, '000000'))->toThrow(OtpInvalid::class)
        ->and($user->fresh()?->email_verified_at)->toBeNull();

    app(VerifyEmail::class)($user, '654 321');

    expect($user->fresh()?->email_verified_at)->not->toBeNull();
    Event::assertDispatched(EmailVerified::class);
});

it('pose email_verified_at quand l’administration valide le compte, et le journalise ; un e-mail déjà confirmé n’est pas touché', function (): void {
    Notification::fake();
    $admin = F::member('admin');
    $user = app(RegisterUser::class)(R::data());

    app(ReviewRegistration::class)->approve($user, $admin);

    $user->refresh();
    expect($user->status)->toBe(UserStatus::Active)
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(AuditLog::query()->ofAction('users.email_verified_by_admin')->forSubject($user)->exists())->toBeTrue()
        ->and(AuditLog::query()->ofAction('users.registration_approved')->forSubject($user)->exists())->toBeTrue();

    $already = User::factory()->withProfile()->create(['status' => UserStatus::Pending, 'email_verified_at' => now()->subDay()]);
    $before = $already->email_verified_at;
    app(ReviewRegistration::class)->approve($already, $admin);

    expect($already->fresh()?->email_verified_at?->equalTo($before))->toBeTrue()
        ->and(AuditLog::query()->ofAction('users.email_verified_by_admin')->forSubject($already)->exists())->toBeFalse();
});
