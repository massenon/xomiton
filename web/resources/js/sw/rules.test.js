// Règles du service worker (P5-10, ADR-26) : exclusions (administration, API hors référentiels GET, tunnel d'accès,
// OTP, fichiers signés), pages gardées (liste, thématiques, jamais une recherche), réponses stockables, bibliothèque hors
// connexion (pages et fichiers des ressources enregistrées ou marquées), copie marquée, page minimale échappée avec ses
// numéros d'urgence en liens tel:, page rendue sans compte.
import { describe, expect, it } from 'vitest';
import {
  DEFAULT_PAGES, canStore, isExcluded, isGuestResponse, isReferential, isResourceFile, isResourcePage, isStoredPage,
  markOfflineCopy, offlineDocument, offlineResourcePaths, pageKey, samePath, telHref,
} from './rules.js';

const ORIGIN = 'https://xomiton.test';
const url = (path) => new URL(path, ORIGIN);
const response = (status = 200, headers = {}, extra = {}) => ({ status, type: 'basic', redirected: false, headers: new Headers(headers), ...extra });

describe('rules', () => {
  it('exclut l’administration, l’API, le tunnel d’accès, l’OTP et les fichiers privés', () => {
    ['/admin', '/admin/alertes', '/api/v1/sos', '/connexion', '/inscription/profil', '/deconnexion', '/mot-de-passe-oublie',
      '/nouveau-mot-de-passe', '/code-de-verification', '/otp', '/sanctum/csrf-cookie', '/livewire/update', '/media/local/x.pdf',
      '/build/assets/public.js', '/sw.js', '/up'].forEach((path) => expect(isExcluded(path), path).toBe(true));
    ['/', '/urgences', '/sos', '/signaler', '/signaler/confirmation', '/ressources', '/thematiques/consentement', '/administration-fiscale']
      .forEach((path) => expect(isExcluded(path), path).toBe(false));
  });

  it('ne garde que les référentiels lus en GET', () => {
    expect(isReferential('/api/v1/zones')).toBe(true);
    expect(isReferential('/api/v1/violence-types')).toBe(true);
    expect(isReferential('/api/v1/needs')).toBe(true);
    expect(isReferential('/api/v1/urgences')).toBe(true);
    expect(isReferential('/api/v1/zones', 'POST')).toBe(false);
    expect(isReferential('/api/v1/signalements')).toBe(false);
    expect(isReferential('/api/v1/me')).toBe(false);
  });

  it('garde les pages de crise et les thématiques consultées, jamais une adresse avec paramètres', () => {
    DEFAULT_PAGES.forEach((path) => expect(isStoredPage(url(path))).toBe(true));
    expect(DEFAULT_PAGES).toEqual(['/urgences', '/sos', '/signaler', '/annuaire', '/ressources']);
    expect(isStoredPage(url('/thematiques'))).toBe(true);
    expect(isStoredPage(url('/thematiques/violences-conjugales'))).toBe(true);
    expect(isStoredPage(url('/ressources?q=avortement'))).toBe(false);
    expect(isStoredPage(url('/sos?alerte=01J'))).toBe(false);
    expect(isStoredPage(url('/signaler/confirmation'))).toBe(false);
    expect(isStoredPage(url('/fil'))).toBe(false);
    expect(isStoredPage(url('/fil'), ['/fil'])).toBe(true);
    expect(pageKey(url('/ressources?q=x'))).toBe(`${ORIGIN}/ressources`);
  });

  it('distingue fiches et fichiers des ressources', () => {
    expect(isResourcePage('/ressources/plan-de-securite')).toBe(true);
    expect(isResourcePage('/ressources/plan-de-securite/lire')).toBe(false);
    expect(isResourceFile('/ressources/plan-de-securite/lire')).toBe(true);
    expect(isResourceFile('/ressources/plan-de-securite/telecharger')).toBe(true);
    expect(isResourceFile('/ressources/plan-de-securite')).toBe(false);
  });

  it('ne stocke qu’un 200 de même origine, sans redirection ni no-store (sauf page sans cookie ou ressource marquée)', () => {
    expect(canStore(response())).toBe(true);
    expect(canStore(response(200, { 'Cache-Control': 'no-cache, private' }))).toBe(true);
    expect(canStore(response(200, { 'Cache-Control': 'no-store, private' }))).toBe(false);
    expect(canStore(response(302))).toBe(false);
    expect(canStore(response(500))).toBe(false);
    expect(canStore(response(200, {}, { redirected: true }))).toBe(false);
    expect(canStore(response(0, {}, { type: 'opaqueredirect' }))).toBe(false);
    expect(canStore(response(200, {}, { type: 'opaque' }))).toBe(false);
    expect(canStore(response(200, { 'Cache-Control': 'no-store' }, { redirected: true }), { explicit: true })).toBe(true);
    expect(canStore(response(200, { 'Cache-Control': 'no-store, private' }), { anonymous: true })).toBe(true);
    expect(canStore(response(200, {}, { redirected: true }), { anonymous: true })).toBe(false);
    expect(canStore(null)).toBe(false);
  });

  it('liste les pages et fichiers à garder, de la même origine seulement', () => {
    const paths = offlineResourcePaths({
      items: {
        guide: { url: `${ORIGIN}/ressources/guide`, file: `${ORIGIN}/ressources/guide/telecharger`, title: 'Guide' },
        ailleurs: { url: 'https://autre.test/ressources/x', file: null },
        casse: null,
      },
      marked: [{ slug: 'plan', url: '/ressources/plan', file: '/ressources/plan/lire', title: 'Plan' }, { url: '/admin/x' }],
    }, ORIGIN);
    expect(paths).toEqual(['/ressources/guide', '/ressources/guide/telecharger', '/ressources/plan', '/ressources/plan/lire']);
    expect(offlineResourcePaths(undefined, ORIGIN)).toEqual([]);
    expect(samePath('nope', ORIGIN)).toBe('/nope');
    expect(samePath('', ORIGIN)).toBeNull();
  });

  it('marque une copie servie depuis le cache et échappe la page minimale', () => {
    expect(markOfflineCopy('<!DOCTYPE html>\n<html lang="fr" data-bs-theme="light">')).toBe('<!DOCTYPE html>\n<html data-xm-offline-copy lang="fr" data-bs-theme="light">');
    expect(markOfflineCopy('<html>')).toBe('<html data-xm-offline-copy>');
    const html = offlineDocument({ title: 'Hors connexion', text: '<b>Appeler</b> & attendre' });
    expect(html).toContain('<title>Hors connexion</title>');
    expect(html).toContain('&lt;b&gt;Appeler&lt;/b&gt; &amp; attendre');
    expect(html).not.toContain('<ul');
  });

  it('liste les numéros d’urgence en liens tel: (chiffres et « + » de tête), sans entrée vide', () => {
    expect(telHref('+229 01-90 00 00')).toBe('tel:+22901900000');
    expect(telHref('1 17')).toBe('tel:117');
    expect(telHref('—')).toBe('');
    const html = offlineDocument({ title: 'Hors connexion', text: 'En danger.', numbers: 'Numéros' }, [
      { label: 'Police <secours>', number: '117' }, { label: 'Vide', number: '' }, null,
    ]);
    expect(html).toContain('<h2 style="font-size:1.1rem">Numéros</h2>');
    expect(html).toContain('<a href="tel:117"');
    expect(html).toContain('Police &lt;secours&gt;&#160;: 117');
    expect(html).not.toContain('Vide');
  });

  it('reconnaît une page rendue sans compte (X-Xm-Audience: guest)', () => {
    expect(isGuestResponse(response(200, { 'X-Xm-Audience': 'guest' }))).toBe(true);
    expect(isGuestResponse(response(200))).toBe(false);
    expect(isGuestResponse(response(200, { 'X-Xm-Audience': 'member' }))).toBe(false);
    expect(isGuestResponse(null)).toBe(false);
  });
});
