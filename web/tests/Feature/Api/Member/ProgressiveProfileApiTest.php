<?php

declare(strict_types=1);

use App\Enums\ConsentKind;
use App\Models\Consent;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Support\FeedFixtures as F;

/*
 * Profil progressif (P8-08, lot profil ; docs/API.md §3) : PATCH me/profile accepte identité de genre et orientation
 * (listes fermées, chiffrées au repos selon config xomiton.encrypted_columns.profiles) **seulement** sous consentement
 * `sensitive_data` en cours ou accordé dans la même requête ; `prefer_not` efface ; un retrait du consentement efface
 * et retire des champs partagés ; les groupes traités ne sont plus reproposés ; relecture via GET me / me/profile.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->awa = F::member();
    $this->awa->profile->forceFill(['pronouns' => null, 'age_range' => null, 'interests' => null, 'progressive_answered' => null])->save();
});

it('refuse identité de genre et orientation sans consentement (422), sans rien enregistrer', function (): void {
    F::as($this->awa)->patchJson('/api/v1/me/profile', ['gender_identity' => 'femme_cis', 'orientation' => 'lesbienne'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['gender_identity', 'orientation'])
        ->assertJsonPath('errors.gender_identity.0', __('profile.progressive.sensitive_required'));

    expect($this->awa->profile->fresh()->gender_identity)->toBeNull()
        ->and(Consent::query()->where('user_id', $this->awa->id)->count())->toBe(0);
});

it('enregistre genre et orientation chiffrés avec le consentement donné dans la même requête, relus par GET me', function (): void {
    F::as($this->awa)->patchJson('/api/v1/me/profile', ['sensitive_consent' => true, 'gender_identity' => 'non_binaire', 'orientation' => 'queer'])
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.profile.gender_identity', 'non_binaire')
        ->assertJsonPath('data.profile.orientation', 'queer')
        ->assertJsonPath('data.profile.progressive.sensitive_consent', true);

    $row = DB::table('profiles')->where('user_id', $this->awa->id)->first(['gender_identity', 'orientation']);
    expect(config('xomiton.encrypted_columns.profiles'))->toContain('gender_identity', 'orientation')
        ->and($row->gender_identity)->not->toContain('non_binaire')
        ->and($row->orientation)->not->toContain('queer');

    $consent = Consent::query()->where('user_id', $this->awa->id)->sole();
    expect($consent->kind)->toBe(ConsentKind::SensitiveData)->and($consent->granted)->toBeTrue()->and($consent->ip)->toBeNull();

    F::as($this->awa)->getJson('/api/v1/me')
        ->assertJsonPath('data.profile.gender_identity', 'non_binaire')
        ->assertJsonPath('data.profile.orientation', 'queer')
        ->assertJsonPath('data.profile.shared_fields', []);
    F::as($this->awa)->getJson('/api/v1/me/profile')->assertHeader('Cache-Control', 'no-store, private')->assertJsonPath('data.orientation', 'queer');
});

it('accepte un consentement déjà en cours, refuse après un retrait, et le retrait efface les données sensibles', function (): void {
    F::as($this->awa)->patchJson('/api/v1/me/profile', ['sensitive_consent' => true])->assertOk();
    F::as($this->awa)->patchJson('/api/v1/me/profile', ['gender_identity' => 'femme_trans', 'shared_fields' => ['gender_identity', 'ally_status']])->assertOk();

    expect($this->awa->profile->fresh()->shared_fields)->toBe(['gender_identity', 'ally_status']);

    F::as($this->awa)->patchJson('/api/v1/me/profile', ['sensitive_consent' => false])
        ->assertOk()
        ->assertJsonPath('data.profile.gender_identity', null)
        ->assertJsonPath('data.profile.shared_fields', ['ally_status'])
        ->assertJsonPath('data.profile.progressive.sensitive_consent', false);

    expect(Consent::query()->where('user_id', $this->awa->id)->orderBy('id')->pluck('granted')->all())->toBe([true, false]);

    F::as($this->awa)->patchJson('/api/v1/me/profile', ['orientation' => 'bisexuelle'])->assertUnprocessable()->assertJsonValidationErrors('orientation');
    F::as($this->awa)->patchJson('/api/v1/me/profile', ['shared_fields' => ['orientation']])->assertUnprocessable()->assertJsonValidationErrors('shared_fields');
    // Retirer et enregistrer dans la même requête : le retrait l'emporte.
    F::as($this->awa)->patchJson('/api/v1/me/profile', ['sensitive_consent' => false, 'gender_identity' => 'femme_cis'])->assertUnprocessable();
});

it('refuse une valeur hors des listes fermées et un champ partagé inconnu', function (): void {
    F::as($this->awa)->patchJson('/api/v1/me/profile', ['sensitive_consent' => true, 'gender_identity' => 'texte libre', 'shared_fields' => ['trusted_contact_phone']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['gender_identity', 'shared_fields.0']);
});

it('propose un groupe à la fois et ne repropose ni un groupe rempli ni « Je préfère ne pas le dire »', function (): void {
    F::as($this->awa)->getJson('/api/v1/me/profile')
        ->assertJsonPath('data.progressive.next', 'pronouns')
        ->assertJsonPath('data.progressive.done', false)
        ->assertJsonPath('data.progressive.total', 8);

    F::as($this->awa)->patchJson('/api/v1/me/profile', ['pronouns' => 'prefer_not'])->assertOk()
        ->assertJsonPath('data.profile.pronouns', null)
        ->assertJsonPath('data.profile.progressive.next', 'member_type');

    F::as($this->awa)->patchJson('/api/v1/me/profile', ['member_type' => 'membre', 'ally_status' => 'aucun', 'gender_identity' => 'prefer_not', 'orientation' => 'prefer_not'])
        ->assertOk()
        ->assertJsonPath('data.profile.progressive.next', 'age');

    F::as($this->awa)->patchJson('/api/v1/me/profile', ['age_range' => '18-24', 'answered_groups' => ['location']])->assertOk()
        ->assertJsonPath('data.profile.age_range', '18-24')
        ->assertJsonPath('data.profile.progressive.next', 'interests');

    F::as($this->awa)->patchJson('/api/v1/me/profile', ['interests' => []])->assertOk()
        ->assertJsonPath('data.profile.progressive.done', true)
        ->assertJsonPath('data.profile.progressive.answered', 8);

    // « Je préfère ne pas le dire » sur l'âge efface la tranche déjà donnée.
    F::as($this->awa)->patchJson('/api/v1/me/profile', ['age_range' => 'prefer_not'])->assertOk()->assertJsonPath('data.profile.age_range', null);
    expect(User::query()->find($this->awa->id)->profile->completion)->toBeInt();
});

it('ne fait jamais compter les données sensibles dans la complétion', function (): void {
    F::as($this->awa)->patchJson('/api/v1/me/profile', [])->assertOk();
    $before = $this->awa->profile->fresh()->completion;

    F::as($this->awa)->patchJson('/api/v1/me/profile', ['sensitive_consent' => true, 'gender_identity' => 'femme_cis', 'orientation' => 'lesbienne', 'ally_status' => 'allie'])->assertOk();

    expect($this->awa->profile->fresh()->completion)->toBe($before);
});

// --- Revue du lot profil ------------------------------------------------------------------------------------------------

it('ne propose pas le type de membre au personnel et refuse « pair » pour tout le monde, sans toucher aux rôles ni au statut', function (): void {
    $admin = F::member('admin');
    $admin->profile->forceFill(['member_type' => App\Enums\MemberType::Member, 'progressive_answered' => null])->save();

    $groups = collect(F::as($admin)->getJson('/api/v1/me/profile')->assertOk()->json('data.progressive.groups'))->pluck('answered', 'key');
    expect($groups['member_type'])->toBeTrue();

    F::as($admin)->patchJson('/api/v1/me/profile', ['member_type' => 'pair'])->assertUnprocessable()->assertJsonValidationErrors('member_type');

    $admin = User::query()->findOrFail($admin->id);
    expect($admin->getRoleNames()->all())->toBe(['admin'])
        ->and($admin->status)->toBe(App\Enums\UserStatus::Active);

    // Un compte « membre » ne s'attribue pas non plus le rôle pair (décision client du 2026-10-29).
    F::as($this->awa)->patchJson('/api/v1/me/profile', ['member_type' => 'pair'])->assertUnprocessable();
    expect($this->awa->fresh()->hasRole('pair'))->toBeFalse()->and($this->awa->fresh()->status)->toBe(App\Enums\UserStatus::Active);
});

it('règle « Afficher ma zone » depuis le groupe Ville du bandeau', function (): void {
    $zone = App\Models\Zone::factory()->create();

    F::as($this->awa)->patchJson('/api/v1/me/profile', ['zone_id' => $zone->id, 'show_zone' => false])->assertOk()
        ->assertJsonPath('data.profile.show_zone', false);
    expect($this->awa->profile->fresh()->show_zone)->toBeFalse();

    F::as($this->awa)->patchJson('/api/v1/me/profile', ['show_zone' => 'peut-être'])->assertUnprocessable()->assertJsonValidationErrors('show_zone');
});

it('ne sert genre, orientation et profil progressif qu’à GET me et me/profile, jamais aux réponses de réglages', function (): void {
    profileConsentFor($this->awa);
    $this->awa->profile->forceFill(['gender_identity' => 'non_binaire'])->save();

    F::as($this->awa)->getJson('/api/v1/me')->assertOk()
        ->assertJsonPath('data.profile.gender_identity', 'non_binaire')
        ->assertJsonPath('data.profile.progressive.total', 8);

    $settings = F::as($this->awa)->patchJson('/api/v1/me/settings', ['theme' => 'dark'])->assertOk();
    expect($settings->json('data.profile'))->not->toHaveKey('gender_identity')
        ->and($settings->json('data.profile'))->not->toHaveKey('progressive')
        ->and($settings->getContent())->not->toContain('non_binaire');
});

function profileConsentFor(User $user): void
{
    Consent::query()->create(['user_id' => $user->id, 'kind' => ConsentKind::SensitiveData, 'version' => '1', 'granted' => true]);
}
