<?php

declare(strict_types=1);

use App\Application\Account\PersonalDataExport;
use App\Application\Admin\Registrations\RegistrationSummary;
use App\Application\Auth\Listeners\NotifyValidatorsOfPendingAccount;
use App\Application\Auth\RegisterUser;
use App\Application\Retention\AnonymizeDeletedAccounts;
use App\Enums\UserStatus;
use App\Livewire\Admin\Registrations\Index as RegistrationsScreen;
use App\Models\DatabaseNotification;
use App\Models\User;
use App\Notifications\PeerPending;
use App\Support\Settings;
use Database\Seeders\EmailTemplatesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Support\AlertFixtures;
use Tests\Support\RegistrationFixtures as R;

/*
 * Inscription en 3 étapes (11/11/2026) côté équipe : l'administration est informée de CHAQUE compte inscrit (web et API),
 * l'écran Inscriptions montre ce qui sert à valider (jamais l'orientation ni l'identité de genre), et les nouveaux champs
 * sont exportés (RGPD) puis effacés à l'anonymisation du compte.
 */

beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, EmailTemplatesSeeder::class, SettingsSeeder::class]);
    Mail::fake();
    config(['mail.default' => 'array']);
});

it('prévient les validateurs (cloche) de chaque inscription web : pseudo, type et lien vers l’écran Inscriptions', function (): void {
    $admin = AlertFixtures::admin();
    $moderator = AlertFixtures::member('moderateur');

    $this->post('/inscription', R::payload())->assertRedirect(route('fil'));
    auth('web')->logout();
    $this->flushSession();
    $this->post('/inscription', R::allyPayload(['pseudo' => 'Maitre_B', 'email' => 'maitre@exemple.bj', 'ally_role' => 'avocat']))->assertRedirect(route('fil'));

    $rows = DatabaseNotification::query()->where('notifiable_id', $admin->id)->orderBy('id')->get();
    expect($rows)->toHaveCount(2)
        ->and($rows[0]->data['body'])->toContain('Awa_S')->toContain('membre')
        ->and($rows[1]->data['body'])->toContain('Maitre_B')->toContain('allié·e, avocat·e')
        ->and($rows[1]->data['url'])->toBe(route('admin.inscriptions'))
        ->and($rows[1]->data['body'])->not->toContain('lesbienne')
        ->and(DatabaseNotification::query()->where('notifiable_id', $moderator->id)->exists())->toBeFalse();
});

it('prévient aussi les validateurs pour une inscription par l’API, par cloche et push selon les préférences', function (): void {
    Notification::fake();
    $admin = AlertFixtures::admin();

    $this->postJson('/api/v1/auth/register', R::payload())->assertCreated();

    $pseudo = 'Awa_S';
    Notification::assertSentTo($admin, PeerPending::class, function (PeerPending $n) use ($admin, $pseudo): bool {
        $push = $n->toPush($admin);

        return $n->pseudo === $pseudo && $n->kind === 'membre'
            && in_array('database', $n->via($admin), true)
            && $push->data['type'] === 'peer_pending'
            && $n->toArray($admin)['url'] === route('admin.inscriptions');
    });
});

it('ne prévient personne pour un compte actif d’emblée (mode peers) et masque le texte en mode discret', function (): void {
    Notification::fake();
    $admin = AlertFixtures::admin();
    Settings::set('reg_manual_validation', 'peers');

    $this->postJson('/api/v1/auth/register', R::payload())->assertCreated();
    Notification::assertNothingSentTo($admin);

    $admin->profile->forceFill(['discreet_mode' => true])->save();
    $masked = (new PeerPending(7, 'Awa_S', 'allié·e, avocat·e'))->toArray($admin->fresh());
    expect($masked['body'])->not->toContain('Awa_S')->and($masked['url'])->toBe(route('admin.inscriptions'));
});

it('nomme le type : membre, allié·e sans statut, allié·e et son statut', function (): void {
    $member = app(RegisterUser::class)(R::data());
    $ally = app(RegisterUser::class)(R::data(R::allyPayload(['pseudo' => 'Allie_1', 'email' => 'a1@exemple.bj', 'ally_role' => 'psychologue'])));

    expect(NotifyValidatorsOfPendingAccount::kindOf($member))->toBe('membre')
        ->and(NotifyValidatorsOfPendingAccount::kindOf($ally))->toBe('allié·e, psychologue');

    $ally->profile->forceFill(['ally_role' => null])->save();
    expect(NotifyValidatorsOfPendingAccount::kindOf($ally->fresh()))->toBe('allié·e');
});

