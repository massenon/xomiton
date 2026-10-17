<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Options d'un sondage : libellé unique par sondage, ordre d'affichage par `position`.
     */
    public function up(): void
    {
        Schema::create('poll_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained('polls')->cascadeOnDelete();
            $table->string('label', 80);
            $table->unsignedTinyInteger('position');
            $table->unsignedInteger('votes_count')->default(0);
            $table->timestamps();

            $table->unique(['poll_id', 'label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poll_options');
    }
};
