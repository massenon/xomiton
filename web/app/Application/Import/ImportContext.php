<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Enums\DirectoryRole;
use App\Models\AuditLog;
use App\Models\CaseRecord;
use App\Models\Commune;
use App\Models\DirectoryContact;
use App\Models\ForumTopic;
use App\Models\NewsletterSubscriber;
use App\Models\SyncDevice;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * État partagé d'une exécution de l'import : options, rapport, journal des rejets et tables de
 * correspondance (identifiants du prototype → identifiants en base). Les correspondances se
 * reconstruisent à la demande depuis la base et les fichiers JSON, ce qui permet d'importer une
 * collection seule (`--only=suivi`) après coup.
 */
final class ImportContext
{
    public const AUDIT_REJECTED = 'import.rejected';

    public const AUDIT_ACCOUNT_CREATED = 'import.account_created';

    public const AUDIT_CASE_RENUMBERED = 'import.case_renumbered';

    public const AUDIT_REFERENCE_CREATED = 'import.reference_created';

    public readonly ImportReport $report;

    /** @var array<string, array<string|int, int>> */
    private array $maps = [];

    public function __construct(
        public readonly PrototypeData $data,
        public readonly bool $dryRun,
        public readonly bool $withDemo,
        public readonly bool $production,
        private readonly LoggerInterface $logger,
    ) {
        $this->report = new ImportReport;
    }

    /**
     * Contenus de démonstration et données personnelles de l'annuaire : importés hors production,
     * ou en production seulement sur demande explicite (`--with-demo`).
     */
    public function demoAllowed(): bool
    {
        return $this->withDemo || ! $this->production;
    }

    /* ----------------------------------------------------------------- journal */

    /**
     * Rejet d'une ligne : compteur du rapport, entrée d'audit `import.rejected` et ligne du fichier
     * storage/logs/import-prototype.log, toujours avec la raison.
     */
    public function reject(string $collection, string|int|null $prototypeId, string $reason): void
    {
        $this->report->rejected($collection);

        $detail = sprintf('%s #%s : %s', $collection, $prototypeId ?? '?', $reason);

        $this->logger->warning(($this->dryRun ? '[simulation] ' : '').'rejet '.$detail);
        $this->audit(self::AUDIT_REJECTED, $detail);
    }

    /** Information de suivi (compte créé, code renumérotée, référentiel complété…) dans le fichier journal. */
    public function note(string $message): void
    {
        $this->logger->info(($this->dryRun ? '[simulation] ' : '').$message);
    }

    /** Entrée d'audit système (acteur `système`, sans requête HTTP). */
    public function audit(string $action, string $detail, ?Model $subject = null): void
    {
        $log = new AuditLog([
            'actor' => AuditLog::ACTOR_SYSTEM,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'detail' => Str::limit($detail, 2000, '…'),
        ]);

        $log->save();
    }

    /* ------------------------------------------------------------ conversions */

    /**
     * Horodatage du prototype (unix `created`, `ts`, ou chaîne `Y-m-d H:i[:s]`) dans le fuseau de l'application.
     */
    public function dateTime(mixed $value): ?Carbon
    {
        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            return null;
        }

