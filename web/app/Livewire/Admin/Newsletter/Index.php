<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Newsletter;

use App\Application\Admin\Newsletter\ManageCampaigns;
use App\Application\Admin\Newsletter\ManageSubscribers;
use App\Application\Admin\Newsletter\NewsletterStatistics;
use App\Application\Mail\SmtpSettings;
use App\Application\Newsletter\Campaigns\CampaignRenderer;
use App\Domain\Newsletter\Exceptions\CommunicationRefused;
use App\Enums\CampaignStatus;
use App\Enums\SubscriberStatus;
use App\Livewire\Attributes\SafeUrl;
use App\Livewire\Concerns\InteractsWithUi;
use App\Livewire\Concerns\ValidatesForm;
use App\Livewire\Concerns\WithDataTable;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterInterest;
use App\Models\NewsletterSubscriber;
use App\Models\NewsletterTag;
use App\Models\User;
use App\Support\LocaleFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Newsletter `/admin/newsletter` (P14-02, prototype admin/newsletter.php) : KPI permanents (abonné·es actif·ves, taux d'ouverture,
 * de clic, de désabonnement, de rebond avec leur cible et une alerte sous la cible), puis trois onglets dans l'URL (`?onglet=`) :
 * Statistiques (12 mois, par statut, par campagne, liens cliqués, centres d'intérêt), Abonné·es (liste filtrable, actions
 * groupées, import CSV avec attestation de consentement, export, suppression RGPD) et Campagnes (liste, modale de composition avec
 * gabarit par rubriques, segment et comptage en direct, aperçu, test, mode d'envoi ; statistiques, modifier ou voir, annuler la
 * programmation, dupliquer, supprimer). Permissions : `newsletter.manage_subscribers`, `newsletter.manage_campaigns`,
 * `newsletter.send` (programmer ou lancer), rejouées par action. Aucun SMTP dans la requête : l'envoi réel est fait par
 * `newsletter:process` (P14-03) ; les tests partent par la boîte d'envoi. L'audit ne contient ni objet, ni corps, ni adresse.
 */
final class Index extends Component
{
    use InteractsWithUi;
    use ValidatesForm;
    use WithDataTable {
        restoreCsvState as private restoreTableState;
    }
    use WithFileUploads;

    public const TAB_STATS = 'stats';

    public const TAB_SUBSCRIBERS = 'abonnes';

    public const TAB_CAMPAIGNS = 'campagnes';

    #[SafeUrl(as: 'onglet', except: 'stats')]
    public string $tab = self::TAB_STATS;

    // Filtres des abonné·es.
    #[SafeUrl(as: 'st', except: '')]
    public string $status = '';

    #[SafeUrl(as: 'tag', except: '')]
    public string $tagFilter = '';

    #[SafeUrl(as: 'int', except: '')]
    public string $interestFilter = '';

    #[SafeUrl(as: 'du', except: '')]
    public string $from = '';

    #[SafeUrl(as: 'au', except: '')]
    public string $until = '';

    // Filtre des campagnes.
    #[SafeUrl(as: 'cst', except: '')]
    public string $campaignStatus = '';

    // Modale abonné·e.
    #[Locked]
    public ?int $subscriberId = null;

    /** @var array{email: string, name: string, status: string, tags: list<string>, interests: list<string>, send_confirmation: bool} */
    public array $subForm = [];

    // Import CSV.
    public ?TemporaryUploadedFile $csv = null;

    public bool $importAttested = false;

    public bool $importOptin = true;

    public string $importTag = '';

    /** @var array{imported: int, duplicates: int, invalid: int}|null */
    public ?array $importReport = null;

    // Modale campagne.
    #[Locked]
    public ?int $campaignId = null;

    /** @var array<string, mixed> */
    public array $campForm = [];

    public bool $testimonialConsent = false;

    public string $campPane = 'edit';

    public string $previewMode = 'desktop';

    public string $previewSubject = '';

    public string $previewHtml = '';

    public string $testTo = '';

    #[Locked]
    public ?int $statsCampaignId = null;

    public function mount(): void
    {
        abort_unless($this->canSubscribers() || $this->canCampaigns(), 403);

        if (! array_key_exists($this->tab, $this->tabs())) {
            $this->tab = (string) array_key_first($this->tabs());
        }

        $this->resetSubForm();
        $this->resetCampForm();
    }

