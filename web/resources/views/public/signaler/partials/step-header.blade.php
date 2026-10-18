{{-- En-tête d'étape : « Étape n sur N » visible avec ou sans JavaScript, titre focalisé à chaque changement d'étape. --}}
<p class="small text-secondary fw-semibold mb-1">{{ __('alerts.report.step', ['current' => $n, 'total' => $total]) }}</p>
<h2 class="h5 fw-bold mb-3" id="etape-{{ $n }}-titre" tabindex="-1">{{ $steps[$n - 1] }}</h2>
