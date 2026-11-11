<?php

declare(strict_types=1);

namespace App\Application\Content;

use App\Application\Shared\MediaUploader;
use App\Models\Resource;
use App\Support\CountableVisit;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Fichier d'une ressource (P4-03, « téléchargement signé ») par deux adresses stables écrites dans la page et transmises
 * au service worker (P5-10) — jamais de lien signé dans le HTML, il expirerait dans une page servie depuis un cache :
 *   - `/ressources/{slug}/telecharger` : compte puis renvoie un lien signé périssable (`media.signed`,
 *     `xomiton.uploads.signed_url_minutes`) vers le fichier du disque privé ;
 *   - `/ressources/{slug}/lire` (vidéo / audio) : le contrôleur sert le fichier lui-même, plages `Range` comprises
 *     (206, `Accept-Ranges`), sans redirection : iOS Safari exige les plages, l'avance dans le média ne recharge pas tout
 *     le fichier et aucune signature n'expire en cours de lecture.
 *
 * Compté (`downloads_count`, indicateur de la page, CONTENT_STRATEGY.md) : comme une consultation (CountableVisit : GET
 * d'un navigateur, ni préchargement ni robot) et seulement la première requête de plage (CountableVisit::isFirstRange) —
 * une lecture ne compte pas une fois par saut dans le fichier. Requête directe : `updated_at` intact. Aucune réponse HTTP
 * n'est décidée ici : null → 404 au contrôleur.
 */
final class ResourceDownload
{
    public function __construct(private readonly MediaUploader $uploader) {}

    /** Ressource du catalogue (publiée, hors actualité) qui porte un fichier, sinon null. */
    public function find(string $slug): ?Resource
    {
        $resource = Resource::query()->catalog()->where('slug', $slug)->first(['id', 'slug', 'kind', 'file_path']);

        return $resource !== null && $resource->isDownloadable() ? $resource : null;
    }

    /** Même règle par identifiant (API mobile, `GET /api/v1/ressources/{id}/download`, P6-08), poids compris. */
    public function findById(int $id): ?Resource
    {
        $resource = Resource::query()->catalog()->whereKey($id)->first(['id', 'slug', 'kind', 'file_path', 'file_size']);

        return $resource !== null && $resource->isDownloadable() ? $resource : null;
    }

    public function record(Resource $resource, Request $request): void
    {
        if (CountableVisit::counts($request) && CountableVisit::isFirstRange($request)) {
            Resource::query()->whereKey($resource->getKey())->toBase()->increment('downloads_count');
        }
    }

    /**
     * Lien signé temporaire vers le fichier du disque privé ; `$expires` (expires()) quand l'appelant renvoie aussi
     * l'échéance au client : même instant que la signature.
     */
    public function signedUrl(Resource $resource, ?CarbonInterface $expires = null): string
    {
        return $this->uploader->signedUrl(MediaUploader::privateDisk(), (string) $resource->file_path, $expires ?? $this->expires());
    }

    /** Échéance d'un lien délivré maintenant (`xomiton.uploads.signed_url_minutes`). */
    public function expires(): CarbonInterface
    {
        return now()->addMinutes(max(1, (int) config('xomiton.uploads.signed_url_minutes', 10)));
    }

    /**
     * Fichier lisible dans la page : vidéo ou audio dont le fichier existe sur le disque privé et dont le type réel
     * (libmagic, jamais l'extension) est un média ; sinon null.
     *
     * @return array{path: string, mime: string, name: string}|null
     */
    public function media(Resource $resource): ?array
    {
        if (! $resource->isPlayable()) {
            return null;
        }

        $storage = Storage::disk(MediaUploader::privateDisk());
        $relative = ltrim(str_replace('\\', '/', (string) $resource->file_path), '/');

        if (str_contains($relative, '..') || ! $storage->exists($relative)) {
            return null;
        }

        $path = $storage->path($relative);
        $mime = MediaUploader::detectMime($path);

        if (! str_starts_with($mime, 'video/') && ! str_starts_with($mime, 'audio/')) {
            return null;
        }

        $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));

        return ['path' => $path, 'mime' => $mime, 'name' => $resource->slug.($extension !== '' ? '.'.$extension : '')];
    }
}
