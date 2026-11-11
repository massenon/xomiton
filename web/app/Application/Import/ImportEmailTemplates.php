<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Enums\TemplateCategory;
use App\Models\EmailTemplate;
use Illuminate\Support\Str;

/**
 * email_templates.json → email_templates (slug, name, category, system → is_system conservé, subject,
 * preheader, body, active → is_active, updated_by e-mail → FK, created / updated → horodatages).
 * Déjà semés : réalignés par slug ; les variables sont recalculées par le modèle.
 */
final class ImportEmailTemplates extends AbstractImporter
{
    public static function collection(): string
    {
        return 'email_templates';
    }

    protected function importRow(array $row, int $index): void
    {
        $name = $this->required($row['name'] ?? null, 'name', 80);
        $slug = $this->text($row['slug'] ?? null, 60) ?? Str::slug($name);

        if ($slug === '') {
            $this->reject('slug impossible à déterminer');
        }

        $template = EmailTemplate::query()->firstOrNew(['slug' => $slug]);

        $template->fill([
            'name' => $name,
            'category' => TemplateCategory::fromPrototype((string) ($row['category'] ?? '')),
            'is_system' => (bool) ($row['system'] ?? $template->is_system ?? false),
            'subject' => $this->required($row['subject'] ?? null, 'subject', 150),
            'preheader' => $this->text($row['preheader'] ?? null, 150),
            'body' => $this->required($row['body'] ?? null, 'body'),
            'is_active' => (bool) ($row['active'] ?? true),
            'updated_by' => $this->ctx->userIdByEmail((string) ($row['updated_by'] ?? '')),
        ]);

        if (($createdAt = $this->ctx->dateTime($row['created'] ?? null)) !== null && ! $template->exists) {
            $template->created_at = $createdAt;
        }

        if (($updatedAt = $this->ctx->dateTime($row['updated'] ?? null)) !== null) {
            $template->updated_at = $updatedAt;
        }

        $template->save();
        $this->track($template);
    }
}
