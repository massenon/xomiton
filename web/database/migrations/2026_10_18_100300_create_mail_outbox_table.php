<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * E-mails en attente quand aucun SMTP n'est configuré (DATABASE_DICTIONARY.md §9 `mail_outbox`) :
     * contenu complet conservé, envoyé plus tard par `mail:flush-outbox`.
     */
    public function up(): void
    {
        Schema::create('mail_outbox', function (Blueprint $table) {
            $table->id();
            $table->string('to', 190);
            $table->string('from_address', 190);
            $table->string('from_name', 80)->nullable();
            $table->string('reply_to', 190)->nullable();
            $table->string('subject', 160);
            $table->longText('html');
            $table->longText('text')->nullable();
            $table->string('template_slug', 60)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->string('last_error', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_outbox');
    }
};
