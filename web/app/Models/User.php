<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserStatus;
use App\Support\MediaUrl;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * Image affichée quand le profil n'a pas d'avatar téléversé (fichier de public/).
     */
    public const DEFAULT_AVATAR = 'assets/images/xomiton/avatar1.png';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'pseudo',
        'name',
        'email',
        'phone',
        'password',
        'status',
        'zone_id',
        'commune_id',
        'email_verified_at',
        'last_login_at',
        'last_seen_at',
        'suspended_at',
        'suspension_reason',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        // name et phone ne sont jamais publics (RG-02) : seul le pseudo est affiché.
        'name',
        'phone',
        'password',
        'remember_token',
        'suspension_reason',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => UserStatus::class,
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'suspended_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // -- Identité (lot 2) ---------------------------------------------------------------------

    /**
     * @return HasOne<Profile, $this>
     */
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    /**
     * @return HasOne<UserSetting, $this>
     */
    public function settings(): HasOne
    {
        return $this->hasOne(UserSetting::class);
    }

    /**
     * @return BelongsTo<Zone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * @return BelongsTo<Commune, $this>
     */
    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }

    /**
     * @return HasMany<Consent, $this>
     */
    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }

    /**
     * Blocages émis par ce compte.
     *
     * @return HasMany<UserBlock, $this>
     */
    public function blocks(): HasMany
    {
        return $this->hasMany(UserBlock::class);
    }

    /**
     * Comptes bloqués par ce compte.
     *
     * @return BelongsToMany<User, $this>
     */
    public function blockedUsers(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'user_blocks', 'user_id', 'blocked_user_id')
            ->withPivot(['reason', 'created_at']);
    }

    /**
     * Comptes qui ont bloqué ce compte.
     *
     * @return BelongsToMany<User, $this>
     */
    public function blockedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'user_blocks', 'blocked_user_id', 'user_id')
            ->withPivot(['reason', 'created_at']);
    }

    // -- Contenus (lot 3) ---------------------------------------------------------------------

    /**
     * Fiches thématiques rédigées par ce compte (`thematiques.created_by`).
     *
     * @return HasMany<Thematique, $this>
     */
    public function thematiques(): HasMany
    {
        return $this->hasMany(Thematique::class, 'created_by');
    }

    /**
     * @return HasMany<AssistantConversation, $this>
     */
    public function assistantConversations(): HasMany
    {
        return $this->hasMany(AssistantConversation::class);
    }

    // -- Réseau social (lot 4) ----------------------------------------------------------------

    /**
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /**
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Publications aimées (`post_likes`).
     *
     * @return BelongsToMany<Post, $this>
     */
    public function likedPosts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_likes')->withPivot('created_at');
    }

    /**
     * Publications enregistrées (`post_saves`).
     *
     * @return BelongsToMany<Post, $this>
     */
    public function savedPosts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_saves')->withPivot('created_at');
    }

    /**
     * Comptes suivis par ce compte (`follows.follower_id` = moi).
     *
     * @return BelongsToMany<User, $this>
     */
    public function following(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'follows', 'follower_id', 'followed_id')->withPivot('created_at');
    }

    /**
     * Abonné·es de ce compte (`follows.followed_id` = moi).
     *
     * @return BelongsToMany<User, $this>
     */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'follows', 'followed_id', 'follower_id')->withPivot('created_at');
    }

    /**
     * @return HasMany<Invitation, $this>
     */
    public function invitationsSent(): HasMany
    {
        return $this->hasMany(Invitation::class, 'from_user_id');
    }

    /**
     * @return HasMany<Invitation, $this>
     */
    public function invitationsReceived(): HasMany
    {
        return $this->hasMany(Invitation::class, 'to_user_id');
    }

    /**
     * @return HasMany<GroupMember, $this>
     */
    public function groupMemberships(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    /**
     * Groupes rejoints, quel que soit le statut d'adhésion (`group_members`).
     *
     * @return BelongsToMany<Group, $this>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_members')
            ->withPivot(['role', 'status', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * Pages suivies (`page_followers`).
     *
     * @return BelongsToMany<Page, $this>
     */
    public function followedPages(): BelongsToMany
    {
        return $this->belongsToMany(Page::class, 'page_followers')->withPivot('created_at');
    }

    /**
     * Albums de la médiathèque du profil.
     *
     * @return HasMany<Album, $this>
     */
    public function albums(): HasMany
    {
        return $this->hasMany(Album::class);
    }

    // -- Communauté (lot 5) -------------------------------------------------------------------

    /**
     * Événements organisés par ce compte (`events.organizer_id`).
     *
     * @return HasMany<Event, $this>
     */
    public function organizedEvents(): HasMany
    {
        return $this->hasMany(Event::class, 'organizer_id');
    }

    /**
     * @return HasMany<EventRsvp, $this>
     */
    public function eventRsvps(): HasMany
    {
        return $this->hasMany(EventRsvp::class);
    }

    /**
     * @return HasMany<ForumTopic, $this>
     */
    public function forumTopics(): HasMany
    {
        return $this->hasMany(ForumTopic::class);
    }

    /**
     * Conversations auxquelles ce compte participe (`conversation_participants`), y compris celles quittées.
     *
     * @return BelongsToMany<Conversation, $this>
     */
    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_participants')
            ->withPivot(['role', 'last_read_message_id', 'muted_until', 'left_at'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    // -- Alertes et annuaire (lot 6) ----------------------------------------------------------

    /**
     * Alertes émises par ce compte (`alerts.user_id`, null si anonyme).
     *
     * @return HasMany<Alert, $this>
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    /**
     * Alertes prises en charge par ce compte (`alerts.acknowledged_by`).
     *
     * @return HasMany<Alert, $this>
     */
    public function acknowledgedAlerts(): HasMany
    {
        return $this->hasMany(Alert::class, 'acknowledged_by');
    }

    /**
     * @return HasMany<SyncDevice, $this>
     */
    public function syncDevices(): HasMany
    {
        return $this->hasMany(SyncDevice::class);
    }

    /**
     * Fiche d'annuaire liée à ce compte (alertes « routées vers moi »).
     *
     * @return HasOne<DirectoryContact, $this>
     */
    public function directoryContact(): HasOne
    {
        return $this->hasOne(DirectoryContact::class);
    }

    // -- Dossiers (lot 7) ---------------------------------------------------------------------

    /**
     * Dossiers collectés par ce compte pair (`cases.collected_by`).
     *
     * @return HasMany<CaseRecord, $this>
     */
    public function collectedCases(): HasMany
    {
        return $this->hasMany(CaseRecord::class, 'collected_by');
    }

    // -- Communication et plateforme (lots 10 et 11) ------------------------------------------

    /**
     * @return HasMany<AuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * @return HasMany<PushSubscription, $this>
     */
    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    /**
     * Abonnement à la lettre d'information rattaché à ce compte.
     *
     * @return HasOne<NewsletterSubscriber, $this>
     */
    public function newsletterSubscriber(): HasOne
    {
        return $this->hasOne(NewsletterSubscriber::class);
    }

    // -- Scopes et état -----------------------------------------------------------------------

    /**
     * @param  Builder<User>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', UserStatus::Active);
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', UserStatus::Pending);
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeSuspended(Builder $query): void
    {
        $query->where('status', UserStatus::Suspended);
    }

    /**
     * Comptes d'une zone (département) donnée.
     *
     * @param  Builder<User>  $query
     */
    public function scopeInZone(Builder $query, int $zoneId): void
    {
        $query->where('zone_id', $zoneId);
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function isSuspended(): bool
    {
        return $this->status === UserStatus::Suspended;
    }

    // -- Anonymisation (DATABASE_ENGINEERING.md §6 : compte supprimé, logique 30 j puis anonymisation) ----

    /** Pseudo d'un compte anonymisé : `membre-supprimé-<id>`. */
    public const ANONYMIZED_PSEUDO_PREFIX = 'membre-supprimé-';

    /** Domaine réservé (RFC 2606) de l'e-mail d'un compte anonymisé : `supprime-<id>@anonyme.invalid`. */
    public const ANONYMIZED_EMAIL_DOMAIN = 'anonyme.invalid';

    public static function anonymizedPseudo(int $id): string
    {
        return self::ANONYMIZED_PSEUDO_PREFIX.$id;
    }

    public static function anonymizedEmail(int $id): string
    {
        return 'supprime-'.$id.'@'.self::ANONYMIZED_EMAIL_DOMAIN;
    }

    /**
     * L'e-mail d'anonymisation sert de marqueur : `accounts:anonymize` ne repasse jamais sur un compte.
     */
    public function isAnonymized(): bool
    {
        return str_ends_with(mb_strtolower((string) $this->email), '@'.self::ANONYMIZED_EMAIL_DOMAIN);
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeAnonymized(Builder $query): void
    {
        $query->where('email', 'like', '%@'.self::ANONYMIZED_EMAIL_DOMAIN);
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeNotAnonymized(Builder $query): void
    {
        $query->where('email', 'not like', '%@'.self::ANONYMIZED_EMAIL_DOMAIN);
    }

    /**
     * URL de l'avatar : fichier du profil (disque public ou image de démonstration de public/),
     * ou image par défaut si le fichier manque — jamais de 404.
     *
     * @return Attribute<string, never>
     */
    protected function avatarUrl(): Attribute
    {
        return Attribute::get(fn (): string => MediaUrl::resolve($this->profile?->avatar_path, self::DEFAULT_AVATAR));
    }
}
