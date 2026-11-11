{{-- Phase 4 « Consentements » (facultative) : données sensibles, newsletter — cases distinctes, jamais pré-cochées. --}}
<x-layouts.auth :seo="\App\Http\Controllers\Auth\Support\RegistrationSteps::seo($step)" image="hero-12.jpg" :max-width="520" compact decor>
    <x-slot:side>@include('auth.partials.register-side')</x-slot:side>

    <h1 class="h3 fw-bold mb-2">{{ __('auth.register.heading') }}</h1>
    @include('auth.partials.stepper')
    @include('auth.partials.verify-banner')
    @include('auth.partials.status')
    <x-error-summary />

    <form method="post" action="{{ route('inscription.consentements.store') }}" novalidate>
        @csrf
        <h2 class="h5 mb-1">{{ __('auth.register.consents.heading') }}</h2>
        <p class="text-body-secondary small mb-2">{{ __('auth.register.consents.intro') }}</p>

        <x-field name="sensitive_data" type="checkbox" :label="__('auth.register.consents.sensitive_data')" :value="false" />
        <x-field name="newsletter" type="checkbox" :label="__('auth.register.consents.newsletter')" :value="false" />

        <div class="xm-auth-actions">
            <a class="btn btn-light" href="{{ route('inscription.securite') }}">{{ __('common.back') }}</a>
            <button type="submit" class="btn btn-link text-decoration-none xm-auth-actions__skip" form="skip-consents">{{ __('auth.register.skip_step') }}</button>
            <x-button type="submit" class="xm-auth-actions__primary">{{ __('auth.register.finish') }}</x-button>
        </div>
    </form>
    <form id="skip-consents" method="post" action="{{ route('inscription.passer', ['etape' => 'consents']) }}" class="d-none">@csrf</form>
</x-layouts.auth>
