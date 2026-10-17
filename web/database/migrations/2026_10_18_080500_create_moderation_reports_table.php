<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Signalements de modération (DATABASE_DICTIONARY.md §7 `moderation_reports`). La cible est typée
     * (`subject_type`, `subject_id`) avec un extrait figé au moment du signalement ; le ou la signalant·e
     * est un compte (`reporter_user_id`) ou un libellé libre (`reporter_label` : « Système (mots interdits) »).
     */
    public function up(): void
    {
        Schema::create('moderation_reports', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 15);
            $table->string('subject_type', 80);
            $table->unsignedBigInteger('subject_id');
            $table->string('excerpt', 255)->nullable();
            $table->foreignId('reported_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reporter_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reporter_label', 80)->nullable();
            $table->string('reason', 80);
            $table->string('details', 500)->nullable();
            $table->string('status', 10)->default('en_attente');
            $table->string('action', 40)->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_reports');
    }
};
