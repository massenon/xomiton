<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Publications masquées par un·e membre (« Masquer » du menu de publication, P7-02, FR-SOC-03, prototype
     * inc/render.php l.47) : retirées de son seul fil, sans effet sur les autres ni sur la modération. Un seul masquage par
     * membre et par publication ; « Annuler » supprime la ligne.
     */
    public function up(): void
    {
        Schema::create('post_hides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['post_id', 'user_id']);
            $table->index(['user_id', 'post_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_hides');
    }
};
