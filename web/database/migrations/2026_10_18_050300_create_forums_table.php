<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Forums thématiques (DATABASE_DICTIONARY.md §4 `forums`) : clé courte du prototype (`f1`…),
     * compteurs de sujets et de messages dénormalisés.
     */
    public function up(): void
    {
        Schema::create('forums', function (Blueprint $table) {
            $table->id();
            $table->string('key', 10)->unique();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->string('description', 255);
            $table->string('icon', 40);
            $table->unsignedTinyInteger('position')->default(0);
            $table->unsignedInteger('topics_count')->default(0);
            $table->unsignedInteger('posts_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forums');
    }
};
