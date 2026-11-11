<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P7-02 (revue fil-domaine-api) : `posts.edited_at`, posé par App\Application\Feed\ManagePost::update seulement, pour la
 * mention « modifiée ». `updated_at` bouge aussi avec les compteurs, la modération et le masquage : il ne dit rien d'une
 * modification du texte par l'auteur·rice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->timestamp('edited_at')->nullable()->after('published_at');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('edited_at');
        });
    }
};
