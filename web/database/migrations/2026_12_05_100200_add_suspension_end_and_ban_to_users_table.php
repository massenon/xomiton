<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Suspension temporaire et bannissement (P18-06, Q97) :
     *  - `suspended_until` : fin d'une suspension temporaire (null = jusqu'à réactivation) ; la commande planifiée
     *    `accounts:lift-suspensions` réactive les comptes dont la date est passée ;
     *  - `banned_at`, `banned_by`, `ban_reason` (≤ 200, écrit pour la personne) : bannissement, nouveau statut définitif
     *    `banni` dans `users.status` (20 caractères : aucune modification de colonne), levé seulement par un·e superadmin.
     * Colonnes nullables sans clé étrangère (SQLite) ; index sur `suspended_until` pour la commande. Idempotente.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'suspended_until')) {
                $table->timestamp('suspended_until')->nullable()->index();
            }

            if (! Schema::hasColumn('users', 'banned_at')) {
                $table->timestamp('banned_at')->nullable();
            }

            if (! Schema::hasColumn('users', 'banned_by')) {
                $table->unsignedBigInteger('banned_by')->nullable();
            }

            if (! Schema::hasColumn('users', 'ban_reason')) {
                $table->string('ban_reason', 200)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'suspended_until')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropIndex(['suspended_until']);
            });
        }

        Schema::table('users', function (Blueprint $table): void {
            foreach (['ban_reason', 'banned_by', 'banned_at', 'suspended_until'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
