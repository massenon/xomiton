{{--
    Conteneur des toasts publics (prototype assets/js/xomiton.js XM.toast : .toast-container.position-fixed.top-0.end-0.p-3 ;
    Bootstrap Toast .toast.align-items-center.text-bg-{type}.border-0, 4,5 s, fermable). Léger : aucune information ne doit exister uniquement dans un toast.
    Déclenchement : window.XM.toast(message, type) ou $dispatch('toast', { type, message }) (resources/js/public/xomiton-ui.js).
    Chaque toast : role="status" + aria-live="polite" (succès, info) ou role="alert" + aria-live="assertive" (danger).
    Props : message + type (primary | success | danger | warning | info | secondary) → toast affiché au chargement (flash de session : :message="session('toast')").
    L'administration utilise SweetAlert2 (UI_UX_DESIGN_SYSTEM §4.6) ; ce composant sert aux pages publiques.
--}}
@props(['message' => null, 'type' => 'primary'])
<div {{ $attributes->class('toast-container position-fixed top-0 end-0 p-3') }} id="xmToastWrap" data-xm-close-label="{{ __('common.close') }}">
    @if ($message)
        <div class="toast align-items-center text-bg-{{ $type }} border-0" role="{{ $type === 'danger' ? 'alert' : 'status' }}" aria-live="{{ $type === 'danger' ? 'assertive' : 'polite' }}" aria-atomic="true" data-xm-toast-autoshow>
            <div class="d-flex">
                <div class="toast-body">{{ $message }}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="{{ __('common.close') }}"></button>
            </div>
        </div>
    @endif
</div>
