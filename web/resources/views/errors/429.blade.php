{{--
    Erreur 429 (limitation de débit, P3-05) : délai lu dans l'en-tête Retry-After de l'exception quand il existe
    (errors.429.text avec :seconds), sinon la variante sans délai. Action : réessayer (page précédente) ou retour à l'accueil.
--}}
@php
    $headers = isset($exception) && method_exists($exception, 'getHeaders') ? (array) $exception->getHeaders() : [];
    $seconds = (int) ($headers['Retry-After'] ?? $headers['retry-after'] ?? 0);
    $text = $seconds > 0 ? __('errors.429.text', ['seconds' => $seconds]) : __('errors.429.text_no_delay');
    $previous = url()->previous();
    $href = $previous !== '' && $previous !== url()->current() ? $previous : route('home');
@endphp
<x-error-page :code="429" :title="__('errors.429.title')" :text="$text"
    :action="['label' => __('errors.429.action'), 'href' => $href]"
    :secondary="['label' => __('common.back_home'), 'href' => route('home')]"
    icon="speedometer" />
