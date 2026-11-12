<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Events;

use App\Application\Community\Events\EventApproval;
use App\Application\Community\Events\ReviewEvent;
use App\Domain\Content\Exceptions\ContentRefused;
use App\Enums\ContentStatus;
use App\Livewire\Attributes\SafeUrl;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Event;
use App\Models\User;
use App\Support\LocaleFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Calendrier des événements `/admin/evenements` (P18-14) : mois choisi (`?mois=2026-11`, précédent / suivant), événements
 * du mois groupés par semaine (lundi → dimanche, heure du Bénin), type, statut et participant·es ; file d'approbation
 * (événements « en attente » : approuver / refuser avec motif) ; réglage de la file d'approbation (coupée par défaut).
 * Lecture : `posts.moderate` (route) ; approuver / refuser : `moderation.manage` ; réglage : `events.manage` — revérifiées
 * à chaque action, l'événement ouvert pour un refus est verrouillé (`#[Locked]`).
 */
final class Index extends Component
{
    use InteractsWithUi;

    #[SafeUrl(as: 'mois', except: '')]
    public string $month = '';

    #[Locked]
    public ?int $refusingId = null;

    public string $reason = '';

    public function mount(): void
    {
        abort_unless($this->actor()->can('posts.moderate'), 403);

        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->month) !== 1) {
            $this->month = '';
        }
    }

    public function shift(int $months): void
    {
        $this->month = $this->monthStart()->addMonthsNoOverflow($months < 0 ? -1 : 1)->format('Y-m');
        unset($this->events);
    }

    public function today(): void
    {
        $this->month = '';
        unset($this->events);
    }

    /** @return Collection<int, Event> */
    #[Computed]
    public function events(): Collection
    {
        $start = $this->monthStart();

        return Event::query()->with(['organizer:id,pseudo', 'group:id,name'])
            ->whereBetween('starts_at', [$start->utc(), $start->endOfMonth()->utc()])
            ->orderBy('starts_at')->orderBy('id')->get();
    }

    /** @return Collection<int, Event> */
    #[Computed]
    public function pending(): Collection
    {
        return Event::query()->with(['organizer:id,pseudo', 'group:id,name'])
            ->where('status', ContentStatus::Pending->value)->orderBy('starts_at')->limit(50)->get();
    }

    public function approve(int $id, ReviewEvent $review): void
    {
        abort_unless($this->actor()->can('moderation.manage'), 403);
        $event = Event::query()->findOrFail($id);

        try {
            $review->approve($event, $this->actor());
        } catch (ContentRefused $refused) {
            $this->toast($refused->userMessage(), 'error');

            return;
        }

        $this->toast((string) __('admin_events.toasts.approved', ['title' => $event->title]));
        unset($this->events, $this->pending);
    }

    public function askRefuse(int $id): void
    {
        abort_unless($this->actor()->can('moderation.manage'), 403);
        $this->refusingId = (int) Event::query()->findOrFail($id)->getKey();
        $this->reason = '';
        $this->resetErrorBag();
        $this->openModal('xmEventRefuse');
    }

    public function refuse(ReviewEvent $review): void
    {
        abort_unless($this->actor()->can('moderation.manage'), 403);
        abort_if($this->refusingId === null, 404);
        $this->validate(
            ['reason' => ['required', 'string', 'min:5', 'max:'.ReviewEvent::REASON_MAX]],
            [],
            ['reason' => (string) __('admin_events.fields.reason')],
        );
        $event = Event::query()->findOrFail($this->refusingId);

        try {
            $review->refuse($event, $this->reason, $this->actor());
        } catch (ContentRefused $refused) {
            $this->toast($refused->userMessage(), 'error');

            return;
        }

        $this->closeModal('xmEventRefuse');
        $this->reset('refusingId', 'reason');
        $this->toast((string) __('admin_events.toasts.refused'), 'warning');
        unset($this->events, $this->pending);
    }

    public function toggleApproval(): void
    {
        abort_unless($this->actor()->can('events.manage'), 403);
        $required = ! EventApproval::required();
        EventApproval::set($required, $this->actor());
        $this->toast((string) __($required ? 'admin_events.toasts.approval_on' : 'admin_events.toasts.approval_off'));
    }

    public function render(): View
    {
        $start = $this->monthStart();
        $format = LocaleFormatter::make(null, self::timezone());

        return view('livewire.admin.events.index', [
            'weeks' => $this->weeks($start),
            'monthLabel' => $format->monthYear($start),
            'format' => $format,
            'approvalRequired' => EventApproval::required(),
            'canDecide' => $this->actor()->can('moderation.manage'),
            'canConfigure' => $this->actor()->can('events.manage'),
        ])->layout('components.layouts.admin', [
            'title' => __('admin_events.title'),
            'adminPage' => 'evenements',
        ]);
    }

    /**
     * Semaines (lundi → dimanche) qui touchent le mois, chacune avec ses événements.
     *
     * @return list<array{start: CarbonImmutable, end: CarbonImmutable, events: list<Event>}>
     */
    private function weeks(CarbonImmutable $start): array
    {
        $tz = self::timezone();
        $weeks = [];
        $all = $this->events();
        $cursor = $start->startOfWeek(CarbonImmutable::MONDAY);
        $last = $start->endOfMonth();

        while ($cursor->lessThanOrEqualTo($last)) {
            $end = $cursor->endOfWeek(CarbonImmutable::SUNDAY);
            $events = $all->filter(static function (Event $event) use ($cursor, $end, $tz): bool {
                $at = CarbonImmutable::parse((string) $event->getRawOriginal('starts_at'), 'UTC')->setTimezone($tz);

                return $at->betweenIncluded($cursor, $end);
            })->values()->all();
            $weeks[] = ['start' => $cursor, 'end' => $end, 'events' => $events];
            $cursor = $cursor->addWeek();
        }

        return $weeks;
    }

    private function monthStart(): CarbonImmutable
    {
        $tz = self::timezone();

        return $this->month !== ''
            ? CarbonImmutable::createFromFormat('Y-m-d H:i:s', $this->month.'-01 00:00:00', $tz) ?: CarbonImmutable::now($tz)->startOfMonth()
            : CarbonImmutable::now($tz)->startOfMonth();
    }

    private static function timezone(): string
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
