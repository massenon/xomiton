<?php

declare(strict_types=1);

use App\Enums\AgeRange;
use App\Enums\ConsentKind;
use App\Enums\MemberType;
use App\Enums\OtpPurpose;
use App\Enums\ProfileVisibility;
use App\Enums\UserStatus;
use App\Models\Consent;
use App\Models\OtpCode;
use App\Models\Profile;
use App\Models\User;
use App\Models\UserBlock;
use App\Models\UserSetting;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/*
 * Lot 2 « identity » : une création par factory et une assertion métier par table
 * (unicité, cast chiffré, scope). Nécessite l'intégration des lots pour RefreshDatabase.
 */

describe('users', function (): void {
    it('crée un compte actif avec pseudo unique et enums castés', function (): void {
        $user = User::factory()->create(['pseudo' => 'awa_test']);

        expect($user->status)->toBe(UserStatus::Active)
            ->and($user->status->label())->toBe('Actif')
            ->and($user->status->badge())->toBe('success')
            ->and(Hash::check('password', $user->password))->toBeTrue();

        expect(fn () => User::factory()->create(['pseudo' => 'awa_test']))->toThrow(QueryException::class);
    });

    it('filtre par statut et masque les données privées', function (): void {
        User::factory()->create();
        User::factory()->pending()->create();
        User::factory()->suspended('Propos haineux')->create();

        expect(User::active()->count())->toBe(1)
            ->and(User::pending()->count())->toBe(1)
            ->and(User::suspended()->count())->toBe(1);

        $array = User::active()->first()->toArray();
        expect($array)->not->toHaveKeys(['name', 'phone', 'password', 'suspension_reason']);
    });

    it('supprime logiquement et fournit un avatar par défaut', function (): void {
        $user = User::factory()->create();

        expect($user->avatar_url)->toEndWith('assets/images/xomiton/avatar1.png')
            ->and(is_file(public_path(User::DEFAULT_AVATAR)))->toBeTrue();

        // Fichier absent du disque public ou de public/ : repli sur l'avatar par défaut, jamais de 404.
        Storage::fake('public');
        $profile = Profile::factory()->for($user)->create(['avatar_path' => 'avatars/inexistant.png']);

        expect($user->fresh()->avatar_url)->toEndWith(User::DEFAULT_AVATAR);

        Storage::disk('public')->put('avatars/perso.png', 'png');
        $profile->update(['avatar_path' => 'avatars/perso.png']);

        expect($user->fresh()->avatar_url)->toEndWith('/storage/avatars/perso.png');

        $profile->update(['avatar_path' => 'assets/images/xomiton/avatar2.png']);

        expect($user->fresh()->avatar_url)->toEndWith('assets/images/xomiton/avatar2.png');

        $user->delete();

        expect(User::count())->toBe(0)
            ->and(User::withTrashed()->count())->toBe(1);
    });
});

describe('profiles', function (): void {
    it('chiffre les données sensibles et caste les enums', function (): void {
        $profile = Profile::factory()->withSensitiveData()->create(['orientation' => 'lesbienne', 'age_range' => '18-24']);

        $raw = DB::table('profiles')->where('id', $profile->id)->value('orientation');

        expect($raw)->not->toBe('lesbienne')
            ->and($profile->fresh()->orientation)->toBe('lesbienne')
            ->and($profile->age_range)->toBe(AgeRange::From18To24)
            ->and($profile->member_type)->toBe(MemberType::Member)
            ->and($profile->visibility)->toBe(ProfileVisibility::Community)
            ->and($profile->show_zone)->toBeTrue()
            ->and($profile->searchable)->toBeTrue()
            ->and($profile->toArray())->not->toHaveKeys(['orientation', 'gender_identity', 'trusted_contact_phone']);
    });

    it('expose les quatre visibilités et les quatre types de membre du dictionnaire', function (): void {
        Profile::factory()->publiclyVisible()->create();
        Profile::factory()->create();
        Profile::factory()->discreet()->create();
        Profile::factory()->ally()->create(['visibility' => ProfileVisibility::Followers]);

        expect(array_column(ProfileVisibility::cases(), 'value'))->toBe(['public', 'community', 'followers', 'private'])
            ->and(array_column(MemberType::cases(), 'value'))->toBe(['membre', 'pair', 'allie', 'ressource'])
            ->and(MemberType::Ally->label())->toBe('Allié·e')
            ->and(ProfileVisibility::Public->badge())->toBe('success')
            ->and(Profile::visibleToCommunity()->count())->toBe(2)
            ->and(Profile::publiclyVisible()->count())->toBe(1)
            ->and(Profile::searchable()->count())->toBe(3);
    });

    it('impose un seul profil par compte et expose le scope des pair·es', function (): void {
        $user = User::factory()->peer()->create();

        expect(Profile::peers()->count())->toBe(1)
            ->and($user->profile->isPeer())->toBeTrue()
            ->and($user->avatar_url)->toEndWith('avatar1.png');

        expect(fn () => Profile::factory()->create(['user_id' => $user->id]))->toThrow(QueryException::class);
    });
});

