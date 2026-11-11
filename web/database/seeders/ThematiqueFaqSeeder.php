<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Faq;
use App\Support\FrenchTypography;
use Illuminate\Database\Seeder;

/**
 * FAQ des fiches thématiques (P4-02, database/seeders/data/content/thematiques_faqs.php) : trois questions par catégorie
 * de thématique, hors accueil. Séparé de ContentSeeder pour garder intactes les dix FAQ du centre d'aide du prototype
 * (data/faqs.json). Textes médicaux et juridiques à valider par l'équipe éditoriale (plan §7, Q58) : publiés à la création
 * hors production seulement (DatabaseSeeder::DEMO_ENVIRONMENTS), en brouillon en production ; `is_published` d'une ligne
 * existante n'est jamais réécrit (une FAQ dépubliée depuis l'admin le reste). Idempotent : une question est retrouvée
 * sous toutes ses graphies (FrenchTypography), anciennes formulations comprises ; une catégorie absente
 * (ReferenceDataSeeder / ContentSeeder non joués) est ignorée.
 */
class ThematiqueFaqSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array<string, list<array{0: string, 1: string, 2?: list<string>}>> $data */
        $data = require __DIR__.'/data/content/thematiques_faqs.php';
        $categories = Category::query()->ofKind(Category::KIND_THEMATIQUE)->whereIn('slug', array_keys($data))->get()->keyBy('slug');
        $publish = app()->environment(DatabaseSeeder::DEMO_ENVIRONMENTS);

        foreach ($data as $slug => $rows) {
            $category = $categories->get($slug);

            if (! $category instanceof Category) {
                continue;
            }

            foreach ($rows as $index => $row) {
                [$question, $answer] = $row;
                $variants = collect([$question, ...($row[2] ?? [])])
                    ->flatMap(static fn (string $text): array => FrenchTypography::variants($text))
                    ->unique()
                    ->values()
                    ->all();
                $faq = Faq::query()->whereIn('question', $variants)->orderBy('id')->first() ?? new Faq(['is_published' => $publish]);

                $faq->fill([
                    'question' => $question,
                    'category_id' => $category->getKey(),
                    'answer' => $answer,
                    'position' => $index + 1,
                    'show_on_home' => false,
                ])->save();
            }
        }
    }
}
