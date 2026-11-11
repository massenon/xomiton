{{--
    Remerciement après l'inscription (P4-07, vous, noindex) : même page pour toute demande valide, que l'adresse soit
    nouvelle, en attente ou déjà abonnée (anti-énumération) ; aucune adresse affichée.
--}}
<x-layouts.public :seo="$seo">
    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6">
                    <div class="card card-body p-4 p-sm-5 text-center" role="status">
                        <i class="bi bi-envelope-check text-success display-5 mb-2" aria-hidden="true"></i>
                        <h1 class="h3">{{ __('newsletter.public.subscribed_title') }}</h1>
                        <p class="text-secondary">{{ __('newsletter.public.subscribed') }}</p>
                        <p class="small text-secondary">{{ __('newsletter.public.thanks_hint') }}</p>
                        <div><a class="btn btn-primary" href="{{ route('home') }}">{{ __('newsletter.public.back_home') }}</a></div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-layouts.public>
