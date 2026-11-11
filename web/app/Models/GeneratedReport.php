<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JobStatus;
use App\Enums\ReportFormat;
use App\Enums\ReportKind;
use Database\Factories\GeneratedReportFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rapport généré (DATABASE_DICTIONARY.md §7 `generated_reports`) : demandé à l'écran ou par une
 * planification, produit par un job (`status`), fichier conservé jusqu'à `expires_at`.
 */
class GeneratedReport extends Model
{
    /** @use HasFactory<GeneratedReportFactory> */
    use HasFactory;

    /**
     * Désagrégations admises (`dimensions`), telles que l'écran Rapports du prototype les propose.
     *
     * @var list<string>
     */
    public const DIMENSIONS = ['departement', 'commune', 'population', 'sexe', 'age'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'kind',
        'period_start',
        'period_end',
        'dimensions',
        'format',
        'filters',
        'rows_count',
        'file_path',
        'file_size',
        'status',
        'error',
        'recipients',
        'generated_by',
        'completed_at',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ReportKind::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'dimensions' => 'array',
            'format' => ReportFormat::class,
            'filters' => 'array',
            'rows_count' => 'integer',
            'file_size' => 'integer',
            'status' => JobStatus::class,
            'recipients' => 'array',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * @param  Builder<GeneratedReport>  $query
     */
    public function scopeWithStatus(Builder $query, JobStatus $status): void
    {
        $query->where('status', $status);
    }

    /**
     * Rapports en attente ou en cours de génération.
     *
     * @param  Builder<GeneratedReport>  $query
     */
    public function scopeUnfinished(Builder $query): void
    {
        $query->whereIn('status', JobStatus::unfinished());
    }

    /**
     * @param  Builder<GeneratedReport>  $query
     */
    public function scopeDone(Builder $query): void
    {
        $query->where('status', JobStatus::Done);
    }

    /**
     * @param  Builder<GeneratedReport>  $query
     */
    public function scopeFailed(Builder $query): void
    {
        $query->where('status', JobStatus::Failed);
    }

    /**
     * @param  Builder<GeneratedReport>  $query
     */
    public function scopeOfKind(Builder $query, ReportKind $kind): void
    {
        $query->where('kind', $kind);
    }

    /**
     * Fichiers dont la durée de conservation est dépassée (purge planifiée).
     *
     * @param  Builder<GeneratedReport>  $query
     */
    public function scopeExpired(Builder $query): void
    {
        $query->whereNotNull('file_path')->whereNotNull('expires_at')->where('expires_at', '<=', now());
    }

    /**
     * Fichiers encore téléchargeables.
     *
     * @param  Builder<GeneratedReport>  $query
     */
    public function scopeDownloadable(Builder $query): void
    {
        $query->done()
            ->whereNotNull('file_path')
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function isFinished(): bool
    {
        return $this->status->isFinished();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function hasFile(): bool
    {
        return $this->file_path !== null && ! $this->isExpired();
    }

    /** Le fichier contient le registre détaillé (lignes pseudonymisées, permission `cases.export`). */
    public function hasDetail(): bool
    {
        return (bool) (($this->filters ?? [])['detail'] ?? false);
    }

    /** Période affichée à la façon du prototype : « 01/01/2026 – 31/12/2026 ». */
    public function periodLabel(): string
    {
        return $this->period_start->format('d/m/Y').' – '.$this->period_end->format('d/m/Y');
    }
}
