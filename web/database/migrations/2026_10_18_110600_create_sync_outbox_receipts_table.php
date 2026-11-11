<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Accusés de réception de la file hors connexion (DATABASE_DICTIONARY.md §10 `sync_outbox_receipts`) :
     * un reçu par élément poussé par un appareil, identifié par sa clé d'idempotence, pour rejouer sans doublon.
     * Jamais modifié ; supprimé avec l'appareil.
     */
    public function up(): void
    {
        Schema::create('sync_outbox_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('sync_devices')->cascadeOnDelete();
            $table->char('idempotency_key', 36);
            $table->string('kind', 20);
            $table->string('subject_type', 80)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('result', 10);
            $table->char('payload_hash', 64);
            $table->timestamp('created_at')->nullable();

            $table->unique(['device_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_outbox_receipts');
    }
};
