<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modèles d'e-mails transactionnels éditables en administration (DATABASE_DICTIONARY.md §9
     * `email_templates`, prototype `email_templates.json`). Le slug est immuable quand `is_system` (RG-10).
     */
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 60)->unique();
            $table->string('category', 20);
            $table->boolean('is_system')->default(false);
            $table->string('subject', 150);
            $table->string('preheader', 150)->nullable();
            $table->longText('body');
            $table->json('variables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
