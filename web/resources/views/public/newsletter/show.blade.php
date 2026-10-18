{{--
    Page /newsletter (P4-07, vous ; prototype newsletter.php habillage `card card-body p-4 p-sm-5`) : présentation,
    formulaire complet (adresse, centres d'intérêt facultatifs, consentement) — le pied de page garde sa version courte.
    Publique et indexable, au sitemap. Inscription : POST newsletter.subscribe → /newsletter/merci (réponse identique).
--}}
<x-layouts.public :seo="$seo">
    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-10 col-lg-7">
                    <div class="card card-body p-4 p-sm-5">
                        <div class="text-center">
                            <i class="bi bi-envelope-paper text-primary display-5 mb-2" aria-hidden="true"></i>
                            <h1 class="h3">{{ __('newsletter.public.page_title') }}</h1>
                            <p class="text-secondary">{{ __('newsletter.public.page_lead') }}</p>
                        </div>
                        <ul class="list-unstyled small mb-4">
                            @foreach (['frequency' => 'calendar-check', 'interests' => 'tags', 'privacy' => 'shield-lock'] as $point => $icon)
                                <li class="d-flex gap-2 mb-2"><i class="bi bi-{{ $icon }} text-primary" aria-hidden="true"></i><span>{{ __('newsletter.public.page_points.'.$point) }}</span></li>
                            @endforeach
                        </ul>
                        <h2 class="h5 mb-3">{{ __('newsletter.public.form_title') }}</h2>
                        <x-newsletter-form variant="page" :interests="$interests" />
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-layouts.public>
