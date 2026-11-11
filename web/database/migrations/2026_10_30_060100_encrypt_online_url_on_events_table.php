<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P8-04 (revue du lot evenements, Q77) : le lien de connexion d'un événement en ligne (souvent avec un code d'accès)
     * est réservé aux participant·es confirmé·es **comme l'adresse exacte** : il est donc chiffré au repos de la même
     * façon (cast `encrypted`, `xomiton.encrypted_columns.events`). La colonne passe de `string(500)` à `text` (la
     * charge chiffrée dépasse 500 caractères) et les liens existants sont chiffrés.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->text('online_url')->nullable()->change();
        });

        DB::table('events')->select(['id', 'online_url'])->whereNotNull('online_url')->where('online_url', '!=', '')->orderBy('id')
            ->chunkById(100, static function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('events')->where('id', $row->id)->update(['online_url' => Crypt::encryptString((string) $row->online_url)]);
                }
            });
    }

    public function down(): void
    {
        DB::table('events')->select(['id', 'online_url'])->whereNotNull('online_url')->orderBy('id')
            ->chunkById(100, static function ($rows): void {
                foreach ($rows as $row) {
                    try {
                        $url = Crypt::decryptString((string) $row->online_url);
                    } catch (Throwable) {
                        $url = null;
                    }

                    DB::table('events')->where('id', $row->id)->update(['online_url' => $url !== null ? mb_substr($url, 0, 500) : null]);
                }
            });

        Schema::table('events', function (Blueprint $table) {
            $table->string('online_url', 500)->nullable()->change();
        });
    }
};
