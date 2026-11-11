<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lot IOS-05 : plateforme de l'appareil (`ios`, `android`, `web`) pour adapter la charge FCM (bloc `notification`
     * neutre et `apns` sur iOS). Null = inconnue (traitée comme Android). Rattrapage depuis `sync_devices.platform`.
     */
    public function up(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $table): void {
            $table->string('platform', 16)->nullable()->after('kind');
        });

        Illuminate\Support\Facades\DB::table('push_subscriptions')->where('kind', 'webpush')->update(['platform' => 'web']);
    }

    public function down(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $table): void {
            $table->dropColumn('platform');
        });
    }
};