        try {
            if (is_int($value) || is_float($value) || (is_string($value) && ctype_digit($value))) {
                return Carbon::createFromTimestamp((int) $value, $this->timezone());
            }

            return is_string($value) ? Carbon::parse($value, $this->timezone()) : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** Date du prototype (`Y-m-d`), sans heure. */
    public function date(mixed $value): ?Carbon
    {
        return $this->dateTime($value)?->startOfDay();
    }

    public function timezone(): string
    {
        return (string) config('app.timezone', 'UTC');
    }

    /* --------------------------------------------------------- correspondances */

    public function remember(string $map, string|int $key, int $id): void
    {
        $this->maps[$map][$key] = $id;
    }

    public function lookup(string $map, string|int $key): ?int
    {
        return $this->maps[$map][$key] ?? null;
    }

    /**
     * Compte issu de `users.json` (identifiant du prototype) ; 0 = anonyme. Reconstruit depuis l'e-mail
     * du fichier quand la collection `users` n'a pas été importée dans cette exécution.
     */
    public function userId(int|string|null $prototypeId): ?int
    {
        $prototypeId = (int) $prototypeId;

        if ($prototypeId <= 0) {
            return null;
        }

        if (($id = $this->lookup('users', $prototypeId)) !== null) {
            return $id;
        }

        foreach ($this->data->rows('users') as $row) {
            if ((int) ($row['id'] ?? 0) === $prototypeId) {
                $id = $this->userIdByEmail((string) ($row['email'] ?? ''));

                if ($id !== null) {
                    $this->remember('users', $prototypeId, $id);
                }

                return $id;
            }
        }

        return null;
    }

    public function userIdByEmail(string $email): ?int
    {
        $email = mb_strtolower(trim($email));

        if ($email === '') {
            return null;
        }

        if (($id = $this->lookup('users_by_email', $email)) !== null) {
            return $id;
        }

        $id = User::withTrashed()->where('email', $email)->value('id');

        if ($id !== null) {
            $this->remember('users_by_email', $email, (int) $id);
        }

        return $id === null ? null : (int) $id;
    }

    /** Compte retrouvé par son nom complet (pair·es des registres, auteur·rices des notes, organisateur·rices). */
    public function userIdByName(?string $name): ?int
    {
        $key = ImportKeys::normalizeName((string) $name);

        if ($key === '') {
            return null;
        }

        if (! isset($this->maps['users_by_name'])) {
            $this->maps['users_by_name'] = [];

            foreach (User::withTrashed()->whereNotNull('name')->pluck('name', 'id') as $id => $fullName) {
                $this->maps['users_by_name'][ImportKeys::normalizeName((string) $fullName)] = (int) $id;
            }
        }

        return $this->maps['users_by_name'][$key] ?? null;
    }

    /** Compte retrouvé par son pseudonyme. */
    public function userIdByPseudo(?string $pseudo): ?int
    {
        $pseudo = trim((string) $pseudo);

        if ($pseudo === '') {
            return null;
        }

        $id = User::withTrashed()->where('pseudo', $pseudo)->value('id');

        return $id === null ? null : (int) $id;
    }

    /** Enregistre un compte créé ou mis à jour dans les correspondances par e-mail et par nom. */
    public function rememberUser(User $user, int|string|null $prototypeId = null): void
    {
        if ($prototypeId !== null && (int) $prototypeId > 0) {
            $this->remember('users', (int) $prototypeId, (int) $user->getKey());
        }

        $this->remember('users_by_email', mb_strtolower((string) $user->email), (int) $user->getKey());

        if ($user->name !== null && isset($this->maps['users_by_name'])) {
            $this->maps['users_by_name'][ImportKeys::normalizeName($user->name)] = (int) $user->getKey();
        }
    }

    /**
     * Contact de l'annuaire issu de `annuaire.json` (identifiant du prototype). Reconstruit depuis
     * l'e-mail, ou le nom et le rôle, quand la collection n'a pas été importée dans cette exécution.
     */
    public function contactId(int|string|null $prototypeId): ?int
    {
        $prototypeId = (int) $prototypeId;

        if ($prototypeId <= 0) {
            return null;
        }

        if (($id = $this->lookup('contacts', $prototypeId)) !== null) {
            return $id;
        }

        foreach ($this->data->rows('annuaire') as $row) {
            if ((int) ($row['id'] ?? 0) !== $prototypeId) {
                continue;
            }

            $contact = $this->findContact(
                (string) ($row['email'] ?? ''),
                (string) ($row['name'] ?? ''),
                DirectoryRole::tryFromLabel((string) ($row['role'] ?? '')),
            );

            if ($contact !== null) {
                $this->remember('contacts', $prototypeId, (int) $contact->getKey());

                return (int) $contact->getKey();
            }

            return null;
        }

        return null;
    }

    /** Contact retrouvé par e-mail, sinon par nom (et rôle si connu) ; les fiches supprimées sont incluses. */
    public function findContact(string $email, string $name, ?DirectoryRole $role = null): ?DirectoryContact
    {
        $email = mb_strtolower(trim($email));
        $name = trim($name);

        if ($email !== '') {
            $contact = DirectoryContact::withTrashed()->where('email', $email)->first();

            if ($contact !== null) {
                return $contact;
            }
        }

        if ($name === '') {
            return null;
        }

        $query = DirectoryContact::withTrashed()->where('name', $name);

        if ($role !== null) {
            $query->where('role', $role->value);
        }

        return $query->first();
    }

    /** Contact de l'annuaire dont le nom correspond (insensible à la casse et aux espaces). */
    public function contactByName(string $name): ?DirectoryContact
    {
        $key = ImportKeys::normalizeName($name);

        if ($key === '') {
            return null;
        }

        foreach (DirectoryContact::withTrashed()->get(['id', 'name', 'email', 'user_id']) as $contact) {
            if (ImportKeys::normalizeName((string) $contact->name) === $key) {
                return $contact;
            }
        }

        return null;
    }

    /** Dossier retrouvé par son code du prototype (`VBG-00xx`, même pour les VDH renumérotés). */
    public function caseIdByPrototypeCode(?string $code): ?int
    {
        $code = mb_strtoupper(trim((string) $code));

        if ($code === '') {
            return null;
        }

        if (($id = $this->lookup('cases', $code)) !== null) {
            return $id;
        }

        $id = CaseRecord::query()->where('client_uuid', ImportKeys::caseUuid($code))->value('id');

        if ($id !== null) {
            $this->remember('cases', $code, (int) $id);
        }

        return $id === null ? null : (int) $id;
    }

    /** Sujet de forum issu de `forum_topics.json` ; reconstruit par forum et titre si besoin. */
    public function topicId(int|string|null $prototypeId): ?int
    {
        $prototypeId = (int) $prototypeId;

        if ($prototypeId <= 0) {
            return null;
        }

        if (($id = $this->lookup('topics', $prototypeId)) !== null) {
            return $id;
        }

        foreach ($this->data->rows('forum_topics') as $row) {
            if ((int) ($row['id'] ?? 0) !== $prototypeId) {
                continue;
            }

            $id = ForumTopic::withTrashed()
                ->where('title', trim((string) ($row['title'] ?? '')))
                ->whereHas('forum', fn ($forums) => $forums->where('key', (string) ($row['forum'] ?? '')))
                ->value('id');

            if ($id !== null) {
                $this->remember('topics', $prototypeId, (int) $id);
            }

            return $id === null ? null : (int) $id;
        }

        return null;
    }

    /** Abonné·e issu·e de `newsletter_subscribers.json` ; reconstruit par e-mail si besoin. */
    public function subscriberId(int|string|null $prototypeId): ?int
    {
        $prototypeId = (int) $prototypeId;

        if ($prototypeId <= 0) {
            return null;
        }

        if (($id = $this->lookup('subscribers', $prototypeId)) !== null) {
            return $id;
        }

        foreach ($this->data->rows('newsletter_subscribers') as $row) {
            if ((int) ($row['id'] ?? 0) !== $prototypeId) {
                continue;
            }

            $id = NewsletterSubscriber::query()->whereEmail((string) ($row['email'] ?? ''))->value('id');

            if ($id !== null) {
                $this->remember('subscribers', $prototypeId, (int) $id);
            }

            return $id === null ? null : (int) $id;
        }

        return null;
    }

    /** Appareil de synchronisation retrouvé par son libellé du prototype (`Chantal H. — Porto-Novo`). */
    public function deviceIdByLabel(?string $label): ?int
    {
        $label = trim((string) $label);

        if ($label === '') {
            return null;
        }

        if (($id = $this->lookup('devices', $label)) !== null) {
            return $id;
        }

        $id = SyncDevice::query()->where('label', $label)->value('id');

        if ($id !== null) {
            $this->remember('devices', $label, (int) $id);
        }

        return $id === null ? null : (int) $id;
    }

    /* --------------------------------------------------------------- référentiels */

    /**
     * Département par libellé ; créé s'il manque (DATABASE_ENGINEERING.md §7 : « crée les zones/communes
     * manquantes »), journalisé. `National` et vide valent null.
     */
    public function zoneId(?string $label): ?int
    {
        $label = trim((string) $label);

        if ($label === '' || mb_strtolower($label) === 'national') {
            return null;
        }

        $slug = Str::slug($label);

        if ($slug === '') {
            return null;
        }

        if (($id = $this->lookup('zones', $slug)) !== null) {
            return $id;
        }

        $zone = Zone::query()->where('slug', $slug)->orWhere('name', $label)->first();

        if ($zone === null) {
            $zone = Zone::query()->create([
                'name' => $label,
                'slug' => $slug,
                'position' => ((int) Zone::query()->max('position')) + 1,
                'is_active' => true,
            ]);

            $this->note(sprintf('département « %s » créé (absent du référentiel)', $label));
            $this->audit(self::AUDIT_REFERENCE_CREATED, 'zones : '.$label, $zone);
        }

        $this->remember('zones', $slug, (int) $zone->getKey());

        return (int) $zone->getKey();
    }

    /** Commune par libellé dans un département ; créée si elle manque, ignorée sans département. */
    public function communeId(?string $label, ?int $zoneId): ?int
    {
        $label = trim((string) $label);

        if ($label === '' || $zoneId === null) {
            return null;
        }

        $slug = Str::slug($label);

        if ($slug === '') {
            return null;
        }

        $key = $zoneId.'/'.$slug;

        if (($id = $this->lookup('communes', $key)) !== null) {
            return $id;
        }

        $commune = Commune::query()->where('zone_id', $zoneId)
            ->where(fn ($query) => $query->where('slug', $slug)->orWhere('name', $label))
            ->first();

        if ($commune === null) {
            $commune = Commune::query()->create([
                'zone_id' => $zoneId,
                'name' => $label,
                'slug' => $slug,
                'position' => ((int) Commune::query()->where('zone_id', $zoneId)->max('position')) + 1,
                'is_active' => true,
            ]);

            $this->note(sprintf('commune « %s » créée (absente du référentiel)', $label));
            $this->audit(self::AUDIT_REFERENCE_CREATED, 'communes : '.$label, $commune);
        }

        $this->remember('communes', $key, (int) $commune->getKey());

        return (int) $commune->getKey();
    }
}
