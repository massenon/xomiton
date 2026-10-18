{{--
    Étape 6 : récapitulatif relisible (JavaScript ; « Modifier » rouvre l'étape) puis un seul bouton primaire, bleu,
    « Envoyer mon signalement » (signaler-et-sos.md : pas de rouge). Sans JavaScript : relecture des étapes ci-dessus.
--}}
<section class="xm-report__step card card-body mb-4" id="etape-{{ $n }}" aria-labelledby="etape-{{ $n }}-titre" x-show="isStep({{ $n }})">
    @include('public.signaler.partials.step-header')

    <p class="mb-3" x-show="false">{{ __('alerts.report.review.nojs') }}</p>
    <div hidden x-bind:hidden="false">
        <p class="mb-3">{{ __('alerts.report.review.text') }}</p>
        {{-- dl > div > dt + dd (HTML : un div par groupe) ; le bouton « Modifier » est dans la définition. --}}
        <dl class="xm-report__summary mb-0">
            <template x-for="row in summary" :key="row.key">
                <div class="py-2 border-bottom">
                    <dt class="small text-secondary fw-normal" x-text="row.label"></dt>
                    <dd class="mb-0 d-flex justify-content-between align-items-start gap-3">
                        <span class="text-break" x-text="row.value"></span>
                        <button type="button" class="btn btn-link btn-sm px-0 flex-shrink-0" x-on:click="edit(row.step)" x-bind:aria-label="row.edit">{{ __('buttons.edit') }}</button>
                    </dd>
                </div>
            </template>
        </dl>
    </div>

    <div class="d-flex flex-wrap justify-content-between gap-2 mt-4">
        <button type="button" class="btn btn-light border" hidden x-bind:hidden="false" x-on:click="back()"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ __('common.back') }}</button>
        <button type="submit" class="btn btn-primary btn-lg ms-auto" id="f-send" x-bind:aria-busy="sending ? 'true' : 'false'">
            <i class="bi bi-send me-1" aria-hidden="true"></i>{{ __('buttons.help.send_report') }}
        </button>
    </div>
</section>
