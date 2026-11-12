<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Badges vérifiés par l'administration (P18-09, Q97) : `badge` = `membre_verifie` (« Membre vérifié ») ou
     * `pair_animateur` (« Pair animateur·rice », pair·e approuvé·e), attribués **à la main** (aucune règle automatique),
     * retirables, journalisés (`badges.granted`, `badges.revoked`). Un badge au plus de chaque sorte par compte. Jamais de
     * classement. Clé étrangère vers `users` (suppression en cascade : le badge suit le compte) ; `granted_by` sans clé
     * (le compte de l'équipe peut disparaître, le badge reste).
     */
    public function up(): void
    {
        if (Schema::hasTable('user_badges')) {
            return;
        }

        Schema::create('user_badges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('badge', 30);
            $table->unsignedBigInteger('granted_by')->nullable();
            $table->timestamp('granted_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'badge']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_badges');
    }
};
