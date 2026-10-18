// Outils des tests Vitest de l'espace Vue (P6-04, P6-05) : rendu d'un composant avec Pinia neuf et vue-i18n chargé du
// vrai catalogue (resources/js/lang/fr.json, export de lang/fr), et normalisation HTML du test de parité Blade / Vue.
import { createPinia, setActivePinia, type Pinia } from 'pinia';
import { render, type RenderOptions } from '@testing-library/vue';
import type { Component } from 'vue';
import { i18n, loadMessages } from '@/app/i18n';
import fr from '@/lang/fr.json';

export function useFrench(): void {
    loadMessages('fr', fr as unknown as Record<string, string>);
}

export function freshPinia(): Pinia {
    const pinia = createPinia();
    setActivePinia(pinia);
    return pinia;
}

/** Rend un composant Xm* comme dans l'application (Pinia + i18n français). */
export function renderXm(component: Component, options: RenderOptions<Component> = {}) {
    const pinia = freshPinia();
    useFrench();
    const global = options.global ?? {};
    return render(component, { ...options, global: { ...global, plugins: [pinia, i18n, ...(global.plugins ?? [])] } });
}

export interface NormalizeOptions {
    /** Attributs propres à une technologie (Bootstrap data-api côté Blade, gestion Vue côté Xm*). */
    ignoreAttributes?: string[];
    /** false : textes ignorés (contenu écrit par Alpine x-text côté Blade). */
    text?: boolean;
    /** false : attribut style ignoré (x-show Alpine vs v-show Vue). */
    style?: boolean;
}

const BOOLEAN_ATTRIBUTES = new Set(['disabled', 'required', 'hidden', 'novalidate', 'readonly', 'multiple', 'autofocus']);
// État vivant d'un champ (propriété DOM côté Vue, attribut côté Blade) : vérifié par les tests du composant.
const LIVE_STATE = new Set(['checked', 'selected']);

const keepAttribute = (el: Element, name: string, opts: NormalizeOptions): boolean => {
    if (name.startsWith('x-') || name.startsWith('data-xm-') || name.startsWith(':') || name.startsWith('@')) return false;
    if (opts.ignoreAttributes?.includes(name)) return false;
    if (LIVE_STATE.has(name)) return false;
    if (name === 'style' && opts.style === false) return false;
    if (name === 'value' && (el.tagName === 'SELECT' || el.tagName === 'TEXTAREA')) return false;
    if (name === 'value' && el.tagName === 'INPUT' && !['checkbox', 'radio'].includes((el.getAttribute('type') || '').toLowerCase())) return false;
    return true;
};

const escapeText = (text: string) => text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const escapeAttr = (text: string) => escapeText(text).replace(/"/g, '&quot;');

function serialize(node: Node, opts: NormalizeOptions): string {
    let out = '';
    node.childNodes.forEach((child) => {
        if (child.nodeType === Node.TEXT_NODE) {
            if (opts.text === false) return;
            const parent = child.parentElement;
            if (parent && parent.tagName === 'TEXTAREA') return;
            const text = (child.textContent || '').replace(/\s+/g, ' ').trim();
            if (text) out += escapeText(text);
            return;
        }
        if (child.nodeType !== Node.ELEMENT_NODE) return;
        const el = child as Element;
        const tag = el.tagName.toLowerCase();
        const attrs = [...el.attributes]
            .filter((attr) => keepAttribute(el, attr.name, opts))
            .map((attr) => {
                let value = attr.value;
                if (attr.name === 'class') value = [...new Set(value.split(/\s+/).filter(Boolean))].sort().join(' ');
                if (BOOLEAN_ATTRIBUTES.has(attr.name)) value = '';
                if (['href', 'src', 'srcset'].includes(attr.name)) value = value.replace(/https?:\/\/[^/"\s]+(?=\/|$)/g, '') || '/';
                return [attr.name, value] as const;
            })
            .filter(([name, value]) => !(name === 'class' && value === ''))
            .sort(([a], [b]) => (a < b ? -1 : a > b ? 1 : 0));
        out += `<${tag}${attrs.map(([name, value]) => ` ${name}="${escapeAttr(value)}"`).join('')}>`;
        out += serialize(tag === 'template' ? (el as HTMLTemplateElement).content : el, opts);
        if (!['img', 'input', 'source', 'br', 'hr', 'meta', 'link'].includes(tag)) out += `</${tag}>`;
    });
    return out;
}

/** Arbre HTML comparable : classes et attributs triés, espaces repliés, commentaires et crochets Alpine ignorés. */
export function normalizeHtml(html: string, opts: NormalizeOptions = {}): string {
    const template = document.createElement('template');
    template.innerHTML = html;
    return serialize(template.content, opts);
}
