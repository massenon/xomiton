<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Événements de la communauté (DATABASE_DICTIONARY.md §4 `events`) : identifiant public ULID,
     * organisateur·rice membre ou libellé libre, rattachement facultatif à un groupe ou une page,
     * compteur `going_count` dénormalisé recalculé par les actions.
     */
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('title', 200);
            $table->string('slug', 220)->unique();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->string('place', 200)->nullable();
            $table->boolean('is_online')->default(false);
            $table->string('online_url', 500)->nullable();
            $table->text('description')->nullable();
            $table->string('image_path', 255)->nullable();
            $table->foreignId('organizer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('organizer_label', 120)->nullable();
            $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->foreignId('page_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->unsignedInteger('going_count')->default(0);
            $table->string('status', 10)->default('publie');
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['starts_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
