<?php

declare(strict_types=1);

namespace App\Application\Sync\Push\Handlers;

use App\Application\Cases\CreateCase;
use App\Application\Cases\PeerSpace;
use App\Application\Sync\Conflicts\ApplyCaseChanges;
use App\Application\Sync\Pull\Collections\CasesCollection;
use App\Application\Sync\Push\ItemHandler;
use App\Application\Sync\Push\ItemOutcome;
use App\Application\Sync\Push\ItemRejected;
use App\Application\Sync\Push\PushContext;
use App\Application\Sync\Push\PushItem;
use App\Enums\AlertSource;
use App\Models\CaseRecord;
use Illuminate\Support\Facades\Gate;

/**
 * `collecte` (dossier VBG / VDH, P9-04, P9-06) : `op: create` → CreateCase (P9-01, P9-02 : code `VBG-0000` attribué
 * par le serveur, `client_uuid` = uuid de l'élément, source `sync-offline`, appareil rattaché ; rejeu → `duplicate`) ;
 * `op: update` → ApplyCaseChanges (fusion par champ, conflit 409 enregistré ; état courant du dossier rendu dans
 * `case`, pour que l'appareil reprenne la valeur du serveur même quand rien n'est appliqué). Mêmes gardes que l'API
 * `pair/` : espace pair actif (PeerSpace, RG-03 : un compte pair en attente de validation ne collecte pas) et
 * `cases.create`, sinon 403. Avertissements CaseQuality dans `warnings`.
 */
final class CaseItemHandler implements ItemHandler
{
    public function __construct(
        private readonly CreateCase $create,
        private readonly ApplyCaseChanges $apply,
    ) {}

    public function handle(PushItem $item, PushContext $context): ItemOutcome
    {
        if (! PeerSpace::allows($context->user)) {
            throw ItemRejected::forbidden();
        }

        return $item->isUpdate() ? $this->update($item, $context) : $this->create($item, $context);
    }

    private function create(PushItem $item, PushContext $context): ItemOutcome
    {
        if (Gate::forUser($context->user)->denies('create', CaseRecord::class)) {
            throw ItemRejected::forbidden();
        }

        $written = ($this->create)($context->user, $context->decoder->caseInput($item), $item->uuid, AlertSource::SyncOffline, $context->deviceId());

        return $written->created
            ? ItemOutcome::created($item, $written->case, $written->warningsArray())
            : ItemOutcome::duplicate($item, $written->case);
    }

    private function update(PushItem $item, PushContext $context): ItemOutcome
    {
        $result = ($this->apply)($context->user, $context->device, $context->decoder->caseChanges($item));
        $record = CasesCollection::row($result->case);

        return $result->hasConflicts()
            ? ItemOutcome::conflict($item, $result->case, $result->conflicts, $result->applied, $result->kept, $record, $result->warningsArray())
            : ItemOutcome::updated($item, $result->case, $result->applied, $result->kept, $record, $result->warningsArray());
    }
}
