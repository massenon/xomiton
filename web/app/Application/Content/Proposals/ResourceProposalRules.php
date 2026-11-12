<?php

declare(strict_types=1);

namespace App\Application\Content\Proposals;

use App\Enums\ResourceKind;
use App\Models\Category;
use App\Rules\UploadAllowed;
use Illuminate\Validation\Rule;

/**
 * Règles du formulaire « Proposer une ressource » (P18-12), partagées par l'API (`POST resource-proposals`), la page Vue et
 * l'application : titre, type (fiche, guide, document PDF ou lien), résumé, catégorie facultative, lien `https` (exigé
 * pour un lien) ou fichier PDF (exigé pour un document PDF, MediaUploader collection `documents`, 10 Mo au plus),
 * crédit facultatif de l'auteur·rice.
 */
final class ResourceProposalRules
{
    public const TITLE_MIN = 5;

    public const TITLE_MAX = 150;

    public const SUMMARY_MIN = 20;

    public const SUMMARY_MAX = 500;

    public const FILE_MAX_KB = 10240;

    /** Types proposables : jamais vidéo ni audio (sous-titres et transcription exigés à la publication). */
    public const KINDS = [ResourceKind::Sheet, ResourceKind::Guide, ResourceKind::Pdf, ResourceKind::Link];

    /** @return list<string> */
    public static function kindValues(): array
    {
        return array_map(static fn (ResourceKind $kind): string => $kind->value, self::KINDS);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:'.self::TITLE_MIN, 'max:'.self::TITLE_MAX],
            'kind' => ['required', 'string', Rule::in(self::kindValues())],
            'summary' => ['required', 'string', 'min:'.self::SUMMARY_MIN, 'max:'.self::SUMMARY_MAX],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('kind', Category::KIND_RESOURCE)->where('is_active', true)],
            'external_url' => ['nullable', 'required_if:kind,'.ResourceKind::Link->value, 'string', 'max:500', 'url:https'],
            'file' => ['nullable', 'required_if:kind,'.ResourceKind::Pdf->value, 'file', new UploadAllowed('documents', self::FILE_MAX_KB)],
            'credit_author' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Options du formulaire (types, catégories de ressources, limites), servies à la page Vue et à l'application.
     *
     * @return array{kinds: list<array{value: string, label: string}>, categories: list<array{value: int, label: string}>, limits: array<string, int>}
     */
    public static function options(): array
    {
        return [
            'kinds' => array_map(static fn (ResourceKind $kind): array => ['value' => $kind->value, 'label' => $kind->label()], self::KINDS),
            'categories' => Category::query()->where('kind', Category::KIND_RESOURCE)->where('is_active', true)->orderBy('position')->orderBy('label')->get(['id', 'label'])
                ->map(static fn (Category $category): array => ['value' => (int) $category->getKey(), 'label' => (string) $category->label])->values()->all(),
            'limits' => [
                'title_min' => self::TITLE_MIN,
                'title_max' => self::TITLE_MAX,
                'summary_min' => self::SUMMARY_MIN,
                'summary_max' => self::SUMMARY_MAX,
                'file_max_kb' => self::FILE_MAX_KB,
            ],
        ];
    }
}
