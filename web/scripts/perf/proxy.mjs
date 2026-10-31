// Mandataire de mesure (docs/PERFORMANCE.md §11) : remplace Apache/LiteSpeed pour la mesure locale. Relaie vers
// `php -S`, compresse en gzip les types texte (mod_deflate) et pose le cache longue durée des fichiers de /build
// (règle XM_IMMUTABLE de public/.htaccess). Usage : node scripts/perf/proxy.mjs <portProxy> <portPhp>
import http from 'node:http';
import zlib from 'node:zlib';

const [, , listen = '8099', upstream = '8098'] = process.argv;
const TEXT = /^(text\/|application\/(javascript|json|manifest\+json)|image\/svg\+xml)/;

http.createServer((req, res) => {
  const proxied = http.request({ host: '127.0.0.1', port: Number(upstream), path: req.url, method: req.method, headers: { ...req.headers, 'accept-encoding': 'identity' } }, (up) => {
    const headers = { ...up.headers };
    if (req.url.startsWith('/build/')) headers['cache-control'] = 'public, max-age=31536000, immutable';
    const type = String(headers['content-type'] || '');
    if (process.env.STRIP_SPECULATION && type.startsWith('text/html')) {
      const chunks = [];
      up.on('data', (c) => chunks.push(c));
      up.on('end', () => {
        const body = Buffer.from(Buffer.concat(chunks).toString('utf8').replace(/<script type="speculationrules"[\s\S]*?<\/script>/, ''));
        delete headers['content-length']; delete headers.etag; headers['content-encoding'] = 'gzip';
        res.writeHead(up.statusCode, headers); res.end(zlib.gzipSync(body));
      });
      return;
    }
    const gz = TEXT.test(type) && /\bgzip\b/.test(String(req.headers['accept-encoding'] || '')) && up.statusCode === 200;
    if (!gz) { res.writeHead(up.statusCode, headers); up.pipe(res); return; }
    delete headers['content-length'];
    headers['content-encoding'] = 'gzip';
    headers.vary = [headers.vary, 'Accept-Encoding'].filter(Boolean).join(', ');
    res.writeHead(up.statusCode, headers);
    up.pipe(zlib.createGzip({ level: 6 })).pipe(res);
  });
  proxied.on('error', () => { res.writeHead(502); res.end(); });
  req.pipe(proxied);
}).listen(Number(listen), '127.0.0.1');
