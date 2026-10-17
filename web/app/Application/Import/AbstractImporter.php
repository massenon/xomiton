<?php

declare(strict_types=1);

namespace App\Application\Import;

use Illuminate\Database\Eloquent\Model;

/**
 * Importeur d'une collection du prototype : une classe par fichier JSON. Chaque ligne passe par
 * importRow() ; une RejectedRow est journalisée avec sa raison et l'import continue. Les compteurs du
 * rapport sont alimentés par track() (créé / mis à jour selon wasRecentlyCreated).
 */
abstract class AbstractImporter
{
    public function __construct(protected readonly ImportContext $ctx) {}

    /** Nom de la collection = nom du fichier JSON du prototype (`users`, `annuaire`, `cases`…). */
    abstract public static function collection(): string;

    /** Contenu de démonstration : importé hors production, ou avec --with-demo. */
    public static function isDemo(): bool
    {
        return false;
    }

    /** Données personnelles (annuaire) : mêmes conditions que la démonstration. */
    public static function holdsPersonalData(): bool
    {
        return false;
    }

    /**
     * @param  array<string, mixed>  $row
     *
     * @throws RejectedRow
     */
    abstract protected function importRow(array $row, int $index): void;

    public function run(): void
    {
        $collection = static::collection();
        $this->ctx->report->touch($collection);

        foreach ($this->rows() as $index => $row) {
            try {
                $this->importRow($row, $index);
            } catch (RejectedRow $rejected) {
                $this->ctx->reject($collection, $this->prototypeId($row, $index), $rejected->getMessage());
            }
        }

        $this->afterAll();
    }

    /** @return list<array<string, mixed>> */
    protected function rows(): array
    {
        return $this->ctx->data->rows(static::collection());
    }

    /** Traitement de fin de collection (compteurs dénormalisés, totaux). */
    protected function afterAll(): void {}

    /** @param  array<string, mixed>  $row */
    protected function prototypeId(array $row, int $index): string|int
    {
        $id = $row['id'] ?? null;

        return is_scalar($id) && (string) $id !== '' ? (is_int($id) ? $id : (string) $id) : $index + 1;
    }

    /** Compte la ligne comme créée ou mise à jour selon que le modèle vient d'être inséré. */
    protected function track(Model $model): void
    {
        $model->wasRecentlyCreated
            ? $this->ctx->report->created(static::collection())
            : $this->ctx->report->updated(static::collection());
    }

    protected function reject(string $reason): never
    {
        throw new RejectedRow($reason);
    }

    /** Chaîne nettoyée du prototype, null si vide. */
    protected function text(mixed $value, ?int $max = null): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        return $max !== null ? mb_substr($text, 0, $max) : $text;
    }

    /** Chaîne obligatoire : rejet si vide. */
    protected function required(mixed $value, string $field, ?int $max = null): string
    {
        return $this->text($value, $max) ?? $this->reject(sprintf('champ « %s » manquant', $field));
    }

    /** Chemin public d'une image de démonstration du prototype (`hero-12.jpg` → `assets/images/xomiton/hero-12.jpg`). */
    protected function imagePath(mixed $file): ?string
    {
        $file = $this->text($file);

        if ($file === null) {
            return null;
        }

        if (str_starts_with($file, 'http://') || str_starts_with($file, 'https://') || str_starts_with($file, 'assets/')) {
            return $file;
        }

        return 'assets/images/xomiton/'.ltrim($file, '/');
    }
}
