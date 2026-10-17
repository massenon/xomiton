<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Notifications émises pour une alerte (DATABASE_DICTIONARY.md §5 `alert_notifications`) :
     * une ligne par canal et destinataire, rejouée par le cron si l'envoi échoue (index status, next_retry_at).
     */
    public function up(): void
    {
        Schema::create('alert_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alert_id')->constrained('alerts')->restrictOnDelete();
            $table->string('channel', 10);
            $table->string('recipient', 190);
            $table->foreignId('directory_contact_id')->nullable()->constrained('directory_contacts')->nullOnDelete();
            $table->string('status', 10)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('last_error', 255)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_retry_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_notifications');
    }
};
