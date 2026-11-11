<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Messages d'une conversation avec l'assistant (DATABASE_DICTIONARY.md §3 `assistant_messages`) :
     * corps chiffré (cast `encrypted`), supprimés avec la conversation ; `created_at` seul.
     */
    public function up(): void
    {
        Schema::create('assistant_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('assistant_conversations')->cascadeOnDelete();
            $table->string('role', 10);
            $table->text('body');
            $table->foreignId('matched_kb_id')->nullable()->constrained('assistant_kb')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_messages');
    }
};
