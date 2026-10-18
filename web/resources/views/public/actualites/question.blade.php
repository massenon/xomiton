{{-- « Une question ? » (prototype blog.php l.53-58) : illustration, centre d'aide. Un seul bouton, en doux (CTA_LIBRARY.md). --}}
<section class="card" id="actualites-question" aria-labelledby="actualites-question-titre">
    <div class="card-body text-center">
        <x-img class="mb-2 xm-news-character" :src="asset('assets/images/xomiton/personnage-2.png')" :alt="__('pages/actualites.question_alt')" width="95" height="90" />
        <h2 class="h6" id="actualites-question-titre">{{ __('pages/actualites.question_title') }}</h2>
        <p class="small text-secondary">{{ __('pages/actualites.question_text') }}</p>
        <a class="btn btn-primary-soft btn-sm" href="{{ route('aide.index') }}">{{ __('pages/actualites.question_cta') }}</a>
    </div>
</section>
