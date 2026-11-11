<?php

declare(strict_types=1);

namespace App\Application\Content\Results;

use App\Models\Category;
use App\Models\DirectoryContact;
use App\Models\Faq;
use App\Models\Resource;
use App\Models\Thematique;
use Illuminate\Support\Collection;

/**
 * Résultat de ShowThematique : la fiche publiée et ses blocs liés (P4-02).
 */
final readonly class ThematiqueSheet
{
    /**
     * @param  Category|null  $category  catégorie du fil d'Ariane : celle de la fiche si elle est un filtre de la liste (active, `thematique`), sinon null
     * @param  Collection<int, DirectoryContact>  $contacts  contacts d'annuaire actifs et publics (Q19), au plus `contacts_max`
     * @param  Collection<int, Thematique>  $related  0 à `related_count` fiches publiées
     * @param  Collection<int, Faq>  $faqs  vide, ou entre `faq_min` et `faq_max` questions publiées
     */
    public function __construct(
        public Thematique $thematique,
        public ?Category $category,
        public Collection $contacts,
        public Collection $related,
        public ?Resource $primaryResource,
        public Collection $faqs,
    ) {}
}
