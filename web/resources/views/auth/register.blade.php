{{--
    /inscription : UN formulaire en 3 étapes obligatoires, rendu entièrement par le serveur et envoyé une seule fois à la fin
    (décision client du 11/11/2026 ; ancien formulaire du client). Le compte n'existe qu'à la fin.
    Amélioration progressive : sans JavaScript les trois étapes sont visibles à la suite et s'envoient d'un coup ; avec Alpine
    (xmRegister, resources/js/public/inscription.js) une étape à la fois, étape validée avant « Suivant » (mêmes règles que le
    serveur), « Précédent » toujours présent, focus sur le titre de l'étape, résumé d'erreurs focalisable + message sous chaque
    champ, données conservées ; à l'erreur serveur l'étape fautive est rouverte. Variables : $step (étape à ouvrir), $steps,
    $options (RegistrationOptions::payload), $zones, $communesByZone, $minAge, $invitationCode, $invitationRole.
--}}
@php
    $total = count($steps);
    $newTab = '<span class="visually-hidden"> '.e(__('microcopy.a11y.opens_new_tab')).'</span>';
    $options = $options ?? [];
    $toChoices = fn (array $list): array => collect($list)->mapWithKeys(fn (array $item): array => [$item['value'] => $item['label']])->all();
    $config = [
        'initial' => $step,
        'steps' => collect(\App\Http\Controllers\Auth\Support\RegistrationSteps::STEPS)->map(fn (string $key): array => ['key' => $key, 'fields' => \App\Http\Controllers\Auth\Support\RegistrationSteps::FIELDS[$key]])->all(),
        'minAge' => $minAge,
        'benin' => \App\Application\Shared\Countries::BENIN,
        'communes' => $communesByZone,
        'texts' => [
            'stepper' => ['done' => __('components.stepper.done'), 'current' => __('components.stepper.current'), 'todo' => __('components.stepper.todo')],
            'step_of' => __('auth.register.step_of', ['current' => ':current', 'total' => ':total']),
            'commune_placeholder' => __('auth.register.profile.choose'),
            'commune_first' => __('auth.register.profile.choose_zone_first'),
            'errors' => __('auth.register.errors'),
            'sending' => __('auth.register.sending'),
        ],
    ];
