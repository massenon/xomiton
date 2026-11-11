<?php

declare(strict_types=1);

namespace App\Application\Shared;

use Collator;
use Illuminate\Support\Facades\Cache;
use Locale;

/**
 * Liste des pays (ISO 3166-1 alpha-2, 249 codes) pour l'inscription : libellés en français tirés d'ICU (ext-intl,
 * `Locale::getDisplayRegion`), triés selon l'ordre alphabétique français (`Collator`, accents et « Å » compris), le
 * Bénin proposé en premier. Calculée une fois puis gardée en cache 24 h (clé versionnée, comme les autres référentiels).
 */
final class Countries
{
    public const BENIN = 'BJ';

    /** @var list<string> Codes officiels ISO 3166-1 alpha-2. */
    public const CODES = [
        'AD', 'AE', 'AF', 'AG', 'AI', 'AL', 'AM', 'AO', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AW', 'AX', 'AZ',
        'BA', 'BB', 'BD', 'BE', 'BF', 'BG', 'BH', 'BI', 'BJ', 'BL', 'BM', 'BN', 'BO', 'BQ', 'BR', 'BS', 'BT', 'BV', 'BW', 'BY', 'BZ',
        'CA', 'CC', 'CD', 'CF', 'CG', 'CH', 'CI', 'CK', 'CL', 'CM', 'CN', 'CO', 'CR', 'CU', 'CV', 'CW', 'CX', 'CY', 'CZ',
        'DE', 'DJ', 'DK', 'DM', 'DO', 'DZ', 'EC', 'EE', 'EG', 'EH', 'ER', 'ES', 'ET',
        'FI', 'FJ', 'FK', 'FM', 'FO', 'FR',
        'GA', 'GB', 'GD', 'GE', 'GF', 'GG', 'GH', 'GI', 'GL', 'GM', 'GN', 'GP', 'GQ', 'GR', 'GS', 'GT', 'GU', 'GW', 'GY',
        'HK', 'HM', 'HN', 'HR', 'HT', 'HU',
        'ID', 'IE', 'IL', 'IM', 'IN', 'IO', 'IQ', 'IR', 'IS', 'IT',
        'JE', 'JM', 'JO', 'JP',
        'KE', 'KG', 'KH', 'KI', 'KM', 'KN', 'KP', 'KR', 'KW', 'KY', 'KZ',
        'LA', 'LB', 'LC', 'LI', 'LK', 'LR', 'LS', 'LT', 'LU', 'LV', 'LY',
        'MA', 'MC', 'MD', 'ME', 'MF', 'MG', 'MH', 'MK', 'ML', 'MM', 'MN', 'MO', 'MP', 'MQ', 'MR', 'MS', 'MT', 'MU', 'MV', 'MW', 'MX', 'MY', 'MZ',
        'NA', 'NC', 'NE', 'NF', 'NG', 'NI', 'NL', 'NO', 'NP', 'NR', 'NU', 'NZ',
        'OM',
        'PA', 'PE', 'PF', 'PG', 'PH', 'PK', 'PL', 'PM', 'PN', 'PR', 'PS', 'PT', 'PW', 'PY',
        'QA',
        'RE', 'RO', 'RS', 'RU', 'RW',
        'SA', 'SB', 'SC', 'SD', 'SE', 'SG', 'SH', 'SI', 'SJ', 'SK', 'SL', 'SM', 'SN', 'SO', 'SR', 'SS', 'ST', 'SV', 'SX', 'SY', 'SZ',
        'TC', 'TD', 'TF', 'TG', 'TH', 'TJ', 'TK', 'TL', 'TM', 'TN', 'TO', 'TR', 'TT', 'TV', 'TW', 'TZ',
        'UA', 'UG', 'UM', 'US', 'UY', 'UZ',
        'VA', 'VC', 'VE', 'VG', 'VI', 'VN', 'VU',
        'WF', 'WS',
        'YE', 'YT',
        'ZA', 'ZM', 'ZW',
    ];

    private const CACHE_KEY = 'countries:v1:fr';

    private const TTL_SECONDS = 86400;

    private function __construct() {}

    /**
     * @return list<array{value: string, label: string}> Bénin d'abord, puis tous les pays par ordre alphabétique français.
     */
    public static function all(): array
    {
        /** @var list<array{value: string, label: string}> $list */
        $list = Cache::remember(self::CACHE_KEY, self::TTL_SECONDS, static fn (): array => self::build());

        return $list;
    }

    public static function isValid(string $code): bool
    {
        return in_array($code, self::CODES, true);
    }

    public static function name(string $code): ?string
    {
        foreach (self::all() as $country) {
            if ($country['value'] === $code) {
                return $country['label'];
            }
        }

        return null;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private static function build(): array
    {
        $names = [];

        foreach (self::CODES as $code) {
            $name = Locale::getDisplayRegion('-'.$code, 'fr');
            $names[$code] = $name === '' ? $code : $name;
        }

        $collator = new Collator('fr_FR');
        $collator->asort($names);

        $benin = $names[self::BENIN];
        unset($names[self::BENIN]);

        $list = [['value' => self::BENIN, 'label' => $benin]];

        foreach ($names as $code => $name) {
            $list[] = ['value' => (string) $code, 'label' => $name];
        }

        return $list;
    }
}
