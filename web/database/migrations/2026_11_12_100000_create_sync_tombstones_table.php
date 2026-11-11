<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pierres tombales de `GET sync/pull` (P9-06, DATABASE_DICTIONARY.md §10 `sync_tombstones`) : un enregistrement
     * synchronisé supprimé pour de bon (suppression définitive) ou sorti de la portée d'un compte (dossier réattribué,
     * alerte routée ailleurs) ; l'appareil le retire au tirage suivant. Une suppression logique (`deleted_at`) ou une
     * sortie de portée visible sur la ligne (statut, visibilité) n'en écrit pas : le tirage la lit sur la ligne.
     * `user_id` null = pour tout le monde ; sinon pour ce compte seulement. Purgées par `sync:prune`.
     */
    public function up(): void
    {
        Schema::create('sync_tombstones', function (Blueprint $table) {
            $table->id();
            $table->string('collection', 40);
            $table->string('subject_key', 40);
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['collection', 'created_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_tombstones');
    }
};
