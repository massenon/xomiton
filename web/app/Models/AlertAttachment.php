<?php

declare(strict_types=1);

namespace App\Models;

use App\Application\Shared\MediaUploader;
use Database\Factories\AlertAttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Preuve jointe à une alerte (P5-04) : fichier sur le disque privé, déjà passé par MediaUploader (métadonnées retirées),
 * servi seulement par lien signé (`media.signed`, 10 min). Aucun nom d'origine ; jamais modifiée.
 */
class AlertAttachment extends Model
{
    /** @use HasFactory<AlertAttachmentFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /** Collections de MediaUploader acceptées comme preuves (config xomiton.uploads.collections). */
    public const KINDS = ['images', 'documents', 'audio'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'alert_id',
        'kind',
        'disk',
        'path',
        'mime',
        'size',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'disk',
        'path',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Alert, $this>
     */
    public function alert(): BelongsTo
    {
        return $this->belongsTo(Alert::class);
    }

    /** Collection déduite du type réel du fichier (image, PDF, audio). */
    public static function kindForMime(string $mime): string
    {
        return match (true) {
            str_starts_with($mime, 'image/') => 'images',
            str_starts_with($mime, 'audio/') => 'audio',
            default => 'documents',
        };
    }

    /** Lien signé temporaire (administration, contact routé). */
    public function signedUrl(int $minutes = 10): string
    {
        return app(MediaUploader::class)->signedUrl($this->disk, $this->path, $minutes);
    }
}
