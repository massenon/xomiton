<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Compteurs d'événements de contenu (P10-06, CONTENT_STRATEGY.md §1) : un compteur par type d'événement et par jour,
     * sans traceur tiers ni donnée personnelle (ni identifiant, ni IP, ni agent utilisateur). Conservés 30 jours
     * (`content-events:purge`).
     */
    public function up(): void
    {
        Schema::create('content_events', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 40);
            $table->date('day');
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('updated_at')->nullable();
            $table->unique(['kind', 'day']);
            $table->index('day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_events');
    }
};
