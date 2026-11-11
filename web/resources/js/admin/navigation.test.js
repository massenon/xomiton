// @vitest-environment jsdom
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { announcePage, installNavigationFetch, leaveIfRelay, needsFullLoad, nonceFromCsp, registerTeardown, relayHtml, restoreShellState, runTeardowns, swapNonce, syncSidebar } from './navigation.js';
import { resetAdminI18n } from './i18n.js';

const response = (body, init = {}, url = 'http://localhost/admin/alertes') => {
  const res = new Response(body, { status: 200, headers: { 'content-type': 'text/html' }, ...init });
  Object.defineProperty(res, 'url', { value: url });
  return res;
};
const PAGE = '<!DOCTYPE html><html><head><script nonce="NEW">1</script></head><body class="xm-admin"><script nonce="NEW">2</script></body></html>';

describe('navigation sans rechargement de l’administration', () => {
  beforeEach(() => {
    document.body.innerHTML = '';
    document.head.innerHTML = '';
    document.documentElement.removeAttribute('class');
    resetAdminI18n();
    window.localStorage.clear();
  });

  it('lit le nonce de la CSP et aligne head (nonce vide, comme le DOM) et body (nonce du document)', () => {
    expect(nonceFromCsp("default-src 'self'; script-src 'self' 'nonce-AbC123=' 'unsafe-eval'")).toBe('AbC123=');
    expect(nonceFromCsp('')).toBeNull();
    const out = swapNonce(PAGE, 'NEW', 'OLD');
    expect(out).toContain('<head><script nonce="">1</script></head>');
    expect(out).toContain('<body class="xm-admin"><script nonce="OLD">2</script>');
    expect(swapNonce(PAGE, null, 'OLD')).toBe(PAGE);
  });

  it('exige un chargement complet pour toute réponse hors administration', () => {
    const ok = response(PAGE);
    expect(needsFullLoad(ok, PAGE)).toBe(false);
    expect(needsFullLoad(response('', { status: 419 }), '')).toBe(true);
    expect(needsFullLoad(response('', { status: 503 }), '')).toBe(true);
    expect(needsFullLoad(response('x', { headers: { 'content-type': 'application/pdf' } }), 'x')).toBe(true);
    expect(needsFullLoad(response('<body>login</body>'), '<body>login</body>')).toBe(true);
    const redirected = response(PAGE, {}, 'http://localhost/connexion');
    Object.defineProperty(redirected, 'redirected', { value: true });
    expect(needsFullLoad(redirected, PAGE)).toBe(true);
    const inside = response(PAGE, {}, 'http://localhost/admin/registres');
    Object.defineProperty(inside, 'redirected', { value: true });
    expect(needsFullLoad(inside, PAGE)).toBe(false);
  });

  it('remplace une réponse inutilisable par une page de relais, qui recharge l’adresse demandée', async () => {
    document.body.innerHTML = '<script nonce="OLD"></script>';
    const calls = [];
    const win = {
      document,
      location: { href: 'http://localhost/admin', replace: (url) => calls.push(url) },
      fetch: vi.fn(async () => response('', { status: 419 })),
    };
    installNavigationFetch(win);
    const result = await win.fetch('http://localhost/admin/alertes', { headers: { 'X-Livewire-Navigate': '' } });
    const html = await result.text();
    expect(result.url).toBe('http://localhost/admin/alertes');
    expect(html).toContain('data-xm-nav-relay="http://localhost/admin/alertes"');

    document.body.innerHTML = '<p data-xm-nav-relay="http://localhost/admin/alertes"></p>';
    expect(leaveIfRelay(document, win)).toBe(true);
    expect(calls).toEqual(['http://localhost/admin/alertes']);
    expect(relayHtml('/a?x="1"', 'Chargement <…>')).not.toContain('<…>');
  });

  it('laisse passer les autres requêtes et réécrit les nonces des navigations réussies', async () => {
    const script = document.createElement('script');
    script.setAttribute('nonce', 'OLD');
    document.head.append(script);
    const ok = response(PAGE, { headers: { 'content-type': 'text/html', 'content-security-policy': "script-src 'nonce-NEW'" } });
    const win = { document, location: { href: 'http://localhost/admin' }, fetch: vi.fn(async (url) => (String(url).includes('autre') ? response('{}') : ok)) };
    installNavigationFetch(win);
    expect(await (await win.fetch('/autre')).text()).toBe('{}');
    const nav = await win.fetch('/admin/alertes', { headers: { 'X-Livewire-Navigate': '' } });
    expect(nav.status).toBe(200);
    expect(nav.url).toBe('http://localhost/admin/alertes');
    expect(await nav.text()).toContain('<head><script nonce="">1</script></head>');
  });

  it('restaure le thème et la barre repliée perdus par le remplacement des attributs de <html>', () => {
    window.localStorage.setItem('theme', 'dark');
    window.localStorage.setItem('xm-admin-sidebar', 'collapsed');
    restoreShellState(document, window);
    expect(document.documentElement.getAttribute('data-bs-theme')).toBe('dark');
    expect(document.documentElement.classList.contains('xm-sidebar-collapsed')).toBe(true);
    window.localStorage.clear();
    restoreShellState(document, window);
    expect(document.documentElement.getAttribute('data-bs-theme')).toBe('light');
    expect(document.documentElement.classList.contains('xm-sidebar-collapsed')).toBe(false);
  });

  it('met à jour l’entrée active (aria-current) et les pastilles de la barre latérale persistée', () => {
    document.body.innerHTML = `<aside id="xmSidebar">
      <a data-xm-nav="alertes" class="item active" aria-current="page"><span class="label">Alertes</span><span class="badge bg-danger" aria-hidden="true">2</span><span class="visually-hidden">(2 nouvelles)</span></a>
      <a data-xm-nav="registres" class="item"><span class="label">Registres</span></a></aside>`;
    syncSidebar(document, { current: 'registres', badges: { alertes: { text: '5', color: 'danger', label: '5 nouvelles alertes' } } });
    const [alerts, registers] = document.querySelectorAll('a');
    expect(registers.getAttribute('aria-current')).toBe('page');
    expect(registers.classList.contains('active')).toBe(true);
    expect(alerts.hasAttribute('aria-current')).toBe(false);
    expect(alerts.classList.contains('active')).toBe(false);
    expect(alerts.querySelectorAll('.badge')).toHaveLength(1);
    expect(alerts.querySelector('.badge').textContent).toBe('5');
    expect(alerts.querySelector('.visually-hidden').textContent).toBe('(5 nouvelles alertes)');
    expect(document.querySelectorAll('[aria-current="page"]')).toHaveLength(1);
  });

  it('annonce « Page : titre » dans la région aria-live et place le focus sur le h1', () => {
    vi.useFakeTimers();
    document.body.innerHTML = '<div id="xmRouteAnnouncer" role="status"></div><div class="xm-admin-topbar"><h1>Registres</h1></div>';
    announcePage(document);
    vi.advanceTimersByTime(100);
    expect(document.getElementById('xmRouteAnnouncer').textContent).toBe('Page : Registres');
    expect(document.activeElement).toBe(document.querySelector('h1'));
    expect(document.querySelector('h1').getAttribute('tabindex')).toBe('-1');
    vi.useRealTimers();
  });

  it('démonte les écrans une seule fois à chaque navigation, ou tout de suite si la page a déjà changé', () => {
    const a = vi.fn();
    registerTeardown(a);
    registerTeardown('pas une fonction');
    runTeardowns();
    runTeardowns();
    expect(a).toHaveBeenCalledTimes(1);

    const late = vi.fn();
    registerTeardown(late, -1); // écran monté pour une page déjà quittée
    expect(late).toHaveBeenCalledTimes(1);
  });
});
