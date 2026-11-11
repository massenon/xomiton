<?php

declare(strict_types=1);

namespace App\Application\Admin\Content;

use App\Application\Seo\MetaText;
use App\Enums\ContentStatus;
use App\Enums\ResourceKind;
use App\Models\Category;
use App\Models\SlugRedirect;
use Closure;
use Illuminate\Validation\Rule;

/**
 * Règles de validation de l'administration du contenu (P12-03), écrites sur les noms de colonnes et lues par
 * ValidatesForm. Méta SEO (SEO_CONTENT_GUIDE_INSTRUCTION.md) : le titre est compté **sans** le suffixe « – Xomiton »
 * ajouté par `<x-seo>` (10 caractères) : 40 à 50 caractères enregistrés = 50 à 60 affichés ; description 140 à 160 ;
 * l'un et l'autre uniques dans leur table (deux fiches ne partagent jamais le même titre ou la même description).
 */
final class ContentRules
{
    public const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public const META_TITLE_MIN = 40;

    public const FAQ_ANSWER_MAX_WORDS = 60;

    /**
     * @return array<string, list<mixed>>
     */
    public static function resource(?int $id): array
    {
        return [
            'title' => ['required', 'string', 'min:5', 'max:200'],
            'kind' => ['required', Rule::enum(ResourceKind::class)],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->whereIn('kind', [Category::KIND_RESOURCE, Category::KIND_THEMATIQUE])],
            'thematique_id' => ['nullable', 'integer', Rule::exists('thematiques', 'id')->whereNull('deleted_at')],
            'slug' => ['nullable', 'string', 'max:120', 'regex:'.self::SLUG_PATTERN, Rule::unique('resources', 'slug')->ignore($id), self::slugAvailable(SlugRedirect::TYPE_RESOURCE, $id)],
            'summary' => ['required', 'string', 'min:20', 'max:500'],
            'body' => ['nullable', 'string', 'max:200000'],
            'external_url' => ['nullable', 'string', 'url:https', 'max:500'],
            'duration_minutes' => ['nullable', 'integer', 'between:1,1000'],
            'is_offline_available' => ['boolean'],
            'transcript' => ['nullable', 'string', 'max:200000'],
            'status' => ['required', Rule::in([ContentStatus::Draft->value, ContentStatus::Published->value])],
            ...self::meta('resources', $id),
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function thematique(?int $id): array
    {
        return [
            'title' => ['required', 'string', 'min:5', 'max:200'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('kind', Category::KIND_THEMATIQUE)],
            'slug' => ['nullable', 'string', 'max:120', 'regex:'.self::SLUG_PATTERN, Rule::unique('thematiques', 'slug')->ignore($id), self::slugAvailable(SlugRedirect::TYPE_THEMATIQUE, $id)],
            'summary' => ['required', 'string', 'min:20', 'max:500'],
            'body' => ['nullable', 'string', 'max:200000'],
            'content_warning' => ['nullable', 'string', 'max:255'],
            'reading_minutes' => ['required', 'integer', 'between:1,120'],
            'status' => ['required', Rule::in([ContentStatus::Draft->value, ContentStatus::Published->value])],
            ...self::meta('thematiques', $id),
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function faq(): array
    {
        return [
            'question' => ['required', 'string', 'min:8', 'max:255'],
            'answer' => ['required', 'string', 'min:20', 'max:2000', self::maxWords(self::FAQ_ANSWER_MAX_WORDS)],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->whereIn('kind', [Category::KIND_FAQ, Category::KIND_THEMATIQUE])],
            'is_published' => ['boolean'],
            'show_on_home' => ['boolean'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function category(?int $id, ?string $kind): array
    {
        return [
            'kind' => ['required', Rule::in(Category::KINDS)],
            'slug' => ['required', 'string', 'max:60', 'regex:'.self::SLUG_PATTERN, Rule::unique('categories', 'slug')->where('kind', $kind)->ignore($id)],
            'label' => ['required', 'string', 'min:2', 'max:80'],
            'icon' => ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9-]+$/'],
            'color' => ['nullable', 'string', 'max:20', 'regex:/^[a-z0-9#-]+$/i'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Méta SEO partagée : longueurs et unicité dans la table.
     *
     * @return array<string, list<mixed>>
     */
    private static function meta(string $table, ?int $id): array
    {
        return [
            'meta_title' => ['nullable', 'string', 'min:'.self::META_TITLE_MIN, 'max:'.MetaText::TITLE_MAX, Rule::unique($table, 'meta_title')->ignore($id)],
            'meta_description' => ['nullable', 'string', 'min:'.MetaText::DESCRIPTION_MIN, 'max:'.MetaText::DESCRIPTION_MAX, Rule::unique($table, 'meta_description')->ignore($id)],
        ];
    }

    /** Pas plus de $max mots (séparés par une espace, une apostrophe ne coupe pas un mot). */
    public static function maxWords(int $max): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($max): void {
            $words = preg_split('/\s+/u', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY) ?: [];

            if (count($words) > $max) {
                $fail((string) __('admin_content.errors.too_many_words', ['max' => $max]));
            }
        };
    }

    private static function slugAvailable(string $type, ?int $id): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($type, $id): void {
            if (is_string($value) && $value !== '' && SlugHistory::usedByOther($type, $value, $id)) {
                $fail((string) __('admin_content.errors.slug_history'));
            }
        };
    }

    /** Nombre de FAQ publiées par catégorie hors des bornes publiques (3 à 5) : aide à l'équipe éditoriale. */
    public static function faqBoundsOk(int $count): bool
    {
        return $count >= (int) config('xomiton.content.faq_min', 3) && $count <= (int) config('xomiton.content.faq_max', 5);
    }
}
