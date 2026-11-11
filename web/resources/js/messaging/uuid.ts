// UUID v4 du `client_uuid` d'un message (P8-01) : même algorithme que shared/outbox.js (`uuid`), sans charger la boîte
// d'envoi ni idb avant le premier envoi hors connexion ; crypto.randomUUID absent hors contexte sécurisé.
export function uuidV4(cryptoImpl: Crypto | undefined = globalThis.crypto): string {
    if (cryptoImpl && typeof cryptoImpl.randomUUID === 'function') return cryptoImpl.randomUUID();
    const bytes = new Uint8Array(16);
    if (cryptoImpl && typeof cryptoImpl.getRandomValues === 'function') cryptoImpl.getRandomValues(bytes);
    else for (let i = 0; i < 16; i += 1) bytes[i] = Math.floor(Math.random() * 256);
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}
