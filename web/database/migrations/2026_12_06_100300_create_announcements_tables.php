<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Annonce globale (P18-13, Q97) : bannière de l'espace membre web et de l'application, **jamais** du site public ni des
     * visiteur·ses. `level` = `info` | `important`, `audience` = `membres` (tous les comptes de l'espace social) | `pairs`
     * (pair·es seulement), période `starts_at` → `ends_at` (fin facultative), `link_path` = chemin interne facultatif
     * (`/forums`…). Distincte du bandeau de maintenance (`maintenance_windows`). `announcement_dismissals` : fermeture
     * mémorisée par compte et par annonce (web et application partagent le même état). Idempotente.
     */
    public function up(): void
    {
        if (! Schema::hasTable('announcements')) {
            Schema::create('announcements', function (Blueprint $table): void {
                $table->id();
                $table->string('title', 80);
                $table->string('message', 280);
                $table->string('link_path', 200)->nullable();
                $table->string('level', 20)->default('info');
                $table->string('audience', 20)->default('membres');
                $table->timestamp('starts_at');
                $table->timestamp('ends_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->index(['starts_at', 'ends_at']);
            });
        }

        if (! Schema::hasTable('announcement_dismissals')) {
            Schema::create('announcement_dismissals', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamp('dismissed_at')->nullable();
                $table->unique(['announcement_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_dismissals');
        Schema::dropIfExists('announcements');
    }
};
