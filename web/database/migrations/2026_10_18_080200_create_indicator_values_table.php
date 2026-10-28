<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Valeurs d'indicateur par période et par zone (DATABASE_DICTIONARY.md §7 `indicator_values`) : saisies
     * manuelles et instantanés calculés par `indicators:snapshot`. `zone_id` null = valeur nationale.
     * L'unicité SQL ne couvre pas les lignes à `zone_id` null (NULL distincts) : l'application passe par
     * `updateOrCreate` sur le même quadruplet.
     */
    public function up(): void
    {
        Schema::create('indicator_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indicator_id')->constrained('indicators')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->foreignId('zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->decimal('value', 12, 2);
            $table->boolean('is_manual')->default(false);
            $table->timestamp('computed_at');
            $table->timestamps();

            $table->unique(['indicator_id', 'period_start', 'period_end', 'zone_id'], 'indicator_values_period_zone_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicator_values');
    }
};
