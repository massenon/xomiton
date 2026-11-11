<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Profil (P8-07, P8-08) :
     *  - `shared_fields` : champs sensibles que la personne a **explicitement** choisi de montrer sur son profil
     *    (`gender_identity`, `orientation`, `ally_status` ; App\Domain\Account\SensitiveProfile). Null = aucun : rien de
     *    sensible n'apparaît sur `/membres/{pseudo}` ni dans `GET members/{pseudo}` sans ce choix ;
     *  - `progressive_answered` : groupes du profil progressif déjà traités (réponse ou « Je préfère ne pas le dire »),
     *    que le bandeau « Complète ton profil » ne repropose plus.
     */
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->json('shared_fields')->nullable()->after('interests');
            $table->json('progressive_answered')->nullable()->after('shared_fields');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn(['shared_fields', 'progressive_answered']);
        });
    }
};
