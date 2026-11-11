<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sujets de forum : identifiant public ULID, forum non supprimable tant qu'il a des sujets
     * (`restrict`), auteur·rice détaché·e à la suppression du compte, épinglage et verrouillage.
     */
    public function up(): void
    {
        Schema::create('forum_topics', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('forum_id')->constrained('forums')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 200);
            $table->text('body');
            $table->boolean('is_anonymous')->default(false);
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('replies_count')->default(0);
            $table->timestamp('last_reply_at')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_locked')->default(false);
            $table->string('status', 10)->default('publie');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['forum_id', 'last_reply_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_topics');
    }
};
