{{--
    « Signaler cette conversation » (P20-08) : création publiée d'un·e membre. Formulaire POST + @csrf sans JavaScript
    (ConversationsBdReportController), motifs du projet ; visiteur·se : lien de connexion ; sa propre création : message.
    Retour du serveur annoncé (role="status"). Ancre #signaler.
    Variables : $conversation.
--}}
@php($viewer = auth()->user())
<section class="card no-print" id="signaler" aria-labelledby="bdc-signaler-titre">
    <div class="card-body vstack gap-2">
        <h2 class="h6 mb-0" id="bdc-signaler-titre"><i class="bi bi-flag me-1" aria-hidden="true"></i>{{ __('pages/conversations_bd.report.title') }}</h2>
        @if (session('bdc_report'))
            <p class="alert alert-success mb-0" role="status">{{ session('bdc_report') }}</p>
        @elseif (! $viewer)
            <p class="mb-0 small">{{ __('pages/conversations_bd.report.login') }} <a href="{{ route('connexion') }}">{{ __('pages/conversations_bd.report.login_link') }}</a></p>
        @elseif ($conversation->isAuthoredBy($viewer))
            <p class="mb-0 small text-secondary">{{ __('pages/conversations_bd.report.own') }}</p>
        @else
            <details @if ($errors->any()) open @endif>
                <summary class="small">{{ __('pages/conversations_bd.report.text') }}</summary>
                <form method="post" action="{{ route('conversations-bd.report', ['slug' => $conversation->slug]) }}" class="vstack gap-2 mt-2" novalidate>
                    @csrf
                    <div>
                        <label class="form-label" for="bdc-report-reason">{{ __('pages/conversations_bd.report.reason') }}</label>
                        <select class="form-select @error('reason') is-invalid @enderror" id="bdc-report-reason" name="reason" required @error('reason') aria-invalid="true" aria-describedby="bdc-report-reason-error" @enderror>
                            <option value="">{{ __('pages/conversations_bd.report.reason_placeholder') }}</option>
                            @foreach (\App\Enums\ContentReportReason::selectable() as $value)
                                <option value="{{ $value }}" @selected(old('reason') === $value)>{{ \App\Enums\ContentReportReason::from($value)->label() }}</option>
                            @endforeach
                        </select>
                        @error('reason')<div class="invalid-feedback" id="bdc-report-reason-error">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="form-label" for="bdc-report-details">{{ __('pages/conversations_bd.report.details') }}</label>
                        <textarea class="form-control" id="bdc-report-details" name="details" rows="3" maxlength="500">{{ old('details') }}</textarea>
                    </div>
                    <p class="mb-0"><button type="submit" class="btn btn-outline-danger"><i class="bi bi-flag me-1" aria-hidden="true"></i>{{ __('pages/conversations_bd.report.submit') }}</button></p>
                </form>
            </details>
        @endif
    </div>
</section>
