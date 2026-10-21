<?php

declare(strict_types=1);

namespace App\Application\Directory;

use App\Application\Directory\Data\DirectoryImportReport;
use App\Application\Shared\Referentials;
use App\Enums\DirectoryRole;
use App\Models\DirectoryContact;
use App\Models\User;
use App\Support\Audit;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Import CSV de l'annuaire (P11-04 ; prototype admin/annuaire.php l.47-62) : une ligne = une fiche, validée par les
 * mêmes règles que la modale (DirectoryContactRules : téléphone `phone_bj`, e-mail, commune du département, rôle connu).
 * Les lignes valides sont enregistrées — créées, ou **mises à jour** quand l'e-mail (sinon le couple nom + rôle) existe
 * déjà, ce qui rend l'import rejouable sans doublon — et les autres sont rejetées avec leur motif dans un rapport
 * (DirectoryImportReport). Fichier UTF-8 ou Windows-1252, séparateur « ; », « , » ou tabulation, 1 Mo et
 * `MAX_ROWS` lignes au plus. Autorisation : `DirectoryContactPolicy::import` (`directory.manage`). Une seule ligne
 * d'audit « directory.imported » (effectifs, jamais de coordonnées).
 */
final class ImportDirectoryCsv
{
    public const MAX_ROWS = 1000;

    public const MAX_BYTES = 1_048_576;

    public function __construct(
        private readonly DirectoryContactRules $rules,
        private readonly SaveDirectoryContact $save,
        private readonly Referentials $referentials,
    ) {}

    /**
     * @throws DomainException fichier illisible, trop gros, sans les colonnes « Nom » et « Rôle » ou trop long
     */
    public function __invoke(User $by, string $content): DirectoryImportReport
    {
        Gate::forUser($by)->authorize('import', DirectoryContact::class);

        if (strlen($content) > self::MAX_BYTES) {
            throw new DomainException((string) __('admin_data.directory.import.errors.too_big'));
        }

        $rows = $this->parse($content);

        if (count($rows) > self::MAX_ROWS) {
            throw new DomainException((string) __('admin_data.directory.import.errors.too_long', ['max' => self::MAX_ROWS]));
        }

        $created = 0;
        $updated = 0;
        $rejected = [];

        DB::transaction(function () use ($rows, &$created, &$updated, &$rejected): void {
            foreach ($rows as $line => $row) {
                [$data, $problems] = $this->prepare($row);
                $validator = Validator::make($data, $this->rules->rules(), [], (array) __('admin_data.directory.fields'));
                $reasons = $problems;

                if ($validator->fails()) {
                    // Rôle, département et commune inconnus sont déjà expliqués par `prepare()`.
                    foreach ($validator->errors()->messages() as $field => $messages) {
                        if ($problems === [] || ! in_array($field, ['role', 'zone_id', 'commune_id'], true)) {
                            array_push($reasons, ...array_values($messages));
                        }
                    }
                } elseif ($problems === []) {
                    $reasons = array_values(DirectoryContactRules::crossChecks($validator->validated()));
                }

                if ($reasons !== []) {
                    $rejected[] = ['line' => $line, 'name' => Str::limit(trim((string) ($row['name'] ?? '')), 60, '…'), 'reasons' => $reasons];

                    continue;
                }

                $existing = $this->existing($validator->validated());
                $this->save->persist($validator->validated(), $existing);
                $existing === null ? $created++ : $updated++;
            }
        });

        $report = new DirectoryImportReport($created, $updated, $rejected);

        Audit::log('directory.imported', null, sprintf('lignes=%d ; créées=%d ; mises à jour=%d ; rejetées=%d', $report->total(), $created, $updated, count($rejected)), $by);

        return $report;
    }

    /**
     * Lignes du fichier indexées par leur numéro de ligne (l'en-tête est la ligne 1), colonnes reconnues seulement.
     *
     * @return array<int, array<string, string>>
     */
    private function parse(string $content): array
    {
        $content = (string) preg_replace('/^\xEF\xBB\xBF/', '', $content);

        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $firstLine = strtok($content, "\n") ?: '';
        $delimiter = collect([';' => substr_count($firstLine, ';'), ',' => substr_count($firstLine, ','), "\t" => substr_count($firstLine, "\t")])->sortDesc()->keys()->first();
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new DomainException((string) __('admin_data.directory.import.errors.unreadable'));
        }

        fwrite($stream, $content);
        rewind($stream);

        $map = null;
        $rows = [];
        $line = 0;

