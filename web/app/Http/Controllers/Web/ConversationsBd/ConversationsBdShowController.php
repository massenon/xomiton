<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\ConversationsBd;

use App\Application\Content\ConversationsBd\ConversationsBdCatalog;
use App\Application\Content\ViolencesNumeriques\Discretion;
use App\Application\Seo\MetaText;
use App\Application\Seo\StructuredData;
use App\Http\Controllers\Controller;
use App\Models\BdConversation;
use App\Models\User;
use App\Support\LocaleFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Page publique d'une conversation en BD `/conversations-bd/{slug}` (P20-06, PRD P0-2 à P0-4) : publiée seulement,
 * sinon 404 neutre (brouillon, proposée, refusée, retirée ou inconnue : réponse identique). Avertissement de contenu,
 * lecture case par case (visionneuse `xmComic`, ancre `#case-n`) et, sans JavaScript, toutes les cases à la suite ;
 * bulles au texte réel (indexable) et transcription lue par les lecteurs d'écran ; fin de lecture : message clé, liens
 * d'aide (Signaler, SOS, urgences, aide), mode séance et questions d'animation, autres conversations. Mode discret
 * (Discretion) : titre neutre et cases repliées pour une personne connectée qui l'a choisi.
 * SEO : og:image = première case illustrée (sinon visuel par défaut) ; JSON-LD CreativeWork + BreadcrumbList.
 */
final class ConversationsBdShowController extends Controller
{
    public function __invoke(Request $request, string $slug, ConversationsBdCatalog $catalog, Discretion $discretion): View
    {
        $conversation = $catalog->find($slug);
        abort_if($conversation === null, 404);

        $panels = $catalog->panels($conversation);
        $title = (string) $conversation->title;
        $canonical = route('conversations-bd.show', ['slug' => $conversation->slug]);
        $user = $request->user();
        $user = $user instanceof User ? $user : null;

        return view('public.conversations-bd.show', [
            'conversation' => $conversation,
            'panels' => $panels,
            'names' => $catalog->names(),
            'others' => $catalog->others($conversation),
            'catalog' => $catalog,
            'hideImages' => $discretion->hidesImages($user),
            'breadcrumb' => [
                ['label' => __('seo.breadcrumb_home'), 'url' => route('home')],
                ['label' => __('pages/conversations_bd.title'), 'url' => route('conversations-bd.index')],
                ['label' => $title],
            ],
            'seo' => $discretion->seo($this->seo($conversation, $catalog, $panels, $canonical), $user),
        ]);
    }

    /**
     * @param  list<array{bubbles: list<array<string, mixed>>}>  $panels
     * @return array<string, mixed>
     */
    private function seo(BdConversation $conversation, ConversationsBdCatalog $catalog, array $panels, string $canonical): array
    {
        $title = (string) $conversation->title;
        $description = MetaText::description(
            (string) ($conversation->meta_description ?: $conversation->summary),
            '<p>'.e($catalog->plainText($panels)).'</p>',
            (string) __('pages/conversations_bd.meta_description_filler'),
        );
        $cover = $catalog->cover($conversation);
        $image = $cover ?? asset('assets/images/xomiton/covers/og-default.jpg');
        $modified = $conversation->updated_at !== null ? LocaleFormatter::make()->isoDate($conversation->updated_at) : null;
        $published = $conversation->published_at !== null ? LocaleFormatter::make()->isoDate($conversation->published_at) : null;
        $root = StructuredData::root();

        return [
            'title' => MetaText::title((string) ($conversation->meta_title ?: __('pages/conversations_bd.meta_title_show', ['title' => $title]))),
            'description' => $description,
            'type' => 'article',
            'image' => $image,
            'imageAlt' => __('pages/conversations_bd.cover_alt', ['title' => $title]),
            'breadcrumb' => [[__('pages/conversations_bd.title'), route('conversations-bd.index')], [$title, $canonical]],
            'modified' => $modified,
            'schema' => [array_filter([
                '@type' => 'CreativeWork',
                '@id' => $canonical.'#conversation',
                'name' => $title,
                'headline' => $title,
                'description' => $description,
                'genre' => (string) __('pages/conversations_bd.genre'),
                'keywords' => implode(', ', array_filter([(string) $conversation->theme, (string) $conversation->category])) ?: null,
                'audience' => $conversation->audience ? ['@type' => 'Audience', 'audienceType' => (string) $conversation->audience] : null,
                'inLanguage' => __('seo.in_language'),
                'image' => $image,
                'author' => ['@id' => $root.'#org'],
                'publisher' => ['@id' => $root.'#org'],
                'datePublished' => $published,
                'dateModified' => $modified,
                'mainEntityOfPage' => ['@id' => $canonical.'#page'],
                'isAccessibleForFree' => true,
            ], static fn (mixed $value): bool => $value !== null)],
        ];
    }
}
