{{--
    Étape 5 : choix explicite anonyme / contactable (signaler-et-sos.md), moyen de contact et canal préféré (SMS ·
    WhatsApp · appel · e-mail, ContactChannel) exigés seulement si la personne accepte d'être recontactée. Membre
    connecté·e : case « ne pas lier à mon compte » (cochée par défaut, RG-01 / RG-02), précédée d'un champ caché à 0.
--}}
<section class="xm-report__step card card-body mb-4" id="etape-{{ $n }}" aria-labelledby="etape-{{ $n }}-titre" x-show="isStep({{ $n }})">
    @include('public.signaler.partials.step-header')

    @auth
        <input type="hidden" name="anonymous" value="0">
        <x-field type="switch" name="anonymous" :label="__('alerts.report.fields.link_account')" :hint="__('alerts.report.fields.link_account_help')" :value="true" />
    @endauth

    <x-field type="radio-group" name="contactable" :label="__('alerts.report.fields.contactable')" value="0"
             :options="['0' => __('alerts.report.fields.contactable_no'), '1' => __('alerts.report.fields.contactable_yes')]" />

    <div class="xm-report__contact" x-show="contactable">
        <p class="small text-secondary" x-show="false">{{ __('alerts.report.fields.contact_only_if') }}</p>
        <x-field name="contact" :label="__('alerts.report.fields.contact')" :hint="__('alerts.report.fields.contact_hint')"
                 :placeholder="__('alerts.report.fields.contact_placeholder')" autocomplete="off" :maxlength="190" />
        <x-field type="radio-group" name="preferred_channel" :label="__('alerts.report.fields.preferred_channel')" :options="$options['channels']" />
    </div>

    @include('public.signaler.partials.step-nav')
</section>
