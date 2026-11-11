{{--
    Pages légales `/politique-de-confidentialite`, `/conditions-utilisation`, `/mentions-legales`, `/cookies`
    (LegalPageController ; textes lang/fr/pages/legal.php). Publiques, indexables, sans connexion ; même gabarit que
    `/confidentialite` (héro, fil d'Ariane, sous-navigation collante, cartes `xm-legal-section`), vous (VOICE_AND_TONE §3).
    Un champ que le client doit fournir s'affiche « [À COMPLÉTER PAR LE CLIENT : …] » en surbrillance (<mark>).
    Blocs d'une section : texte (paragraphe), ['list' => […]], ['ol' => […]], ['table' => ['head' => […], 'rows' => […]]],
    ['dl' => [[terme, définition], …]], ['note' => texte].
    Variables : $page, $content (title, lead, sections), $updatedAt (Y-m-d), $seo.
--}}
@php
    $formatter = \App\Support\LocaleFormatter::make();
    // Échappe puis met en surbrillance les marqueurs « À COMPLÉTER PAR LE CLIENT » (typographie : le texte est déjà échappé).
    $t = static fn (mixed $text): \Illuminate\Support\HtmlString => new \Illuminate\Support\HtmlString(
        preg_replace('/\[À COMPLÉTER PAR LE CLIENT[^\]]*\]/u', '<mark class="xm-todo">$0</mark>', e((string) $text))
    );
    $others = collect(\App\Http\Controllers\Web\Legal\LegalPageController::ROUTES)->except($page);
@endphp
<x-layouts.public :seo="$seo">
    <section class="py-5 text-white xm-hero-bg xm-hero-page" aria-labelledby="legal-titre">
        <div class="container">
            <h1 class="fw-bold text-white" id="legal-titre">{{ $content['title'] }}</h1>
            <p class="mb-1 xm-hero-sub">{{ $content['lead'] }}</p>
            <p class="mb-0 small xm-hero-sub">{!! __('pages/legal.updated', ['date' => '<time datetime="'.e($updatedAt).'">'.e($formatter->longDate($updatedAt)).'</time>']) !!}</p>
        </div>
    </section>

    <div class="container py-5">
        <x-breadcrumb class="mb-4" :items="[['label' => __('seo.breadcrumb_home'), 'url' => route('home')], ['label' => $content['title']]]" />
        <div class="row g-4">
            <aside class="col-lg-3">
                <nav class="card xm-legal-nav" aria-label="{{ __('pages/legal.nav_label') }}">
                    <div class="list-group list-group-flush">
                        @foreach ($content['sections'] as $section)
                            <a class="list-group-item list-group-item-action" href="#{{ $section['id'] }}">{{ $section['title'] }}</a>
                        @endforeach
                    </div>
                </nav>
            </aside>

            <div class="col-lg-9 vstack gap-4">
                @foreach ($content['sections'] as $section)
                    <section class="card xm-legal-section" id="{{ $section['id'] }}" aria-labelledby="{{ $section['id'] }}-titre">
                        <div class="card-body p-4">
                            <h2 class="h4 mb-3" id="{{ $section['id'] }}-titre">{{ $section['title'] }}</h2>
                            @foreach ($section['blocks'] as $block)
                                @if (is_string($block))
                                    <p>{{ $t($block) }}</p>
                                @elseif (isset($block['list']))
                                    <ul>@foreach ($block['list'] as $item)<li>{{ $t($item) }}</li>@endforeach</ul>
                                @elseif (isset($block['ol']))
                                    <ol>@foreach ($block['ol'] as $item)<li>{{ $t($item) }}</li>@endforeach</ol>
                                @elseif (isset($block['dl']))
                                    <dl class="row mb-3">
                                        @foreach ($block['dl'] as [$term, $definition])
                                            <dt class="col-sm-4">{{ $term }}</dt>
                                            <dd class="col-sm-8">{{ $t($definition) }}</dd>
                                        @endforeach
                                    </dl>
                                @elseif (isset($block['table']))
                                    <div class="table-responsive mb-3">
                                        <table class="table table-sm table-bordered align-middle small">
                                            <caption class="visually-hidden">{{ $section['title'] }}</caption>
                                            <thead class="table-light"><tr>@foreach ($block['table']['head'] as $cell)<th scope="col">{{ $cell }}</th>@endforeach</tr></thead>
                                            <tbody>
                                                @foreach ($block['table']['rows'] as $row)
                                                    <tr>@foreach ($row as $i => $cell)@if ($i === 0)<th scope="row" class="fw-semibold">{{ $t($cell) }}</th>@else<td>{{ $t($cell) }}</td>@endif @endforeach</tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @elseif (isset($block['note']))
                                    <div class="alert alert-info"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>{{ $t($block['note']) }}</div>
                                @endif
                            @endforeach
                        </div>
                    </section>
                @endforeach

                <section class="card xm-legal-section" aria-labelledby="legal-autres">
                    <div class="card-body p-4">
                        <h2 class="h5" id="legal-autres">{{ __('pages/legal.others') }}</h2>
                        <ul class="list-inline mb-0">
                            @foreach ($others as $key => $name)
                                <li class="list-inline-item me-3"><a href="{{ route($name) }}">{{ __('pages/legal.pages.'.$key.'.title') }}</a></li>
                            @endforeach
                            <li class="list-inline-item me-3"><a href="{{ route('account-deletion.show') }}">{{ __('pages/deletion.title') }}</a></li>
                            <li class="list-inline-item"><a href="{{ route('confidentialite') }}#charte">{{ __('footer.charter') }}</a></li>
                        </ul>
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-layouts.public>
