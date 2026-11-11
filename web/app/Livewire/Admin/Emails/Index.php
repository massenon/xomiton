<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Emails;

use App\Application\Admin\Emails\EmailPreview;
use App\Application\Admin\Emails\ManageEmailTemplates;
use App\Application\Mail\SmtpSettings;
use App\Domain\Newsletter\Exceptions\CommunicationRefused;
use App\Enums\TemplateCategory;
use App\Livewire\Attributes\SafeUrl;
use App\Livewire\Concerns\InteractsWithUi;
use App\Livewire\Concerns\ValidatesForm;
use App\Livewire\Concerns\WithDataTable;
use App\Models\EmailTemplate;
use App\Models\MailLog;
use App\Models\MailOutbox;
use App\Models\User;
use App\Support\LocaleFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Templates e-mails `/admin/emails` (P14-01, prototype admin/emails.php, `email_templates.manage`) : KPI, liste (recherche
 * nom / slug, filtres catégorie et état), modale d'édition (slug généré et verrouillé pour un template système, variables
 * cliquables, aperçu bureau / mobile avec données fictives, envoi de test), dupliquer, activer / désactiver, suppression
 * (RG-10 : un template système exige son slug et `email_templates.delete_system`), état du SMTP et de `mail_outbox`,
 * derniers envois (adresses masquées). Le test d'envoi est déposé dans la boîte d'envoi : aucun SMTP dans la requête.
 * Le réglage du serveur SMTP reste dans Paramètres (superadmin).
 */
final class Index extends Component
{
    use InteractsWithUi;
    use ValidatesForm;
    use WithDataTable;

    #[SafeUrl(as: 'cat', except: '')]
    public string $category = '';

    #[SafeUrl(as: 'etat', except: '')]
    public string $state = '';

    #[Locked]
    public ?int $templateId = null;

    /** @var array{name: string, slug: string, category: string, subject: string, preheader: string, body: string, is_active: bool} */
    public array $form = [];

    /** `edit` | `preview` : volet de la modale. */
    public string $pane = 'edit';

    public string $previewMode = 'desktop';

    public string $previewSubject = '';

    public string $previewHtml = '';

    /** @var list<string> */
    public array $previewUnknown = [];

    public string $testTo = '';

    #[Locked]
    public ?int $deleteId = null;

    public string $deleteSlug = '';

    public function mount(): void
    {
        $this->authorize('viewAny', EmailTemplate::class);
        $this->resetForm();
    }

    protected function sortableColumns(): array
    {
        return ['nom' => 'name', 'slug' => 'slug', 'categorie' => 'category', 'modifie' => 'updated_at'];
    }

    protected function searchableColumns(): array
    {
        return ['name', 'slug'];
    }

    protected function defaultSort(): array
    {
        return ['nom', 'asc'];
    }

    protected function filterProperties(): array
    {
        return ['category', 'state'];
    }

    // --- Modale -------------------------------------------------------------------------------------------------------

    public function resetForm(): void
    {
        $this->reset('templateId', 'pane', 'previewMode', 'previewSubject', 'previewHtml', 'previewUnknown', 'testTo');
        $this->form = ['name' => '', 'slug' => '', 'category' => TemplateCategory::Other->value, 'subject' => '', 'preheader' => '', 'body' => '', 'is_active' => true];
        $this->resetErrorBag();
    }

    public function create(): void
    {
        $this->authorize('create', EmailTemplate::class);
        $this->resetForm();
        $this->openModal('xmEmailEdit');
    }

    public function edit(int $id): void
    {
        $template = EmailTemplate::query()->findOrFail($id);
        $this->authorize('update', $template);
        $this->resetForm();
        $this->templateId = (int) $template->getKey();
        $this->form = [
            'name' => (string) $template->name,
            'slug' => (string) $template->slug,
            'category' => $template->category->value,
            'subject' => (string) $template->subject,
            'preheader' => (string) $template->preheader,
            'body' => (string) $template->body,
            'is_active' => (bool) $template->is_active,
        ];
        $this->openModal('xmEmailEdit');
    }

