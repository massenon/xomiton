<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pages d'organisations (DATABASE_DICTIONARY.md §4 `pages`) : catégorie libre (Organisation,
     * Santé, Juridique, Réseau…), vérification par l'administration.
     */
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->string('category', 60);
            $table->text('description')->nullable();
            $table->string('image_path', 255)->nullable();
            $table->string('cover_path', 255)->nullable();
            $table->string('website', 190)->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('followers_count')->default(0);
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
