{{-- /nouveau-mot-de-passe (P2-05) : par lien signé (champs cachés) ou après le code saisi (session) ; mot de passe + confirmation + jauge. --}}
<x-layouts.auth :seo="['title' => __('seo.noindex.reset_password')]" image="sign-in.jpg" :max-width="440">
    <h1 class="h2 fw-bold">{{ __('auth.reset.heading') }}</h1>
    <p class="text-body-secondary">{{ __('auth.reset.intro') }}</p>

    @include('auth.partials.status')
    <x-error-summary />

    <form method="post" action="{{ route('reinitialisation.store') }}" novalidate>
        @csrf
        @if ($token)
            <input type="hidden" name="email" value="{{ $email }}">
            <input type="hidden" name="token" value="{{ $token }}">
        @endif
        <x-field name="password" type="password" :label="__('auth.reset.password')" :hint="__('auth.register.account.password_hint')" autocomplete="new-password" required autofocus data-password-meter="#pwMeter" aria-describedby="pwMeter-rules" />
        @include('auth.partials.password-meter', ['id' => 'pwMeter'])
        <div class="mt-3">
            <x-field name="password_confirmation" type="password" :label="__('auth.reset.password_confirmation')" autocomplete="new-password" required />
        </div>
        <div class="d-grid"><x-button type="submit" size="lg">{{ __('auth.reset.submit') }}</x-button></div>
    </form>

    <p class="text-center mt-4 mb-0"><a href="{{ route('connexion') }}">← {{ __('auth.forgot.back') }}</a></p>
</x-layouts.auth>
