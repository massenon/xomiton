// Boutons « Images PNG » des pages publiques des conversations en BD (P20-09) : module chargé au premier clic par
// public.js (budget du paquet public). Le bouton porte `data-xm-bd-export="panels|strip"`, la zone des cases
// `data-xm-bd-export-root` (sélecteur), le titre et la source ; l'état est annoncé dans `[data-xm-bd-export-status]`
// (role="status"). En échec, le message renvoie vers le PDF (toujours disponible, produit par le serveur).
import { exportPng } from '../shared/bd-png';

export async function runExport(button) {
    const box = button.closest('[data-xm-bd-export-box]') || document;
    const status = box.querySelector('[data-xm-bd-export-status]');
    const root = document.querySelector(button.dataset.xmBdExportRoot || '#conversation') || document;
    const say = (key) => {
        if (status) status.textContent = button.dataset[key] || '';
    };
    if (button.getAttribute('aria-disabled') === 'true') return;
    button.setAttribute('aria-disabled', 'true');
    say('xmBdWorking');
    try {
        await exportPng(root, button.dataset.xmBdExport === 'strip' ? 'strip' : 'panels', button.dataset.xmBdTitle || document.title, button.dataset.xmBdSource || location.href);
        say('xmBdDone');
    } catch {
        say('xmBdFailed');
    } finally {
        button.removeAttribute('aria-disabled');
    }
}
