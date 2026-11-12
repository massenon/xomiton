<?php

declare(strict_types=1);

namespace App\Application\Content\Proposals;

use App\Application\Community\UniqueSlug;
use App\Application\Shared\MediaUploader;
use App\Domain\Content\Exceptions\ContentRefused;
use App\Enums\ContentStatus;
use App\Enums\ProposalStatus;
use App\Models\Resource;
use App\Models\ResourceProposal;
use App\Models\User;
use App\Notifications\TeamNotice;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * File de validation des ressources proposées (P18-12, `/admin/contenu/propositions`, `resources.manage`) :
 *  - accepter : crée une ressource **en brouillon** dans /admin/contenu (titre, type, résumé, catégorie, lien, fichier
 *    PDF repris tel quel sur le disque privé), avec, si l'auteur·rice l'a accepté et que l'équipe le garde, une ligne de
 *    crédit (pseudonyme) dans le corps ; l'équipe relit, complète et publie ensuite comme toute ressource ;
 *  - refuser : motif obligatoire écrit pour l'auteur·rice ; le fichier PDF éventuel est supprimé (rien n'est gardé
 *    d'une proposition refusée hors de son texte).
 * L'auteur·rice est notifié·e (TeamNotice) ; journal `resource_proposals.accepted|refused` sans texte libre.
 */
final class ReviewResourceProposal
{
    public const REASON_MAX = 500;

    public function __construct(private readonly MediaUploader $uploader) {}

    /**
     * @throws ContentRefused
     */
    public function accept(ResourceProposal $proposal, User $actor, bool $credit): Resource
    {
        $this->assertPending($proposal);
        $author = $proposal->user_id !== null ? User::query()->find($proposal->user_id) : null;
        $credit = $credit && $proposal->credit_author && $author !== null;

        $resource = DB::transaction(function () use ($proposal, $actor, $credit, $author): Resource {
            $resource = Resource::query()->create([
                'title' => (string) $proposal->title,
                'slug' => UniqueSlug::for(Resource::class, (string) $proposal->title, 120),
                'kind' => $proposal->resourceKind(),
                'summary' => (string) $proposal->summary,
                'category_id' => $proposal->category_id,
                'external_url' => $proposal->external_url,
                'file_path' => $proposal->file_path,
                'file_size' => $proposal->file_size,
                'body' => $credit ? '<p>'.e((string) __('admin_content.proposals.credit_line', ['name' => (string) $author?->pseudo])).'</p>' : null,
                'status' => ContentStatus::Draft,
                'created_by' => $actor->getKey(),
            ]);

            $proposal->forceFill([
                'status' => ProposalStatus::Accepted->value,
                'resource_id' => $resource->getKey(),
                'reviewed_by' => $actor->getKey(),
                'reviewed_at' => now(),
            ])->save();

            return $resource;
        });

        Audit::log('resource_proposals.accepted', $proposal, 'resource='.$resource->getKey(), $actor);
        $this->notify($proposal, new TeamNotice(TeamNotice::PROPOSAL_ACCEPTED, (string) $proposal->title));

        return $resource;
    }

    /**
     * @throws ContentRefused
     */
    public function refuse(ResourceProposal $proposal, string $reason, User $actor): void
    {
        $this->assertPending($proposal);
        $path = $proposal->file_path;

        $proposal->forceFill([
            'status' => ProposalStatus::Refused->value,
            'refusal_reason' => trim($reason),
            'file_path' => null,
            'file_size' => null,
            'reviewed_by' => $actor->getKey(),
            'reviewed_at' => now(),
        ])->save();

        if (is_string($path) && $path !== '') {
            Storage::disk(MediaUploader::privateDisk())->delete($path);
        }

        Audit::log('resource_proposals.refused', $proposal, null, $actor);
        $this->notify($proposal, new TeamNotice(TeamNotice::PROPOSAL_REFUSED, (string) $proposal->title, trim($reason)));
    }

    /** Lien signé (10 min) vers le PDF proposé, pour la relecture par l'équipe. */
    public function fileUrl(ResourceProposal $proposal): ?string
    {
        $path = $proposal->file_path;

        return is_string($path) && $path !== '' ? $this->uploader->signedUrl(MediaUploader::privateDisk(), $path) : null;
    }

    /**
     * @throws ContentRefused
     */
    private function assertPending(ResourceProposal $proposal): void
    {
        if (! $proposal->isPending()) {
            throw new ContentRefused('proposal_decided');
        }
    }

    private function notify(ResourceProposal $proposal, TeamNotice $notice): void
    {
        // Compte relu en entier : l'écran charge la relation réduite (id, pseudo), sans le statut.
        $author = $proposal->user_id !== null ? User::query()->find($proposal->user_id) : null;

        if ($author === null || ! $author->isActive()) {
            return;
        }

        try {
            $author->notify($notice);
        } catch (Throwable $exception) {
            Log::warning('Ressources proposées : notification non envoyée.', ['proposal_id' => $proposal->getKey(), 'error' => $exception->getMessage()]);
        }
    }
}
