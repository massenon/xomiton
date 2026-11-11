<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Me;

use App\Application\Account\Jobs\BuildPersonalDataExport;
use App\Application\Account\PersonalDataExports;
use App\Http\Controllers\Api\V1\ApiController;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/v1/me/export (P6-06, RGPD) : 202, l'export est construit en file (BuildPersonalDataExport) puis le lien de
 * téléchargement part par e-mail (`export-donnees`). Limiteur `account_sensitive` (par compte et par route).
 */
final class ExportController extends ApiController
{
    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $hours = PersonalDataExports::linkHours();

        Audit::log('account.export_requested', $user, null, $user);
        BuildPersonalDataExport::dispatch((int) $user->getKey());

        return $this->accepted([
            'status' => 'preparing',
            'link_valid_hours' => $hours,
            'message' => __('profile.settings.data.preparing', ['hours' => $hours]),
        ]);
    }
}
