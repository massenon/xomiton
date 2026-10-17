<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catégories typées (thématiques, ressources, FAQ, événements) (DATABASE_DICTIONARY.md §1 `categories`).
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20);
            $table->string('slug', 60);
            $table->string('label', 80);
            $table->string('icon', 40)->nullable();
            $table->string('color', 20)->nullable();
            $table->unsignedTinyInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['kind', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
