<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P20-08 (lot B, PRD conversation-en-bd P0-7) : `bd_conversations.withdrawn_by` — qui a retiré une création publiée
     * (l'auteur·rice elle-même, ou la modération après un signalement). Une création retirée par la modération peut être
     * rendue visible par une décision « approuver » de `/admin/moderation` ; une création retirée par son auteur·rice ne
     * l'est jamais (elle seule la repropose). Nullable, sans contrainte (compte supprimé : la trace reste). Idempotente.
     */
    public function up(): void
    {
        if (Schema::hasTable('bd_conversations') && ! Schema::hasColumn('bd_conversations', 'withdrawn_by')) {
            Schema::table('bd_conversations', function (Blueprint $table): void {
                $table->unsignedBigInteger('withdrawn_by')->nullable()->after('withdrawn_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bd_conversations') && Schema::hasColumn('bd_conversations', 'withdrawn_by')) {
            Schema::table('bd_conversations', function (Blueprint $table): void {
                $table->dropColumn('withdrawn_by');
            });
        }
    }
};
