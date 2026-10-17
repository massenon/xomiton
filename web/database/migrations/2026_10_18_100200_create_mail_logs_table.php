<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Journal des envois d'e-mails (DATABASE_DICTIONARY.md §9 `mail_logs`) : une ligne par tentative,
     * jamais mise à jour, purgée après 180 jours. Le template est référencé par slug (il peut disparaître).
     */
    public function up(): void
    {
        Schema::create('mail_logs', function (Blueprint $table) {
            $table->id();
            $table->string('to', 190);
            $table->string('subject', 160);
            $table->string('template_slug', 60)->nullable();
            $table->string('context', 40)->nullable();
            $table->string('transport', 10);
            $table->boolean('ok');
            $table->string('error', 255)->nullable();
            $table->string('message_id', 190)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('created_at');
            $table->index(['template_slug', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_logs');
    }
};
