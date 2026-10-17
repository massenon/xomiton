<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\TemplateCategory;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Modèles d'e-mails du lot 10 (DATABASE_DICTIONARY.md §9 `email_templates`), repris du prototype
 * (database/seeders/data/communication/email_templates.php ← prototype_social/data/email_templates.json) : 8 templates
 * du prototype + le template système `mot-de-passe-modifie` (P2-05), soit 9 (6 système).
 * Idempotent : un template absent est créé avec ses textes ; un template déjà présent n'est réaligné que sur
 * `name`, `category`, `is_system` et ses `variables` recalculées, car sujet, pré-en-tête, corps et `is_active`
 * sont éditables en administration et ne doivent pas être écrasés au rejeu.
 */
class EmailTemplatesSeeder extends Seeder
{
    /** Compte de l'administrateur·rice du prototype (`updated_by` : admin@xomiton.bj), s'il existe. */
    private const ADMIN_EMAIL = 'admin@xomiton.bj';

    public function run(): void
    {
        /** @var list<array{name: string, slug: string, category: string, system: bool, subject: string, preheader: string|null, body: string, active: bool}> $templates */
        $templates = require __DIR__.'/data/communication/email_templates.php';

        $adminId = User::query()->where('email', self::ADMIN_EMAIL)->value('id');

        foreach ($templates as $data) {
            $template = EmailTemplate::query()->firstOrNew(['slug' => $data['slug']]);

            $template->fill([
                'name' => $data['name'],
                'category' => TemplateCategory::fromPrototype($data['category']),
                'is_system' => $data['system'],
            ]);

            if (! $template->exists) {
                $template->fill([
                    'subject' => $data['subject'],
                    'preheader' => $data['preheader'],
                    'body' => $data['body'],
                    'is_active' => $data['active'],
                    'updated_by' => $adminId,
                ]);
            }

            // Les variables sont recalculées par EmailTemplate::saving() à partir des textes courants.
            $template->save();
        }
    }
}
