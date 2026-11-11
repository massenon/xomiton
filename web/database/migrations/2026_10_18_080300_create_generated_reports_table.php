<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rapports générés (DATABASE_DICTIONARY.md §7 `generated_reports`) : historique de l'écran
     * Administration › Rapports (`rapports_generes.json`), fichier produit par un job, puis expiré.
     */
    public function up(): void
    {
        Schema::create('generated_reports', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->string('kind', 20);
            $table->date('period_start');
            $table->date('period_end');
            $table->json('dimensions');
            $table->string('format', 10);
            $table->json('filters')->nullable();
            $table->unsignedInteger('rows_count')->nullable();
            $table->string('file_path', 255)->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->string('status', 10)->default('pending');
            $table->string('error', 255)->nullable();
            $table->json('recipients')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_reports');
    }
};
