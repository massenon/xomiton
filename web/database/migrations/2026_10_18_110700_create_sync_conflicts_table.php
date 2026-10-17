<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Conflits de synchronisation (DATABASE_DICTIONARY.md §10 `sync_conflicts`) : modification concurrente d'un même
     * champ côté serveur et côté appareil ; l'administration choisit la version conservée.
     * Le conflit survit à la suppression de l'appareil (historique), d'où `device_id` nullable.
     */
    public function up(): void
    {
        Schema::create('sync_conflicts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->nullable()->constrained('sync_devices')->nullOnDelete();
            $table->string('subject_type', 80);
            $table->unsignedBigInteger('subject_id');
            $table->string('field', 40);
            $table->text('server_value')->nullable();
            $table->text('device_value')->nullable();
            $table->string('status', 12)->default('a_resoudre');
            $table->string('resolution', 10)->nullable();
            $table->text('resolved_value')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_conflicts');
    }
};
