// @vitest-environment jsdom
// Avis de la boîte d'envoi (P5-10) : signalement mis en file (toast), envoyé au rejeu (code de suivi affiché une fois,
// copie, impression, brouillon /signaler effacé), refusé (lien vers le formulaire) ; SOS envoyé (toast hors page SOS),
// refusé (avis d'alerte vers les contacts d'urgence) ; région live créée vide au montage. Textes reçus de Blade, insérés
// en textContent.
import 'fake-indexeddb/auto';
import { IDBFactory } from 'fake-indexeddb';
import { openDB } from 'idb';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { clearReportDraft, mountOutboxNotices } from './outbox-notices.js';

const TEXTS = {
  queued: 'Envoi enregistré', report_sent_title: 'Signalement envoyé', report_sent: 'Code de suivi :', report_keep: 'À conserver',
  report_failed_title: 'Signalement non envoyé', report_failed: 'Refusé', report_resume: 'Reprendre', report_url: '/signaler',
  sos_sent: 'Alerte SOS envoyée.', sos_failed_title: 'Alerte SOS non envoyée', sos_failed: 'Appeler', sos_contacts: 'Contacts',
  urgences_url: '/urgences', copy: 'Copier le code', copied: 'Code copié.', print: 'Imprimer', close: 'Fermer',
};

const flushed = (detail) => window.dispatchEvent(new CustomEvent('xm:outbox:flushed', { detail }));

beforeEach(() => {
  if (typeof window.__xmOutboxNotices === 'function') window.__xmOutboxNotices();
  document.body.innerHTML = '<main id="contenu"><h1>Page</h1></main>';
  window.XM = { toast: vi.fn() };
});

describe('mountOutboxNotices', () => {
  it('crée la région live vide dès le montage, avant tout avis', () => {
    mountOutboxNotices(TEXTS, { clearDraft: vi.fn() });
    const region = document.getElementById('xmOutboxNotices');
    expect(region).not.toBeNull();
    expect(region.getAttribute('aria-live')).toBe('polite');
    expect(region.children).toHaveLength(0);
    expect(document.querySelector('main').firstElementChild).toBe(region);
  });

  it('propose d’imprimer le code de suivi (P5-04 : copier et imprimer)', () => {
    const print = vi.spyOn(window, 'print').mockImplementation(() => undefined);
    mountOutboxNotices(TEXTS, { clearDraft: vi.fn() });
    flushed({ sent: [{ type: 'signalement', state: 'sent', data: { tracking_code: 'SIG1' } }], failed: [] });
    const button = [...document.querySelectorAll('#xmOutboxNotices button')].find((b) => b.textContent === 'Imprimer');
    button.click();
    expect(print).toHaveBeenCalledTimes(1);
    print.mockRestore();
  });

  it('annonce un signalement mis en file, pas un SOS (la page SOS l’affiche)', () => {
    mountOutboxNotices(TEXTS, { clearDraft: vi.fn() });
    window.dispatchEvent(new CustomEvent('xm:outbox:queued', { detail: { type: 'signalement' } }));
    window.dispatchEvent(new CustomEvent('xm:outbox:queued', { detail: { type: 'sos' } }));
    expect(window.XM.toast).toHaveBeenCalledTimes(1);
    expect(window.XM.toast).toHaveBeenCalledWith('Envoi enregistré', 'secondary');
  });

  it('affiche le code de suivi d’un signalement envoyé au rejeu et efface le brouillon', async () => {
    const clearDraft = vi.fn();
    const writeText = vi.fn(async () => undefined);
    Object.defineProperty(window.navigator, 'clipboard', { value: { writeText }, configurable: true });
    mountOutboxNotices(TEXTS, { clearDraft });
    flushed({ sent: [{ type: 'signalement', state: 'sent', data: { tracking_code: 'SIG123', tracking_code_display: 'SIG-123<b>' } }], failed: [] });

    const notice = document.querySelector('#xmOutboxNotices .alert-success');
    expect(notice.getAttribute('role')).toBe('status');
    expect(document.querySelector('main').firstElementChild.id).toBe('xmOutboxNotices');
    expect(notice.querySelector('[data-xm-outbox-code]').textContent).toBe('SIG-123<b>');
    expect(notice.querySelector('b')).toBeNull();
    expect(notice.textContent).toContain('Code de suivi :');
    expect(notice.textContent).toContain('À conserver');
    expect(clearDraft).toHaveBeenCalledTimes(1);

    notice.querySelector('button.btn-light').click();
    await Promise.resolve();
    await Promise.resolve();
    expect(writeText).toHaveBeenCalledWith('SIG-123<b>');
    expect(notice.querySelector('button.btn-light').textContent).toBe('Code copié.');
    notice.querySelector('.btn-close').click();
    expect(document.querySelector('#xmOutboxNotices .alert')).toBeNull();
  });

  it('signale un envoi refusé : signalement (reprise) et SOS (contacts d’urgence, role=alert)', () => {
    mountOutboxNotices(TEXTS, { clearDraft: vi.fn() });
    flushed({ sent: [], failed: [{ type: 'signalement', state: 'invalid', status: 422 }, { type: 'sos', state: 'failed', status: 403 }] });
    const [report, sos] = document.querySelectorAll('#xmOutboxNotices .alert');
    expect(report.className).toContain('alert-warning');
    expect(report.querySelector('a').getAttribute('href')).toBe('/signaler');
    expect(sos.className).toContain('alert-danger');
    expect(sos.getAttribute('role')).toBe('alert');
    expect(sos.querySelector('a').getAttribute('href')).toBe('/urgences');
  });

  it('annonce un SOS parti au rejeu, sauf sur la page SOS ou avec le bouton flottant', () => {
    mountOutboxNotices(TEXTS, { clearDraft: vi.fn() });
    flushed({ sent: [{ type: 'sos', state: 'sent', data: {} }], failed: [] });
    expect(window.XM.toast).toHaveBeenCalledWith('Alerte SOS envoyée.', 'success');
    document.body.insertAdjacentHTML('beforeend', '<div x-data="xmSosPage({})"></div>');
    flushed({ sent: [{ type: 'sos', state: 'sent', data: {} }], failed: [] });
    expect(window.XM.toast).toHaveBeenCalledTimes(1);
  });
});

describe('clearReportDraft', () => {
  it('efface le brouillon de signaler.js (même base, même magasin)', async () => {
    globalThis.indexedDB = new IDBFactory();
    const db = await openDB('xomiton-drafts', 1, { upgrade(d) { d.createObjectStore('drafts'); } });
    await db.put('drafts', { step: 2 }, 'signaler');
    db.close();
    await clearReportDraft();
    const again = await openDB('xomiton-drafts', 1);
    expect(await again.get('drafts', 'signaler')).toBeUndefined();
    again.close();
  });
});
