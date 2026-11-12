{{--
    Mode séance d'une conversation en BD `/conversations-bd/{slug}/seance` (P20-10, ConversationsBdSeanceController) pour
    les pair·es animateur·rices. Jamais indexé. Avec JavaScript (resources/js/public/bd-seance.js, xmBdSeance) : une case
    à la fois, d'abord toutes les bulles sauf les pensées, puis « Révéler les pensées » ; questions d'animation à la fin ;
    plein écran ; clavier et télécommande (→, Page suivante, Espace, Entrée : avancer ; ←, Page précédente : revenir ;
    P : pensées ; F : plein écran ; Début / Fin). Sans JavaScript : toutes les cases, pensées comprises, puis les questions.
    P20-09 : lien « PDF à imprimer (avec les questions) » pour animer sans projecteur.
    Variables : $conversation, $panels, $names, $seo.
--}}
@php
    $count = count($panels);
    $options = [
        'panels' => $count,
        'thoughts' => array_map(static fn (array $panel): bool => $panel['thoughts'], $panels),
        'texts' => [
            'counter' => __('pages/conversations_bd.seance.counter'),
            'questions' => __('pages/conversations_bd.seance.questions_counter'),
            'revealed' => __('pages/conversations_bd.seance.revealed'),
            'hidden' => __('pages/conversations_bd.seance.thoughts_hidden'),
        ],
    ];
@endphp
<x-layouts.public :seo="$seo" body-class="xm-bdc-seance-body">
    <div class="container py-3 py-sm-4">
        <section class="xm-bdc-seance vstack gap-3" id="seance" tabindex="-1" aria-labelledby="seance-titre"
                 x-data='xmBdSeance(@json($options, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE))' x-on:keydown.window="onKey($event)">
            <header class="d-flex flex-wrap align-items-center justify-content-between gap-2 xm-bdc-seance-head">
                <div>
                    <p class="mb-1"><x-badge color="violet" soft icon="easel">{{ __('pages/conversations_bd.seance.kicker') }}</x-badge></p>
                    <h1 class="h4 mb-0" id="seance-titre">{{ $conversation->title }}</h1>
                </div>
                <div class="d-flex flex-wrap gap-2 no-print">
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('conversations-bd.show', ['slug' => $conversation->slug]) }}"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ __('pages/conversations_bd.seance.back') }}</a>
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('conversations-bd.pdf', ['slug' => $conversation->slug]) }}" rel="nofollow" download><i class="bi bi-filetype-pdf me-1" aria-hidden="true"></i>{{ __('pages/conversations_bd.export.seance_pdf') }}</a>
                    <button type="button" class="btn btn-sm btn-outline-primary" x-show="enhanced" x-cloak x-on:click="toggleFullscreen()" x-bind:aria-pressed="fullscreen ? 'true' : 'false'">
                        <i class="bi bi-arrows-fullscreen me-1" aria-hidden="true"></i><span x-text="fullscreen ? @js(__('pages/conversations_bd.seance.exit_fullscreen')) : @js(__('pages/conversations_bd.seance.fullscreen'))">{{ __('pages/conversations_bd.seance.fullscreen') }}</span>
                    </button>
                </div>
            </header>
            <p class="small text-secondary mb-0 xm-bdc-seance-help" x-show="enhanced" x-cloak>{{ __('pages/conversations_bd.seance.keyboard_help') }}</p>

            <ol class="list-unstyled mb-0 vstack gap-4 xm-bdc-seance-steps">
                @foreach ($panels as $index => $panel)
                    <li class="xm-bdc-seance-step" data-xm-seance-step>
                        <x-bd.panel :bubbles="$panel['bubbles']" :names="$names" :image="$panel['image']" :alt="$panel['alt']" :n="$panel['n']" :count="$count" :title="$conversation->title" :priority="$index === 0" />
                    </li>
                @endforeach
                <li class="xm-bdc-seance-step" data-xm-seance-step>
                    <section class="card xm-bdc-seance-questions" aria-labelledby="seance-questions-titre">
                        <div class="card-body vstack gap-3">
                            <h2 class="h4 mb-0" id="seance-questions-titre">{{ __('pages/conversations_bd.seance.questions_title') }}</h2>
                            @if ($conversation->questions() === [])
                                <p class="mb-0">{{ __('pages/conversations_bd.seance.no_questions') }}</p>
                            @else
                                <ol class="mb-0 vstack gap-2 fs-5">
                                    @foreach ($conversation->questions() as $question)
                                        <li>{{ $question }}</li>
                                    @endforeach
                                </ol>
                            @endif
                            @if ($conversation->key_message)
                                <p class="mb-0 fw-semibold"><i class="bi bi-lightbulb me-1" aria-hidden="true"></i>{{ __('pages/conversations_bd.seance.key_message', ['message' => $conversation->key_message]) }}</p>
                            @endif
                            <p class="mb-0 no-print" x-show="enhanced" x-cloak><button type="button" class="btn btn-outline-primary" x-on:click="go(0)"><i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>{{ __('pages/conversations_bd.seance.restart') }}</button></p>
                        </div>
                    </section>
                </li>
            </ol>

            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 xm-bdc-seance-nav no-print" x-show="enhanced" x-cloak>
                <button type="button" class="btn btn-outline-primary" x-on:click="prev()" x-bind:disabled="isFirst()" aria-controls="seance">
                    <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ __('pages/conversations_bd.seance.previous') }}
                </button>
                <p class="mb-0 fw-semibold text-center" aria-live="polite" aria-atomic="true" x-text="status()"></p>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-warning" x-show="hasThoughts()" x-on:click="toggleThoughts()" x-bind:aria-pressed="revealed ? 'true' : 'false'">
                        <i class="bi bi-cloud me-1" aria-hidden="true"></i><span x-text="revealed ? @js(__('pages/conversations_bd.seance.hide')) : @js(__('pages/conversations_bd.seance.reveal'))">{{ __('pages/conversations_bd.seance.reveal') }}</span>
                    </button>
                    <button type="button" class="btn btn-primary" x-on:click="next()" x-bind:disabled="isLast()" aria-controls="seance">
                        {{ __('pages/conversations_bd.seance.next') }}<i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
        </section>
    </div>
</x-layouts.public>
