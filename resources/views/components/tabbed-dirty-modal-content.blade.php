@props([
    'name',                                                    // modal name untuk open-modal listener
    'savedEvent' => 'refresh-after-saved',                     // event yang reset dirty per section (sub-component dispatch ini setelah save sukses)
    'wireKey',                                                 // wire:key untuk container
    'tabs' => [],                                              // array tab config: [['key' => '...', 'label' => '...', 'saveEvent' => '...'], ...]
    'initialTab' => null,                                      // tab default; null = ambil dari tabs[0]
    'wrapperClass' => 'flex flex-col min-h-[calc(100vh-8rem)]',
])

@php
    $initial = $initialTab ?? ($tabs[0]['key'] ?? '');
@endphp

{{--
    Tabbed Dirty Modal Content — dirty tracking PER-TAB.
    Sub-component bertanggung jawab dispatch event:
        $dispatch('section-dirty', { tab: '<tab-key>' })   → tandai tab dirty
        $dispatch('section-clean', { tab: '<tab-key>' })   → tandai tab clean (biasanya setelah save sukses)

    Wrapper expose Alpine scope:
        activeTab               — tab key aktif (set via @click di tab nav)
        tabDirty[key]           — bool per tab
        saveMap[key]            — { label, saveEvent } per tab
        tryClose()              — dipanggil tombol Tutup; jika ada dirty → warning, else langsung closeModal
        saveActive()            — dispatch saveEvent untuk activeTab (dipanggil tombol Simpan footer)
        isAnyDirty()            — boolean
        dirtyTabLabels()        — list label tab yang dirty (untuk warning UI)
--}}

<div class="{{ $wrapperClass }}"
    wire:key="{{ $wireKey }}"
    x-data="{
        activeTab: @js($initial),
        tabDirty: {},
        showUnsavedWarning: false,
        savingAndClosing: false,
        saveMap: @js(collect($tabs)->mapWithKeys(fn($t) => [$t['key'] => ['label' => $t['label'], 'saveEvent' => $t['saveEvent']]])->all()),

        markDirty(tab) {
            if (this.saveMap[tab]) this.tabDirty[tab] = true;
        },
        markClean(tab) {
            this.tabDirty[tab] = false;
        },
        isAnyDirty() {
            return Object.values(this.tabDirty).some(v => v);
        },
        dirtyTabKeys() {
            return Object.entries(this.tabDirty).filter(([k, v]) => v).map(([k]) => k);
        },
        dirtyTabLabels() {
            return this.dirtyTabKeys().map(k => this.saveMap[k]?.label ?? k);
        },

        saveActive() {
            const ev = this.saveMap[this.activeTab]?.saveEvent;
            if (ev) Livewire.dispatch(ev);
        },

        tryClose() {
            if (this.isAnyDirty()) {
                this.showUnsavedWarning = true;
            } else {
                $wire.closeModal();
            }
        },

        async saveAndClose() {
            if (this.savingAndClosing) return;
            this.savingAndClosing = true;
            try {
                const dirtyKeys = this.dirtyTabKeys();
                const events = dirtyKeys.map(k => this.saveMap[k]?.saveEvent).filter(Boolean);
                if (events.length > 0) {
                    let saved = 0;
                    const onSaved = () => saved++;
                    window.addEventListener('{{ $savedEvent }}', onSaved);
                    try {
                        events.forEach(e => Livewire.dispatch(e));
                        const deadline = Date.now() + 3000;
                        while (saved < events.length && Date.now() < deadline) {
                            await new Promise(r => setTimeout(r, 50));
                        }
                    } finally {
                        window.removeEventListener('{{ $savedEvent }}', onSaved);
                    }
                }
                this.tabDirty = {};
                this.showUnsavedWarning = false;
                await $wire.closeModal();
            } finally {
                this.savingAndClosing = false;
            }
        },
    }"
    x-init="
        window.addEventListener('open-modal', (e) => {
            if (e.detail && e.detail.name === '{{ $name }}') {
                tabDirty = {};
                showUnsavedWarning = false;
            }
        });
    "
    x-on:section-dirty="markDirty($event.detail.tab)"
    x-on:section-clean="markClean($event.detail.tab)">

    {{ $slot }}

    {{-- ══════════ KONFIRMASI TUTUP TANPA SIMPAN (per-tab) ══════════ --}}
    <div x-cloak x-show="showUnsavedWarning" class="fixed inset-0 z-[99]" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-gray-900/50 dark:bg-gray-900/70"
            x-on:click="showUnsavedWarning = false"></div>
        <div class="fixed inset-0 flex items-center justify-center p-4">
            <div class="w-full max-w-md overflow-hidden bg-white border border-gray-200 shadow-2xl rounded-2xl dark:bg-gray-800 dark:border-gray-700"
                x-on:click.stop>
                <div class="flex items-start gap-3 px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-center w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900/30 shrink-0">
                        <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Ada tab belum disimpan</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Tab berikut punya perubahan belum disimpan:
                            <span class="font-medium text-gray-700 dark:text-gray-300" x-text="dirtyTabLabels().join(', ')"></span>.
                            Simpan semua dan tutup sekarang?
                        </p>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2 px-5 py-4 bg-gray-50/70 dark:bg-gray-900/20">
                    <x-secondary-button type="button" x-on:click="showUnsavedWarning = false"
                        x-bind:disabled="savingAndClosing">
                        Lanjut Edit
                    </x-secondary-button>
                    <x-primary-button type="button" x-on:click="saveAndClose()"
                        x-bind:disabled="savingAndClosing">
                        <span x-show="!savingAndClosing">Tutup dan Simpan</span>
                        <span x-show="savingAndClosing" x-cloak class="flex items-center gap-1">
                            <x-loading /> Menyimpan...
                        </span>
                    </x-primary-button>
                </div>
            </div>
        </div>
    </div>
</div>
