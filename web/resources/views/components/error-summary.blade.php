{{-- Résumé d'erreurs : focalisé après un envoi raté, un lien par champ. --}}
@if ($errors->any())
    <div class="alert alert-danger" role="alert" tabindex="-1" x-data x-init="$el.focus()">
        <p class="fw-bold mb-2"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> {{ trans_choice(':count erreur à corriger|:count erreurs à corriger', $errors->count()) }}</p>
        <ul class="mb-0">
            @foreach ($errors->keys() as $key)
                <li><a class="alert-link" href="#f-{{ str_replace(['[', ']', '.'], '-', $key) }}">{{ $errors->first($key) }}</a></li>
            @endforeach
        </ul>
    </div>
@endif
