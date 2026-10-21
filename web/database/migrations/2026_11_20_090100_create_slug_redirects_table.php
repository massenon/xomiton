<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historique des adresses d'une ressource ou d'une thématique (P12-03, SEO_CONTENT_GUIDE_INSTRUCTION.md) : un
     * ancien slug redirige en 301 vers l'adresse actuelle (middleware RedirectRenamedSlug). Une ligne par ancien slug.
     */
    public function up(): void
    {
        Schema::create('slug_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 20);
            $table->unsignedBigInteger('subject_id');
            $table->string('old_slug', 120);
            $table->timestamp('created_at')->nullable();
            $table->unique(['subject_type', 'old_slug']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slug_redirects');
    }
};
