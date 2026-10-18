<?php

declare(strict_types=1);

namespace App\Application\Community\Pages;

use App\Application\Community\Data\PageDraft;
use App\Application\Community\UniqueSlug;
use App\Application\Feed\BadWordFilter;
use App\Domain\Feed\Exceptions\ContentBlocked;
use App\Models\Page;
use App\Models\PageFollower;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/**
 * Créer une page d'organisation (P8-03, create-page.php l.9-18) : filtre de mots sur le nom et la description (terme
 * bloquant → 422 `content_blocked`), slug unique, page **en attente d'approbation** (`is_approved = false` : visible de
 * sa seule propriétaire et de la modération jusqu'à l'approbation, P12-05), la propriétaire la suit (prototype :
 * x_set_toggle), journal `page.created` (et `page.words_flagged` avec les termes « à surveiller » trouvés, pour P12-05).
 */
final class CreatePage
{
    public function __construct(private readonly BadWordFilter $filter) {}

    /**
     * @throws ContentBlocked
     */
    public function __invoke(User $owner, PageDraft $draft): Page
    {
        $verdict = $this->filter->assertAllowed(['name' => $draft->name, 'description' => $draft->description]);

        /** @var Page $page */
        $page = DB::transaction(static function () use ($owner, $draft): Page {
            $page = Page::query()->create([
                'name' => trim($draft->name),
                'slug' => UniqueSlug::for(Page::class, $draft->name),
                'category' => $draft->category,
                'description' => $draft->description !== null && trim($draft->description) !== '' ? trim($draft->description) : null,
                'website' => $draft->website,
                'owner_id' => $owner->getKey(),
                'followers_count' => 1,
                'is_verified' => false,
                'is_approved' => false,
                'is_featured' => false,
                'is_active' => true,
            ]);

            PageFollower::query()->create(['page_id' => $page->getKey(), 'user_id' => $owner->getKey()]);

            return $page;
        });

        Audit::log('page.created', $page, null, $owner);

        // Terme « à surveiller » : la page attend déjà l'approbation (P12-05), qui relira `page.words_flagged`.
        if ($verdict->isFlagged()) {
            Audit::log('page.words_flagged', $page, (string) __('feed.moderation.auto_details', ['terms' => implode(', ', $verdict->flaggingTerms)]), $owner);
        }

        return $page;
    }
}
