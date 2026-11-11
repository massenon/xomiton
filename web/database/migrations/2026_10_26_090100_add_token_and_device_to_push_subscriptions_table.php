<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lot api-me (P6-09, critère « un jeton révoqué n'est plus notifié ») : lien entre l'abonnement push et l'accès qui
     * l'a enregistré (DATABASE_DICTIONARY.md §9 `push_subscriptions`, Q70).
     *  - `token_id` : jeton Sanctum de l'appareil (null pour une session web) ; supprimer le jeton (déconnexion,
     *    DELETE me/sessions/{id}, changement de mot de passe, suppression du compte) supprime l'abonnement (cascade) ;
     *  - `sync_device_id` : appareil `X-Device-Id` ; sa révocation (DELETE me/devices/{uuid}) supprime l'abonnement
     *    (App\Application\Account\DeviceRegistry::revoke ; les appareils ne sont jamais supprimés, d'où nullOnDelete).
     */
    public function up(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $table): void {
            $table->foreignId('token_id')->nullable()->after('user_id')->constrained('personal_access_tokens')->cascadeOnDelete();
            $table->foreignId('sync_device_id')->nullable()->after('token_id')->constrained('sync_devices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sync_device_id');
            $table->dropConstrainedForeignId('token_id');
        });
    }
};
