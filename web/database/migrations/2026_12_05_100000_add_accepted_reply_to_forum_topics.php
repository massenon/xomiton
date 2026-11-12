<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Réponse validée d'un sujet de forum (P18-03, Q97) : `accepted_reply_id` (réponse du même sujet), `accepted_by` (compte
     * qui a validé : l'auteur·rice du sujet ou la modération) et `accepted_at`. Un sujet est « résolu » quand
     * `accepted_reply_id` est renseigné. Colonnes nullables sans clé étrangère (SQLite ne sait pas en ajouter à une table
     * existante) ; la suppression d'une réponse vide le champ (RemoveForumContent). Idempotente (hasColumn).
     */
    public function up(): void
    {
        Schema::table('forum_topics', function (Blueprint $table): void {
            if (! Schema::hasColumn('forum_topics', 'accepted_reply_id')) {
                $table->unsignedBigInteger('accepted_reply_id')->nullable()->index();
            }

            if (! Schema::hasColumn('forum_topics', 'accepted_by')) {
                $table->unsignedBigInteger('accepted_by')->nullable();
            }

            if (! Schema::hasColumn('forum_topics', 'accepted_at')) {
                $table->timestamp('accepted_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('forum_topics', 'accepted_reply_id')) {
            Schema::table('forum_topics', function (Blueprint $table): void {
                $table->dropIndex(['accepted_reply_id']);
            });
        }

        Schema::table('forum_topics', function (Blueprint $table): void {
            foreach (['accepted_at', 'accepted_by', 'accepted_reply_id'] as $column) {
                if (Schema::hasColumn('forum_topics', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
