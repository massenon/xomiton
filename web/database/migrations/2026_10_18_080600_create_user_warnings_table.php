<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Avertissements adressés à un·e membre (DATABASE_DICTIONARY.md §7 `user_warnings`) : journal immuable,
     * rattaché au signalement d'origine quand il existe ; disparaît avec le compte (cascade).
     */
    public function up(): void
    {
        Schema::create('user_warnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 200);
            $table->foreignId('moderation_report_id')->nullable()->constrained('moderation_reports')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_warnings');
    }
};
