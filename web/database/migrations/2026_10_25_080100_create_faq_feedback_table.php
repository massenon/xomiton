<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Réponses à « Cet article vous a-t-il aidé·e ? » (P4-05, help-details.php l.37-38 : toast seul dans le prototype ;
     * décision du plan §7 : persister, compteur « utile » de /admin/contenu onglet FAQ en P12-03). Une ligne par question
     * et par votant·e : `fingerprint` = KeyedHash de la question et d'un jeton aléatoire de session (ni IP, ni compte,
     * ni identifiant de session) ; un nouveau vote du même jeton remplace le précédent. DATABASE_DICTIONARY.md §3.
     */
    public function up(): void
    {
        Schema::create('faq_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faq_id')->constrained('faqs')->cascadeOnDelete();
            $table->boolean('helpful');
            $table->char('fingerprint', 64);
            $table->timestamp('created_at')->nullable();
            $table->unique(['faq_id', 'fingerprint']);
            $table->index(['faq_id', 'helpful']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faq_feedback');
    }
};
