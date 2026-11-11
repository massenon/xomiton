<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Version simple d'un texte de loi (P4-04, interrupteur « Lire en version simple » de /lois ; prototype
     * lois-benin.php l.8-16, tableau `$simple` écrit dans la page) : colonne facultative, la page retombe sur `summary`
     * quand elle est vide (comportement du prototype). DATABASE_DICTIONARY.md §3 `laws` corrigé.
     */
    public function up(): void
    {
        Schema::table('laws', function (Blueprint $table) {
            $table->text('simple_summary')->nullable()->after('summary');
        });
    }

    public function down(): void
    {
        Schema::table('laws', function (Blueprint $table) {
            $table->dropColumn('simple_summary');
        });
    }
};
