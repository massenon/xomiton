<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Boîte à suggestions (P18-14) : `category` = `fonctionnalite` | `contenu` | `securite` | `autre`, `message` ≤ 1000
     * caractères, `status` = `nouvelle` | `en_cours` | `retenue` | `ecartee`. `user_id` reste renseigné pour le limiteur et
     * la réponse de l'équipe même quand la suggestion est **anonyme** (`is_anonymous`) : l'écran d'administration n'affiche
     * alors jamais le compte. `reply` : réponse de l'équipe envoyée en notification. Idempotente.
     */
    public function up(): void
    {
        if (Schema::hasTable('suggestions')) {
            return;
        }

        Schema::create('suggestions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category', 20);
            $table->text('message');
            $table->boolean('is_anonymous')->default(false);
            $table->string('status', 20)->default('nouvelle');
            $table->text('reply')->nullable();
            $table->unsignedBigInteger('replied_by')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suggestions');
    }
};
