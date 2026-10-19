// Garde-fous de crise de la page /assistant (P8-11) : même règle que KnowledgeBaseAnswerer::detectCrisis côté serveur
// (mot ou groupe de mots entier, accents, casse et ponctuation ignorés, pluriel en -s / -x toléré), appliquée **avant
// tout appel réseau** : un message de crise affiche aussitôt les numéros d'urgence, le SOS et « Parler à un humain »,
// même hors connexion, sans rien envoyer au fournisseur. Les mots-clés arrivent déjà normalisés (props de la page,
// AssistantPageController) ; seul le message est normalisé ici.

const LIGATURES: Record<string, string> = { œ: 'oe', Œ: 'oe', æ: 'ae', Æ: 'ae', ß: 'ss' };

/** Forme comparable : comme KnowledgeBaseAnswerer::normalize (Str::ascii, minuscules, [^a-z0-9]+ → espace). */
export function normalize(text: string): string {
    return String(text ?? '')
        .replace(/[œŒæÆß]/g, (char) => LIGATURES[char] ?? char)
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, ' ')
        .trim();
}

/**
 * Mot-clé de crise trouvé dans le message (forme normalisée), ou null. Sans lookbehind (Safari iOS < 16.4) : le texte
 * normalisé ne contient que [a-z0-9 ], un mot entier est donc encadré d'espaces.
 */
export function detectCrisis(message: string, keywords: readonly string[]): string | null {
    const haystack = ` ${normalize(message)} `;
    if (haystack.trim() === '') return null;
    for (const keyword of keywords) {
        const needle = normalize(keyword);
        if (needle === '') continue;
        if ([needle, `${needle}s`, `${needle}x`].some((form) => haystack.includes(` ${form} `))) return keyword;
    }
    return null;
}