    // --- Onglets et tableau ------------------------------------------------------------------------------------------

    /** @return array<string, array{label: string, icon: string, count?: int}> */
    public function tabs(): array
    {
        $tabs = [self::TAB_STATS => ['label' => (string) __('newsletter.admin.tabs.stats'), 'icon' => 'graph-up']];

        if ($this->canSubscribers()) {
            $tabs[self::TAB_SUBSCRIBERS] = ['label' => (string) __('newsletter.admin.tabs.subscribers'), 'icon' => 'people', 'count' => NewsletterSubscriber::query()->count()];
        }

        if ($this->canCampaigns()) {
            $tabs[self::TAB_CAMPAIGNS] = ['label' => (string) __('newsletter.admin.tabs.campaigns'), 'icon' => 'envelope-paper', 'count' => NewsletterCampaign::query()->count()];
        }

        return $tabs;
    }

    public function setTab(string $tab): void
    {
        if (! array_key_exists($tab, $this->tabs())) {
            return;
        }

        $this->tab = $tab;
        $this->reset('sort', 'direction', 'search', 'status', 'tagFilter', 'interestFilter', 'from', 'until', 'campaignStatus');
        $this->clearSelection();
        $this->resetPage();
    }

    protected function sortableColumns(): array
    {
        return $this->tab === self::TAB_CAMPAIGNS
            ? ['date' => 'created_at', 'objet' => 'subject', 'statut' => 'status']
            : ['inscription' => 'created_at', 'email' => 'email', 'statut' => 'status'];
    }

    protected function searchableColumns(): array
    {
        return $this->tab === self::TAB_CAMPAIGNS ? ['subject'] : ['email', 'name'];
    }

    protected function defaultSort(): array
    {
        return [$this->tab === self::TAB_CAMPAIGNS ? 'date' : 'inscription', 'desc'];
    }

    protected function filterProperties(): array
    {
        return $this->tab === self::TAB_CAMPAIGNS ? ['campaignStatus'] : ['status', 'tagFilter', 'interestFilter', 'from', 'until'];
    }

    // --- Export CSV des abonné·es --------------------------------------------------------------------------------------

    public function export(): void
    {
        $this->authorize('export', NewsletterSubscriber::class);
        $this->tab = self::TAB_SUBSCRIBERS;
        $this->requestCsvExport();
    }

    protected function csvScreen(): string
    {
        return 'newsletter-abonnes';
    }

    protected function authorizeCsvExport(bool $full): void
    {
        $this->authorize('export', NewsletterSubscriber::class);
    }

    protected function restoreCsvState(array $state): void
    {
        $this->tab = self::TAB_SUBSCRIBERS;
        $this->restoreTableState($state);
    }

    protected function csvQuery(): Builder
    {
        return $this->filteredSubscribers();
    }

    protected function csvColumns(bool $full): array
    {
        return [
            'email' => (string) __('newsletter.admin.subscribers.fields.email'),
            'name' => (string) __('newsletter.admin.subscribers.fields.name'),
            'status' => (string) __('newsletter.admin.subscribers.columns.status'),
            'tags' => (string) __('newsletter.admin.subscribers.columns.tags'),
            'interests' => (string) __('newsletter.admin.subscribers.columns.interests'),
            'created' => (string) __('newsletter.admin.subscribers.columns.subscribed_at'),
            'confirmed' => (string) __('admin_newsletter.subscribers.confirmed_at'),
            'source' => (string) __('newsletter.admin.subscribers.columns.source'),
        ];
    }

    protected function csvRow(\Illuminate\Database\Eloquent\Model $model, bool $full): array
    {
        /** @var NewsletterSubscriber $model */
        return [
            'email' => $model->email,
            'name' => $model->name,
            'status' => $model->status->label(),
            'tags' => implode(', ', (array) $model->tags),
            'interests' => implode(', ', (array) $model->interests),
            'created' => $model->created_at?->format('Y-m-d'),
            'confirmed' => $model->confirmed_at?->format('Y-m-d'),
            'source' => $model->source->label(),
        ];
    }

