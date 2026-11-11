<p class="h2 fw-bold">{{ __('auth.register.side_title') }}</p>
<ul class="list-unstyled lead mb-0">
    @foreach (__('auth.register.side_points') as $point)
        <li class="mb-2"><i class="bi bi-check-circle-fill me-2" aria-hidden="true"></i>{{ $point }}</li>
    @endforeach
</ul>
