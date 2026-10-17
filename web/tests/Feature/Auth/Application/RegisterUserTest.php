<?php

declare(strict_types=1);

use App\Application\Auth\CompleteRegistrationProfile;
use App\Application\Auth\ProfileData;
use App\Application\Auth\RecordOptionalConsents;
use App\Application\Auth\RegisterData;
use App\Application\Auth\RegisterUser;
use App\Application\Auth\VerifyEmail;
use App\Domain\Auth\ConsentVersion;
use App\Domain\Auth\Exceptions\ConsentRequired;
use App\Domain\Auth\Exceptions\OtpInvalid;
use App\Domain\Auth\Exceptions\RegistrationsClosed;
use App\Enums\ConsentKind;
use App\Enums\MemberType;
use App\Enums\OtpPurpose;
use App\Enums\UserStatus;
use App\Events\Auth\EmailVerified;
use App\Events\Auth\UserRegistered;
use App\Mail\TemplateMail;
use App\Models\AuditLog;
use App\Models\Commune;
use App\Models\OtpCode;
use App\Models\User;
use App\Models\Zone;
use App\Support\Settings;
use Database\Seeders\EmailTemplatesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/*
 * P2-03 (Q12 c) : compte créé et actif dès la phase 1, e-mail non vérifié, consentements horodatés, code `signup`
 * puis `bienvenue` ; phases 2-3 facultatives (pair → en_attente, RG-03) ; phase 4 consentements facultatifs.
 */

beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, EmailTemplatesSeeder::class, SettingsSeeder::class]);
    Mail::fake();
    config(['mail.default' => 'array']);
});

function registerData(array $overrides = []): RegisterData
{
    return new RegisterData(...array_merge([
        'pseudo' => 'Awa_S',
        'email' => 'Awa@Exemple.bj',
        'password' => 'Xomiton2026!',
        'acceptsTerms' => true,
        'acceptsPrivacy' => true,
        'ip' => '41.79.1.1',
        'userAgent' => 'Mozilla/5.0 (test)',
    ], $overrides));
}

it('crée le compte dès la phase 1 : actif, non vérifié, rôle membre, profil, réglages, consentements, code puis bienvenue', function (): void {
    Event::fake([UserRegistered::class]);

    $user = app(RegisterUser::class)(registerData());

    expect($user->pseudo)->toBe('Awa_S')
        ->and($user->email)->toBe('awa@exemple.bj')
        ->and($user->status)->toBe(UserStatus::Active)
        ->and($user->email_verified_at)->toBeNull()
        ->and(Hash::check('Xomiton2026!', $user->password))->toBeTrue()
        ->and($user->hasRole('membre'))->toBeTrue()
        ->and($user->profile?->member_type)->toBe(MemberType::Member)
        ->and($user->settings?->language)->toBe('fr')
        ->and($user->consents()->count())->toBe(2);

    $terms = $user->consents()->ofKind(ConsentKind::Terms)->granted()->firstOrFail();
    expect($terms->version)->toBe(ConsentVersion::current())
        ->and($terms->getAttribute('ip'))->toBe('41.79.1.1')
        ->and($terms->getAttribute('user_agent'))->toBe('Mozilla/5.0 (test)')
        ->and($user->consents()->ofKind(ConsentKind::Privacy)->granted()->exists())->toBeTrue();

    expect(OtpCode::query()->forEmail('awa@exemple.bj', OtpPurpose::Signup)->valid()->count())->toBe(1);

    $slugs = [];
    Mail::assertSent(TemplateMail::class, function (TemplateMail $mail) use (&$slugs): bool {
        $slugs[] = $mail->templateSlug;

        return $mail->hasTo('awa@exemple.bj');
    });
    expect($slugs)->toBe(['code-verification', 'bienvenue']);

    Event::assertDispatched(UserRegistered::class, fn (UserRegistered $event): bool => $event->user->is($user));
    expect(AuditLog::query()->ofAction('auth.registered')->forSubject($user)->exists())->toBeTrue();
});

it('exige les deux consentements obligatoires, cases distinctes', function (): void {
    expect(fn () => app(RegisterUser::class)(registerData(['acceptsPrivacy' => false])))->toThrow(ConsentRequired::class)
        ->and(fn () => app(RegisterUser::class)(registerData(['acceptsTerms' => false])))->toThrow(ConsentRequired::class)
        ->and(User::query()->count())->toBe(0);
});

