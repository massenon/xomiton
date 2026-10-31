{{--
    Inscription à la newsletter (P0-07 `x-newsletter-form`, P4-07 ; prototype inc/x_newsletter.php nl_footer_form, D8) :
    POST newsletter.subscribe, @csrf, pot de miel `website` masqué, consentement explicite jamais pré-coché, retour vers
    la page d'origine. Erreurs lues dans le sac `newsletter`, saisie dans old('newsletter.*'), affichées seulement par
    le formulaire envoyé (`nl_form`) : le pied de page et la page /newsletter cohabitent sans doublon d'identifiants, et
    aucun champ `email` d'une autre page ne reprend l'adresse. Erreur du pied de page focalisée au chargement (comme
    x-error-summary, ACCESSIBILITE §4). Vous (VOICE_AND_TONE.md §3).
    Props :
      variant   : footer (pied de page sombre, champ + bouton en ligne, prototype) | page (/newsletter, x-field, intérêts)
      interests : [slug => libellé] des centres d'intérêt proposés (variante page ; vide = aucun choix)
--}}
@props(['variant' => 'footer', 'interests' => []])
@php
    $isPage = $variant === 'page';
    $bag = isset($errors) ? $errors->getBag('newsletter') : new \Illuminate\Support\MessageBag();
    $old = old(\App\Http\Requests\Newsletter\SubscribeNewsletterRequest::OLD_INPUT);
    $old = is_array($old) ? $old : [];
    $mine = ($old['nl_form'] ?? null) === $variant;
    $flash = session(\App\Http\Controllers\Web\Newsletter\NewsletterController::FLASH);
    $flash = is_array($flash) && ($flash['form'] ?? 'footer') === $variant ? $flash : null;
    $emailError = $mine ? $bag->first('email') : null;
    $consentError = $mine ? $bag->first('consent') : null;
    $interestsError = $mine ? ($bag->first('interests') ?: $bag->first('interests.*')) : null;
    $p = $isPage ? 'nlPage' : 'nl';
    $privacyUrl = route('confidentialite').'#confidentialite';
@endphp
@if ($isPage)
    <form {{ $attributes->merge(['id' => 'inscription'])->class('xm-nl-form xm-nl-form--page') }} method="post" action="{{ route('newsletter.subscribe') }}" novalidate>
        @csrf
        <input type="hidden" name="back" value="{{ route('newsletter.show', [], false) }}">
        <input type="hidden" name="nl_form" value="page">
        <div class="visually-hidden" aria-hidden="true"><label for="{{ $p }}Website">{{ __('footer.newsletter_honeypot') }}</label><input id="{{ $p }}Website" name="website" tabindex="-1" autocomplete="off"></div>
        @if ($flash && ($flash['ok'] ?? false))
            <x-alert type="success">{{ $flash['message'] ?? '' }}</x-alert>
        @elseif ($flash)
            <x-alert type="danger" id="{{ $p }}Flash" tabindex="-1" x-data x-init="$el.focus()">{{ $flash['message'] ?? '' }}</x-alert>
        @endif
        @if ($mine && $bag->any())
            <x-error-summary :messages="array_filter(['email' => $emailError, 'interests' => $interestsError, 'consent' => $consentError])" />
        @endif
        <x-field name="email" type="email" :label="__('newsletter.public.email_label')" :hint="__('newsletter.public.email_hint')" :error="$emailError" :value="$mine ? ($old['email'] ?? null) : null" autocomplete="email" inputmode="email" maxlength="190" :placeholder="__('newsletter.public.email_placeholder')" required />
        @if ($interests !== [])
            <x-field name="interests" type="checkbox-group" :label="__('newsletter.public.interests_label')" :options="$interests" :error="$interestsError" :value="$mine ? (array) ($old['interests'] ?? []) : []" optional />
        @endif
        <div class="mb-3">
            <div class="form-check">
                <input @class(['form-check-input', 'is-invalid' => $consentError]) type="checkbox" id="f-consent" name="consent" value="1" required @checked($mine && ($old['consent'] ?? false)) @if ($consentError) aria-invalid="true" aria-describedby="f-consent-error" @endif>
                <label class="form-check-label" for="f-consent">{!! __('newsletter.public.consent', ['url' => e($privacyUrl)]) !!}</label>
            </div>
            @if ($consentError)<div id="f-consent-error" class="invalid-feedback d-block"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>{{ $consentError }}</div>@endif
        </div>
        <div class="d-grid">
            <button class="btn btn-primary" type="submit"><i class="bi bi-send me-1" aria-hidden="true"></i>{{ __('newsletter.public.submit') }}</button>
        </div>
    </form>
