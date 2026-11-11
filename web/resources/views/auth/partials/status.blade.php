{{-- Message de session (statut d'une étape précédente) : x-alert avec icône + texte, jamais la couleur seule. --}}
@if (session('status'))
    <x-alert :type="session('status_type', 'info')" class="mb-4">{{ session('status') }}</x-alert>
@endif
