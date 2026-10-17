<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Étapes de suivi d'un dossier (DATABASE_DICTIONARY.md §6 `case_followups`) : accueil, écoute, appui
     * juridique, suivi à 30 jours… Le commentaire est chiffré ; `client_uuid` sert d'idempotence à la
     * synchronisation hors connexion.
     */
    public function up(): void
    {
        Schema::create('case_followups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->restrictOnDelete();
            $table->date('date');
            $table->string('step', 80);
            $table->foreignId('by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('outcome', 10);
            $table->date('next_on')->nullable();
            $table->text('comment')->nullable();
            $table->char('client_uuid', 36)->nullable()->unique();
            $table->timestamps();

            $table->index(['case_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_followups');
    }
};
