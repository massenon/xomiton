<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drapeau « contact d'urgence » de l'annuaire (P11-04, Q59 a) : une fiche publique cochée est servie hors connexion
     * par `/urgences` et `GET urgences`, en plus des rôles d'urgence (point focal, juriste, psychologue). Faux par défaut :
     * aucune fiche existante ne change de visibilité.
     */
    public function up(): void
    {
        Schema::table('directory_contacts', function (Blueprint $table) {
            $table->boolean('is_emergency')->default(false)->after('is_national_focal');
        });
    }

    public function down(): void
    {
        Schema::table('directory_contacts', function (Blueprint $table) {
            $table->dropColumn('is_emergency');
        });
    }
};
