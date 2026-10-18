<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Application\Seo\MetaText;
use App\Enums\BadWordSeverity;
use App\Enums\CccKind;
use App\Enums\ContentStatus;
use App\Enums\DirectoryRole;
use App\Models\AssistantKb;
use App\Models\BadWord;
use App\Models\Category;
use App\Models\CccTool;
use App\Models\Faq;
use App\Models\Law;
use App\Models\Thematique;
use App\Support\FrenchTypography;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Contenus éditoriaux du lot 3, repris du prototype (prototype_social/inc/data.php,
 * inc/x_thematiques_content.php, inc/x_public.php, data/faqs.json, data/assistant_kb.json, data/bad_words.json).
 * Les textes sont dans database/seeders/data/content/*.php. Idempotent : rejouable sans doublon,
 * les textes sont réalignés sur le prototype à chaque exécution.
 */
class ContentSeeder extends Seeder
{
    /** Chemin public des images de démonstration, comme User::DEFAULT_AVATAR. */
    private const IMAGE_BASE = 'assets/images/xomiton/';

    /** Date de publication figée des contenus du prototype (déterminisme du seed). */
    private const PUBLISHED_AT = '2026-10-01 08:00:00';

    /** @var array<string, array{0: string, 1: string, 2: string}> catégories de thématiques du lot 1, au cas où ReferenceDataSeeder n'a pas tourné */
    private const THEMATIQUE_CATEGORIES = [
        'vbg' => ['Violences basées sur le genre', 'bi-shield-exclamation', 'danger'],
        'regles' => ['Hygiène menstruelle', 'bi-droplet-half', 'danger'],
        'mental' => ['Santé mentale', 'bi-heart-pulse', 'primary'],
        'droits' => ['Droits & lois', 'bi-bank', 'info'],
        'esc' => ['Éducation complète à la sexualité', 'bi-book', 'success'],
        'corps' => ['Autonomie & autonomisation', 'bi-person-check', 'warning'],
    ];

    /** @var array<string, list<DirectoryRole>> rôles d'annuaire proposés en bas de fiche, par catégorie (contacts de x_thematique_content()) */
    private const CONTACT_ROLES = [
        'vbg' => [DirectoryRole::PointFocal, DirectoryRole::Juriste, DirectoryRole::Psychologue],
        'droits' => [DirectoryRole::Juriste, DirectoryRole::PointFocal],
        'mental' => [DirectoryRole::Psychologue, DirectoryRole::PointFocal],
        'esc' => [DirectoryRole::Gynecologue, DirectoryRole::Psychologue],
        'regles' => [DirectoryRole::Gynecologue],
        'corps' => [DirectoryRole::PairAnimateur, DirectoryRole::ResponsableReseau],
    ];

    private const CONTENT_WARNING = 'Ce contenu évoque des violences. Prenez votre temps, et si vous êtes en danger, utilisez le bouton SOS.';

    public function run(): void
    {
        $this->seedThematiques();
        $this->seedLaws();
        $this->seedFaqs();
        $this->seedCccTools();
        $this->seedAssistantKb();
        $this->seedBadWords();
    }

    /** @return array<string, mixed> */
    private function data(string $name): array
    {
        return require __DIR__.'/data/content/'.$name.'.php';
    }

    private function seedThematiques(): void
    {
        $data = $this->data('thematiques');
        $content = $this->data('thematiques_content');
        $categories = $this->thematiqueCategories();
        $publishedAt = Carbon::parse(self::PUBLISHED_AT);

        /** @var array<int, Thematique> $created id prototype => modèle */
        $created = [];

        foreach ($data['rows'] as $index => [$protoId, $cat, $title, $summary]) {
            $body = $this->thematiqueBody($content, $protoId, $cat, $title, $summary);
            $thematique = Thematique::query()->withTrashed()->updateOrCreate(
                ['slug' => Str::slug($title)],
                [
                    'category_id' => $categories[$cat]->getKey(),
                    'title' => $title,
                    'summary' => $summary,
                    'body' => $body,
                    'sections' => $this->thematiqueSections($content, $protoId, $cat),
                    'content_warning' => $cat === 'vbg' ? self::CONTENT_WARNING : null,
                    'image_path' => self::IMAGE_BASE.$data['images'][$index % count($data['images'])],
                    'cover_path' => self::IMAGE_BASE.'covers/th-'.$protoId.'.jpg',
                    'reading_minutes' => 3 + ($index % 6),
                    'related_contact_roles' => array_map(static fn (DirectoryRole $role): string => $role->value, self::CONTACT_ROLES[$cat]),
                    'status' => ContentStatus::Published,
                    'published_at' => $publishedAt,
                    // Bornes SEO (P4-02) : titre ≤ 50 + « – Xomiton », description 140–160, même règle que le repli de la page.
                    'meta_title' => MetaText::title($title),
                    'meta_description' => MetaText::description($summary, $body, (string) __('pages/thematiques.meta_description_filler')),
                ],
            );

            // Une fiche du prototype supprimée logiquement est rétablie : le seed fait référence.
            if ($thematique->trashed()) {
                $thematique->restore();
            }

            $created[$protoId] = $thematique;
        }

        $this->linkRelatedThematiques($created, $data['rows']);
    }

