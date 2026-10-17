{{-- Racine Inertia de l'espace membre et pair éducateur·rice (D9). Le gabarit 3 colonnes vit dans resources/js/Layouts/MemberLayout.vue. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    @include('partials.head', ['seo' => ['index' => false]])
    @routes(null, Illuminate\Support\Facades\Vite::cspNonce())
    @vite('resources/js/app.ts')
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
