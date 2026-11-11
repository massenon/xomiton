{{--
    Page publique « Supprimer mon compte et mes données » `/supprimer-mon-compte` (AccountDeletionController::show ;
    textes lang/fr/pages/deletion.php). Indexable, accessible SANS connexion (exigence Google Play), vous. Procédure dans
    l'application et sur le web, délais, données effacées / conservées, puis formulaire de demande par e-mail (lien de
    vérification) pour qui n'a plus accès à son compte. Réponse du formulaire identique que le compte existe ou non.
    Variables : $content (title, lead, sections), $updatedAt, $seo.
--}}
@php
    $formatter = \App\Support\LocaleFormatter::make();
    $flash = session(\App\Http\Controllers\Web\Legal\AccountDeletionController::FLASH);
    $emailError = $errors->first('email');
    $confirmError = $errors->first('confirm');
@endphp
<x-layouts.public :seo="$seo">
    <section class="py-5 text-white xm-hero-bg xm-hero-page" aria-labelledby="legal-titre">
        <div class="container">
            <h1 class="fw-bold text-white" id="legal-titre">{{ $content['title'] }}</h1>
            <p class="mb-1 xm-hero-sub">{{ $content['lead'] }}</p>
            <p class="mb-0 small xm-hero-sub">{!! __('pages/legal.updated', ['date' => '<time datetime="'.e($updatedAt).'">'.e($formatter->longDate($updatedAt)).'</time>']) !!}</p>
        </div>
    </section>

    <div class="container py-5">
        <x-breadcrumb class="mb-4" :items="[['label' => __('seo.breadcrumb_home'), 'url' => route('home')], ['label' => $content['title']]]" />
        <div class="row g-4">
            <aside class="col-lg-3">
                <nav class="card xm-legal-nav" aria-label="{{ __('pages/legal.nav_label') }}">
                    <div class="list-group list-group-flush">
                        @foreach ($content['sections'] as $section)
                            <a class="list-group-item list-group-item-action" href="#{{ $section['id'] }}">{{ $section['title'] }}</a>
                        @endforeach
                        <a class="list-group-item list-group-item-action" href="#demande">{{ __('pages/deletion.form.nav') }}</a>
                    </div>
                </nav>
            </aside>

            <div class="col-lg-9 vstack gap-4">
                @foreach ($content['sections'] as $section)
                    <section class="card xm-legal-section" id="{{ $section['id'] }}" aria-labelledby="{{ $section['id'] }}-titre">
                        <div class="card-body p-4">
                            <h2 class="h4 mb-3" id="{{ $section['id'] }}-titre">{{ $section['title'] }}</h2>
                            @foreach ($section['blocks'] as $block)
                                @if (is_string($block))
                                    <p>{{ $block }}</p>
                                @elseif (isset($block['list']))
                                    <ul>@foreach ($block['list'] as $item)<li>{{ $item }}</li>@endforeach</ul>
                                @elseif (isset($block['ol']))
                                    <ol>@foreach ($block['ol'] as $item)<li>{{ $item }}</li>@endforeach</ol>
                                @elseif (isset($block['note']))
                                    <div class="alert alert-info"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>{{ $block['note'] }}</div>
                                @endif
                            @endforeach
                        </div>
                    </section>
                @endforeach

                <section class="card xm-legal-section" id="demande" aria-labelledby="demande-titre">
                    <div class="card-body p-4">
                        <h2 class="h4" id="demande-titre">{{ __('pages/deletion.form.title') }}</h2>
                        <p>{{ __('pages/deletion.form.intro', ['hours' => config('xomiton.accounts.deletion_request_hours', 24)]) }}</p>
                        @if ($flash)
                            <x-alert type="danger" id="deletionFlash" tabindex="-1" x-data x-init="$el.focus()">{{ $flash }}</x-alert>
                        @endif
                        @if ($errors->any())
                            <x-error-summary :messages="array_filter(['email' => $emailError, 'confirm' => $confirmError])" />
                        @endif
                        <form method="post" action="{{ route('account-deletion.request') }}" novalidate>
                            @csrf
                            <div class="visually-hidden" aria-hidden="true"><label for="delWebsite">{{ __('footer.newsletter_honeypot') }}</label><input id="delWebsite" name="website" tabindex="-1" autocomplete="off"></div>
                            <x-field name="email" type="email" :label="__('pages/deletion.form.email_label')" :hint="__('pages/deletion.form.email_hint')" :error="$emailError" :value="old('email')" autocomplete="email" inputmode="email" maxlength="190" required />
                            <div class="mb-3">
                                <div class="form-check">
                                    <input @class(['form-check-input', 'is-invalid' => $confirmError]) type="checkbox" id="del-confirm" name="confirm" value="1" required @checked(old('confirm')) @if ($confirmError) aria-invalid="true" aria-describedby="del-confirm-error" @endif>
                                    <label class="form-check-label" for="del-confirm">{{ __('pages/deletion.form.confirm_label', ['days' => config('xomiton.accounts.deletion_grace_days', 30)]) }}</label>
                                </div>
                                @if ($confirmError)<div id="del-confirm-error" class="invalid-feedback d-block"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>{{ $confirmError }}</div>@endif
                            </div>
                            <div class="d-grid d-sm-block">
                                <button class="btn btn-primary" type="submit"><i class="bi bi-envelope-check me-1" aria-hidden="true"></i>{{ __('pages/deletion.form.submit') }}</button>
                            </div>
                        </form>
                        <p class="small text-secondary mt-3 mb-0">{!! __('pages/deletion.form.privacy', ['url' => e(route('legal.privacy')).'#droits']) !!}</p>
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-layouts.public>
