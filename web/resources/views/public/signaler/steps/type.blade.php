{{-- Étape 1 : signalement ou besoin (Q29 : `report` · `need`), type de violence (obligatoire pour un signalement). --}}
@php
    $typeError = $errors->first('type');
    $typeValue = (string) old('type', $initialType ?? '');
@endphp
<section class="xm-report__step card card-body mb-4" id="etape-{{ $n }}" aria-labelledby="etape-{{ $n }}-titre" x-show="isStep({{ $n }})">
    @include('public.signaler.partials.step-header')

    <fieldset class="mb-3" id="f-type" tabindex="-1" @if ($typeError) aria-invalid="true" aria-describedby="f-type-error" @endif>
        <legend class="form-label fs-6 fw-semibold">{{ __('alerts.report.question') }}</legend>
        @foreach (['report', 'need'] as $value)
            <div class="form-check xm-report__choice">
                <input @class(['form-check-input', 'is-invalid' => $typeError]) type="radio" name="type" id="f-type-{{ $loop->index }}" value="{{ $value }}"
                       aria-describedby="f-type-{{ $loop->index }}-help" required @checked($typeValue === $value)>
                <label class="form-check-label fw-semibold" for="f-type-{{ $loop->index }}">{{ __('alerts.report.tabs.'.$value) }}</label>
                <p class="small text-secondary mb-0" id="f-type-{{ $loop->index }}-help">{{ __('alerts.report.help.'.$value) }}</p>
            </div>
        @endforeach
        @if ($typeError)<div id="f-type-error" class="invalid-feedback d-block"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>{{ $typeError }}</div>@endif
    </fieldset>

    <x-field type="select" name="category" :label="__('alerts.report.fields.category')" :hint="__('alerts.report.fields.category_hint')"
             :options="['' => __('alerts.report.fields.category_placeholder')] + $options['violence_types']" />

    <p class="small mb-0">
        {{ __('alerts.report.sos_hint') }}
        <a class="btn btn-sm btn-outline-danger ms-1" href="{{ route('sos') }}"><i class="bi bi-broadcast me-1" aria-hidden="true"></i>{{ __('alerts.report.tabs.sos') }}</a>
    </p>

    @include('public.signaler.partials.step-nav')
</section>
