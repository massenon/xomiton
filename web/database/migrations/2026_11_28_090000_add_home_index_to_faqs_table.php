<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Performance (docs/PERFORMANCE.md §11) : la FAQ de la page d'accueil filtre sur `is_published` et `show_on_home` puis
     * trie sur `position` ; l'index évite le parcours et le tri de toute la table à chaque (re)calcul du cache de l'accueil.
     */
    public function up(): void
    {
        Schema::table('faqs', function (Blueprint $table) {
            $table->index(['is_published', 'show_on_home', 'position'], 'faqs_home_index');
        });
    }

    public function down(): void
    {
        Schema::table('faqs', function (Blueprint $table) {
            $table->dropIndex('faqs_home_index');
        });
    }
};
