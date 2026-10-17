<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Centres d'intérêt des abonné·es (DATABASE_DICTIONARY.md §9 `newsletter_interests`, ADR-20) :
     * référentiel des valeurs portées en liaison par `newsletter_subscriber_interests`.
     */
    public function up(): void
    {
        Schema::create('newsletter_interests', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();
            $table->string('label', 60);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_interests');
    }
};
