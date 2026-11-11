<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

/**
 * Retours d'interface des écrans d'administration (P10-03, UI_UX_DESIGN_SYSTEM.md §4.3 et §4.6) :
 * - `toast()` : toast SweetAlert2 (resources/js/admin/toasts.js : coin supérieur droit, 4,5 s, pause au survol,
 *   `role="status"` / `aria-live="polite"`, `alert` / `assertive` pour une erreur). Texte au passé, même verbe que le
 *   bouton (« Publier » → « Publié. ») ;
 * - `flashToast()` : même toast après une redirection (session, lu par le gabarit admin) ;
 * - `openModal()` / `closeModal()` : modale Bootstrap montée en permanence (x-admin.modal).
 * Types : success | info | warning | error (`danger` est accepté et traité comme `error`).
 */
trait InteractsWithUi
{
    protected function toast(string $message, string $type = 'success'): void
    {
        $this->dispatch('toast', type: self::toastType($type), message: $message);
    }

    /** Même convention de session que l'espace membre (`toast`, `toast_type`, HandleInertiaRequests). */
    protected function flashToast(string $message, string $type = 'success'): void
    {
        session()->flash('toast', $message);
        session()->flash('toast_type', self::toastType($type));
    }

    protected function openModal(string $id): void
    {
        $this->dispatch('xm-modal', id: $id, action: 'show');
    }

    protected function closeModal(string $id): void
    {
        $this->dispatch('xm-modal', id: $id, action: 'hide');
    }

    private static function toastType(string $type): string
    {
        return match ($type) {
            'danger', 'error' => 'error',
            'warning', 'info' => $type,
            default => 'success',
        };
    }
}
