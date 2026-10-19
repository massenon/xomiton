{{--
    Contenu de la page de maintenance (P13-07), bloc autonome `.xmm` stylé par maintenance/content.css (jetons --xm-*
    redéfinis dans le bloc, requêtes de conteneur) : servi par maintenance.blade.php et par l'aperçu en direct de l'écran
    d'administration (P13-06), qui affiche ainsi exactement le même rendu.
    Ordre : accès prioritaires SOS · Signaler · Urgences (RG-01, D8), logo, état (pastille animée + « Maintenance en
    cours »), titre, message public, carte d'état (fin prévue en clair, compte à rebours et barre d'avancement ajoutés
    par le script, dépassement « La reprise prend un peu plus de temps… »), illustration, numéros d'urgence (même source
    que /urgences), réassurance, pied avec le lien discret vers l'espace administration.
    `generic` (errors/503 hors maintenance : panne, base injoignable) : titre neutre, bandeau « si le SOS en ligne ne
    répond pas, appelez… », sans carte « Pendant la maintenance » ni promesse sur les parcours en ligne.
    Variables : voir App\Application\Platform\MaintenancePage::data() ; `theme` (aperçu) force clair ou sombre ;
    `embedded` (aperçu en direct de l'administration) remplace <main> par un bloc.
--}}
@php
    $texts = [
        'remaining' => __('maintenance.status.remaining_live'),
        'overdue' => __('maintenance.status.overdue'),
        'reopened' => __('maintenance.status.reopened'),
        'soon' => __('maintenance.status.soon'),
    ];
    $sosUrl = route('sos', absolute: false);
    $reportUrl = route('signaler', absolute: false);
    $urgencesUrl = route('urgences', absolute: false);
    $mainTag = ! empty($embedded) ? 'div' : 'main';
