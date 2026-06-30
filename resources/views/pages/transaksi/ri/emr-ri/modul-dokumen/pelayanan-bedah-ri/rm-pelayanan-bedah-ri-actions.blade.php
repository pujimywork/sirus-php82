<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/pelayanan-bedah-ri/rm-pelayanan-bedah-ri-actions.blade.php
//
// Umbrella "Pelayanan Bedah (PAB)" — wadah sub-navigasi untuk form bedah/anestesi RI.
// Sub-form pertama: Laporan Operasi (BAP). Tambahkan form berikutnya (Site Marking,
// Pra Induksi, Bromage, dll.) sebagai sub-nav baru di sini.

use Livewire\Component;

new class extends Component {
    public ?string $riHdrNo = null;
    public bool $disabled = false;

    public function mount(?string $riHdrNo = null, bool $disabled = false): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->disabled = $disabled;
    }
};
?>

<div x-data="{ subTab: 'laporanOperasi' }">

    {{-- ══ SUB-NAV ══ --}}
    <div class="mb-4">
        <div class="flex flex-wrap gap-2">
            <button type="button" x-on:click="subTab = 'laporanOperasi'"
                class="inline-flex items-center gap-2 px-3.5 py-2 text-base font-medium rounded-xl border transition"
                :class="subTab === 'laporanOperasi'
                    ? 'bg-brand-50 border-brand-300 text-brand-700 dark:bg-brand-900/20 dark:border-brand-700 dark:text-brand-300'
                    : 'bg-canvas border-hairline text-muted hover:border-brand-300 dark:bg-gray-900 dark:border-gray-700'">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Laporan Operasi (BAP)
            </button>

            {{-- Form bedah/anestesi berikutnya ditambahkan di sini --}}
        </div>
        <p class="mt-2 text-sm text-muted-soft dark:text-gray-500">
            Dokumen Pelayanan Anestesi &amp; Bedah (PAB) untuk episode operasi pasien rawat inap.
        </p>
    </div>

    {{-- ══ SUB-PANEL: LAPORAN OPERASI ══ --}}
    <div x-show="subTab === 'laporanOperasi'" x-transition.opacity.duration.200ms>
        <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.laporan-operasi-ri.rm-laporan-operasi-ri-actions
            :riHdrNo="$riHdrNo" :disabled="$disabled"
            wire:key="laporan-operasi-ri-{{ $riHdrNo ?? 'init' }}" />
    </div>

</div>
