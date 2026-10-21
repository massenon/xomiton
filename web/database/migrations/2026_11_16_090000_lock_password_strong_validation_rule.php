<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * P11-05 : la règle `password_strong` est verrouillée comme `required` (toujours active, non désactivable).
     * Les bases déjà semées la rattrapent ici ; le seeder du catalogue pose le même verrou sur une base neuve.
     */
    public function up(): void
    {
        DB::table('validation_rules')->where('key', 'password_strong')->update(['is_locked' => true, 'is_active' => true]);
    }

    public function down(): void
    {
        DB::table('validation_rules')->where('key', 'password_strong')->update(['is_locked' => false]);
    }
};
