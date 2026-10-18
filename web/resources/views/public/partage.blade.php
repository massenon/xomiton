{{--
    Lien de partage d'une publication /p/{ulid} (P7-02, Q31 b ; vous, noindex) : page d'atterrissage neutre, sans aucun
    contenu ni pseudonyme ; « Se connecter » renvoie au détail /fil/{ulid} après connexion (`?next=`).
    Variables : seo, loginUrl, registerUrl.
--}}
<x-layouts.public :seo="$seo">
    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6">
                    <div class="card card-body p-4 p-sm-5 text-center">
                        <i class="bi bi-chat-heart text-primary display-5 mb-2" aria-hidden="true"></i>
                        <h1 class="h3">{{ __('pages/partage.title') }}</h1>
                        <p class="text-secondary">{{ __('pages/partage.lead') }}</p>
                        <p class="small text-secondary"><i class="bi bi-shield-lock me-1" aria-hidden="true"></i>{{ __('pages/partage.privacy') }}</p>
                        <div class="d-grid d-sm-flex justify-content-sm-center gap-2 mt-2">
                            <x-button :href="$loginUrl" icon="box-arrow-in-right">{{ __('pages/partage.login') }}</x-button>
                            <x-button :href="$registerUrl" variant="outline" icon="person-plus">{{ __('pages/partage.register') }}</x-button>
                        </div>
                        <hr class="my-4">
                        <p class="small mb-2">{{ __('pages/partage.help') }}</p>
                        <div class="d-flex flex-wrap justify-content-center gap-3 small">
                            <a href="{{ route('signaler') }}"><i class="bi bi-megaphone me-1" aria-hidden="true"></i>{{ __('pages/partage.help_report') }}</a>
                            <a href="{{ route('urgences') }}"><i class="bi bi-telephone me-1" aria-hidden="true"></i>{{ __('pages/partage.help_urgences') }}</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-layouts.public>
