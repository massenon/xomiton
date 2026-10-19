// Garde-fous de crise côté page (P8-11) : même normalisation que KnowledgeBaseAnswerer::normalize et même règle de mot
// entier (pluriel en -s / -x toléré), sans lookbehind.
import { describe, expect, it } from 'vitest';
import { detectCrisis, normalize } from './crisis';
// Écrit par tests/Feature/Assistant/CrisisParityTest.php : mots-clés servis à la page et résultat du serveur.
import parity from './crisis.parity.json';

const KEYWORDS = ['suicide', 'me tuer', 'il va me tuer', 'viol', 'frappe', 'sequestree', 'en danger'];

describe('normalize()', () => {
    it('suit la forme du serveur : minuscules, sans accent, ponctuation et apostrophes effacées', () => {
        expect(normalize('  Qu’est-ce que le CONSENTEMENT ?!  ')).toBe('qu est ce que le consentement');
        expect(normalize('Dépistage VIH / IST')).toBe('depistage vih ist');
        expect(normalize('Ma sœur, Séquestrée')).toBe('ma soeur sequestree');
        expect(normalize('   ')).toBe('');
    });
});

describe('detectCrisis()', () => {
    it('trouve un mot ou un groupe de mots entier, quels que soient accents, casse et ponctuation', () => {
        expect(detectCrisis('Il va me TUER !', KEYWORDS)).toBe('me tuer');
        expect(detectCrisis('Je suis séquestrée chez lui', KEYWORDS)).toBe('sequestree');
        expect(detectCrisis('il me frappe', KEYWORDS)).toBe('frappe');
        expect(detectCrisis('je suis en danger.', KEYWORDS)).toBe('en danger');
    });

    it('tolère le pluriel, pas un mot qui en contient un autre', () => {
        expect(detectCrisis('des viols répétés', KEYWORDS)).toBe('viol');
        expect(detectCrisis('les violences conjugales', KEYWORDS)).toBeNull();
        expect(detectCrisis('Comment porter plainte ?', KEYWORDS)).toBeNull();
        expect(detectCrisis('', KEYWORDS)).toBeNull();
        expect(detectCrisis('il me frappe', [])).toBeNull();
    });
});

describe('parité avec KnowledgeBaseAnswerer::detectCrisis', () => {
    it('trouve la même crise que le serveur sur chaque mot-clé et ses variantes (crisis.parity.json)', () => {
        expect(parity.keywords.length).toBeGreaterThan(0);
        expect(parity.samples.some((sample) => sample.match === null)).toBe(true);
        const got = parity.samples.map((sample) => ({ text: sample.text, match: detectCrisis(sample.text, parity.keywords) }));
        expect(got).toEqual(parity.samples);
    });
});
