<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Salons de discussion entre pair·es (DATABASE_DICTIONARY.md §8 `peer_rooms`) : remplace le paramètre
     * `gateway_chat` du prototype par une ligne par salon ; le salon est une conversation de groupe.
     */
    public function up(): void
    {
        Schema::create('peer_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_moderated')->default(true);
            $table->boolean('allow_anonymous')->default(true);
            $table->unsignedSmallInteger('max_members')->default(50);
            $table->foreignId('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peer_rooms');
    }
};
