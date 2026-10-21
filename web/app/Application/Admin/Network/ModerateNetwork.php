<?php

declare(strict_types=1);

namespace App\Application\Admin\Network;

use App\Application\Admin\Content\PublicContentCache;
use App\Application\Community\Forums\RemoveForumContent;
use App\Application\Feed\ManagePost;
use App\Application\Moderation\ContentVisibility;
use App\Domain\Content\Exceptions\ContentRefused;
use App\Enums\ContentStatus;
use App\Models\Event;
use App\Models\ForumTopic;
use App\Models\Group;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;

/**
 * Actions de l'administration du réseau social (P12-05, `/admin/reseau`) : voir est un lien, le reste passe ici.
 *  - masquer / rendre visible : groupe et page (`is_active`), événement (statut `masque`), sujet de forum et publication
 *    (ContentVisibility, mêmes compteurs que la modération) ;
 *  - approuver / retirer l'approbation d'une page d'organisation (P8-03 : tant qu'elle n'est pas approuvée, seule sa
 *    propriétaire et la modération la voient) ;
 *  - mettre en avant / retirer : page et groupe (tête de liste) ;
 *  - supprimer : suppression douce (groupe, page, événement), RemoveForumContent (sujet), ManagePost (publication).
 * Chaque action est journalisée (`network.*`, sans texte). Les permissions sont rejouées par le composant (policies).
 */
final class ModerateNetwork
{
    public function __construct(
        private readonly ContentVisibility $visibility,
        private readonly RemoveForumContent $forumContent,
        private readonly ManagePost $posts,
        private readonly PublicContentCache $cache,
    ) {}

    /** Le sujet peut-il être masqué puis rendu visible ? */
    public static function hideable(Model $subject): bool
    {
        return $subject instanceof Group || $subject instanceof Page || $subject instanceof Event || $subject instanceof Post || $subject instanceof ForumTopic;
    }

    public static function isHidden(Model $subject): bool
    {
        return match (true) {
            $subject instanceof Group, $subject instanceof Page => ! $subject->is_active,
            $subject instanceof Event => $subject->status === ContentStatus::Hidden,
            default => ContentVisibility::isHidden($subject),
        };
    }

    public function hide(Model $subject, User $actor): bool
    {
        $changed = match (true) {
            $subject instanceof Group, $subject instanceof Page => $this->setActive($subject, false),
            $subject instanceof Event => $this->setEventStatus($subject, ContentStatus::Hidden),
            $subject instanceof Post, $subject instanceof ForumTopic => $this->visibility->hide($subject, (string) __('admin_network.hidden_reason')),
            default => throw new ContentRefused('network_unsupported'),
        };

        if ($changed) {
            Audit::log('network.'.self::kind($subject).'_hidden', $subject, null, $actor);
            $this->cache->flush();
        }

        return $changed;
    }

    public function show(Model $subject, User $actor): bool
    {
        $changed = match (true) {
            $subject instanceof Group, $subject instanceof Page => $this->setActive($subject, true),
            $subject instanceof Event => $this->setEventStatus($subject, ContentStatus::Published),
            $subject instanceof Post, $subject instanceof ForumTopic => $this->visibility->show($subject),
            default => throw new ContentRefused('network_unsupported'),
        };

        if ($changed) {
            Audit::log('network.'.self::kind($subject).'_shown', $subject, null, $actor);
            $this->cache->flush();
        }

        return $changed;
    }

    /** Approuve une page d'organisation, ou retire son approbation (elle redevient « en attente »). */
    public function approve(Page $page, bool $approved, User $actor): Page
    {
        $page->forceFill([
            'is_approved' => $approved,
            'approved_at' => $approved ? now() : null,
            'approved_by' => $approved ? $actor->getKey() : null,
        ])->save();

        Audit::log($approved ? 'network.page_approved' : 'network.page_unapproved', $page, null, $actor);
        $this->cache->flush();

        return $page;
    }

    public function feature(Group|Page $subject, bool $featured, User $actor): void
    {
        $subject->forceFill(['is_featured' => $featured])->save();
        Audit::log('network.'.self::kind($subject).($featured ? '_featured' : '_unfeatured'), $subject, null, $actor);
        $this->cache->flush();
    }

    public function delete(Model $subject, User $actor): void
    {
        match (true) {
            $subject instanceof Group, $subject instanceof Page, $subject instanceof Event => $subject->delete(),
            $subject instanceof ForumTopic => $this->forumContent->topic($subject, $actor),
            $subject instanceof Post => $this->posts->delete($subject, $actor),
            default => throw new ContentRefused('network_unsupported'),
        };

        Audit::log('network.'.self::kind($subject).'_deleted', $subject, null, $actor);
        $this->cache->flush();
    }

    private function setActive(Group|Page $subject, bool $active): bool
    {
        if ($subject->is_active === $active) {
            return false;
        }

        $subject->forceFill(['is_active' => $active])->save();

        return true;
    }

    private function setEventStatus(Event $event, ContentStatus $status): bool
    {
        // Un événement en brouillon n'est ni masqué ni publié par la modération.
        if ($event->status === $status || $event->status === ContentStatus::Draft) {
            return false;
        }

        $event->forceFill(['status' => $status])->save();

        return true;
    }

    private static function kind(Model $subject): string
    {
        return match (true) {
            $subject instanceof Group => 'group',
            $subject instanceof Page => 'page',
            $subject instanceof Event => 'event',
            $subject instanceof ForumTopic => 'topic',
            default => 'post',
        };
    }
}
