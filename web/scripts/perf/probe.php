<?php

declare(strict_types=1);

/**
 * Sonde serveur (docs/PERFORMANCE.md §11) : traite des requêtes GET dans le processus, sans réseau, et affiche pour chaque
 * page le nombre de requêtes SQL, le temps serveur (froid = 1re requête du processus, chaud = médiane des suivantes),
 * les en-têtes de cache et la présence de Set-Cookie. Usage : php scripts/perf/probe.php [/chemin ...]
 * À lancer avec APP_ENV=production APP_DEBUG=false et une base SQLite semée.
 */

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);

$paths = array_slice($argv, 1) ?: ['/', '/politique-de-confidentialite', '/conditions-utilisation', '/mentions-legales', '/cookies', '/supprimer-mon-compte', '/connexion'];
$t0 = hrtime(true);
$kernel->bootstrap();
printf("amorçage de l'application : %.1f ms\n", (hrtime(true) - $t0) / 1e6);

$queries = 0;
DB::listen(function () use (&$queries): void {
    $queries++;
});

printf("%-32s %5s %9s %9s %-40s %s\n", 'chemin', 'SQL', 'froid ms', 'chaud ms', 'Cache-Control', 'cookies');

foreach ($paths as $path) {
    $times = [];
    $sql = 0;
    $last = null;
    for ($i = 0; $i < 8; $i++) {
        $queries = 0;
        $request = Request::create($path, 'GET');
        $t = hrtime(true);
        $response = $kernel->handle($request);
        $times[] = (hrtime(true) - $t) / 1e6;
        $sql = $queries;
        $last = $response;
        $kernel->terminate($request, $response);
    }
    $cold = array_shift($times);
    sort($times);
    $cookies = implode(',', array_map(fn ($c) => $c->getName(), $last->headers->getCookies()));
    printf("%-32s %5d %9.1f %9.1f %-40s %s\n", $path, $sql, $cold, $times[intdiv(count($times), 2)], $last->headers->get('Cache-Control'), $cookies ?: '-');
}
