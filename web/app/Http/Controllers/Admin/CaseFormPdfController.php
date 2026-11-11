<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Application\Cases\CaseFormSheet;
use App\Http\Controllers\Controller;
use App\Models\CaseForm;
use App\Support\Audit;
use App\Support\LocaleFormatter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fiche de référence en PDF A4 côté serveur (P11-02, ADR-23 ; prototype admin/fiches.php « Imprimer / Exporter en PDF ») :
 * `barryvdh/laravel-dompdf`, HTML hors ligne (`isRemoteEnabled` désactivé), police DejaVu Sans, pied « Confidentiel » sur
 * chaque page. Réservé à `case_forms.manage` (CaseFormPolicy::print) ; chaque génération est journalisée
 * (`case_forms.printed`, référence et code de dossier, jamais le contenu) ; la réponse n'est jamais mise en cache
 * (`no-store`) ni indexée.
 */
final class CaseFormPdfController extends Controller
{
    public function __invoke(CaseForm $form, CaseFormSheet $sheet): Response
    {
        Gate::authorize('print', $form);

        $data = $sheet($form);
        Audit::log('case_forms.printed', $form, sprintf('fiche=%s ; dossier=%s', $data['reference'], $data['case_code']));

        $pdf = Pdf::setOption(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'defaultFont' => 'DejaVu Sans', 'dpi' => 96])
            ->setPaper('a4', 'portrait')
            ->loadView('pdf.case-form', [
                'sheet' => $data,
                'format' => LocaleFormatter::make(null, (string) config('xomiton.display_timezone', 'UTC')),
                'siteName' => (string) config('app.name', 'Xomiton'),
            ]);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$data['reference'].'.pdf"',
            'Cache-Control' => 'no-store, private',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