it('montre à l’écran Inscriptions prénom, nom, pseudo, e-mail, âge, pays, ville, téléphone, type et statut d’allié·e, jamais orientation ni identité de genre', function (): void {
    $member = app(RegisterUser::class)(R::data(['pseudo' => 'Membre_1', 'email' => 'membre1@exemple.bj', 'city' => 'Cotonou', 'phone' => '97 38 88 47', 'orientation' => 'bisexuelle', 'gender_identity' => 'non_binaire']));
    $ally = app(RegisterUser::class)(R::data(R::allyPayload(['pseudo' => 'Allie_2', 'email' => 'allie2@exemple.bj', 'first_name' => 'Chantal', 'last_name' => 'Dossou', 'age' => 41, 'country_code' => 'FR', 'phone' => '+33 6 12 34 56 78', 'ally_role' => 'autre', 'ally_role_other' => 'Formatrice'])));

    $admin = AlertFixtures::admin();
    $this->actingAs($admin);
    $html = Livewire::test(RegistrationsScreen::class)
        ->assertSee('Membre_1')->assertSee('Awa Sossou')->assertSee('membre1@exemple.bj')->assertSee('24 ans')->assertSee('Cotonou, Bénin')->assertSee('+229 01 97 38 88 47')->assertSee('Membre de la communauté')
        ->assertSee('Allie_2')->assertSee('Chantal Dossou')->assertSee('41 ans')->assertSee('France')->assertSee('+33612345678')
        ->assertSee('Personne ressource / allié·e')->assertSee(__('admin.registrations.details.ally_role', ['role' => 'Autre']))->assertSee('(Formatrice)')
        ->html();

    // Données sensibles chiffrées, inutiles à la validation : jamais à l'écran.
    foreach (['bisexuelle', 'Bisexuel', 'non_binaire', 'Non binaire', 'femme_cis', 'lesbienne', 'Lesbienne'] as $secret) {
        expect($html)->not->toContain($secret);
    }

    $summary = RegistrationSummary::of($ally);
    expect(array_keys($summary))->toBe(['name', 'age', 'place', 'phone', 'is_ally', 'ally_role', 'ally_role_other'])
        ->and($summary['is_ally'])->toBeTrue();
});

it('valide un compte depuis l’écran : actif et e-mail confirmé par l’équipe', function (): void {
    Notification::fake();
    $user = app(RegisterUser::class)(R::data());
    $this->actingAs(AlertFixtures::admin());

    Livewire::test(RegistrationsScreen::class)->call('approve', $user->id)->assertHasNoErrors();

    expect($user->fresh()->status)->toBe(UserStatus::Active)->and($user->fresh()->email_verified_at)->not->toBeNull();
});

it('exporte les nouveaux champs (RGPD) puis n’en laisse rien à l’anonymisation du compte', function (): void {
    $user = app(RegisterUser::class)(R::data(['city' => 'Cotonou', 'gender_identity' => 'autre', 'gender_identity_other' => 'Genderfluid', 'orientation' => 'autre', 'orientation_other' => 'Demi']));
    $ally = app(RegisterUser::class)(R::data(R::allyPayload(['pseudo' => 'Allie_3', 'email' => 'allie3@exemple.bj', 'ally_role' => 'autre', 'ally_role_other' => 'Formatrice'])));

    $export = app(PersonalDataExport::class)->collect($user->fresh());
    expect($export['account']['first_name'])->toBe('Awa')
        ->and($export['account']['last_name'])->toBe('Sossou')
        ->and($export['profile']['age'])->toBe(24)
        ->and($export['profile']['country_code'])->toBe('BJ')
        ->and($export['profile']['city'])->toBe('Cotonou')
        ->and($export['profile']['gender_identity_other'])->toBe('Genderfluid')
        ->and($export['profile']['orientation_other'])->toBe('Demi');
    expect(app(PersonalDataExport::class)->collect($ally->fresh())['profile']['ally_role_other'])->toBe('Formatrice');

    foreach ([$user, $ally] as $account) {
        app(AnonymizeDeletedAccounts::class)->anonymize($account->fresh());
    }

    foreach ([$user, $ally] as $account) {
        $raw = DB::table('profiles')->where('user_id', $account->id)->first();
        $identity = DB::table('users')->where('id', $account->id)->first();

        foreach (['age', 'age_range', 'country_code', 'city', 'gender_identity', 'gender_identity_other', 'orientation', 'orientation_other', 'ally_role', 'ally_role_other'] as $column) {
            expect($raw->{$column})->toBeNull();
        }
        expect($identity->first_name)->toBeNull()->and($identity->last_name)->toBeNull()->and($identity->name)->toBeNull();
    }

    expect(User::query()->whereKey($user->id)->value('pseudo'))->toBe(User::anonymizedPseudo($user->id));
});
