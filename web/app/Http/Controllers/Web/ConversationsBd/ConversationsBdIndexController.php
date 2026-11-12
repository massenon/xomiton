<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\ConversationsBd;

use App\Application\Content\ConversationsBd\ConversationsBdCatalog;
use App\Application\Content\ViolencesNumeriques\Discretion;
use App\Application\Seo\StructuredData;
use App\Http\Controllers\Controller;
use App\Models\BdConversation;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Liste publique des conversations en BD `/conversations-bd` (P20-06, PRD P0-3) : conversations publiées seulement,
 * indexable, vous. Mode discret (Discretion, comme les BD) : titre neutre pour une personne connectée qui l'a choisi.
 * SEO : CollectionPage + BreadcrumbList.
 */
final class ConversationsBdIndexController extends Controller
{
    public function __invoke(Request $request, ConversationsBdCatalog $catalog, Discretion $discretion): View
    {
        $conversations = $catalog->published();
        $canonical = route('conversations-bd.index');
        $title = (string) __('pages/conversations_bd.title');
        $first = $conversations->first();
        $cover = $first instanceof BdConversation ? $catalog->cover($first) : null;
        $user = $request->user();

        return view('public.conversations-bd.index', [
            'conversations' => $conversations,
            'catalog' => $catalog,
            'breadcrumb' => [['label' => __('seo.breadcrumb_home'), 'url' => route('home')], ['label' => $title]],
            'seo' => $discretion->seo([
                'title' => __('pages/conversations_bd.meta_title'),
                'description' => __('pages/conversations_bd.meta_description'),
                'image' => $cover ?? 'covers/og-default.jpg',
                'imageAlt' => $cover !== null && $first instanceof BdConversation ? __('pages/conversations_bd.cover_alt', ['title' => $first->title]) : null,
                'breadcrumb' => [[$title, $canonical]],
                'schema' => [StructuredData::collectionPage($canonical, $title, $conversations->count(), $conversations->map(static fn (BdConversation $conversation): array => [
                    'url' => route('conversations-bd.show', ['slug' => $conversation->slug]),
                    'name' => (string) $conversation->title,
                ])->values()->all())],
            ], $user instanceof User ? $user : null),
        ]);
    }
}
