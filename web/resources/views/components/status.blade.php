{{--
    Statut de cas (COMPOSANTS §Badges : new bleu · assigned violet · in_progress info · pending_victim_response alerte · resolved vert · closed neutre · unresolved rouge),
    rendu par <x-badge scope="case"> : badge + icône + libellé en toutes lettres.
    Props : status (new | assigned | in_progress | pending_victim_response | resolved | closed | unresolved).
--}}
@props(['status' => 'new'])
<x-badge scope="case" :status="$status" {{ $attributes }} />
