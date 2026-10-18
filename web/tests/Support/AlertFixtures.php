<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Application\Alerts\Data\ReportData;
use App\Application\Alerts\Data\SosData;
use App\Enums\AlertSource;
use App\Enums\AlertType;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Support\Str;

/**
 * Données de test des cas d'usage d'alerte (P5-02) : signalement, SOS, comptes de l'équipe.
 */
final class AlertFixtures
{
    public const DESCRIPTION = 'Mon voisin me menace depuis trois semaines devant chez moi.';

    /**
     * @param  array<string, mixed>  $overrides  arguments nommés de ReportData
     */
    public static function report(array $overrides = []): ReportData
    {
        return new ReportData(...[
            'type' => AlertType::Report,
            'idempotencyKey' => (string) Str::uuid(),
            'source' => AlertSource::Web,
            'userId' => null,
            'anonymous' => true,
            'category' => 'physique',
            'description' => self::DESCRIPTION,
            'occurredOn' => null,
            'needs' => [],
            'zoneId' => PrototypeDirectory::zone('Littoral')->id,
            ...$overrides,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides  arguments nommés de SosData
     */
    public static function sos(array $overrides = []): SosData
    {
        return new SosData(...[
            'idempotencyKey' => (string) Str::uuid(),
            'source' => AlertSource::Web,
            ...$overrides,
        ]);
    }

    public static function admin(): User
    {
        $admin = User::factory()->withProfile()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    public static function member(string $role = 'membre'): User
    {
        $user = User::factory()->withProfile()->create();
        $user->assignRole($role);

        return $user;
    }

    public static function zone(string $name): Zone
    {
        return PrototypeDirectory::zone($name);
    }
}
