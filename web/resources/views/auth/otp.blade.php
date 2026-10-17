{{--
    /code-de-verification (LOGIN §5) : écran unique purpose = login | signup | reset. 6 cases numériques
    (inputmode="numeric", autocomplete="one-time-code" sur la première), collage réparti, retour arrière, renvoi après 30 s
    (compte à rebours, resources/js/public/auth.js). Erreur indifférenciée (MICROCOPY §4).
--}}
<x-layouts.auth :seo="['title' => __('auth.otp_screen.title')]" image="sign-in.jpg" :max-width="440">
    <h1 class="h2 fw-bold">{{ __('auth.otp_screen.heading') }}</h1>
    <p class="text-body-secondary mb-1">{{ __('auth.otp_screen.purpose.'.$purpose->value) }}</p>
    <p class="text-body-secondary">{{ __('auth.otp_screen.intro', ['email' => $maskedEmail, 'minutes' => $ttlMinutes, 'length' => $length]) }}</p>

    @include('auth.partials.status')
    @if ($errors->any())
        <x-alert type="danger" class="mb-4" id="f-code-error" x-data x-init="$el.focus()" tabindex="-1">{{ $errors->first() }}</x-alert>
    @endif

    <form method="post" action="{{ route('otp.verify') }}" novalidate data-xm-otp-form>
        @csrf
        <input type="hidden" name="purpose" value="{{ $purpose->value }}">
        <fieldset class="mb-4" data-xm-otp>
            <legend class="form-label">{{ __('auth.otp_screen.code_label', ['length' => $length]) }}</legend>
            <div class="xm-otp d-flex gap-2">
                @for ($i = 0; $i < $length; $i++)
                    <input type="text" class="form-control form-control-lg text-center xm-otp__digit {{ $errors->has('code') ? 'is-invalid' : '' }}"
                           name="code[]" id="{{ $i === 0 ? 'f-code' : 'f-code-'.$i }}" inputmode="numeric" pattern="[0-9]*" maxlength="{{ $i === 0 ? $length : 1 }}"
                           autocomplete="{{ $i === 0 ? 'one-time-code' : 'off' }}" aria-label="{{ __('auth.otp_screen.digit', ['n' => $i + 1, 'length' => $length]) }}"
                           @if ($errors->has('code')) aria-invalid="true" aria-describedby="f-code-error" @endif @if ($i === 0) autofocus @endif required>
                @endfor
            </div>
        </fieldset>
        <div class="d-grid"><x-button type="submit" size="lg">{{ __('auth.otp_screen.submit') }}</x-button></div>
    </form>

    <form method="post" action="{{ route('otp.resend') }}" class="text-center mt-4" data-xm-otp-resend>
        @csrf
        <input type="hidden" name="purpose" value="{{ $purpose->value }}">
        <p class="small text-body-secondary mb-2">{{ __('auth.otp_screen.not_received') }}</p>
        <button type="submit" class="btn btn-light border" data-resend-in="{{ $resendInSeconds }}" data-label="{{ __('auth.otp_screen.resend') }}" data-label-wait="{{ __('auth.otp_screen.resend_in', ['seconds' => ':seconds']) }}" @disabled($resendInSeconds > 0)>
            <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i><span data-resend-label>{{ $resendInSeconds > 0 ? __('auth.otp_screen.resend_in', ['seconds' => $resendInSeconds]) : __('auth.otp_screen.resend') }}</span>
        </button>
        <span class="visually-hidden" aria-live="polite" data-resend-live></span>
    </form>

    <p class="text-center small mt-4 mb-0">
        @if ($purpose === \App\Enums\OtpPurpose::Signup)
            <a href="{{ route('fil') }}">{{ __('auth.otp_screen.later') }}</a>
        @else
            <a href="{{ route('connexion') }}">← {{ __('auth.otp_screen.back_to_login') }}</a>
        @endif
    </p>
</x-layouts.auth>