it('refuse l’inscription quand l’administration l’a fermée, avec le message configuré', function (): void {
    Settings::set('registrations_open', false);
    Settings::set('reg_closed_msg', 'Fermé pour les fêtes, retour le 3 janvier.');

    expect(fn () => app(RegisterUser::class)(registerData()))->toThrow(RegistrationsClosed::class, 'Fermé pour les fêtes, retour le 3 janvier.');

    try {
        app(RegisterUser::class)(registerData());
    } catch (RegistrationsClosed $e) {
        expect($e->userMessage())->toBe('Fermé pour les fêtes, retour le 3 janvier.')
            ->and($e->status())->toBe(403);
    }

    Settings::set('reg_closed_msg', '');

    try {
        app(RegisterUser::class)(registerData());
    } catch (RegistrationsClosed $e) {
        expect($e->userMessage())->toBe(__('auth.registrations_closed'));
    }
});

it('complète le profil en phases 2-3 et place un·e pair en attente de validation sans fermer la session', function (): void {
    $zone = Zone::factory()->create();
    $commune = Commune::factory()->create(['zone_id' => $zone->id]);
    $user = app(RegisterUser::class)(registerData());

    app(CompleteRegistrationProfile::class)($user, new ProfileData(
        pronouns: 'elle',
        zoneId: $zone->id,
        communeId: $commune->id,
        memberType: MemberType::Peer,
        trustedContactPhone: '97 38 88 47',
        trustedContactName: 'Chantal',
        discreetMode: true,
    ));

    $user->refresh();

    expect($user->status)->toBe(UserStatus::Pending)
        ->and($user->hasRole('pair'))->toBeTrue()
        ->and($user->hasRole('membre'))->toBeFalse()
        ->and($user->zone_id)->toBe($zone->id)
        ->and($user->commune_id)->toBe($commune->id)
        ->and($user->profile?->member_type)->toBe(MemberType::Peer)
        ->and($user->profile?->pronouns)->toBe('elle')
        ->and($user->profile?->discreet_mode)->toBeTrue()
        ->and($user->profile?->trusted_contact_phone)->toBe('+229 01 97 38 88 47')
        ->and($user->profile?->getRawOriginal('trusted_contact_phone'))->not->toContain('97 38 88 47')
        ->and($user->profile?->completion)->toBe(57)
        ->and(AuditLog::query()->ofAction('auth.peer_requested')->forSubject($user)->exists())->toBeTrue();
});

it('laisse un compte membre actif quand les phases facultatives sont passées', function (): void {
    $user = app(RegisterUser::class)(registerData());

    app(CompleteRegistrationProfile::class)($user, new ProfileData);

    expect($user->fresh()?->status)->toBe(UserStatus::Active)
        ->and($user->fresh()?->hasRole('membre'))->toBeTrue()
        ->and($user->profile?->member_type)->toBe(MemberType::Member);
});

it('enregistre les consentements facultatifs, accordés ou refusés, en phase 4', function (): void {
    $user = app(RegisterUser::class)(registerData());

    app(RecordOptionalConsents::class)($user, sensitiveData: true, newsletter: false, ip: '41.79.1.1');

    $sensitive = $user->consents()->ofKind(ConsentKind::SensitiveData)->firstOrFail();
    $newsletter = $user->consents()->ofKind(ConsentKind::Newsletter)->firstOrFail();

    expect($sensitive->granted)->toBeTrue()
        ->and($newsletter->granted)->toBeFalse()
        ->and($user->consents()->count())->toBe(4);
});

it('confirme l’e-mail avec le code signup et refuse un mauvais code', function (): void {
    Event::fake([EmailVerified::class]);
    $user = app(RegisterUser::class)(registerData());
    OtpCode::query()->forEmail($user->email, OtpPurpose::Signup)->update(['code_hash' => OtpCode::hash('654321')]);

    expect(fn () => app(VerifyEmail::class)($user, '000000'))->toThrow(OtpInvalid::class)
        ->and($user->fresh()?->email_verified_at)->toBeNull();

    app(VerifyEmail::class)($user, '654 321');

    expect($user->fresh()?->email_verified_at)->not->toBeNull();
    Event::assertDispatched(EmailVerified::class);
});
