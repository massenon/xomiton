<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catalogue des indicateurs de pilotage (DATABASE_DICTIONARY.md §7 `indicators`). Les 16 indicateurs
     * du prototype sont `is_builtin` et calculés par `calc_key` ; `calc_key` null = indicateur saisi à la main.
     */
    public function up(): void
    {
        Schema::create('indicators', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('label', 160);
            $table->text('definition');
            $table->string('formula', 255);
            $table->string('source', 80);
            $table->string('frequency', 15);
            $table->decimal('target', 10, 2)->nullable();
            $table->string('unit', 5);
            $table->string('calc_key', 40)->nullable();
            $table->string('better', 4)->default('up');
            $table->boolean('is_builtin')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicators');
    }
};
