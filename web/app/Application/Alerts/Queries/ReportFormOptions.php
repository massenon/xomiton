<?php

declare(strict_types=1);

namespace App\Application\Alerts\Queries;

use App\Application\Shared\MediaUploader;
use App\Application\Shared\Referentials;
use App\Enums\ContactChannel;

/**
 * Options du formulaire /signaler (P5-04) : types de violence, besoins, départements et communes depuis les
 * référentiels en cache (Referentials, la même source que `GET /api/v1/zones` ; aucune liste codée en dur), canaux de
 * recontact (ContactChannel) et bornes de la charge utile (récit, preuves) lues dans config/xomiton.php et
 * MediaUploader, celles que StoreReportRequest applique.
 */
final class ReportFormOptions
{
    /** Collections de MediaUploader acceptées comme preuves (AlertAttachment::kindForMime). */
    public const EVIDENCE_COLLECTIONS = ['images', 'documents', 'audio'];

    public function __construct(private readonly Referentials $referentials) {}

    /**
     * @return array{
     *     violence_types: array<string, string>,
     *     needs: array<string, string>,
     *     zones: list<array{id: int, name: string, communes: array<int, string>}>,
     *     channels: array<string, string>,
     *     limits: array{description_min: int, description_max: int, attachments_max: int, max_mb: array<string, int>, accept: string, max_mb_display: int}
     * }
     */
    public function __invoke(): array
    {
        $types = [];
        foreach ($this->referentials->violenceTypes() as $type) {
            $types[$type['slug']] = $type['label'];
        }

        $needs = [];
        foreach ($this->referentials->needs() as $need) {
            $needs[$need['slug']] = $need['label'];
        }

        $zones = array_map(static function (array $zone): array {
            $communes = [];
            foreach ($zone['communes'] as $commune) {
                $communes[(int) $commune['id']] = $commune['name'];
            }

            return ['id' => (int) $zone['id'], 'name' => $zone['name'], 'communes' => $communes];
        }, $this->referentials->zones());

        $channels = [];
        foreach (ContactChannel::cases() as $channel) {
            $channels[$channel->value] = (string) __('alerts.report.channels.'.$channel->value);
        }

        return [
            'violence_types' => $types,
            'needs' => $needs,
            'zones' => $zones,
            'channels' => $channels,
            'limits' => self::limits(),
        ];
    }

    /**
     * @return array{description_min: int, description_max: int, attachments_max: int, max_mb: array<string, int>, accept: string, max_mb_display: int}
     */
    public static function limits(): array
    {
        $maxMb = [];
        $mimes = [];

        foreach (self::EVIDENCE_COLLECTIONS as $collection) {
            $maxMb[$collection] = (int) floor(MediaUploader::maxKb($collection) / 1024);
            $mimes = [...$mimes, ...MediaUploader::allowedMimes($collection)];
        }

        return [
            'description_min' => (int) config('xomiton.alerts.description_min', 20),
            'description_max' => (int) config('xomiton.alerts.description_max', 4000),
            'attachments_max' => (int) config('xomiton.alerts.attachments_max', 5),
            'max_mb' => $maxMb,
            'accept' => implode(',', array_values(array_unique($mimes))),
            'max_mb_display' => $maxMb === [] ? 0 : max($maxMb),
        ];
    }
}
