<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Planning de garde (P1-17, NOTIFICATIONS_INSTRUCTION.md §2) : plages horaires pendant lesquelles un compte
     * est « admin de garde » (`kind = admin`, destinataire des SOS et alertes routées) ou point focal remplaçant
     * (`kind = focal`, national si `zone_id` est nul, sinon pour le département). Lu par `OnCallRoster`
     * (`SosDispatcher` P5-03, `alerts:escalate` P5-07). Lot 12 « garde ».
     */
    public function up(): void
    {
        Schema::create('on_call_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('kind', 20)->default('admin');
            $table->foreignId('zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->string('notes', 200)->nullable();
            $table->timestamps();

            $table->index(['starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('on_call_shifts');
    }
};
