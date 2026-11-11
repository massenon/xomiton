<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Termes filtrés par la modération automatique (DATABASE_DICTIONARY.md §3 `bad_words`).
     */
    public function up(): void
    {
        Schema::create('bad_words', function (Blueprint $table) {
            $table->id();
            $table->string('word', 80)->unique();
            $table->string('severity', 10);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bad_words');
    }
};