@else
    @if ($flash)
        @if ($flash['ok'] ?? false)
            <div class="alert alert-success py-2 small mb-2" role="status">{{ $flash['message'] ?? '' }}</div>
        @else
            <div class="alert alert-danger py-2 small mb-2" role="alert" id="{{ $p }}Flash" tabindex="-1" x-data x-init="$el.focus()">{{ $flash['message'] ?? '' }}</div>
        @endif
    @endif
    @if ($mine && $bag->any())
        <div class="alert alert-danger py-2 small mb-2" role="alert" id="{{ $p }}Errors" tabindex="-1" x-data x-init="$el.focus()">
            @foreach (array_filter([$emailError, $consentError, $interestsError]) as $message)
                <span class="d-block">{{ $message }}</span>
            @endforeach
        </div>
    @endif
    @if (request()->attributes->get(\App\Http\Middleware\CacheableGuestPage::ATTRIBUTE, false))
        {{--
            Page servie sans session (CacheableGuestPage, PERFORMANCE.md §11) : pas de jeton CSRF à joindre, donc pas de
            formulaire POST ici (aucune exemption du jeton). Le bouton mène à /newsletter, page à session et à formulaire protégé.
        --}}
        <a class="btn btn-primary" href="{{ route('newsletter.show') }}#inscription"><i class="bi bi-send me-1" aria-hidden="true"></i>{{ __('footer.newsletter_subscribe') }}</a>
    @else
    <form {{ $attributes->class('xm-nl-form') }} method="post" action="{{ route('newsletter.subscribe') }}" novalidate>
        @csrf
        <input type="hidden" name="back" value="{{ request()->getRequestUri() }}">
        <input type="hidden" name="nl_form" value="footer">
        <div class="visually-hidden" aria-hidden="true"><label for="{{ $p }}Website">{{ __('footer.newsletter_honeypot') }}</label><input id="{{ $p }}Website" name="website" tabindex="-1" autocomplete="off"></div>
        <label class="visually-hidden" for="{{ $p }}Email">{{ __('footer.newsletter_email') }}</label>
        <div class="input-group">
            <input @class(['form-control', 'is-invalid' => $emailError]) type="email" id="{{ $p }}Email" name="email" value="{{ $mine ? ($old['email'] ?? '') : '' }}" placeholder="{{ __('footer.newsletter_email') }}" autocomplete="email" inputmode="email" maxlength="190" required @if ($emailError) aria-invalid="true" aria-describedby="{{ $p }}Errors" @endif>
            <button class="btn btn-primary" type="submit"><i class="bi bi-send me-1" aria-hidden="true"></i>{{ __('footer.newsletter_subscribe') }}</button>
        </div>
        <div class="form-check small mt-2">
            <input @class(['form-check-input', 'is-invalid' => $consentError]) type="checkbox" id="{{ $p }}Consent" name="consent" value="1" required @checked($mine && ($old['consent'] ?? false)) @if ($consentError) aria-invalid="true" aria-describedby="{{ $p }}Errors" @endif>
            <label class="form-check-label opacity-75" for="{{ $p }}Consent">{!! __('footer.newsletter_consent', ['url' => e($privacyUrl)]) !!}</label>
        </div>
    </form>
    @endif
@endif
