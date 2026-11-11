<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Zones multi-pays (décision client du 11/11/2026, IMPLEMENTATION_PLAN.md Q34) : chaque zone appartient à un pays
     * (`country_code`, ISO 3166-1 alpha-2) ; `BJ` rattrape les zones existantes (valeur par défaut de la colonne). Les communes
     * dépendent de leur zone, donc de son pays. Le nom et le slug d'une zone ne sont plus uniques dans toute la table mais
     * par pays (deux pays peuvent avoir un « Littoral »). Noms d'index explicites, tous de 64 caractères au plus (MySQL).
     */
    public function up(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->dropUnique('zones_name_unique');
            $table->dropUnique('zones_slug_unique');
        });

        Schema::table('zones', function (Blueprint $table) {
            $table->char('country_code', 2)->default('BJ')->after('slug');
            $table->unique(['country_code', 'name'], 'zones_country_name_unique');
            $table->unique(['country_code', 'slug'], 'zones_country_slug_unique');
            $table->index('country_code', 'zones_country_code_index');
            $table->index(['country_code', 'is_active', 'position'], 'zones_country_active_position_index');
        });
    }

    public function down(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->dropUnique('zones_country_name_unique');
            $table->dropUnique('zones_country_slug_unique');
            $table->dropIndex('zones_country_code_index');
            $table->dropIndex('zones_country_active_position_index');
        });

        Schema::table('zones', function (Blueprint $table) {
            $table->dropColumn('country_code');
            $table->unique('name', 'zones_name_unique');
            $table->unique('slug', 'zones_slug_unique');
        });
    }
};
