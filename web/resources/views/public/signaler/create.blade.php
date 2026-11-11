{{--
    Signaler (P5-04) : gabarit sobre 640 px pour tout le monde (Q18 a), tu (Q47, VOICE_AND_TONE §3), six étapes (Q11 a,
    design-system/xomiton/pages/signaler-et-sos.md) : type · faits · besoins et lieu · preuves · contact · vérification.
    Sans JavaScript : toutes les étapes à la suite et un seul envoi (POST + @csrf). Avec Alpine (xmReport,
    resources/js/public/signaler.js) : une étape à la fois, stepper aria-current="step", validation par étape, brouillon
    local, cascade département → commune depuis GET /api/v1/zones, position facultative, état hors ligne
    ([data-outbox] pour la boîte d'envoi de P5-10). Écarts au prototype signalement.php (formulaire unique à onglets,
    visuel hero-10, col-lg-8) : consignés en Q11 / Q18. Bouton d'envoi bleu, aucun rouge hors accès SOS.
--}}
@use('App\Application\Alerts\Support\ReportSteps')
@php
    $steps = ReportSteps::labels();
    $total = ReportSteps::total();
    $initialStep = $errors->any() ? ReportSteps::firstWithErrors(array_keys($errors->getMessages())) : 1;
    $limits = $options['limits'];
    $draftDays = (int) config('xomiton.alerts.draft_days', 7);
    $zoneNames = collect($options['zones'])->mapWithKeys(fn (array $zone): array => [$zone['id'] => $zone['name']])->all();
    $communeNames = collect($options['zones'])->flatMap(fn (array $zone): array => $zone['communes'])->all();
    $required = fn (string $attribute): string => (string) __('validation.required', ['attribute' => __('validation.attributes.'.$attribute)]);

    $config = [
        'steps' => ReportSteps::forClient(),
        'initial' => $initialStep,
        'hasOld' => $errors->any(),
        'zonesUrl' => route('api.referentials.zones'),
        'today' => now()->toDateString(),
        'draftDays' => $draftDays,
        'limits' => $limits,
        'labels' => [
            'type' => __('alerts.report.steps.type'),
            'category' => __('alerts.report.fields.category'),
            'description' => __('alerts.report.fields.description'),
            'occurred_on' => __('alerts.report.fields.date'),
            'needs' => __('alerts.report.fields.needs'),
            'place' => __('alerts.report.review.place'),
            'position' => __('alerts.report.fields.position'),
            'attachments' => __('alerts.report.steps.evidence'),
            'contact' => __('alerts.report.steps.contact'),
        ],
        'choices' => [
            'type' => ['report' => __('alerts.report.tabs.report'), 'need' => __('alerts.report.tabs.need')],
            'category' => $options['violence_types'],
            'needs' => $options['needs'],
            'zones' => $zoneNames,
            'communes' => $communeNames,
            'preferred_channel' => $options['channels'],
        ],
        'texts' => [
            'stepper' => ['done' => __('components.stepper.done'), 'current' => __('components.stepper.current'), 'todo' => __('components.stepper.todo')],
            'errors' => [
                'type' => $required('type'),
                'category' => __('alerts.report.fields.category_error'),
                'description' => $required('description'),
                'description_min' => __('alerts.report.fields.description_min', ['min' => $limits['description_min']]),
                'description_max' => __('validation.max.string', ['attribute' => __('validation.attributes.description'), 'max' => $limits['description_max']]),
                'date_future' => __('alerts.report.fields.date_future'),
                'needs' => __('alerts.report.fields.needs_error'),
                'zone' => $required('zone_id'),
                'contact' => $required('contact'),
                'contact_invalid' => __('alerts.report.fields.contact_invalid'),
                'channel' => $required('preferred_channel'),
                'files_too_many' => __('alerts.report.fields.evidence_too_many'),
                'file_too_large' => __('alerts.report.fields.evidence_too_large'),
                'file_type' => __('alerts.report.fields.evidence_type'),
            ],
            'commune_placeholder' => __('alerts.report.fields.commune_placeholder'),
            'position_help' => __('alerts.report.fields.position_help'),
            'position_locating' => __('alerts.report.fields.position_locating'),
            'position_attached' => __('alerts.report.fields.position_attached'),
            'position_unavailable' => __('alerts.report.fields.position_unavailable'),
            'files_one' => trans_choice('alerts.report.fields.evidence_added', 1, ['count' => ':count']),
            'files_many' => trans_choice('alerts.report.fields.evidence_added', 2, ['count' => ':count']),
            'empty' => __('alerts.report.review.empty'),
            'no_files' => __('alerts.report.review.no_files'),
            'position_yes' => __('alerts.report.review.position_yes'),
            'position_no' => __('alerts.report.review.position_no'),
            'anonymous_yes' => __('alerts.report.review.anonymous_yes'),
            'anonymous_no' => __('alerts.report.review.anonymous_no'),
            'channel' => __('alerts.report.review.channel'),
            'edit_step' => __('alerts.report.review.edit_step'),
            'draft_restored' => __('alerts.report.draft.restored'),
            'draft_cleared' => __('alerts.report.draft.cleared'),
        ],
    ];
