{{--
    Page /sos (P5-05 ; design-system/xomiton/pages/signaler-et-sos.md, prime sur prototype_social/sos.php — Q21 b) :
    gabarit sobre 640 px, tu, rouge réservé au bouton et au décompte. Composant Alpine xmSosPage
    (resources/js/public/sos.js) : idle → localisation (si « Partager ma position ») → repli département / commune
    (sos.php l.62-67, envoi sans position après xomiton.alerts.sos_locate_seconds (8 s au total), Q61) → envoi API → « Ton alerte est partie » (aria-live assertive),
    personnes prévenues (x-alerts.notified, sondage 10 s), appel du contact principal, « Je suis en sécurité »,
    repentir 60 s ; hors ligne : message, appel direct des numéros d'urgence, nouvel essai au retour du réseau.
    Variables : member, shareDefault, profile, zones, numbers (KnowledgeBaseAnswerer::emergencyNumbers), initial
    (SOS du compte retrouvé par ?alerte=, forme de SosStatusResource), seo.
--}}
@php
    $number = $numbers[0]['number'] ?? null;
    $tel = static fn (string $value): string => 'tel:'.preg_replace('/[^\d+]/', '', $value);
    $countdown = (int) config('xomiton.alerts.sos_countdown_seconds', 5);
    // Étapes « En attendant l'aide » : celle qui cite un numéro disparaît quand aucun n'est administré.
    $steps = array_keys(array_filter(trans('alerts.sos.done.steps'), static fn (string $step): bool => $number !== null || ! str_contains($step, ':number')));
    $config = [
        'member' => $member,
        'share' => $shareDefault,
        'profile' => $profile,
        'zones' => $zones,
        'initial' => $initial,
        'endpoint' => route('api.sos.store'),
        'showUrl' => route('api.sos.show', ['alert' => '__ID__']),
        'csrfUrl' => url('/sanctum/csrf-cookie'),
        'poll' => (int) config('xomiton.alerts.sos_poll_seconds', 10),
        'pollMinutes' => (int) config('xomiton.alerts.sos_poll_minutes', 30),
        'locateTimeout' => 1000 * (int) config('xomiton.alerts.sos_locate_seconds', 8),
        'texts' => [
            'sent' => __('alerts.sos.done.title'),
            'queued' => __('alerts.sos.queued.text'),
            'failed' => __('alerts.sos.failed.title'),
            'cancelled' => __('components.sos.cancelled'),
            'undone' => __('alerts.sos.done.undone_title').'. '.__('alerts.sos.done.undone'),
            'safeConfirmed' => __('alerts.sos.done.safe_confirmed'),
            'actionFailed' => __('alerts.sos.done.action_failed'),
            'routed' => __('alerts.sos.done.routed'),
            'positionYes' => __('alerts.sos.done.position_yes'),
            'positionNo' => __('alerts.sos.done.position_no'),
            'positionJoined' => __('alerts.sos.done.position_joined'),
            'confirmShared' => __('alerts.sos.confirm_shared'),
            'confirmUnshared' => __('alerts.sos.confirm_unshared'),
            'undo' => __('alerts.sos.done.undo'),
            'callPrimary' => __('alerts.notified.call_primary'),
        ],
    ];
