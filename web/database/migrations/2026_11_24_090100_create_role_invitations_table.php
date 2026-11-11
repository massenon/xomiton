<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Invitations à rejoindre l'équipe (décisions client du 2026-10-29) : lien / code signé à usage unique créé par
     * l'administration ; la personne termine une inscription **membre** ordinaire (case « 15 ans » comprise) et reçoit le
     * rôle `pair` (ou `admin`, réservé au superadmin) à la validation. Le code n'est jamais conservé en clair
     * (`code_hash`, SHA-256) ; l'adresse e-mail d'invitation, facultative, est liée à l'inscription.
     * `status` : pending (envoyée) · redeemed (inscription faite, rôle en attente de validation) · accepted (rôle donné) ·
     * refused (refus motivé) · revoked (annulée) · expired.
     */
    public function up(): void
    {
        Schema::create('role_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('role', 20);
            $table->string('code_hash', 64)->unique();
            $table->string('code_hint', 8);
            $table->string('email', 190)->nullable();
            $table->string('note', 190)->nullable();
            $table->string('status', 12)->default('pending');
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision_reason', 200)->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at'], 'role_inv_status_exp_idx');
            $table->index('role', 'role_inv_role_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_invitations');
    }
};
