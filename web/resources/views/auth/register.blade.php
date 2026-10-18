{{-- /inscription — phase 1 « Compte », la seule obligatoire (prototype sign-up.php, D4, Q12 c, Q30 a). --}}
<x-layouts.auth :seo="\App\Http\Controllers\Auth\Support\RegistrationSteps::seo('account')" image="hero-12.jpg" :max-width="520">
    <x-slot:side>@include('auth.partials.register-side')</x-slot:side>

    <h1 class="h2 fw-bold">{{ __('auth.register.heading') }}</h1>
    <p class="text-body-secondary">{{ __('auth.register.intro') }} <a href="{{ route('connexion') }}" class="fw-bold">{{ __('auth.register.sign_in') }}</a></p>

    @include('auth.partials.stepper')
    @include('auth.partials.status')
    <x-error-summary />

    <form method="post" action="{{ route('inscription.store') }}" novalidate data-xm-auth-form>
        @csrf
        <h2 class="h4 mb-3">{{ __('auth.register.account.heading') }}</h2>
        <x-field name="pseudo" :label="__('auth.register.account.pseudo')" :hint="__('auth.register.account.pseudo_hint')" autocomplete="username" maxlength="40" required autofocus />
        <x-field name="email" type="email" :label="__('auth.register.account.email')" :hint="__('auth.register.account.email_hint')" autocomplete="email" inputmode="email" maxlength="190" required />
        <x-field name="password" type="password" :label="__('auth.register.account.password')" :hint="__('auth.register.account.password_hint')" autocomplete="new-password" required data-password-meter="#pwMeter" aria-describedby="pwMeter-rules" />
        @include('auth.partials.password-meter', ['id' => 'pwMeter'])

        <fieldset class="mt-4">
            <legend class="visually-hidden">{{ __('auth.register.steps.consents') }}</legend>
            <div class="form-check mb-2 xm-field">
                <input class="form-check-input {{ $errors->has('cgu') ? 'is-invalid' : '' }}" type="checkbox" id="f-cgu" name="cgu" value="1" @checked(old('cgu')) @if ($errors->has('cgu')) aria-invalid="true" aria-describedby="f-cgu-error" @endif>
                @php($newTab = '<span class="visually-hidden"> '.e(__('microcopy.a11y.opens_new_tab')).'</span>')
                <label class="form-check-label" for="f-cgu">{!! __('auth.register.account.cgu', [
                    'terms' => '<a href="'.route('confidentialite').'#conditions" target="_blank" rel="noopener">'.e(__('auth.register.account.cgu_terms')).$newTab.'</a>',
                    'charter' => '<a href="'.route('confidentialite').'#charte" target="_blank" rel="noopener">'.e(__('auth.register.account.cgu_charter')).$newTab.'</a>',
                ]) !!}</label>
                @error('cgu')<div id="f-cgu-error" class="invalid-feedback d-block"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>{{ $message }}</div>@enderror
            </div>
            <div class="form-check mb-3 xm-field">
                <input class="form-check-input {{ $errors->has('privacy') ? 'is-invalid' : '' }}" type="checkbox" id="f-privacy" name="privacy" value="1" @checked(old('privacy')) @if ($errors->has('privacy')) aria-invalid="true" aria-describedby="f-privacy-error" @endif>
                <label class="form-check-label" for="f-privacy">{!! __('auth.register.account.privacy', [
                    'policy' => '<a href="'.route('confidentialite').'#confidentialite" target="_blank" rel="noopener">'.e(__('auth.register.account.privacy_policy')).$newTab.'</a>',
                ]) !!}</label>
                @error('privacy')<div id="f-privacy-error" class="invalid-feedback d-block"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>{{ $message }}</div>@enderror
            </div>
        </fieldset>

        <div class="d-flex justify-content-between align-items-center mt-4">
            <a class="btn btn-light" href="{{ route('connexion') }}">{{ __('common.back') }}</a>
            <x-button type="submit">{{ __('auth.register.account.submit') }}</x-button>
        </div>
    </form>
</x-layouts.auth>