    public function save(ManageEmailTemplates $manage): void
    {
        $template = $this->templateId === null ? null : EmailTemplate::query()->findOrFail($this->templateId);
        $this->authorize($template === null ? 'create' : 'update', $template ?? EmailTemplate::class);

        $data = $this->validateForm('form', $this->form, [
            'name' => ['required', 'string', 'min:3', 'max:80'],
            'slug' => ['nullable', 'string', 'max:'.ManageEmailTemplates::SLUG_MAX, 'regex:'.ManageEmailTemplates::SLUG_PATTERN],
            'category' => ['required', Rule::enum(TemplateCategory::class)],
            'subject' => ['required', 'string', 'min:3', 'max:150'],
            'preheader' => ['nullable', 'string', 'max:150'],
            'body' => ['required', 'string', 'min:10', 'max:60000'],
            'is_active' => ['boolean'],
        ], (array) __('admin.emails.fields'));

        try {
            $manage->save($template, [
                'name' => (string) $data['name'],
                'slug' => (string) ($data['slug'] ?? ''),
                'category' => (string) $data['category'],
                'subject' => (string) $data['subject'],
                'preheader' => (string) ($data['preheader'] ?? ''),
                'body' => (string) $data['body'],
                'is_active' => (bool) ($data['is_active'] ?? false),
            ], $this->actor());
        } catch (CommunicationRefused $refused) {
            $this->addError($refused->errorCode() === 'slug_invalid' ? 'form.slug' : 'form.body', $refused->userMessage());

            return;
        }

        $this->closeModal('xmEmailEdit');
        $this->resetForm();
        unset($this->stats, $this->variables);
        $this->toast((string) __('admin_newsletter.emails.toasts.saved'));
    }

    // --- Aperçu et test (contenu non enregistré) -----------------------------------------------------------------------

    public function showPreview(): void
    {
        $this->validateForm('form', $this->form, [
            'subject' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:60000'],
        ], (array) __('admin.emails.fields'));

        $rendered = EmailPreview::render($this->form['subject'], $this->form['preheader'], $this->form['body']);
        $this->previewSubject = $rendered['subject'];
        $this->previewHtml = $rendered['html'];
        $this->previewUnknown = $rendered['unknown'];
        $this->pane = 'preview';
    }

    public function setPane(string $pane): void
    {
        if ($pane === 'preview') {
            $this->showPreview();

            return;
        }

        $this->pane = 'edit';
    }

    public function setPreviewMode(string $mode): void
    {
        $this->previewMode = $mode === 'mobile' ? 'mobile' : 'desktop';
    }

    public function sendTest(ManageEmailTemplates $manage): void
    {
        $template = $this->templateId === null ? new EmailTemplate : EmailTemplate::query()->findOrFail($this->templateId);
        $this->authorize('sendTest', $template);
        $this->validate(['testTo' => ['required', 'email:filter', 'max:190']], [], ['testTo' => (string) __('admin.emails.test.address')]);

        $user = $this->actor();

        if (! RateLimiter::attempt('email-template-test:'.$user->getKey(), 5, static fn (): bool => true, 60)) {
            $this->addError('testTo', (string) __('admin_newsletter.emails.test.throttled'));

            return;
        }

        try {
            $manage->sendTest($this->form['subject'], $this->form['preheader'], $this->form['body'], $this->testTo, $user);
        } catch (CommunicationRefused $refused) {
            $this->addError('testTo', $refused->userMessage());

            return;
        }

        $this->toast((string) __('admin_newsletter.emails.toasts.test_queued'));
        unset($this->outbox);
    }

    // --- Actions de ligne ----------------------------------------------------------------------------------------------

    public function duplicate(int $id, ManageEmailTemplates $manage): void
    {
        $template = EmailTemplate::query()->findOrFail($id);
        $this->authorize('create', EmailTemplate::class);
        $manage->duplicate($template, $this->actor());
        unset($this->stats);
        $this->toast((string) __('admin_newsletter.emails.toasts.duplicated'));
    }

    public function toggle(int $id, ManageEmailTemplates $manage): void
    {
        $template = EmailTemplate::query()->findOrFail($id);
        $this->authorize('update', $template);
        $template = $manage->toggle($template, $this->actor());
        unset($this->stats);
        $this->toast((string) __($template->is_active ? 'admin_newsletter.emails.toasts.activated' : 'admin_newsletter.emails.toasts.deactivated'), $template->is_active ? 'success' : 'warning');
    }

