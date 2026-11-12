{{--
    Page publique d'une conversation en BD `/conversations-bd/{slug}` (P20-06, ConversationsBdShowController). Indexable,
    vous. Ordre : fil d'Ariane → badge + h1 + résumé (thème, public) → avertissement de contenu (lien SOS) → visionneuse
    (repliée en mode discret pour une personne connectée qui l'a choisi) → fin de lecture : « À retenir » (message clé),
    « Besoin d'aide ? » (Signaler, SOS, urgences, centre d'aide), « Animer une séance » (mode séance, questions) ; colonne :
    autres conversations. Impression : cases seules (no-print sur le reste de l'interface).
    P20-08 / P20-09 (lot B) : auteur·rice d'une création (« Anonyme » ou pseudonyme), « Télécharger » (PDF serveur, PNG
    produits dans le navigateur), « Signaler cette conversation » pour une création d'un·e membre.
    Variables : $conversation, $panels, $names, $others, $catalog, $hideImages, $breadcrumb, $seo.
--}}
<x-layouts.public :seo="$seo">
    @if (! $hideImages && ($panels[0]['image'] ?? null))
        <x-slot:head>
            <link rel="preload" as="image" href="{{ $panels[0]['image'] }}" fetchpriority="high">
        </x-slot:head>
    @endif

    <div class="container py-4 py-sm-5">
        <div class="row g-4">
            <div class="col-lg-8 vstack gap-4">
                <x-breadcrumb :items="$breadcrumb" class="no-print" />
                <article class="vstack gap-3" aria-labelledby="bdc-titre">
                    <header class="vstack gap-2">
                        <p class="mb-0"><x-badge color="violet" soft icon="chat-square-quote">{{ __('pages/conversations_bd.kicker') }}</x-badge></p>
                        <h1 class="h3 mb-0" id="bdc-titre">{{ $conversation->title }}</h1>
                        @if (trim((string) $conversation->summary) !== '')
                            <p class="lead fs-6 mb-0">{{ $conversation->summary }}</p>
                        @endif
                        @if ($author = $conversation->authorLabel())
                            <p class="small mb-0"><x-badge color="secondary" soft icon="person">{{ __('pages/conversations_bd.member_badge') }}</x-badge> <span class="ms-1">{{ __('pages/conversations_bd.author_line', ['name' => $author]) }}</span></p>
                        @endif
                        <p class="small text-secondary mb-0">
                            @if ($conversation->theme){{ __('pages/conversations_bd.theme', ['theme' => $conversation->theme]) }}@endif
                            @if ($conversation->theme && $conversation->audience) · @endif
                            @if ($conversation->audience){{ __('pages/conversations_bd.audience', ['audience' => $conversation->audience]) }}@endif
                        </p>
                    </header>

                    @if ($conversation->content_warning)
                        <x-alert type="warning" icon="exclamation-triangle" role="none" class="mb-0">
                            {{ $conversation->content_warning }}
                            <a href="{{ route('sos') }}" class="alert-link ms-1">{{ __('pages/conversations_bd.warning_sos_link') }}</a>
                        </x-alert>
                    @endif

                    @if ($hideImages)
                        <details class="card">
                            <summary class="card-body fw-semibold">
                                <i class="bi bi-eye-slash me-1" aria-hidden="true"></i>{{ __('pages/conversations_bd.discreet_title') }} — {{ __('pages/conversations_bd.discreet_show') }}
                                <span class="d-block small fw-normal text-secondary">{{ __('pages/conversations_bd.discreet_text') }}</span>
                            </summary>
                            <div class="card-body pt-0">@include('public.conversations-bd.viewer', ['panels' => $panels, 'names' => $names, 'title' => $conversation->title, 'id' => 'conversation'])</div>
                        </details>
                    @else
                        @include('public.conversations-bd.viewer', ['panels' => $panels, 'names' => $names, 'title' => $conversation->title, 'id' => 'conversation'])
                    @endif

                    @if ($conversation->key_message)
                        <section class="card border-0 bg-primary-subtle" aria-labelledby="bdc-retenir-titre">
                            <div class="card-body vstack gap-1">
                                <h2 class="h5 mb-0" id="bdc-retenir-titre"><i class="bi bi-lightbulb me-1" aria-hidden="true"></i>{{ __('pages/conversations_bd.key_message_title') }}</h2>
                                <p class="mb-0 fw-semibold">{{ $conversation->key_message }}</p>
                            </div>
                        </section>
                    @endif

                    @include('public.conversations-bd.export', ['conversation' => $conversation, 'root' => '#conversation'])

                    <section class="card border-primary no-print" aria-labelledby="bdc-aide-titre">
                        <div class="card-body vstack gap-2">
                            <h2 class="h5 mb-0" id="bdc-aide-titre">{{ __('pages/conversations_bd.help_title') }}</h2>
                            <p class="mb-0 text-secondary">{{ __('pages/conversations_bd.help_text') }}</p>
                            <div class="d-flex flex-wrap gap-2">
                                <a class="btn btn-primary" href="{{ route('signaler') }}"><i class="bi bi-megaphone me-1" aria-hidden="true"></i>{{ __('pages/conversations_bd.help_report') }}</a>
                                <a class="btn btn-primary-soft" href="{{ route('sos') }}"><i class="bi bi-broadcast me-1" aria-hidden="true"></i>{{ __('pages/conversations_bd.help_sos') }}</a>
                                <a class="btn btn-primary-soft" href="{{ route('urgences') }}"><i class="bi bi-telephone-fill me-1" aria-hidden="true"></i>{{ __('pages/conversations_bd.help_emergency') }}</a>
                                <a class="btn btn-primary-soft" href="{{ route('aide.index') }}"><i class="bi bi-life-preserver me-1" aria-hidden="true"></i>{{ __('pages/conversations_bd.help_center') }}</a>
                            </div>
                        </div>
                    </section>

                    <section class="card no-print" aria-labelledby="bdc-seance-titre">
                        <div class="card-body vstack gap-2">
                            <h2 class="h5 mb-0" id="bdc-seance-titre">{{ __('pages/conversations_bd.seance_title') }}</h2>
                            <p class="mb-0 text-secondary">{{ __('pages/conversations_bd.seance_text') }}</p>
                            @if ($conversation->questions() !== [])
                                <h3 class="h6 mb-0 mt-2">{{ __('pages/conversations_bd.questions_title') }}</h3>
                                <ol class="mb-0">
                                    @foreach ($conversation->questions() as $question)
                                        <li>{{ $question }}</li>
                                    @endforeach
                                </ol>
                            @endif
                            <p class="mb-0 mt-1"><a class="btn btn-outline-primary" href="{{ route('conversations-bd.seance', ['slug' => $conversation->slug]) }}" rel="nofollow"><i class="bi bi-easel me-1" aria-hidden="true"></i>{{ __('pages/conversations_bd.seance_open') }}</a></p>
                        </div>
                    </section>
                    @if ($conversation->isMemberCreation())
                        @include('public.conversations-bd.report', ['conversation' => $conversation])
                    @endif
                </article>
            </div>

            <aside class="col-lg-4 no-print" aria-label="{{ __('pages/conversations_bd.others_title') }}">
                <div class="vstack gap-4">
                    @if ($others->isNotEmpty())
                        <section aria-labelledby="bdc-autres-titre">
                            <h2 class="h5" id="bdc-autres-titre">{{ __('pages/conversations_bd.others_title') }}</h2>
                            <ul class="list-unstyled vstack gap-3 mb-0">
                                @foreach ($others as $other)
                                    <li>@include('public.conversations-bd.card', ['conversation' => $other, 'heading' => 'h3'])</li>
                                @endforeach
                            </ul>
                        </section>
                    @endif
                    <p class="small mb-0"><a href="{{ route('conversations-bd.index') }}">{{ __('pages/conversations_bd.all_conversations') }}</a></p>
                    <p class="small mb-0"><a href="{{ route('bd.index') }}">{{ __('pages/conversations_bd.comics_link') }}</a></p>
                </div>
            </aside>
        </div>
    </div>
</x-layouts.public>
