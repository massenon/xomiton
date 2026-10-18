// @vitest-environment jsdom
// Bibliothèque hors connexion des ressources (P4-03) : lecture du stockage, ajout / retrait avec événement et toast,
// liste « enregistrées seulement » (stockage local et ressources marquées par l'équipe, cartes chargées ou non),
// stockage indisponible, libellés du compteur.
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { xmOfflineLibrary } from "./ressources.js";

const KEY = "xm_offline_res_v1";
const make = (marked = []) => {
    const library = xmOfflineLibrary({
        key: KEY,
        marked,
        texts: {
            saved: "Ressource enregistrée.",
            removed: "Ressource retirée.",
            removeHint: " « :title » du hors connexion",
        },
        countLabels: {
            zero: "Aucune ressource hors connexion",
            one: ":count disponible hors connexion",
            many: ":count disponibles hors connexion",
        },
    });
    library.$dispatch = vi.fn();
    return library;
};
const input = (checked, dataset = {}) => ({ checked, dataset });

describe("xmOfflineLibrary", () => {
    let events;
    const listener = (e) => events.push(e.detail);

    beforeEach(() => {
        window.localStorage.clear();
        events = [];
        window.addEventListener("xm:offline-resources", listener);
    });

    afterEach(() => {
        window.removeEventListener("xm:offline-resources", listener);
        vi.restoreAllMocks();
    });

    it("démarre vide avec un stockage absent et annonce son état", () => {
        const library = make();
        library.init();

        expect(library.count()).toBe(0);
        expect(library.isSaved("a")).toBe(false);
        expect(events).toEqual([
            { action: "init", slug: null, item: null, items: {}, marked: [] },
        ]);
    });

    it("ignore un stockage illisible ou d’un autre format (ancien tableau d’ids du prototype)", () => {
        window.localStorage.setItem(KEY, "[1,2]");
        const library = make();
        library.init();
        expect(library.count()).toBe(0);

        window.localStorage.setItem(KEY, "{pas du json");
        library.init();
        expect(library.count()).toBe(0);
    });

    it("enregistre une ressource avec sa page, son fichier et son titre, puis la retire", () => {
        const library = make();
        library.init();

        library.toggle(
            "a",
            input(true, {
                offlineUrl: "/ressources/a",
                offlineFile: "/ressources/a/telecharger",
                offlineTitle: "Fiche A",
            }),
        );

        expect(library.isSaved("a")).toBe(true);
        const stored = JSON.parse(window.localStorage.getItem(KEY));
        expect(stored.a).toMatchObject({
            url: "/ressources/a",
            file: "/ressources/a/telecharger",
            title: "Fiche A",
        });
        expect(events.at(-1)).toMatchObject({
            action: "add",
            slug: "a",
            item: { url: "/ressources/a", file: "/ressources/a/telecharger" },
        });
        expect(library.$dispatch).toHaveBeenLastCalledWith("toast", {
            type: "success",
            message: "Ressource enregistrée.",
        });

        library.toggle("a", input(false));

        expect(library.isSaved("a")).toBe(false);
        expect(JSON.parse(window.localStorage.getItem(KEY))).toEqual({});
        expect(events.at(-1)).toMatchObject({
            action: "remove",
            slug: "a",
            item: null,
        });
        expect(library.$dispatch).toHaveBeenLastCalledWith("toast", {
            type: "secondary",
            message: "Ressource retirée.",
        });
    });

    it("relit les ressources enregistrées par une visite précédente", () => {
        window.localStorage.setItem(
            KEY,
            JSON.stringify({
                b: { url: "/ressources/b", file: null, title: "B" },
            }),
        );
        const library = make();
        library.init();

        expect(library.isSaved("b")).toBe(true);
        expect(events[0].items).toHaveProperty("b");
    });

    it("liste les ressources enregistrées, chargées ou non, les plus récentes d’abord", () => {
        window.localStorage.setItem(
            KEY,
            JSON.stringify({
                ancienne: {
                    url: "/ressources/ancienne",
                    file: null,
                    title: "Ancienne",
                    savedAt: "2026-10-01T08:00:00.000Z",
                },
                recente: {
                    url: "/ressources/recente",
                    file: null,
                    title: "Récente",
                    savedAt: "2026-10-10T08:00:00.000Z",
                },
                cassee: { file: null, title: "Sans adresse" },
            }),
        );
        const library = make();
        library.init();

        expect(library.savedList().map((entry) => entry.slug)).toEqual([
            "recente",
            "ancienne",
        ]);
        expect(library.savedList()[0]).toMatchObject({
            url: "/ressources/recente",
            title: "Récente",
            marked: false,
        });
        expect(library.count()).toBe(2);
        expect(library.removeHint(library.savedList()[0])).toBe(
            " « Récente » du hors connexion",
        );
    });

    it("montre et compte les ressources marquées par l’équipe en mode « enregistrées seulement »", () => {
        const marked = [
            {
                slug: "crise-72-h",
                url: "/ressources/crise-72-h",
                file: "/ressources/crise-72-h/telecharger",
                title: "Que faire après une agression",
            },
            { slug: "invalide" },
        ];
        window.localStorage.setItem(
            KEY,
            JSON.stringify({
                a: {
                    url: "/ressources/a",
                    title: "A",
                    savedAt: "2026-10-10T08:00:00.000Z",
                },
            }),
        );
        const library = make(marked);
        library.init();

        expect(library.savedList()).toEqual([
            {
                slug: "a",
                url: "/ressources/a",
                title: "A",
                savedAt: "2026-10-10T08:00:00.000Z",
                marked: false,
            },
            {
                slug: "crise-72-h",
                url: "/ressources/crise-72-h",
                title: "Que faire après une agression",
                savedAt: "",
                marked: true,
            },
        ]);
        expect(library.countLabel()).toBe("2 disponibles hors connexion");
        expect(events[0].marked).toEqual([marked[0]]);

        // Une ressource enregistrée puis marquée n'apparaît qu'une fois, comme marquée.
        library.toggle(
            "crise-72-h",
            input(true, {
                offlineUrl: "/ressources/crise-72-h",
                offlineTitle: "Crise",
            }),
        );
        expect(
            library.savedList().filter((entry) => entry.slug === "crise-72-h"),
        ).toEqual([expect.objectContaining({ marked: true })]);
        expect(library.count()).toBe(2);
    });

    it("retire une ressource depuis la liste et ignore un retrait sans objet", () => {
        const library = make();
        library.init();
        library.toggle(
            "a",
            input(true, { offlineUrl: "/ressources/a", offlineTitle: "A" }),
        );

        library.remove("a");
        expect(library.isSaved("a")).toBe(false);
        expect(library.savedList()).toEqual([]);
        expect(events.at(-1)).toMatchObject({ action: "remove", slug: "a" });

        const before = events.length;
        library.remove("a");
        expect(events).toHaveLength(before);
    });

    it("garde l’état en mémoire quand le stockage refuse l’écriture", () => {
        vi.spyOn(Storage.prototype, "setItem").mockImplementation(() => {
            throw new DOMException("quota", "QuotaExceededError");
        });
        const library = make();
        library.init();

        expect(() =>
            library.toggle("a", input(true, { offlineUrl: "/ressources/a" })),
        ).not.toThrow();
        expect(library.isSaved("a")).toBe(true);
    });

    it("accorde le compteur : zéro, un, plusieurs", () => {
        const library = make();
        library.init();
        expect(library.countLabel()).toBe("Aucune ressource hors connexion");

        library.toggle("a", input(true, { offlineUrl: "/ressources/a" }));
        expect(library.countLabel()).toBe("1 disponible hors connexion");

        library.toggle("b", input(true, { offlineUrl: "/ressources/b" }));
        expect(library.countLabel()).toBe("2 disponibles hors connexion");
    });
});
