<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P19 (contenus et BD sur les violences numériques, Q98, Q19) :
     *  - `ccc_tools.is_public` : BD servie aussi sur la page publique indexable `/bd/{key}` (défaut faux : bd1–bd3 restent
     *    réservées aux membres et pair·es) ;
     *  - `ccc_tools.thematique_id` : fiche de connaissances liée à la BD (lien BD ↔ fiche) ;
     *  - `ccc_tools.feed_post_id` : publication du fil qui a annoncé la BD (marqueur d'idempotence : jamais republiée) ;
     *  - `thematiques.legal_references` : références juridiques (HTML nettoyé) saisies et validées par l'administration,
     *    affichées seulement si remplies ;
     *  - `faqs.thematique_id` : questions propres à une fiche (FAQ de la fiche avant celles de sa catégorie).
     * Idempotente (hasColumn) ; pas de clé étrangère (SQLite ne sait pas en ajouter à une table existante) : une fiche ou
     * une publication supprimée laisse un identifiant orphelin, ignoré à la lecture.
     */
    public function up(): void
    {
        Schema::table('ccc_tools', function (Blueprint $table): void {
            if (! Schema::hasColumn('ccc_tools', 'is_public')) {
                $table->boolean('is_public')->default(false);
            }

            if (! Schema::hasColumn('ccc_tools', 'thematique_id')) {
                $table->unsignedBigInteger('thematique_id')->nullable()->index();
            }

            if (! Schema::hasColumn('ccc_tools', 'feed_post_id')) {
                $table->unsignedBigInteger('feed_post_id')->nullable();
            }
        });

        if (! Schema::hasColumn('thematiques', 'legal_references')) {
            Schema::table('thematiques', function (Blueprint $table): void {
                $table->text('legal_references')->nullable();
            });
        }

        if (! Schema::hasColumn('faqs', 'thematique_id')) {
            Schema::table('faqs', function (Blueprint $table): void {
                $table->unsignedBigInteger('thematique_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('faqs', 'thematique_id')) {
            Schema::table('faqs', function (Blueprint $table): void {
                $table->dropIndex(['thematique_id']);
                $table->dropColumn('thematique_id');
            });
        }

        if (Schema::hasColumn('thematiques', 'legal_references')) {
            Schema::table('thematiques', function (Blueprint $table): void {
                $table->dropColumn('legal_references');
            });
        }

        Schema::table('ccc_tools', function (Blueprint $table): void {
            if (Schema::hasColumn('ccc_tools', 'thematique_id')) {
                $table->dropIndex(['thematique_id']);
            }

            foreach (['feed_post_id', 'thematique_id', 'is_public'] as $column) {
                if (Schema::hasColumn('ccc_tools', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
