// Renvoi d'un SOS refusé par la validation (422, RG-01 : un SOS part toujours), commun à public/sos.js (boucle de la page)
// et shared/outbox.js (boîte d'envoi) : mêmes champs retirés et même nouvelle clé, dérivée de l'ancienne. Quand les deux
// renvoient la même alerte, le second envoi a la même clé et le même corps : le serveur rejoue la réponse du premier
// (ADR-32), jamais deux SOS. Sans dépendance (ni idb ni texte).

/** Retire les champs refusés ; une position refusée part entière, une zone refusée emporte sa commune. */
export function strip(payload, fields = []) {
  const lean = { ...payload };
  fields.forEach((field) => {
    const name = String(field).split('.')[0];
    delete lean[name];
    if (['lat', 'lng', 'accuracy'].includes(name)) { delete lean.lat; delete lean.lng; delete lean.accuracy; }
    if (name === 'zone_id') delete lean.commune_id;
  });
  return lean;
}

/** Empreinte FNV-1a 32 bits (déterministe, sans crypto : la clé n'est pas un secret). */
function fnv(text, seed) {
  let hash = (0x811c9dc5 ^ seed) >>> 0;
  for (let i = 0; i < text.length; i += 1) {
    hash ^= text.charCodeAt(i);
    hash = Math.imul(hash, 0x01000193) >>> 0;
  }
  return hash;
}

/** Clé du renvoi sans les champs refusés : UUID (format v4) dérivé de la clé d'origine, identique d'un module à l'autre. */
export function derivedKey(key) {
  const source = `${String(key || '').toLowerCase()}:stripped`;
  const hex = [0x9e37, 0x85eb, 0xc2b2, 0x27d4].map((seed) => fnv(source, seed).toString(16).padStart(8, '0')).join('');
  const variant = ((parseInt(hex[16], 16) & 0x3) | 0x8).toString(16);
  return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-4${hex.slice(13, 16)}-${variant}${hex.slice(17, 20)}-${hex.slice(20, 32)}`;
}
