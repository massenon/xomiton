<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Conversations avec l'assistant (DATABASE_DICTIONARY.md §3 `assistant_conversations`) : d'un·e membre
     * (`user_id`) ou d'un·e visiteur·se identifié·e par `session_key` ; `is_flagged` déclenche l'escalade humaine.
     */
    public function up(): void
    {
        Schema::create('assistant_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('session_key', 64)->nullable();
            $table->boolean('is_flagged')->default(false);
            $table->string('flag_reason', 200)->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_conversations');
    }
};
