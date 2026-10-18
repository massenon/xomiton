{{-- Que dit la loi ? (prototype violences.php l.124-134) : renvoi vers /lois. --}}
<section class="card card-body" aria-labelledby="loi-titre">
    <div class="d-flex align-items-start gap-3">
        <span class="icon-lg bg-info bg-opacity-10 text-info rounded-circle flex-shrink-0" aria-hidden="true"><i class="bi bi-bank"></i></span>
        <div>
            <h2 class="h5" id="loi-titre">{{ __('pages/violences.law.title') }}</h2>
            <p class="small mb-2">{!! __('pages/violences.law.text', ['law' => '<strong>'.e(__('pages/violences.law.law_name')).'</strong>']) !!}</p>
            <a class="btn btn-sm btn-info-soft" href="{{ route('lois') }}">{{ __('pages/violences.law.cta') }} <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i></a>
        </div>
    </div>
</section>
