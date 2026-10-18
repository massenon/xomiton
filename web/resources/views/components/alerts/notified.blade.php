{{--
    Personnes prévenues d'une alerte (P5-03 ; signaler-et-sos.md l.18, maquette mobile simulateur.html l.909-912) : monté
    par la page /sos (P5-05) dans l'état « Alerte envoyée ». Liste rendue côté serveur (sans JavaScript, tests) puis tenue
    à jour par le composant Alpine xmNotified (resources/js/public/alertes-dispatch.js), qui sonde `endpoint`
    (GET /api/v1/sos/{ulid}, data.notified) toutes les `poll` secondes jusqu'à `pollUntil`, un état final ou un 404.
    État en toutes lettres + badge « Vu » / « SMS » (jamais la couleur seule) ; liste aria-live="polite" ; annonce
    « :name a bien reçu ton alerte » pour les lecteurs d'écran ; bouton d'appel seulement si le numéro peut être montré.
    Props : people (NotifiedPerson[] ou tableaux de NotifiedPersonResource), endpoint (null = liste statique),
            poll (secondes, défaut xomiton.alerts.sos_poll_seconds), pollUntil (ISO 8601), title,
            token (SOS sans compte : jeton `sos_token` de la création, envoyé en X-Sos-Token par le sondage, jamais stocké).
    Heure « Mis à jour à » : gabarit tiré de LocaleFormatter::time() (« 9 h 05 »), appliqué par le navigateur.
--}}
@props(['people' => [], 'endpoint' => null, 'poll' => null, 'pollUntil' => null, 'title' => null, 'token' => null])
@php
    $people = collect($people)->map(static fn ($person) => $person instanceof \App\Application\Alerts\Dispatch\NotifiedPerson
        ? \App\Http\Resources\Alerts\NotifiedPersonResource::make($person)->resolve()
        : (array) $person)->values()->all();
    $initials = static function (string $label): string {
        $words = preg_split('/\s+/u', trim($label), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return mb_strtoupper(implode('', array_map(static fn (string $word): string => mb_substr($word, 0, 1), array_slice($words, 0, 2))));
    };
    $config = [
        'people' => $people,
        'endpoint' => $endpoint,
        'poll' => (int) ($poll ?? config('xomiton.alerts.sos_poll_seconds', 10)),
        'pollUntil' => $pollUntil,
        'token' => $endpoint === null ? null : $token,
        'texts' => [
            'acknowledged' => __('alerts.sos.done.acknowledged'),
            'updated' => __('alerts.notified.updated'),
            // 13 h 47 de référence → gabarit « {H} h {mm} » : même format que le serveur, heure locale du navigateur.
            'time' => str_replace(['13', '47'], ['{H}', '{mm}'], \App\Support\LocaleFormatter::make(null, 'UTC')->time(\Carbon\CarbonImmutable::create(2000, 1, 1, 13, 47, 0, 'UTC'))),
        ],
    ];
    $call = __('buttons.help.call');
@endphp
<section {{ $attributes->class(['card', 'xm-notified']) }} x-data="xmNotified(@js($config))" aria-labelledby="xmNotifiedTitle">
    <div class="card-body">
        <h2 id="xmNotifiedTitle" class="h5 mb-3">{{ $title ?? __('alerts.notified.title') }}</h2>

        <ul class="list-unstyled vstack gap-2 mb-0" aria-live="polite" aria-relevant="text" x-show="! ready">
            @foreach ($people as $person)
                <li class="d-flex align-items-center gap-3">
                    <span class="avatar avatar-xs"><span class="avatar-img rounded-circle bg-primary bg-opacity-10 text-primary fw-bold d-flex align-items-center justify-content-center" aria-hidden="true">{{ $initials((string) $person['label']) }}</span></span>
                    <span class="flex-grow-1 small"><b>{{ $person['label'] }}</b><span class="text-body-secondary"> · {{ $person['role'] }}</span><br><span class="text-body-secondary">{{ $person['state_label'] }}</span></span>
                    @if ($person['state'] === 'seen')
                        <x-badge color="success" icon="check2">{{ __('alerts.notified.badge_seen') }}</x-badge>
                    @elseif ($person['state'] === 'sms_sent')
                        <x-badge color="neutral" icon="chat-left-text">{{ __('alerts.notified.badge_sms') }}</x-badge>
                    @endif
                    @if (! empty($person['phone']))
                        <a class="btn btn-sm btn-primary-soft" href="tel:{{ preg_replace('/[^\d+]/', '', (string) $person['phone']) }}" aria-label="{{ $call }} {{ $person['label'] }}"><i class="bi bi-telephone me-1" aria-hidden="true"></i>{{ $call }}</a>
                    @endif
                </li>
            @endforeach
        </ul>

        <template x-if="ready">
            <ul class="list-unstyled vstack gap-2 mb-0" aria-live="polite" aria-relevant="text">
                <template x-for="p in people" :key="p.key">
                    <li class="d-flex align-items-center gap-3">
                        <span class="avatar avatar-xs"><span class="avatar-img rounded-circle bg-primary bg-opacity-10 text-primary fw-bold d-flex align-items-center justify-content-center" aria-hidden="true" x-text="initials(p.label)"></span></span>
                        <span class="flex-grow-1 small"><b x-text="p.label"></b><span class="text-body-secondary" x-text="' · ' + p.role"></span><br><span class="text-body-secondary" x-text="p.state_label"></span></span>
                        <template x-if="p.state === 'seen'"><x-badge color="success" icon="check2">{{ __('alerts.notified.badge_seen') }}</x-badge></template>
                        <template x-if="p.state === 'sms_sent'"><x-badge color="neutral" icon="chat-left-text">{{ __('alerts.notified.badge_sms') }}</x-badge></template>
                        <a x-show="p.phone" class="btn btn-sm btn-primary-soft" :href="'tel:' + telHref(p.phone)" :aria-label="@js($call) + ' ' + p.label"><i class="bi bi-telephone me-1" aria-hidden="true"></i>{{ $call }}</a>
                    </li>
                </template>
            </ul>
        </template>

        <p class="text-body-secondary small mb-0" @if ($people !== []) hidden @endif :hidden="people.length > 0">{{ __('alerts.sos.done.received') }}</p>
        <p class="small text-body-secondary mt-2 mb-0" x-show="offline" x-cloak><i class="bi bi-wifi-off me-1" aria-hidden="true"></i>{{ __('alerts.notified.offline') }}</p>
        <p class="visually-hidden" aria-live="polite" aria-atomic="true" x-text="announcement"></p>
        <p class="small text-body-secondary mt-2 mb-0" x-show="updatedAt" x-cloak x-text="updatedLabel()"></p>
    </div>
</section>
