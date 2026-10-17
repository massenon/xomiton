<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fiches de référence VBG / VDH rattachées à un dossier (DATABASE_DICTIONARY.md §6 `case_forms`).
     * L'identification est pseudonymisée (json) ; l'auteur·e des faits, le récit, la note de services et
     * le·la signataire du consentement sont chiffrés (colonnes `text`, jamais indexées).
     */
    public function up(): void
    {
        Schema::create('case_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->restrictOnDelete();
            $table->string('reference', 14)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('identification');
            $table->date('facts_date');
            $table->string('facts_place', 120)->nullable();
            $table->string('facts_type', 40)->nullable();
            $table->text('facts_author')->nullable();
            $table->text('facts_description');
            $table->json('services');
            $table->text('services_note')->nullable();
            $table->foreignId('referral_contact_id')->nullable()->constrained('directory_contacts')->nullOnDelete();
            $table->string('referral_label', 120)->nullable();
            $table->date('referral_date')->nullable();
            $table->string('referral_reason', 255)->nullable();
            $table->string('referral_urgency', 10)->nullable();
            $table->boolean('consent_given');
            $table->string('consent_mode', 10);
            $table->boolean('consent_sharing')->default(false);
            $table->text('consent_signatory')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_forms');
    }
};
