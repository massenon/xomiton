<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * État d'un traitement asynchrone (DATABASE_DICTIONARY.md §7 `generated_reports.status`, §10 `backups.status`).
 */
enum JobStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Done = 'done';
    case Failed = 'failed';

    public function label(): string
    {
        return (string) __('enums/steering.job_status.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'secondary',
            self::Running => 'info',
            self::Done => 'success',
            self::Failed => 'danger',
        };
    }

    /** Traitement terminé, avec ou sans succès. */
    public function isFinished(): bool
    {
        return in_array($this, [self::Done, self::Failed], true);
    }

    /**
     * @return list<self>
     */
    public static function unfinished(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $status): bool => ! $status->isFinished()));
    }
}
