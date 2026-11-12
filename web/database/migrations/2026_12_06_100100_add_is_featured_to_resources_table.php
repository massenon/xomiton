<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ressource mise en avant (P18-11) : guides et ressources d'urgence placés en tête du catalogue `/ressources` et de
     * l'API `ressources` (badge « À la une »). Faux par défaut : rien ne change pour les ressources existantes. Idempotente.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('resources', 'is_featured')) {
            Schema::table('resources', function (Blueprint $table): void {
                $table->boolean('is_featured')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('resources', 'is_featured')) {
            Schema::table('resources', function (Blueprint $table): void {
                $table->dropColumn('is_featured');
            });
        }
    }
};
