{{-- /mot-de-passe-oublie (prototype forgot-password.php, Q8 c) : réponse identique quel que soit le compte. --}}
<x-layouts.auth :seo="['title' => __('auth.forgot.title')]" image="sign-in.jpg" :max-width="440">
    <h1 class="h2 fw-bold">{{ __('auth.forgot.heading') }}</h1>
    <p class="text-body-secondary">{{ __('auth.forgot.intro') }}</p>

    @include('auth.partials.status')
    <x-error-summary />

    <form method="post" action="{{ route('mot-de-passe-oublie.store') }}" novalidate>
        @csrf
        <x-field name="email" type="email" :label="__('auth.forgot.email')" autocomplete="email" inputmode="email" maxlength="190" required autofocus class="form-control-lg" />
        <div class="d-grid"><x-button type="submit" size="lg">{{ __('auth.forgot.submit') }}</x-button></div>
    </form>

    <p class="text-center mt-4 mb-0"><a href="{{ route('connexion') }}">← {{ __('auth.forgot.back') }}</a></p>
</x-layouts.auth>
