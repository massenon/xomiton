<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Type d'un événement (P18-14) : `groupe_parole`, `atelier`, `rencontre` ou `autre` (défaut : les événements existants
     * deviennent « Autre »). Le statut `en_attente` (file d'approbation facultative, réglage `events.require_approval`)
     * tient dans `status` (varchar 10) sans changement de schéma. Idempotente.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('events', 'type')) {
            Schema::table('events', function (Blueprint $table): void {
                $table->string('type', 20)->default('autre');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('events', 'type')) {
            Schema::table('events', function (Blueprint $table): void {
                $table->dropColumn('type');
            });
        }
    }
};
