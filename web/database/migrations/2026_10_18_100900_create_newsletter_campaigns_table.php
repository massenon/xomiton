<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Campagnes newsletter (DATABASE_DICTIONARY.md §9 `newsletter_campaigns`, prototype
     * `newsletter_campaigns.json`). Le segment `{tags[], interests[], since, until}` est résolu à l'envoi
     * en lignes de `newsletter_campaign_recipients` ; suppression logique.
     */
    public function up(): void
    {
        Schema::create('newsletter_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('subject', 150);
            $table->string('from_name', 60);
            $table->string('reply_to', 190);
            $table->string('preheader', 150)->nullable();
            $table->longText('body');
            $table->json('segment');
            $table->string('status', 12)->default('brouillon');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->string('test_sent_to', 190)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_campaigns');
    }
};