    public function askDelete(int $id): void
    {
        $template = EmailTemplate::query()->findOrFail($id);
        $this->authorize('delete', $template);
        $this->deleteId = (int) $template->getKey();
        $this->deleteSlug = '';
        $this->resetErrorBag();
        $this->openModal('xmEmailDelete');
    }

    public function delete(ManageEmailTemplates $manage): void
    {
        $template = EmailTemplate::query()->findOrFail($this->deleteId ?? 0);
        $this->authorize('delete', $template);

        try {
            $manage->delete($template, $this->deleteSlug, $this->actor());
        } catch (CommunicationRefused $refused) {
            $this->addError('deleteSlug', $refused->userMessage());

            return;
        }

        $this->closeModal('xmEmailDelete');
        $this->reset('deleteId', 'deleteSlug');
        unset($this->stats);
        $this->toast((string) __('admin_newsletter.emails.toasts.deleted'), 'warning');
    }

    // --- Données -------------------------------------------------------------------------------------------------------

    /** @return array{total: int, active: int, system: int, sent_30d: int} */
    #[Computed]
    public function stats(): array
    {
        return [
            'total' => EmailTemplate::query()->count(),
            'active' => EmailTemplate::query()->active()->count(),
            'system' => EmailTemplate::query()->system()->count(),
            'sent_30d' => MailLog::query()->where('ok', true)->where('created_at', '>=', now()->subDays(30))->count(),
        ];
    }

    /** @return array<string, string> nom => valeur fictive */
    #[Computed]
    public function variables(): array
    {
        return EmailPreview::availableVariables();
    }

    /** @return array{pending: int, failed: int} */
    #[Computed]
    public function outbox(): array
    {
        return [
            'pending' => MailOutbox::query()->pending()->count(),
            'failed' => MailOutbox::query()->pending()->where('attempts', '>=', MailOutbox::MAX_ATTEMPTS)->count(),
        ];
    }

    /** @return Collection<int, MailLog> */
    #[Computed]
    public function recent(): Collection
    {
        return MailLog::query()->orderByDesc('id')->limit(8)->get();
    }

    /** @return Builder<EmailTemplate> */
    private function filtered(): Builder
    {
        return EmailTemplate::query()
            ->when(TemplateCategory::tryFrom($this->category), fn (Builder $q, TemplateCategory $c) => $q->inCategory($c))
            ->when($this->state === 'actif', fn (Builder $q) => $q->where('is_active', true))
            ->when($this->state === 'inactif', fn (Builder $q) => $q->where('is_active', false));
    }

    public function render(): View
    {
        $rows = $this->paginateQuery($this->filtered());
        [$sortKey, $sortDirection] = $this->currentSort();

        return view('livewire.admin.emails.index', [
            'rows' => $rows,
            'sortKey' => $sortKey,
            'sortDirection' => $sortDirection,
            'format' => LocaleFormatter::make(null, (string) config('xomiton.display_timezone', 'UTC')),
            'smtp' => [
                'configured' => SmtpSettings::isConfigured(),
                'host' => SmtpSettings::host(),
                'from' => SmtpSettings::fromAddress(),
                'mailer' => (string) config('mail.default', 'log'),
            ],
            'canSettings' => (bool) auth()->user()?->can('settings.view'),
            'editing' => $this->templateId === null ? null : EmailTemplate::query()->find($this->templateId),
            'deleting' => $this->deleteId === null ? null : EmailTemplate::query()->find($this->deleteId),
        ])->layout('components.layouts.admin', [
            'title' => __('admin.emails.title'),
            'adminPage' => 'emails',
        ]);
    }

    /** `awa@exemple.bj` → `a***@exemple.bj` : un journal lisible ne montre jamais l'adresse complète. */
    public static function mask(string $address): string
    {
        $at = strrpos($address, '@');

        return $at === false || $at < 1 ? '***' : mb_substr($address, 0, 1).'***'.substr($address, $at);
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
