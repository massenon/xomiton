<?php

declare(strict_types=1);

namespace App\Application\Content\ViolencesNumeriques;

use App\Application\Admin\Content\PublicContentCache;
use App\Application\Seo\MetaText;
use App\Enums\CccKind;
use App\Enums\ContentStatus;
use App\Enums\DirectoryRole;
use App\Models\Category;
use App\Models\CccTool;
use App\Models\Faq;
use App\Models\Thematique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Import idempotent des contenus des violences numériques (P19-03, P19-06 ; décisions du client du 12/11/2026, Q98).
 * Rejouable sans doublon : chaque ligne est retrouvée par sa clé naturelle et ses textes sont réalignés sur les fichiers.
 *
 *   - 6 catégories `violence_numerique` (clé `kind` + `slug`) regroupées sous le carrefour `/violences-numeriques` ;
 *   - sujet pilote → **fiche** `Thematique` (clé `slug`) : titre, résumé, sections, avertissement, couverture, image de
 *     partage 1080 × 1080, méta SEO (titre 40–50 + « – Xomiton », description 140–160), contacts liés ; 3 FAQ propres
 *     (`faqs.thematique_id`, clé fiche + rang) ; publiée à la création seulement ;
 *   - sujet « à venir » → fiche **brouillon** créée une fois (titre, résumé, catégorie), réalignée tant qu'elle reste un
 *     brouillon sans contenu : l'équipe la rédige puis la publie dans `/admin/contenu`, le carrefour la relie alors ;
 *   - BD pilote → `CccTool` kind `bd`, clé `vn0X`, planches `{image, caption, alt}`, lien vers sa fiche ; publiée et
 *     publique à la création seulement.
 *
 * Jamais touchés à la mise à jour : `legal_references` (saisies par l'administration), le statut et la date de
 * publication d'une fiche, `is_published` / `is_public` d'une BD, une fiche supprimée (elle reste supprimée).
 */
final class ImportViolencesNumeriques
{
    /** Date de publication figée des fiches du pilote (déterminisme). */
    public const PUBLISHED_AT = '2026-11-12 08:00:00';

    /** Rang des BD du pilote après bd1–bd3 dans `/ccc`. */
    private const POSITION_OFFSET = 10;

    private const READING_MINUTES = 5;

    /** @var list<DirectoryRole> rôles d'annuaire proposés en bas de fiche */
    private const CONTACT_ROLES = [DirectoryRole::PointFocal, DirectoryRole::Juriste, DirectoryRole::Psychologue];

    /** Compléments de titre SEO essayés dans l'ordre : le premier qui donne 40 à 50 caractères gagne. */
    private const TITLE_SUFFIXES = [' : reconnaître, réagir, se faire aider', ' : reconnaître et réagir', ' : que faire ?', '', ' : réagir'];

    public function __construct(
        private readonly ViolencesNumeriquesSource $source,
        private readonly PublicContentCache $cache,
    ) {}

    /**
     * @return array<string, int> nombre de catégories, fiches, sujets à venir, questions, BD ; créations et mises à jour
     */
    public function __invoke(): array
    {
        $categories = $this->source->categories();
        $topics = [];

        foreach ($categories as $category) {
            foreach ($category['topics'] as $topic) {
                if ($topic['status'] === ViolencesNumeriquesSource::STATUS_PILOT) {
                    $topics[$topic['slug']] = $this->source->topic($topic['slug']);
                }
            }
        }

        $report = ['categories' => 0, 'fiches' => 0, 'upcoming' => 0, 'faqs' => 0, 'comics' => 0, 'created' => 0, 'updated' => 0];

        DB::transaction(function () use ($categories, $topics, &$report): void {
            /** @var array<string, list<Thematique>> $pilotsByCategory */
            $pilotsByCategory = [];
            $comicRank = 0;

            foreach ($categories as $position => $row) {
                $category = $this->upsertCategory($row, $position + 1, $report);

                foreach ($row['topics'] as $topic) {
                    if ($topic['status'] !== ViolencesNumeriquesSource::STATUS_PILOT) {
                        $this->upsertUpcoming($category, $topic, $report);

                        continue;
                    }

                    $data = $topics[$topic['slug']];
                    $thematique = $this->upsertFiche($category, $topic['slug'], $data, $report);
                    $this->upsertFaqs($thematique, (array) $data['faq'], $report);
                    $this->upsertComic($thematique, $data, ++$comicRank, $report);
                    $pilotsByCategory[$row['slug']][] = $thematique;
                }
            }

            $this->linkRelated($pilotsByCategory);
        });

        $this->cache->flush();

        return $report;
    }

    /**
     * @param  array{slug: string, label: string, icon: string}  $row
     * @param  array<string, int>  $report
     */
    private function upsertCategory(array $row, int $position, array &$report): Category
    {
        $category = Category::query()->firstOrNew(['kind' => Category::KIND_DIGITAL_VIOLENCE, 'slug' => $row['slug']]);
        $creating = ! $category->exists;
        $category->fill([
            'label' => $row['label'],
            'icon' => $row['icon'] === '' ? null : 'bi-'.$row['icon'],
            'color' => 'primary',
            'position' => $position,
        ]);

        if ($creating) {
            $category->is_active = true;
        }

        $category->save();
        $this->count($creating, $report);
        $report['categories']++;

        return $category;
    }

    /**
     * @param  array{slug: string, title: string, summary: string}  $topic
     * @param  array<string, int>  $report
     */
    private function upsertUpcoming(Category $category, array $topic, array &$report): void
    {
        $thematique = Thematique::query()->withTrashed()->firstOrNew(['slug' => $topic['slug']]);
        $creating = ! $thematique->exists;
        $report['upcoming']++;

        // Une fiche déjà rédigée, publiée ou supprimée par l'équipe n'est plus réalignée.
        if (! $creating && ($thematique->trashed() || $this->status($thematique) !== ContentStatus::Draft || trim((string) $thematique->body) !== '' || ! empty($thematique->sections))) {
            return;
        }

        $thematique->fill([
            'category_id' => $category->getKey(),
            'title' => $topic['title'],
            'summary' => $this->sentence($topic['summary']),
            'related_contact_roles' => $this->contactRoles(),
        ]);

        if ($creating) {
            $thematique->forceFill(['status' => ContentStatus::Draft, 'published_at' => null, 'reading_minutes' => self::READING_MINUTES]);
        }

        $thematique->save();
        $this->count($creating, $report);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, int>  $report
     */
    private function upsertFiche(Category $category, string $slug, array $data, array &$report): Thematique
    {
        $thematique = Thematique::query()->withTrashed()->firstOrNew(['slug' => $slug]);
        $creating = ! $thematique->exists;
        $title = (string) $data['title'];
        $summary = (string) $data['summary'];
        $sections = $this->sections((array) ($data['fiche']['sections'] ?? []));
        $bodyText = implode(' ', array_column($sections, 'body'));
        $cover = (string) ($data['bd']['cover']['image'] ?? '');
        $share = (string) ($data['share']['image'] ?? '');

        $thematique->fill([
            'category_id' => $category->getKey(),
            'title' => $title,
            'summary' => $summary,
            'body' => null,
            'sections' => $sections,
            'content_warning' => $this->nullable($data['content_warning'] ?? null),
            'image_path' => $cover === '' ? null : $cover,
            'cover_path' => $cover === '' ? null : $cover,
            'og_image_path' => $share === '' ? null : $share,
            'reading_minutes' => self::READING_MINUTES,
            'related_contact_roles' => $this->contactRoles(),
            'meta_title' => self::metaTitle($title),
            'meta_description' => MetaText::description($summary, $bodyText, (string) __('pages/violences_numeriques.meta_description_filler')),
        ]);

        if ($creating) {
            $thematique->forceFill(['status' => ContentStatus::Published, 'published_at' => Carbon::parse(self::PUBLISHED_AT)]);
        }

        $thematique->save();
        $this->count($creating, $report);
        $report['fiches']++;

        return $thematique;
    }

    /**
     * @param  array<int, mixed>  $faqs
     * @param  array<string, int>  $report
     */
    private function upsertFaqs(Thematique $thematique, array $faqs, array &$report): void
    {
        foreach (array_values(array_filter($faqs, 'is_array')) as $index => $row) {
            $question = trim((string) ($row['question'] ?? ''));
            $answer = trim((string) ($row['answer'] ?? ''));

            if ($question === '' || $answer === '') {
                continue;
            }

            $faq = Faq::query()->where('thematique_id', $thematique->getKey())->where('position', $index + 1)->first() ?? new Faq;
            $creating = ! $faq->exists;

            // Slug posé ici : l'import tourne aussi sans événements de modèle (DatabaseSeeder, WithoutModelEvents).
            if (trim((string) $faq->slug) === '') {
                $faq->slug = Faq::uniqueSlug($question);
            }

            $faq->fill([
                'thematique_id' => $thematique->getKey(),
                'category_id' => null,
                'question' => $question,
                'answer' => $answer,
                'position' => $index + 1,
                'show_on_home' => false,
            ]);

            if ($creating) {
                $faq->is_published = true;
            }

            $faq->save();
            $this->count($creating, $report);
            $report['faqs']++;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, int>  $report
     */
    private function upsertComic(Thematique $thematique, array $data, int $rank, array &$report): void
    {
        $bd = (array) $data['bd'];
        $tool = CccTool::query()->firstOrNew(['key' => (string) $data['key']]);
        $creating = ! $tool->exists;

        $tool->fill([
            'kind' => CccKind::Comic,
            'title' => (string) ($bd['title'] ?? $data['title']),
            'description' => (string) $data['summary'],
            'image_path' => $this->nullable($bd['cover']['image'] ?? null),
            'pages' => array_values(array_map(static fn (array $page): array => [
                'image' => (string) ($page['image'] ?? ''),
                'caption' => trim((string) ($page['caption'] ?? '')),
                'alt' => trim((string) ($page['alt'] ?? '')),
            ], array_filter((array) ($bd['pages'] ?? []), 'is_array'))),
            'chapters' => null,
            'duration_minutes' => null,
            'position' => self::POSITION_OFFSET + $rank,
            'thematique_id' => $thematique->getKey(),
        ]);

        if ($creating) {
            $tool->forceFill(['is_published' => true, 'is_public' => true]);
        }

        $tool->save();
        $this->count($creating, $report);
        $report['comics']++;
    }

    /**
     * Fiches liées : les autres fiches pilotes de la même catégorie d'abord, puis les autres (3 au plus).
     *
     * @param  array<string, list<Thematique>>  $byCategory
     */
    private function linkRelated(array $byCategory): void
    {
        $all = array_merge(...array_values($byCategory ?: [[]]));

        foreach ($byCategory as $siblings) {
            foreach ($siblings as $thematique) {
                $ordered = [...$siblings, ...$all];
                $ids = [];

                foreach ($ordered as $candidate) {
                    $id = (int) $candidate->getKey();
                    if ($id !== (int) $thematique->getKey() && ! in_array($id, $ids, true)) {
                        $ids[] = $id;
                    }
                }

                $thematique->forceFill(['related_ids' => array_slice($ids, 0, 3)])->save();
            }
        }
    }

    /**
     * Titre SEO sans suffixe (40 à 50 caractères : 50 à 60 avec « – Xomiton ») ; à défaut, le titre borné.
     */
    public static function metaTitle(string $title): string
    {
        foreach (self::TITLE_SUFFIXES as $suffix) {
            $candidate = $title.$suffix;
            $length = mb_strlen($candidate);

            if ($length >= 40 && $length <= MetaText::TITLE_MAX) {
                return $candidate;
            }
        }

        return MetaText::title($title);
    }

    /**
     * @param  array<int, mixed>  $sections
     * @return list<array{title: string, body: string}>
     */
    private function sections(array $sections): array
    {
        $out = [];

        foreach ($sections as $section) {
            if (is_array($section) && trim((string) ($section['title'] ?? '')) !== '' && trim((string) ($section['body'] ?? '')) !== '') {
                $out[] = ['title' => trim((string) $section['title']), 'body' => trim((string) $section['body'])];
            }
        }

        return $out;
    }

    /** @return list<string> */
    private function contactRoles(): array
    {
        return array_map(static fn (DirectoryRole $role): string => $role->value, self::CONTACT_ROLES);
    }

    private function status(Thematique $thematique): ?ContentStatus
    {
        return ContentStatus::tryFrom((string) $thematique->getRawOriginal('status'));
    }

    private function sentence(string $text): string
    {
        $text = trim($text);

        return $text === '' || preg_match('/[.!?…]$/u', $text) === 1 ? $text : $text.'.';
    }

    private function nullable(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /** @param  array<string, int>  $report */
    private function count(bool $created, array &$report): void
    {
        $report[$created ? 'created' : 'updated']++;
    }
}
