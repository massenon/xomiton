#!/usr/bin/env node
// Sélecteur d'émojis : valide la source unique (resources/js/emoji/emojis.json) et en dérive la version Dart de l'application
// (../mobile/lib/core/emoji/emoji_data.dart, constantes compilées : aucun asset, quelques dizaines de Ko dans l'APK).
//   node scripts/emoji-sync.mjs           écrit le fichier Dart
//   node scripts/emoji-sync.mjs --check   n'écrit rien, échoue (code 1) si la source est invalide ou le fichier Dart périmé
// Contrôles : chaque émoji est une séquence Unicode RGI (émoji natif, jamais une image), sans doublon, avec un nom
// français ; aucun émoji écarté (resources/js/emoji/excluded.json) ne figure dans la liste.
import { existsSync, readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const source = resolve(root, 'resources/js/emoji/emojis.json');
const excludedFile = resolve(root, 'resources/js/emoji/excluded.json');
const target = resolve(root, '../mobile/lib/core/emoji/emoji_data.dart');
const check = process.argv.includes('--check');

const data = JSON.parse(readFileSync(source, 'utf8'));
const excluded = new Set(JSON.parse(readFileSync(excludedFile, 'utf8')).groups.flatMap((group) => group.emojis));
const errors = [];
const seen = new Set();
const base = (emoji) => emoji.replace(/[\u{1F3FB}-\u{1F3FF}️]/gu, '');

for (const category of data.categories) {
    if (!category.id || !category.label || !category.icon) errors.push(`catégorie incomplète : ${category.id}`);
    for (const entry of category.emojis) {
        if (!/^\p{RGI_Emoji}$/v.test(entry.c)) errors.push(`séquence non standard : ${entry.c}`);
        if (!entry.n) errors.push(`nom manquant : ${entry.c}`);
        if (seen.has(entry.c)) errors.push(`doublon : ${entry.c}`);
        seen.add(entry.c);
        if (excluded.has(entry.c) || excluded.has(base(entry.c))) errors.push(`émoji écarté présent : ${entry.c}`);
    }
}
if (errors.length > 0) {
    console.error(`emoji-sync : ${errors.length} erreur(s)\n- ${errors.join('\n- ')}`);
    process.exit(1);
}

const str = (value) => `'${value.replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/\$/g, '\\$')}'`;
const lines = [
    '// GÉNÉRÉ par web/scripts/emoji-sync.mjs depuis web/resources/js/emoji/emojis.json : ne pas modifier à la main.',
    '// Émojis Unicode natifs (aucune image, aucune police), noms français pour la recherche et l’accessibilité.',
    '',
    '/// Un émoji : `char` (séquence Unicode), `name` (nom français, lu par TalkBack), `keywords` (recherche),',
    '/// `tone` (accepte une teinte de peau).',
    'class EmojiEntry {',
    '  const EmojiEntry(this.char, this.name, [this.keywords = \'\', this.tone = false]);',
    '  final String char;',
    '  final String name;',
    '  final String keywords;',
    '  final bool tone;',
    '}',
    '',
    'class EmojiCategory {',
    '  const EmojiCategory(this.id, this.label, this.icon, this.emojis);',
    '  final String id;',
    '  final String label;',
    '  final String icon;',
    '  final List<EmojiEntry> emojis;',
    '}',
    '',
    `const int kEmojiDataVersion = ${data.version};`,
    '',
    'const List<EmojiCategory> kEmojiCategories = [',
];
for (const category of data.categories) {
    lines.push(`  EmojiCategory(${str(category.id)}, ${str(category.label)}, ${str(category.icon)}, [`);
    for (const e of category.emojis) {
        const args = [str(e.c), str(e.n)];
        if (e.k || e.t) args.push(str(e.k ?? ''));
        if (e.t) args.push('true');
        lines.push(`    EmojiEntry(${args.join(', ')}),`);
    }
    lines.push('  ]),');
}
lines.push('];', '');
const output = lines.join('\n');

const current = existsSync(target) ? readFileSync(target, 'utf8') : null;
if (check) {
    if (current !== output) {
        console.error('emoji-sync : emoji_data.dart est périmé. Lancer « node scripts/emoji-sync.mjs ».');
        process.exit(1);
    }
    console.log('emoji-sync : à jour.');
} else {
    mkdirSync(dirname(target), { recursive: true });
    writeFileSync(target, output);
    console.log(`emoji-sync : ${seen.size} émojis écrits dans ${target}`);
}