@endphp
<div @class(['xmm', 'xmm-overdue' => $overdue, 'xmm-generic' => $generic]) data-xm-maintenance @if (! empty($theme)) data-xm-theme="{{ $theme }}" @endif
     @if ($endsIso) data-ends-at="{{ $endsIso }}" @endif @if ($startsIso) data-starts-at="{{ $startsIso }}" @endif
     @if ($statusUrl) data-status-url="{{ $statusUrl }}" data-poll="{{ $pollSeconds }}" @endif
     data-announce="{{ implode(',', $announceMinutes) }}"
     data-texts="{{ json_encode($texts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}">
    <svg class="xmm-sprite" aria-hidden="true" focusable="false">
        <symbol id="xmm-i-sos" viewBox="0 0 24 24"><path d="M8.2 2.5h7.6l5.7 5.7v7.6l-5.7 5.7H8.2l-5.7-5.7V8.2z"/><path d="M12 7.5v5.5M12 16.4v.1"/></symbol>
        <symbol id="xmm-i-flag" viewBox="0 0 24 24"><path d="M5.5 21V3.5M5.5 4h12l-2.5 4.5 2.5 4.5h-12"/></symbol>
        <symbol id="xmm-i-phone" viewBox="0 0 24 24"><path d="M6.8 3.2 9.4 3l1.8 4.3-2.3 1.5a12 12 0 0 0 6.3 6.3l1.5-2.3 4.3 1.8-.2 2.6a2.8 2.8 0 0 1-3 2.6A16 16 0 0 1 4.2 6.2a2.8 2.8 0 0 1 2.6-3z"/></symbol>
        <symbol id="xmm-i-heart" viewBox="0 0 24 24"><path d="M12 20s-7.5-4.6-7.5-10.2A4.2 4.2 0 0 1 12 7.2a4.2 4.2 0 0 1 7.5 2.6C19.5 15.4 12 20 12 20z"/></symbol>
        <symbol id="xmm-i-shield" viewBox="0 0 24 24"><path d="M12 3 5 5.8V11c0 4.4 3 8.2 7 9.5 4-1.3 7-5.1 7-9.5V5.8z"/><path d="m9 11.8 2.1 2.1L15.2 9.8"/></symbol>
        <symbol id="xmm-i-device" viewBox="0 0 24 24"><rect x="7" y="2.5" width="10" height="19" rx="2.6"/><path d="M11 18.2h2"/></symbol>
        <symbol id="xmm-i-refresh" viewBox="0 0 24 24"><path d="M19.5 10.5A7.7 7.7 0 0 0 6 6.6L4.5 8.2M4.5 4v4.2h4.2M4.5 13.5A7.7 7.7 0 0 0 18 17.4l1.5-1.6m0 4.2v-4.2h-4.2"/></symbol>
        <symbol id="xmm-i-lock" viewBox="0 0 24 24"><rect x="5" y="10.5" width="14" height="10" rx="2.4"/><path d="M8.5 10.5V8a3.5 3.5 0 0 1 7 0v2.5"/></symbol>
        <symbol id="xmm-i-arrow" viewBox="0 0 24 24"><path d="M5 12h13.5M13.5 7l5 5-5 5"/></symbol>
    </svg>

    <a class="xmm-skip" href="#xmm-main">{{ __('maintenance.skip') }}</a>

    <aside class="xmm-help" aria-labelledby="xmm-help-title">
        <div class="xmm-wrap xmm-help__in">
            <div class="xmm-help__text">
                <p class="xmm-help__title" id="xmm-help-title"><svg class="xmm-i" aria-hidden="true"><use href="#xmm-i-heart"/></svg>{{ __('maintenance.always_available.title') }}</p>
                <p class="xmm-help__sub">{{ $helpText }}</p>
            </div>
            <nav class="xmm-help__actions" aria-label="{{ __('maintenance.always_available.nav') }}">
                <a class="xmm-btn xmm-btn--sos" href="{{ $sosUrl }}"><svg class="xmm-i" aria-hidden="true"><use href="#xmm-i-sos"/></svg>{{ __('maintenance.always_available.sos') }}</a>
                <a class="xmm-btn xmm-btn--report" href="{{ $reportUrl }}"><svg class="xmm-i" aria-hidden="true"><use href="#xmm-i-flag"/></svg>{{ __('maintenance.always_available.report') }}</a>
                <a class="xmm-btn xmm-btn--help" href="{{ $urgencesUrl }}"><svg class="xmm-i" aria-hidden="true"><use href="#xmm-i-phone"/></svg>{{ __('maintenance.always_available.emergency') }}</a>
            </nav>
        </div>
    </aside>

    <header class="xmm-top xmm-wrap">
        <span class="xmm-logo">@include('maintenance.logo')</span>
    </header>

    {{-- Aperçu intégré à l'administration (`embedded`) : simple bloc, la page hôte a déjà son <main>. --}}
    <{{ $mainTag }} class="xmm-wrap xmm-main" id="xmm-main" tabindex="-1">
        <section class="xmm-hero" aria-labelledby="xmm-title">
            <div class="xmm-hero__text">
                <p class="xmm-eyebrow">@unless ($generic)<span class="xmm-pulse" aria-hidden="true"></span>@endunless{{ $eyebrow }}</p>
                <h1 class="xmm-title" id="xmm-title">{{ $titleLead }} <span class="xmm-mark">{{ $titleMark }}</span>.</h1>
                <p class="xmm-lead">{{ $message }}</p>

                @if ($disconnected)
                    <p class="xmm-note" role="status"><svg class="xmm-i" aria-hidden="true"><use href="#xmm-i-lock"/></svg><span>{{ __('maintenance.disconnected') }}</span></p>
                @endif

                <section class="xmm-card xmm-status" aria-labelledby="xmm-status-title">
                    <h2 class="xmm-vh" id="xmm-status-title">{{ __('maintenance.status.title') }}</h2>
                    @unless ($generic)
                        <dl class="xmm-status__grid">
                            <div class="xmm-status__cell">
                                <dt>{{ __('maintenance.status.ends') }}</dt>
                                <dd>
                                    <strong class="xmm-status__big" data-xmm-ends>{{ $endsTime ?? __('maintenance.status.soon') }}</strong>
                                    @if ($endsDay)<span class="xmm-status__sub" data-xmm-ends-day>{{ $endsDay }}</span>@endif
                                </dd>
                            </div>
                            @if ($endsIso)
                                {{-- Compte à rebours visuel : la phrase en clair et la zone aria-live portent déjà l'information. --}}
                                <div class="xmm-status__cell xmm-countdown xmm-js-only" data-xmm-countdown aria-hidden="true">
                                    <dt>{{ __('maintenance.status.remaining') }}</dt>
                                    <dd><strong class="xmm-status__big xmm-digits" data-xmm-remaining>--:--</strong></dd>
                                </div>
                            @elseif ($startsTime)
                                <div class="xmm-status__cell">
                                    <dt>{{ __('maintenance.status.started') }}</dt>
                                    <dd><strong class="xmm-status__big">{{ $startsTime }}</strong></dd>
                                </div>
                            @endif
                        </dl>
                        @if ($progress !== null)
                            <div class="xmm-progress" role="progressbar" aria-label="{{ __('maintenance.status.progress') }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progress }}" data-xmm-bar style="--xmm-p: {{ round($progress / 100, 3) }}"><span></span></div>
                        @endif
                    @endunless
                    <p class="xmm-status__text"><span data-xmm-until>{{ $untilText }}</span>@if ($sinceText) <span class="xmm-status__since">{{ $sinceText }}</span>@endif</p>
                    <div class="xmm-status__foot">
                        @unless ($generic)
                            <p class="xmm-check"><span class="xmm-dot" aria-hidden="true"></span><span class="xmm-js-only">{{ __('maintenance.status.check') }}</span><span class="xmm-nojs-only">{{ __('maintenance.status.check_nojs') }}</span></p>
                        @endunless
                        <a class="xmm-btn xmm-btn--ghost" href="{{ $reloadUrl ?? '#' }}"><svg class="xmm-i" aria-hidden="true"><use href="#xmm-i-refresh"/></svg>{{ __('maintenance.refresh') }}</a>
                    </div>
                    <p class="xmm-vh" aria-live="polite" data-xmm-announce></p>
                </section>
            </div>
            <figure class="xmm-hero__art">@include('maintenance.illustration')</figure>
        </section>

        <div @class(['xmm-grid', 'xmm-grid--single' => $generic])>
            <section class="xmm-card xmm-numbers" aria-labelledby="xmm-numbers-title">
                <h2 class="xmm-h2" id="xmm-numbers-title">{{ __('maintenance.always_available.numbers_title') }}</h2>
                <p class="xmm-muted">{{ __('maintenance.always_available.numbers_text') }}</p>
                @if ($numbers !== [])
                    <ul class="xmm-numbers__list">
                        @foreach ($numbers as $entry)
                            <li>
                                {{-- Nom accessible « Appeler Police secours 117 » : contient le texte visible (WCAG 2.5.3). --}}
                                <a class="xmm-number" href="{{ $entry['tel'] }}">
                                    <span class="xmm-vh">{{ __('maintenance.always_available.call_prefix') }} </span>
                                    <span class="xmm-number__label">{{ $entry['label'] }}</span>
                                    <strong class="xmm-number__value">{{ $entry['number'] }}</strong>
                                    <span class="xmm-number__icon" aria-hidden="true"><svg class="xmm-i"><use href="#xmm-i-phone"/></svg></span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <a class="xmm-link" href="{{ $urgencesUrl }}">{{ __('maintenance.always_available.all_contacts') }}<svg class="xmm-i" aria-hidden="true"><use href="#xmm-i-arrow"/></svg></a>
            </section>

            @unless ($generic)
                <section class="xmm-card xmm-reassure" aria-labelledby="xmm-reassure-title">
                    <h2 class="xmm-h2" id="xmm-reassure-title">{{ __('maintenance.reassure.title') }}</h2>
                    <ul class="xmm-reassure__list">
                        <li><span class="xmm-badge" aria-hidden="true"><svg class="xmm-i"><use href="#xmm-i-shield"/></svg></span><p><strong>{{ __('maintenance.reassure.data_title') }}</strong> {{ __('maintenance.reassure.data_text') }}</p></li>
                        <li><span class="xmm-badge" aria-hidden="true"><svg class="xmm-i"><use href="#xmm-i-device"/></svg></span><p><strong>{{ __('maintenance.reassure.drafts_title') }}</strong> {{ __('maintenance.reassure.drafts_text') }}</p></li>
                        <li><span class="xmm-badge" aria-hidden="true"><svg class="xmm-i"><use href="#xmm-i-heart"/></svg></span><p><strong>{{ __('maintenance.reassure.crisis_title') }}</strong> {{ __('maintenance.reassure.crisis_text') }}</p></li>
                    </ul>
                </section>
            @endunless
        </div>
    </{{ $mainTag }}>

    <footer class="xmm-wrap xmm-footer">
        <div class="xmm-foot">
            <p>© Xomiton · {{ __('maintenance.tagline') }}</p>
            <a class="xmm-admin" href="{{ route('connexion', absolute: false) }}"><svg class="xmm-i" aria-hidden="true"><use href="#xmm-i-lock"/></svg>{{ __('maintenance.admin_login') }}</a>
        </div>
    </footer>
</div>
