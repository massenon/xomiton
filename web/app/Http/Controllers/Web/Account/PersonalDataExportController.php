<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Account;

use App\Application\Account\PersonalDataExports;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * GET /mes-donnees/export/{token} (P6-06) : lien signé de l'e-mail `export-donnees`, connexion exigée (`auth` : après
 * connexion, retour au lien d'origine). Signature vérifiée ici plutôt que par le middleware `signed` : un lien expiré
 * ou altéré affiche « lien expiré, redemande un export » (403, `account.export.link_invalid`) au lieu de la 403
 * générique. Le fichier d'un autre compte, expiré ou effacé : 404. Fichier chiffré sur le disque, déchiffré en mémoire
 * et envoyé en flux ; réponse jamais mise en cache.
 */
final class PersonalDataExportController extends Controller
{
    public function __invoke(Request $request, string $token, PersonalDataExports $exports): StreamedResponse|Response
    {
        if (! $request->hasValidSignature()) {
            return response()->view('account.export-link-invalid', ['hours' => PersonalDataExports::linkHours()], 403)
                ->header('Cache-Control', 'no-store, private');
        }

        /** @var User $user */
        $user = $request->user();
        $path = $exports->find($user, $token) ?? throw new NotFoundHttpException;

        try {
            $json = $exports->read($path);
        } catch (DecryptException) {
            throw new NotFoundHttpException;
        }

        Audit::log('account.export_downloaded', $user, null, $user);

        return response()->streamDownload(static function () use ($json): void {
            echo $json;
        }, 'xomiton-mes-donnees-'.now()->format('Y-m-d').'.json', [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
