<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\ProfileVisibility;
use App\Models\Conversation;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Données de test de la messagerie (P8-01) : conversations créées par l'API (comme l'écran), envoi avec `client_uuid`,
 * blocage, profil privé. Suppose RolesAndPermissionsSeeder exécuté.
 */
final class MessagingFixtures
{
    public static function member(string $role = 'membre', bool $verified = true): User
    {
        return FeedFixtures::member($role, $verified);
    }

    public static function privateConversation(User $a, User $b): Conversation
    {
        $id = FeedFixtures::as($a)->postJson('/api/v1/conversations', ['kind' => 'private', 'user_id' => $b->id])->json('data.id');

        return Conversation::query()->where('ulid', $id)->firstOrFail();
    }

    /**
     * @param  list<User>  $others
     */
    public static function groupConversation(User $creator, array $others, string $title = 'Cercle des sœurs'): Conversation
    {
        $id = FeedFixtures::as($creator)->postJson('/api/v1/conversations', [
            'kind' => 'group', 'title' => $title, 'user_ids' => array_map(fn (User $u) => $u->id, $others),
        ])->json('data.id');

        return Conversation::query()->where('ulid', $id)->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public static function send(User $author, Conversation $conversation, string $body = 'Bonjour', ?string $uuid = null, array $extra = []): TestResponse
    {
        return FeedFixtures::as($author)->postJson('/api/v1/conversations/'.$conversation->ulid.'/messages', [
            'body' => $body, 'client_uuid' => $uuid ?? (string) Str::uuid(),
        ] + $extra);
    }

    public static function block(User $blocker, User $blocked): void
    {
        UserBlock::query()->create(['user_id' => $blocker->id, 'blocked_user_id' => $blocked->id]);
    }

    public static function privateProfile(User $user): void
    {
        $user->profile->forceFill(['visibility' => ProfileVisibility::Private])->save();
    }
}
