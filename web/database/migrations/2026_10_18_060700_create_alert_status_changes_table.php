<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historique des statuts d'une alerte (DATABASE_DICTIONARY.md §5 `alert_status_changes`),
     * affiché par le suivi par code de la personne qui a signalé ; journal immuable.
     */
    public function up(): void
    {
        Schema::create('alert_status_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alert_id')->constrained('alerts')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 15)->nullable();
            $table->string('to_status', 15);
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_status_changes');
    }
};
