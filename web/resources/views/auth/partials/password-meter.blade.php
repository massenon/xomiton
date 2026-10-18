{{--
    Jauge de solidité (LOGIN §4, Q30 a) : 4 segments + texte + liste des règles cochées en direct par
    resources/js/public/password-meter.js (mêmes règles que App\Domain\Auth\PasswordStrength). Indicative : le bouton reste actif.
    Texte + couleur : le niveau est écrit (préfixe « Solidité du mot de passe » lu par les lecteurs d'écran) et coloré ;
    champ vide = data-level="empty", texte neutre (pas de rouge avant la saisie, authentification.md).
    Règles affichées avant la saisie et liées au champ : la page passe aria-describedby="{$id}-rules" à x-field (lues au focus).
    Seul le niveau est en aria-live, réécrit seulement quand il change (password-meter.js).
    Paramètre : $id (identifiant de la jauge, référencé par data-password-meter sur le champ).
--}}
@php($rules = ['length', 'uppercase', 'lowercase', 'digit', 'symbol'])
<div id="{{ $id }}" class="xm-pw-meter mt-2" data-level="empty" data-labels='@json(__('auth.password_strength'))'>
    <div class="xm-pw-meter__segments" aria-hidden="true">
        @for ($i = 0; $i < \App\Domain\Auth\PasswordStrength::SEGMENTS; $i++)<span class="xm-pw-meter__segment"></span>@endfor
    </div>
    <p class="xm-pw-meter__label small fw-bold mb-1" aria-live="polite" aria-atomic="true"><span class="visually-hidden">{{ __('auth.password_meter_label') }} </span><span data-password-label>{{ __('auth.password_strength.empty') }}</span></p>
    <ul id="{{ $id }}-rules" class="xm-pw-meter__rules list-unstyled small mb-0">
        @foreach ($rules as $rule)
            <li data-rule="{{ $rule }}" data-checked="false"><i class="bi bi-circle me-1" data-rule-icon aria-hidden="true"></i>{{ __('auth.password_rules.'.$rule) }}</li>
        @endforeach
    </ul>
</div>
