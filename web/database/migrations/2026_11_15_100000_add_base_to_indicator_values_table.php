<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dénominateur (effectif de base) d'un pourcentage calculé : sans lui, la tendance d'un pourcentage ne peut pas
     * appliquer le masquage des effectifs de 1 à 4 (« 100 % » issu d'un seul dossier). Nul pour un nombre, une saisie
     * manuelle ou un instantané antérieur à cette colonne (la tendance ne l'affiche alors pas).
     */
    public function up(): void
    {
        Schema::table('indicator_values', function (Blueprint $table) {
            $table->unsignedInteger('base')->nullable()->after('value');
        });
    }

    public function down(): void
    {
        Schema::table('indicator_values', function (Blueprint $table) {
            $table->dropColumn('base');
        });
    }
};
