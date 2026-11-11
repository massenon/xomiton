<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Account\ProgressiveProfile;
use App\Enums\AgeRange;
use App\Enums\MemberType;
use App\Enums\ProfileVisibility;
use App\Support\MediaUrl;
use Database\Factories\ProfileFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
    /** @use HasFactory<ProfileFactory> */
    use HasFactory;

    /** Couverture du prototype (`partials/profile-head.php`) quand la personne n'en a pas choisi. */
    public const DEFAULT_COVER = 'assets/images/xomiton/profile-cover.jpg';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'pronouns',
        'age_range',
        'member_type',
        'gender_identity',
        'orientation',
        'bio',
        'completion',
        'avatar_path',
        'cover_path',
        'discreet_mode',
        'trusted_contact_phone',
        'trusted_contact_name',
        'website',
        'visibility',
        'show_zone',
        'searchable',
        'ally_status',
        'interests',
        'shared_fields',
        'progressive_answered',
    ];

    /**
     * Données sensibles : jamais sérialisées par défaut (RG-02, consentement sensitive_data).
     *
     * @var list<string>
     */
    protected $hidden = [
        'gender_identity',
        'orientation',
        'trusted_contact_phone',
        'trusted_contact_name',
        'ally_status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'age_range' => AgeRange::class,
            'member_type' => MemberType::class,
            'visibility' => ProfileVisibility::class,
            'gender_identity' => 'encrypted',
            'orientation' => 'encrypted',
            'trusted_contact_phone' => 'encrypted',
            // Q10 c (P6-06) : allié·e / personne ressource, chiffré comme les autres données d'identité.
            'ally_status' => 'encrypted',
            'interests' => 'array',
            // P8-07, P8-08 : champs sensibles montrés par choix explicite, groupes du profil progressif traités.
            'shared_fields' => 'array',
            'progressive_answered' => 'array',
            'completion' => 'integer',
            'discreet_mode' => 'boolean',
            'show_zone' => 'boolean',
            'searchable' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<Profile>  $query
     */
    public function scopePeers(Builder $query): void
    {
        $query->where('member_type', MemberType::Peer);
    }

    /**
     * Profils visibles par un·e membre connecté·e (public ou communauté), hors profils réservés
     * aux abonné·es ou privés.
     *
     * @param  Builder<Profile>  $query
     */
    public function scopeVisibleToCommunity(Builder $query): void
    {
        $query->whereIn('visibility', [ProfileVisibility::Public, ProfileVisibility::Community]);
    }

    /**
     * Profils visibles sans connexion (visibilité « tout le monde »).
     *
     * @param  Builder<Profile>  $query
     */
    public function scopePubliclyVisible(Builder $query): void
    {
        $query->where('visibility', ProfileVisibility::Public);
    }

    /**
     * Profils proposés dans les suggestions « Qui suivre » et la recherche.
     *
     * @param  Builder<Profile>  $query
     */
    public function scopeSearchable(Builder $query): void
    {
        $query->where('searchable', true);
    }

    /**
     * Note des groupes du profil progressif comme traités (réponse ou « Je préfère ne pas le dire », P8-08) ; le bandeau
     * ne les repropose plus. N'enregistre pas.
     */
    public function markAnswered(string ...$groups): void
    {
        $this->progressive_answered = ProgressiveProfile::normalize([...(array) ($this->progressive_answered ?? []), ...$groups]) ?: null;
    }

    /**
     * URL de la couverture (disque public, P8-07), sinon celle du prototype.
     */
    public function coverUrl(): string
    {
        return MediaUrl::resolve($this->cover_path, self::DEFAULT_COVER);
    }

    public function isPeer(): bool
    {
        return $this->member_type === MemberType::Peer;
    }
}
