<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Application\Seo\MetaText;
use App\Enums\ContentStatus;
use App\Enums\ResourceKind;
use App\Models\Resource;
use App\Models\Thematique;
use App\Support\FrenchTypography;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Actualités de démonstration pour `/actualites` (P4-10 ; data/content/news_demo.php, prototype x_blog_articles()) :
 * six ressources `kind = article` (Q1 a) tirées des fiches thématiques semées par ContentSeeder — titre, résumé,
 * catégorie, thématique liée (couverture de repli), durée de lecture — avec le corps générique de blog-details.php.
 * Jamais en production (DatabaseSeeder::DEMO_ENVIRONMENTS) ; idempotent (clé : le slug) ; une fiche thématique absente
 * est ignorée ; un slug déjà pris par une ressource d'un autre type n'est jamais écrasé.
 */
class DemoNewsSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(DatabaseSeeder::DEMO_ENVIRONMENTS) || ! Schema::hasTable('resources')) {
            return;
        }

        /** @var array{published_from: string, thematiques: list<int>, days_step: int, days_offset: int, sections: list<array{0: string, 1: string}>} $data */
        $data = require database_path('seeders/data/content/news_demo.php');
        $from = Carbon::parse($data['published_from']);

        foreach ($data['thematiques'] as $index => $protoId) {
            $thematique = Thematique::query()->where('cover_path', 'like', '%/covers/th-'.$protoId.'.jpg')->first();

            if ($thematique === null) {
                continue;
            }

            $slug = Str::slug((string) $thematique->title);
            $existing = Resource::query()->withTrashed()->where('slug', $slug)->first();

            if ($existing !== null && $existing->kind !== ResourceKind::Article) {
                continue;
            }

            $summary = (string) $thematique->summary;
            $body = $this->body($data['sections'], $summary);
            $article = Resource::query()->withTrashed()->updateOrCreate(['slug' => $slug], [
                'category_id' => $thematique->category_id,
                'thematique_id' => $thematique->getKey(),
                'title' => (string) $thematique->title,
                'kind' => ResourceKind::Article,
                'summary' => $summary,
                'body' => $body,
                'image_path' => null,
                'duration_minutes' => $thematique->reading_minutes,
                'status' => ContentStatus::Published,
                'published_at' => $from->copy()->subDays($index * $data['days_step'] + $data['days_offset']),
                'meta_title' => MetaText::title((string) $thematique->title),
                'meta_description' => MetaText::description($summary, $body, (string) __('pages/actualites.meta_description_filler')),
            ]);

            if ($article->trashed()) {
                $article->restore();
            }
        }
    }

    /**
     * @param  list<array{0: string, 1: string}>  $sections
     */
    private function body(array $sections, string $summary): string
    {
        $html = '';

        foreach ($sections as [$title, $text]) {
            $html .= '<h2>'.e(FrenchTypography::apply($title)).'</h2>'
                .'<p>'.e(FrenchTypography::apply(str_replace(':summary', rtrim($summary), $text))).'</p>';
        }

        return $html;
    }
}
