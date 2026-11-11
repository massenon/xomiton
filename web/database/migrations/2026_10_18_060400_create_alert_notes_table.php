<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Notes internes sur une alerte (DATABASE_DICTIONARY.md §5 `alert_notes`) : plusieurs notes
     * traçables par auteur·rice plutôt qu'une colonne ; corps chiffré, jamais mis à jour.
     */
    public function up(): void
    {
        Schema::create('alert_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alert_id')->constrained('alerts')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_notes');
    }
};
