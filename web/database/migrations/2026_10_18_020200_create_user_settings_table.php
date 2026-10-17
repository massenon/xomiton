<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('theme', 10)->default('auto');
            $table->string('language', 5)->default('fr');
            // Préférences par catégorie : { categorie: { in_app, email, push } } ; sécurité et SOS non désactivables.
            $table->json('notification_prefs')->nullable();
            $table->boolean('mask_sensitive_notifications')->default(false);
            // Résumé d'activité : never, daily, weekly (prototype settings.php : jamais / quotidien / hebdo).
            $table->string('notification_digest', 10)->default('never');
            $table->boolean('sos_share_gps')->default(true);
            $table->boolean('pin_enabled')->default(false);
            $table->string('pin_hash', 255)->nullable();
            $table->boolean('neutral_tab_title')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};
