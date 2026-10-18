#!/usr/bin/env node
// Budgets de poids au build (P4-12, PERFORMANCE.md §1 et §10) : lit public/build/manifest.json, mesure la taille gzip
// de chaque entrée (plus ses imports statiques pour le JS) et compare aux budgets. Un budget « strict » dépassé fait
// échouer le build (exit 1) ; un budget « indicatif » n'émet qu'un avertissement. Lancé par « npm run build »
// (vite build && node scripts/check-budgets.mjs) ; « npm run build:nocheck » construit sans contrôle.
import { readFileSync, existsSync } from 'node:fs';
import { gzipSync } from 'node:zlib';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const buildDir = resolve(root, 'public/build');
const manifestPath = resolve(buildDir, 'manifest.json');

const KO = 1024;

/** Budgets gzip en octets. `strict` : le build échoue ; sinon avertissement seulement. */
const budgets = [
    { entry: 'resources/js/public.js', label: 'JS public (public.js + imports statiques)', max: 60 * KO, strict: true },
    { entry: 'resources/css/critical.css', label: 'CSS critique (inliné)', max: 50 * KO, strict: true },
    { entry: 'resources/css/xomiton.css', label: 'CSS principal (xomiton.css)', max: 120 * KO, strict: false },
    { entry: 'resources/js/app.ts', label: 'JS espace membre (app.ts + imports statiques)', max: 150 * KO, strict: false },
];

if (!existsSync(manifestPath)) {
    console.error(`check-budgets : manifest introuvable (${manifestPath}). Lancer « vite build » d'abord.`);
    process.exit(1);
}

const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));

const gzipSize = (file) => gzipSync(readFileSync(resolve(buildDir, file)), { level: 9 }).length;

/** Fichiers (JS ou CSS) d'une entrée avec ses imports statiques, sans doublon ; les imports dynamiques sont exclus. */
const filesOf = (key, seen = new Set()) => {
    const chunk = manifest[key];
    if (!chunk || seen.has(key)) return [];
    seen.add(key);
    const files = [chunk.file];
    for (const imported of chunk.imports || []) files.push(...filesOf(imported, seen));
    return files;
};

const fmt = (bytes) => `${(bytes / KO).toFixed(1)} Ko`;

let failed = false;
console.log('Budgets gzip (public/build/manifest.json) :');

for (const budget of budgets) {
    if (!manifest[budget.entry]) {
        console.log(`  - ${budget.label} : entrée ${budget.entry} absente du manifest${budget.strict ? ' (ÉCHEC)' : ' (ignorée)'}`);
        failed ||= budget.strict;
        continue;
    }
    const files = [...new Set(filesOf(budget.entry))];
    const size = files.reduce((total, file) => total + gzipSize(file), 0);
    const over = size > budget.max;
    const status = over ? (budget.strict ? 'ÉCHEC' : 'AVERTISSEMENT') : 'ok';
    console.log(`  - ${budget.label} : ${fmt(size)} / ${fmt(budget.max)} ${status}  [${files.join(', ')}]`);
    if (over && budget.strict) failed = true;
}

if (failed) {
    console.error('check-budgets : au moins un budget strict est dépassé (PERFORMANCE.md §1).');
    process.exit(1);
}
