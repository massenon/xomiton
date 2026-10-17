{{-- Monté en permanence, ouvert par Alpine : $dispatch('open-modal', 'id'). Jamais derrière un @if.
     Nécessite le plugin Alpine Focus (x-trap) : fourni avec Livewire 3. --}}
@props(['id', 'title', 'type' => 'confirm', 'confirmWord' => null, 'dirtyGuard' => false])
<div x-data="{ open: false, dirty: false, word: '', opener: null,
               close() { if (this.dirty && ! window.confirm(@js(__('Abandonner les modifications ?')))) return; this.open = false; this.dirty = false; this.$nextTick(() => this.opener && this.opener.focus()); } }"
     x-on:open-modal.window="if ($event.detail === '{{ $id }}') { open = true; opener = document.activeElement }"
     x-on:keydown.escape.window="if (open) close()"
     x-effect="document.body.classList.toggle('overflow-hidden', open)"
     @if ($dirtyGuard) x-on:input="dirty = true" @endif
     x-cloak>
    <div x-show="open" class="position-fixed top-0 start-0 w-100 h-100" style="background:rgb(14 16 32 / .6);z-index:1050" @click="close()" aria-hidden="true"></div>
    <div x-show="open" x-trap.noscroll="open" id="{{ $id }}" role="{{ $type === 'delete' ? 'alertdialog' : 'dialog' }}" aria-modal="true" aria-labelledby="{{ $id }}-title"
         class="position-fixed top-50 start-50 translate-middle xm-card xm-modal p-0" style="z-index:1055;width:min(560px,calc(100vw - 32px));max-height:calc(100vh - 32px)">
        <div class="d-flex align-items-center justify-content-between p-4 border-bottom">
            <h2 id="{{ $id }}-title" class="h5 mb-0">{{ $title }}</h2>
            <button type="button" class="btn-close" @click="close()" aria-label="{{ __('Fermer') }}"></button>
        </div>
        <div class="p-4 overflow-auto">
            {{ $slot }}
            @if ($type === 'delete' && $confirmWord)
                <label class="form-label mt-3" for="{{ $id }}-word">{{ __('Saisis « :w » pour confirmer', ['w' => $confirmWord]) }}</label>
                <input id="{{ $id }}-word" class="form-control" x-model="word" autocomplete="off">
            @endif
        </div>
        <div class="d-flex justify-content-between gap-2 p-4 border-top">
            <button type="button" class="btn btn-light" @click="close()">{{ __('Annuler') }}</button>
            <span @if ($type === 'delete' && $confirmWord) x-bind:class="word !== @js($confirmWord) && 'pe-none opacity-50'" x-bind:aria-disabled="word !== @js($confirmWord)" @endif>{{ $footer ?? '' }}</span>
        </div>
    </div>
</div>
