<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Liens universels iOS (apple-app-site-association) et Android (assetlinks.json), lot IOS-09. Identifiants lus de
 * `xomiton.universal_links` (APPLE_TEAM_ID, ANDROID_SHA256_FINGERPRINTS) : vides, le fichier reste valide avec une liste
 * vide. Aucun secret : ces fichiers sont publics par nature. Servis sans session ni cookie (routes/well-known.php).
 */
final class UniversalLinksController extends Controller
{
    public function apple(): JsonResponse
    {
        $config = (array) config('xomiton.universal_links');
        $team = (string) ($config['apple_team_id'] ?? '');
        $appIds = $team === '' ? [] : [$team.'.'.$config['bundle_id']];
        $components = array_map(fn (string $path): array => ['/' => $path], (array) $config['paths']);

        return $this->json([
            'applinks' => [
                'details' => [['appIDs' => $appIds, 'components' => $components]],
            ],
        ]);
    }

    public function android(): JsonResponse
    {
        $config = (array) config('xomiton.universal_links');
        $fingerprints = array_values((array) ($config['android_sha256_fingerprints'] ?? []));

        if ($fingerprints === []) {
            return $this->json([]);
        }

        return $this->json([[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => [
                'namespace' => 'android_app',
                'package_name' => $config['bundle_id'],
                'sha256_cert_fingerprints' => $fingerprints,
            ],
        ]]);
    }

    /** @param array<mixed> $payload */
    private function json(array $payload): JsonResponse
    {
        return response()->json($payload, 200, [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'public, max-age='.(int) config('xomiton.universal_links.max_age', 3600),
        ], JSON_UNESCAPED_SLASHES);
    }
}
