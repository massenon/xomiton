<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Versions de la plateforme (DATABASE_DICTIONARY.md §10 `app_versions`) : journal des mises à jour
     * déployées et planifiées de l'écran « Mises à jour & optimisation ».
     */
    public function up(): void
    {
        Schema::create('app_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version', 20)->unique();
            $table->string('kind', 20);
            $table->text('notes')->nullable();
            $table->string('status', 12);
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('deployed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_versions');
    }
};
