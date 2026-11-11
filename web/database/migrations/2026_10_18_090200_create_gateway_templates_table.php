<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Messages clés réutilisables pour les publications sortantes (DATABASE_DICTIONARY.md §8
     * `gateway_templates`, prototype `gateway_templates.json`).
     */
    public function up(): void
    {
        Schema::create('gateway_templates', function (Blueprint $table) {
            $table->id();
            $table->string('title', 80);
            $table->string('body', 500);
            $table->string('category', 40)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_templates');
    }
};
