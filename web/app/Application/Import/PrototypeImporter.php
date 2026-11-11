<?php

declare(strict_types=1);

namespace App\Application\Import;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Orchestration de l'import du prototype (IMPLEMENTATION_PLAN.md P1-13, DATABASE_ENGINEERING.md §7) :
 * les collections sont importées dans l'ordre des dépendances (users → annuaire → cases → suivi →
 * alertes → contenus → réseau → communication → plateforme), dans une transaction annulée en
 * simulation (--dry-run). Les collections de démonstration et les données personnelles ne sont
 * importées qu'hors production ou avec --with-demo ; les référentiels et templates toujours.
 */
final class PrototypeImporter
{
    public const LOG_FILE = 'logs/import-prototype.log';

    /**
     * Importeurs dans l'ordre d'exécution (clés étrangères : collected_by, by_user_id, routed_to, updated_by…).
     *
     * @var list<class-string<AbstractImporter>>
     */
    public const IMPORTERS = [
        ImportUsers::class,
        ImportDirectory::class,
        ImportCases::class,
        ImportFollowups::class,
        ImportAlerts::class,
        ImportFaqs::class,
        ImportAssistantKb::class,
        ImportBadWords::class,
        ImportPosts::class,
        ImportForumTopics::class,
        ImportForumReplies::class,
        ImportGroups::class,
        ImportPages::class,
        ImportEvents::class,
        ImportInvitations::class,
        ImportEmailTemplates::class,
        ImportSocialChannels::class,
        ImportGatewayTemplates::class,
        ImportNewsletterSubscribers::class,
        ImportNewsletterCampaigns::class,
        ImportIndicators::class,
        ImportGeneratedReports::class,
        ImportAuditLogs::class,
        ImportModerationReports::class,
        ImportSettings::class,
        ImportAppVersions::class,
        ImportSyncDevices::class,
        ImportSyncConflicts::class,
    ];

    private ?LoggerInterface $logger = null;

    /**
     * Journal de remplacement (tests) ; par défaut le fichier storage/logs/import-prototype.log.
     * Volontairement hors constructeur : le conteneur injecterait sinon le journal applicatif (laravel.log).
     */
    public function withLogger(LoggerInterface $logger): static
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * Noms des collections importables, dans l'ordre d'exécution.
     *
     * @return list<string>
     */
    public static function collections(): array
    {
        return array_map(static fn (string $class): string => $class::collection(), self::IMPORTERS);
    }

    /**
     * @param  list<string>  $only  collections à importer (vide = toutes)
     * @param  (Closure(string, array{created: int, updated: int, rejected: int, skipped: string|null}): void)|null  $progress  appelé après chaque collection
     *
     * @throws InvalidArgumentException dossier inexistant ou collection inconnue
     */
    public function import(string $directory, array $only = [], bool $dryRun = false, bool $withDemo = false, ?Closure $progress = null): ImportReport
    {
        $unknown = array_diff($only, self::collections());

        if ($unknown !== []) {
            throw new InvalidArgumentException(sprintf(
                'Collection(s) inconnue(s) : %s. Collections possibles : %s.',
                implode(', ', $unknown),
                implode(', ', self::collections()),
            ));
        }

        $context = new ImportContext(
            data: new PrototypeData($directory),
            dryRun: $dryRun,
            withDemo: $withDemo,
            production: app()->isProduction(),
            logger: $this->logger ?? self::fileLogger(),
        );

        $context->note(sprintf(
            'début de l’import de %s (%s%s)',
            $context->data->directory,
            $dryRun ? 'simulation' : 'écriture',
            $withDemo ? ', avec la démonstration' : '',
        ));

        DB::beginTransaction();

        try {
            foreach (self::IMPORTERS as $class) {
                $collection = $class::collection();

                if ($only !== [] && ! in_array($collection, $only, true)) {
                    continue;
                }

                $this->runCollection($class, $context);

                if ($progress !== null) {
                    $progress($collection, $context->report->of($collection));
                }
            }

            $dryRun ? DB::rollBack() : DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        $totals = $context->report->totals();
        $context->note(sprintf(
            'fin de l’import : %d créé(s), %d mis à jour, %d rejeté(s), %d collection(s) ignorée(s)',
            $totals['created'],
            $totals['updated'],
            $totals['rejected'],
            $totals['skipped'],
        ));

        return $context->report;
    }

    /**
     * @param  class-string<AbstractImporter>  $class
     */
    private function runCollection(string $class, ImportContext $context): void
    {
        $collection = $class::collection();

        if (($class::isDemo() || $class::holdsPersonalData()) && ! $context->demoAllowed()) {
            $reason = $class::holdsPersonalData()
                ? 'données personnelles : import en production seulement avec --with-demo'
                : 'contenu de démonstration : import en production seulement avec --with-demo';

            $context->report->skip($collection, $reason);
            $context->note(sprintf('collection %s ignorée (%s)', $collection, $reason));

            return;
        }

        if (! $context->data->has($collection)) {
            $context->report->skip($collection, 'fichier absent du dossier');
            $context->note(sprintf('collection %s ignorée (fichier absent)', $collection));

            return;
        }

        (new $class($context))->run();
    }

    /** Canal de journalisation dédié (storage/logs/import-prototype.log), sans toucher à config/logging.php. */
    public static function fileLogger(): LoggerInterface
    {
        return Log::build([
            'driver' => 'single',
            'path' => storage_path(self::LOG_FILE),
            'level' => 'debug',
            'replace_placeholders' => true,
        ]);
    }
}