describe('user_settings', function (): void {
    it('stocke les préférences en JSON et verrouille sécurité et SOS', function (): void {
        $settings = UserSetting::factory()->create([
            'notification_prefs' => ['communaute' => ['in_app' => false, 'email' => false, 'push' => false]],
        ]);

        expect($settings->notification_prefs)->toBeArray()
            ->and($settings->wants('communaute', 'email'))->toBeFalse()
            ->and($settings->wants('sos', 'push'))->toBeTrue()
            ->and(UserSetting::DIGESTS)->toBe(['never', 'daily', 'weekly'])
            ->and($settings->notification_digest)->toBe(UserSetting::DIGEST_NEVER)
            ->and($settings->wantsDigest())->toBeFalse()
            ->and($settings->sos_share_gps)->toBeTrue()
            ->and($settings->toArray())->not->toHaveKey('pin_hash');

        $discreet = UserSetting::factory()->discreet()->weeklyDigest()->create();

        expect($discreet->sos_share_gps)->toBeFalse()
            ->and($discreet->notification_digest)->toBe('weekly')
            ->and($discreet->wantsDigest())->toBeTrue()
            ->and(Hash::check('1234', $discreet->pin_hash))->toBeTrue();

        expect(fn () => UserSetting::factory()->create(['user_id' => $settings->user_id]))->toThrow(QueryException::class);
    });
});

describe('otp_codes', function (): void {
    it('hache le code, ne garde que created_at et filtre les codes valides', function (): void {
        $otp = OtpCode::factory()->withCode('123456')->create(['email' => 'test@xomiton.bj']);
        OtpCode::factory()->expired()->create(['email' => 'test@xomiton.bj']);
        OtpCode::factory()->consumed()->create(['email' => 'test@xomiton.bj']);

        expect($otp->matches('123456'))->toBeTrue()
            ->and($otp->matches('654321'))->toBeFalse()
            ->and($otp->code_hash)->toHaveLength(64)
            ->and($otp->purpose)->toBe(OtpPurpose::Login)
            ->and($otp->created_at)->not->toBeNull()
            ->and(OtpCode::valid()->forEmail('test@xomiton.bj', OtpPurpose::Login)->count())->toBe(1);
    });
});

describe('consents', function (): void {
    it('journalise un consentement par événement, pour un compte ou un·e visiteur·se', function (): void {
        $user = User::factory()->create();
        Consent::factory()->ofKind(ConsentKind::Terms)->create(['user_id' => $user->id, 'version' => '1.0']);
        Consent::factory()->ofKind(ConsentKind::Terms)->withdrawn()->create(['user_id' => $user->id, 'version' => '1.1']);
        $visitor = Consent::factory()->visitor('lectrice@exemple.bj')->create();

        $latest = Consent::latestFor($user, ConsentKind::Terms)->first();

        expect($latest->granted)->toBeFalse()
            ->and($latest->version)->toBe('1.1')
            ->and($user->consents()->ofKind(ConsentKind::Terms)->granted()->count())->toBe(1)
            ->and($visitor->user_id)->toBeNull()
            ->and($visitor->kind)->toBe(ConsentKind::Newsletter)
            ->and($visitor->toArray())->not->toHaveKeys(['ip', 'user_agent']);
    });
});

describe('user_blocks', function (): void {
    it('bloque un compte une seule fois et retrouve le blocage dans les deux sens', function (): void {
        $block = UserBlock::factory()->create();

        expect(UserBlock::between($block->blocked_user_id, $block->user_id)->exists())->toBeTrue()
            ->and($block->user->blockedUsers()->count())->toBe(1)
            ->and($block->blockedUser->blockedByUsers()->count())->toBe(1)
            ->and($block->created_at)->not->toBeNull();

        expect(fn () => UserBlock::factory()->create([
            'user_id' => $block->user_id,
            'blocked_user_id' => $block->blocked_user_id,
        ]))->toThrow(QueryException::class);
    });

    it('supprime les blocages avec le compte', function (): void {
        $block = UserBlock::factory()->create();

        $block->blockedUser->forceDelete();

        expect(UserBlock::count())->toBe(0);
    });
});

