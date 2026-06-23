<?php
// Komponen Modal "EMR RJ khusus Diagnosa". Dipisah dari daftar-rj supaya orchestrator
// tetap ramping (pola sama satu-sehat-rj-actions). Trigger dari parent:
// dispatch 'daftar-rj.diagnosa.open' dengan rjNo. Reuse komponen diagnosa EMR RJ
// (rm-diagnosa-rj-actions) apa adanya — diagnosis + prosedur; perubahan tercatat di log.

use Livewire\Component;
use Livewire\Attributes\On;

new class extends Component {
    public ?int $rjNo = null;

    #[On('daftar-rj.diagnosa.open')]
    public function open(int $rjNo): void
    {
        $this->rjNo = $rjNo;
        // Load diagnosa di komponen anak + buka modal (tiru model EMR RJ).
        $this->dispatch('open-rm-diagnosa-rj', $rjNo);
        $this->dispatch('open-modal', name: 'diagnosa-rj-daftar');
    }
};
?>

<div>
    <x-modal name="diagnosa-rj-daftar" size="full" height="full" focusable>
        <div class="flex flex-col h-full">
            <div class="px-6 py-4 border-b border-hairline dark:border-gray-700">
                @if (!empty($rjNo))
                    <div class="flex items-start gap-3">
                        <div class="flex-1 min-w-0">
                            <livewire:pages::transaksi.rj.display-pasien-rj.display-pasien-rj :rjNo="$rjNo"
                                wire:key="diagnosa-rj-daftar-display-pasien-{{ $rjNo }}" />
                        </div>
                        <x-icon-button color="gray" type="button" x-on:click="$dispatch('close-modal', { name: 'diagnosa-rj-daftar' })" class="shrink-0">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </x-icon-button>
                    </div>
                @endif
                <h2 class="mt-2 text-base font-semibold text-ink dark:text-gray-100">EMR Rawat Jalan — Diagnosa</h2>
            </div>

            <div class="flex-1 px-6 py-4 overflow-y-auto">
                <livewire:pages::transaksi.rj.emr-rj.diagnosa.rm-diagnosa-rj-actions
                    wire:key="diagnosa-rj-daftar-actions" />
            </div>

            <div class="sticky bottom-0 z-10 flex items-center justify-end gap-2 px-6 py-3 border-t border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-700">
                <x-secondary-button type="button" x-on:click="$dispatch('close-modal', { name: 'diagnosa-rj-daftar' })">Tutup</x-secondary-button>
                <x-primary-button type="button" x-on:click="$dispatch('save-rm-diagnosa-rj')">Simpan Diagnosa</x-primary-button>
            </div>
        </div>
    </x-modal>
</div>
