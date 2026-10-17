{{--
    Accordéon Bootstrap (prototype help.php : .accordion.accordion-flush > .accordion-item > .accordion-header > button.accordion-button[data-bs-toggle=collapse]).
    aria-expanded / aria-controls gérés par le balisage et Bootstrap Collapse ; un seul panneau ouvert à la fois (data-bs-parent).
    Props :
      items : [['q' => 'Question', 'a' => '<p>Réponse (HTML de confiance : lang ou contenu éditorial)</p>'], …]
      id    : identifiant unique (défaut : dérivé des items)
      level : balise de titre des questions (h2 | h3 | h4, défaut h3)
      open  : index du panneau ouvert au chargement (0 par défaut, null = tout fermé)
      flush : variante sans bordure externe (défaut true, comme la FAQ du prototype)
--}}
@props(['items', 'id' => null, 'level' => 'h3', 'open' => 0, 'flush' => true])
@php($id ??= 'acc'.substr(md5(json_encode($items)), 0, 6))
<div {{ $attributes->class(['accordion xm-accordion', 'accordion-flush' => $flush]) }} id="{{ $id }}">
    @foreach ($items as $i => $item)
        @php($isOpen = $open !== null && (int) $open === (int) $i)
        <div class="accordion-item">
            <{{ $level }} class="accordion-header" id="{{ $id }}-h{{ $i }}">
                <button @class(['accordion-button fw-semibold', 'collapsed' => ! $isOpen]) type="button" data-bs-toggle="collapse" data-bs-target="#{{ $id }}-p{{ $i }}" aria-expanded="{{ $isOpen ? 'true' : 'false' }}" aria-controls="{{ $id }}-p{{ $i }}">{{ $item['q'] }}</button>
            </{{ $level }}>
            <div id="{{ $id }}-p{{ $i }}" @class(['accordion-collapse collapse', 'show' => $isOpen]) data-bs-parent="#{{ $id }}" aria-labelledby="{{ $id }}-h{{ $i }}">
                <div class="accordion-body xm-prose">{!! $item['a'] !!}</div>
            </div>
        </div>
    @endforeach
</div>
