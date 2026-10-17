<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fenêtres de maintenance planifiées (DATABASE_DICTIONARY.md §10 `maintenance_windows`) : pendant une fenêtre
     * active, seule l'administration peut se connecter et le message est affiché aux autres.
     */
    public function up(): void
    {
        Schema::create('maintenance_windows', function (Blueprint $table) {
            $table->id();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('message', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_windows');
    }
};
