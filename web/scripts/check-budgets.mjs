#!/usr/bin/env node
// Budgets de poids au build (P4-12, PERFORMANCE.md §1 et §10) : lit public/build/manifest.json, mesure la taille gzip
// de chaque entrée (plus ses imports statiques pour le JS) et compare aux budgets. Un budget « strict » dépassé fait
// échouer le build (exit 1) ; un budget « indicatif » n'émet qu'un avertissement. Lancé par « npm run build »
// (vite build && node scripts/check-budgets.mjs) ; « npm run build:nocheck » construit sans contrôle.
// Espace membre : le JS initial d'une page Inertia est app.ts + le morceau de la page + leurs imports statiques (le
// morceau de page est chargé avant le montage) ; chaque Pages/**/*.vue du manifest est mesuré ainsi (indicatif).
// Garde Bootstrap (P4-12) : shared/bootstrap.js est chargé à la demande dans l'espace membre ; le retrouver parmi les
// imports statiques d'app.ts (gabarit compris) fait échouer le build, dans ceux d'une page donne un avertissement.
import { readFileSync, existsSync } from 'node:fs';
import { gzipSync } from 'node:zlib';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const buildDir = resolve(root, 'public/build');
const manifestPath = resolve(buildDir, 'manifest.json');

const KO = 1024;
const APP = 'resources/js/app.ts';
const BOOTSTRAP = 'resources/js/shared/bootstrap.js';
const PAGES = /^resources\/js\/Pages\/.+\.vue$/;
const PAGE_MAX = 150 * KO;

/** Budgets gzip en octets. `strict` : le build échoue ; sinon avertissement seulement. */
const budgets = [
    { entry: 'resources/js/public.js', label: 'JS public (public.js + imports statiques)', max: 60 * KO, strict: true },
    { entry: 'resources/css/critical.css', label: 'CSS critique (inliné)', max: 50 * KO, strict: true },
    { entry: 'resources/css/xomiton.css', label: 'CSS principal (xomiton.css)', max: 120 * KO, strict: false },
    { entry: APP, label: 'JS espace membre (app.ts + imports statiques)', max: 150 * KO, strict: true },
];

if (!existsSync(manifestPath)) {
    console.error(`check-budgets : manifest introuvable (${manifestPath}). Lancer « vite build » d'abord.`);
    process.exit(1);
}

const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));

const sizes = new Map();
const gzipSize = (file) => {
    if (!sizes.has(file)) sizes.set(file, gzipSync(readFileSync(resolve(buildDir, file)), { level: 9 }).length);
    return sizes.get(file);
};

/** Fichiers (JS ou CSS) d'une entrée avec ses imports statiques, sans doublon ; les imports dynamiques sont exclus. */
const filesOf = (key, seen = new Set()) => {
    const chunk = manifest[key];
    if (!chunk || seen.has(key)) return [];
    seen.add(key);
    const files = [chunk.file];
    for (const imported of chunk.imports || []) files.push(...filesOf(imported, seen));
    return files;
};

/**
 * Fichier du morceau de shared/bootstrap.js : clé source, ou morceau partagé « _bootstrap-<hash>.js » (nommé d'après
 * le module, quand public.js et admin.js l'importent en dur et app.ts à la demande).
 */
const bootstrapFile = Object.entries(manifest).find(([key, chunk]) => key === BOOTSTRAP || chunk.src === BOOTSTRAP
    || (chunk.name === 'bootstrap' && /^_bootstrap-[\w-]+\.js$/.test(key)))?.[1].file ?? null;

const fmt = (bytes) => `${(bytes / KO).toFixed(1)} Ko`;
const total = (files) => files.reduce((sum, file) => sum + gzipSize(file), 0);

let failed = false;
console.log('Budgets gzip (public/build/manifest.json) :');

for (const budget of budgets) {
    if (!manifest[budget.entry]) {
        console.log(`  - ${budget.label} : entrée ${budget.entry} absente du manifest${budget.strict ? ' (ÉCHEC)' : ' (ignorée)'}`);
        failed ||= budget.strict;
        continue;
    }
    const files = [...new Set(filesOf(budget.entry))];
    const size = total(files);
    const over = size > budget.max;
    const status = over ? (budget.strict ? 'ÉCHEC' : 'AVERTISSEMENT') : 'ok';
    console.log(`  - ${budget.label} : ${fmt(size)} / ${fmt(budget.max)} ${status}  [${files.join(', ')}]`);
    if (over && budget.strict) failed = true;
}

const appFiles = new Set(filesOf(APP));

if (!bootstrapFile) {
    console.log(`  - Garde Bootstrap : morceau de ${BOOTSTRAP} introuvable dans le manifest (AVERTISSEMENT : garde inopérante)`);
} else if (appFiles.has(bootstrapFile)) {
    console.log(`  - Garde Bootstrap : ${bootstrapFile} parmi les imports statiques d'app.ts (ÉCHEC : passer par shared/bootstrap-lazy.js)`);
    failed = true;
} else {
    console.log(`  - Garde Bootstrap : ${bootstrapFile} hors des imports statiques d'app.ts ok`);
}

const pages = Object.keys(manifest).filter((key) => PAGES.test(key)).sort();
if (pages.length && manifest[APP]) {
    console.log(`JS initial des pages membre (app.ts + morceau de page + imports statiques, indicatif, ${fmt(PAGE_MAX)}) :`);
    for (const page of pages) {
        const own = [...new Set(filesOf(page))].filter((file) => !appFiles.has(file));
        const size = total([...appFiles, ...own]);
        const notes = [];
        if (size > PAGE_MAX) notes.push('AVERTISSEMENT');
        if (bootstrapFile && own.includes(bootstrapFile)) notes.push(`AVERTISSEMENT : ${bootstrapFile} importé en dur`);
        console.log(`  - ${page.replace('resources/js/Pages/', '')} : ${fmt(size)} (+${fmt(total(own))}) ${notes.join(' ; ') || 'ok'}`);
    }
}

if (failed) {
    console.error('check-budgets : au moins un budget strict est dépassé (PERFORMANCE.md §1).');
    process.exit(1);
}
