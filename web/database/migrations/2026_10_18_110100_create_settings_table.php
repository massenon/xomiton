<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Paramètres de la plateforme (DATABASE_DICTIONARY.md §10 `settings`) : une ligne par clé, valeur JSON typée.
     * Les clés de type `secret` (mot de passe SMTP…) sont chiffrées par le modèle avant stockage.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('group', 30);
            $table->string('type', 10);
            $table->json('value')->nullable();
            $table->boolean('is_public')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
