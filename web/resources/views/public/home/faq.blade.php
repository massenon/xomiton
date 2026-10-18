{{-- Section 9 — FAQ #faq (prototype index.php l.359-375) : 5–6 questions « accueil » de la base (Q16), même source que le JSON-LD FAQPage. --}}
<section class="lp-section" id="faq" aria-labelledby="faqTitre">
    <div class="container lp-narrow">
        <div class="text-center mb-5">
            <div class="lp-eyebrow mb-2">{{ __('pages/accueil.faq.eyebrow') }}</div>
            <h2 id="faqTitre" class="lp-title">{{ __('pages/accueil.faq.title') }}</h2>
        </div>
        <x-accordion id="lpFaq" level="h3" :open="0" :flush="false" :items="$faqItems" />
        <div class="text-center mt-4">
            <x-button variant="primary-soft" :href="route('aide.index')">{{ __('buttons.discovery.help_center_all') }}</x-button>
        </div>
    </div>
</section>
