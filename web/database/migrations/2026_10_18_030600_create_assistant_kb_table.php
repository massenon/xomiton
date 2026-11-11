<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Base de connaissances de l'assistant (DATABASE_DICTIONARY.md §3 `assistant_kb`) : question type,
     * réponse et mots-clés d'appariement ; `hits_count` mesure l'utilité de chaque entrée.
     */
    public function up(): void
    {
        Schema::create('assistant_kb', function (Blueprint $table) {
            $table->id();
            $table->string('question', 255);
            $table->text('answer');
            $table->json('keywords');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('hits_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_kb');
    }
};