    /**
     * Catégories `thematique` du lot 1 (créées si ReferenceDataSeeder n'a pas encore tourné).
     *
     * @return array<string, Category>
     */
    private function thematiqueCategories(): array
    {
        $categories = [];
        $position = 0;

        foreach (self::THEMATIQUE_CATEGORIES as $slug => [$label, $icon, $color]) {
            $categories[$slug] = Category::query()->firstOrCreate(
                ['kind' => Category::KIND_THEMATIQUE, 'slug' => $slug],
                ['label' => $label, 'icon' => $icon, 'color' => $color, 'position' => ++$position, 'is_active' => true],
            );
        }

        return $categories;
    }

    /**
     * Corps HTML : paragraphes de la catégorie précédés du paragraphe « À propos du thème », comme dans
     * x_thematique_content(). Seule une introduction spécifique ouvre le corps en `<p class="lead">` : elle remplace
     * alors le résumé en chapeau de la fiche (un seul chapeau, comme le prototype) ; sinon la vue montre le résumé.
     *
     * @param  array<string, mixed>  $content
     */
    private function thematiqueBody(array $content, int $protoId, string $cat, string $title, string $summary): string
    {
        $intro = $content['specific'][$protoId] ?? null;
        $about = 'À propos du thème « '.rtrim($title, ' ?').' » : '.rtrim($summary, '.')
            .". Cette fiche vous en donne les repères essentiels, de manière simple, en privilégiant votre sécurité et votre droit à l'information.";
        $paragraphs = array_slice([$about, ...$content['categories'][$cat]['paragraphs']], 0, 5);

        return ($intro === null ? '' : '<p class="lead">'.e($intro).'</p>')
            .implode('', array_map(static fn (string $p): string => '<p>'.e($p).'</p>', $paragraphs));
    }

    /**
     * Accordéon `[{title, body}]` avec les quatre blocs de thematique.php du prototype :
     * « Points clés », « Que faire ? », « Cadre légal », « À retenir ».
     *
     * @param  array<string, mixed>  $content
     * @return list<array{title: string, body: string}>
     */
    private function thematiqueSections(array $content, int $protoId, string $cat): array
    {
        $category = $content['categories'][$cat];
        $lawKeys = $content['laws_overrides'][$protoId] ?? $content['laws_by_category'][$cat];
        $laws = array_map(static function (string $key) use ($content): string {
            [$title, $text] = $content['laws'][$key];

            return '<p><strong>'.e($title).'</strong><br>'.e($text).'</p>';
        }, $lawKeys);

        return [
            ['title' => 'Points clés', 'body' => $this->htmlList('ul', $category['points'])],
            ['title' => 'Que faire ?', 'body' => $this->htmlList('ol', $category['steps'])],
            ['title' => 'Cadre légal', 'body' => implode('', $laws)],
            ['title' => 'À retenir', 'body' => '<p>'.e($category['retenir']).'</p>'],
        ];
    }

    /** @param  list<string>  $items */
    private function htmlList(string $tag, array $items): string
    {
        return '<'.$tag.'>'.implode('', array_map(static fn (string $item): string => '<li>'.e($item).'</li>', $items)).'</'.$tag.'>';
    }

    /**
     * `related_ids` : trois autres thématiques de la même catégorie, complétées hors catégorie
     * s'il en manque, dans l'ordre du prototype (thematique.php).
     *
     * @param  array<int, Thematique>  $created
     * @param  list<array{0: int, 1: string, 2: string, 3: string}>  $rows
     */
    private function linkRelatedThematiques(array $created, array $rows): void
    {
        $byCategory = [];

        foreach ($rows as [$protoId, $cat]) {
            $byCategory[$cat][] = $protoId;
        }

        foreach ($rows as [$protoId, $cat]) {
            $siblings = array_values(array_filter($byCategory[$cat], static fn (int $id): bool => $id !== $protoId));
            $others = array_filter(array_column($rows, 0), static fn (int $id): bool => $id !== $protoId && ! in_array($id, $siblings, true));
            $relatedIds = array_map(
                static fn (int $id): int => (int) $created[$id]->getKey(),
                array_slice([...$siblings, ...$others], 0, 3),
            );

            $created[$protoId]->forceFill(['related_ids' => $relatedIds])->save();
        }
    }

