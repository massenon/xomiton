{{-- Étape 2 : récit (20 à 4000 caractères, config xomiton.alerts) et date des faits (facultative, jamais future). --}}
<section class="xm-report__step card card-body mb-4" id="etape-{{ $n }}" aria-labelledby="etape-{{ $n }}-titre" x-show="isStep({{ $n }})">
    @include('public.signaler.partials.step-header')

    <x-field type="textarea" name="description" :label="__('alerts.report.fields.description')" :hint="__('alerts.report.fields.description_help')"
             :placeholder="__('alerts.report.fields.description_placeholder', ['min' => $limits['description_min']])"
             :maxlength="$limits['description_max']" :rows="6" required minlength="{{ $limits['description_min'] }}" />

    <x-field type="date" name="occurred_on" :label="__('alerts.report.fields.date')" :hint="__('alerts.report.fields.date_help')"
             :optional="true" max="{{ now()->toDateString() }}" />

    @include('public.signaler.partials.step-nav')
</section>
