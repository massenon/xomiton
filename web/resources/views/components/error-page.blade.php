{{--
    Page d'erreur HTTP (P3-05 textes, P4-11 pages 404 / 503 sobres ; prototype error-404.php) dans le gabarit sobre :
    code, titre, explication, action principale, action secondaire facultative, puis le rappel de crise et les numéros
    d'urgence (errors.emergency, config xomiton.assistant.emergency_numbers surchargée par le paramètre `emergency_numbers`).
    La lecture des paramètres est protégée : une 500 levée parce que la base est injoignable doit quand même s'afficher.
    Props : code (int), title, text, action (['label' => …, 'href' => …]), secondary (idem, facultatif), icon (Bootstrap Icons sans « bi- »).
--}}
@props(['code', 'title', 'text', 'action', 'secondary' => null, 'icon' => 'exclamation-triangle'])
@php
    try {
        $numbers = \App\Support\Settings::array('emergency_numbers', (array) config('xomiton.assistant.emergency_numbers', []));
    } catch (\Throwable) {
        $numbers = (array) config('xomiton.assistant.emergency_numbers', []);
    }
@endphp
<x-layouts.plain :seo="['title' => __('errors.page_title', ['code' => $code]), 'description' => $text, 'index' => false]" body-class="xm-error">
    <section class="text-center" data-xm-error="{{ $code }}">
        <p class="text-uppercase small fw-bold text-secondary mb-2">{{ __('errors.code', ['code' => $code]) }}</p>
        <div class="display-3 text-primary"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></div>
        <h1 class="h2 fw-bold mt-3">{{ $title }}</h1>
        <p class="lead mb-4">{{ $text }}</p>
        <div class="d-flex flex-column flex-sm-row gap-2 justify-content-center">
            <a class="btn btn-primary" href="{{ $action['href'] }}"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ $action['label'] }}</a>
            @if ($secondary)
                <a class="btn btn-outline-primary" href="{{ $secondary['href'] }}">{{ $secondary['label'] }}</a>
            @endif
        </div>
        <p class="small text-body-secondary mt-4 mb-0">{{ __('errors.help') }}</p>
    </section>

    <section class="card border-danger-subtle mt-4" aria-labelledby="error-emergency-title">
        <div class="card-body">
            <h2 class="h5 fw-bold text-danger" id="error-emergency-title"><i class="bi bi-heart-pulse-fill me-1" aria-hidden="true"></i>{{ __('errors.emergency.title') }}</h2>
            <p class="mb-3">{{ __('errors.emergency.text') }}</p>
            <div class="d-flex flex-column flex-sm-row gap-2">
                <a class="btn btn-danger fw-bold flex-fill" href="{{ route('sos') }}"><i class="bi bi-exclamation-octagon-fill me-1" aria-hidden="true"></i>{{ __('errors.emergency.sos') }}</a>
                <a class="btn btn-outline-secondary flex-fill" href="{{ route('urgences') }}"><i class="bi bi-telephone-fill me-1" aria-hidden="true"></i>{{ __('errors.emergency.contacts') }}</a>
            </div>
            @if ($numbers !== [])
                <ul class="list-unstyled mt-3 mb-0">
                    @foreach ($numbers as $entry)
                        @if (is_array($entry) && isset($entry['label'], $entry['number']))
                            <li class="d-flex justify-content-between align-items-center py-2 border-top">
                                <span>{{ $entry['label'] }}</span>
                                <a class="fw-bold text-decoration-none" href="tel:{{ preg_replace('/[^+0-9]/', '', (string) $entry['number']) }}">{{ $entry['number'] }}</a>
                            </li>
                        @endif
                    @endforeach
                </ul>
            @endif
        </div>
    </section>
</x-layouts.plain>
