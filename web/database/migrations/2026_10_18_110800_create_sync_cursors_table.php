<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Curseurs de synchronisation descendante (DATABASE_DICTIONARY.md §10 `sync_cursors`) : pour chaque appareil
     * et chaque collection, le dernier identifiant tiré, afin de ne renvoyer que le delta au prochain `pull`.
     */
    public function up(): void
    {
        Schema::create('sync_cursors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('sync_devices')->cascadeOnDelete();
            $table->string('collection', 40);
            $table->unsignedBigInteger('cursor')->default(0);
            $table->timestamp('pulled_at')->nullable();
            $table->timestamps();

            $table->unique(['device_id', 'collection']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_cursors');
    }
};
