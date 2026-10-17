<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kit CCC numérisé (DATABASE_DICTIONARY.md §3 `ccc_tools`) : modules du manuel (`chapters`),
     * boîtes à images et bandes dessinées (`pages`), identifiés par une clé publique courte (`m1`, `bd1`…).
     */
    public function up(): void
    {
        Schema::create('ccc_tools', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20);
            $table->string('key', 40)->unique();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('image_path', 255)->nullable();
            $table->json('pages')->nullable();
            $table->json('chapters')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->unsignedTinyInteger('position')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ccc_tools');
    }
};
