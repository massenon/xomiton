<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ressources proposées par les membres (P18-12) : jamais publiques. `status` = `proposee` (file de validation),
     * `acceptee` (une ressource en brouillon a été créée dans /admin/contenu : `resource_id`) ou `refusee` (`refusal_reason`
     * écrit pour l'auteur·rice). Fichier PDF sur le disque privé (MediaUploader, collection `documents`) ou lien `https`.
     * `credit_author` : l'auteur·rice accepte d'être créditée (pseudonyme) ; l'équipe garde le dernier mot. Clé étrangère
     * vers `users` (suppression en cascade : la proposition suit le compte) ; `resource_id`, `category_id` et `reviewed_by`
     * sans clé (le contenu ou le compte de l'équipe peut disparaître). Idempotente.
     */
    public function up(): void
    {
        if (Schema::hasTable('resource_proposals')) {
            return;
        }

        Schema::create('resource_proposals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 150);
            $table->string('kind', 20);
            $table->text('summary');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('external_url', 500)->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->boolean('credit_author')->default(false);
            $table->string('status', 20)->default('proposee');
            $table->string('refusal_reason', 500)->nullable();
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_proposals');
    }
};
