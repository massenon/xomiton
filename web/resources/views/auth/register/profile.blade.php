{{-- Phase 2 « Profil » (facultative) : pronoms, département → commune en cascade (Alpine, données injectées), type de membre. --}}
<x-layouts.auth :seo="['title' => __('auth.register.title')]" image="hero-12.jpg" :max-width="520">
    <x-slot:side>@include('auth.partials.register-side')</x-slot:side>

    <h1 class="h2 fw-bold">{{ __('auth.register.heading') }}</h1>
    @include('auth.partials.stepper')
    @include('auth.partials.verify-banner')
    @include('auth.partials.status')
    <x-error-summary />

    @php
        $zoneValue = old('zone_id', $user?->zone_id);
        $communeValue = old('commune_id', $user?->commune_id);
    @endphp
    <form method="post" action="{{ route('inscription.profil.store') }}" novalidate
          x-data="{ zone: @js($zoneValue === null ? '' : (string) $zoneValue), commune: @js($communeValue === null ? '' : (string) $communeValue), communes: @js($communesByZone) }">
        @csrf
        <h2 class="h4 mb-1">{{ __('auth.register.profile.heading') }}</h2>
        <p class="text-body-secondary small">{{ __('auth.register.profile.intro') }}</p>

        <x-field name="pronouns" type="select" :label="__('auth.register.profile.pronouns')" optional :value="$profile?->pronouns"
                 :options="['' => __('auth.register.profile.pronouns_placeholder')] + __('auth.register.profile.pronouns_options')" />

        <div class="row g-3">
            <div class="col-sm-6">
                <div class="mb-3 xm-field">
                    <label class="form-label" for="f-zone_id">{{ __('auth.register.profile.zone') }} <span class="small text-secondary fw-normal">{{ __('components.field.optional') }}</span></label>
                    <select class="form-select {{ $errors->has('zone_id') ? 'is-invalid' : '' }}" id="f-zone_id" name="zone_id" x-model="zone" x-on:change="commune = ''" @if ($errors->has('zone_id')) aria-invalid="true" aria-describedby="f-zone_id-error" @endif>
                        <option value="">{{ __('auth.register.profile.choose') }}</option>
                        @foreach ($zones as $zone)
                            <option value="{{ $zone->getKey() }}" @selected((string) $zoneValue === (string) $zone->getKey())>{{ $zone->name }}</option>
                        @endforeach
                    </select>
                    @error('zone_id')<div id="f-zone_id-error" class="invalid-feedback d-block"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="col-sm-6">
                <div class="mb-3 xm-field">
                    <label class="form-label" for="f-commune_id">{{ __('auth.register.profile.commune') }} <span class="small text-secondary fw-normal">{{ __('components.field.optional') }}</span></label>
                    <select class="form-select {{ $errors->has('commune_id') ? 'is-invalid' : '' }}" id="f-commune_id" name="commune_id" x-model="commune" x-bind:disabled="zone === ''" @if ($errors->has('commune_id')) aria-invalid="true" aria-describedby="f-commune_id-error" @endif>
                        <option value="" x-text="zone === '' ? @js(__('auth.register.profile.choose_zone_first')) : @js(__('auth.register.profile.choose'))">{{ __('auth.register.profile.choose_zone_first') }}</option>
                        <template x-for="c in (communes[zone] || [])" :key="c.id">
                            <option :value="String(c.id)" x-text="c.name" :selected="String(c.id) === commune"></option>
                        </template>
                    </select>
                    @error('commune_id')<div id="f-commune_id-error" class="invalid-feedback d-block"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <x-field name="member_type" type="radio-group" :label="__('auth.register.profile.member_type')" :hint="__('auth.register.profile.member_type_hint')"
                 :value="$profile?->member_type?->value" :options="__('auth.register.profile.member_types')" />

        <div class="d-flex justify-content-between align-items-center mt-4 gap-2">
            <a class="btn btn-light" href="{{ route('fil') }}">{{ __('common.back') }}</a>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-link text-decoration-none" form="skip-profile">{{ __('auth.register.skip') }}</button>
                <x-button type="submit">{{ __('common.next') }}</x-button>
            </div>
        </div>
    </form>
    <form id="skip-profile" method="post" action="{{ route('inscription.passer', ['etape' => 'profile']) }}" class="d-none">@csrf</form>
</x-layouts.auth>
