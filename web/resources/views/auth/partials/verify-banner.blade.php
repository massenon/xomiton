{{--
    Bandeau « Confirme ton e-mail » (Q12 c) : affiché tant que users.email_verified_at est nul. Lien vers l'écran du code
    et bouton « Renvoyer le code » (POST otp.resend, purpose=signup, limiteur 'otp').
--}}
@if ($user !== null && $user->email_verified_at === null)
    <x-alert type="warning" icon="envelope-exclamation" :title="__('auth.verify_banner.title')" class="xm-verify-banner mb-4">
        <p class="mb-2">{{ __('auth.verify_banner.text') }}</p>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <a class="btn btn-sm btn-primary" href="{{ route('otp', ['purpose' => 'signup']) }}"><i class="bi bi-123 me-1" aria-hidden="true"></i>{{ __('auth.verify_banner.enter_code') }}</a>
            <form method="post" action="{{ route('otp.resend') }}" class="d-inline">
                @csrf
                <input type="hidden" name="purpose" value="signup">
                <button type="submit" class="btn btn-sm btn-light border"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>{{ __('auth.verify_banner.resend') }}</button>
            </form>
        </div>
    </x-alert>
@endif
