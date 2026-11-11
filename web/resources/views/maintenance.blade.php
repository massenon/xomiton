{{--
    Page de maintenance (RG-14, P2-11, Q28 ; refonte P13-07) : document autonome, rendu avec le statut 503 par
    App\Http\Middleware\EnsureNotInMaintenance (MaintenancePage::response), par resources/views/errors/503.blade.php
    (variante `generic`) et par l'aperçu de l'administration. Aucune dépendance au manifest Vite : CSS en ligne minifié
    (maintenance/page.css + content.css, < 30 Ko), logo et illustration SVG en ligne, police Montserrat du build si le
    manifest existe, sinon pile système ; `noindex`. Sans JavaScript : la fin prévue reste écrite en clair. Avec :
    compte à rebours, barre d'avancement, dépassement de la reprise annoncée (classe .xmm-overdue, phrase remplacée),
    annonces `aria-live` aux seuils 60 / 30 / 15 / 5 min puis au dépassement et à la réouverture, vérification de la
    réouverture (GET /api/v1/status toutes les 60 s, décalage aléatoire) puis rechargement ; une nouvelle reprise
    annoncée recharge la page (heures recomposées par le serveur, fuseau xomiton.display_timezone).
    Thème : choix enregistré (`localStorage.theme` clair ou sombre, comme partials/head) sinon préférence du système.
    Variables : MaintenancePage::data().
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $pageTitle }} – {{ __('seo.site_name') }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0433C3">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/images/xomiton/brand/icone-appli-bleu.svg') }}">
    <script nonce="{{ $nonce }}">(function(){try{var t=localStorage.getItem('theme');if(t==='dark'||t==='light'){document.documentElement.setAttribute('data-bs-theme',t)}}catch(e){}})();</script>
    @if ($fontUrl)
        <link rel="preload" as="font" type="font/woff2" crossorigin href="{{ $fontUrl }}">
    @endif
    <style nonce="{{ $nonce }}">
@foreach ([800 => ['Montserrat ExtraBold', 'Montserrat-ExtraBold'], 700 => ['Montserrat Bold', 'Montserrat-Bold']] as $weight => $locals)
@font-face{font-family:"Montserrat";font-style:normal;font-weight:{{ $weight }};font-display:swap;src:local("{{ $locals[0] }}"),local("{{ $locals[1] }}")@if ($fontUrl),url("{{ $fontUrl }}") format("woff2")@endif;unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD}
@endforeach
{!! $css !!}
    </style>
</head>
<body>
    @if ($preview)
        <p class="xmm-ribbon" role="note">{{ __('maintenance.admin.preview_ribbon') }}</p>
    @endif
    @include('maintenance.content')
    @if ($statusUrl)
        <script nonce="{{ $nonce }}">
            (function () {
                var root = document.querySelector('[data-xm-maintenance]');
                if (!root) return;
                root.classList.add('xmm-js');
                var attr = function (name) { return root.getAttribute(name) || ''; };
                var ends = Date.parse(attr('data-ends-at'));
                var starts = Date.parse(attr('data-starts-at'));
                var url = attr('data-status-url');
                var poll = (parseInt(attr('data-poll'), 10) || 60) * 1000;
                var marks = attr('data-announce').split(',').map(Number).filter(function (n) { return n > 0; });
                var texts = {};
                try { texts = JSON.parse(attr('data-texts')) || {}; } catch (e) {}
                var $ = function (sel) { return root.querySelector(sel); };
                var out = $('[data-xmm-remaining]');
                var bar = $('[data-xmm-bar]');
                var live = $('[data-xmm-announce]');
                var skew = 0;
                var mark = null;
                var overdue = root.classList.contains('xmm-overdue');
                var reopening = false;
                var pad = function (n) { return (n < 10 ? '0' : '') + n; };
                var say = function (text) { if (live && text) { live.textContent = text; } };
                var setText = function (sel, text) { var el = $(sel); if (el && text) { el.textContent = text; } };

                // Seuil déjà franchi au chargement : rien n'est annoncé avant le seuil suivant (la phrase en clair suffit).
                function markFor(minutes) {
                    var found = null;
                    marks.forEach(function (m) { if (minutes <= m && (found === null || m < found)) { found = m; } });
                    return found;
                }

                function showOverdue() {
                    if (overdue) return;
                    overdue = true;
                    root.classList.add('xmm-overdue');
                    setText('[data-xmm-ends]', texts.soon);
                    setText('[data-xmm-until]', texts.overdue);
                    say(texts.overdue);
                    check();
                }

                function tick() {
                    if (isNaN(ends) || overdue) return;
                    var now = Date.now() + skew;
                    var left = Math.max(0, Math.round((ends - now) / 1000));
                    if (left === 0) { showOverdue(); return; }
                    var h = Math.floor(left / 3600), m = Math.floor((left % 3600) / 60), s = left % 60;
                    if (out) out.textContent = (h > 0 ? pad(h) + ':' : '') + pad(m) + ':' + pad(s);
                    if (bar && !isNaN(starts) && ends > starts) {
                        var p = Math.min(1, Math.max(0, (now - starts) / (ends - starts)));
                        bar.style.setProperty('--xmm-p', p.toFixed(3));
                        bar.setAttribute('aria-valuenow', String(Math.round(p * 100)));
                    }
                    var current = markFor(Math.ceil(left / 60));
                    if (mark === null) { mark = current === null ? Infinity : current; return; }
                    if (current !== null && current < mark) {
                        mark = current;
                        say(String(texts.remaining || '').replace(':minutes', String(current)));
                    }
                }

                function reopen() {
                    if (reopening) return;
                    reopening = true;
                    root.classList.add('xmm-open');
                    say(texts.reopened);
                    setTimeout(function () { window.location.reload(); }, 1500);
                }

                function check() {
                    if (!window.fetch || reopening) return;
                    fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store', credentials: 'omit' })
                        .then(function (r) { return r.ok ? r.json() : null; })
                        .then(function (body) {
                            var data = body && body.data;
                            if (!data || !data.maintenance) return;
                            if (data.server_time) { var server = Date.parse(data.server_time); if (!isNaN(server)) skew = server - Date.now(); }
                            if (data.maintenance.active === false) { reopen(); return; }
                            // Nouvelle reprise annoncée : la page est recomposée par le serveur (heures en clair, fuseau).
                            var next = Date.parse(data.maintenance.ends_at || '');
                            if (!isNaN(next) && next !== ends) { reopening = true; window.location.reload(); }
                        })
                        .catch(function () {});
                }

                tick();
                setInterval(tick, 1000);
                setTimeout(function loop() { check(); setTimeout(loop, poll); }, Math.round(poll * (0.5 + Math.random() * 0.5)));
                document.addEventListener('visibilitychange', function () { if (!document.hidden) check(); });
            })();
        </script>
    @endif
</body>
</html>
