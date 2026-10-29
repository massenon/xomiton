{{-- Phase 3 « Sécurité » (facultative) : contact de confiance (phone_bj, chiffré) et mode discret. --}}
<x-layouts.auth :seo="\App\Http\Controllers\Auth\Support\RegistrationSteps::seo($step)" image="hero-12.jpg" :max-width="520" compact>
    <x-slot:side>@include('auth.partials.register-side')</x-slot:side>

    <h1 class="h3 fw-bold mb-2">{{ __('auth.register.heading') }}</h1>
    @include('auth.partials.stepper')
    @include('auth.partials.verify-banner')
    @include('auth.partials.status')
    <x-error-summary />

    <form method="post" action="{{ route('inscription.securite.store') }}" novalidate>
        @csrf
        <h2 class="h5 mb-1">{{ __('auth.register.security.heading') }}</h2>
        <p class="text-body-secondary small mb-2">{{ __('auth.register.security.intro') }}</p>

        <x-field name="trusted_contact_name" :label="__('auth.register.security.trusted_contact_name')" optional :value="$profile?->trusted_contact_name" autocomplete="off" maxlength="80" />
        <x-field name="trusted_contact_phone" type="tel" :label="__('auth.register.security.trusted_contact_phone')" :hint="__('auth.register.security.trusted_contact_phone_hint')" optional
                 :value="$profile?->trusted_contact_phone" autocomplete="off" inputmode="tel" placeholder="+229 01 …" maxlength="30" />
        <x-field name="discreet_mode" type="switch" :label="__('auth.register.security.discreet_mode')" :hint="__('auth.register.security.discreet_mode_hint')" :value="(bool) ($profile?->discreet_mode ?? false)" />

        <div class="xm-auth-actions">
            <a class="btn btn-light" href="{{ route('inscription.profil') }}">{{ __('common.back') }}</a>
            <button type="submit" class="btn btn-link text-decoration-none xm-auth-actions__skip" form="skip-security">{{ __('auth.register.skip_step') }}</button>
            <x-button type="submit" class="xm-auth-actions__primary">{{ __('common.next') }}</x-button>
        </div>
    </form>
    <form id="skip-security" method="post" action="{{ route('inscription.passer', ['etape' => 'security']) }}" class="d-none">@csrf</form>
</x-layouts.auth>
