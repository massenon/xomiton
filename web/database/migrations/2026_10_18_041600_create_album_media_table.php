<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Médias d'un album (image ou vidéo), ordonnés par `position` et supprimés avec l'album.
     */
    public function up(): void
    {
        Schema::create('album_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('album_id')->constrained('albums')->cascadeOnDelete();
            $table->string('path', 255);
            $table->string('kind', 10)->default('image');
            $table->string('alt', 255)->nullable();
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamps();

            $table->index(['album_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('album_media');
    }
};
