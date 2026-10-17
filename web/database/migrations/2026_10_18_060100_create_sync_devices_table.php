<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Appareils de synchronisation hors connexion des pair·es (DATABASE_DICTIONARY.md §10 `sync_devices`).
     * Avancée dans le lot « alertes » car `alerts.device_id` et `cases.device_id` la référencent.
     */
    public function up(): void
    {
        Schema::create('sync_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->char('device_uuid', 36)->unique();
            $table->string('label', 80);
            $table->string('platform', 40)->nullable();
            $table->string('app_version', 20)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->unsignedInteger('pending_count')->default(0);
            $table->unsignedInteger('conflicts_count')->default(0);
            $table->boolean('is_revoked')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_devices');
    }
};
