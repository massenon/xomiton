{{--
    Article du centre d'aide `/aide/{slug}` (P4-05, AideShowController ; prototype_social/help-details.php). Public et
    indexable, gabarit public (Q18 a), vous. Fil d'Ariane terminé par la question (le prototype : « Article {id} »),
    badge de catégorie, réponse en chapeau, « À savoir » de la catégorie, vote « Cet article vous a-t-il aidé·e ? »
    (help-details.php l.37-38 : toast seul ; ici persisté, plan §7) : formulaire POST + @csrf qui fonctionne sans
    JavaScript (retour #avis avec le remerciement), amélioré par resources/js/public/aide.js (envoi sans rechargement,
    remerciement à la place des boutons, focus déplacé dessus). « Non » en `btn-light` (rouge réservé à l'attention, D5).
    Colonne (sans nom propre : chaque section a son titre) : autres questions, « Besoin d'aide maintenant ? » (SOS seul
    bouton rouge plein) ; texte rouge sur fond teinté en .xm-help-attention (contraste AA). Titres h1 › h2 sans saut.
    Variables : $faq, $category, $know, $related, $breadcrumb, $feedbackThanks, $seo.
--}}
@php
    $color = in_array($category['color'], ['primary', 'danger', 'success', 'info', 'warning'], true) ? $category['color'] : 'primary';
    // Rouge #E30E0E sur fond rouge à 10 % ≈ 4,1:1 : texte en --xm-red-600 (≈ 5,3:1, aide.css .xm-help-attention).
    $badgeText = $color === 'danger' ? 'xm-help-attention' : 'text-'.$color;
@endphp
<x-layouts.public :seo="$seo" pub-nav="aide">
    <div class="container py-4 py-sm-5">
        <div class="vstack gap-4">
            <x-breadcrumb :items="$breadcrumb" />

            <div class="row g-4">
                <div class="col-lg-8">
                    <article class="card" aria-labelledby="aide-article-titre">
                        <div class="card-body p-4">
                            <a class="badge bg-{{ $color }} bg-opacity-10 {{ $badgeText }} mb-2 text-decoration-none" href="{{ route('aide.index', ['cat' => $category['slug']]) }}">@if ($category['icon'])<i class="bi {{ $category['icon'] }} me-1" aria-hidden="true"></i>@endif{{ $category['label'] }}</a>
                            <h1 class="h3" id="aide-article-titre">{{ $faq->question }}</h1>
                            <p class="lead">{{ $faq->answer }}</p>

                            @if ($know !== [])
                                <h2 class="h5 mt-4" id="aide-a-savoir">{{ __('pages/aide.know_title') }}</h2>
                                <ul aria-labelledby="aide-a-savoir">
                                    @foreach ($know as $item)
                                        <li class="mb-1">{{ $item }}</li>
                                    @endforeach
                                </ul>
                            @endif

                            <hr>
                            <form class="xm-help-feedback" id="avis" method="post" action="{{ route('aide.feedback', $faq->slug) }}" data-xm-faq-feedback
                                  data-feedback-failed="{{ __('pages/aide.feedback.failed') }}" data-feedback-expired="{{ __('errors.419.public.text') }}">
                                @csrf
                                <div class="d-flex flex-wrap align-items-center gap-3" role="group" aria-labelledby="avis-question" data-feedback-actions{{ $feedbackThanks ? ' hidden' : '' }}>
                                    <span class="small text-secondary" id="avis-question">{{ __('pages/aide.feedback.question') }}</span>
                                    <button class="btn btn-sm btn-success-soft" type="submit" name="helpful" value="1"><i class="bi bi-hand-thumbs-up me-1" aria-hidden="true"></i>{{ __('pages/aide.feedback.yes') }}</button>
                                    <button class="btn btn-sm btn-light" type="submit" name="helpful" value="0"><i class="bi bi-hand-thumbs-down me-1" aria-hidden="true"></i>{{ __('pages/aide.feedback.no') }}</button>
                                </div>
                                <p class="small text-success mb-0" tabindex="-1" data-feedback-done>{{ $feedbackThanks }}</p>
                            </form>
                        </div>
                    </article>
                </div>

                <aside class="col-lg-4 vstack gap-3">
                    @if ($related->isNotEmpty())
                        <section class="card" aria-labelledby="aide-autres-titre">
                            <div class="card-header"><h2 class="h6 mb-0" id="aide-autres-titre">{{ __('pages/aide.related_title') }}</h2></div>
                            <div class="list-group list-group-flush">
                                @foreach ($related as $other)
                                    <a class="list-group-item list-group-item-action small" href="{{ route('aide.show', $other->slug) }}">{{ $other->question }}</a>
                                @endforeach
                            </div>
                        </section>
                    @endif
                    <section class="card bg-danger bg-opacity-10 border-danger" aria-labelledby="aide-maintenant-titre">
                        <div class="card-body">
                            <h2 class="h6 xm-help-attention" id="aide-maintenant-titre">{{ __('pages/aide.now_title') }}</h2>
                            <div class="d-flex flex-wrap gap-2">
                                <a class="btn btn-danger btn-sm" href="{{ route('sos') }}">{{ __('pages/aide.danger_sos') }}</a>
                                <a class="btn btn-light btn-sm" href="{{ route('signaler') }}">{{ __('pages/aide.now_report') }}</a>
                            </div>
                        </div>
                    </section>
                </aside>
            </div>
        </div>
    </div>
</x-layouts.public>
