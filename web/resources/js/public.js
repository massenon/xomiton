// Paquet des pages publiques (Blade) : Bootstrap + Alpine pour l'interactivité légère.
// Aucune dépendance à Vue ni à Livewire ici (SEO, poids).
import 'bootstrap';
import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import './public/xomiton-ui.js';
import './shared/theme.js';

Alpine.plugin(focus);
window.Alpine = Alpine;
Alpine.start();
