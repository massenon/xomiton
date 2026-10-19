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
     * P8-04 (lot evenements) : l'adresse exacte quitte `place`.
     *
     *  - `place` ne garde que la ville ou la zone, montrée à tous les membres (et aux visiteur·ses pour un événement
     *    public) ;
     *  - `address` (texte chiffré, cast `encrypted`) : adresse exacte, servie aux seul·es participant·es confirmé·es, à
     *    l'organisateur·rice et à la modération (`simulateur.html` l.1164 : « l'adresse exacte n'est envoyée qu'aux
     *    participant·es confirmé·es ») ;
     *  - `is_public` (Q35 a) : événement ouvert aux visiteur·ses, listé sur la page Blade publique `/evenements`, dans le
     *    plan du site et en JSON-LD `Event` — sans adresse ni lien de connexion.
     *
     * Reprise : un lieu du prototype « Ville – lieu précis » (`Cotonou – Zogbo`) est coupé au premier tiret demi-cadratin :
     * la ville reste dans `place`, le reste devient l'adresse chiffrée.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->text('address')->nullable()->after('place');
            $table->boolean('is_public')->default(false)->after('status');
            $table->index(['is_public', 'status', 'starts_at']);
        });

        DB::table('events')->select(['id', 'place'])->where('place', 'like', '% – %')->orderBy('id')
            ->chunkById(100, static function ($rows): void {
                foreach ($rows as $row) {
                    [$city, $address] = array_map('trim', explode(' – ', (string) $row->place, 2));

                    if ($city === '' || $address === '') {
                        continue;
                    }

                    DB::table('events')->where('id', $row->id)->update([
                        'place' => mb_substr($city, 0, 200),
                        'address' => Crypt::encryptString($address),
                    ]);
                }
            });
    }

    /**
     * Retour arrière : l'adresse exacte déchiffrée est recollée dans `place` (« Ville – adresse », 200 caractères au
     * plus), pour ne pas la perdre avec la colonne.
     */
    public function down(): void
    {
        DB::table('events')->select(['id', 'place', 'address'])->whereNotNull('address')->orderBy('id')
            ->chunkById(100, static function ($rows): void {
                foreach ($rows as $row) {
                    try {
                        $address = trim(Crypt::decryptString((string) $row->address));
                    } catch (Throwable) {
                        continue;
                    }

                    if ($address === '') {
                        continue;
                    }

                    $city = trim((string) $row->place);
                    DB::table('events')->where('id', $row->id)->update([
                        'place' => mb_substr($city === '' ? $address : $city.' – '.$address, 0, 200),
                    ]);
                }
            });

        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['is_public', 'status', 'starts_at']);
            $table->dropColumn(['address', 'is_public']);
        });
    }
};
