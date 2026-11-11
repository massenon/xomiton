<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remise d'une publication sortante sur un réseau (DATABASE_DICTIONARY.md §8 `outbound_deliveries`) :
     * table de liaison publication ↔ passerelle, une ligne par réseau ciblé, cascade des deux côtés.
     */
    public function up(): void
    {
        Schema::create('outbound_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outbound_post_id')->constrained('outbound_posts')->cascadeOnDelete();
            $table->foreignId('social_channel_id')->constrained('social_channels')->cascadeOnDelete();
            $table->string('status', 10)->default('en_attente');
            $table->string('external_id', 120)->nullable();
            $table->string('external_url', 500)->nullable();
            $table->string('error', 255)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['outbound_post_id', 'social_channel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_deliveries');
    }
};
