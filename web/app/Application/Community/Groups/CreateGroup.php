<?php

declare(strict_types=1);

namespace App\Application\Community\Groups;

use App\Application\Community\Data\GroupDraft;
use App\Application\Community\UniqueSlug;
use App\Application\Feed\BadWordFilter;
use App\Domain\Feed\Exceptions\ContentBlocked;
use App\Enums\GroupRole;
use App\Enums\MembershipStatus;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/**
 * Créer un groupe (P8-02, groups.php l.20-31) : filtre de mots sur le nom et la description (terme bloquant → 422
 * `content_blocked`), slug unique, la personne qui crée en est propriétaire **et** admin active (compteur à 1),
 * couverture de marque par défaut (`Group::DEFAULT_COVER`), journal `group.created` (et `group.words_flagged` avec les
 * termes « à surveiller » trouvés, pour la relecture de P12-05).
 */
final class CreateGroup
{
    public function __construct(private readonly BadWordFilter $filter) {}

    /**
     * @throws ContentBlocked
     */
    public function __invoke(User $owner, GroupDraft $draft): Group
    {
        $verdict = $this->filter->assertAllowed(['name' => $draft->name, 'description' => $draft->description]);

        /** @var Group $group */
        $group = DB::transaction(static function () use ($owner, $draft): Group {
            $group = Group::query()->create([
                'name' => trim($draft->name),
                'slug' => UniqueSlug::for(Group::class, $draft->name),
                'description' => $draft->description !== null && trim($draft->description) !== '' ? trim($draft->description) : null,
                'privacy' => $draft->privacy,
                'zone_id' => $draft->zoneId,
                'owner_id' => $owner->getKey(),
                'members_count' => 1,
                'posts_count' => 0,
                'is_active' => true,
            ]);

            GroupMember::query()->create([
                'group_id' => $group->getKey(),
                'user_id' => $owner->getKey(),
                'role' => GroupRole::Admin,
                'status' => MembershipStatus::Active,
                'joined_at' => now(),
            ]);

            return $group;
        });

        Audit::log('group.created', $group, null, $owner);

        // Terme « à surveiller » : le groupe reste créé ; l'entrée `group.words_flagged` sera relue par l'écran Réseau
        // social (P12-05), `moderation_reports` ne visant encore que publications, commentaires, messages et comptes.
        if ($verdict->isFlagged()) {
            Audit::log('group.words_flagged', $group, (string) __('feed.moderation.auto_details', ['terms' => implode(', ', $verdict->flaggingTerms)]), $owner);
        }

        return $group;
    }
}
