<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Demandes du rôle pair faites par un membre depuis son profil (D-MOB-15, tranché 2026-10-29). Une seule demande en
     * cours par compte : `open_user_id` vaut `user_id` tant que la demande est en attente et NULL ensuite (index unique,
     * donc sans doublon même en cas de requêtes simultanées). Le motif de la personne (facultatif, court) et celui de la
     * décision (écrit pour elle) sont limités à 200 caractères. Jamais d'attribution automatique : le rôle n'est donné
     * que par une décision d'un compte habilité (`peers.assign`).
     * `status` : pending · approved · rejected · cancelled.
     */
    public function up(): void
    {
        Schema::create('role_requests', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('open_user_id')->nullable()->unique();
            $table->string('role', 20)->default('pair');
            $table->string('status', 12)->default('pending');
            $table->string('reason', 200)->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_reason', 200)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'role_req_status_idx');
            $table->index(['user_id', 'status'], 'role_req_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_requests');
    }
};
