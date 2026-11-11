<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Destinataires d'une campagne (DATABASE_DICTIONARY.md §9 `newsletter_campaign_recipients`) : table de
     * liaison campagne ↔ abonné·e portant l'envoi, l'ouverture, le rebond et la désinscription.
     * Taux d'ouverture = ouvertures / (envoyés − rebonds).
     */
    public function up(): void
    {
        Schema::create('newsletter_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('newsletter_campaigns')->cascadeOnDelete();
            $table->foreignId('subscriber_id')->constrained('newsletter_subscribers')->cascadeOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->boolean('bounced')->default(false);
            $table->boolean('unsubscribed')->default(false);
            $table->string('error', 255)->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'subscriber_id']);
            $table->index(['campaign_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_campaign_recipients');
    }
};
