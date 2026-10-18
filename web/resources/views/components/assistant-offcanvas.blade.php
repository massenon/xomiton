{{--
    Assistant public (P4-01 ; prototype index.php l.399-431) : bouton flottant .xm-chat-fab (z-index 1040, sous le SOS) et
    offcanvas Bootstrap piloté par le composant Alpine xmAssistant (resources/js/public/assistant.js). Les réponses viennent
    de POST /api/v1/assistant/question (KnowledgeBaseAnswerer, sans stockage) ; tous les textes viennent de lang/fr.
    Bulles en x-text (jamais x-html) ; journal role="log" aria-live="polite" ; Échap, piège et retour du focus : Bootstrap.
    Props : id (défaut offcanvasChat), fab (bouton flottant, défaut vrai), chips (questions proposées, défaut lang).
    Registre tu quelle que soit la page hôte (Q56) : l'offcanvas est un dialogue distinct du corps de page et ses réponses
    serveur tutoient (lang/fr/assistant.php, assistant_kb) ; une page hôte ne lui passe donc pas ses propres textes.
    Hors connexion : premier numéro d'urgence configuré (KnowledgeBaseAnswerer::emergencyNumbers()) en texte et en tel:.
--}}
@props(['id' => 'offcanvasChat', 'fab' => true, 'chips' => null])
@php
    $chips = $chips ?? (array) __('components.assistant.chips');
    $inputId = $id.'Input';
    $firstNumber = \App\Application\Shared\KnowledgeBaseAnswerer::emergencyNumbers()[0] ?? null;
    $config = [
        'url' => route('api.assistant.question'),
        'csrfUrl' => url('/sanctum/csrf-cookie'),
        'offlineUrl' => route('urgences'),
        'welcome' => __('components.assistant.welcome'),
        'texts' => [
            'loading' => __('common.loading'),
            'error' => __('components.assistant.error'),
            'offline' => $firstNumber !== null
                ? __('components.assistant.offline', ['number' => $firstNumber['number']])
                : __('components.assistant.offline_no_number'),
            'offlineLink' => __('components.assistant.offline_link'),
            'rateLimited' => __('components.assistant.rate_limited'),
            'retry' => __('common.retry'),
        ],
        'offlineCall' => $firstNumber === null ? null : [
            'label' => __('assistant.links.call', ['number' => $firstNumber['number'], 'label' => $firstNumber['label']]),
            'url' => \App\Application\Shared\KnowledgeBaseAnswerer::telHref($firstNumber['number']),
        ],
    ];
@endphp
@if ($fab)
    <button class="xm-chat-fab btn btn-primary no-print" type="button" data-bs-toggle="offcanvas" data-bs-target="#{{ $id }}" aria-controls="{{ $id }}" aria-label="{{ __('components.assistant.open') }}" title="{{ __('components.assistant.open') }}"><i class="bi bi-chat-heart-fill" aria-hidden="true"></i></button>
@endif
<div {{ $attributes->class(['offcanvas offcanvas-end xm-assistant no-print']) }} tabindex="-1" id="{{ $id }}" aria-labelledby="{{ $id }}Label" x-data="xmAssistant({{ \Illuminate\Support\Js::from($config) }})">
    <div class="offcanvas-header border-bottom">
        <div class="d-flex align-items-center gap-2">
            <span class="icon-lg rounded-circle text-white bg-primary" aria-hidden="true"><i class="bi bi-robot"></i></span>
            <div>
                <h2 class="h5 offcanvas-title mb-0" id="{{ $id }}Label">{{ __('components.assistant.title') }}</h2>
                <small class="text-secondary">{{ __('components.assistant.subtitle') }} <span class="text-success">{{ __('components.assistant.online') }}</span></small>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="{{ __('common.close') }}"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column p-0">
        <div class="msgs flex-grow-1 p-3 d-flex flex-column gap-2" role="log" aria-live="polite" aria-relevant="additions" x-ref="log">
            <template x-for="m in messages" :key="m.id">
                <div class="d-flex flex-column gap-2">
                    <div class="bub" :class="m.who === 'me' ? 'me' : 'bot'" :aria-busy="m.loading ? 'true' : 'false'" x-text="m.text"></div>
                    <div class="d-flex flex-wrap gap-2" x-show="(m.links || []).length > 0">
                        <template x-for="l in (m.links || [])" :key="l.url">
                            <a class="btn btn-sm" :class="l.variant === 'danger' ? 'btn-danger' : 'btn-primary-soft'" :href="l.url" x-text="l.label"></a>
                        </template>
                    </div>
                    <div class="d-flex flex-wrap gap-2" x-show="(m.suggestions || []).length > 0">
                        <template x-for="s in (m.suggestions || [])" :key="s">
                            <button type="button" class="btn btn-sm btn-primary-soft lp-chip text-start" x-on:click="ask(s, $el)" :disabled="busy" x-text="s"></button>
                        </template>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary align-self-start lp-chip" x-show="m.retry" x-on:click="retry(m)" :disabled="busy" x-text="texts.retry"></button>
                </div>
            </template>
        </div>
        <div class="px-3 pb-2 d-flex gap-2 overflow-auto">
            @foreach ($chips as $chip)
                <button type="button" class="btn btn-sm btn-primary-soft text-nowrap lp-chip" x-on:click="ask($el.textContent, $el)" :disabled="busy">{{ $chip }}</button>
            @endforeach
        </div>
        <form class="border-top p-3 d-flex gap-2" autocomplete="off" x-on:submit.prevent="send()">
            <label class="visually-hidden" for="{{ $inputId }}">{{ __('components.assistant.input_label') }}</label>
            <input class="form-control" id="{{ $inputId }}" type="text" x-ref="input" x-model="input" placeholder="{{ __('components.assistant.placeholder') }}" maxlength="500" enterkeyhint="send">
            <button class="btn btn-primary" type="submit" aria-label="{{ __('common.send') }}" title="{{ __('common.send') }}" :disabled="busy"><i class="bi bi-send-fill" aria-hidden="true"></i></button>
        </form>
        <p class="small text-secondary px-3 pb-3 mb-0">{{ __('components.assistant.danger') }} <a class="text-danger fw-bold" href="{{ route('sos') }}">{{ __('components.assistant.sos') }}</a></p>
    </div>
</div>
