<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Compléments de `alerts` (P5-01, P5-04, P5-07) : date des faits (collectée par le prototype, signalement.php l.57-58,
 * mais jamais stockée), canal préféré de recontact (signaler-et-sos.md l.11) et marqueur du rappel au contact routé
 * (Q27 : rappel puis escalade des signalements et besoins sans accusé, idempotence du cron). Aucune colonne chiffrée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alerts', function (Blueprint $table): void {
            $table->date('occurred_on')->nullable()->after('category');
            $table->string('preferred_channel', 10)->nullable()->after('contact');
            $table->timestamp('reminded_at')->nullable()->after('escalated_at');
        });
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table): void {
            $table->dropColumn(['occurred_on', 'preferred_channel', 'reminded_at']);
        });
    }
};
