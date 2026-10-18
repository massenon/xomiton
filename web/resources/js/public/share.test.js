// @vitest-environment jsdom
// Bouton « Partager » des actualités (P4-10) : partage natif, annulation silencieuse, repli copie du lien + toast,
// échec annoncé, bouton révélé seulement avec JavaScript.
import { describe, expect, it, vi } from 'vitest';
import { initShare, shareFrom } from './share.js';

const button = () => {
  document.body.innerHTML = '<button type="button" hidden data-xm-share data-share-title="Titre" data-share-url="https://xomiton.test/actualites/a" data-share-copied="Lien copié." data-share-failed="Copie impossible."></button>';
  return document.querySelector('[data-xm-share]');
};

describe('share', () => {
  it('utilise le partage natif quand il existe', async () => {
    const share = vi.fn().mockResolvedValue(undefined);
    const toast = vi.fn();

    expect(await shareFrom(button(), { navigator: { share }, toast })).toBe('shared');
    expect(share).toHaveBeenCalledWith({ title: 'Titre', url: 'https://xomiton.test/actualites/a' });
    expect(toast).not.toHaveBeenCalled();
  });

  it('ignore l’annulation du partage natif', async () => {
    const abort = Object.assign(new Error('cancel'), { name: 'AbortError' });
    const toast = vi.fn();

    expect(await shareFrom(button(), { navigator: { share: vi.fn().mockRejectedValue(abort) }, toast })).toBe('cancelled');
    expect(toast).not.toHaveBeenCalled();
  });

  it('copie le lien sans partage natif, ou après son échec, et le dit', async () => {
    const writeText = vi.fn().mockResolvedValue(undefined);
    const toast = vi.fn();

    expect(await shareFrom(button(), { navigator: { clipboard: { writeText } }, toast })).toBe('copied');
    expect(writeText).toHaveBeenCalledWith('https://xomiton.test/actualites/a');
    expect(toast).toHaveBeenCalledWith('Lien copié.', 'success');

    const share = vi.fn().mockRejectedValue(new Error('NotAllowedError'));
    expect(await shareFrom(button(), { navigator: { share, clipboard: { writeText } }, toast })).toBe('copied');
  });

  it('annonce l’échec de la copie', async () => {
    const toast = vi.fn();

    expect(await shareFrom(button(), { navigator: { clipboard: { writeText: vi.fn().mockRejectedValue(new Error('denied')) } }, toast })).toBe('failed');
    expect(await shareFrom(button(), { navigator: {}, toast })).toBe('failed');
    expect(toast).toHaveBeenLastCalledWith('Copie impossible.', 'warning');
  });

  it('révèle et branche les boutons une seule fois', () => {
    const el = button();
    initShare();
    initShare();

    expect(el.hidden).toBe(false);
    expect(el.dataset.shareReady).toBe('1');
  });
});
