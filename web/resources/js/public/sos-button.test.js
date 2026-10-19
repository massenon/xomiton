// @vitest-environment jsdom
// Composant Alpine xmSos (<x-sos-button>, P5-05 / P5-08) testé sans Alpine : la fabrique renvoie un objet simple.
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { DOUBLE_TAP_MS, TAP_MAX_MS, closeOverlays, xmSos } from './sos-button.js';
import { provideBootstrap } from '../shared/bootstrap-lazy.js';

const make = (opts = {}) => {
  const c = xmSos({ action: 'sos-send', texts: { sending: 'Envoi…', countdownTitle: 'Envoi de ton alerte…', confirmTitle: 'Envoyer ?' }, ...opts });
  c.$dispatch = vi.fn();
  c.$nextTick = (fn) => fn();
  c.$root = document.createElement('div');
  return c;
};

describe('xmSos', () => {
  beforeEach(() => { vi.useFakeTimers(); });
  afterEach(() => { vi.useRealTimers(); document.body.innerHTML = ''; });

  it('lance le décompte après 2 s de maintien et envoie au bout de 5 s, jamais avant', () => {
    const c = make();
    c.start();
    vi.advanceTimersByTime(1999);
    expect(c.counting).toBe(false);
    vi.advanceTimersByTime(1);
    expect(c.counting).toBe(true);
    expect(c.left).toBe(5);
    vi.advanceTimersByTime(4000);
    expect(c.$dispatch).not.toHaveBeenCalled();
    vi.advanceTimersByTime(1000);
    expect(c.$dispatch).toHaveBeenCalledWith('sos-send');
    expect(c.done).toBe(true);
  });

  it('annule pendant le décompte : rien ne part', () => {
    const c = make();
    c.begin();
    vi.advanceTimersByTime(3000);
    c.abort();
    vi.advanceTimersByTime(10000);
    expect(c.$dispatch).not.toHaveBeenCalled();
    expect(c.open).toBe(false);
  });

  it('un maintien interrompu ne déclenche rien', () => {
    const c = make();
    c.start();
    vi.advanceTimersByTime(1500);
    c.release();
    vi.advanceTimersByTime(5000);
    expect(c.counting).toBe(false);
    expect(c.confirming).toBe(false);
  });

  it('double appui → confirmation ; deux appuis espacés ne confirment pas', () => {
    const c = make();
    c.start(); vi.advanceTimersByTime(TAP_MAX_MS - 100); c.release();
    vi.advanceTimersByTime(DOUBLE_TAP_MS + 50);
    c.start(); vi.advanceTimersByTime(100); c.release();
    expect(c.confirming).toBe(false);
    vi.advanceTimersByTime(100);
    c.start(); vi.advanceTimersByTime(100); c.release();
    expect(c.confirming).toBe(true);
    c.begin();
    expect(c.counting).toBe(true);
  });

  it('activation sans pointeur (lecteur d’écran, detail 0) → confirmation ; un clic souris seul ne fait rien', () => {
    const c = make();
    c.activate({ detail: 1 });
    expect(c.confirming).toBe(false);
    c.activate({ detail: 0 });
    expect(c.confirming).toBe(true);
  });

  it('clavier seul : Entrée → confirmation → « Envoyer » → envoi au terme des 5 s', () => {
    const c = make({ managed: true });
    c.ask(); // keydown.enter
    expect(c.confirming).toBe(true);
    expect(c.panelTitle()).toBe('Envoyer ?');
    c.begin(); // bouton « Envoyer » du panneau
    vi.advanceTimersByTime(5000);
    expect(c.$dispatch).toHaveBeenCalledWith('sos-send', expect.objectContaining({ resolve: expect.any(Function) }));
  });

  it('Espace maintenue : la répétition de keydown ne relance pas le délai', () => {
    const c = make();
    c.startKey();
    vi.advanceTimersByTime(1500);
    c.startKey();
    vi.advanceTimersByTime(500);
    expect(c.counting).toBe(true);
  });

  it('mode managed : « Envoi… » puis résultat rendu par l’hôte', () => {
    const c = make({ managed: true });
    c.send();
    expect(c.sending).toBe(true);
    expect(c.panelTitle()).toBe('Envoi…');
    const detail = c.$dispatch.mock.calls[0][1];
    expect(c.$dispatch.mock.calls[0][0]).toBe('sos-send');
    detail.resolve({ state: 'queued', title: 'Tu es hors ligne', text: 'Ton alerte partira.', call: { href: 'tel:117', label: 'Appeler le 117' } });
    expect(c.sending).toBe(false);
    expect(c.done).toBe(true);
    expect(c.panelTitle()).toBe('Tu es hors ligne');
    expect(c.message).toBe('Tu es hors ligne Ton alerte partira.');
    detail.resolve({ state: 'idle' });
    expect(c.open).toBe(false);
  });

  it('Échap annule le décompte et referme un résultat', () => {
    const c = make({ managed: true });
    c.begin();
    c.escape();
    expect(c.counting).toBe(false);
    c.resolve({ state: 'failed', title: 'x' });
    c.escape();
    expect(c.done).toBe(false);
  });

  it('bouton flottant : ouvrir le panneau ferme les modales Bootstrap ouvertes', () => {
    document.body.innerHTML = '<div class="modal show" id="m"></div>';
    const spy = vi.spyOn(document, 'querySelectorAll');
    const c = make({ floating: true });
    c.ask();
    expect(spy).toHaveBeenCalledWith('.modal.show');
    expect(c.confirming).toBe(true);
    spy.mockRestore();
    expect(() => closeOverlays(document)).not.toThrow();
  });

  it('Espace maintenue 2 s : le focus va sur le titre du panneau, la répétition puis le relâchement n’annulent rien', () => {
    document.body.innerHTML = '<div id="w"><button class="xm-sos" type="button">SOS</button><div><h2 tabindex="-1" data-sos-title></h2><button type="button" id="cancel">Annuler</button></div></div>';
    const c = make({ managed: true });
    c.$root = document.getElementById('w');
    const cancel = document.getElementById('cancel');
    cancel.addEventListener('click', () => c.abort());
    // Clic natif d'un bouton au relâchement d'Espace (non simulé par jsdom) : seul l'élément focalisé le recevrait.
    const keyup = (event) => { if (event.key === ' ' && event.target === cancel) cancel.click(); };
    document.addEventListener('keyup', keyup);
    c.$root.querySelector('.xm-sos').focus();
    c.startKey();
    vi.advanceTimersByTime(2000);
    expect(c.counting).toBe(true);
    expect(document.activeElement).toBe(c.$root.querySelector('[data-sos-title]'));
    for (let i = 0; i < 3; i += 1) document.activeElement.dispatchEvent(new KeyboardEvent('keydown', { key: ' ', repeat: true, bubbles: true }));
    document.activeElement.dispatchEvent(new KeyboardEvent('keyup', { key: ' ', bubbles: true }));
    expect(c.counting).toBe(true);
    vi.advanceTimersByTime(5000);
    expect(c.$dispatch).toHaveBeenCalledWith('sos-send', expect.objectContaining({ resolve: expect.any(Function) }));
    document.removeEventListener('keyup', keyup);
  });

  it('confirmation : texte de l’hôte (confirmBind), relu à chaque affichage ; sinon texte générique ; aucun texte par défaut', () => {
    let shared = true;
    const c = make({ texts: { confirmText: 'Générique.' }, confirm: () => (shared ? 'Avec ta position.' : 'Sans ta position.') });
    c.ask();
    expect(c.panelText()).toBe('Avec ta position.');
    shared = false;
    expect(c.panelText()).toBe('Sans ta position.');
    expect(make({ texts: { confirmText: 'Générique.' } }).confirmText()).toBe('Générique.');
    const bare = xmSos();
    expect(Object.values(bare.texts).every((text) => text === '')).toBe(true);
  });
});

