<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Liaison abonné·e ↔ tag (DATABASE_DICTIONARY.md §9 `newsletter_subscriber_tags`) : un seul couple,
     * cascade des deux côtés, sans horodatage.
     */
    public function up(): void
    {
        Schema::create('newsletter_subscriber_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscriber_id')->constrained('newsletter_subscribers')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('newsletter_tags')->cascadeOnDelete();

            $table->unique(['subscriber_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscriber_tags');
    }
};
