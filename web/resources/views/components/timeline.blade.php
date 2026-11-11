{{--
    Chronologie d'un dossier (prototype admin/suivi.php « Frise de suivi » : ul.list-unstyled.mb-0.ms-2.border-start.border-2.ps-3 > li.mb-3.position-relative
    avec point coloré, étape en gras + badge de résultat, « date · par », commentaire, prochain suivi, « En retard » en rouge).
    Props :
      items : [[
          'title' => 'Entretien d’accueil', 'date' => '12/03/2026', 'datetime' => '2026-03-12', 'by' => 'Pair A.',
          'kind' => 'internal | shared | status' (note interne, note partagée, changement de statut : annoncé aux lecteurs d'écran, couleur du point),
          'badge' => ['label' => 'Réalisé', 'color' => 'success'], 'text' => 'Commentaire', 'next' => '20/03/2026', 'late' => false,
          'color' => 'primary' (couleur du point, prime sur kind),
          'action' => ['label' => 'Marquer réalisé', 'wire' => 'markDone(12)', 'sr' => 'pour Accueil'] (bouton Livewire facultatif sous l'étape),
        ], …]
      label : nom accessible de la liste
      empty : texte affiché sans étape
--}}
@props(['items' => [], 'label' => null, 'empty' => null])
@if (empty($items))
    <p {{ $attributes->class('text-secondary mb-0') }}>{{ $empty ?? __('components.timeline.empty') }}</p>
@else
    <ol {{ $attributes->class('xm-timeline list-unstyled mb-0 ms-2 border-start border-2 ps-3') }} aria-label="{{ $label ?? __('components.timeline.label') }}">
        @foreach ($items as $it)
            @php
                $kind = $it['kind'] ?? null;
                $kindColor = ['internal' => 'neutral', 'shared' => 'primary', 'status' => 'info'][$kind] ?? 'primary';
                $late = ! empty($it['late']);
                $color = $late ? 'danger' : ($it['color'] ?? $kindColor);
                $dot = $color === 'neutral' ? 'xm-timeline__dot--neutral' : "bg-$color";
            @endphp
            <li class="xm-timeline__item mb-3 position-relative">
                <span class="xm-timeline__dot position-absolute rounded-circle {{ $dot }}" aria-hidden="true"></span>
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <div>
                        @if ($kind)<span class="visually-hidden">{{ __("components.timeline.kinds.$kind") }} : </span>@endif
                        <span class="fw-semibold">{{ $it['title'] }}</span>
                        @if (! empty($it['badge']))<x-badge :color="$it['badge']['color'] ?? 'neutral'" class="ms-1">{{ $it['badge']['label'] }}</x-badge>@endif
                        @if ($late)<x-badge color="danger" class="ms-1">{{ __('components.timeline.late') }}</x-badge>@endif
                    </div>
                    @if (! empty($it['date']) || ! empty($it['by']))
                        <span class="small text-secondary">@if (! empty($it['date']))<time{!! ! empty($it['datetime']) ? ' datetime="'.e($it['datetime']).'"' : '' !!}>{{ $it['date'] }}</time>@endif @if (! empty($it['by']))· {{ $it['by'] }}@endif</span>
                    @endif
                </div>
                @if (! empty($it['text']))<div class="small">{{ $it['text'] }}</div>@endif
                @if (! empty($it['next']))<div class="small {{ $late ? 'text-danger fw-semibold' : 'text-secondary' }}"><i class="bi bi-calendar-event me-1" aria-hidden="true"></i>{{ __('components.timeline.next', ['date' => $it['next']]) }}</div>@endif
                @if (! empty($it['action']))<button type="button" class="btn btn-sm btn-outline-success py-0 mt-1" wire:click="{{ $it['action']['wire'] }}" wire:loading.attr="disabled">{{ $it['action']['label'] }}@if (! empty($it['action']['sr']))<span class="visually-hidden"> {{ $it['action']['sr'] }}</span>@endif</button>@endif
            </li>
        @endforeach
    </ol>
@endif
