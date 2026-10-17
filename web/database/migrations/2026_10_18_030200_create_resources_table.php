<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ressources documentaires et actualités (DATABASE_DICTIONARY.md §3 `resources`) : fiche, guide,
     * vidéo, audio, PDF, article ou lien, éventuellement liées à une thématique.
     */
    public function up(): void
    {
        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('thematique_id')->nullable()->constrained('thematiques')->nullOnDelete();
            $table->string('slug', 120)->unique();
            $table->string('title', 200);
            $table->string('kind', 20);
            $table->string('summary', 500);
            $table->longText('body')->nullable();
            $table->string('file_path', 255)->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->string('external_url', 500)->nullable();
            $table->string('image_path', 255)->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->string('status', 10)->default('publie');
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('downloads_count')->default(0);
            $table->unsignedInteger('views_count')->default(0);
            $table->boolean('is_offline_available')->default(false);
            $table->string('meta_title', 70)->nullable();
            $table->string('meta_description', 160)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index('kind');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resources');
    }
};
