<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\DirectoryRole;
use App\Models\DirectoryContact;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contacts d'annuaire fictifs pour voir les fiches thématiques en local ou en recette (P4-02) : six fiches publiques, une
 * par rôle proposé en bas des fiches, plus une non publique et une inactive pour voir le filtre Q19 (jamais affichées
 * hors connexion). Numéros fictifs. Lancement manuel seulement (`php artisan db:seed --class=DemoDirectorySeeder`) :
 * l'annuaire de démonstration reste celui de `xomiton:import-prototype --only=annuaire`, que DatabaseSeeder ne doit pas
 * préremplir. Jamais en production ; idempotent (clé : le nom).
 */
class DemoDirectorySeeder extends Seeder
{
    /**
     * @return list<array{name: string, role: DirectoryRole, org: string, phone: string, zone: string, is_public: bool, is_active: bool, is_national_focal: bool}>
     */
    public static function contacts(): array
    {
        $contact = static fn (string $name, DirectoryRole $role, string $org, string $phone, string $zone, bool $public = true, bool $active = true, bool $national = false): array => [
            'name' => $name, 'role' => $role, 'org' => $org, 'phone' => $phone, 'zone' => $zone,
            'is_public' => $public, 'is_active' => $active, 'is_national_focal' => $national,
        ];

        return [
            $contact('Démo – Point focal national', DirectoryRole::PointFocal, 'Coordination Xomiton', '+229 01 00 00 01 01', 'Littoral', national: true),
            $contact('Démo – Juriste', DirectoryRole::Juriste, 'Clinique juridique partenaire', '+229 01 00 00 01 02', 'Littoral'),
            $contact('Démo – Psychologue', DirectoryRole::Psychologue, 'Centre d’écoute partenaire', '+229 01 00 00 01 03', 'Atlantique'),
            $contact('Démo – Gynécologue', DirectoryRole::Gynecologue, 'Centre de santé partenaire', '+229 01 00 00 01 04', 'Ouémé'),
            $contact('Démo – Pair animatrice', DirectoryRole::PairAnimateur, 'Réseau Xomiton', '+229 01 00 00 01 05', 'Borgou'),
            $contact('Démo – Responsable de réseau', DirectoryRole::ResponsableReseau, 'Réseau Xomiton', '+229 01 00 00 01 06', 'Zou'),
            $contact('Démo – Juriste (non publique)', DirectoryRole::Juriste, 'Clinique juridique partenaire', '+229 01 00 00 01 07', 'Littoral', public: false),
            $contact('Démo – Psychologue (inactive)', DirectoryRole::Psychologue, 'Centre d’écoute partenaire', '+229 01 00 00 01 08', 'Atlantique', active: false),
        ];
    }

    public function run(): void
    {
        if (! app()->environment(DatabaseSeeder::DEMO_ENVIRONMENTS) || ! Schema::hasTable('directory_contacts')) {
            return;
        }

        foreach (self::contacts() as $contact) {
            $model = DirectoryContact::query()->withTrashed()->updateOrCreate(
                ['name' => $contact['name']],
                [
                    'role' => $contact['role'],
                    'org' => $contact['org'],
                    'phone' => $contact['phone'],
                    'whatsapp' => $contact['phone'],
                    'email' => null,
                    'zone_id' => Schema::hasTable('zones') ? DB::table('zones')->where('name', $contact['zone'])->value('id') : null,
                    'availability' => 'Lun–Ven, 8 h – 17 h',
                    'is_active' => $contact['is_active'],
                    'is_public' => $contact['is_public'],
                    'is_national_focal' => $contact['is_national_focal'],
                ],
            );

            if ($model->trashed()) {
                $model->restore();
            }
        }
    }
}
