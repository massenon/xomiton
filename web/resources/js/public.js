// Paquet des pages publiques (Blade) : Bootstrap (composants utilisés seulement, shared/bootstrap.js) + Alpine pour
// l'interactivité légère. Aucune dépendance à Vue ni à Livewire ici (SEO, poids) ; budget gzip < 60 Ko contrôlé par
// scripts/check-budgets.mjs au build (P4-12).
import './shared/bootstrap.js';
import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import './public/xomiton-ui.js';
import './public/load-more.js';
import './public/ressources.js';
import './public/share.js';
import './public/auth.js';
import './public/assistant.js';
import './public/alertes-dispatch.js';
import './shared/theme.js';
// Hors connexion (P5-10, ADR-26) : amorce de la boîte d'envoi (outbox.js chargé à la demande) et service worker.
import './shared/outbox-boot.js';
import './shared/sw-register.js';

Alpine.plugin(focus);
window.Alpine = Alpine;

// Formulaire /signaler et sa confirmation (P5-04) : module chargé à la demande (budget du paquet public), avant le
// démarrage d'Alpine qui doit connaître xmReport / xmReportSent. Si le chargement échoue (2G, hors ligne sans précache),
// Alpine démarre quand même mais ignore ces blocs (x-ignore) : la page reste le formulaire complet sans JavaScript,
// au lieu d'une navigation pas à pas révélée et inerte.
// Page /sos et bouton SOS flottant (P5-05, P5-08) : sos.js chargé de même ; en échec, ces blocs sont ignorés et le
// bouton flottant reste le lien vers /sos (x-sos-fab).
const lazyModules = [
  ['[x-data^="xmReport"]', () => import('./public/signaler.js')],
  ['[x-data^="xmSosPage"], [x-data^="xmSosFab"]', () => import('./public/sos.js')],
  // P4-04 : auto-évaluation /violences, recherche et version simple /lois, quiz /do-glegbe (sans réseau ni envoi).
  ['[x-data^="xmSelfCheck"]', () => import('./public/violences.js')],
  ['[x-data^="xmLaws"]', () => import('./public/lois.js')],
  ['[x-data^="xmQuiz"]', () => import('./public/do-glegbe.js')],
].filter(([selector]) => document.querySelector(selector));

if (lazyModules.length > 0) {
  Promise.allSettled(lazyModules.map(([selector, load]) => load().catch((error) => {
    document.querySelectorAll(selector).forEach((el) => el.setAttribute('x-ignore', ''));
    throw error;
  }))).finally(() => Alpine.start());
} else {
  Alpine.start();
}