@endphp
<x-layouts.auth :seo="\App\Http\Controllers\Auth\Support\RegistrationSteps::seo()" image="hero-12.jpg" :max-width="520" compact decor>
    <x-slot:side>@include('auth.partials.register-side')</x-slot:side>

    <h1 class="h3 fw-bold mb-1">{{ __('auth.register.heading') }}</h1>
    <p class="text-body-secondary small mb-2">{{ __('auth.register.intro') }} <a href="{{ route('connexion') }}" class="fw-bold">{{ __('auth.register.sign_in') }}</a></p>

    @include('auth.partials.status')

    @if (! empty($invitationCode))
        <div class="alert alert-info d-flex gap-2" role="status"><i class="bi bi-envelope-heart-fill" aria-hidden="true"></i><div>{{ __('auth.register.invitation.banner.'.($invitationRole === 'pair' ? 'pair' : 'admin')) }}</div></div>
    @endif

    <div class="xm-register" x-data="xmRegister(@js($config))">
        {{-- « Étape n sur 3 » + stepper (aria-current="step") tenus à jour par Alpine ; données-compteur lues par les lecteurs d'écran. --}}
        <div class="xm-auth-progress" x-ref="progress">
            <p class="xm-auth-progress__count small text-body-secondary fw-bold text-uppercase mb-0" data-xm-step-counter x-text="counter">{{ __('auth.register.step_of', ['current' => $step, 'total' => $total]) }}</p>
            <x-stepper :steps="$steps" :current="$step" class="xm-stepper--compact" />
        </div>

        {{-- Résumé des erreurs du serveur : focalisé au chargement ; ses liens rouvrent l'étape du champ visé. --}}
        <div x-on:click="jump($event)">
            <x-error-summary />
        </div>
        {{-- Résumé des erreurs de l'étape (contrôle avant « Suivant ») : focalisable, un lien par champ. --}}
        <div class="alert alert-danger xm-error-summary" role="alert" tabindex="-1" x-ref="clientErrors" hidden x-bind:hidden="errorList.length === 0" x-on:click="jump($event)">
            <p class="fw-bold mb-2"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>{{ __('auth.register.client_errors') }}</p>
            <ul class="mb-0">
                <template x-for="item in errorList" :key="item.field">
                    <li><a class="alert-link" x-bind:href="'#' + item.target" x-text="item.message"></a></li>
                </template>
            </ul>
        </div>

        <form method="post" action="{{ route('inscription.store') }}" novalidate data-xm-auth-form x-ref="form" x-on:submit="submit($event)" x-on:change="onChange($event)">
            @csrf
            @if (! empty($invitationCode))
                <input type="hidden" name="invitation" value="{{ $invitationCode }}">
            @endif
            @error('invitation')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror

            {{-- Étape 1 : informations de base --}}
            <section class="xm-register__step" id="etape-1" aria-labelledby="etape-1-titre" x-show="isStep(1)">
                <h2 class="h5 mb-2" id="etape-1-titre" tabindex="-1">{{ __('auth.register.basic.heading') }}</h2>
                <div class="row gx-3">
                    <div class="col-md-6"><x-field name="first_name" :label="__('auth.register.basic.first_name')" autocomplete="given-name" maxlength="60" required :autofocus="! $errors->any()" /></div>
                    <div class="col-md-6"><x-field name="last_name" :label="__('auth.register.basic.last_name')" autocomplete="family-name" maxlength="60" required /></div>
                </div>
                <div class="row gx-3">
                    <div class="col-md-6"><x-field name="pseudo" :label="__('auth.register.basic.pseudo')" :hint="__('auth.register.basic.pseudo_hint')" autocomplete="username" maxlength="40" required /></div>
                    <div class="col-md-6"><x-field name="email" type="email" :label="__('auth.register.basic.email')" :hint="__('auth.register.basic.email_hint')" autocomplete="email" inputmode="email" maxlength="190" required /></div>
                </div>
                <div class="row gx-3">
                    <div class="col-md-6"><x-field name="age" type="number" :label="__('auth.register.basic.age')" :hint="__('auth.register.basic.age_hint', ['min_age' => $minAge])" inputmode="numeric" min="{{ $minAge }}" max="120" required /></div>
                </div>
            </section>

            {{-- Étape 2 : profil et identité --}}
            <section class="xm-register__step" id="etape-2" aria-labelledby="etape-2-titre" x-show="isStep(2)">
                <h2 class="h5 mb-2" id="etape-2-titre" tabindex="-1">{{ __('auth.register.profile.heading') }}</h2>
                <div class="row gx-3">
                    <div class="col-md-6"><x-field type="select" name="pronouns" :label="__('auth.register.profile.pronouns')" required
                        :options="['' => __('auth.register.profile.pronouns_placeholder')] + $toChoices($options['pronouns'])" /></div>
                    <div class="col-md-6"><x-field type="select" name="country_code" :label="__('auth.register.profile.country')" required autocomplete="country"
                        :value="old('country_code', \App\Application\Shared\Countries::BENIN)"
                        :options="['' => __('auth.register.profile.country_placeholder')] + $toChoices($options['countries'])" /></div>
                </div>
                <div class="row gx-3">
                    <div class="col-md-6"><x-field name="city" :label="__('auth.register.profile.city')" optional autocomplete="address-level2" maxlength="120" /></div>
                    <div class="col-md-6"><x-field name="phone" type="tel" :label="__('auth.register.profile.phone')" optional autocomplete="tel" inputmode="tel" maxlength="30" :hint="__('auth.register.profile.phone_hint')" /></div>
                </div>
                {{-- Département → commune : Bénin seulement (routage du SOS par zone). Sans JavaScript : toutes les communes, par département. --}}
                <div class="row gx-3" x-show="isBenin">
                    <div class="col-md-6"><x-field type="select" name="zone_id" :label="__('auth.register.profile.zone')" optional
                        :options="['' => __('auth.register.profile.choose')] + collect($zones)->mapWithKeys(fn (array $zone): array => [$zone['id'] => $zone['name']])->all()" /></div>
                    <div class="col-md-6">
                        @php($communeError = $errors->first('commune_id'))
                        <div class="mb-3 xm-field">
                            <label class="form-label" for="f-commune_id">{{ __('auth.register.profile.commune') }} <span class="small text-secondary fw-normal">{{ __('components.field.optional') }}</span></label>
                            <select @class(['form-select', 'is-invalid' => $communeError]) id="f-commune_id" name="commune_id" x-ref="commune" @if ($communeError) aria-invalid="true" aria-describedby="f-commune_id-error" @endif>
                                <option value="">{{ __('auth.register.profile.choose') }}</option>
                                @foreach ($zones as $zone)
                                    <optgroup label="{{ $zone['name'] }}">
                                        @foreach ($communesByZone[(string) $zone['id']] ?? [] as $commune)
                                            <option value="{{ $commune['id'] }}" @selected((string) old('commune_id') === (string) $commune['id'])>{{ $commune['name'] }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            @if ($communeError)<div id="f-commune_id-error" class="invalid-feedback d-block"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>{{ $communeError }}</div>@endif
                        </div>
                    </div>
                    <div class="col-12"><p class="form-text mt-0">{{ __('auth.register.profile.location_hint') }}</p></div>
                </div>
                <x-field type="radio-group" name="member_type" :label="__('auth.register.profile.member_type')" :options="$toChoices($options['member_types'])" />
            </section>

            {{-- Étape 3 : détails (selon le choix de l'étape 2) et sécurité --}}
            <section class="xm-register__step" id="etape-3" aria-labelledby="etape-3-titre" x-show="isStep(3)">
                <h2 class="h5 mb-2" id="etape-3-titre" tabindex="-1">{{ __('auth.register.details.heading') }}</h2>
                <p class="form-text mt-0" x-show="memberType === ''">{{ __('auth.register.details.both_hint') }}</p>

                <div x-show="memberType !== 'allie'" data-xm-block="membre">
                    <h3 class="h6 mb-1" x-show="memberType === ''">{{ __('auth.register.details.member_heading') }}</h3>
                    <div class="row gx-3">
                        <div class="col-md-6"><x-field type="select" name="gender_identity" :label="__('auth.register.details.gender_identity')"
                            :options="['' => __('auth.register.details.choose')] + $toChoices($options['gender_identities'])" /></div>
                        <div class="col-md-6" x-show="genderIdentity === 'autre' || memberType === ''"><x-field name="gender_identity_other" :label="__('auth.register.details.other')" maxlength="120" /></div>
                    </div>
                    <div class="row gx-3">
                        <div class="col-md-6"><x-field type="select" name="orientation" :label="__('auth.register.details.orientation')"
                            :options="['' => __('auth.register.details.choose')] + $toChoices($options['orientations'])" /></div>
                        <div class="col-md-6" x-show="orientation === 'autre' || memberType === ''"><x-field name="orientation_other" :label="__('auth.register.details.other')" maxlength="120" /></div>
                    </div>
                    <div class="xm-auth-checks">
                        <x-field type="checkbox" name="sensitive_data" :label="__('auth.register.details.sensitive_data')" />
                    </div>
                </div>

                <div x-show="memberType !== 'membre'" data-xm-block="allie">
                    <h3 class="h6 mb-1" x-show="memberType === ''">{{ __('auth.register.details.ally_heading') }}</h3>
                    <div class="row gx-3">
                        <div class="col-md-6"><x-field type="select" name="ally_role" :label="__('auth.register.details.ally_role')"
                            :options="['' => __('auth.register.details.ally_role_placeholder')] + $toChoices($options['ally_roles'])" /></div>
                        <div class="col-md-6" x-show="allyRole === 'autre' || memberType === ''"><x-field name="ally_role_other" :label="__('auth.register.details.ally_role_other')" maxlength="120" /></div>
                    </div>
                </div>

                <div class="row gx-3">
                    <div class="col-md-6"><x-field name="password" type="password" :label="__('auth.register.details.password')" autocomplete="new-password" required data-password-meter="#pwMeter" aria-describedby="pwMeter-rules" /></div>
                    <div class="col-md-6"><x-field name="password_confirmation" type="password" :label="__('auth.register.details.password_confirmation')" autocomplete="new-password" required /></div>
                </div>
                @include('auth.partials.password-meter', ['id' => 'pwMeter', 'compact' => true])

                <fieldset class="mt-3 xm-auth-checks">
                    <legend class="visually-hidden">{{ __('auth.register.details.consents') }}</legend>
                    <div class="form-check mb-1 xm-field">
                        <input class="form-check-input {{ $errors->has('cgu') ? 'is-invalid' : '' }}" type="checkbox" id="f-cgu" name="cgu" value="1" @checked(old('cgu')) @if ($errors->has('cgu')) aria-invalid="true" aria-describedby="f-cgu-error" @endif>
                        <label class="form-check-label" for="f-cgu">{!! __('auth.register.details.cgu', [
                            'terms' => '<a href="'.route('confidentialite').'#conditions" target="_blank" rel="noopener">'.e(__('auth.register.details.cgu_terms')).$newTab.'</a>',
                            'charter' => '<a href="'.route('confidentialite').'#charte" target="_blank" rel="noopener">'.e(__('auth.register.details.cgu_charter')).$newTab.'</a>',
                        ]) !!}</label>
                        @error('cgu')<div id="f-cgu-error" class="invalid-feedback d-block"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>{{ $message }}</div>@enderror
                    </div>
                    <div class="form-check mb-1 xm-field">
                        <input class="form-check-input {{ $errors->has('privacy') ? 'is-invalid' : '' }}" type="checkbox" id="f-privacy" name="privacy" value="1" @checked(old('privacy')) @if ($errors->has('privacy')) aria-invalid="true" aria-describedby="f-privacy-error" @endif>
                        <label class="form-check-label" for="f-privacy">{!! __('auth.register.details.privacy', [
                            'policy' => '<a href="'.route('confidentialite').'#confidentialite" target="_blank" rel="noopener">'.e(__('auth.register.details.privacy_policy')).$newTab.'</a>',
                        ]) !!}</label>
                        @error('privacy')<div id="f-privacy-error" class="invalid-feedback d-block"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>{{ $message }}</div>@enderror
                    </div>
                </fieldset>
            </section>

            {{-- « Précédent » toujours présent : à l'étape 1 il ramène à la connexion. « Suivant » et « Créer mon compte » : un seul visible avec JavaScript, seul le second sans. --}}
            <div class="xm-auth-actions">
                <a class="btn btn-light" href="{{ route('connexion') }}" x-on:click="previous($event)">{{ __('auth.register.previous') }}</a>
                <button type="button" class="btn btn-primary xm-auth-actions__primary" hidden x-bind:hidden="isLast()" x-on:click="next()">{{ __('auth.register.next') }}</button>
                <x-button type="submit" class="xm-auth-actions__primary" x-bind:hidden="! isLast()" x-bind:disabled="sending">{{ __('auth.register.submit') }}</x-button>
            </div>
        </form>
    </div>
</x-layouts.auth>
