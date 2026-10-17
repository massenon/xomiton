<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Models\GatewayTemplate;

/**
 * gateway_templates.json → gateway_templates (title, text → body, catégorie déduite du titre comme le
 * seeder : prévention / dépistage / SOS). Déjà semés : réalignés par titre, `is_active` conservé.
 */
final class ImportGatewayTemplates extends AbstractImporter
{
    public static function collection(): string
    {
        return 'gateway_templates';
    }

    protected function importRow(array $row, int $index): void
    {
        $title = $this->required($row['title'] ?? null, 'title', 80);
        $body = $this->required($row['text'] ?? $row['body'] ?? null, 'text', GatewayTemplate::BODY_MAX_LENGTH);

        $template = GatewayTemplate::query()->firstOrNew(['title' => $title]);

        $template->fill([
            'body' => $body,
            'category' => $this->category($title),
            'position' => $index + 1,
        ]);

        if (! $template->exists) {
            $template->is_active = true;
        }

        $template->save();
        $this->track($template);
    }

    private function category(string $title): ?string
    {
        $title = mb_strtolower($title);

        return match (true) {
            str_contains($title, 'dépistage'), str_contains($title, 'depistage') => GatewayTemplate::CATEGORY_DEPISTAGE,
            str_contains($title, 'sos') => GatewayTemplate::CATEGORY_SOS,
            str_contains($title, 'prévention'), str_contains($title, 'prevention') => GatewayTemplate::CATEGORY_PREVENTION,
            default => null,
        };
    }
}
