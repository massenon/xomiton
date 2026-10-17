<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Abonné·es à la newsletter (DATABASE_DICTIONARY.md §9 `newsletter_subscribers`). Les colonnes json
     * `tags` / `interests` servent à l'affichage ; la segmentation passe par les tables de liaison (ADR-20).
     * Le `token` (32 hex) signe les liens de confirmation et de désinscription.
     */
    public function up(): void
    {
        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email', 190)->unique();
            $table->string('name', 80)->nullable();
            $table->string('status', 12)->default('en_attente');
            $table->json('tags')->nullable();
            $table->json('interests')->nullable();
            $table->string('source', 10)->default('footer');
            $table->char('token', 32)->unique();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('confirmation_sent_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->string('unsubscribe_reason', 200)->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->string('bounce_reason', 200)->nullable();
            $table->string('consent_meta', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_opened_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');
    }
};
