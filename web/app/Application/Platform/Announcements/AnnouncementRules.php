<?php

declare(strict_types=1);

namespace App\Application\Platform\Announcements;

use App\Enums\AnnouncementAudience;
use App\Enums\AnnouncementLevel;
use Illuminate\Validation\Rule;

/**
 * Règles du formulaire d'annonce globale (P18-13, `/admin/annonces`) : titre court, message, lien **interne** facultatif
 * (chemin qui commence par une seule barre oblique : `/forums`, `/evenements`… ; jamais une adresse externe), niveau,
 * public, période (dates saisies à l'heure du Bénin ; fin facultative, postérieure au début).
 */
final class AnnouncementRules
{
    public const TITLE_MAX = 80;

    public const MESSAGE_MAX = 280;

    public const LINK_MAX = 200;

    /** Chemin interne : « / » suivi d'un caractère qui n'est ni « / » ni « \ », sans espace ni schéma. */
    public const LINK_PATTERN = '#^/(?![/\\\\])[A-Za-z0-9\-._~/?=&%\#]*$#';

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:'.self::TITLE_MAX],
            'message' => ['required', 'string', 'min:5', 'max:'.self::MESSAGE_MAX],
            'link_path' => ['nullable', 'string', 'max:'.self::LINK_MAX, 'regex:'.self::LINK_PATTERN],
            'level' => ['required', Rule::enum(AnnouncementLevel::class)],
            'audience' => ['required', Rule::enum(AnnouncementAudience::class)],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'ends_at' => ['nullable', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
        ];
    }
}
