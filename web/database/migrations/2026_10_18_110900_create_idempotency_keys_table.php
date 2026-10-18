<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Réponses mémorisées du middleware `idempotent` (P5-09, ADR-32, DATABASE_DICTIONARY.md §10 `idempotency_keys`) :
     * une ligne par clé, route et acteur ; `status_code` nul = requête d'origine en cours. Ni l'acteur ni le chemin concret
     * ne sont stockés (RG-01 / RG-02 : aucun lien entre un compte et un signalement anonyme) ; en-têtes et corps de la
     * réponse sont chiffrés (code de suivi, ULID de SOS). Purgé par `idempotency:purge` après `expires_at`.
     */
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->char('idempotency_key', 36);
            // HMAC-SHA256 (clé d'application) de « acteur | méthode | chemin concret » : ni compte ni identifiant en clair.
            $table->char('scope', 64);
            $table->string('method', 10);
            // Gabarit de route (`/api/v1/sos/{alert}/cancel`), jamais le chemin concret.
            $table->string('route', 255);
            $table->char('request_hash', 64);
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->text('response_headers')->nullable();
            $table->longText('response_body')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['scope', 'idempotency_key']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
