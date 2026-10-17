<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Clics sur les liens d'une campagne (DATABASE_DICTIONARY.md §9 `newsletter_clicks`) : une ligne par
     * (destinataire, URL) pour les clics uniques, `clicks_count` cumule les clics répétés.
     */
    public function up(): void
    {
        Schema::create('newsletter_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipient_id')->constrained('newsletter_campaign_recipients')->cascadeOnDelete();
            $table->string('url', 2048);
            $table->char('url_hash', 64);
            $table->timestamp('clicked_at');
            $table->unsignedSmallInteger('clicks_count')->default(1);
            $table->timestamp('created_at')->nullable();

            $table->unique(['recipient_id', 'url_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_clicks');
    }
};
