<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tags de segmentation des abonné·es (DATABASE_DICTIONARY.md §9 `newsletter_tags`, ADR-20) :
     * référentiel des valeurs portées en liaison par `newsletter_subscriber_tags`.
     */
    public function up(): void
    {
        Schema::create('newsletter_tags', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();
            $table->string('label', 60);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_tags');
    }
};