// Bootstrap chargé à la demande (espace membre, shared/bootstrap-lazy.js) : fausses classes Modal et Offcanvas espionnées.
describe('closeOverlays', () => {
  const fake = () => {
    const hidden = [];
    const component = () => ({ getInstance: vi.fn((el) => (el.id === 'orphan' ? null : { hide: () => hidden.push(el.id) })) });
    return { hidden, Modal: component(), Offcanvas: component() };
  };
  const overlays = '<div class="modal show" id="m"></div><div class="modal" id="closed"></div><div class="modal show" id="orphan"></div><div class="offcanvas show" id="o"></div>';

  afterEach(() => { provideBootstrap(null); document.body.innerHTML = ''; });

  it('Bootstrap chargé : masque la modale et l’offcanvas ouverts, ignore les fermés et ceux sans instance', () => {
    const bootstrap = fake();
    provideBootstrap(bootstrap);
    document.body.innerHTML = overlays;
    closeOverlays(document);
    expect(bootstrap.hidden).toEqual(['m', 'o']);
    expect(bootstrap.Modal.getInstance).toHaveBeenCalledTimes(2);
    expect(bootstrap.Offcanvas.getInstance).toHaveBeenCalledTimes(1);
  });

  it('Bootstrap absent : rien ne se passe, sans exception', () => {
    provideBootstrap(null);
    document.body.innerHTML = overlays;
    expect(() => closeOverlays(document)).not.toThrow();
    expect(document.querySelectorAll('.show')).toHaveLength(3);
    expect(() => closeOverlays(null)).not.toThrow();
  });
});
