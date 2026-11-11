<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lot api-me (P6-06) : colonnes annoncées par P1-02 mais absentes du schéma livré.
     *  - `profiles.ally_status` : allié·e / personne ressource du profil progressif (Q10 c), chiffré, aucun droit associé ;
     *  - `profiles.interests` : centres d'intérêt (slugs de `newsletter_interests`, prototype `simulateur.html` l.1222) ;
     *  - `user_settings.sounds_enabled` : sons de l'application (le son du SOS reste actif pour qui le reçoit).
     * DATABASE_DICTIONARY.md §2. Gardé par hasColumn : un autre lot peut avoir posé la même colonne.
     */
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table): void {
            if (! Schema::hasColumn('profiles', 'ally_status')) {
                $table->text('ally_status')->nullable();
            }

            if (! Schema::hasColumn('profiles', 'interests')) {
                $table->json('interests')->nullable();
            }
        });

        Schema::table('user_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('user_settings', 'sounds_enabled')) {
                $table->boolean('sounds_enabled')->default(true);
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('user_settings', 'sounds_enabled')) {
                $table->dropColumn('sounds_enabled');
            }
        });

        Schema::table('profiles', function (Blueprint $table): void {
            foreach (['interests', 'ally_status'] as $column) {
                if (Schema::hasColumn('profiles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
