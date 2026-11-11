<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Signalement d'une inscription (D-MOB-17, tranché 2026-10-29) : codes séparés par des virgules posés à l'inscription
     * (`disposable_email`, `repeated_fingerprint`), jamais l'empreinte ni l'adresse IP. Un compte signalé est placé en
     * attente de validation ; la file des inscriptions de l'administration affiche les motifs.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('registration_flags', 120)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('registration_flags');
        });
    }
};
