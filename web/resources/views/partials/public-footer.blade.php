{{-- Pied de page public (prototype partials/public-footer.php + inc/x_newsletter.php nl_footer_form). --}}
<footer class="xm-pub-footer text-white pt-5 pb-3 mt-0 no-print">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <img src="{{ asset('assets/images/xomiton/brand/logo-mono-blanc.svg') }}" alt="{{ __('common.logo_alt') }}" title="Xomiton" style="height:40px;width:auto" class="mb-3" width="160" height="40">
                <p class="small opacity-75 mb-3">{{ __('footer.brand_text') }}</p>
                <a class="btn btn-sm btn-danger fw-bold" href="{{ route('sos') }}"><i class="bi bi-broadcast me-1" aria-hidden="true"></i>{{ __('footer.sos_gps') }}</a>
            </div>
            <div class="col-6 col-lg-2">
                <h2 class="h6 text-white">{{ __('footer.platform') }}</h2>
                <ul class="list-unstyled small mb-0 xm-flinks">
                    <li><a href="{{ route('home') }}#valeurs">{{ __('footer.about') }}</a></li>
                    <li><a href="{{ route('aide.index') }}">{{ __('footer.support') }}</a></li>
                    <li><a href="{{ route('do-glegbe') }}">{{ __('footer.do_glegbe') }}</a></li>
                    <li><a href="{{ route('ressources.index') }}">{{ __('footer.resources') }}</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-2">
                <h2 class="h6 text-white">{{ __('footer.help_protection') }}</h2>
                <ul class="list-unstyled small mb-0 xm-flinks">
                    <li><a href="{{ route('signaler') }}">{{ __('footer.report') }}</a></li>
                    <li><a href="{{ route('urgences') }}">{{ __('footer.emergency') }}</a></li>
                    <li><a href="{{ route('aide.index') }}">{{ __('footer.help_center') }}</a></li>
                    <li><a href="{{ route('confidentialite') }}">{{ __('footer.privacy_terms') }}</a></li>
                </ul>
            </div>
            <div class="col-lg-4" id="newsletter">
                <h2 class="h6 text-white">{{ __('footer.newsletter') }}</h2>
                <p class="small opacity-75 mb-2">{{ __('footer.newsletter_text') }}</p>
                @if (session('newsletter_flash'))
                    <div class="alert alert-{{ session('newsletter_flash')['ok'] ? 'success' : 'danger' }} py-2 small mb-2" role="status">{{ session('newsletter_flash')['message'] }}</div>
                @endif
                <form class="xm-nl-form" method="post" action="{{ route('newsletter.subscribe') }}" novalidate>
                    @csrf
                    <input type="hidden" name="back" value="{{ url()->current() }}">
                    <div class="visually-hidden" aria-hidden="true"><label for="nlWebsite">{{ __('footer.newsletter_honeypot') }}</label><input id="nlWebsite" name="website" tabindex="-1" autocomplete="off"></div>
                    <label class="visually-hidden" for="nlEmail">{{ __('footer.newsletter_email') }}</label>
                    <div class="input-group">
                        <input class="form-control" type="email" id="nlEmail" name="email" placeholder="{{ __('footer.newsletter_email') }}" autocomplete="email" required>
                        <button class="btn btn-primary" type="submit"><i class="bi bi-send me-1" aria-hidden="true"></i>{{ __('footer.newsletter_subscribe') }}</button>
                    </div>
                    <div class="form-check small mt-2">
                        <input class="form-check-input" type="checkbox" id="nlConsent" name="consent" value="1" required>
                        <label class="form-check-label opacity-75" for="nlConsent">{!! __('footer.newsletter_consent', ['url' => route('confidentialite').'#confidentialite']) !!}</label>
                    </div>
                </form>
            </div>
        </div>
        <hr class="border-light opacity-25 my-4">
        <div class="d-flex flex-column flex-md-row justify-content-between small opacity-75 gap-2">
            <span>{{ __('footer.copyright', ['year' => now()->year]) }}</span>
            <span class="d-flex flex-wrap gap-3 xm-flinks">
                <a href="{{ route('confidentialite') }}#charte">{{ __('footer.charter') }}</a>
                <a href="{{ route('confidentialite') }}#confidentialite">{{ __('footer.privacy') }}</a>
                <a href="{{ route('confidentialite') }}#conditions">{{ __('footer.terms') }}</a>
            </span>
        </div>
    </div>
</footer>
