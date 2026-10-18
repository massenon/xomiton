{{--
    Erreur 403 (P3-05, Q12 c) : affiche le message de l'exception quand il en porte un (ex. abort(403, « Confirme ton e-mail
    pour continuer. ») du middleware `verified`), sinon le texte générique errors.403. Les messages anglais par défaut de
    Laravel / Symfony (« This action is unauthorized. », « Forbidden ») ne sont jamais affichés.
--}}
@php
    $message = isset($exception) ? trim((string) $exception->getMessage()) : '';
    $defaults = ['This action is unauthorized.', 'Forbidden', 'Unauthorized', 'User does not have the right permissions.', 'User does not have the right roles.'];
    $text = $message !== '' && ! in_array($message, $defaults, true) && ! str_starts_with($message, 'User does not have') ? $message : __('errors.403.text');
@endphp
<x-error-page :code="403" :title="__('errors.403.title')" :text="$text" :action="['label' => __('errors.403.action'), 'href' => route('home')]" icon="shield-lock" />
