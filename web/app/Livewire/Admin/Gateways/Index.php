<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Gateways;

use App\Application\Admin\Gateways\ManageGateways;
use App\Application\Admin\Gateways\ManageGatewayTemplates;
use App\Application\Admin\Gateways\ManagePeerRooms;
use App\Domain\Content\Exceptions\ContentRefused;
use App\Enums\CccKind;
use App\Enums\OutboundKind;
use App\Livewire\Concerns\InteractsWithUi;
use App\Livewire\Concerns\ValidatesForm;
use App\Models\CccTool;
use App\Models\GatewayTemplate;
use App\Models\OutboundDelivery;
use App\Models\OutboundPost;
use App\Models\PeerRoom;
use App\Models\SocialChannel;
use App\Models\User;
use App\Support\LocaleFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Passerelles `/admin/passerelles` (P12-08, D4-bis, ADR-15 ; prototype admin/passerelles.php) : cartes des six réseaux
 * (connecter / déconnecter le compte de l'organisation), planification d'une publication (BD SSR, message clé,
 * texte personnalisé ≤ 500), file de publication (marquer publié à la main, annuler, retirer), modèles de messages clés et
 * salons de discussion entre pair·es. Aucun connecteur réel avant P14-04 : ManualPublisher prépare le texte, une personne
 * publie puis marque la remise. Comptes : `gateways.connect` (SocialChannelPolicy) ; file, modèles, salons :
 * `gateways.publish` (OutboundPostPolicy). Le jeton saisi n'est jamais relu ni journalisé.
 */
final class Index extends Component
{
    use InteractsWithUi;
    use ValidatesForm;
    use WithPagination;

    #[Locked]
    public ?int $connectingId = null;

    public string $account = '';

    public string $token = '';

    /** @var array{kind: string, reference: string, message: string, channels: list<int|string>, at: string} */
    public array $postForm = [];

    #[Locked]
    public ?int $templateId = null;

    /** @var array<string, mixed> */
    public array $templateForm = [];

    #[Locked]
    public ?int $roomId = null;

    /** @var array<string, mixed> */
    public array $roomForm = [];

    public function mount(): void
    {
        abort_unless($this->actor()->can('gateways.connect'), 403);
        $this->resetPostForm();
        $this->resetTemplateForm();
        $this->resetRoomForm();
    }

    // --- Comptes -----------------------------------------------------------------------------------------------------

    public function openConnect(int $id): void
    {
        $channel = SocialChannel::query()->findOrFail($id);
        $this->authorize('connect', $channel);
        $this->connectingId = (int) $channel->getKey();
        $this->account = '';
        $this->token = '';
        $this->resetErrorBag();
        unset($this->connecting);
        $this->openModal('xmGatewayConnect');
    }

    public function resetConnect(): void
    {
        $this->reset('connectingId', 'account', 'token');
        $this->resetErrorBag();
        unset($this->connecting);
    }

    public function connect(ManageGateways $manage): void
    {
        $channel = SocialChannel::query()->findOrFail($this->connectingId ?? 0);
        $this->authorize('connect', $channel);
        $this->validate([
            'account' => ['required', 'string', 'regex:'.ManageGateways::HANDLE_PATTERN],
            'token' => ['nullable', 'string', 'max:'.ManageGateways::TOKEN_MAX],
        ], ['account.regex' => (string) __('admin_content.errors.handle_invalid')], [
            'account' => (string) __('admin_gateways.connect.account'),
            'token' => (string) __('admin_gateways.connect.token'),
        ]);

        try {
            $manage->connect($channel, $this->account, $this->token, $this->actor());
        } catch (ContentRefused $refused) {
            $this->addError('account', $refused->userMessage());

            return;
        }

        $this->closeModal('xmGatewayConnect');
        $this->resetConnect();
        unset($this->channels);
        $this->toast((string) __('admin_gateways.toasts.connected', ['network' => $channel->label]));
    }

    public function disconnect(int $id, ManageGateways $manage): void
    {
        $channel = SocialChannel::query()->findOrFail($id);
        $this->authorize('disconnect', $channel);

        try {
            $manage->disconnect($channel, $this->actor());
        } catch (ContentRefused $refused) {
            $this->toast($refused->userMessage(), 'error');

            return;
        }

        unset($this->channels);
        $this->toast((string) __('admin_gateways.toasts.disconnected', ['network' => $channel->label]), 'warning');
    }

    // --- File de publication -----------------------------------------------------------------------------------------

    public function resetPostForm(): void
    {
        $this->postForm = [
            'kind' => OutboundKind::Bd->value,
            'reference' => '',
            'message' => '',
            'channels' => [],
            'at' => CarbonImmutable::now($this->timezone())->addDay()->setTime(9, 0)->format('Y-m-d\TH:i'),
        ];
        $this->resetErrorBag();
    }

    public function schedule(ManageGateways $manage): void
    {
        $this->authorize('create', OutboundPost::class);
        $form = $this->postForm;
        $needsReference = in_array($form['kind'], [OutboundKind::Bd->value, OutboundKind::Template->value], true);

        $this->validateForm('postForm', $form, [
            'kind' => ['required', Rule::enum(OutboundKind::class)],
            'reference' => [$needsReference ? 'required' : 'nullable', 'string', 'max:120'],
            'message' => [$form['kind'] === OutboundKind::Custom->value ? 'required' : 'nullable', 'string', 'min:'.($form['kind'] === OutboundKind::Custom->value ? OutboundPost::MESSAGE_MIN_LENGTH : 0), 'max:'.OutboundPost::MESSAGE_MAX_LENGTH],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['integer', Rule::exists('social_channels', 'id')->where('is_connected', true)],
            'at' => ['required', 'date_format:Y-m-d\TH:i'],
        ], (array) __('admin_gateways.schedule.fields'));

        try {
            $manage->schedule([
                'kind' => $form['kind'],
                'reference' => $form['reference'] === '' ? null : $form['reference'],
                'message' => $form['message'],
                'channel_ids' => array_map('intval', $form['channels']),
                'scheduled_at' => CarbonImmutable::createFromFormat('Y-m-d\TH:i', $form['at'], $this->timezone())->utc(),
            ], $this->actor());
        } catch (ContentRefused $refused) {
            $this->addError('postForm.at', $refused->userMessage());

            return;
        }

        $this->resetPostForm();
        $this->toast((string) __('admin_gateways.toasts.scheduled'));
    }

    public function markDelivered(int $id, ManageGateways $manage): void
    {
        $delivery = OutboundDelivery::query()->with('post')->findOrFail($id);
        $this->authorize('update', $delivery->post ?? abort(404));

        try {
            $manage->markDelivered($delivery, $this->actor());
        } catch (ContentRefused $refused) {
            $this->toast($refused->userMessage(), 'error');

            return;
        }

        $this->toast((string) __('admin_gateways.toasts.marked'));
    }

    public function cancelPost(int $id, ManageGateways $manage): void
    {
        $post = OutboundPost::query()->findOrFail($id);
        $this->authorize('update', $post);

        try {
            $manage->cancel($post, $this->actor());
        } catch (ContentRefused $refused) {
            $this->toast($refused->userMessage(), 'error');

            return;
        }

        $this->toast((string) __('admin_gateways.toasts.cancelled'), 'warning');
    }

    public function removePost(int $id, ManageGateways $manage): void
    {
        $post = OutboundPost::query()->findOrFail($id);
        $this->authorize('delete', $post);

        try {
            $manage->remove($post, $this->actor());
        } catch (ContentRefused $refused) {
            $this->toast($refused->userMessage(), 'error');

            return;
        }

        $this->toast((string) __('admin_gateways.toasts.removed'), 'warning');
    }

    // --- Modèles de messages clés ------------------------------------------------------------------------------------

    public function resetTemplateForm(): void
    {
        $this->reset('templateId');
        $this->templateForm = ['title' => '', 'body' => '', 'is_active' => true];
        $this->resetErrorBag();
    }

    public function newTemplate(): void
    {
        $this->authorize('create', OutboundPost::class);
        $this->resetTemplateForm();
        $this->openModal('xmGatewayTemplate');
    }

    public function editTemplate(int $id): void
    {
        $this->authorize('create', OutboundPost::class);
        $template = GatewayTemplate::query()->findOrFail($id);
        $this->resetTemplateForm();
        $this->templateId = (int) $template->getKey();
        $this->templateForm = ['title' => (string) $template->title, 'body' => (string) $template->body, 'is_active' => (bool) $template->is_active];
        $this->openModal('xmGatewayTemplate');
    }

    public function saveTemplate(ManageGatewayTemplates $manage): void
    {
        $this->authorize('create', OutboundPost::class);
        $template = $this->templateId === null ? null : GatewayTemplate::query()->findOrFail($this->templateId);

        $data = $this->validateForm('templateForm', $this->templateForm, [
            'title' => ['required', 'string', 'min:'.GatewayTemplate::TITLE_MIN_LENGTH, 'max:80'],
            'body' => ['required', 'string', 'min:'.GatewayTemplate::BODY_MIN_LENGTH, 'max:'.GatewayTemplate::BODY_MAX_LENGTH],
            'is_active' => ['boolean'],
        ], (array) __('admin_gateways.templates.fields'));

        $manage->save($template, ['title' => (string) $data['title'], 'body' => (string) $data['body'], 'is_active' => (bool) ($data['is_active'] ?? false)], $this->actor());
        $this->closeModal('xmGatewayTemplate');
        $this->resetTemplateForm();
        unset($this->templates);
        $this->toast((string) __('admin_gateways.toasts.template_saved'));
    }

    public function deleteTemplate(int $id, ManageGatewayTemplates $manage): void
    {
        $this->authorize('create', OutboundPost::class);
        $manage->delete(GatewayTemplate::query()->findOrFail($id), $this->actor());
        unset($this->templates);
        $this->toast((string) __('admin_gateways.toasts.template_deleted'), 'warning');
    }

    // --- Salons entre pair·es ----------------------------------------------------------------------------------------

    public function resetRoomForm(): void
    {
        $this->reset('roomId');
        $this->roomForm = ['name' => '', 'description' => '', 'is_moderated' => true, 'allow_anonymous' => true, 'max_members' => (string) PeerRoom::DEFAULT_MAX_MEMBERS, 'is_active' => true];
        $this->resetErrorBag();
    }

    public function newRoom(): void
    {
        $this->authorize('create', OutboundPost::class);
        $this->resetRoomForm();
        $this->openModal('xmGatewayRoom');
    }

    public function editRoom(int $id): void
    {
        $this->authorize('create', OutboundPost::class);
        $room = PeerRoom::query()->findOrFail($id);
        $this->resetRoomForm();
        $this->roomId = (int) $room->getKey();
        $this->roomForm = [
            'name' => (string) $room->name, 'description' => (string) $room->description, 'is_moderated' => (bool) $room->is_moderated,
            'allow_anonymous' => (bool) $room->allow_anonymous, 'max_members' => (string) $room->max_members, 'is_active' => (bool) $room->is_active,
        ];
        $this->openModal('xmGatewayRoom');
    }

    public function saveRoom(ManagePeerRooms $manage): void
    {
        $this->authorize('create', OutboundPost::class);
        $room = $this->roomId === null ? null : PeerRoom::query()->findOrFail($this->roomId);

        $data = $this->validateForm('roomForm', $this->roomForm, [
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_moderated' => ['boolean'],
            'allow_anonymous' => ['boolean'],
            'max_members' => ['required', 'integer', 'between:'.PeerRoom::MIN_MEMBERS.','.PeerRoom::MAX_MEMBERS],
            'is_active' => ['boolean'],
        ], (array) __('admin_gateways.rooms.fields'));

        $manage->save($room, [
            'name' => (string) $data['name'],
            'description' => isset($data['description']) ? (string) $data['description'] : null,
            'is_moderated' => (bool) ($data['is_moderated'] ?? false),
            'allow_anonymous' => (bool) ($data['allow_anonymous'] ?? false),
            'max_members' => (int) $data['max_members'],
            'is_active' => (bool) ($data['is_active'] ?? false),
        ], $this->actor());
        $this->closeModal('xmGatewayRoom');
        $this->resetRoomForm();
        unset($this->rooms);
        $this->toast((string) __('admin_gateways.toasts.room_saved'));
    }

    public function toggleRoom(int $id, ManagePeerRooms $manage): void
    {
        $this->authorize('create', OutboundPost::class);
        $room = $manage->toggle(PeerRoom::query()->findOrFail($id), $this->actor());
        unset($this->rooms);
        $this->toast((string) __($room->is_active ? 'admin_gateways.toasts.room_activated' : 'admin_gateways.toasts.room_deactivated'));
    }

    // --- Données -----------------------------------------------------------------------------------------------------

    /** @return Collection<int, SocialChannel> */
    #[Computed]
    public function channels(): Collection
    {
        return SocialChannel::query()->orderBy('id')->get();
    }

    #[Computed]
    public function connecting(): ?SocialChannel
    {
        return $this->connectingId === null ? null : SocialChannel::query()->find($this->connectingId);
    }

    /** @return Collection<int, CccTool> */
    #[Computed]
    public function comics(): Collection
    {
        return CccTool::query()->ofKind(CccKind::Comic)->published()->ordered()->get(['id', 'key', 'title']);
    }

    /** @return Collection<int, GatewayTemplate> */
    #[Computed]
    public function templates(): Collection
    {
        return GatewayTemplate::query()->orderBy('position')->orderBy('id')->get();
    }

    /** @return Collection<int, PeerRoom> */
    #[Computed]
    public function rooms(): Collection
    {
        return PeerRoom::query()->orderBy('name')->get();
    }

    public function render(): View
    {
        return view('livewire.admin.gateways.index', [
            'queue' => OutboundPost::query()->with(['deliveries.channel'])->queue()->paginate(8, pageName: 'file'),
            'format' => LocaleFormatter::make(null, $this->timezone()),
            'canPublish' => $this->actor()->can('gateways.publish'),
            'manage' => app(ManageGateways::class),
            'maxMessage' => OutboundPost::MESSAGE_MAX_LENGTH,
        ])->layout('components.layouts.admin', [
            'title' => __('admin_gateways.title'),
            'adminPage' => 'passerelles',
        ]);
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
}
