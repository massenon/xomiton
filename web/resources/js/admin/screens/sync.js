// Écran Hors connexion & synchro (P13-01) : « Cache local (ce navigateur) » — état du service worker, caches ouverts et
// nombre de ressources, bouton « Vider le cache local ». Monté par admin.js pour [data-xm-screen="sync"]. Les textes
// viennent du serveur (<script type="application/json" id="xmSyncI18n">). Ne touche ni à la boîte d'envoi hors ligne
// (IndexedDB `xomiton-outbox`) ni au compte : seuls les caches du navigateur (API Cache) sont vidés.

export function readTexts(doc = document) {
  try {
    return JSON.parse(doc.getElementById('xmSyncI18n')?.textContent || '{}');
  } catch (e) {
    return {};
  }
}

/** Libellé de l'état du service worker. */
export function workerState(nav, texts) {
  if (!nav || !('serviceWorker' in nav)) return texts.unsupported || '';
  return nav.serviceWorker.controller ? texts.active || '' : texts.waiting || '';
}

/** « nom — n ressource(s) » pour chaque cache, dans l'ordre des noms. */
export async function describeCaches(cachesApi, texts) {
  const names = (await cachesApi.keys()).slice().sort();
  const entries = [];

  for (const name of names) {
    const cache = await cachesApi.open(name);
    const count = (await cache.keys()).length;
    entries.push({ name, count, text: `${name} — ${count} ${texts.resources || ''}`.trim() });
  }

  return entries;
}

function render(list, entries, texts) {
  list.textContent = '';

  if (entries.length === 0) {
    const li = list.ownerDocument.createElement('li');
    li.className = 'text-secondary';
    li.textContent = texts.none || '';
    list.appendChild(li);
    return;
  }

  entries.forEach((entry) => {
    const li = list.ownerDocument.createElement('li');
    li.innerHTML = '<i class="bi bi-hdd me-1" aria-hidden="true"></i>';
    li.appendChild(list.ownerDocument.createTextNode(entry.text));
    list.appendChild(li);
  });
}

export default function mount(win = window, doc = document) {
  const list = doc.getElementById('xmSyncCacheList');
  const state = doc.getElementById('xmSyncSwState');
  const clear = doc.getElementById('xmSyncCacheClear');

  if (!list || !state) return;

  const texts = readTexts(doc);
  state.textContent = workerState(win.navigator, texts);

  const refresh = async () => {
    if (!('caches' in win)) {
      list.textContent = texts.unavailable || '';
      return;
    }

    try {
      render(list, await describeCaches(win.caches, texts), texts);
    } catch (e) {
      list.textContent = texts.failed || '';
    }
  };

  refresh();

  clear?.addEventListener('click', async () => {
    if (!('caches' in win)) return;

    const confirmed = win.XM?.admin?.confirm ? await win.XM.admin.confirm({ message: texts.confirm || '' }) : win.confirm(texts.confirm || '');

    if (!confirmed) return;

    const keys = await win.caches.keys();
    await Promise.all(keys.map((key) => win.caches.delete(key)));
    win.XM?.admin?.toast?.(texts.cleared || '', 'success');
    refresh();
  });
}
