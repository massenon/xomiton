{{-- Inscriptions fermées par l'administration (FR-AUTH-06, Q26) : message configuré (≤ 300 car.), newsletter, connexion. --}}
<x-layouts.auth :seo="['title' => __('auth.register.title')]" image="hero-12.jpg" :max-width="520">
    <x-slot:side>@include('auth.partials.register-side')</x-slot:side>

    <div class="text-center py-3" data-xm-registrations-closed>
        <div class="display-3 text-primary"><i class="bi bi-door-closed" aria-hidden="true"></i></div>
        <h1 class="h3 fw-bold mt-3">{{ __('auth.register.closed.title') }}</h1>
        <p class="text-body-secondary">{{ $message }}</p>
        @if ($reopensAt)
            <p class="fw-bold">{{ __('auth.register.closed.reopen', ['date' => \App\Support\LocaleFormatter::make()->longDate($reopensAt)]) }}</p>
        @endif
    </div>

    <div class="card">
        <div class="card-body">
            <h2 class="h5 fw-bold">{{ __('auth.register.closed.newsletter_title') }}</h2>
            <p class="small text-body-secondary">{{ __('auth.register.closed.newsletter_text') }}</p>
            <form method="post" action="{{ route('newsletter.subscribe') }}" class="d-flex flex-column flex-sm-row gap-2" novalidate>
                @csrf
                <div class="flex-grow-1">
                    <label class="visually-hidden" for="f-newsletter-email">{{ __('auth.register.closed.newsletter_email') }}</label>
                    <input class="form-control" id="f-newsletter-email" name="email" type="email" autocomplete="email" inputmode="email" required placeholder="{{ __('auth.register.closed.newsletter_email') }}">
                </div>
                <x-button type="submit" icon="envelope">{{ __('auth.register.closed.newsletter_submit') }}</x-button>
            </form>
        </div>
    </div>

    <p class="text-center mt-4 mb-0"><a href="{{ route('connexion') }}" class="fw-bold">{{ __('auth.register.closed.sign_in') }}</a></p>
</x-layouts.auth>
