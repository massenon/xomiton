{{--
    Confirmation d'envoi (P5-04) : code de suivi affiché en grand, une seule fois (flash de session, jamais dans l'URL),
    copier (Alpine xmReportSent, presse-papiers) et imprimer ; contact routé (nom et téléphone seulement si la fiche est
    publique, CreatedAlert::routedPayload) ; le brouillon local est effacé à l'affichage. Non indexée, tu (Q47).
--}}
@php
    $isNeed = ($sent['type'] ?? '') === \App\Enums\AlertType::Need->value;
    $routed = is_array($sent['routed'] ?? null) ? $sent['routed'] : null;
    $component = ['code' => $sent['code'], 'texts' => ['copied' => __('alerts.report.done.copied'), 'copy_failed' => __('alerts.report.done.copy_failed')]];
@endphp
<x-layouts.plain :seo="$seo">
    <div class="xm-report-sent text-center" x-data="xmReportSent(@js($component))">
        <span class="xm-report-sent__icon d-inline-flex align-items-center justify-content-center rounded-circle bg-success text-white mb-3" aria-hidden="true"><i class="bi bi-check-lg"></i></span>
        <h1 class="h3 fw-bold mb-2">{{ $isNeed ? __('alerts.report.done.need_title') : __('alerts.report.done.title') }}</h1>
        <p class="xm-report__lead">{{ __('alerts.report.done.text') }}</p>

        @if ($sent['duplicate'] ?? false)
            <x-alert type="info" class="text-start">{{ __('alerts.report.done.duplicate') }}</x-alert>
        @endif

        <div class="card card-body my-4">
            <dl class="mb-2">
                <dt class="fw-semibold mb-1">{{ __('alerts.report.done.tracking') }}</dt>
                <dd class="xm-tracking-code mb-0"><strong>{{ $sent['code'] }}</strong></dd>
            </dl>
            <p class="small text-secondary mb-3">{{ __('alerts.report.done.tracking_help') }}</p>
            <p class="small fw-semibold mb-3">{{ __('alerts.report.done.once') }}</p>
            <div class="no-print" hidden x-bind:hidden="false">
                <div class="d-flex flex-wrap justify-content-center gap-2">
                    <button type="button" class="btn btn-primary" x-show="canCopy" x-on:click="copy()"><i class="bi bi-clipboard me-1" aria-hidden="true"></i>{{ __('alerts.report.done.copy') }}</button>
                    <button type="button" class="btn btn-light border" x-on:click="print()"><i class="bi bi-printer me-1" aria-hidden="true"></i>{{ __('buttons.print') }}</button>
                </div>
            </div>
            <p class="small mt-2 mb-0" role="status" x-text="copied"></p>
        </div>

        <div class="alert alert-primary text-start">
            @if ($routed !== null)
                <p class="fw-bold mb-1"><i class="bi bi-person-check me-1" aria-hidden="true"></i>{{ __('alerts.report.done.routed') }}</p>
                <p class="mb-0">
                    {{ isset($routed['name']) ? __('alerts.report.done.routed_to', ['name' => $routed['name'], 'role' => $routed['role']]) : $routed['role'] }}
                    @if (! empty($routed['phone']))
                        · <a href="tel:{{ preg_replace('/[^\d+]/', '', (string) $routed['phone']) }}">{{ $routed['phone'] }}</a>
                    @endif
                </p>
            @else
                <p class="mb-0"><i class="bi bi-inbox me-1" aria-hidden="true"></i>{{ __('alerts.report.done.received') }}</p>
            @endif
        </div>

        <div class="d-flex flex-wrap justify-content-center gap-2 no-print">
            <a class="btn btn-primary-soft" href="{{ route('signaler.suivi') }}">{{ __('buttons.help.track_report') }}</a>
            <a class="btn btn-primary-soft" href="{{ route('urgences') }}">{{ __('buttons.help.emergency_contacts') }}</a>
            <a class="btn btn-light border" href="{{ route('signaler') }}">{{ __('buttons.help.another_report') }}</a>
            <a class="btn btn-outline-danger" href="{{ route('sos') }}">{{ __('buttons.help.in_danger_sos') }}</a>
        </div>
    </div>
</x-layouts.plain>
