{{--
    Modale Bootstrap (prototype partials/modals.php, admin/emails.php #mDelete) : montée en permanence (jamais derrière un @if),
    ouverte par data-bs-toggle="modal" data-bs-target="#id" ou bootstrap.Modal. Focus piégé, Échap et aria-modal gérés par Bootstrap ;
    retour du focus au déclencheur, mot de confirmation et garde « formulaire modifié » par resources/js/public/xomiton-ui.js.
    Props :
      id, title   : obligatoires (aria-labelledby = {id}Title)
      type        : confirm | form | delete | fullscreen
                    delete → en-tête rouge avec icône, bouton « Supprimer définitivement » (btn-danger), mot de confirmation facultatif
                    fullscreen → modal-fullscreen-sm-down (plein écran sur mobile)
      size        : sm | md | lg | xl (lg : CRUD ; xl : éditeurs riches, + modal-fullscreen-lg-down)
      scrollable  : corps défilant et pied fixe (défaut true) ; centered : modal-dialog-centered (défaut true)
      form        : URL d'envoi → le contenu est un <form method="post"> avec @csrf ; method : POST | PUT | PATCH | DELETE
      action      : libellé du bouton principal à droite (btn-primary ; btn-danger pour delete ; submit si form)
      confirmWord : (delete) mot à saisir pour activer le bouton principal
      dirtyGuard  : confirmation avant fermeture si un champ a été modifié (+ backdrop statique)
      static      : data-bs-backdrop="static"
      heading     : niveau du titre (h2 à h6, défaut h5 comme le prototype) ; h2 quand la page a déjà des h3 (axe heading-order)
    Slots : défaut (corps), footer (boutons supplémentaires ; « Annuler » reste à gauche, l'action principale à droite).
--}}
@props(['id', 'title', 'type' => 'confirm', 'size' => 'md', 'scrollable' => true, 'centered' => true, 'form' => null, 'method' => 'POST', 'action' => null, 'confirmWord' => null, 'dirtyGuard' => false, 'static' => false, 'heading' => 'h5'])
@php
    $isDelete = $type === 'delete';
    $method = strtoupper($method);
    $spoof = ! in_array($method, ['GET', 'POST'], true);
    $contentTag = $form ? 'form' : 'div';
    $actionLabel = $action ?? ($isDelete && ! isset($footer) ? __('components.modal.delete_action') : null);
    $contentAttrs = $form ? ' method="'.($method === 'GET' ? 'get' : 'post').'" action="'.e($form).'"' : '';
    $heading = in_array($heading, ['h2', 'h3', 'h4', 'h5', 'h6'], true) ? $heading : 'h5';
@endphp
<div {{ $attributes->class(['modal fade', 'xm-modal--delete' => $isDelete]) }} id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}Title" aria-hidden="true"
     @if ($static || $dirtyGuard) data-bs-backdrop="static" @endif
     @if ($dirtyGuard) data-xm-dirty-guard="{{ __('components.modal.dirty_guard') }}" @endif
     @if ($confirmWord) data-xm-confirm-word="{{ $confirmWord }}" @endif>
    <div @class(['modal-dialog', 'modal-dialog-centered' => $centered, 'modal-dialog-scrollable' => $scrollable, 'modal-sm' => $size === 'sm', 'modal-lg' => $size === 'lg', 'modal-xl modal-fullscreen-lg-down' => $size === 'xl', 'modal-fullscreen-sm-down' => $type === 'fullscreen'])>
        <{{ $contentTag }} class="modal-content"{!! $contentAttrs !!}>
            @if ($form && $method !== 'GET')
                @csrf
                @if ($spoof) @method($method) @endif
            @endif
            <div @class(['modal-header', 'border-0' => $isDelete])>
                <{{ $heading }} @class(['modal-title', 'h5' => $heading !== 'h5', 'text-danger' => $isDelete]) id="{{ $id }}Title">@if ($isDelete)<i class="bi bi-exclamation-triangle me-2" aria-hidden="true"></i>@endif{{ $title }}</{{ $heading }}>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('common.close') }}"></button>
            </div>
            <div @class(['modal-body', 'pt-0' => $isDelete])>
                {{ $slot }}
                @if ($isDelete && $confirmWord)
                    <div class="alert alert-danger small mt-3 mb-0">
                        <label class="form-label mb-1" for="{{ $id }}Word">{{ __('components.modal.confirm_word_label') }} <code>{{ $confirmWord }}</code></label>
                        <input class="form-control form-control-sm font-monospace" id="{{ $id }}Word" name="confirm_word" autocomplete="off" required data-xm-confirm-input>
                    </div>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('common.cancel') }}</button>
                @isset($footer){{ $footer }}@endisset
                @if ($actionLabel)
                    <button type="{{ $form ? 'submit' : 'button' }}" class="btn {{ $isDelete ? 'btn-danger' : 'btn-primary' }}" data-xm-modal-action{!! $confirmWord ? ' disabled' : '' !!}>@if ($isDelete)<i class="bi bi-trash me-1" aria-hidden="true"></i>@endif{{ $actionLabel }}</button>
                @endif
            </div>
        </{{ $contentTag }}>
    </div>
</div>