@endphp
<x-layouts.plain :seo="$seo" body-class="xm-sos-page">
    <div class="vstack gap-4" x-data="xmSosPage(@js($config))" x-on:sos-send="onSend($event.detail)" data-outbox="sos">
        {{-- Région d'annonce toujours présente : « Ton alerte est partie », hors ligne, annulation (aria-live assertive). --}}
        <p class="visually-hidden" aria-live="assertive" aria-atomic="true" x-text="announce"></p>

        <header class="text-center">
            <h1 class="h3 fw-bold mb-2">{{ __('alerts.sos.title') }}</h1>
            <p class="text-body-secondary mb-0">{{ __('alerts.sos.intro') }}</p>
        </header>

        <noscript>
            <x-alert type="warning" :title="__('alerts.sos.noscript')">
                <ul class="list-unstyled mb-0 mt-2">
                    @foreach ($numbers as $item)
                        <li><a class="fw-bold" href="{{ $tel($item['number']) }}">{{ $item['label'] }} · {{ $item['number'] }}</a></li>
                    @endforeach
                </ul>
            </x-alert>
        </noscript>

        {{-- 1. Bouton : maintien 2 s, alternatives, partage de la position, numéro et message facultatifs. --}}
        <section class="card" x-show="phase === 'idle'" @if ($initial) x-cloak @endif aria-labelledby="sosHelpText">
            <div class="card-body text-center py-5">
                <x-sos-button :fixed="false" size="lg" panel="inline" :managed="true" action="sos-send" id="sosButton" confirm-bind="sosConfirmText()" />
                <p class="fs-5 fw-semibold mt-4 mb-1" id="sosHelpText">{{ __('alerts.sos.button_help') }}</p>
                <p class="small text-body-secondary mb-4">{{ __('alerts.sos.alternatives') }} {{ __('alerts.sos.countdown_hint', ['seconds' => $countdown]) }}</p>

                <div class="mx-auto text-start xm-sos-options">
                    <x-field type="checkbox" name="share_location" :label="__('alerts.sos.share')" :value="$shareDefault" x-model="share"
                             :hint="__('alerts.sos.share_help').' '.($member ? __('alerts.sos.share_remembered_account') : __('alerts.sos.share_remembered_device'))" />
                    <details class="mt-2">
                        <summary class="fw-semibold">{{ __('alerts.sos.details') }}</summary>
                        <div class="pt-3">
                            <x-field type="tel" name="phone" :label="__('alerts.sos.phone')" :optional="true" x-model="phone" autocomplete="tel" inputmode="tel" :placeholder="__('alerts.sos.phone_placeholder')" maxlength="40" />
                            <x-field type="textarea" name="note" :label="__('alerts.sos.message')" :optional="true" x-model="note" :rows="3" :maxlength="(int) config('xomiton.alerts.sos_message_max', 1000)" :placeholder="__('alerts.sos.message_placeholder')" />
                        </div>
                    </details>
                </div>
            </div>
        </section>

        {{-- 2. Localisation (si partagée) : bornée (xomiton.alerts.sos_locate_seconds), envoi possible sans attendre. --}}
        <section class="card" x-show="phase === 'locating'" x-cloak>
            <div class="card-body text-center py-5">
                <span class="spinner-border text-primary mb-3" aria-hidden="true"></span>
                <h2 class="h5" tabindex="-1" data-sos-focus="locating">{{ __('alerts.sos.locating') }}</h2>
                <p class="text-body-secondary">{{ __('alerts.sos.locating_help') }}</p>
                <button type="button" class="btn btn-light btn-lg" x-on:click="skipLocate()">{{ __('alerts.sos.send_without_position') }}</button>
            </div>
        </section>

        {{-- 3. Repli sans GPS (sos.php l.62-67) : département puis commune ; sans choix, l'alerte part quand même. --}}
        <section class="card" x-show="phase === 'manual'" x-cloak>
            <div class="card-body">
                <h2 class="h5" tabindex="-1" data-sos-focus="manual"><i class="bi bi-geo-alt-fill me-1" aria-hidden="true"></i>{{ __('alerts.sos.manual.title') }}</h2>
                <p>{{ __('alerts.sos.manual.text') }}</p>
                <div class="row g-2">
                    <div class="col-sm-6">
                        <label class="form-label" for="sosZone">{{ __('alerts.sos.manual.zone') }}</label>
                        <select class="form-select" id="sosZone" x-model="zoneId" x-on:change="communeId = ''">
                            <option value="">{{ __('alerts.sos.manual.choose') }}</option>
                            @foreach ($zones as $zone)
                                <option value="{{ $zone['id'] }}">{{ $zone['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label" for="sosCommune">{{ __('alerts.sos.manual.commune') }}</label>
                        <select class="form-select" id="sosCommune" x-model="communeId" x-bind:disabled="communes().length === 0">
                            <option value="">{{ __('alerts.sos.manual.choose') }}</option>
                            <template x-for="commune in communes()" :key="commune.id">
                                <option :value="String(commune.id)" x-text="commune.name"></option>
                            </template>
                        </select>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-primary btn-lg" x-on:click="submit()"><i class="bi bi-send me-1" aria-hidden="true"></i>{{ __('alerts.sos.manual.send') }}</button>
                    <button type="button" class="btn btn-dark btn-lg" x-on:click="abortManual()">{{ __('components.sos.cancel') }}</button>
                </div>
            </div>
        </section>

        {{-- 4. Envoi en cours. --}}
        <section class="card" x-show="phase === 'sending'" x-cloak>
            <div class="card-body text-center py-5">
                <span class="spinner-border text-primary mb-3" aria-hidden="true"></span>
                <h2 class="h5 mb-0" tabindex="-1" data-sos-focus="sending">{{ __('alerts.sos.sending') }}</h2>
            </div>
        </section>

        {{-- 5. Hors ligne ou réseau en échec : nouvel essai automatique (même Idempotency-Key), appel direct. --}}
        <section class="card" x-show="phase === 'queued'" x-cloak>
            <div class="card-body">
                <h2 class="h5" tabindex="-1" data-sos-focus="queued"><i class="bi bi-wifi-off me-1" aria-hidden="true"></i>{{ __('alerts.sos.queued.title') }}</h2>
                <p class="mb-2">{{ __('alerts.sos.queued.text') }}</p>
                <p class="small text-body-secondary">{{ __('alerts.sos.queued.keep_open') }}</p>
                <div class="d-grid gap-2">
                    @foreach ($numbers as $item)
                        <a class="btn btn-primary btn-lg" href="{{ $tel($item['number']) }}"><i class="bi bi-telephone-fill me-1" aria-hidden="true"></i>{{ $item['label'] }} · {{ $item['number'] }}</a>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- 6. Refus définitif de l'API (rare) : appel direct, nouvel essai. --}}
        <section class="card" x-show="phase === 'failed'" x-cloak>
            <div class="card-body">
                <h2 class="h5" tabindex="-1" data-sos-focus="failed">{{ __('alerts.sos.failed.title') }}</h2>
                <p>{{ __('alerts.sos.failed.text') }}</p>
                <div class="d-grid gap-2">
                    @foreach ($numbers as $item)
                        <a class="btn btn-primary btn-lg" href="{{ $tel($item['number']) }}"><i class="bi bi-telephone-fill me-1" aria-hidden="true"></i>{{ $item['label'] }} · {{ $item['number'] }}</a>
                    @endforeach
                    <button type="button" class="btn btn-light btn-lg" x-on:click="retry()">{{ __('alerts.sos.failed.retry') }}</button>
                </div>
            </div>
        </section>

        {{-- 7. Alerte envoyée (ou annulée) : confirmation, appel, « Je suis en sécurité », repentir, personnes prévenues. --}}
        <template x-if="phase === 'sent' || phase === 'cancelled'">
            <div class="vstack gap-4">
                <section class="card">
                    <div class="card-body">
                        <div class="text-center" x-show="phase === 'sent'">
                            <span class="xm-sos-done-icon rounded-circle d-inline-flex align-items-center justify-content-center bg-success text-white" aria-hidden="true"><i class="bi bi-check-lg fs-2"></i></span>
                            <h2 class="h4 mt-3 text-success" tabindex="-1" data-sos-focus="sent">{{ __('alerts.sos.done.title') }}</h2>
                            <p class="mb-1">{{ __('alerts.sos.done.text') }}</p>
                            <p class="small text-body-secondary mb-0" x-show="routedText() !== ''" x-text="routedText()"></p>
                            <p class="small text-body-secondary mb-0" x-show="positionText() !== ''" x-text="positionText()"></p>
                        </div>
                        <div class="text-center" x-show="phase === 'cancelled'">
                            <h2 class="h4" tabindex="-1" data-sos-focus="cancelled">{{ __('alerts.sos.done.undone_title') }}</h2>
                            <p class="mb-0">{{ __('alerts.sos.done.undone') }}</p>
                        </div>

                        <div class="d-grid gap-2 mt-4" x-show="phase === 'sent'">
                            <a class="btn btn-primary btn-lg" x-show="primary()" x-bind:href="primary() ? callHref(primary().phone) : null"><i class="bi bi-telephone-fill me-1" aria-hidden="true"></i><span x-text="callPrimaryText()"></span></a>
                            @if ($number)
                                <a class="btn btn-primary btn-lg" x-show="! primary()" href="{{ $tel($number) }}"><i class="bi bi-telephone-fill me-1" aria-hidden="true"></i>{{ __('alerts.sos.done.call_number', ['number' => $number]) }}</a>
                            @endif
                            <button type="button" class="btn btn-outline-primary btn-lg" x-show="alert && ! alert.safe" x-bind:disabled="busy" x-on:click="safe()"><i class="bi bi-shield-check me-1" aria-hidden="true"></i>{{ __('alerts.sos.done.safe') }}</button>
                            <p class="text-success fw-semibold text-center mb-0" x-show="alert && alert.safe"><i class="bi bi-shield-check me-1" aria-hidden="true"></i>{{ __('alerts.sos.done.safe_confirmed') }}</p>
                            <template x-if="cancelLeft() > 0">
                                <div class="text-center mt-2">
                                    <p class="small text-body-secondary mb-2" x-text="undoText()"></p>
                                    <button type="button" class="btn btn-dark btn-lg" x-bind:disabled="busy" x-on:click="cancel()">{{ __('alerts.sos.done.cancel') }}</button>
                                </div>
                            </template>
                        </div>
                        {{-- Rouge réservé au bouton et au décompte (signaler-et-sos.md) : erreur d'action en avertissement. --}}
                        <x-alert type="warning" class="small mt-3 mb-0" x-show="error !== ''" x-cloak><span x-text="error"></span></x-alert>
                    </div>
                </section>

                <x-alerts.notified bind="notifiedOptions()" />

                <section class="card" x-show="phase === 'sent'" aria-labelledby="sosWaitTitle">
                    <div class="card-body">
                        <h2 class="h6" id="sosWaitTitle">{{ __('alerts.sos.done.while_waiting') }}</h2>
                        <div class="vstack gap-2">
                            @foreach ($steps as $i)
                                <div class="xm-step"><span class="num" aria-hidden="true">{{ $loop->iteration }}</span><div>{{ __('alerts.sos.done.steps.'.$i, ['number' => (string) $number]) }}</div></div>
                            @endforeach
                        </div>
                    </div>
                </section>

                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-light" href="{{ route('urgences') }}"><i class="bi bi-telephone me-1" aria-hidden="true"></i>{{ __('alerts.sos.done.emergency') }}</a>
                    <a class="btn btn-primary-soft" href="{{ route('signaler') }}">{{ __('alerts.sos.done.report') }}</a>
                    <button type="button" class="btn btn-light" x-show="phase === 'cancelled' || (alert && alert.safe)" x-on:click="restart()">{{ __('alerts.sos.done.new') }}</button>
                </div>
            </div>
        </template>

        <p class="small text-body-secondary text-center mb-0">
            @if ($number)
                <a href="{{ $tel($number) }}">{{ __('alerts.sos.doubt', ['number' => $number]) }}</a> ·
            @endif
            <a href="{{ route('urgences') }}">{{ __('alerts.sos.all_contacts') }}</a>
        </p>
    </div>
</x-layouts.plain>
