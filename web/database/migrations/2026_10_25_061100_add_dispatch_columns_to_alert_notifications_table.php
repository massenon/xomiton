<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lot alertes-dispatch (P5-03) : consignation de chaque remise d'alerte. `recipient` ne contient que des références
 * (`user:`, `subscription:`, `contact:`, `trusted:`), jamais un numéro ni une adresse (ADR-33) : aucune colonne
 * chiffrée n'est ajoutée. L'index unique garantit « une notification par événement et par personne » (NOTIFICATIONS §4.2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alert_notifications', function (Blueprint $table): void {
            // Ce qui a été envoyé (App\Enums\NotificationPurpose) : le rejeu reconstruit la notification à partir de lui.
            $table->string('purpose', 30)->default('sos_triggered')->after('channel');
            // Pilote effectif (log, vapid, firebase, fournisseur SMS, database, outbox, smtp) : « fournisseur » de P1-05.
            $table->string('provider', 20)->nullable()->after('status');
            // Compte destinataire (admin de garde, compte lié à une fiche, auteur·e) : requêtes et état « vu ».
            $table->foreignId('user_id')->nullable()->after('directory_contact_id')->constrained('users')->nullOnDelete();
            // Abonnement visé par une ligne push ; un abonnement 404 / 410 supprimé laisse la ligne (abandonnée au rejeu).
            $table->foreignId('push_subscription_id')->nullable()->after('user_id')->constrained('push_subscriptions')->nullOnDelete();
            // Accusé de réception de la personne (compte ou lien signé) : état « vu » de la page SOS.
            $table->timestamp('seen_at')->nullable()->after('sent_at');
            $table->unique(['alert_id', 'purpose', 'channel', 'recipient'], 'alert_notifications_dedup_unique');
        });
    }

    public function down(): void
    {
        Schema::table('alert_notifications', function (Blueprint $table): void {
            $table->dropUnique('alert_notifications_dedup_unique');
            $table->dropConstrainedForeignId('push_subscription_id');
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['purpose', 'provider', 'seen_at']);
        });
    }
};
