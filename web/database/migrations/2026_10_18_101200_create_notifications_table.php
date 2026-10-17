<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Notifications en base (migration Laravel `make:notifications-table`, DATABASE_DICTIONARY.md §9
     * `notifications`). Colonnes ajoutées : `category` (préférences par catégorie de `user_settings`)
     * et `is_sensitive` (masquage dans la cloche et sur l'écran de verrouillage).
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->string('category', 30)->nullable()->index();
            $table->boolean('is_sensitive')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
