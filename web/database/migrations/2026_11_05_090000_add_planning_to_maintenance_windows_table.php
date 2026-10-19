<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Planification des fenêtres de maintenance (P13-06, DATABASE_DICTIONARY.md §10 `maintenance_windows`) :
     * message public porté à 280 caractères (éditeur de l'écran d'administration), motif interne jamais affiché au
     * public, délai d'annonce préalable (bandeau P13-08) et annulation d'une fenêtre à venir (jamais supprimée).
     */
    public function up(): void
    {
        Schema::table('maintenance_windows', function (Blueprint $table) {
            $table->string('message', 280)->nullable()->change();
            $table->string('reason', 255)->nullable()->after('message');
            $table->unsignedSmallInteger('announce_hours')->default(0)->after('reason');
            $table->timestamp('cancelled_at')->nullable()->after('is_active');
            $table->index(['is_active', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_windows', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'starts_at']);
            $table->dropColumn(['reason', 'announce_hours', 'cancelled_at']);
            $table->string('message', 255)->nullable()->change();
        });
    }
};
