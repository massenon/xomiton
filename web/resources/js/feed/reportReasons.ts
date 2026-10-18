// Motifs de la modale « Signaler cette publication / ce commentaire » (lot fil-ecrans, P7-05) : reçus du serveur en
// props `report_reasons` (FeedComposerOptions::reportReasons() = ContentReportReason::selectable(), libellés
// enums/feed.report_reason) et fournis par la page à ses cartes et commentaires (provide / inject) : aucune liste
// recopiée dans le navigateur. Une page qui affiche <XmPost> sans les fournir n'offre que le motif « Autre »
// (ContentReportReason::Other, toujours accepté par l'API).
import { inject, provide, type InjectionKey } from 'vue';
import type { Option } from './types';

export const REPORT_REASONS: InjectionKey<Option[]> = Symbol('xm.feed.reportReasons');

/** = ContentReportReason::Other. */
export const FALLBACK_REASON = 'autre';

export const provideReportReasons = (reasons: Option[] | null | undefined): void => provide(REPORT_REASONS, reasons ?? []);

export const injectReportReasons = (): Option[] => inject(REPORT_REASONS, []);
