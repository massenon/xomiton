<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fiches thématiques publiques (DATABASE_DICTIONARY.md §3 `thematiques`) : identifiées par slug,
     * rattachées à une catégorie `thematique` (lot 1) que l'on ne peut pas supprimer tant qu'elle est utilisée.
     */
    public function up(): void
    {
        Schema::create('thematiques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->string('slug', 120)->unique();
            $table->string('title', 200);
            $table->string('summary', 500);
            $table->longText('body')->nullable();
            $table->json('sections')->nullable();
            $table->string('content_warning', 255)->nullable();
            $table->string('image_path', 255)->nullable();
            $table->string('cover_path', 255)->nullable();
            $table->unsignedTinyInteger('reading_minutes')->default(3);
            $table->json('related_contact_roles')->nullable();
            $table->json('related_ids')->nullable();
            $table->string('status', 10)->default('publie');
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('views_count')->default(0);
            $table->string('meta_title', 70)->nullable();
            $table->string('meta_description', 160)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thematiques');
    }
};
