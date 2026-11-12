<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Plusieurs images et vidéos relues avant parution (Q96) :
     *  - `post_media.mime` : type réel du fichier stocké (vidéo non ré-encodée) ;
     *  - `post_media.alt` passe à 500 caractères (description obligatoire d'une vidéo, 5 à 500) ;
     *  - `posts.reviewed_by` / `reviewed_at` : qui a décidé de la relecture et quand ;
     *  - `posts.review_reason` (code du motif de refus) et `review_note` (texte libre ≤ 300 car., écrit pour l'auteur·rice).
     * Les statuts `relecture` et `refuse` tiennent dans `posts.status` (10 caractères) : aucune modification de colonne.
     * Idempotente (hasColumn) ; aucune clé étrangère ajoutée (SQLite ne sait pas en ajouter à une table existante).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('post_media', 'mime')) {
            Schema::table('post_media', function (Blueprint $table): void {
                $table->string('mime', 100)->nullable()->after('path');
            });
        }

        Schema::table('post_media', function (Blueprint $table): void {
            $table->string('alt', 500)->nullable()->change();
        });

        Schema::table('posts', function (Blueprint $table): void {
            if (! Schema::hasColumn('posts', 'reviewed_by')) {
                $table->unsignedBigInteger('reviewed_by')->nullable();
            }

            if (! Schema::hasColumn('posts', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable();
            }

            if (! Schema::hasColumn('posts', 'review_reason')) {
                $table->string('review_reason', 30)->nullable();
            }

            if (! Schema::hasColumn('posts', 'review_note')) {
                $table->string('review_note', 300)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            foreach (['review_note', 'review_reason', 'reviewed_at', 'reviewed_by'] as $column) {
                if (Schema::hasColumn('posts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('post_media', function (Blueprint $table): void {
            $table->string('alt', 255)->nullable()->change();
        });

        if (Schema::hasColumn('post_media', 'mime')) {
            Schema::table('post_media', function (Blueprint $table): void {
                $table->dropColumn('mime');
            });
        }
    }
};
