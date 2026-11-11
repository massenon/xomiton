<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Escalades d'une alerte (DATABASE_DICTIONARY.md §5 `alert_escalations`) : SOS sans accusé
     * sous 5 minutes (RG-05) ou réaffectation manuelle ; journal immuable.
     */
    public function up(): void
    {
        Schema::create('alert_escalations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alert_id')->constrained('alerts')->restrictOnDelete();
            $table->foreignId('from_contact_id')->nullable()->constrained('directory_contacts')->nullOnDelete();
            $table->foreignId('to_contact_id')->nullable()->constrained('directory_contacts')->nullOnDelete();
            $table->string('reason', 40);
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_escalations');
    }
};
