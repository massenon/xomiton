<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Alertes : signalements, demandes de besoin et SOS (DATABASE_DICTIONARY.md §5 `alerts`).
     * Pas de suppression logique : l'historique ne se supprime jamais par cascade (`restrict` depuis
     * les tables filles). Récit, position GPS et contact sont chiffrés (colonnes `text`, jamais indexées).
     */
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('type', 15);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_anonymous')->default(true);
            $table->string('pseudo', 40)->nullable();
            $table->string('category', 20)->nullable();
            $table->text('description')->nullable();
            $table->json('needs')->nullable();
            $table->foreignId('zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->foreignId('commune_id')->nullable()->constrained('communes')->nullOnDelete();
            $table->text('lat')->nullable();
            $table->text('lng')->nullable();
            $table->unsignedSmallInteger('location_accuracy')->nullable();
            $table->text('contact')->nullable();
            $table->string('status', 15)->default('nouveau');
            $table->string('priority', 10)->default('normale');
            $table->foreignId('routed_to')->nullable()->constrained('directory_contacts')->nullOnDelete();
            $table->string('routing_fallback', 20)->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('safe_at')->nullable();
            $table->char('tracking_code', 10)->unique();
            $table->string('source', 15)->default('web');
            $table->char('idempotency_key', 36)->nullable()->unique();
            $table->foreignId('device_id')->nullable()->constrained('sync_devices')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['type', 'status']);
            $table->index(['routed_to', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