    public function downloadTemplate(): StreamedResponse
    {
        $this->authorize('import', NewsletterSubscriber::class);

        return response()->streamDownload(static function (): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\u{FEFF}");
            fputcsv($out, (array) __('admin_newsletter.subscribers.csv_headers'), ';', '"', '');
            fputcsv($out, (array) __('admin_newsletter.subscribers.csv_example'), ';', '"', '');
            fclose($out);
        }, 'modele-import-newsletter.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // --- Abonné·es ----------------------------------------------------------------------------------------------------

    public function resetSubForm(): void
    {
        $this->reset('subscriberId');
        $this->subForm = ['email' => '', 'name' => '', 'status' => SubscriberStatus::Pending->value, 'tags' => [], 'interests' => [], 'send_confirmation' => true];
        $this->resetErrorBag();
    }

    public function newSubscriber(): void
    {
        $this->authorize('create', NewsletterSubscriber::class);
        $this->resetSubForm();
        $this->openModal('xmNlSubscriber');
    }

    public function editSubscriber(int $id): void
    {
        $subscriber = NewsletterSubscriber::query()->findOrFail($id);
        $this->authorize('update', $subscriber);
        $this->resetSubForm();
        $this->subscriberId = (int) $subscriber->getKey();
        $this->subForm = [
            'email' => (string) $subscriber->email,
            'name' => (string) $subscriber->name,
            'status' => $subscriber->status->value,
            'tags' => array_values((array) $subscriber->tags),
            'interests' => array_values((array) $subscriber->interests),
            'send_confirmation' => false,
        ];
        $this->openModal('xmNlSubscriber');
    }

    public function saveSubscriber(ManageSubscribers $manage): void
    {
        $subscriber = $this->subscriberId === null ? null : NewsletterSubscriber::query()->findOrFail($this->subscriberId);
        $this->authorize($subscriber === null ? 'create' : 'update', $subscriber ?? NewsletterSubscriber::class);

        $data = $this->validateForm('subForm', $this->subForm, [
            'email' => [$subscriber === null ? 'required' : 'nullable', 'string', 'email:filter', 'max:190'],
            'name' => ['nullable', 'string', 'max:80'],
            'status' => ['required', Rule::enum(SubscriberStatus::class)],
            'tags' => ['array'],
            'tags.*' => ['string', 'max:60'],
            'interests' => ['array'],
            'interests.*' => ['string', 'max:60'],
            'send_confirmation' => ['boolean'],
        ], (array) __('newsletter.admin.subscribers.fields'));

        try {
            $manage->save($subscriber, [
                'email' => (string) ($subscriber?->email ?? $data['email']),
                'name' => (string) ($data['name'] ?? ''),
                'status' => (string) $data['status'],
                'tags' => array_values((array) ($data['tags'] ?? [])),
                'interests' => array_values((array) ($data['interests'] ?? [])),
                'send_confirmation' => (bool) ($data['send_confirmation'] ?? false),
            ], $this->actor());
        } catch (CommunicationRefused $refused) {
            $this->addError('subForm.email', $refused->userMessage());

            return;
        }

        $this->closeModal('xmNlSubscriber');
        $this->toast((string) __($subscriber === null ? 'newsletter.admin.toasts.subscriber_added' : 'newsletter.admin.toasts.subscriber_updated'));
        $this->resetSubForm();
        $this->afterSubscriberChange();
    }

    public function unsubscribeSubscriber(int $id, ManageSubscribers $manage): void
    {
        $subscriber = NewsletterSubscriber::query()->findOrFail($id);
        $this->authorize('update', $subscriber);
        $manage->unsubscribe($subscriber, $this->actor());
        $this->toast((string) __('newsletter.admin.toasts.subscriber_unsubscribed'), 'warning');
        $this->afterSubscriberChange();
    }

    public function resendConfirmation(int $id, ManageSubscribers $manage): void
    {
        $subscriber = NewsletterSubscriber::query()->findOrFail($id);
        $this->authorize('update', $subscriber);

        try {
            $manage->resendConfirmation($subscriber, $this->actor());
        } catch (CommunicationRefused $refused) {
            $this->toast($refused->userMessage(), 'error');

            return;
        }

        $this->toast((string) __('newsletter.admin.toasts.confirmation_resent'));
    }

    public function deleteSubscriber(int $id, ManageSubscribers $manage): void
    {
        $subscriber = NewsletterSubscriber::query()->findOrFail($id);
        $this->authorize('delete', $subscriber);
        $manage->delete($subscriber, $this->actor());
        $this->toast((string) __('newsletter.admin.toasts.subscriber_deleted'), 'warning');
        $this->afterSubscriberChange();
    }

    /** @return array<string, string> actions groupées proposées */
    public function bulkActions(): array
    {
        $actions = ['unsubscribe' => (string) __('newsletter.admin.subscribers.unsubscribe_action'), 'delete' => (string) __('newsletter.admin.subscribers.delete_rgpd')];

        foreach ($this->tags as $slug => $label) {
            $actions['tag:'.$slug] = (string) __('admin_newsletter.subscribers.bulk_tag', ['tag' => $label]);
        }

        return $actions;
    }

    public function applyBulk(ManageSubscribers $manage): void
    {
        $this->authorize('viewAny', NewsletterSubscriber::class);

        if (! array_key_exists($this->bulkAction, $this->bulkActions()) || $this->selected === []) {
            return;
        }

        $this->authorize($this->bulkAction === 'delete' ? 'delete' : 'update', new NewsletterSubscriber);

        $count = $manage->bulk($this->bulkAction, $this->selected, $this->actor());
        $this->clearSelection();
        $this->toast((string) __('newsletter.admin.toasts.bulk_applied', ['count' => $count]));
        $this->afterSubscriberChange();
    }

    public function openImport(): void
    {
        $this->authorize('import', NewsletterSubscriber::class);
        $this->reset('csv', 'importAttested', 'importTag', 'importReport');
        $this->importOptin = true;
        $this->resetErrorBag();
        $this->openModal('xmNlImport');
    }

    public function import(ManageSubscribers $manage): void
    {
        $this->authorize('import', NewsletterSubscriber::class);

        try {
            $this->validate(['csv' => ['required', 'file', 'mimes:csv,txt', 'max:1024']], [], ['csv' => (string) __('admin_newsletter.subscribers.import_file')]);
        } catch (ValidationException $e) {
            $this->csv = null;

            throw $e;
        }

        try {
            $this->importReport = $manage->import((string) $this->csv?->get(), $this->importAttested, $this->importOptin, $this->importTag === '' ? null : $this->importTag, $this->actor());
        } catch (CommunicationRefused $refused) {
            $this->addError('importAttested', $refused->userMessage());

            return;
        } finally {
            $this->csv = null;
        }

        $this->toast((string) __('newsletter.admin.toasts.imported', ['imported' => $this->importReport['imported'], 'skipped' => $this->importReport['duplicates'] + $this->importReport['invalid']]), $this->importReport['imported'] > 0 ? 'success' : 'warning');
        $this->afterSubscriberChange();
    }

    // --- Campagnes ----------------------------------------------------------------------------------------------------

    public function resetCampForm(): void
    {
        $this->reset('campaignId', 'testimonialConsent', 'campPane', 'previewMode', 'previewSubject', 'previewHtml', 'testTo');
        $this->campForm = [
            'subject' => '',
            'from_name' => SmtpSettings::fromName(),
            'reply_to' => SmtpSettings::replyTo() ?? SmtpSettings::fromAddress(),
            'preheader' => '',
            'body' => '',
            'seg_tags' => [],
            'seg_interests' => [],
            'seg_since' => '',
            'seg_until' => '',
            'mode' => 'draft',
            'scheduled_at' => CarbonImmutable::now($this->timezone())->addDay()->setTime(9, 0)->format('Y-m-d\TH:i'),
        ];
        $this->resetErrorBag();
    }

    public function newCampaign(): void
    {
        $this->authorize('create', NewsletterCampaign::class);
        $this->resetCampForm();
        $this->openModal('xmNlCampaign');
    }

    public function editCampaign(int $id): void
    {
        $campaign = NewsletterCampaign::query()->findOrFail($id);
        $this->authorize('view', $campaign);
        $this->resetCampForm();
        $this->campaignId = (int) $campaign->getKey();
        $segment = (array) $campaign->segment;
        $this->campForm = [
            'subject' => (string) $campaign->subject,
            'from_name' => (string) $campaign->from_name,
            'reply_to' => (string) $campaign->reply_to,
            'preheader' => (string) $campaign->preheader,
            'body' => (string) $campaign->body,
            'seg_tags' => array_values((array) ($segment['tags'] ?? [])),
            'seg_interests' => array_values((array) ($segment['interests'] ?? [])),
            'seg_since' => (string) ($segment['since'] ?? ''),
            'seg_until' => (string) ($segment['until'] ?? ''),
            'mode' => 'draft',
            'scheduled_at' => $campaign->scheduled_at?->setTimezone($this->timezone())->format('Y-m-d\TH:i') ?? $this->campForm['scheduled_at'],
        ];
        $this->openModal('xmNlCampaign');
    }

    /** Ajoute une rubrique du gabarit au corps ; le témoignage n'est inséré qu'avec le consentement attesté. */
    public function insertSection(string $key): void
    {
        if (! in_array($key, ['featured', 'resource', 'event', 'testimonial', 'emergency'], true)) {
            return;
        }

        if ($key === 'testimonial' && ! $this->testimonialConsent) {
            $this->addError('campForm.body', (string) __('admin_newsletter.errors.testimonial_consent'));

            return;
        }

        $this->resetErrorBag('campForm.body');
        $this->campForm['body'] = trim((string) $this->campForm['body']."\n".(string) __('admin_newsletter.campaigns.sections.'.$key.'.html'));
    }

    public function saveCampaign(ManageCampaigns $manage): void
    {
        $campaign = $this->campaignId === null ? null : NewsletterCampaign::query()->findOrFail($this->campaignId);
        $this->authorize($campaign === null ? 'create' : 'update', $campaign ?? NewsletterCampaign::class);
        $mode = in_array($this->campForm['mode'], ['draft', 'schedule', 'now'], true) ? (string) $this->campForm['mode'] : 'draft';

        if ($mode !== 'draft') {
            $this->authorize('send', $campaign ?? new NewsletterCampaign);
        }

        $data = $this->validateCampaignForm($mode);

        try {
            $saved = $manage->save($campaign, $data, $mode, $this->actor());
        } catch (CommunicationRefused $refused) {
            $this->addError(in_array($refused->errorCode(), ['scheduled_past'], true) ? 'campForm.scheduled_at' : 'campForm.subject', $refused->userMessage());

            return;
        }

        $this->closeModal('xmNlCampaign');
        $this->resetCampForm();
        unset($this->kpis);
        $format = LocaleFormatter::make(null, $this->timezone());
        $this->toast(match ($mode) {
            'schedule' => (string) __('newsletter.admin.toasts.campaign_scheduled', ['date' => $format->shortDate($saved->scheduled_at), 'time' => $format->time($saved->scheduled_at), 'count' => $saved->targetQuery()->count()]),
            'now' => (string) __('newsletter.admin.toasts.campaign_sending', ['count' => $saved->targetQuery()->count(), 'batch' => NewsletterCampaign::BATCH_SIZE]),
            default => (string) __('newsletter.admin.toasts.campaign_saved'),
        });
    }

    public function previewCampaign(ManageCampaigns $manage): void
    {
        $data = $this->validateCampaignForm('draft');
        $rendered = $manage->preview($data);
        $this->previewSubject = $rendered['subject'];
        $this->previewHtml = $rendered['html'];
        $this->campPane = 'preview';
    }

    public function setCampPane(string $pane, ManageCampaigns $manage): void
    {
        if ($pane === 'preview') {
            $this->previewCampaign($manage);

            return;
        }

        $this->campPane = 'edit';
    }

    public function setPreviewMode(string $mode): void
    {
        $this->previewMode = $mode === 'mobile' ? 'mobile' : 'desktop';
    }

    public function sendCampaignTest(ManageCampaigns $manage): void
    {
        $campaign = $this->campaignId === null ? new NewsletterCampaign : NewsletterCampaign::query()->findOrFail($this->campaignId);
        $this->authorize('sendTest', $campaign);
        $this->validate(['testTo' => ['required', 'email:filter', 'max:190']], [], ['testTo' => (string) __('newsletter.admin.campaigns.fields.test_address')]);

        $user = $this->actor();

        if (! RateLimiter::attempt('newsletter-test:'.$user->getKey(), 5, static fn (): bool => true, 60)) {
            $this->addError('testTo', (string) __('admin_newsletter.emails.test.throttled'));

            return;
        }

        try {
            $manage->sendTest($this->campForm + [], $this->testTo, $user);
        } catch (CommunicationRefused $refused) {
            $this->addError('testTo', $refused->userMessage());

            return;
        }

        $this->toast((string) __('admin_newsletter.campaigns.test_queued'));
    }

    public function cancelSchedule(int $id, ManageCampaigns $manage): void
    {
        $campaign = NewsletterCampaign::query()->findOrFail($id);
        $this->authorize('cancel', $campaign);

        try {
            $manage->cancelSchedule($campaign, $this->actor());
        } catch (CommunicationRefused $refused) {
            $this->toast($refused->userMessage(), 'error');

            return;
        }

        $this->toast((string) __('newsletter.admin.toasts.campaign_cancelled'), 'warning');
    }

    public function duplicateCampaign(int $id, ManageCampaigns $manage): void
    {
        $campaign = NewsletterCampaign::query()->findOrFail($id);
        $this->authorize('create', NewsletterCampaign::class);
        $manage->duplicate($campaign, $this->actor());
        $this->toast((string) __('newsletter.admin.toasts.campaign_duplicated'));
    }

    public function deleteCampaign(int $id, ManageCampaigns $manage): void
    {
        $campaign = NewsletterCampaign::query()->findOrFail($id);
        $this->authorize('delete', $campaign);

        try {
            $manage->delete($campaign, $this->actor());
        } catch (CommunicationRefused $refused) {
            $this->toast($refused->userMessage(), 'error');

            return;
        }

        $this->toast((string) __('newsletter.admin.toasts.campaign_deleted'), 'warning');
    }

    public function showStats(int $id): void
    {
        $campaign = NewsletterCampaign::query()->findOrFail($id);
        $this->authorize('view', $campaign);
        $this->statsCampaignId = (int) $campaign->getKey();
        $this->openModal('xmNlCampaignStats');
    }

    /** « Traiter la file d'envoi » : démarre les campagnes échues et dépose les lots (aucun SMTP dans la requête). */
    public function processQueue(ManageCampaigns $manage): void
    {
        $this->authorize('send', new NewsletterCampaign);
        $count = $manage->processQueue($this->actor());
        $this->toast(trans_choice('newsletter.admin.toasts.queue_processed', $count, ['count' => $count]), $count > 0 ? 'success' : 'info');
        unset($this->kpis);
    }

    // --- Données -------------------------------------------------------------------------------------------------------

    /** @return array<string, string> slug => libellé */
    #[Computed]
    public function tags(): array
    {
        return NewsletterTag::query()->ordered()->pluck('label', 'slug')->map(static fn (mixed $l): string => (string) $l)->all();
    }

    /** @return array<string, string> slug => libellé */
    #[Computed]
    public function interests(): array
    {
        return NewsletterInterest::query()->ordered()->pluck('label', 'slug')->map(static fn (mixed $l): string => (string) $l)->all();
    }

    /** @return array{status: array<string, int>, totals: array<string, int|float>} */
    #[Computed]
    public function kpis(): array
    {
        $stats = app(NewsletterStatistics::class);

        return ['status' => $stats->subscribersByStatus(), 'totals' => $stats->totals()];
    }

    /** Abonné·es actif·ves visé·es par le segment en cours de saisie (comptage en direct). */
    #[Computed]
    public function segmentCount(): int
    {
        return app(ManageCampaigns::class)->segmentCount([
            'tags' => $this->campForm['seg_tags'] ?? [],
            'interests' => $this->campForm['seg_interests'] ?? [],
            'since' => $this->campForm['seg_since'] ?? '',
            'until' => $this->campForm['seg_until'] ?? '',
        ]);
    }

    /** @return Builder<NewsletterSubscriber> */
    private function filteredSubscribers(): Builder
    {
        return NewsletterSubscriber::query()
            ->when(SubscriberStatus::tryFrom($this->status), fn (Builder $q, SubscriberStatus $s) => $q->withStatus($s))
            ->when($this->tagFilter !== '', fn (Builder $q) => $q->withAnyTag([$this->tagFilter]))
            ->when($this->interestFilter !== '', fn (Builder $q) => $q->withAnyInterest([$this->interestFilter]))
            ->when($this->validDate($this->from), fn (Builder $q) => $q->whereDate('created_at', '>=', $this->from))
            ->when($this->validDate($this->until), fn (Builder $q) => $q->whereDate('created_at', '<=', $this->until));
    }

    /** @return Builder<NewsletterCampaign> */
    private function filteredCampaigns(): Builder
    {
        return NewsletterCampaign::query()->when(CampaignStatus::tryFrom($this->campaignStatus), fn (Builder $q, CampaignStatus $s) => $q->withStatus($s));
    }

    private function validDate(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && strtotime($value) !== false;
    }

    /**
     * @return array{subject: string, from_name: string, reply_to: string, preheader: ?string, body: string, segment: array<string, mixed>, scheduled_at: ?CarbonImmutable}
     */
    private function validateCampaignForm(string $mode): array
    {
        $data = $this->validateForm('campForm', $this->campForm, [
            'subject' => ['required', 'string', 'min:'.ManageCampaigns::SUBJECT_MIN, 'max:'.ManageCampaigns::SUBJECT_MAX],
            'from_name' => ['required', 'string', 'min:2', 'max:60'],
            'reply_to' => ['required', 'email:filter', 'max:190'],
            'preheader' => ['nullable', 'string', 'max:150'],
            'body' => ['required', 'string', 'min:'.ManageCampaigns::BODY_MIN, 'max:100000'],
            'seg_tags' => ['array'],
            'seg_tags.*' => ['string', 'max:60'],
            'seg_interests' => ['array'],
            'seg_interests.*' => ['string', 'max:60'],
            'seg_since' => ['nullable', 'date_format:Y-m-d'],
            'seg_until' => ['nullable', 'date_format:Y-m-d'],
            'scheduled_at' => [$mode === 'schedule' ? 'required' : 'nullable', 'date_format:Y-m-d\TH:i'],
        ], (array) __('admin_newsletter.campaigns.fields'));

        return [
            'subject' => (string) $data['subject'],
            'from_name' => (string) $data['from_name'],
            'reply_to' => (string) $data['reply_to'],
            'preheader' => isset($data['preheader']) ? (string) $data['preheader'] : null,
            'body' => (string) $data['body'],
            'segment' => ['tags' => $data['seg_tags'] ?? [], 'interests' => $data['seg_interests'] ?? [], 'since' => $data['seg_since'] ?? '', 'until' => $data['seg_until'] ?? ''],
            'scheduled_at' => $mode === 'schedule' && ! empty($data['scheduled_at']) ? CarbonImmutable::createFromFormat('Y-m-d\TH:i', (string) $data['scheduled_at'], $this->timezone())->utc() : null,
        ];
    }

    private function afterSubscriberChange(): void
    {
        unset($this->kpis);
    }

    private function canSubscribers(): bool
    {
        return (bool) auth()->user()?->can('viewAny', NewsletterSubscriber::class);
    }

    private function canCampaigns(): bool
    {
        return (bool) auth()->user()?->can('viewAny', NewsletterCampaign::class);
    }

    private function timezone(): string
    {
        return (string) config('xomiton.display_timezone', 'UTC');
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    public function render(): View
    {
        $stats = app(NewsletterStatistics::class);
        $data = [
            'format' => LocaleFormatter::make(null, $this->timezone()),
            'tabs' => $this->tabs(),
            'targets' => NewsletterStatistics::TARGETS,
            'canSend' => (bool) auth()->user()?->can('send', new NewsletterCampaign),
            'batchSize' => NewsletterCampaign::BATCH_SIZE,
        ];

        if ($this->tab === self::TAB_SUBSCRIBERS && $this->canSubscribers()) {
            $data['rows'] = $this->paginateQuery($this->filteredSubscribers());
        } elseif ($this->tab === self::TAB_CAMPAIGNS && $this->canCampaigns()) {
            $data['rows'] = $this->paginateQuery($this->filteredCampaigns());
        } else {
            $data += [
                'months' => $stats->twelveMonths(),
                'byCampaign' => $stats->byCampaign(),
                'links' => $stats->topLinks(),
                'interestCounts' => $stats->interests(),
            ];
        }

        [$sortKey, $sortDirection] = $this->currentSort();
        $data += ['sortKey' => $sortKey, 'sortDirection' => $sortDirection];
        $data['editingCampaign'] = $this->campaignId === null ? null : NewsletterCampaign::query()->find($this->campaignId);
        $data['statsCampaign'] = $this->statsCampaignId === null ? null : NewsletterCampaign::query()->find($this->statsCampaignId);
        $data['campaignStats'] = $data['statsCampaign'] === null ? null : $stats->forCampaign($data['statsCampaign']);
        $data['variables'] = CampaignRenderer::VARIABLES;

        return view('livewire.admin.newsletter.index', $data)->layout('components.layouts.admin', [
            'title' => __('newsletter.admin.title'),
            'adminPage' => 'newsletter',
        ]);
    }
}
