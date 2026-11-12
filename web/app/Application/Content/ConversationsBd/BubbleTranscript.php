<?php

declare(strict_types=1);

namespace App\Application\Content\ConversationsBd;

use App\Enums\BubbleTailTarget;
use App\Enums\BubbleType;

/**
 * Transcription d'une case pour les lecteurs d'écran et les petits écrans (P20-04, PRD P0-4) : les bulles dans l'ordre
 * de lecture, chacune avec son personnage et sa nature (« Fifamè pense : … », « Message reçu de … : … », « … dit, hors de
 * l'image : … », « Narration : … »). Une chaîne de bulles associées est lue comme une seule réplique (§4). Jumeau Vue :
 * `transcript()` de resources/js/components/bd/bubbles.ts.
 */
final class BubbleTranscript
{
    /**
     * @param  list<array<string, mixed>>  $bubbles  bulles au format de la grammaire, dans l'ordre de lecture
     * @param  array<string, string>  $names  clé du personnage => nom affiché
     * @return list<array{text: string, thought: bool}>
     */
    public static function lines(array $bubbles, array $names): array
    {
        $lines = [];

        foreach (BubbleSvg::chains($bubbles) as $members) {
            $first = $bubbles[$members[0]];
            $last = $bubbles[$members[count($members) - 1]];
            $type = BubbleType::tryFrom((string) ($first['type'] ?? '')) ?? BubbleType::Speech;
            $texts = [];

            foreach ($members as $index) {
                $words = BubbleGeometry::words((string) ($bubbles[$index]['texte'] ?? ''));
                if ($words !== []) {
                    $texts[] = implode(' ', $words);
                }
            }

            $lines[] = ['text' => self::line($type, $first, $last, implode(' ', $texts), $names), 'thought' => $type === BubbleType::Thought];
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $first
     * @param  array<string, mixed>  $last  dernière bulle de la chaîne (celle qui porte la queue)
     * @param  array<string, string>  $names
     */
    private static function line(BubbleType $type, array $first, array $last, string $text, array $names): string
    {
        if ((bool) ($first['masque'] ?? false)) {
            return (string) __('conversations_bd.sr.masked');
        }

        if ($type === BubbleType::Caption) {
            return (string) __('conversations_bd.sr.narration', ['text' => $text]);
        }

        $key = is_string($first['personnage'] ?? null) ? $first['personnage'] : '';
        $name = $names[$key] ?? ($key !== '' ? $key : (string) __('conversations_bd.sr.someone'));
        $tail = is_array($last['queue'] ?? null) ? $last['queue'] : [];
        $target = BubbleTailTarget::tryFrom((string) ($tail['vers'] ?? ''));

        if ($type === BubbleType::Message) {
            return (string) __($target === BubbleTailTarget::Character ? 'conversations_bd.sr.message_sent' : 'conversations_bd.sr.message_received', ['name' => $name, 'text' => $text]);
        }

        if ($type === BubbleType::DeviceVoice) {
            return (string) __('conversations_bd.sr.device', ['name' => $name, 'text' => $text]);
        }

        $verb = (string) __('conversations_bd.sr.verbs.'.$type->value);

        return (string) __($target !== null && $target->isEdge() ? 'conversations_bd.sr.line_offscreen' : 'conversations_bd.sr.line', ['name' => $name, 'verb' => $verb, 'text' => $text]);
    }
}