describe('seeders', function (): void {
    it('crée les rôles, les permissions module.action et les comptes de démonstration', function (): void {
        Storage::fake('public');

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(DemoAccountsSeeder::class);
        $this->seed(DemoAccountsSeeder::class);

        $admin = User::where('email', 'admin@xomiton.bj')->firstOrFail();
        $peer = User::where('email', 'pair@xomiton.bj')->firstOrFail();
        $member = User::where('email', 'membre@xomiton.bj')->firstOrFail();

        expect(User::count())->toBe(count(DemoAccountsSeeder::accounts()))
            ->and($admin->hasRole('superadmin'))->toBeTrue()
            ->and($admin->can('roles.assign_admin'))->toBeTrue()
            ->and($admin->can('admin.access'))->toBeTrue()
            ->and($peer->hasRole('pair'))->toBeTrue()
            ->and($peer->can('cases.create'))->toBeTrue()
            ->and($peer->can('admin.access'))->toBeFalse()
            ->and($peer->profile->member_type)->toBe(MemberType::Peer)
            ->and($member->pseudo)->toBe('awa_s')
            ->and($member->settings)->not->toBeNull()
            ->and($member->settings->notification_digest)->toBe(UserSetting::DIGEST_WEEKLY)
            ->and($member->settings->sos_share_gps)->toBeTrue()
            ->and($member->profile->visibility)->toBe(ProfileVisibility::Community)
            ->and($member->profile->searchable)->toBeTrue()
            ->and(Storage::disk('public')->exists('avatars/avatar3.png'))->toBeTrue()
            ->and($member->avatar_url)->toEndWith('/storage/avatars/avatar3.png')
            ->and(Hash::check('Membre123!', $member->password))->toBeTrue();
    });
});

describe('relations inverses', function (): void {
    it('expose depuis User les relations vers chaque lot, vides pour un compte neuf', function (): void {
        $user = User::factory()->create();

        $hasMany = [
            'consents', 'blocks', 'thematiques', 'assistantConversations', 'posts', 'comments', 'invitationsSent',
            'invitationsReceived', 'groupMemberships', 'albums', 'organizedEvents', 'eventRsvps', 'forumTopics',
            'messages', 'alerts', 'acknowledgedAlerts', 'syncDevices', 'collectedCases', 'auditLogs', 'pushSubscriptions',
        ];
        $belongsToMany = ['blockedUsers', 'blockedByUsers', 'likedPosts', 'savedPosts', 'following', 'followers', 'groups', 'followedPages', 'conversations'];
        $hasOne = ['profile', 'settings', 'directoryContact', 'newsletterSubscriber'];

        foreach ($hasMany as $relation) {
            expect($user->{$relation}())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\HasMany::class, $relation)
                ->and($user->{$relation}()->count())->toBe(0, $relation);
        }

        foreach ($belongsToMany as $relation) {
            expect($user->{$relation}())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsToMany::class, $relation)
                ->and($user->{$relation}()->count())->toBe(0, $relation);
        }

        foreach ($hasOne as $relation) {
            expect($user->{$relation}())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\HasOne::class, $relation)
                ->and($user->{$relation})->toBeNull($relation);
        }
    });

    it('retrouve les enregistrements liés par les bonnes clés étrangères', function (): void {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $post = App\Models\Post::factory()->for($user)->create();
        App\Models\PostLike::factory()->create(['post_id' => $post->id, 'user_id' => $other->id]);
        App\Models\PostSave::factory()->create(['post_id' => $post->id, 'user_id' => $other->id]);
        App\Models\Follow::factory()->create(['follower_id' => $other->id, 'followed_id' => $user->id]);
        App\Models\Invitation::factory()->create(['from_user_id' => $user->id, 'to_user_id' => $other->id]);
        App\Models\GroupMember::factory()->create(['user_id' => $user->id]);
        App\Models\PageFollower::factory()->create(['user_id' => $user->id]);
        App\Models\Event::factory()->create(['organizer_id' => $user->id]);
        App\Models\ConversationParticipant::factory()->create(['user_id' => $user->id]);
        App\Models\Alert::factory()->create(['user_id' => $user->id, 'is_anonymous' => false]);
        App\Models\Alert::factory()->create(['acknowledged_by' => $user->id]);
        App\Models\CaseRecord::factory()->create(['collected_by' => $user->id]);
        App\Models\DirectoryContact::factory()->create(['user_id' => $user->id]);
        App\Models\NewsletterSubscriber::factory()->create(['user_id' => $user->id]);
        App\Models\Thematique::factory()->create(['created_by' => $user->id]);
        App\Models\Album::factory()->for($user)->create();

        expect($user->posts()->count())->toBe(1)
            ->and($other->likedPosts()->count())->toBe(1)
            ->and($other->savedPosts()->count())->toBe(1)
            ->and($user->followers()->count())->toBe(1)
            ->and($other->following()->first()->is($user))->toBeTrue()
            ->and($user->invitationsSent()->count())->toBe(1)
            ->and($other->invitationsReceived()->count())->toBe(1)
            ->and($user->groups()->count())->toBe(1)
            ->and($user->groupMemberships()->count())->toBe(1)
            ->and($user->followedPages()->count())->toBe(1)
            ->and($user->organizedEvents()->count())->toBe(1)
            ->and($user->conversations()->count())->toBe(1)
            ->and($user->alerts()->count())->toBe(1)
            ->and($user->acknowledgedAlerts()->count())->toBe(1)
            ->and($user->collectedCases()->count())->toBe(1)
            ->and($user->directoryContact)->not->toBeNull()
            ->and($user->newsletterSubscriber)->not->toBeNull()
            ->and($user->thematiques()->count())->toBe(1)
            ->and($user->albums()->count())->toBe(1);
    });
});
