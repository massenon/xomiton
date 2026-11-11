<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Annuaire des contacts d'orientation (DATABASE_DICTIONARY.md §5 `directory_contacts`).
     * L'index (role, zone_id, is_active) sert au routage des alertes (RG-04).
     */
    public function up(): void
    {
        Schema::create('directory_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('role', 40);
            $table->string('org', 120)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('whatsapp', 30)->nullable();
            $table->foreignId('zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->foreignId('commune_id')->nullable()->constrained('communes')->nullOnDelete();
            $table->string('availability', 120)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(true);
            $table->boolean('is_national_focal')->default(false);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['role', 'zone_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('directory_contacts');
    }
};
