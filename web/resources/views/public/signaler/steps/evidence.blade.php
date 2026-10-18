{{--
    Étape 4 : preuves facultatives (image · PDF · audio, MediaUploader sur le disque privé, métadonnées retirées, lien
    signé pour l'équipe : P3-04). Un navigateur ne regarnit jamais un champ fichier : après une erreur, il faut les
    rejoindre (message).
--}}
@php
    $mb = $limits['max_mb'];
@endphp
<section class="xm-report__step card card-body mb-4" id="etape-{{ $n }}" aria-labelledby="etape-{{ $n }}-titre" x-show="isStep({{ $n }})">
    @include('public.signaler.partials.step-header')

    <div class="alert alert-warning d-flex align-items-start gap-2">
        <i class="bi bi-shield-exclamation flex-shrink-0" aria-hidden="true"></i>
        <p class="mb-0">{{ __('alerts.report.fields.evidence_warning') }}</p>
    </div>

    @if ($errors->any())
        <p class="small fw-semibold">{{ __('alerts.report.fields.evidence_reselect') }}</p>
    @endif

    <x-field type="file" name="attachments[]" id="f-attachments" :label="__('alerts.report.fields.evidence')"
             :hint="__('alerts.report.fields.evidence_hint', ['count' => $limits['attachments_max'], 'image' => $mb['images'] ?? 0, 'pdf' => $mb['documents'] ?? 0, 'audio' => $mb['audio'] ?? 0])"
             :error="$errors->first('attachments') ?: $errors->first('attachments.*')" multiple accept="{{ $limits['accept'] }}" />
    <p class="small text-secondary mb-0" role="status" x-text="filesText"></p>

    @include('public.signaler.partials.step-nav')
</section>
