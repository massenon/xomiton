<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Planification de la génération automatique des rapports (DATABASE_DICTIONARY.md §7 `report_schedules`,
     * `rapport_plans` du prototype) : fréquence, jour d'envoi (1–28), formats, destinataires, désagrégations.
     */
    public function up(): void
    {
        Schema::create('report_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20);
            $table->string('frequency', 15);
            $table->unsignedTinyInteger('day_of_period')->default(1);
            $table->json('formats');
            $table->json('recipients');
            $table->json('dimensions');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_schedules');
    }
};
