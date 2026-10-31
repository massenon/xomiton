{{--
    Navigation sans rechargement perceptible (docs/PERFORMANCE.md §11) : « Speculation Rules » (Chrome, Edge, Opera).
      - prefetch, eagerness `moderate` : la page est demandée au survol d'un lien (~200 ms) ou sur indication du navigateur ;
      - prerender, eagerness `conservative` : la page est préparée au toucher / clic (pointerdown), à peine avant l'ouverture,
        pour ne pas peser sur le processeur d'un téléphone d'entrée de gamme.
    Liste BLANCHE de chemins publics en lecture seule (config xomiton.speculation) : aucune route SOS, signalement,
    urgences, compte, authentification, API ni POST ; ni lien `rel=nofollow`, `download`, `target`, ni `data-no-prefetch`.
    Le navigateur n'applique rien avec « économiser les données », en économie d'énergie ni en mémoire basse.
    CSP : un script `speculationrules` est soumis à script-src, il porte donc le nonce de la requête.
    Le repli (shared/prefetch.js, Safari / Firefox) relit CE JSON : une seule liste à maintenir.
--}}
@if (config('xomiton.speculation.enabled', true))
    @php
        $specExclude = (array) config('xomiton.speculation.exclude', []);
        $specWhere = ['and' => array_values(array_filter([
            ['href_matches' => array_values((array) config('xomiton.speculation.allow', []))],
            $specExclude !== [] ? ['not' => ['href_matches' => array_values($specExclude)]] : null,
            ['not' => ['selector_matches' => '[rel~=nofollow], [download], [target], [data-no-prefetch]']],
        ]))];
        $specRules = [
            'prefetch' => [['where' => $specWhere, 'eagerness' => 'moderate']],
            'prerender' => [['where' => $specWhere, 'eagerness' => 'conservative']],
        ];
    @endphp
    <script type="speculationrules" nonce="{{ Illuminate\Support\Facades\Vite::cspNonce() }}">{!! json_encode($specRules, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endif
