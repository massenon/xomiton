// Corps d'une publication découpé en texte et liens (P19-11) : seules les adresses du site deviennent des liens.
import { describe, expect, it } from 'vitest';
import { bodySegments } from './format';

describe('bodySegments', () => {
    it('relie l’adresse du site et laisse le reste en texte', () => {
        expect(bodySegments('Lire la BD : https://xomiton.bj/bd/vn02.', 'https://xomiton.bj')).toEqual([
            { text: 'Lire la BD : ' },
            { text: 'https://xomiton.bj/bd/vn02', href: 'https://xomiton.bj/bd/vn02' },
            { text: '.' },
        ]);
    });

    it('ne relie jamais une adresse externe ni un autre hôte', () => {
        expect(bodySegments('Voir https://exemple.test/x et http://xomiton.bj.exemple.test/', 'https://xomiton.bj')).toEqual([
            { text: 'Voir https://exemple.test/x et http://xomiton.bj.exemple.test/' },
        ]);
    });

    it('rend le texte tel quel sans origine valide ou sans adresse', () => {
        expect(bodySegments('Bonjour', 'https://xomiton.bj')).toEqual([{ text: 'Bonjour' }]);
        expect(bodySegments('https://xomiton.bj/bd', '')).toEqual([{ text: 'https://xomiton.bj/bd' }]);
    });
});
