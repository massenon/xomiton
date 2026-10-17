<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Communes rattachées à un département (DATABASE_DICTIONARY.md §1 `communes`).
     */
    public function up(): void
    {
        Schema::create('communes', function (Blueprint $table) {
            $table->id();
            // restrict : on ne supprime pas un département qui a encore des communes.
            $table->foreignId('zone_id')->constrained()->restrictOnDelete();
            $table->string('name', 80);
            $table->string('slug', 80);
            $table->unsignedTinyInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['zone_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communes');
    }
};
