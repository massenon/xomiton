<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Fiche d'annuaire qui a accusé réception par lien signé (P5-02, Q50) : `acknowledged_by` ne désigne qu'un compte.
 * Permet de redonner les coordonnées de la personne à cette seule fiche si elle revient sur la page après l'accusé.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alerts', function (Blueprint $table): void {
            $table->foreignId('acknowledged_by_contact_id')->nullable()->after('acknowledged_by')
                ->constrained('directory_contacts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('acknowledged_by_contact_id');
        });
    }
};
