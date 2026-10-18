{{--
    Que faire / que ne pas faire (prototype violences.php l.101-122). L'annuaire complet est réservé aux membres (Q19) :
    un·e visiteur·se est orienté·e vers les contacts d'urgence publics de /urgences (mêmes fiches que l'API).
--}}
@php
    $link = static fn (string $href, string $label): string => '<a href="'.e($href).'">'.e($label).'</a>';
    $report = $link(route('signaler'), __('pages/violences.do.report'));
@endphp
<div class="row g-3">
    <div class="col-md-6">
        <section class="card card-body h-100 border-success" aria-labelledby="faire-titre">
            <h2 class="h6 text-success" id="faire-titre"><i class="bi bi-check-circle-fill me-1" aria-hidden="true"></i>{{ __('pages/violences.do.title') }}</h2>
            <ul class="small mb-0 ps-3 vstack gap-1">
                @foreach ((array) __('pages/violences.do.items') as $item)
                    <li>{{ $item }}</li>
                @endforeach
                @auth
                    <li>{!! __('pages/violences.do.contact_member', ['link' => $link(route('annuaire'), __('pages/violences.do.directory')), 'report' => $report]) !!}</li>
                @else
                    <li>{!! __('pages/violences.do.contact_guest', ['link' => $link(route('urgences').'#urgences-contacts', __('pages/violences.do.emergency_contacts')), 'report' => $report]) !!}</li>
                @endauth
                <li>{!! __('pages/violences.do.plan', ['link' => $link(route('urgences').'#plan', __('pages/violences.do.plan_link'))]) !!}</li>
            </ul>
        </section>
    </div>
    <div class="col-md-6">
        <section class="card card-body h-100 border-danger" aria-labelledby="ne-pas-faire-titre">
            <h2 class="h6 text-danger" id="ne-pas-faire-titre"><i class="bi bi-x-circle-fill me-1" aria-hidden="true"></i>{{ __('pages/violences.dont.title') }}</h2>
            <ul class="small mb-0 ps-3 vstack gap-1">
                @foreach ((array) __('pages/violences.dont.items') as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </section>
    </div>
</div>
