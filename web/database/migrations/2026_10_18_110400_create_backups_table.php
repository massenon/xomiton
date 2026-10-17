<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sauvegardes de la base (DATABASE_DICTIONARY.md §10 `backups`) : inventaire des tables, emplacement du fichier
     * sur son disque, somme de contrôle et trace de restauration. Le fichier lui-même reste hors base.
     * `tables` est toujours renseigné (`[]` pour une sauvegarde en échec sans inventaire).
     */
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40)->unique();
            $table->string('kind', 20);
            $table->unsignedBigInteger('size_bytes');
            $table->json('tables');
            $table->string('storage_disk', 20);
            $table->string('storage_path', 255);
            $table->char('checksum', 64)->nullable();
            $table->string('status', 10)->default('done');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('restored_at')->nullable();
            $table->foreignId('restored_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};
