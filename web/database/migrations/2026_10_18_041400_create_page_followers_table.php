<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Abonnements aux pages : table de liaison, un seul par (page, membre).
     */
    public function up(): void
    {
        Schema::create('page_followers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('pages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['page_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_followers');
    }
};
