// Bibliothèque hors connexion des ressources (P4-03 ; prototype ressources.php l.99-125) : interrupteur « Hors ligne »
// par ressource, marquage local par appareil dans localStorage, ressources marquées par l'équipe (`marked`, fournies
// par le serveur), mode « enregistrées seulement » qui liste les unes et les autres, cartes chargées ou non.
// Aucune dépendance au service worker : P5-10 écoute l'événement window `xm:offline-resources`
// ({ action: init | add | remove, slug, item: { url, file, title, savedAt }, items, marked }) pour mettre en cache `url`
// (page) et `file` (source du lecteur ou téléchargement stable). Aucun texte en dur : tout vient des options (lang/fr
// via le contrôleur). Le titre est gardé avec l'adresse : la liste hors réseau n'a pas d'autre source, et le slug de
// l'adresse en dit déjà autant. `supported` : le navigateur a un service worker ; sinon (navigateur ancien, certaines
// navigations privées) l'interrupteur et le filtre sont masqués : aucune promesse de lecture hors ligne non tenue.

const hasOwn = (object, key) =>
    Object.prototype.hasOwnProperty.call(object, key);

/** Lecture tolérante : objet indexé par slug, sinon {} (absent, illisible, stockage indisponible). */
const read = (key) => {
    try {
        const value = JSON.parse(window.localStorage.getItem(key) || "{}");
        return value && typeof value === "object" && !Array.isArray(value)
            ? value
            : {};
    } catch {
        return {};
    }
};

/** Ressources marquées valides seulement (slug, url et titre textuels). */
const cleanMarked = (list) =>
    (Array.isArray(list) ? list : []).filter(
        (entry) =>
            entry &&
            typeof entry.slug === "string" &&
            typeof entry.url === "string" &&
            typeof entry.title === "string",
    );

export function xmOfflineLibrary(opts = {}) {
    const key = opts.key || "xm_offline_res_v1";
    const event = opts.event || "xm:offline-resources";
    const texts = {
        saved: "",
        removed: "",
        removeHint: "",
        ...(opts.texts || {}),
    };
    const labels = {
        zero: "",
        one: ":count",
        many: ":count",
        ...(opts.countLabels || {}),
    };
    const marked = cleanMarked(opts.marked);
    const supported =
        opts.supported ??
        (typeof navigator !== "undefined" && "serviceWorker" in navigator);

    return {
        items: {},
        only: false,
        supported,

        init() {
            this.items = read(key);
            this.emit("init", null);
        },

        isSaved(slug) {
            return hasOwn(this.items, slug);
        },

        /**
         * Liste du mode « enregistrées seulement » : ressources enregistrées sur l'appareil (les plus récentes d'abord), puis
         * celles marquées par l'équipe ; une ressource marquée l'emporte (badge, pas de bouton « Retirer »).
         */
        savedList() {
            const markedSlugs = new Set(marked.map((entry) => entry.slug));
            const saved = Object.entries(this.items)
                .filter(
                    ([slug, item]) =>
                        !markedSlugs.has(slug) &&
                        item &&
                        typeof item.url === "string",
                )
                .map(([slug, item]) => ({
                    slug,
                    url: item.url,
                    title: item.title || slug,
                    savedAt: item.savedAt || "",
                    marked: false,
                }))
                .sort((a, b) => b.savedAt.localeCompare(a.savedAt));

            return [
                ...saved,
                ...marked.map((entry) => ({
                    slug: entry.slug,
                    url: entry.url,
                    title: entry.title,
                    savedAt: "",
                    marked: true,
                })),
            ];
        },

        count() {
            return this.savedList().length;
        },

        countLabel() {
            const n = this.count();
            return (
                n === 0 ? labels.zero : n === 1 ? labels.one : labels.many
            ).replace(":count", String(n));
        },

        removeHint(entry) {
            return texts.removeHint.replace(":title", entry?.title ?? "");
        },

        toggle(slug, input) {
            if (!input.checked) {
                this.remove(slug);
                return;
            }
            const data = input.dataset || {};
            this.items[slug] = {
                url: data.offlineUrl || null,
                file: data.offlineFile || null,
                title: data.offlineTitle || slug,
                savedAt: new Date().toISOString(),
            };
            this.persist();
            this.emit("add", slug);
            this.$dispatch?.("toast", {
                type: "success",
                message: texts.saved,
            });
        },

        /** Retrait depuis l'interrupteur décoché ou le bouton « Retirer » de la liste. */
        remove(slug) {
            if (!this.isSaved(slug)) return;
            delete this.items[slug];
            this.persist();
            this.emit("remove", slug);
            this.$dispatch?.("toast", {
                type: "secondary",
                message: texts.removed,
            });
        },

        persist() {
            try {
                window.localStorage.setItem(key, JSON.stringify(this.items));
            } catch {
                // Mode privé ou quota atteint : l'état reste en mémoire pour la page.
            }
        },

        emit(action, slug) {
            const items = JSON.parse(JSON.stringify(this.items));
            window.dispatchEvent(
                new CustomEvent(event, {
                    detail: {
                        action,
                        slug,
                        item: slug ? (items[slug] ?? null) : null,
                        items,
                        marked: marked.map((entry) => ({ ...entry })),
                    },
                }),
            );
        },
    };
}

if (typeof document !== "undefined") {
    document.addEventListener("alpine:init", () => {
        window.Alpine.data("xmOfflineLibrary", xmOfflineLibrary);
    });
}
