<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\DirectoryRole;
use App\Models\Category;
use App\Models\Commune;
use App\Models\Need;
use App\Models\Population;
use App\Models\ViolenceType;
use App\Models\Zone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Référentiels du lot 1, repris des données du prototype (prototype_social/inc/data.php :
 * x_zones(), x_violence_categories(), x_populations(), x_needs(), x_thematique_cats()).
 * Idempotent : rejouable sans doublon, les libellés sont réalignés sur le prototype.
 */
class ReferenceDataSeeder extends Seeder
{
    /** @var array<string, list<string>> département => communes (x_zones()) */
    private const ZONES = [
        'Alibori' => ['Kandi', 'Malanville', 'Banikoara'],
        'Atacora' => ['Natitingou', 'Tanguiéta'],
        'Atlantique' => ['Abomey-Calavi', 'Ouidah', 'Allada'],
        'Borgou' => ['Parakou', 'Nikki', 'Tchaourou'],
        'Collines' => ['Dassa-Zoumè', 'Savalou', 'Savè'],
        'Couffo' => ['Aplahoué', 'Dogbo'],
        'Donga' => ['Djougou', 'Bassila'],
        'Littoral' => ['Cotonou'],
        'Mono' => ['Lokossa', 'Grand-Popo'],
        'Ouémé' => ['Porto-Novo', 'Sèmè-Podji', 'Adjarra'],
        'Plateau' => ['Pobè', 'Kétou'],
        'Zou' => ['Abomey', 'Bohicon', 'Covè'],
    ];

    /** @var list<array{0: string, 1: string, 2: string, 3: string}> slug, libellé, icône, description (x_violence_categories()) */
    private const VIOLENCE_TYPES = [
        ['physique', 'Violence physique', 'bi-hand-index-thumb', 'Coups, brûlures, séquestration, agressions.'],
        ['psychologique', 'Violence psychologique', 'bi-chat-square-heart', "Insultes, humiliations, menaces, chantage à l'outing."],
        ['sexuelle', 'Violence sexuelle', 'bi-exclamation-octagon', 'Viol, attouchements, « viols correctifs », exploitation.'],
        ['economique', 'Violence économique', 'bi-cash-coin', 'Privation de ressources, contrôle financier, exclusion.'],
        ['numerique', 'Violence en ligne', 'bi-phone', "Cyberharcèlement, diffusion d'images, doxxing, outing."],
    ];

    /** @var list<string> x_populations() */
    private const POPULATIONS = [
        'Femmes cis',
        'Femmes lesbiennes',
        'Femmes bisexuelles',
        'Personnes trans / non-binaires',
        'Travailleuses du sexe',
        'Femmes vivant avec le VIH',
        'Jeunes filles (15-24)',
    ];

    /** @var array<string, array{0: string, 1: DirectoryRole|null}> slug => libellé, rôle ciblé (x_needs()) */
    private const NEEDS = [
        'soutien_psychologique' => ['Soutien psychologique', DirectoryRole::Psychologue],
        'soutien_juridique' => ['Soutien juridique', DirectoryRole::Juriste],
        'soutien_medical' => ['Prise en charge médicale', DirectoryRole::Gynecologue],
        'hebergement' => ["Hébergement d'urgence", null],
        'accompagnement' => ['Accompagnement (police, tribunal)', null],
        'securite' => ['Mise en sécurité', null],
        'autre' => ['Autre besoin', null],
    ];

    /** @var array<string, array{0: string, 1: string, 2: string}> slug => libellé, icône, couleur (x_thematique_cats()) */
    private const THEMATIQUE_CATEGORIES = [
        'vbg' => ['Violences basées sur le genre', 'bi-shield-exclamation', 'danger'],
        'regles' => ['Hygiène menstruelle', 'bi-droplet-half', 'danger'],
        'mental' => ['Santé mentale', 'bi-heart-pulse', 'primary'],
        'droits' => ['Droits & lois', 'bi-bank', 'info'],
        'esc' => ['Éducation complète à la sexualité', 'bi-book', 'success'],
        'corps' => ['Autonomie & autonomisation', 'bi-person-check', 'warning'],
    ];

    public function run(): void
    {
        $this->seedZonesAndCommunes();
        $this->seedViolenceTypes();
        $this->seedPopulations();
        $this->seedNeeds();
        $this->seedThematiqueCategories();
    }

    private function seedZonesAndCommunes(): void
    {
        $zonePosition = 0;

        foreach (self::ZONES as $zoneName => $communes) {
            $zone = Zone::query()->updateOrCreate(
                ['slug' => Str::slug($zoneName)],
                ['name' => $zoneName, 'position' => ++$zonePosition, 'is_active' => true],
            );

            foreach ($communes as $index => $communeName) {
                Commune::query()->updateOrCreate(
                    ['zone_id' => $zone->getKey(), 'slug' => Str::slug($communeName)],
                    ['name' => $communeName, 'position' => $index + 1, 'is_active' => true],
                );
            }
        }
    }

    private function seedViolenceTypes(): void
    {
        foreach (self::VIOLENCE_TYPES as $index => [$slug, $label, $icon, $description]) {
            ViolenceType::query()->updateOrCreate(
                ['slug' => $slug],
                ['label' => $label, 'icon' => $icon, 'description' => $description, 'position' => $index + 1, 'is_active' => true],
            );
        }
    }

    private function seedPopulations(): void
    {
        foreach (self::POPULATIONS as $index => $label) {
            Population::query()->updateOrCreate(
                ['slug' => Str::slug($label)],
                ['label' => $label, 'position' => $index + 1, 'is_active' => true],
            );
        }
    }

    private function seedNeeds(): void
    {
        $position = 0;

        foreach (self::NEEDS as $slug => [$label, $role]) {
            Need::query()->updateOrCreate(
                ['slug' => $slug],
                ['label' => $label, 'routed_role' => $role, 'position' => ++$position, 'is_active' => true],
            );
        }
    }

    private function seedThematiqueCategories(): void
    {
        $position = 0;

        foreach (self::THEMATIQUE_CATEGORIES as $slug => [$label, $icon, $color]) {
            Category::query()->updateOrCreate(
                ['kind' => Category::KIND_THEMATIQUE, 'slug' => $slug],
                ['label' => $label, 'icon' => $icon, 'color' => $color, 'position' => ++$position, 'is_active' => true],
            );
        }
    }
}
