{{-- Déclenchement : $dispatch('toast', { type: 'success', message: '…' }). Jamais l'unique porteur d'une information. --}}
<div x-data="{ items: [], push(d) { const t = { id: Date.now() + Math.random(), ...d }; this.items.push(t); setTimeout(() => this.drop(t.id), 6000); }, drop(id) { this.items = this.items.filter(i => i.id !== id); } }"
     x-on:toast.window="push($event.detail)" class="position-fixed bottom-0 start-50 translate-middle-x p-3" style="z-index:1090;width:min(420px,100vw)">
    <template x-for="t in items" x-bind:key="t.id">
        <div class="alert d-flex align-items-start gap-2 shadow" x-bind:class="'alert-' + (t.type || 'info')" x-bind:role="t.type === 'danger' ? 'alert' : 'status'" x-bind:aria-live="t.type === 'danger' ? 'assertive' : 'polite'">
            <span class="flex-grow-1" x-text="t.message"></span>
            <button type="button" class="btn-close" @click="drop(t.id)" aria-label="{{ __('Fermer') }}"></button>
        </div>
    </template>
</div>
