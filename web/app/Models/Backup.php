<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BackupKind;
use App\Enums\JobStatus;
use Database\Factories\BackupFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Sauvegarde de la base (DATABASE_DICTIONARY.md §10 `backups`) : métadonnées et inventaire des tables ;
 * le fichier est sur `storage_disk` à `storage_path`, hors racine web.
 */
class Backup extends Model
{
    /** @use HasFactory<BackupFactory> */
    use HasFactory;

    public const DEFAULT_DISK = 'local';

    /**
     * `tables` n'est jamais NULL en base : une sauvegarde en échec porte un inventaire vide.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'tables' => '[]',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'kind',
        'size_bytes',
        'tables',
        'storage_disk',
        'storage_path',
        'checksum',
        'status',
        'created_by',
        'restored_at',
        'restored_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => BackupKind::class,
            'size_bytes' => 'integer',
            'tables' => 'array',
            'status' => JobStatus::class,
            'restored_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function restorer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'restored_by');
    }

    /**
     * @param  Builder<Backup>  $query
     */
    public function scopeOfKind(Builder $query, BackupKind $kind): void
    {
        $query->where('kind', $kind->value);
    }

    /**
     * Sauvegardes complètes, restaurables.
     *
     * @param  Builder<Backup>  $query
     */
    public function scopeCompleted(Builder $query): void
    {
        $query->where('status', JobStatus::Done->value);
    }

    /**
     * @param  Builder<Backup>  $query
     */
    public function scopeRestored(Builder $query): void
    {
        $query->whereNotNull('restored_at');
    }

    /**
     * @param  Builder<Backup>  $query
     */
    public function scopeLatestFirst(Builder $query): void
    {
        $query->orderByDesc('created_at')->orderByDesc('id');
    }

    public function isRestorable(): bool
    {
        return $this->status === JobStatus::Done;
    }

    public function isRestored(): bool
    {
        return $this->restored_at !== null;
    }

    /**
     * Nombre total de lignes inventoriées (`tables` : `[{name, rows}]`).
     */
    public function rowsCount(): int
    {
        return (int) array_sum(array_map(static fn (array $table): int => (int) ($table['rows'] ?? 0), $this->tables));
    }

    /**
     * Taille lisible, comme `mj_size()` du prototype (« 1,2 Mo », « 48 Ko »).
     */
    public function humanSize(): string
    {
        if ($this->size_bytes >= 1048576) {
            return str_replace('.', ',', (string) round($this->size_bytes / 1048576, 1)).' Mo';
        }

        return max(1, (int) round($this->size_bytes / 1024)).' Ko';
    }

    /**
     * Nom de sauvegarde du prototype : `AAAAMMJJ-HHMMSS[-suffixe]` (unique à la seconde près).
     */
    public static function nameFor(BackupKind $kind, ?Carbon $at = null): string
    {
        $name = ($at ?? now())->format('Ymd-His');
        $suffix = $kind->suffix();

        return $suffix === null ? $name : $name.'-'.$suffix;
    }
}
