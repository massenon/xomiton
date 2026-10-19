<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Application\Admin\CsvExportLink;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Téléchargement d'un export CSV d'administration (P10-02, ADR-33) par lien signé et chiffré (CsvExportLink), lié au
 * compte qui l'a demandé : l'écran Livewire est instancié hors cycle de vie, son état restauré, puis il rejoue ses
 * contrôles (policy de l'écran, `cases.export` pour un export complet), le plafond de lignes et la ligne d'audit avant
 * de diffuser le fichier par paquets (WithDataTable::respondCsvExport).
 */
final class CsvExportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $link = CsvExportLink::read($request->query('t'), $user);
        abort_if($link === null, 403);

        $screen = app($link['screen']);

        // Méthode protégée du trait WithDataTable : jamais exposée comme action Livewire.
        return (fn (): Response => $this->respondCsvExport($link['state'], $link['full']))->call($screen);
    }
}
