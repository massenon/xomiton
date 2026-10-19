// @vitest-environment jsdom
// Bootstrap à la demande dans l'espace membre (P4-12) : geste retenu sur un déclencheur data-bs-* tant que Bootstrap
// manque, puis rejoué une fois chargé (déclencheur aria-busy pendant l'attente, un seul geste retenu par déclencheur) ;
// chargement planifié au repos ; échec réseau ; shared/bootstrap-lazy.js réel
// (le repli de l'en-tête s'ouvre au premier clic, avant tout chargement).
import { afterEach, describe, expect, it, vi } from 'vitest';
import { WAITING_CLASS, bootstrapOnDemand } from './bootstrapOnDemand';

const flush = async () => {
    for (let i = 0; i < 5; i += 1) await Promise.resolve();
};

/** Chargement factice : `resolve` / `reject` règlent les demandes en cours. */
function fakeLoader() {
    let ready = false;
    let settles: { resolve: () => void; reject: (error: Error) => void }[] = [];
    const load = vi.fn(() => new Promise<void>((resolve, reject) => {
        settles.push({ resolve, reject });
    }));
    const settle = (run: (pending: (typeof settles)[number]) => void) => {
        const pending = settles;
        settles = [];
        pending.forEach(run);
    };
    return {
        load,
        loaded: () => ready,
        resolve: () => { ready = true; settle((pending) => pending.resolve()); },
        reject: () => settle((pending) => pending.reject(new Error('réseau'))),
    };
}

let stop: (() => void) | null = null;

afterEach(() => {
    stop?.();
    stop = null;
    document.body.innerHTML = '';
});

describe('bootstrapOnDemand', () => {
    it('retient le clic sur un déclencheur, charge Bootstrap puis rejoue le clic une seule fois', async () => {
        document.body.innerHTML = '<button id="t" data-bs-toggle="dropdown"><span id="icon">i</span></button><button id="other">x</button>';
        const loader = fakeLoader();
        const onClick = vi.fn();
        document.getElementById('t')!.addEventListener('click', onClick);
        stop = bootstrapOnDemand(document, { load: loader.load, loaded: loader.loaded, idle: () => undefined });

        document.getElementById('other')!.click();
        expect(loader.load).not.toHaveBeenCalled();

        document.getElementById('icon')!.click();
        expect(onClick).not.toHaveBeenCalled();
        expect(loader.load).toHaveBeenCalledTimes(1);

        loader.resolve();
        await flush();
        expect(onClick).toHaveBeenCalledTimes(1);

        // Bootstrap là : plus d'interception.
        document.getElementById('t')!.click();
        expect(onClick).toHaveBeenCalledTimes(2);
        expect(loader.load).toHaveBeenCalledTimes(1);
    });

    it('pendant l’attente : déclencheur aria-busy et en attente ; un second clic est absorbé, un seul geste rejoué', async () => {
        document.body.innerHTML = '<button id="t" data-bs-toggle="dropdown">Menu</button><button id="u" data-bs-toggle="collapse" aria-busy="false">Autre</button>';
        const loader = fakeLoader();
        const clicks: string[] = [];
        document.addEventListener('click', (event) => clicks.push((event.target as HTMLElement).id));
        stop = bootstrapOnDemand(document, { load: loader.load, loaded: loader.loaded, idle: () => undefined });
        const t = document.getElementById('t')!;
        const u = document.getElementById('u')!;

        t.click();
        expect(t.getAttribute('aria-busy')).toBe('true');
        expect(t.classList.contains(WAITING_CLASS)).toBe(true);
        t.click();
        u.click();
        expect(u.getAttribute('aria-busy')).toBe('true');
        expect(clicks).toEqual([]);

        loader.resolve();
        await flush();
        expect(clicks).toEqual(['t', 'u']);
        expect(t.hasAttribute('aria-busy')).toBe(false);
        expect(t.classList.contains(WAITING_CLASS)).toBe(false);
        expect(u.getAttribute('aria-busy')).toBe('false');
    });

    it('échec réseau : le geste est rejoué quand même, le geste suivant refait la demande', async () => {
        document.body.innerHTML = '<button id="close" data-bs-dismiss="toast">Fermer</button>';
        const loader = fakeLoader();
        const onClick = vi.fn();
        document.getElementById('close')!.addEventListener('click', onClick);
        stop = bootstrapOnDemand(document, { load: loader.load, loaded: loader.loaded, idle: () => undefined });

        document.getElementById('close')!.click();
        loader.reject();
        await flush();
        expect(onClick).toHaveBeenCalledTimes(1);

        document.getElementById('close')!.click();
        expect(loader.load).toHaveBeenCalledTimes(2);
        expect(onClick).toHaveBeenCalledTimes(1);
    });

    it('flèche sur un menu déroulant : retenue puis rejouée ; autres touches ignorées', async () => {
        document.body.innerHTML = '<button id="t" data-bs-toggle="dropdown">Menu</button>';
        const loader = fakeLoader();
        const keys: string[] = [];
        document.getElementById('t')!.addEventListener('keydown', (event) => keys.push(event.key));
        stop = bootstrapOnDemand(document, { load: loader.load, loaded: loader.loaded, idle: () => undefined });

        const key = (name: string) => document.getElementById('t')!.dispatchEvent(new KeyboardEvent('keydown', { key: name, bubbles: true, cancelable: true }));
        key('Tab');
        expect(keys).toEqual(['Tab']);
        key('ArrowDown');
        expect(keys).toEqual(['Tab']);

        loader.resolve();
        await flush();
        expect(keys).toEqual(['Tab', 'ArrowDown']);
    });

    it('planifie le chargement au repos du navigateur ; déjà chargé, n’intercepte rien', async () => {
        const loader = fakeLoader();
        let idle: (() => void) | null = null;
        stop = bootstrapOnDemand(document, { load: loader.load, loaded: loader.loaded, idle: (callback) => { idle = callback; } });
        expect(loader.load).not.toHaveBeenCalled();
        idle!();
        expect(loader.load).toHaveBeenCalledTimes(1);
        loader.resolve();
        await flush();

        const again = vi.fn(() => Promise.resolve());
        const off = bootstrapOnDemand(document, { load: again, loaded: () => true, idle: (callback) => callback() });
        document.body.innerHTML = '<button id="t" data-bs-toggle="collapse">x</button>';
        const onClick = vi.fn();
        document.getElementById('t')!.addEventListener('click', onClick);
        document.getElementById('t')!.click();
        expect(onClick).toHaveBeenCalledTimes(1);
        expect(again).not.toHaveBeenCalled();
        off();
    });

    it('avec shared/bootstrap-lazy.js : le premier clic sur le bouton de repli ouvre le menu', async () => {
        const lazy = await import('@/shared/bootstrap-lazy.js');
        expect(lazy.loadedBootstrap()).toBeNull();
        document.body.innerHTML = '<button id="t" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-expanded="false">Menu</button><div id="nav" class="collapse">Liens</div>';
        stop = bootstrapOnDemand(document, { idle: () => undefined });

        document.getElementById('t')!.click();
        await vi.waitFor(() => expect(lazy.loadedBootstrap()).not.toBeNull());
        await vi.waitFor(() => expect(document.getElementById('nav')!.className).toMatch(/collapsing|show/));
        expect(document.getElementById('t')!.getAttribute('aria-expanded')).toBe('true');
        expect(await lazy.loadBootstrap()).toBe(lazy.loadedBootstrap());
    });
});
