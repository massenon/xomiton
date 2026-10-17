<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Textes de loi présentés sur la page « Lois » (DATABASE_DICTIONARY.md §3 `laws`, écart résolu
     * « Lois (x_lois, page /lois) → table laws »).
     */
    public function up(): void
    {
        Schema::create('laws', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('title', 200);
            $table->string('subtitle', 200)->nullable();
            $table->text('summary');
            $table->string('category', 80);
            $table->string('official_url', 500)->nullable();
            $table->unsignedTinyInteger('position')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laws');
    }
};
