{{--
    Jauge de solidité (LOGIN §4, Q30 a) : 4 segments + texte + liste des règles cochées en direct par
    resources/js/public/password-meter.js (mêmes règles que App\Domain\Auth\PasswordStrength). Indicative : le bouton reste actif.
    Paramètre : $id (identifiant de la jauge, référencé par data-password-meter sur le champ).
--}}
@php($rules = ['length', 'uppercase', 'lowercase', 'digit', 'symbol'])
<div id="{{ $id }}" class="xm-pw-meter mt-2" data-labels='@json(__('auth.password_strength'))' aria-live="polite">
    <div class="xm-pw-meter__segments" aria-hidden="true">
        @for ($i = 0; $i < \App\Domain\Auth\PasswordStrength::SEGMENTS; $i++)<span class="xm-pw-meter__segment"></span>@endfor
    </div>
    <p class="xm-pw-meter__label small fw-bold mb-1" data-password-label>{{ __('auth.password_strength.empty') }}</p>
    <ul class="xm-pw-meter__rules list-unstyled small mb-0">
        @foreach ($rules as $rule)
            <li data-rule="{{ $rule }}" data-checked="false"><i class="bi bi-circle me-1" data-rule-icon aria-hidden="true"></i>{{ __('auth.password_rules.'.$rule) }}</li>
        @endforeach
    </ul>
</div>
