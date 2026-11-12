<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P19-11 : `is_republish` — republication **explicite** d'une BD déjà diffusée sur un réseau. Sans elle, une BD n'est
     * jamais planifiée ni publiée deux fois sur le même canal (ManageGateways, PublishDueDeliveries). Idempotente.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('outbound_posts', 'is_republish')) {
            Schema::table('outbound_posts', function (Blueprint $table): void {
                $table->boolean('is_republish')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('outbound_posts', 'is_republish')) {
            Schema::table('outbound_posts', function (Blueprint $table): void {
                $table->dropColumn('is_republish');
            });
        }
    }
};
