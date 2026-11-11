{{--
    Encart « Participer à un événement » (visiteur·ses, P8-04) : « Je participe », l'adresse exacte et le rappel de la
    veille sont réservés aux comptes ; liens vers la connexion (`$loginUrl` : retour sur la page après connexion) et
    l'inscription.
--}}
<section class="card card-body" aria-labelledby="evenements-rejoindre-titre">
    <h2 class="h6" id="evenements-rejoindre-titre"><i class="bi bi-calendar-check me-1" aria-hidden="true"></i>{{ __('pages/evenements.join_title') }}</h2>
    <p class="small text-secondary">{{ __('pages/evenements.join_text') }}</p>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-primary btn-sm" href="{{ $loginUrl ?? route('connexion') }}">{{ __('pages/evenements.join_login') }}</a>
        <a class="btn btn-light btn-sm" href="{{ route('inscription') }}">{{ __('pages/evenements.join_register') }}</a>
    </div>
    <p class="small text-secondary mt-3 mb-0">{{ __('pages/evenements.members_more') }}</p>
</section>
