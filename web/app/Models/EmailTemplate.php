<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TemplateCategory;
use Database\Factories\EmailTemplateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modèle d'e-mail éditable en administration (DATABASE_DICTIONARY.md §9 `email_templates`).
 * Les variables `{{nom}}` attendues par le rendu sont détectées dans le sujet, le pré-en-tête et le corps ;
 * le slug d'un template système est immuable (RG-10).
 */
class EmailTemplate extends Model
{
    /** @use HasFactory<EmailTemplateFactory> */
    use HasFactory;

    /** Motif d'une variable de template : `{{ user_first_name }}`, espaces tolérés. */
    public const VARIABLE_PATTERN = '/\{\{\s*([a-z][a-z0-9_]*)\s*\}\}/i';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'category',
        'is_system',
        'subject',
        'preheader',
        'body',
        'variables',
        'is_active',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => TemplateCategory::class,
            'is_system' => 'boolean',
            'variables' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Les variables sont toujours déduites du contenu : jamais saisies à la main.
        static::saving(function (EmailTemplate $template): void {
            $template->variables = self::extractVariables(
                (string) $template->subject,
                (string) $template->preheader,
                (string) $template->body,
            );
        });
    }

    /**
     * Dernier·ère administrateur·rice à avoir modifié le template.
     *
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Journaux d'envoi qui ont utilisé ce template (liaison par slug, conservée si le template disparaît).
     *
     * @return HasMany<MailLog, $this>
     */
    public function mailLogs(): HasMany
    {
        return $this->hasMany(MailLog::class, 'template_slug', 'slug');
    }

    /**
     * E-mails en file d'attente rendus avec ce template (liaison par slug).
     *
     * @return HasMany<MailOutbox, $this>
     */
    public function outboxMails(): HasMany
    {
        return $this->hasMany(MailOutbox::class, 'template_slug', 'slug');
    }

    /**
     * @param  Builder<EmailTemplate>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<EmailTemplate>  $query
     */
    public function scopeSystem(Builder $query): void
    {
        $query->where('is_system', true);
    }

    /**
     * @param  Builder<EmailTemplate>  $query
     */
    public function scopeInCategory(Builder $query, TemplateCategory $category): void
    {
        $query->where('category', $category->value);
    }

    /**
     * @param  Builder<EmailTemplate>  $query
     */
    public function scopeWhereSlug(Builder $query, string $slug): void
    {
        $query->where('slug', $slug);
    }

    public function isSystem(): bool
    {
        return $this->is_system;
    }

    /** Un template système garde son slug : le rendu applicatif le référence en dur (RG-10). */
    public function canRenameSlug(): bool
    {
        return ! $this->is_system;
    }

    /**
     * Variables `{{...}}` distinctes trouvées dans les textes donnés, dans l'ordre d'apparition.
     *
     * @return list<string>
     */
    public static function extractVariables(string ...$sources): array
    {
        $found = [];

        foreach ($sources as $source) {
            if (preg_match_all(self::VARIABLE_PATTERN, $source, $matches) > 0) {
                foreach ($matches[1] as $name) {
                    $found[] = mb_strtolower($name);
                }
            }
        }

        return array_values(array_unique($found));
    }
}
