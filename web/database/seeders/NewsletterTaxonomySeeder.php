<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\NewsletterInterest;
use App\Models\NewsletterTag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Tags et centres d'intérêt de la newsletter (DATABASE_DICTIONARY.md §9, ADR-20), repris du prototype
 * (prototype_social/inc/x_newsletter.php : `nl_tags()`, `nl_interests()`). Idempotent : les libellés sont
 * réalignés par slug à chaque exécution, les valeurs ajoutées par l'administration sont conservées.
 */
class NewsletterTaxonomySeeder extends Seeder
{
    /** @var array<string, string> slug => libellé (`nl_tags()`) */
    private const TAGS = [
        'membre' => 'Membre',
        'pair' => 'Pair·e',
        'partenaire' => 'Partenaire',
        'presse' => 'Presse',
        'bailleur' => 'Bailleur',
    ];

    /** @var list<string> libellés du prototype (`nl_interests()`), slug dérivé */
    private const INTERESTS = [
        'Santé',
        'Droits',
        'Bien-être',
        'Événements',
        'Ressources',
        'Sécurité numérique',
    ];

    public function run(): void
    {
        foreach (self::TAGS as $slug => $label) {
            NewsletterTag::query()->updateOrCreate(['slug' => $slug], ['label' => $label]);
        }

        foreach (self::INTERESTS as $label) {
            NewsletterInterest::query()->updateOrCreate(['slug' => Str::slug($label)], ['label' => $label]);
        }
    }
}
