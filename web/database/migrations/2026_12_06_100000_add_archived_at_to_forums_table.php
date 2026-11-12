<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Forum archivé (P18-10) : `archived_at` renseigné → le forum reste listé et lisible (sujets et réponses visibles), mais
     * n'accepte plus de nouveau sujet. Distinct de `is_active` (forum fermé : invisible, sujets en 404). Idempotente.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('forums', 'archived_at')) {
            Schema::table('forums', function (Blueprint $table): void {
                $table->timestamp('archived_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('forums', 'archived_at')) {
            Schema::table('forums', function (Blueprint $table): void {
                $table->dropColumn('archived_at');
            });
        }
    }
};
