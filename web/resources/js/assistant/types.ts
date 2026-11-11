// Contrat des props de la page /assistant (P8-11, App\Http\Controllers\Web\Member\AssistantPageController) et des
// réponses de l'API assistant (docs/API.md §5).
import type { ChatLink } from './history';

export interface CrisisProps {
    /** Mots-clés de `xomiton.assistant.crisis_keywords`, déjà sous forme comparable. */
    keywords: string[];
    /** Réponse de crise (numéros de settings.emergency_numbers, SOS, signalement, « Parler à un humain »), sans URL. */
    text: string;
    /** Liens de la réponse de crise de l'API : SOS, `tel:` par numéro, besoin, signalement, urgences. */
    links: ChatLink[];
}

export interface OfflineProps {
    text: string;
    escalation: string;
    call: { label: string; url: string } | null;
}

export interface EscalationProps {
    channels: Record<string, string>;
    message_max: number;
}

export interface AssistantLinks {
    sos: string;
    urgences: string;
    annuaire: string;
}

/** `POST /api/v1/assistant/question` → `data` (compte connecté : `conversation_id`, `escalation`). */
export interface AnswerData {
    text: string;
    kind: 'kb' | 'crisis' | 'fallback' | string;
    emergency: boolean;
    suggestions: string[];
    links: ChatLink[];
    conversation_id?: number | null;
    escalation?: boolean;
}

/** `POST /api/v1/assistant/escalate` → `data` (AlertResource : code de suivi, contact routé). */
export interface EscalationResult {
    id: string;
    tracking_code: string;
    tracking_code_display?: string;
    routed?: { role: string; name?: string } | null;
}

export const QUESTION_ENDPOINT = '/api/v1/assistant/question';
export const conversationEndpoint = (id: number): string => `/api/v1/assistant/conversations/${encodeURIComponent(String(id))}`;
