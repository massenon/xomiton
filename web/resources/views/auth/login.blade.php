{{-- /connexion (prototype sign-in.php, LOGIN §3, Q13 b, Q36 : tutoiement). Page publique indexée (seo.pages.connexion) ; pas de rouge hors erreurs (lien Urgences neutre). --}}
<x-layouts.auth :seo="['title' => __('seo.pages.connexion.title'), 'description' => __('seo.pages.connexion.description'), 'index' => true]" image="sign-in.jpg" :max-width="440">
    <h1 class="h2 fw-bold">{{ __('auth.login.heading') }}</h1>
    <p class="text-body-secondary">{{ __('auth.login.intro') }}</p>

    @include('auth.partials.status')
    <x-error-summary />

    <form method="post" action="{{ route('connexion.store') }}" novalidate data-xm-auth-form>
        @csrf
        @if ($next)<input type="hidden" name="next" value="{{ $next }}">@endif
        <x-field name="identifier" :label="__('auth.login.identifier')" type="text" autocomplete="username" required autofocus class="form-control-lg" />
        <x-field name="password" :label="__('auth.login.password')" type="password" autocomplete="current-password" required class="form-control-lg" />
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="form-check mb-0">
                <input type="checkbox" class="form-check-input" id="f-remember" name="remember" value="1" @checked(old('remember'))>
                <label class="form-check-label" for="f-remember">{{ __('auth.login.remember') }}</label>
            </div>
            <a href="{{ route('mot-de-passe-oublie') }}" class="small">{{ __('auth.login.forgot') }}</a>
        </div>
        <div class="d-grid"><x-button type="submit" size="lg">{{ __('auth.login.submit') }}</x-button></div>
    </form>

    <p class="text-center mt-4 mb-2">{{ __('auth.login.no_account') }} <a href="{{ route('inscription') }}" class="fw-bold">{{ __('auth.login.create_account') }}</a></p>
    <p class="text-center small"><a href="{{ route('signaler') }}"><i class="bi bi-megaphone me-1" aria-hidden="true"></i>{{ __('auth.login.report_without_account') }}</a> · <a href="{{ route('urgences') }}"><i class="bi bi-telephone me-1" aria-hidden="true"></i>{{ __('auth.login.emergencies') }}</a></p>
</x-layouts.auth>
