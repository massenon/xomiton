<?php

declare(strict_types=1);

namespace App\Application\Alerts;

use App\Models\Alert;
use App\Models\AlertNote;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Note interne de l'équipe (admin/alertes.php l.39-45, « Visible uniquement par l'équipe ») : corps chiffré, jamais
 * modifié ni supprimé, journal d'audit **sans** le corps. Autorisation `note` (`alerts.manage`).
 */
final class AddInternalNote
{
    public function __invoke(Alert $alert, User $by, string $body): AlertNote
    {
        Gate::forUser($by)->authorize('note', $alert);

        $body = trim($body);
        $max = (int) config('xomiton.alerts.note_max', 1000);

        if ($body === '' || mb_strlen($body) > $max) {
            throw ValidationException::withMessages([
                'body' => $body === ''
                    ? __('validation.required', ['attribute' => __('validation.attributes.body')])
                    : __('validation.max.string', ['attribute' => __('validation.attributes.body'), 'max' => $max]),
            ]);
        }

        $note = AlertNote::query()->create([
            'alert_id' => $alert->getKey(),
            'user_id' => $by->getKey(),
            'body' => $body,
        ]);

        Audit::log('alerts.note_added', $alert, sprintf('note=#%d', (int) $note->getKey()), $by);

        return $note;
    }
}
