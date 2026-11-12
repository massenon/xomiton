<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P20-03 « Conversations en BD » (PRD conversation-en-bd, GRAMMAIRE_DES_BULLES.md, Q99) : des cases illustrées SANS
     * texte sur lesquelles la plateforme dessine des bulles SVG au texte réel. Préfixe `bd_` : la table `conversations`
     * appartient déjà à la messagerie.
     *
     *  - `bd_characters` : casting (personnages de la bible des BD repris, 4 ajoutés, personnages secondaires) ; clé stable
     *    lue par les bulles (`bd_bubbles.character_key`), nom affiché et lu (« Fifamè pense : … »), pronom ;
     *  - `bd_scenes` : bibliothèque de scènes illustrées validées (image sans texte, description = texte alternatif,
     *    personnages présents, zone des bulles, actif / inactif) ; sert aux modèles et, au lot B, à l'éditeur des membres ;
     *  - `bd_conversations` : statut `brouillon|proposee|publiee|refusee|retiree`, origine `modele|membre`, auteur·rice
     *    nullable (compte supprimé : la création reste, anonyme), `is_anonymous` vrai par défaut (Q99), motif de refus,
     *    questions d'animation, dates du cycle (proposition, décision, publication, retrait) ; `ulid` pour les adresses
     *    privées des brouillons des membres (lot B) ; `content_edited_at` / `status_changed_at` : marques posées par
     *    l'administration, lues par l'import (jamais de réalignement d'un contenu ou d'un statut retouché) ;
     *  - `bd_panels` : cases ordonnées (référence à une scène, description de scène propre à la case pour le texte
     *    alternatif) ;
     *  - `bd_bubbles` : type parmi les 9 de la grammaire, personnage, texte, emphase, position x / y en %, queue
     *    {vers, x, y}, chaîne de bulles associées (`linked_to` = rang de la bulle précédente dans la case), superposée,
     *    masquée (insulte jamais écrite : bulle grisée sans texte), ordre de lecture.
     *
     * Pas d'enum SQL (SQLite) : chaînes contrôlées par les enums PHP. Idempotente (hasTable).
     */
    public function up(): void
    {
        if (! Schema::hasTable('bd_characters')) {
            Schema::create('bd_characters', function (Blueprint $table): void {
                $table->id();
                $table->string('key', 40)->unique();
                $table->string('name', 80);
                $table->string('pronoun', 20)->nullable();
                $table->string('role', 200)->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_secondary')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('position')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('bd_scenes')) {
            Schema::create('bd_scenes', function (Blueprint $table): void {
                $table->id();
                $table->string('key', 60)->unique();
                $table->string('title', 160)->nullable();
                $table->string('image_path')->nullable();
                $table->text('description');
                $table->json('characters')->nullable();
                $table->string('bubble_zone', 120)->nullable();
                $table->string('origin', 20)->default('modele');
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('position')->default(0);
                $table->timestamp('content_edited_at')->nullable();
                $table->timestamps();
                $table->index(['is_active', 'position']);
            });
        }

        if (! Schema::hasTable('bd_conversations')) {
            Schema::create('bd_conversations', function (Blueprint $table): void {
                $table->id();
                $table->ulid('ulid')->unique();
                $table->string('slug', 120)->unique();
                $table->string('model_key', 20)->nullable()->unique();
                $table->string('origin', 20)->default('modele');
                $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
                $table->boolean('is_anonymous')->default(true);
                $table->string('title', 160);
                $table->text('summary')->nullable();
                $table->string('theme', 200)->nullable();
                $table->string('category', 120)->nullable();
                $table->string('audience', 200)->nullable();
                $table->text('key_message')->nullable();
                $table->text('content_warning')->nullable();
                $table->json('discussion_questions')->nullable();
                $table->string('status', 20)->default('brouillon');
                $table->text('refusal_reason')->nullable();
                $table->unsignedSmallInteger('position')->default(0);
                $table->string('meta_title', 160)->nullable();
                $table->string('meta_description', 300)->nullable();
                $table->string('og_image_path')->nullable();
                $table->unsignedBigInteger('thematique_id')->nullable()->index();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamp('withdrawn_at')->nullable();
                $table->timestamp('content_edited_at')->nullable();
                $table->timestamp('status_changed_at')->nullable();
                $table->timestamps();
                $table->index(['status', 'position']);
                $table->index(['origin', 'status']);
            });
        }

        if (! Schema::hasTable('bd_panels')) {
            Schema::create('bd_panels', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('conversation_id')->constrained('bd_conversations')->cascadeOnDelete();
                $table->foreignId('scene_id')->nullable()->constrained('bd_scenes')->nullOnDelete();
                $table->unsignedTinyInteger('position')->default(1);
                $table->text('scene_description')->nullable();
                $table->timestamps();
                $table->index(['conversation_id', 'position']);
            });
        }

        if (! Schema::hasTable('bd_bubbles')) {
            Schema::create('bd_bubbles', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('panel_id')->constrained('bd_panels')->cascadeOnDelete();
                $table->unsignedTinyInteger('position')->default(1);
                $table->string('type', 20);
                $table->string('character_key', 40)->nullable();
                $table->text('text')->nullable();
                $table->json('emphasis')->nullable();
                $table->decimal('x', 5, 2)->default(0);
                $table->decimal('y', 5, 2)->default(0);
                $table->string('tail_target', 20)->nullable();
                $table->decimal('tail_x', 5, 2)->nullable();
                $table->decimal('tail_y', 5, 2)->nullable();
                $table->unsignedTinyInteger('linked_to')->nullable();
                $table->boolean('is_overlapping')->default(false);
                $table->boolean('is_masked')->default(false);
                $table->timestamps();
                $table->index(['panel_id', 'position']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bd_bubbles');
        Schema::dropIfExists('bd_panels');
        Schema::dropIfExists('bd_conversations');
        Schema::dropIfExists('bd_scenes');
        Schema::dropIfExists('bd_characters');
    }
};
