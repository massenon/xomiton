<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Effacement à distance d'un appareil (P15-11, volet serveur) : `wipe_requested_at` marque l'appareil « à effacer »
     * (posé par l'administration ou au retrait du rôle pair), `wipe_reason` en garde le motif (liste fermée), `wiped_at`
     * l'accusé de l'appareil (`POST me/devices/{uuid}/wiped`).
     */
    public function up(): void
    {
        Schema::table('sync_devices', function (Blueprint $table): void {
            $table->timestamp('wipe_requested_at')->nullable()->after('is_revoked');
            $table->string('wipe_reason', 30)->nullable()->after('wipe_requested_at');
            $table->timestamp('wiped_at')->nullable()->after('wipe_reason');
        });
    }

    public function down(): void
    {
        Schema::table('sync_devices', function (Blueprint $table): void {
            $table->dropColumn(['wipe_requested_at', 'wipe_reason', 'wiped_at']);
        });
    }
};
