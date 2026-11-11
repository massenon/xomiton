<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Preuves jointes à un signalement (P5-04 : image · PDF · audio, stockage privé hors racine web, métadonnées retirées
 * par MediaUploader, lien signé pour l'administration). Aucun nom de fichier d'origine (il peut identifier la personne),
 * aucun `updated_at` ; suppression par l'anonymisation des alertes seulement (`restrict`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('alert_id')->constrained('alerts')->restrictOnDelete();
            $table->string('kind', 10);
            $table->string('disk', 20);
            $table->string('path', 255);
            $table->string('mime', 80);
            $table->unsignedInteger('size');
            $table->timestamp('created_at')->nullable();

            $table->index(['alert_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_attachments');
    }
};
