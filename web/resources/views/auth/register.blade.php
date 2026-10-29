{{-- /inscription — phase 1 « Compte », la seule obligatoire (prototype sign-up.php, D4, Q12 c, Q30 a). --}}
<x-layouts.auth :seo="\App\Http\Controllers\Auth\Support\RegistrationSteps::seo('account')" image="hero-12.jpg" :max-width="520" compact decor>
    <x-slot:side>@include('auth.partials.register-side')</x-slot:side>

    <h1 class="h3 fw-bold mb-1">{{ __('auth.register.heading') }}</h1>
    <p class="text-body-secondary small mb-2">{{ __('auth.register.intro') }} <a href="{{ route('connexion') }}" class="fw-bold">{{ __('auth.register.sign_in') }}</a></p>

    @include('auth.partials.stepper')
    @include('auth.partials.status')
    <x-error-summary />

    @if (! empty($invitationCode))
        <div class="alert alert-info d-flex gap-2" role="status"><i class="bi bi-envelope-heart-fill" aria-hidden="true"></i><div>{{ __('auth.register.invitation.banner.'.($invitationRole === 'pair' ? 'pair' : 'admin')) }}</div></div>
    @endif

    <form method="post" action="{{ route('inscription.store') }}" novalidate data-xm-auth-form>
        @csrf
        @if (! empty($invitationCode))
            <input type="hidden" name="invitation" value="{{ $invitationCode }}">
        @endif
        @error('invitation')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
        <h2 class="h5 mb-2">{{ __('auth.register.account.heading') }}</h2>
        {{-- Identité de connexion : deux champs courts côte à côte dès md ; aides d'une ligne liées par aria-describedby. --}}
        <div class="row gx-3">
            <div class="col-md-6"><x-field name="pseudo" :label="__('auth.register.account.pseudo')" :hint="__('auth.register.account.pseudo_hint_short')" autocomplete="username" maxlength="40" required autofocus /></div>
            <div class="col-md-6"><x-field name="email" type="email" :label="__('auth.register.account.email')" :hint="__('auth.register.account.email_hint_short')" autocomplete="email" inputmode="email" maxlength="190" required /></div>
        </div>
        {{-- Mot de passe + confirmation côte à côte ; jauge compacte (niveau + règles) sur une ligne dessous. --}}
        <div class="row gx-3">
            <div class="col-md-6"><x-field name="password" type="password" :label="__('auth.register.account.password')" autocomplete="new-password" required data-password-meter="#pwMeter" aria-describedby="pwMeter-rules" /></div>
            <div class="col-md-6"><x-field name="password_confirmation" type="password" :label="__('auth.register.account.password_confirmation')" autocomplete="new-password" required /></div>
        </div>
        @include('auth.partials.password-meter', ['id' => 'pwMeter', 'compact' => true])

        <fieldset class="mt-3 xm-auth-checks">
            <legend class="visually-hidden">{{ __('auth.register.steps.consents') }}</legend>
            <div class="form-check mb-1 xm-field">
                <input class="form-check-input {{ $errors->has('cgu') ? 'is-invalid' : '' }}" type="checkbox" id="f-cgu" name="cgu" value="1" @checked(old('cgu')) @if ($errors->has('cgu')) aria-invalid="true" aria-describedby="f-cgu-error" @endif>
                @php($newTab = '<span class="visually-hidden"> '.e(__('microcopy.a11y.opens_new_tab')).'</span>')
                <label class="form-check-label" for="f-cgu">{!! __('auth.register.account.cgu', [
                    'terms' => '<a href="'.route('confidentialite').'#conditions" target="_blank" rel="noopener">'.e(__('auth.register.account.cgu_terms')).$newTab.'</a>',
                    'charter' => '<a href="'.route('confidentialite').'#charte" target="_blank" rel="noopener">'.e(__('auth.register.account.cgu_charter')).$newTab.'</a>',
                ]) !!}</label>
                @error('cgu')<div id="f-cgu-error" class="invalid-feedback d-block"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>{{ $message }}</div>@enderror
            </div>
            <div class="form-check mb-1 xm-field">
                <input class="form-check-input {{ $errors->has('privacy') ? 'is-invalid' : '' }}" type="checkbox" id="f-privacy" name="privacy" value="1" @checked(old('privacy')) @if ($errors->has('privacy')) aria-invalid="true" aria-describedby="f-privacy-error" @endif>
                <label class="form-check-label" for="f-privacy">{!! __('auth.register.account.privacy', [
                    'policy' => '<a href="'.route('confidentialite').'#confidentialite" target="_blank" rel="noopener">'.e(__('auth.register.account.privacy_policy')).$newTab.'</a>',
                ]) !!}</label>
                @error('privacy')<div id="f-privacy-error" class="invalid-feedback d-block"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>{{ $message }}</div>@enderror
            </div>
            <div class="form-check mb-1 xm-field">
                <input class="form-check-input {{ $errors->has('age_confirmed') ? 'is-invalid' : '' }}" type="checkbox" id="f-age" name="age_confirmed" value="1" @checked(old('age_confirmed')) @if ($errors->has('age_confirmed')) aria-invalid="true" aria-describedby="f-age-error" @endif>
                <label class="form-check-label" for="f-age">{{ __('auth.register.account.age', ['min_age' => config('xomiton.accounts.min_age')]) }}</label>
                @error('age_confirmed')<div id="f-age-error" class="invalid-feedback d-block"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>{{ $message }}</div>@enderror
            </div>
        </fieldset>

        <div class="xm-auth-actions">
            <a class="btn btn-light" href="{{ route('connexion') }}">{{ __('common.back') }}</a>
            <x-button type="submit" class="xm-auth-actions__primary">{{ __('auth.register.account.continue') }}</x-button>
        </div>
    </form>
</x-layouts.auth>
