{{--
    Résumé d'erreurs de formulaire (COMPOSANTS §Champs, ACCESSIBILITE §4) : .alert.alert-danger role="alert", focalisé après un envoi raté,
    un lien par champ vers son identifiant (#f-{nom}, le même que celui de <x-field>).
    Props :
      messages : tableau clé => message (ou MessageBag) ; par défaut le sac $errors partagé par la session
      title    : phrase d'introduction (défaut : « n erreur(s) à corriger »)
      autofocus : focalise le résumé au chargement (défaut true : après un envoi raté ; false dans un catalogue ou une modale)
--}}
@props(['messages' => null, 'title' => null, 'autofocus' => true])
@php
    $list = [];
    if ($messages instanceof \Illuminate\Contracts\Support\MessageBag) {
        $list = $messages->getMessages();
    } elseif (is_array($messages)) {
        $list = array_map(fn ($m) => (array) $m, $messages);
    } elseif (isset($errors) && $errors->any()) {
        $list = $errors->getMessages();
    }
    $count = count($list);
@endphp
@if ($count > 0)
    <div {{ $attributes->class('alert alert-danger xm-error-summary') }} role="alert" tabindex="-1"{!! $autofocus ? ' x-data x-init="$el.focus()"' : '' !!}>
        <p class="fw-bold mb-2"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>{{ $title ?? trans_choice('components.error_summary.title', $count, ['count' => $count]) }}</p>
        <ul class="mb-0">
            @foreach ($list as $key => $msgs)
                <li><a class="alert-link" href="#f-{{ str_replace(['[', ']', '.'], '-', $key) }}">{{ $msgs[0] ?? '' }}</a></li>
            @endforeach
        </ul>
    </div>
@endif