@endphp
<x-layouts.plain :seo="$seo">
    <div class="xm-report" x-data="xmReport(@js($config))">
        <div class="mb-4">
            <span class="badge bg-primary-soft text-primary mb-2"><i class="bi bi-incognito me-1" aria-hidden="true"></i>{{ __('alerts.report.badge') }}</span>
            <h1 class="h3 fw-bold mb-2">{{ __('alerts.report.title') }}</h1>
            <p class="xm-report__lead mb-0">{{ __('alerts.report.intro') }}</p>
        </div>

        <x-alert type="info" icon="shield-lock" :title="__('alerts.report.privacy.title')" class="mb-4">
            <ul class="mb-0 ps-3">
                @foreach (array_keys(__('alerts.report.privacy.items')) as $item)
                    <li>{{ __('alerts.report.privacy.items.'.$item, ['days' => $draftDays]) }}</li>
                @endforeach
            </ul>
        </x-alert>

        {{-- Liens du résumé : jump() rouvre l'étape du champ visé (masquée par x-show) puis lui donne le focus. --}}
        <div x-show="! moved" x-on:click="jump($event)">
            <x-error-summary :messages="ReportSteps::summary($errors->getMessages())" />
        </div>

        <div class="alert alert-danger xm-error-summary" role="alert" tabindex="-1" x-ref="clientErrors" x-show="errorList.length > 0" style="display: none" x-on:click="jump($event)">
            <p class="fw-bold mb-2"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>{{ __('alerts.report.client_errors') }}</p>
            <ul class="mb-0">
                <template x-for="item in errorList" :key="item.field">
                    <li><a class="alert-link" x-bind:href="'#' + item.target" x-text="item.message"></a></li>
                </template>
            </ul>
        </div>

        {{-- x-show sur un conteneur : la classe d-flex (!important) de <x-alert> l'emporterait sur display: none. --}}
        <div x-show="offline" style="display: none">
            <x-alert type="warning" icon="wifi-off">{{ __('alerts.report.offline') }}</x-alert>
        </div>

        <div x-show="draftState === 'found'" style="display: none">
            <x-alert type="info" icon="journal-text">
                <p class="mb-2">{{ __('alerts.report.draft.found') }}</p>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-primary" x-on:click="resumeDraft()">{{ __('alerts.report.draft.resume') }}</button>
                    <button type="button" class="btn btn-sm btn-light border" x-on:click="clearDraft()">{{ __('alerts.report.draft.clear') }}</button>
                </div>
            </x-alert>
        </div>
        <p class="small text-secondary mb-0" role="status" x-text="draftMessage"></p>

        <div class="xm-report__progress" x-ref="progress" hidden x-bind:hidden="false">
            <x-stepper :steps="$steps" :current="$initialStep" :label="__('alerts.report.title')" />
            <p class="small text-secondary">{{ __('alerts.report.draft.saved') }}</p>
        </div>
        <p class="xm-report__lead" x-show="false">{{ __('alerts.report.nojs', ['total' => $total]) }}</p>

        <form method="POST" action="{{ route('signaler.store') }}" enctype="multipart/form-data" class="xm-report__form"
              data-outbox="signalement" data-outbox-endpoint="{{ route('api.signalements.store') }}"
              x-ref="form" x-on:submit="submit($event)" x-on:change="onChange($event)">
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">
            @guest
                <input type="hidden" name="anonymous" value="1">
            @endguest

            @include('public.signaler.steps.type', ['n' => 1])
            @include('public.signaler.steps.facts', ['n' => 2])
            @include('public.signaler.steps.needs', ['n' => 3])
            @include('public.signaler.steps.evidence', ['n' => 4])
            @include('public.signaler.steps.contact', ['n' => 5])
            @include('public.signaler.steps.review', ['n' => 6])
        </form>

        <p class="small text-secondary text-center mb-0">
            <a href="{{ route('signaler.suivi') }}">{{ __('buttons.help.track_report') }}</a>
        </p>
    </div>
</x-layouts.plain>
