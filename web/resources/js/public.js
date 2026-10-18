// Paquet des pages publiques (Blade) : Bootstrap (composants utilisés seulement, shared/bootstrap.js) + Alpine pour
// l'interactivité légère. Aucune dépendance à Vue ni à Livewire ici (SEO, poids) ; budget gzip < 60 Ko contrôlé par
// scripts/check-budgets.mjs au build (P4-12).
import './shared/bootstrap.js';
import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import './public/xomiton-ui.js';
import './public/auth.js';
import './public/assistant.js';
import './shared/theme.js';

Alpine.plugin(focus);
window.Alpine = Alpine;
Alpine.start();
