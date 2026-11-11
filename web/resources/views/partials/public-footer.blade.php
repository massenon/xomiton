{{-- Pied de page public (prototype partials/public-footer.php + inc/x_newsletter.php nl_footer_form → x-newsletter-form, P4-07). --}}
<footer class="xm-pub-footer text-white pt-5 pb-3 mt-0 no-print">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <x-logo variant="mono-blanc" :width="156" :link="false" class="d-block mb-3" />
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
                <x-newsletter-form />
            </div>
        </div>
        <hr class="border-light opacity-25 my-4">
        <div class="d-flex flex-column flex-md-row justify-content-between small opacity-75 gap-2">
            <span>{{ __('footer.copyright', ['year' => now()->year]) }}</span>
            <span class="d-flex flex-wrap gap-3 xm-flinks">
                <a href="{{ route('confidentialite') }}#charte">{{ __('footer.charter') }}</a>
                <a href="{{ route('legal.privacy') }}">{{ __('footer.privacy') }}</a>
                <a href="{{ route('legal.terms') }}">{{ __('footer.terms') }}</a>
                <a href="{{ route('legal.notice') }}">{{ __('footer.legal') }}</a>
                <a href="{{ route('legal.cookies') }}">{{ __('footer.cookies') }}</a>
                <a href="{{ route('account-deletion.show') }}">{{ __('footer.delete_account') }}</a>
            </span>
        </div>
    </div>
</footer>