        while (($cells = fgetcsv($stream, 0, (string) $delimiter, '"', '')) !== false) {
            $line++;

            if ($map === null) {
                $map = [];

                foreach ($cells as $index => $header) {
                    $column = DirectoryCsv::columnFor((string) $header);

                    if ($column !== null && ! in_array($column, $map, true)) {
                        $map[$index] = $column;
                    }
                }

                if (! in_array('name', $map, true) || ! in_array('role', $map, true)) {
                    fclose($stream);

                    throw new DomainException((string) __('admin_data.directory.import.errors.columns'));
                }

                continue;
            }

            $row = [];

            foreach ($map as $index => $column) {
                $row[$column] = trim((string) ($cells[$index] ?? ''));
            }

            if (implode('', $row) === '') {
                continue;
            }

            $rows[$line] = $row;
        }

        fclose($stream);

        return $rows;
    }

    /**
     * Ligne du fichier → données de la fiche, avec les problèmes de correspondance (rôle, département, commune inconnus).
     *
     * @param  array<string, string>  $row
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    private function prepare(array $row): array
    {
        $problems = [];
        $roleCell = $row['role'] ?? '';
        $role = DirectoryRole::tryFromLabel($roleCell) ?? DirectoryRole::tryFrom(Str::of($roleCell)->lower()->ascii()->replace([' ', '-'], '_')->toString());

        if ($role === null) {
            $problems[] = (string) __('admin_data.directory.import.errors.role', ['role' => Str::limit($roleCell, 40, '…')]);
        }

        $zone = null;
        $commune = null;

        if (($row['zone'] ?? '') !== '') {
            $zone = $this->zone($row['zone']);

            if ($zone === null) {
                $problems[] = (string) __('admin_data.directory.import.errors.zone', ['zone' => Str::limit($row['zone'], 40, '…')]);
            } elseif (($row['commune'] ?? '') !== '') {
                $commune = $this->commune($zone, $row['commune']);

                if ($commune === null) {
                    $problems[] = (string) __('admin_data.directory.import.errors.commune', ['commune' => Str::limit($row['commune'], 40, '…'), 'zone' => $zone['name']]);
                }
            }
        } elseif (($row['commune'] ?? '') !== '') {
            $problems[] = (string) __('admin_data.directory.import.errors.commune_without_zone');
        }

        return [[
            'name' => $row['name'] ?? '',
            'role' => $role?->value,
            'org' => $row['org'] ?? null,
            'availability' => $row['availability'] ?? null,
            'phone' => $row['phone'] ?? null,
            'email' => $row['email'] ?? null,
            'whatsapp' => $row['whatsapp'] ?? null,
            'zone_id' => $zone['id'] ?? null,
            'commune_id' => $commune['id'] ?? null,
            'is_active' => DirectoryCsv::toBool($row['active'] ?? null, true),
            'is_public' => DirectoryCsv::toBool($row['public'] ?? null, true),
            'is_emergency' => DirectoryCsv::toBool($row['emergency'] ?? null, false),
            'is_national_focal' => DirectoryCsv::toBool($row['national_focal'] ?? null, false),
        ], $problems];
    }

    /**
     * @return array{id: int, name: string, communes: list<array{id: int, name: string}>}|null
     */
    private function zone(string $name): ?array
    {
        $key = DirectoryCsv::normalize($name);

        foreach ($this->referentials->zones() as $zone) {
            if (DirectoryCsv::normalize($zone['name']) === $key) {
                return ['id' => $zone['id'], 'name' => $zone['name'], 'communes' => array_map(static fn (array $c): array => ['id' => $c['id'], 'name' => $c['name']], $zone['communes'])];
            }
        }

        return null;
    }

    /**
     * @param  array{id: int, name: string, communes: list<array{id: int, name: string}>}  $zone
     * @return array{id: int, name: string}|null
     */
    private function commune(array $zone, string $name): ?array
    {
        $key = DirectoryCsv::normalize($name);

        foreach ($zone['communes'] as $commune) {
            if (DirectoryCsv::normalize($commune['name']) === $key) {
                return $commune;
            }
        }

        return null;
    }

    /**
     * Fiche déjà connue : même e-mail, sinon même nom et même rôle (fiches supprimées comprises : elles reviennent).
     *
     * @param  array<string, mixed>  $validated
     */
    private function existing(array $validated): ?DirectoryContact
    {
        $email = trim((string) ($validated['email'] ?? ''));

        if ($email !== '') {
            return DirectoryContact::withTrashed()->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->orderBy('id')->first();
        }

        return DirectoryContact::withTrashed()
            ->where('name', trim((string) $validated['name']))
            ->where('role', (string) $validated['role'])
            ->orderBy('id')
            ->first();
    }
}
