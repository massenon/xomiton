<?php

declare(strict_types=1);

namespace App\Application\Content\Proposals;

use App\Application\Feed\BadWordFilter;
use App\Application\Shared\MediaUploader;
use App\Enums\ProposalStatus;
use App\Enums\ResourceKind;
use App\Models\ResourceProposal;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * Proposer une ressource (P18-12) : la proposition est enregistrée au statut `proposee`, **jamais publique**, et attend la
 * file de validation de `/admin/contenu/propositions`. Filtre de mots du fil sur le titre et le résumé (terme bloquant →
 * 422 neutre). Fichier PDF sur le disque privé (MediaUploader, collection `documents` : type réel lu dans le contenu) ;
 * lien `https` seulement. Journal `resource_proposals.submitted` sans texte. Limiteur `resource_proposal` par compte
 * (contrôleur).
 *
 * @phpstan-type ProposalInput array{title: string, kind: string, summary: string, category_id?: int|null, external_url?: string|null, credit_author?: bool}
 */
final class ProposeResource
{
    public function __construct(
        private readonly BadWordFilter $filter,
        private readonly MediaUploader $uploader,
    ) {}

    /**
     * @param  ProposalInput  $data
     */
    public function __invoke(User $author, array $data, ?UploadedFile $file): ResourceProposal
    {
        $this->filter->assertAllowed(['title' => $data['title'], 'summary' => $data['summary']], 'resource_proposal');

        $kind = ResourceKind::from($data['kind']);
        $stored = $file !== null && $kind === ResourceKind::Pdf ? $this->uploader->upload($file, 'documents') : null;

        try {
            $proposal = ResourceProposal::query()->create([
                'user_id' => $author->getKey(),
                'title' => trim($data['title']),
                'kind' => $kind->value,
                'summary' => trim($data['summary']),
                'category_id' => $data['category_id'] ?? null,
                'external_url' => self::clean($data['external_url'] ?? null),
                'file_path' => $stored?->path,
                'file_size' => $stored?->size,
                'credit_author' => (bool) ($data['credit_author'] ?? false),
                'status' => ProposalStatus::Proposed->value,
            ]);
        } catch (Throwable $error) {
            if ($stored !== null) {
                $this->uploader->delete($stored);
            }

            throw $error;
        }

        Audit::log('resource_proposals.submitted', $proposal, 'kind='.$kind->value, $author);

        return $proposal;
    }

    private static function clean(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;

        return $value === null || $value === '' ? null : $value;
    }
}
