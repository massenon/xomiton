{{--
    Squelette de chargement (placeholders Bootstrap du thème Social : .placeholder-glow > .placeholder ; UX_GUIDELINES §3 : à la forme du contenu,
    évite le décalage de mise en page ; UI_UX_DESIGN_SYSTEM §4.2 : squelettes de lignes plutôt qu'un spinner).
    Props :
      type  : lines (défaut) | card | avatar | kpi | table
      lines : nombre de lignes (type lines, défaut 3)
      rows  : nombre de lignes de tableau (type table, défaut 3)
      label : texte annoncé (défaut « Chargement… »)
    role="status" + aria-busy="true" + texte caché ; les blocs sont décoratifs (aria-hidden).
--}}
@props(['type' => 'lines', 'lines' => 3, 'rows' => 3, 'label' => null])
<div {{ $attributes->class(['xm-skeleton placeholder-glow', "xm-skeleton--$type"]) }} role="status" aria-busy="true">
    <span class="visually-hidden">{{ $label ?? __('components.skeleton.loading') }}</span>
    <div aria-hidden="true">
        @switch($type)
            @case('card')
                <div class="card h-100">
                    <span class="placeholder d-block w-100 xm-ratio-16x9 rounded-top"></span>
                    <div class="card-body">
                        <span class="placeholder col-4 rounded-pill mb-2"></span>
                        <p class="h5 card-title mb-2"><span class="placeholder col-8"></span></p>
                        <p class="mb-0"><span class="placeholder col-12"></span> <span class="placeholder col-10"></span> <span class="placeholder col-6"></span></p>
                    </div>
                </div>
                @break
            @case('avatar')
                <div class="d-flex align-items-center gap-2">
                    <span class="avatar"><span class="avatar-img rounded-circle placeholder d-block"></span></span>
                    <div class="flex-grow-1"><span class="placeholder col-6 d-block mb-1"></span><span class="placeholder col-4 d-block placeholder-sm"></span></div>
                </div>
                @break
            @case('kpi')
                <div class="card xm-stat h-100">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div class="w-75"><span class="placeholder col-8 d-block placeholder-sm mb-2"></span><span class="placeholder col-5 d-block placeholder-lg"></span></div>
                        <span class="placeholder rounded-circle xm-skeleton__icon"></span>
                    </div>
                </div>
                @break
            @case('table')
                @for ($i = 0; $i < $rows; $i++)
                    <div class="d-flex gap-3 py-2 border-bottom"><span class="placeholder col-2"></span><span class="placeholder col-3"></span><span class="placeholder col-2"></span><span class="placeholder col-2 ms-auto"></span></div>
                @endfor
                @break
            @default
                @for ($i = 0; $i < $lines; $i++)
                    <span class="placeholder d-block mb-2 col-{{ [12, 10, 8, 11, 7][$i % 5] }}"></span>
                @endfor
        @endswitch
    </div>
</div>
