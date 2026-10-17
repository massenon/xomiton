<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Publications du fil (DATABASE_DICTIONARY.md §4 `posts`). L'auteur·rice est facultatif·ve
     * (publication anonyme ou compte supprimé) ; une publication peut appartenir à un groupe ou une page.
     */
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->foreignId('page_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->boolean('is_anonymous')->default(false);
            $table->text('body');
            $table->string('tag', 20);
            $table->string('visibility', 10)->default('public');
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            $table->unsignedSmallInteger('reports_count')->default(0);
            $table->string('status', 10)->default('publie');
            $table->string('hidden_reason', 200)->nullable();
            $table->timestamp('published_at')->useCurrent();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'status']);
            $table->index(['tag', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
