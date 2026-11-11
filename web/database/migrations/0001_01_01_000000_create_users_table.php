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
        // Colonnes de DATABASE_DICTIONARY.md §2 « users ». Cette migration s'exécute avant les référentiels
        // (lot 1, 2026_10_18_01xx) : les contraintes de zone_id / commune_id sont ajoutées par
        // 2026_10_18_020000_add_foreign_keys_to_users_table.php, une fois zones et communes créées.
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('pseudo', 40)->unique();
            $table->string('name', 120)->nullable();
            $table->string('email', 190)->unique();
            $table->string('phone', 30)->nullable();
            $table->string('password');
            $table->string('status', 20)->default('actif');
            $table->foreignId('zone_id')->nullable()->index();
            $table->foreignId('commune_id')->nullable()->index();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->string('suspension_reason', 200)->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
