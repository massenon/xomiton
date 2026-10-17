<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Enums\ContentStatus;
use App\Models\Category;
use App\Models\Event;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * events.json → events (date + time → starts_at, going → going_count, organizer texte → organizer_label
 * et organizer_id si un compte porte ce nom, cat → catégorie `event`, image → image_path, place « en ligne »
 * → is_online). Clé naturelle : le slug du titre.
 */
final class ImportEvents extends AbstractImporter
{
    public static function collection(): string
    {
        return 'events';
    }

    public static function isDemo(): bool
    {
        return true;
    }

    protected function importRow(array $row, int $index): void
    {
        $title = $this->required($row['title'] ?? null, 'title', 200);
        $slug = Str::slug($title);

        if ($slug === '') {
            $this->reject(sprintf('titre « %s » sans slug possible', $title));
        }

        $startsAt = $this->startsAt($row['date'] ?? null, $row['time'] ?? null)
            ?? $this->reject('date ou heure de début absente ou invalide');
        $place = $this->text($row['place'] ?? null, 200);
        $organizer = $this->text($row['organizer'] ?? null, 120);

        $event = Event::withTrashed()->firstOrNew(['slug' => $slug]);

        $event->fill([
            'title' => $title,
            'category_id' => $this->categoryId($row['cat'] ?? $row['category'] ?? null),
            'starts_at' => $startsAt,
            'place' => $place,
            'is_online' => $place !== null && str_contains(mb_strtolower($place), 'en ligne'),
            'description' => $this->text($row['description'] ?? null),
            'image_path' => $this->imagePath($row['image'] ?? null),
            'organizer_id' => $this->ctx->userIdByName($organizer),
            'organizer_label' => $organizer,
            'going_count' => max(0, (int) ($row['going'] ?? 0)),
            'status' => ContentStatus::Published,
        ]);
        $event->deleted_at = null;
        $event->save();

        $this->ctx->remember('events', $this->prototypeId($row, $index), (int) $event->getKey());
        $this->track($event);
    }

    private function startsAt(mixed $date, mixed $time): ?Carbon
    {
        $date = $this->text($date);

        if ($date === null) {
            return null;
        }

        try {
            return Carbon::parse(trim($date.' '.($this->text($time) ?? '00:00')), $this->ctx->timezone());
        } catch (Throwable) {
            return null;
        }
    }

    /** Catégorie d'événement (`categories.kind = event`), créée depuis le libellé du prototype si elle manque. */
    private function categoryId(mixed $label): ?int
    {
        $label = $this->text($label, 80);

        if ($label === null) {
            return null;
        }

        $slug = Str::slug($label);

        if ($slug === '') {
            return null;
        }

        $category = Category::query()->firstOrCreate(
            ['kind' => Category::KIND_EVENT, 'slug' => $slug],
            [
                'label' => $label,
                'position' => ((int) Category::query()->ofKind(Category::KIND_EVENT)->max('position')) + 1,
                'is_active' => true,
            ],
        );

        if ($category->wasRecentlyCreated) {
            $this->ctx->note(sprintf('catégorie d’événement « %s » créée', $label));
        }

        return (int) $category->getKey();
    }
}
