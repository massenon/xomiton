<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Inscription en 3 étapes (décision client du 11/11/2026) :
     *  - `age` : âge saisi (entier), `age_range` en est déduit ;
     *  - `country_code` (ISO 3166-1 alpha-2) et `city` (texte libre) ;
     *  - `ally_role`, `ally_role_other`, `gender_identity_other`, `orientation_other` : données sensibles, chiffrées par
     *    cast Eloquent (colonnes text, jamais indexées ; config `xomiton.encrypted_columns.profiles`).
     * Toutes nullables : les profils existants n'ont rien de ces informations.
     */
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->unsignedTinyInteger('age')->nullable()->after('age_range');
            $table->char('country_code', 2)->nullable()->after('age');
            $table->string('city', 120)->nullable()->after('country_code');
            $table->text('gender_identity_other')->nullable()->after('gender_identity');
            $table->text('orientation_other')->nullable()->after('orientation');
            $table->text('ally_role')->nullable()->after('ally_status');
            $table->text('ally_role_other')->nullable()->after('ally_role');

            $table->index('country_code', 'profiles_country_code_index');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropIndex('profiles_country_code_index');
            $table->dropColumn(['age', 'country_code', 'city', 'gender_identity_other', 'orientation_other', 'ally_role', 'ally_role_other']);
        });
    }
};
