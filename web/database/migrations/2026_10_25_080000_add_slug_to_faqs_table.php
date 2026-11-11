<?php

declare(strict_types=1);

use App\Models\Faq;
use App\Support\FrenchTypography;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adresse stable d'une question du centre d'aide (P4-05, `/aide/{slug}` ; le prototype help-details.php l'adressait
     * par son rang `?id=`). Facultative au niveau du schéma, renseignée par le modèle Faq à l'enregistrement et ici pour
     * les lignes existantes : d'abord les slugs écrits à la main du seed (`slug_by_index` de
     * database/seeders/data/content/faqs.php, retrouvés par la question sous toutes ses graphies, comme ContentSeeder),
     * sinon Faq::slugBase (≤ 5 mots, SEO_CONTENT_GUIDE_INSTRUCTION.md §4) : mêmes adresses quel que soit l'environnement.
     * DATABASE_DICTIONARY.md §3 `faqs` corrigé.
     */
    public function up(): void
    {
        Schema::table('faqs', function (Blueprint $table) {
            $table->string('slug', 80)->nullable()->unique()->after('category_id');
        });

        $rows = DB::table('faqs')->orderBy('id')->get(['id', 'question']);
        $seeded = $this->seededSlugs();
        $slugs = [];

        // Slugs du seed d'abord : une question dérivée plus ancienne ne peut pas les prendre.
        foreach ($rows as $row) {
            $wanted = $seeded[FrenchTypography::plain((string) $row->question)] ?? null;

            if ($wanted !== null && ! in_array($wanted, $slugs, true)) {
                $slugs[$row->id] = $wanted;
            }
        }

        foreach ($rows as $row) {
            if (isset($slugs[$row->id])) {
                continue;
            }

            $base = Faq::slugBase((string) $row->question);
            $slug = $base;

            for ($i = 2; in_array($slug, $slugs, true); $i++) {
                $slug = $base.'-'.$i;
            }

            $slugs[$row->id] = $slug;
        }

        foreach ($slugs as $id => $slug) {
            DB::table('faqs')->where('id', $id)->update(['slug' => $slug]);
        }
    }

    public function down(): void
    {
        Schema::table('faqs', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }

    /**
     * Question du seed (graphie ordinaire) => slug écrit à la main.
     *
     * @return array<string, string>
     */
    private function seededSlugs(): array
    {
        $file = database_path('seeders/data/content/faqs.php');

        if (! is_file($file)) {
            return [];
        }

        $data = require $file;
        $map = [];

        foreach ($data['rows'] ?? [] as $index => $row) {
            $slug = $data['slug_by_index'][$index] ?? null;

            if (is_string($slug) && is_string($row[0] ?? null)) {
                $map[FrenchTypography::plain($row[0])] = $slug;
            }
        }

        return $map;
    }
};
