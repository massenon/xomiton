<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Passerelles réseaux sociaux (DATABASE_DICTIONARY.md §8 `social_channels`) : une ligne par réseau
     * (`gateways.json`), jetons d'accès chiffrés, jamais indexés. Aucune donnée de membre n'y transite.
     */
    public function up(): void
    {
        Schema::create('social_channels', function (Blueprint $table) {
            $table->id();
            $table->string('network', 20)->unique();
            $table->string('label', 40);
            $table->string('icon', 40);
            $table->string('color', 20);
            $table->boolean('supports_api');
            $table->boolean('is_connected')->default(false);
            $table->string('account_handle', 60)->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('external_id', 120)->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('last_error', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_channels');
    }
};
