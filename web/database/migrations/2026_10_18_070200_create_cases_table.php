<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dossiers VBG / VDH collectés par les pair·es (DATABASE_DICTIONARY.md §6 `cases`).
     * Pas de suppression logique : un dossier se clôt puis s'archive (`status`), et l'historique n'est jamais
     * supprimé par cascade (`restrict` sur le département et la collectrice). Les notes sont chiffrées.
     * `occurred_on <= aujourd'hui` (RG-07) est contrôlé côté application (CHECK non déterministe impossible).
     */
    public function up(): void
    {
        Schema::create('cases', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('kind', 3);
            $table->date('occurred_on');
            $table->foreignId('zone_id')->constrained('zones')->restrictOnDelete();
            $table->foreignId('commune_id')->nullable()->constrained('communes')->nullOnDelete();
            $table->foreignId('population_id')->nullable()->constrained('populations')->nullOnDelete();
            $table->string('sex', 10)->nullable();
            $table->string('age_range', 10)->nullable();
            $table->foreignId('violence_type_id')->nullable()->constrained('violence_types')->nullOnDelete();
            $table->string('status', 10)->default('ouvert');
            $table->foreignId('collected_by')->constrained('users')->restrictOnDelete();
            $table->string('referral', 80)->nullable();
            $table->text('notes')->nullable();
            $table->string('source', 15)->default('web');
            $table->char('client_uuid', 36)->nullable()->unique();
            $table->foreignId('device_id')->nullable()->constrained('sync_devices')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('anonymized_at')->nullable();
            $table->timestamps();

            $table->index(['zone_id', 'occurred_on']);
            $table->index(['collected_by', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cases');
    }
};
