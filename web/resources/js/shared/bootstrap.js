// Composants Bootstrap 5.3 réellement utilisés par les vues Blade (P4-12, ADR-18) : import sélectif depuis les sources
// ESM plutôt que `import 'bootstrap'` (bundle complet, ~25 Ko gzip) — Button, Carousel, Popover, ScrollSpy et Tooltip
// ne sont utilisés par aucune vue publique (grep data-bs-toggle / data-bs-ride / data-bs-spy). Chaque module enregistre
// lui-même son API déclarative (data-bs-*) à l'import ; l'administration importe ce fichier puis complète la liste.
import Alert from 'bootstrap/js/src/alert.js';
import Collapse from 'bootstrap/js/src/collapse.js';
import Dropdown from 'bootstrap/js/src/dropdown.js';
import Modal from 'bootstrap/js/src/modal.js';
import Offcanvas from 'bootstrap/js/src/offcanvas.js';
import Tab from 'bootstrap/js/src/tab.js';
import Toast from 'bootstrap/js/src/toast.js';

export { Alert, Collapse, Dropdown, Modal, Offcanvas, Tab, Toast };
