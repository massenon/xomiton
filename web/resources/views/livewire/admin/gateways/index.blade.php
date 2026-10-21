{{--
    Passerelles réseaux (P12-08, D4-bis) : composant App\Livewire\Admin\Gateways\Index, gabarit admin.
    Ordre : bandeau d'information · cartes des réseaux · planifier | file de publication · salons entre pair·es | modèles · modales.
    Aucun connecteur réel avant P14-04 : le texte est prêt à copier, une personne publie puis marque la remise « publiée ».
--}}
<div class="xm-gateways-admin">
    <div class="alert alert-info d-flex gap-2" role="note">
        <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
        <div>{{ __('admin_gateways.notice') }}</div>
    </div>

    <section aria-labelledby="xmGatewayCardsTitle" class="mb-4">
        <h2 class="h6 fw-bold mb-3" id="xmGatewayCardsTitle">{{ __('admin_gateways.cards_title') }}</h2>
        <div class="row g-3">
            @foreach ($this->channels as $channel)
                <div class="col-6 col-md-4 col-xl-2" wire:key="channel-{{ $channel->id }}">
                    <div class="card xm-admin-card h-100 text-center"><div class="card-body d-flex flex-column">
                        <div class="fs-1 mb-1" style="color: {{ $channel->color }}"><i class="bi {{ $channel->icon }}" aria-hidden="true"></i></div>
                        <h3 class="h6 mb-1">{{ $channel->label }}</h3>
                        <div @class(['small mb-2', 'text-success' => $channel->is_connected, 'text-secondary' => ! $channel->is_connected])>
                            @if ($channel->is_connected)<i class="bi bi-plug-fill" aria-hidden="true"></i> {{ $channel->account_handle }}@else{{ __('admin_gateways.not_connected') }}@endif
                        </div>
                        <div class="mb-2"><x-badge :color="$channel->supports_api ? 'info' : 'neutral'" soft class="mx-auto">{{ $channel->supports_api ? __('admin_gateways.api') : __('admin_gateways.manual') }}</x-badge></div>
                        @if ($channel->is_connected && $channel->hasExpiredToken())<div class="small text-danger mb-2">{{ __('admin_gateways.token_expired') }}</div>@endif
                        <div class="mt-auto">
                            @if ($channel->is_connected)
                                <button type="button" class="btn btn-sm btn-outline-danger w-100" wire:click="disconnect({{ $channel->id }})" wire:confirm="{{ __('admin_gateways.confirm.disconnect', ['network' => $channel->label]) }}">{{ __('admin_gateways.disconnect') }}<span class="visually-hidden"> {{ $channel->label }}</span></button>
                            @else
                                <button type="button" class="btn btn-sm btn-primary w-100" wire:click="openConnect({{ $channel->id }})">{{ __('admin_gateways.connect.open') }}<span class="visually-hidden"> {{ $channel->label }}</span></button>
                            @endif
                        </div>
                    </div></div>
                </div>
            @endforeach
        </div>
    </section>

    @if ($canPublish)
        <div class="row g-4 mb-4">
            <div class="col-xl-6">
                <form class="card xm-admin-card h-100" wire:submit="schedule" novalidate aria-labelledby="xmGatewayScheduleTitle">
                    <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmGatewayScheduleTitle"><i class="bi bi-calendar-plus me-2" aria-hidden="true"></i>{{ __('admin_gateways.schedule.title') }}</h2></div>
                    <div class="card-body row g-3">
                        <fieldset class="col-12">
                            <legend class="form-label fw-semibold fs-6">{{ __('admin_gateways.schedule.fields.kind') }}</legend>
                            <div class="btn-group w-100" role="group">
                                @foreach (\App\Enums\OutboundKind::cases() as $kind)
                                    <input type="radio" class="btn-check" name="kind" id="xmGatewayKind{{ $kind->value }}" value="{{ $kind->value }}" wire:model.live="postForm.kind" autocomplete="off">
                                    <label class="btn btn-outline-primary btn-sm" for="xmGatewayKind{{ $kind->value }}">{{ $kind->label() }}</label>
                                @endforeach
                            </div>
                        </fieldset>
                        @if ($postForm['kind'] === 'bd')
                            <div class="col-12">
                                <x-field name="postForm.reference" type="select" :label="__('admin_gateways.schedule.fields.reference_bd')" wire:model="postForm.reference"
                                         :options="['' => __('admin_content.choose')] + $this->comics->pluck('title', 'key')->all()" />
                            </div>
                        @elseif ($postForm['kind'] === 'template')
                            <div class="col-12">
                                <x-field name="postForm.reference" type="select" :label="__('admin_gateways.schedule.fields.reference_template')" wire:model="postForm.reference"
                                         :options="['' => __('admin_content.choose')] + $this->templates->where('is_active', true)->pluck('title', 'id')->all()" />
                            </div>
                        @endif
                        <div class="col-12">
                            <x-field name="postForm.message" type="textarea" :optional="$postForm['kind'] !== 'custom'" :label="__('admin_gateways.schedule.fields.message')" wire:model.blur="postForm.message" rows="3" :maxlength="$maxMessage"
                                     :hint="__('admin_gateways.schedule.message_help')" />
                        </div>
                        <fieldset class="col-12">
                            <legend class="form-label fw-semibold fs-6">{{ __('admin_gateways.schedule.fields.channels') }} <span class="small text-secondary fw-normal">{{ __('admin_gateways.schedule.connected_only') }}</span></legend>
                            <div class="d-flex flex-wrap gap-3">
                                @foreach ($this->channels as $channel)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="xmGatewayNet{{ $channel->id }}" value="{{ $channel->id }}" wire:model="postForm.channels" @disabled(! $channel->is_connected)>
                                        <label @class(['form-check-label', 'text-secondary' => ! $channel->is_connected]) for="xmGatewayNet{{ $channel->id }}">{{ $channel->label }}</label>
                                    </div>
                                @endforeach
                            </div>
                            @error('postForm.channels')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </fieldset>
                        <div class="col-md-7">
                            <label class="form-label fw-semibold" for="xmGatewayAt">{{ __('admin_gateways.schedule.fields.at') }}</label>
                            <input class="form-control @error('postForm.at') is-invalid @enderror" type="datetime-local" id="xmGatewayAt" wire:model.blur="postForm.at" aria-describedby="xmGatewayAtHelp">
                            <div class="form-text" id="xmGatewayAtHelp">{{ __('admin_gateways.schedule.at_help') }}</div>
                            @error('postForm.at')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="card-footer text-end">
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="schedule" @disabled($this->channels->where('is_connected', true)->isEmpty())>{{ __('admin_gateways.schedule.submit') }}</button>
                    </div>
                </form>
            </div>
            <div class="col-xl-6">
                <section class="card xm-admin-card h-100" aria-labelledby="xmGatewayQueueTitle">
                    <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmGatewayQueueTitle">{{ __('admin_gateways.queue.title', ['count' => $queue->total()]) }}</h2></div>
                    <ul class="list-group list-group-flush">
                        @forelse ($queue as $post)
                            <li class="list-group-item" wire:key="post-{{ $post->id }}">
                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    <span class="fw-semibold">{{ \App\Application\Admin\Gateways\ManageGateways::referenceLabel($post) }}</span>
                                    <x-badge :color="$post->kind->badge()" soft>{{ $post->kind->label() }}</x-badge>
                                    <x-badge :color="$post->status->badge()">{{ $post->status->label() }}</x-badge>
                                    <time class="small text-secondary ms-auto" datetime="{{ $post->scheduled_at?->toIso8601String() }}">{{ $format->tableDateTime($post->scheduled_at) }}</time>
                                </div>
                                <div class="small text-secondary text-break">{{ \Illuminate\Support\Str::limit($post->message, 120) }}</div>
                                <ul class="list-unstyled small mt-2 mb-0">
                                    @foreach ($post->deliveries as $delivery)
                                        <li class="d-flex flex-wrap gap-2 align-items-center py-1" wire:key="delivery-{{ $delivery->id }}">
                                            <i class="bi {{ $delivery->channel?->icon }}" aria-hidden="true"></i>
                                            <span>{{ $delivery->channel?->label }}</span>
                                            <x-badge :color="$delivery->status->badge()" soft>{{ $delivery->status->label() }}</x-badge>
                                            @if (! $delivery->status->isDelivered() && $post->status !== \App\Enums\OutboundStatus::Cancelled)
                                                <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" x-data x-on:click="navigator.clipboard.writeText(@js($manage->copyText($delivery))); $el.textContent = @js(__('admin_gateways.queue.copied'))"><i class="bi bi-clipboard me-1" aria-hidden="true"></i>{{ __('admin_gateways.queue.copy') }}<span class="visually-hidden"> {{ $delivery->channel?->label }}</span></button>
                                                <button type="button" class="btn btn-sm btn-success" wire:click="markDelivered({{ $delivery->id }})" wire:confirm="{{ __('admin_gateways.confirm.mark', ['network' => $delivery->channel?->label]) }}">{{ __('admin_gateways.queue.mark') }}<span class="visually-hidden"> {{ $delivery->channel?->label }}</span></button>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                                <div class="d-flex gap-2 mt-2">
                                    @if ($post->isScheduled())
                                        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="cancelPost({{ $post->id }})" wire:confirm="{{ __('admin_gateways.confirm.cancel') }}">{{ __('admin_gateways.queue.cancel') }}</button>
                                    @endif
                                    @if ($post->status !== \App\Enums\OutboundStatus::Published)
                                        <button type="button" class="btn btn-sm btn-link text-danger ms-auto" wire:click="removePost({{ $post->id }})" wire:confirm="{{ __('admin_gateways.confirm.remove') }}">{{ __('admin_gateways.queue.remove') }}</button>
                                    @endif
                                </div>
                            </li>
                        @empty
                            <li class="list-group-item text-center text-secondary py-4">{{ __('admin_gateways.queue.empty') }}</li>
                        @endforelse
                    </ul>
                    @if ($queue->hasPages())
                        <div class="card-footer d-flex justify-content-between align-items-center">
                            <button type="button" class="btn btn-sm btn-light border" wire:click="previousPage('file')" @disabled($queue->onFirstPage())>{{ __('admin.datatable.previous') }}</button>
                            <span class="small text-secondary">{{ $queue->currentPage() }} / {{ $queue->lastPage() }}</span>
                            <button type="button" class="btn btn-sm btn-light border" wire:click="nextPage('file')" @disabled(! $queue->hasMorePages())>{{ __('admin.datatable.next') }}</button>
                        </div>
                    @endif
                </section>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-xl-5">
                <section class="card xm-admin-card h-100" aria-labelledby="xmGatewayRoomsTitle">
                    <div class="card-header d-flex justify-content-between align-items-center gap-2">
                        <h2 class="h6 fw-bold mb-0" id="xmGatewayRoomsTitle">{{ __('admin_gateways.rooms.title') }}</h2>
                        <button type="button" class="btn btn-primary btn-sm" wire:click="newRoom"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('admin_gateways.rooms.new') }}</button>
                    </div>
                    <ul class="list-group list-group-flush">
                        @forelse ($this->rooms as $room)
                            <li class="list-group-item d-flex gap-2" wire:key="room-{{ $room->id }}">
                                <div class="flex-grow-1">
                                    <div class="fw-semibold">{{ $room->name }} @unless ($room->is_active)<x-badge color="neutral" soft>{{ __('admin_gateways.rooms.inactive') }}</x-badge>@endunless</div>
                                    <div class="small text-secondary">{{ __('admin_gateways.rooms.summary', ['max' => $room->max_members, 'moderation' => $room->is_moderated ? __('admin_gateways.rooms.moderated') : __('admin_gateways.rooms.free'), 'anonymous' => $room->allow_anonymous ? __('admin_gateways.rooms.pseudonym') : __('admin_gateways.rooms.named')]) }}</div>
                                </div>
                                <div class="d-flex flex-wrap gap-1 align-items-start">
                                    <button type="button" class="btn btn-sm btn-outline-primary" wire:click="editRoom({{ $room->id }})">{{ __('admin_content.actions.edit') }}<span class="visually-hidden"> {{ $room->name }}</span></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="toggleRoom({{ $room->id }})">{{ $room->is_active ? __('admin_gateways.rooms.deactivate') : __('admin_gateways.rooms.activate') }}<span class="visually-hidden"> {{ $room->name }}</span></button>
                                </div>
                            </li>
                        @empty
                            <li class="list-group-item text-center text-secondary py-4">{{ __('admin_gateways.rooms.empty') }}</li>
                        @endforelse
                    </ul>
                </section>
            </div>
            <div class="col-xl-7">
                <section class="card xm-admin-card h-100" aria-labelledby="xmGatewayTemplatesTitle">
                    <div class="card-header d-flex justify-content-between align-items-center gap-2">
                        <h2 class="h6 fw-bold mb-0" id="xmGatewayTemplatesTitle">{{ __('admin_gateways.templates.title') }}</h2>
                        <button type="button" class="btn btn-primary btn-sm" wire:click="newTemplate"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('admin_gateways.templates.new') }}</button>
                    </div>
                    <ul class="list-group list-group-flush">
                        @forelse ($this->templates as $template)
                            <li class="list-group-item d-flex gap-2" wire:key="template-{{ $template->id }}">
                                <div class="flex-grow-1">
                                    <div class="fw-semibold">{{ $template->title }} @unless ($template->is_active)<x-badge color="neutral" soft>{{ __('admin_gateways.templates.inactive') }}</x-badge>@endunless</div>
                                    <div class="small text-secondary text-break">{{ $template->body }}</div>
                                </div>
                                <div class="d-flex flex-wrap gap-1 align-items-start">
                                    <button type="button" class="btn btn-sm btn-outline-primary" wire:click="editTemplate({{ $template->id }})">{{ __('admin_content.actions.edit') }}<span class="visually-hidden"> {{ $template->title }}</span></button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="deleteTemplate({{ $template->id }})" wire:confirm="{{ __('admin_gateways.confirm.delete_template') }}"><i class="bi bi-trash" aria-hidden="true"></i><span class="visually-hidden">{{ __('admin_content.actions.delete') }} {{ $template->title }}</span></button>
                                </div>
                            </li>
                        @empty
                            <li class="list-group-item text-center text-secondary py-4">{{ __('admin_gateways.templates.empty') }}</li>
                        @endforelse
                    </ul>
                </section>
            </div>
        </div>
    @endif

    <x-admin.modal id="xmGatewayConnect" size="md" heading="h2" :title="__('admin_gateways.connect.title', ['network' => $this->connecting?->label ?? ''])" x-data x-on:hidden-bs-modal.dot="$wire.resetConnect()">
        <form id="xmGatewayConnectForm" wire:submit="connect" novalidate autocomplete="off">
            <p class="small text-secondary">{{ __('admin_gateways.connect.help') }}</p>
            <x-field name="account" :label="__('admin_gateways.connect.account')" wire:model="account" maxlength="41" :placeholder="'@xomiton.bj'" :hint="__('admin_gateways.connect.account_help')" />
            @if ($this->connecting?->supports_api)
                <x-field name="token" type="password" optional :label="__('admin_gateways.connect.token')" wire:model="token" autocomplete="off" :hint="__('admin_gateways.connect.token_help')" />
            @endif
        </form>
        <x-slot:footer>
            <button type="submit" form="xmGatewayConnectForm" class="btn btn-primary" wire:loading.attr="disabled" wire:target="connect"><i class="bi bi-plug me-1" aria-hidden="true"></i>{{ __('admin_gateways.connect.submit') }}</button>
        </x-slot:footer>
    </x-admin.modal>

    <x-admin.modal id="xmGatewayTemplate" size="md" heading="h2" dirty-guard :title="$templateId ? __('admin_gateways.templates.edit_title') : __('admin_gateways.templates.new')" x-data x-on:hidden-bs-modal.dot="$wire.resetTemplateForm()">
        <form id="xmGatewayTemplateForm" wire:submit="saveTemplate" novalidate>
            <x-field name="templateForm.title" :label="__('admin_gateways.templates.fields.title')" wire:model.blur="templateForm.title" maxlength="80" autocomplete="off" />
            <x-field name="templateForm.body" type="textarea" :label="__('admin_gateways.templates.fields.body')" wire:model.blur="templateForm.body" rows="4" maxlength="500" />
            <x-field name="templateForm.is_active" type="switch" :label="__('admin_gateways.templates.fields.is_active')" wire:model="templateForm.is_active" />
        </form>
        <x-slot:footer>
            <button type="submit" form="xmGatewayTemplateForm" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveTemplate"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ __('admin_content.save') }}</button>
        </x-slot:footer>
    </x-admin.modal>

    <x-admin.modal id="xmGatewayRoom" size="md" heading="h2" dirty-guard :title="$roomId ? __('admin_gateways.rooms.edit_title') : __('admin_gateways.rooms.new')" x-data x-on:hidden-bs-modal.dot="$wire.resetRoomForm()">
        <form id="xmGatewayRoomForm" wire:submit="saveRoom" novalidate>
            <x-field name="roomForm.name" :label="__('admin_gateways.rooms.fields.name')" wire:model.blur="roomForm.name" maxlength="120" autocomplete="off" />
            <x-field name="roomForm.description" optional :label="__('admin_gateways.rooms.fields.description')" wire:model.blur="roomForm.description" maxlength="255" autocomplete="off" />
            <x-field name="roomForm.max_members" type="number" :label="__('admin_gateways.rooms.fields.max_members')" wire:model.blur="roomForm.max_members" inputmode="numeric" :hint="__('admin_gateways.rooms.max_help')" />
            <x-field name="roomForm.is_moderated" type="switch" :label="__('admin_gateways.rooms.fields.is_moderated')" wire:model="roomForm.is_moderated" />
            <x-field name="roomForm.allow_anonymous" type="switch" :label="__('admin_gateways.rooms.fields.allow_anonymous')" wire:model="roomForm.allow_anonymous" />
            <x-field name="roomForm.is_active" type="switch" :label="__('admin_gateways.rooms.fields.is_active')" wire:model="roomForm.is_active" />
        </form>
        <x-slot:footer>
            <button type="submit" form="xmGatewayRoomForm" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveRoom"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ __('admin_content.save') }}</button>
        </x-slot:footer>
    </x-admin.modal>
</div>
