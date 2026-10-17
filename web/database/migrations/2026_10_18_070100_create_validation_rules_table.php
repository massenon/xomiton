<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catalogue des règles de validation administrables (DATABASE_DICTIONARY.md §6 `validation_rules`).
     * Remplace les clés `rules_off`, `plausibility`, `dup_*` des paramètres du prototype : une ligne par règle,
     * `is_active` pour l'interrupteur, `is_locked` pour la règle `required` (toujours active, non désactivable)
     * et `params` pour les bornes (plausibilité) ou les champs (doublons).
     */
    public function up(): void
    {
        Schema::create('validation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('label', 120);
            $table->string('description', 255);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_locked')->default(false);
            $table->json('params')->nullable();
            $table->string('valid_example', 120)->nullable();
            $table->string('invalid_example', 120)->nullable();
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('validation_rules');
    }
};
