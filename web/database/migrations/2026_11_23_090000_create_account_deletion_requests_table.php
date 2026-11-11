<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Demandes publiques de suppression de compte (page /supprimer-mon-compte, exigence Google Play : lien web sans
     * connexion). Une ligne par demande ; l'adresse n'est jamais conservée (empreinte HMAC), le jeton non plus (SHA-256).
     * `outcome` : sent (lien envoyé), no_account (aucun compte : rien n'est envoyé), blocked (compte de l'équipe),
     * done (suppression lancée), expired. Purgée après `xomiton.accounts.deletion_request_retention_days`.
     */
    public function up(): void
    {
        Schema::create('account_deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->string('email_hash', 64)->index();
            $table->string('token_hash', 64)->nullable()->unique();
            $table->string('outcome', 20)->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_deletion_requests');
    }
};
