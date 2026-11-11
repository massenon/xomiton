<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Albums de la médiathèque d'un profil (DATABASE_DICTIONARY.md §4 `albums` : albums.php, onglet Médias).
     * `kind` distingue les albums système (photos de profil, couvertures) des albums personnalisés ;
     * `cover_media_id` n'est pas une FK (référence circulaire vers album_media), la cohérence est applicative.
     */
    public function up(): void
    {
        Schema::create('albums', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind', 10)->default('custom');
            $table->string('title', 120);
            $table->string('description', 255)->nullable();
            $table->string('visibility', 10)->default('community');
            $table->unsignedInteger('media_count')->default(0);
            $table->unsignedBigInteger('cover_media_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('albums');
    }
};
