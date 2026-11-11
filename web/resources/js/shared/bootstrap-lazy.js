// Composants Bootstrap à la demande (P4-12, ADR-18) : l'espace membre (app.ts) ne met plus shared/bootstrap.js (Bootstrap
// et Popper, ~18 Ko gzip) dans son paquet initial ; il le charge après le montage, ou au premier geste sur un
// déclencheur data-bs-* (app/bootstrapOnDemand.ts). Les pages publiques et l'administration l'importent toujours
// statiquement : shared/bootstrap.js s'annonce ici à son évaluation (provideBootstrap).
// Les modules partagés (sos-button.js, gabarit membre) lisent les composants par loadedBootstrap() quand il s'agit de
// fermer : une modale, un offcanvas ou un menu ouvert implique Bootstrap déjà chargé ; ils passent par loadBootstrap()
// pour ouvrir (toasts).

/** @typedef {{ Alert: any, Collapse: any, Dropdown: any, Modal: any, Offcanvas: any, Tab: any, Toast: any }} BootstrapComponents */

/** @type {BootstrapComponents | null} */
let components = null;
/** @type {Promise<BootstrapComponents> | null} */
let pending = null;

/**
 * Déclaré par shared/bootstrap.js une fois ses composants enregistrés (API déclarative data-bs-* comprise). Les tests
 * y passent de faux composants (espions) puis null pour revenir à « Bootstrap absent ».
 * @param {BootstrapComponents | null} loaded
 */
export function provideBootstrap(loaded) {
  components = loaded;
}

/** Composants déjà chargés, sinon null. @returns {BootstrapComponents | null} */
export function loadedBootstrap() {
  return components;
}

/**
 * Charge shared/bootstrap.js (une seule fois ; nouvel essai après un échec réseau) et rend ses composants.
 * @returns {Promise<BootstrapComponents>}
 */
export function loadBootstrap() {
  if (components) return Promise.resolve(components);
  pending ||= import('./bootstrap.js').then(
    (module) => components || module,
    (error) => {
      pending = null;
      throw error;
    },
  );
  return pending;
}
