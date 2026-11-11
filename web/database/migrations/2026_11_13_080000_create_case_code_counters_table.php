<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Compteurs des codes de registre (RG-06, DATABASE_DICTIONARY.md §6 `case_code_counters`) : une ligne par nature
     * (`VBG`, `VDH`), dernier rang attribué. CaseRecord::nextCode verrouille **cette seule ligne** (et non les dossiers
     * de la nature) pendant la transaction de l'insertion. Créée à la première attribution d'après le plus haut code en
     * base : pas de reprise de données.
     */
    public function up(): void
    {
        Schema::create('case_code_counters', function (Blueprint $table) {
            $table->string('kind', 3)->primary();
            $table->unsignedInteger('last_sequence')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_code_counters');
    }
};
