// Assistant public (P4-01, <x-assistant-offcanvas>) : composant Alpine xmAssistant. Pose la question à
// POST /api/v1/assistant/question (KnowledgeBaseAnswerer côté serveur, sans stockage) et affiche la réponse en bulles.
// Sanctum en mode SPA (ADR-31) : l'en-tête X-XSRF-TOKEN reprend le cookie XSRF-TOKEN ; sur 419, un passage par
// /sanctum/csrf-cookie puis un seul rejeu. Aucun texte ici : tout vient de Blade (lang/fr) par `opts`.

const readCookie = (name) => {
  if (typeof document === 'undefined') return null;
  const row = document.cookie.split('; ').find((part) => part.startsWith(`${name}=`));
  return row ? decodeURIComponent(row.slice(name.length + 1)) : null;
};

const isOffline = () => typeof navigator !== 'undefined' && navigator.onLine === false;

export function xmAssistant(opts = {}) {
  return {
    url: opts.url ?? '/api/v1/assistant/question',
    csrfUrl: opts.csrfUrl ?? '/sanctum/csrf-cookie',
    csrfCookie: opts.csrfCookie ?? 'XSRF-TOKEN',
    offlineUrl: opts.offlineUrl ?? '/urgences',
    offlineCall: opts.offlineCall ?? null,
    welcome: opts.welcome ?? '',
    texts: { loading: '…', error: '', offline: '', offlineLink: '', rateLimited: '', retry: '', ...(opts.texts || {}) },
    messages: [],
    input: '',
    busy: false,
    _seq: 0,

    init() {
      if (this.welcome) this.push({ who: 'bot', text: this.welcome });
      // À l'ouverture de l'offcanvas (évènement Bootstrap), le focus va au champ de saisie.
      if (this.$el && this.$el.addEventListener) this.$el.addEventListener('shown.bs.offcanvas', () => this.focusInput());
    },

    push(message) {
      this._seq += 1;
      const entry = { id: this._seq, ...message };
      this.messages.push(entry);
      return entry;
    },

    // Question tapée : le focus revient au champ après la réponse.
    send() {
      return this.ask(this.input, 'input');
    },

    // `origin` : 'input' (question tapée) ou la puce cliquée. Une puce garde le focus (sur mobile, redonner le focus
    // au champ ouvrirait le clavier virtuel par-dessus la réponse qui arrive).
    async ask(text, origin = null) {
      const question = String(text ?? '').trim();
      if (question === '' || this.busy) return;
      this.push({ who: 'me', text: question });
      this.input = '';
      await this.exchange(question, origin);
    },

    async retry(message) {
      if (this.busy || !message || !message.retry) return;
      this.messages = this.messages.filter((m) => m.id !== message.id);
      // Le bouton « Réessayer » disparaît avec sa bulle : le focus va au champ pour ne pas être perdu.
      await this.exchange(message.retry, 'input');
    },

    // La bulle de chargement est retirée et la réponse ajoutée avec une nouvelle id : Alpine crée un nouvel élément,
    // que le journal role="log" (aria-relevant="additions") annonce. busy revient toujours à false.
    async exchange(question, origin = null) {
      this.busy = true;
      const pending = this.push({ who: 'bot', text: this.texts.loading, loading: true });
      this.scrollToEnd();
      let reply;
      try {
        reply = await this.request(question);
      } catch (error) {
        reply = { text: this.texts.error, retry: question };
      }
      this.messages = this.messages.filter((m) => m.id !== pending.id);
      this.push({ who: 'bot', ...reply });
      this.busy = false;
      this.scrollToEnd();
      this.restoreFocus(origin);
    },

    restoreFocus(origin) {
      if (origin === 'input') {
        this.focusInput();
        return;
      }
      if (!origin || typeof origin.focus !== 'function') return;
      // La puce, désactivée pendant la requête, a pu perdre le focus : il lui revient, sans défilement, sauf si la
      // personne l'a déjà porté ailleurs.
      const run = () => {
        const active = typeof document !== 'undefined' ? document.activeElement : null;
        if (origin.isConnected !== false && (!active || active === document.body || active === origin)) {
          origin.focus({ preventScroll: true });
        }
      };
      if (this.$nextTick) this.$nextTick(run);
      else run();
    },

    async request(question, retried = false) {
      if (isOffline()) return this.offlineReply();
      const token = readCookie(this.csrfCookie);
      const headers = { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
      if (token) headers['X-XSRF-TOKEN'] = token;

      let response;
      try {
        response = await fetch(this.url, { method: 'POST', credentials: 'same-origin', headers, body: JSON.stringify({ question }) });
      } catch (error) {
        return this.offlineReply();
      }

      if (response.status === 419 && !retried) {
        try {
          await fetch(this.csrfUrl, { method: 'GET', credentials: 'same-origin', headers: { Accept: 'application/json' } });
        } catch (error) {
          return this.offlineReply();
        }
        return this.request(question, true);
      }

      const body = await response.json().catch(() => ({}));

      if (response.ok && body && body.data) {
        const data = body.data;
        return {
          text: String(data.text ?? ''),
          links: Array.isArray(data.links) ? data.links : [],
          suggestions: data.kind === 'fallback' && Array.isArray(data.suggestions) ? data.suggestions : [],
          emergency: Boolean(data.emergency),
        };
      }

      if (response.status === 422) {
        const errors = (body && body.errors && body.errors.question) || [];
        return { text: errors[0] || body.message || this.texts.error, error: true };
      }

      if (response.status === 429) {
        const seconds = Number(response.headers.get('Retry-After') || body.retry_after || 60);
        return { text: this.texts.rateLimited.replace(':seconds', String(seconds)), error: true };
      }

      return { text: this.texts.error, error: true, retry: question };
    },

    // Hors réseau, l'appel reste possible : lien tel: du premier numéro d'urgence configuré, puis les contacts d'urgence.
    offlineReply() {
      const links = [];
      if (this.offlineCall && this.offlineCall.url) links.push({ label: this.offlineCall.label, url: this.offlineCall.url, variant: 'primary-soft' });
      links.push({ label: this.texts.offlineLink, url: this.offlineUrl, variant: 'primary-soft' });
      return { text: this.texts.offline, error: true, links };
    },

    scrollToEnd() {
      const run = () => {
        const log = this.$refs && this.$refs.log;
        if (log) log.scrollTop = log.scrollHeight;
      };
      if (this.$nextTick) this.$nextTick(run);
      else run();
    },

    focusInput() {
      const run = () => {
        const input = this.$refs && this.$refs.input;
        if (input && input.focus) input.focus();
      };
      if (this.$nextTick) this.$nextTick(run);
      else run();
    },
  };
}

if (typeof document !== 'undefined') {
  document.addEventListener('alpine:init', () => {
    window.Alpine.data('xmAssistant', xmAssistant);
  });
}
