{{-- FAQ de la fiche (3 à 5 questions de la catégorie, plan §7) : même source que le JSON-LD FAQPage, panneaux fermés (open = -1 : null retomberait sur le défaut 0 de @props) mais présents dans le DOM. --}}
<section class="mt-4" id="fiche-faq-section" aria-labelledby="fiche-faq-titre">
    <h2 class="h5" id="fiche-faq-titre"><i class="bi bi-question-circle text-primary me-2" aria-hidden="true"></i>{{ __('pages/thematiques.faq_title') }}</h2>
    <x-accordion id="fiche-faq" level="h3" :open="-1" :items="$faqItems" />
    <p class="small mt-3 mb-0"><a href="{{ route('aide.index') }}">{{ __('buttons.discovery.help_center_all') }}</a></p>
</section>
