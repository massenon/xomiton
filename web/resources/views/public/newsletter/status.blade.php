{{--
    Pages à jeton de la newsletter (P4-07, vous, noindex ; prototype newsletter.php) : confirmation en deux temps
    (bouton puis POST), confirmée, lien expiré, désinscription avec motif facultatif, désinscrit·e, lien invalide.
    Variables : state (SubscriptionPageState), email, token, campaignId, ttlHours, title.
--}}
@use('App\Domain\Newsletter\SubscriptionPageState', 'State')
@php
    $strong = $email !== null ? '<strong>'.e($email).'</strong>' : '';
    $icons = [
        State::Confirm->value => 'envelope-heart text-primary',
        State::Confirmed->value => 'envelope-check text-success',
        State::Expired->value => 'hourglass-bottom text-warning',
        State::Unsubscribe->value => 'envelope-dash text-primary',
        State::Unsubscribed->value => 'check2-circle text-success',
        State::Invalid->value => 'link-45deg text-warning',
    ];
@endphp
<x-layouts.public :seo="$seo">
    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6">
                    <div class="card card-body p-4 p-sm-5 text-center" data-xm-newsletter-state="{{ $state->value }}">
                        <i class="bi bi-{{ $icons[$state->value] }} display-5 mb-2" aria-hidden="true"></i>
                        <h1 class="h3">{{ $title }}</h1>
                        @switch($state)
                            @case(State::Confirm)
                                <p class="text-secondary">{!! __('newsletter.public.confirm_text', ['email' => $strong]) !!}</p>
                                <form method="post" action="{{ route('newsletter.confirm.store', ['token' => $token]) }}">
                                    @csrf
                                    <div class="d-grid"><button class="btn btn-primary" type="submit"><i class="bi bi-check2 me-1" aria-hidden="true"></i>{{ __('newsletter.public.confirm_submit') }}</button></div>
                                </form>
                                @break
                            @case(State::Confirmed)
                                <p class="text-secondary">{!! __('newsletter.public.confirmed', ['email' => $strong]) !!}</p>
                                <p class="small text-secondary">{{ __('newsletter.public.confirmed_hint') }}</p>
                                <div><a class="btn btn-primary" href="{{ route('home') }}">{{ __('newsletter.public.back_home') }}</a></div>
                                @break
                            @case(State::Expired)
                                <p class="text-secondary">{{ __('newsletter.public.expired_link', ['hours' => $ttlHours]) }}</p>
                                <div><a class="btn btn-primary" href="{{ route('newsletter.show') }}#inscription">{{ __('newsletter.public.subscribe_again') }}</a></div>
                                @break
                            @case(State::Unsubscribe)
                                <p class="text-secondary">{!! __('newsletter.public.unsubscribe_text', ['email' => $strong]) !!}</p>
                                <form method="post" action="{{ route('newsletter.unsubscribe.store', ['token' => $token]) }}" class="text-start">
                                    @csrf
                                    @if ($campaignId)<input type="hidden" name="c" value="{{ $campaignId }}">@endif
                                    <label class="form-label small" for="nlReason">{{ __('newsletter.public.unsubscribe_reason') }}</label>
                                    <select class="form-select mb-3" id="nlReason" name="reason">
                                        <option value="">{{ __('newsletter.public.unsubscribe_reason_none') }}</option>
                                        @foreach (\App\Application\Newsletter\UnsubscribeFromNewsletter::REASONS as $reason)
                                            <option value="{{ $reason }}">{{ __('newsletter.public.unsubscribe_reasons.'.$reason) }}</option>
                                        @endforeach
                                    </select>
                                    <div class="d-grid"><button class="btn btn-danger" type="submit">{{ __('newsletter.public.unsubscribe_submit') }}</button></div>
                                </form>
                                @break
                            @case(State::Unsubscribed)
                                <p class="text-secondary">{!! __('newsletter.public.unsubscribed', ['email' => $strong]) !!}</p>
                                <div><a class="btn btn-primary-soft" href="{{ route('newsletter.show') }}#inscription">{{ __('newsletter.public.resubscribe') }}</a></div>
                                @break
                            @default
                                <p class="text-secondary">{{ __('newsletter.public.invalid_link') }}</p>
                                <div><a class="btn btn-primary" href="{{ route('home') }}#newsletter">{{ __('newsletter.public.back_home') }}</a></div>
                        @endswitch
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-layouts.public>
