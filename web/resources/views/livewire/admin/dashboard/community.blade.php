{{--
    Indicateurs communautaires (P18-01 à P18-03) : composant App\Livewire\Admin\Dashboard\Community, enfant du tableau de
    bord. Sondage propre toutes les 60 s (agrégats en cache 60 s) ; comptages seulement, jamais de contenu.
--}}
@php
    $kpis = [];

    if ($stats && $canMembers) {
        $retention = $stats['members']['retention'];
        $kpis[] = ['label' => __('admin.dashboard.community.members'), 'value' => $format->number($stats['members']['total']), 'icon' => 'people', 'variant' => 'violet', 'href' => route('admin.utilisateurs'),
                   'sub' => trans_choice('admin.dashboard.community.new_today', $stats['members']['today'], ['count' => $format->number($stats['members']['today'])])];
        $kpis[] = ['label' => __('admin.dashboard.community.retention'), 'value' => $retention ? $format->percent($retention['rate'], 0) : '—', 'icon' => 'arrow-repeat', 'variant' => 'success',
                   'sub' => $retention ? __('admin.dashboard.community.retention_sub', ['retained' => $format->number($retention['retained']), 'cohort' => $format->number($retention['cohort'])]) : __('admin.dashboard.community.retention_none')];
    }

    if ($stats && $canModeration) {
        foreach (['topics' => 'chat-square-text', 'replies' => 'chat-left-dots', 'messages' => 'envelope'] as $key => $icon) {
            $kpis[] = ['label' => __('admin.dashboard.community.'.$key), 'value' => $format->number($stats['exchanges'][$key]['today']), 'icon' => $icon, 'variant' => 'info',
                       'sub' => __('admin.dashboard.community.week', ['count' => $format->number($stats['exchanges'][$key]['week'])])];
        }

        $kpis[] = ['label' => __('admin.dashboard.community.reports'), 'value' => $format->number($stats['reports']['pending']), 'icon' => 'flag', 'variant' => 'danger', 'href' => route('admin.moderation'),
                   'sub' => trans_choice('admin.dashboard.community.high', $stats['reports']['high'], ['count' => $format->number($stats['reports']['high'])])];
        $resolution = $stats['resolution'];
        $kpis[] = ['label' => __('admin.dashboard.community.resolution'), 'value' => $resolution ? $format->percent($resolution['rate'], 0) : '—', 'icon' => 'check2-circle', 'variant' => 'success',
                   'sub' => $resolution ? __('admin.dashboard.community.resolution_sub', ['solved' => $format->number($resolution['solved']), 'topics' => $format->number($resolution['topics'])]) : __('admin.dashboard.community.resolution_none')];
    }
@endphp
<div wire:poll.{{ $pollSeconds }}s data-xm-dashboard-community>
    @if ($kpis !== [])
        <x-admin.kpi id="xmDashCommunity" :title="__('admin.dashboard.community.title')" cols="col-6 col-md-4 col-xl-3" :items="$kpis" />
        <p class="small text-secondary mt-n3 mb-4">{{ __('admin.dashboard.community.privacy', ['seconds' => $pollSeconds]) }}</p>
    @endif
</div>
