{{--
    CTA assistant (FRONT_END_SECTION_ORDER_INSTRUCTION.md : la liste des ressources finit par le CTA assistant) : ouvre
    l'offcanvas public <x-assistant-offcanvas> (P4-01, sans bouton flottant ici). Promesse requalifiée (Q36, Q55) :
    l'assistant répond 24 h/24 mais n'enregistre rien ; la réponse humaine passe par « Exprimer un besoin ». Visiteur·se :
    « Créer un compte » en contour (un seul bouton plein par section, CTA_LIBRARY.md). Aucun rouge.
--}}
<section class="card card-body xm-res-cta" id="assistant" aria-labelledby="assistant-titre">
    <div class="d-flex flex-wrap align-items-center gap-3">
        <span class="icon-lg rounded-circle text-white bg-primary flex-shrink-0" aria-hidden="true"><i class="bi bi-robot" aria-hidden="true"></i></span>
        <div class="flex-grow-1">
            <h2 class="h5 mb-1" id="assistant-titre">{{ __('pages/ressources.assistant_title') }}</h2>
            <p class="small text-secondary mb-0">{{ __('pages/ressources.assistant_text') }} <a href="{{ route('signaler', ['tab' => 'besoin']) }}">{{ __('pages/ressources.assistant_need') }}</a></p>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="offcanvas" data-bs-target="#offcanvasChat" aria-controls="offcanvasChat"><i class="bi bi-chat-heart-fill me-1" aria-hidden="true"></i>{{ __('buttons.help.chat_assistant') }}</button>
    </div>
    @guest
        <hr class="my-3">
        <p class="small text-secondary mb-2">{{ __('pages/ressources.signup_text') }}</p>
        <a class="btn btn-sm btn-outline-primary" href="{{ route('inscription') }}">{{ __('buttons.discovery.sign_up') }}</a>
    @endguest
</section>
