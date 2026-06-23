<?php
// Komponen Modal "EMR UGD khusus Diagnosa". Dipisah dari daftar-ugd supaya orchestrator
// tetap ramping (pola sama satu-sehat / diagnosa-rj-actions). Trigger dari parent:
// dispatch 'daftar-ugd.diagnosa.open' dengan rjNo. Reuse komponen diagnosa EMR UGD
// (rm-diagnosa-ugd-actions) apa adanya — diagnosis + prosedur; perubahan tercatat di log.

use Livewire\Component;
use Livewire\Attributes\On;

new class extends Component {
    public ?int $rjNo = null;

    #[On('daftar-ugd.diagnosa.open')]
    public function open(int $rjNo): void
    {
        $this->rjNo = $rjNo;
        $this->dispatch('open-rm-diagnosa-ugd', $rjNo);
        $this->dispatch('open-modal', name: 'diagnosa-ugd-daftar');
    }
};
?>

<div>
    <x-modal name="diagnosa-ugd-daftar" size="full" height="full" focusable>
        <div class="flex flex-col h-full">
            <div class="px-6 py-4 border-b border-hairline dark:border-gray-700">
                @if (!empty($rjNo))
                    <div class="flex items-start gap-3">
                        <div class="flex-1 min-w-0">
                            <livewire:pages::transaksi.ugd.display-pasien-ugd.display-pasien-ugd :rjNo="$rjNo"
                                wire:key="diagnosa-ugd-daftar-display-pasien-{{ $rjNo }}" />
                        </div>
                        <x-icon-button color="gray" type="button" x-on:click="$dispatch('close-modal', { name: 'diagnosa-ugd-daftar' })" class="shrink-0">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </x-icon-button>
                    </div>
                @endif
                <h2 class="mt-2 text-base font-semibold text-ink dark:text-gray-100">EMR UGD — Diagnosa</h2>
            </div>

            <div class="flex-1 px-6 py-4 overflow-y-auto">
                <livewire:pages::transaksi.ugd.emr-ugd.diagnosa.rm-diagnosa-ugd-actions
                    wire:key="diagnosa-ugd-daftar-actions" />
            </div>

            <div class="sticky bottom-0 z-10 flex items-center justify-end gap-2 px-6 py-3 border-t border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-700">
                <x-secondary-button type="button" x-on:click="$dispatch('close-modal', { name: 'diagnosa-ugd-daftar' })">Tutup</x-secondary-button>
                <x-primary-button type="button" x-on:click="$dispatch('save-rm-diagnosa-ugd')">Simpan Diagnosa</x-primary-button>
            </div>
        </div>
    </x-modal>
</div>
