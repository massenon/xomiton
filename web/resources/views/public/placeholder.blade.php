<x-dynamic-component :component="'layouts.'.$layout" :seo="['title' => $title, 'index' => false]">
    <section class="py-4 py-sm-5">
        <div class="container" style="max-width:720px">
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-tools display-5 text-primary" aria-hidden="true"></i>
                    <h1 class="h3 mt-3">{{ $title }}</h1>
                    <p class="text-body-secondary mb-0">{{ __('common.coming_soon_text', ['page' => $prototype]) }}</p>
                </div>
            </div>
        </div>
    </section>
</x-dynamic-component>
