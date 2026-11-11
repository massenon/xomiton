<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Profil 1–1 avec users ; gender_identity, orientation et trusted_contact_phone sont chiffrés
        // par cast Eloquent (colonnes text, jamais indexées).
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('pronouns', 30)->nullable();
            $table->string('age_range', 10)->nullable();
            $table->string('member_type', 10)->default('membre');
            $table->text('gender_identity')->nullable();
            $table->text('orientation')->nullable();
            $table->string('bio', 500)->nullable();
            $table->unsignedTinyInteger('completion')->default(0);
            $table->string('avatar_path', 255)->nullable();
            $table->string('cover_path', 255)->nullable();
            $table->boolean('discreet_mode')->default(false);
            $table->text('trusted_contact_phone')->nullable();
            $table->string('trusted_contact_name', 80)->nullable();
            $table->string('website', 190)->nullable();
            $table->string('visibility', 10)->default('community');
            $table->boolean('show_zone')->default(true);
            $table->boolean('searchable')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
