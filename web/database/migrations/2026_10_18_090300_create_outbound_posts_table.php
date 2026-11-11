<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Publications sortantes planifiées vers les réseaux (DATABASE_DICTIONARY.md §8 `outbound_posts`,
     * prototype `gateway_posts`). La cible par réseau est dans `outbound_deliveries`.
     */
    public function up(): void
    {
        Schema::create('outbound_posts', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 10);
            $table->string('reference', 120)->nullable();
            $table->string('message', 500);
            $table->string('media_path', 255)->nullable();
            $table->timestamp('scheduled_at');
            $table->string('status', 10)->default('planifie');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_posts');
    }
};
