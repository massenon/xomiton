<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sondage porté par une publication (1–1, RG-09) : 2 à 6 options validées côté application.
     */
    public function up(): void
    {
        Schema::create('polls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->unique()->constrained('posts')->cascadeOnDelete();
            $table->string('question', 200);
            $table->boolean('multiple')->default(false);
            $table->boolean('results_before_vote')->default(false);
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('votes_count')->default(0);
            $table->unsignedInteger('voters_count')->default(0);
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('polls');
    }
};
