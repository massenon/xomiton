<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Comment;
use App\Models\OtpCode;
use App\Models\Post;
use App\Models\PushSubscription;
use App\Models\SyncDevice;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/*
 * `accounts:anonymize` (P1-14) : comptes supprimés logiquement depuis plus de 30 jours anonymisés une seule
 * fois (pseudo membre-supprimé-<id>, e-mail supprime-<id>@anonyme.invalid), contenus conservés.
 */

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::create(2026, 10, 17, 3));
    config(['xomiton.accounts.deletion_grace_days' => 30]);
    Storage::fake('public');
});

/** Compte complet supprimé logiquement il y a `$daysAgo` jours. */
function deletedAccount(int $daysAgo): User
{
    Storage::disk('public')->put('avatars/perso.png', 'png');

    $user = User::factory()
        ->withProfile(
            ['gender_identity' => 'femme trans', 'orientation' => 'lesbienne', 'bio' => 'Ma bio', 'avatar_path' => 'avatars/perso.png',
                'trusted_contact_name' => 'Maman', 'trusted_contact_phone' => '+229 01 00 00 00 00', 'website' => 'https://awa.test', 'completion' => 80],
            ['theme' => 'dark', 'pin_enabled' => true, 'pin_hash' => Hash::make('1234'), 'notification_digest' => 'weekly'],
        )
        ->create(['pseudo' => 'awa_'.$daysAgo, 'name' => 'Awa Sossou', 'phone' => '+229 01 55 12 34 56', 'email' => "awa{$daysAgo}@example.test"]);

    $user->createToken('mobile');
    PushSubscription::factory()->forUser($user)->create();
    SyncDevice::factory()->for($user)->create();
    OtpCode::factory()->create(['email' => $user->email]);
    DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => 'x', 'created_at' => now()]);
    DB::table('sessions')->insert(['id' => 'sess-'.$daysAgo, 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->getTimestamp()]);

    $user->delete();
    User::withTrashed()->whereKey($user->id)->update(['deleted_at' => now()->subDays($daysAgo)]);

    return $user->fresh();
}

it('anonymise un compte supprimé depuis plus de 30 jours et conserve ses contenus', function (): void {
    $user = deletedAccount(31);
    $post = Post::factory()->for($user)->create();
    $comment = Comment::factory()->for($user)->create();
    $recentlyDeleted = deletedAccount(10);
    $active = User::factory()->withProfile()->create();

    $this->artisan('accounts:anonymize')
        ->expectsOutputToContain('1 compte anonymisé')
        ->assertSuccessful();

    $user = User::withTrashed()->findOrFail($user->id);

    expect($user->pseudo)->toBe('membre-supprimé-'.$user->id)
        ->and($user->email)->toBe('supprime-'.$user->id.'@anonyme.invalid')
        ->and($user->isAnonymized())->toBeTrue()
        ->and($user->name)->toBeNull()
        ->and($user->phone)->toBeNull()
        ->and($user->remember_token)->toBeNull()
        ->and(Hash::check('password', $user->password))->toBeFalse()
        ->and($user->deleted_at)->not->toBeNull()
        ->and($user->tokens()->count())->toBe(0)
        ->and($user->pushSubscriptions()->count())->toBe(0)
        ->and($user->syncDevices()->where('is_revoked', false)->count())->toBe(0)
        ->and($user->syncDevices()->count())->toBe(1)
        ->and(OtpCode::query()->where('email', 'awa31@example.test')->count())->toBe(0)
        ->and(DB::table('password_reset_tokens')->where('email', 'awa31@example.test')->count())->toBe(0)
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);

    $profile = $user->profile;

    expect($profile->gender_identity)->toBeNull()
        ->and($profile->orientation)->toBeNull()
        ->and($profile->bio)->toBeNull()
        ->and($profile->avatar_path)->toBeNull()
        ->and($profile->trusted_contact_name)->toBeNull()
        ->and($profile->trusted_contact_phone)->toBeNull()
        ->and($profile->website)->toBeNull()
        ->and($profile->completion)->toBe(0)
        ->and($profile->searchable)->toBeFalse()
        ->and(Storage::disk('public')->exists('avatars/perso.png'))->toBeFalse();

    $settings = $user->settings;

    expect($settings->theme)->toBe('auto')
        ->and($settings->pin_enabled)->toBeFalse()
        ->and($settings->pin_hash)->toBeNull()
        ->and($settings->notification_digest)->toBe('never');

    // Publications et commentaires conservés, désormais sous le pseudo anonymisé.
    expect($post->fresh()->user_id)->toBe($user->id)
        ->and($comment->fresh()->user_id)->toBe($user->id)
        ->and($post->fresh()->user()->withTrashed()->firstOrFail()->pseudo)->toBe('membre-supprimé-'.$user->id);

    // Action sensible tracée dans le journal d'audit, sans donnée personnelle.
    $audit = AuditLog::query()->ofAction('account.anonymize')->forSubject($user)->firstOrFail();

    expect(AuditLog::query()->ofAction('account.anonymize')->count())->toBe(1)
        ->and($audit->detail)->toContain('délai de grâce de 30 jours')
        ->and($audit->detail)->not->toContain('awa31@example.test');

    // Compte supprimé depuis moins de 30 jours et compte actif : intacts.
    $recentlyDeleted = User::withTrashed()->findOrFail($recentlyDeleted->id);

    expect($recentlyDeleted->email)->toBe('awa10@example.test')
        ->and($recentlyDeleted->name)->toBe('Awa Sossou')
        ->and($recentlyDeleted->profile->bio)->toBe('Ma bio')
        ->and($recentlyDeleted->tokens()->count())->toBe(1)
        ->and($active->fresh()->email)->toBe($active->email);
});

it('n’anonymise jamais deux fois le même compte', function (): void {
    $user = deletedAccount(45);

    $this->artisan('accounts:anonymize')->expectsOutputToContain('1 compte anonymisé')->assertSuccessful();

    $anonymized = User::withTrashed()->findOrFail($user->id);
    $password = $anonymized->password;
    $updatedAt = $anonymized->updated_at;

    Carbon::setTestNow(now()->addDays(40));

    $this->artisan('accounts:anonymize')->expectsOutputToContain('0 compte anonymisé')->assertSuccessful();

    $again = User::withTrashed()->findOrFail($user->id);

    expect($again->password)->toBe($password)
        ->and($again->updated_at->equalTo($updatedAt))->toBeTrue()
        ->and(User::withTrashed()->anonymized()->count())->toBe(1)
        ->and(AuditLog::query()->ofAction('account.anonymize')->count())->toBe(1);
});

it('compte sans modifier en simulation', function (): void {
    deletedAccount(60);

    $this->artisan('accounts:anonymize', ['--dry-run' => true])
        ->expectsOutputToContain('Simulation : 1 compte à anonymiser')
        ->assertSuccessful();

    expect(User::withTrashed()->anonymized()->count())->toBe(0);
});
