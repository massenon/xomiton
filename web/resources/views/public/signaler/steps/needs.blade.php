{{--
    Étape 3 : besoins (obligatoires pour « Exprimer un besoin », FR-ALT-02), département (routage RG-04) → commune
    (liste groupée par département sans JavaScript ; cascade depuis GET /api/v1/zones avec Alpine, CommuneInZone côté
    serveur), position facultative (bouton, JavaScript seulement).
--}}
@php
    $communeError = $errors->first('commune_id');
    $communeValue = (string) old('commune_id', '');
    $positionError = $errors->first('lat') ?: $errors->first('lng') ?: $errors->first('accuracy');
@endphp
<section class="xm-report__step card card-body mb-4" id="etape-{{ $n }}" aria-labelledby="etape-{{ $n }}-titre" x-show="isStep({{ $n }})">
    @include('public.signaler.partials.step-header')

    <x-field type="checkbox-group" name="needs" :label="__('alerts.report.fields.needs')" :hint="__('alerts.report.fields.needs_hint')"
             :error="$errors->first('needs') ?: $errors->first('needs.*')" :options="$options['needs']" />

    <x-field type="select" name="zone_id" :label="__('alerts.report.fields.zone')" required
             :options="['' => __('alerts.report.fields.zone_placeholder')] + collect($options['zones'])->mapWithKeys(fn (array $zone): array => [$zone['id'] => $zone['name']])->all()" />

    <div class="mb-3 xm-field">
        <label class="form-label" for="f-commune_id">{{ __('alerts.report.fields.commune') }} <span class="small text-secondary fw-normal">{{ __('components.field.optional') }}</span></label>
        <select @class(['form-select', 'is-invalid' => $communeError]) id="f-commune_id" name="commune_id" x-ref="commune"
                @if ($communeError) aria-invalid="true" aria-describedby="f-commune_id-error" @endif>
            <option value="">{{ __('alerts.report.fields.commune_placeholder') }}</option>
            @foreach ($options['zones'] as $zone)
                <optgroup label="{{ $zone['name'] }}" data-zone="{{ $zone['id'] }}">
                    @foreach ($zone['communes'] as $id => $name)
                        <option value="{{ $id }}" @selected($communeValue === (string) $id)>{{ $name }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
        @if ($communeError)<div id="f-commune_id-error" class="invalid-feedback d-block"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>{{ $communeError }}</div>@endif
    </div>

    <input type="hidden" name="lat" value="{{ old('lat') }}">
    <input type="hidden" name="lng" value="{{ old('lng') }}">
    <input type="hidden" name="accuracy" value="{{ old('accuracy') }}">
    {{-- #f-position : cible des erreurs lat, lng et accuracy (ReportSteps::SUMMARY_TARGETS), visible même sans géolocalisation. --}}
    <div id="f-position" tabindex="-1" @if ($positionError) aria-describedby="f-position-error" @endif>
        <div class="xm-report__position" hidden x-bind:hidden="! geo">
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-primary-soft" x-show="! position" x-on:click="locate()"><i class="bi bi-geo-alt me-1" aria-hidden="true"></i>{{ __('buttons.help.attach_position') }}</button>
                <button type="button" class="btn btn-light border" x-show="position" style="display: none" x-on:click="clearPosition()"><i class="bi bi-x-lg me-1" aria-hidden="true"></i>{{ __('alerts.report.fields.position_remove') }}</button>
            </div>
            <p class="small text-secondary mt-2 mb-0" role="status" x-text="positionText">{{ __('alerts.report.fields.position_help') }}</p>
        </div>
        @if ($positionError)<p id="f-position-error" class="invalid-feedback d-block mb-0"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>{{ $positionError }}</p>@endif
    </div>

    @include('public.signaler.partials.step-nav')
</section>