    private function seedLaws(): void
    {
        foreach ($this->data('laws') as $index => [$slug, $title, $subtitle, $summary, $category]) {
            Law::query()->updateOrCreate(
                ['slug' => $slug],
                ['title' => $title, 'subtitle' => $subtitle, 'summary' => $summary, 'category' => $category, 'position' => $index + 1, 'is_published' => true],
            );
        }
    }

    private function seedFaqs(): void
    {
        $data = $this->data('faqs');
        $categories = [];
        $position = 0;

        foreach ($data['categories'] as $slug => [$label, $icon, $color]) {
            $categories[$slug] = Category::query()->updateOrCreate(
                ['kind' => Category::KIND_FAQ, 'slug' => $slug],
                ['label' => $label, 'icon' => $icon, 'color' => $color, 'position' => ++$position, 'is_active' => true],
            );
        }

        foreach ($data['rows'] as $index => [$question, $answer, $published]) {
            // Une base semée avant l'harmonisation typographique (P4-01) porte l'ancienne graphie (apostrophe droite,
            // espace ordinaire) : la ligne est retrouvée et renommée au lieu d'être dupliquée.
            $faq = Faq::query()->whereIn('question', FrenchTypography::variants($question))->first() ?? new Faq;

            $faq->fill([
                'question' => $question,
                'category_id' => $categories[$data['category_by_index'][$index] ?? 'compte']->getKey(),
                'answer' => $answer,
                'position' => $index + 1,
                'is_published' => $published,
                // Accueil : 5–6 questions (Q16, P4-01) ; le prototype en affichait huit (index.php : array_slice(x_faq(), 0, 8)).
                'show_on_home' => $index < 6,
            ])->save();
        }
    }

    private function seedCccTools(): void
    {
        $data = $this->data('ccc_tools');

        foreach ($data['manuel'] as $index => [$key, $title, $chapters, $duration]) {
            $this->upsertCccTool($key, [
                'kind' => CccKind::Manual, 'title' => $title, 'description' => null, 'image_path' => null,
                'pages' => null, 'chapters' => $chapters, 'duration_minutes' => $duration, 'position' => $index + 1,
            ]);
        }

        foreach ($data['boites'] as $index => [$title, $image, $instruction]) {
            $this->upsertCccTool(CccKind::ImageBox->keyPrefix().($index + 1), [
                'kind' => CccKind::ImageBox, 'title' => $title, 'description' => $instruction, 'image_path' => self::IMAGE_BASE.$image,
                'pages' => [['image' => self::IMAGE_BASE.$image, 'caption' => $instruction, 'alt' => $title]],
                'chapters' => null, 'duration_minutes' => null, 'position' => $index + 1,
            ]);
        }

        foreach ($data['bd'] as $index => [$key, $title, $image, $captions]) {
            $this->upsertCccTool($key, [
                'kind' => CccKind::Comic, 'title' => $title, 'description' => null, 'image_path' => self::IMAGE_BASE.$image,
                'pages' => $this->comicPages($title, self::IMAGE_BASE.$image, $captions),
                'chapters' => null, 'duration_minutes' => null, 'position' => $index + 1,
            ]);
        }
    }

    /** @param  array<string, mixed>  $attributes */
    private function upsertCccTool(string $key, array $attributes): void
    {
        CccTool::query()->updateOrCreate(['key' => $key], [...$attributes, 'is_published' => true]);
    }

    /**
     * @param  list<string>  $captions
     * @return list<array{image: string, caption: string, alt: string}>
     */
    private function comicPages(string $title, string $image, array $captions): array
    {
        return array_map(static fn (string $caption, int $index): array => [
            'image' => $image,
            'caption' => $caption,
            'alt' => $title.' – planche '.($index + 1),
        ], $captions, array_keys($captions));
    }

    private function seedAssistantKb(): void
    {
        foreach ($this->data('assistant_kb') as [$question, $answer, $keywords, $active]) {
            // Ancienne graphie (espace ordinaire avant « ? », apostrophe droite) retrouvée et renommée, jamais dupliquée.
            $entry = AssistantKb::query()->whereIn('question', FrenchTypography::variants($question))->first() ?? new AssistantKb;

            $entry->fill(['question' => $question, 'answer' => $answer, 'keywords' => $keywords, 'is_active' => $active])->save();
        }
    }

    private function seedBadWords(): void
    {
        foreach ($this->data('bad_words') as [$word, $severity]) {
            BadWord::query()->updateOrCreate(
                ['word' => $word],
                ['severity' => BadWordSeverity::from($severity), 'is_active' => true],
            );
        }
    }
}
