<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Messages d'une conversation : corps chiffré (cast `encrypted`, jamais indexé), `client_uuid`
     * pour l'idempotence des envois hors ligne, index (conversation_id, id) pour la pagination.
     */
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->string('media_path', 255)->nullable();
            $table->char('client_uuid', 36)->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('deleted_for_everyone_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'id']);
            $table->unique(['conversation_id', 'client_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
