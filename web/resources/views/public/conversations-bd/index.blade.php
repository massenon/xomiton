{{--
    Liste publique des conversations en BD `/conversations-bd` (P20-06, ConversationsBdIndexController) : conversations
    publiées seulement. Indexable, vous. Variables : $conversations, $catalog, $breadcrumb, $seo.
--}}
<x-layouts.public :seo="$seo">
    <div class="container py-4 py-sm-5">
        <div class="vstack gap-4">
            <x-breadcrumb :items="$breadcrumb" />
            <header class="vstack gap-2">
                <h1 class="h3 mb-0">{{ __('pages/conversations_bd.h1') }}</h1>
                <p class="lead fs-6 mb-0">{{ __('pages/conversations_bd.lead') }}</p>
                <p class="small text-secondary mb-0">{{ trans_choice('pages/conversations_bd.count', $conversations->count(), ['count' => $conversations->count()]) }} · <a href="{{ route('bd.index') }}">{{ __('pages/conversations_bd.comics_link') }}</a></p>
            </header>

            @if ($conversations->isEmpty())
                <x-empty-state :title="__('pages/conversations_bd.empty')" icon="chat-square-quote" />
            @else
                <section aria-labelledby="bdc-liste-titre">
                    <h2 class="visually-hidden" id="bdc-liste-titre">{{ __('pages/conversations_bd.list_label') }}</h2>
                    <ul class="list-unstyled row g-3 mb-0">
                        @foreach ($conversations as $conversation)
                            <li class="col-sm-6 col-lg-4 col-xl-3">@include('public.conversations-bd.card', ['conversation' => $conversation, 'heading' => 'h3', 'priority' => $loop->first])</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <section class="card border-primary" aria-labelledby="bdc-aide-titre">
                <div class="card-body d-flex flex-column flex-md-row gap-3 align-items-md-center">
                    <h2 class="h6 mb-0 flex-grow-1" id="bdc-aide-titre">{{ __('pages/conversations_bd.help_title') }}</h2>
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-sm btn-primary" href="{{ route('signaler') }}"><i class="bi bi-megaphone me-1" aria-hidden="true"></i>{{ __('pages/conversations_bd.help_report') }}</a>
                        <a class="btn btn-sm btn-primary-soft" href="{{ route('sos') }}"><i class="bi bi-broadcast me-1" aria-hidden="true"></i>{{ __('pages/conversations_bd.help_sos') }}</a>
                        <a class="btn btn-sm btn-primary-soft" href="{{ route('urgences') }}"><i class="bi bi-telephone-fill me-1" aria-hidden="true"></i>{{ __('pages/conversations_bd.help_emergency') }}</a>
                        <a class="btn btn-sm btn-primary-soft" href="{{ route('aide.index') }}"><i class="bi bi-life-preserver me-1" aria-hidden="true"></i>{{ __('pages/conversations_bd.help_center') }}</a>
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-layouts.public>
