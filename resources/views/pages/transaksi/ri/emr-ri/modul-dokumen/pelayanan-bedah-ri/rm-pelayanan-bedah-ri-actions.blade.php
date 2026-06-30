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

            <button type="button" x-on:click="subTab = 'instruksiPascaBedah'"
                class="inline-flex items-center gap-2 px-3.5 py-2 text-base font-medium rounded-xl border transition"
                :class="subTab === 'instruksiPascaBedah'
                    ? 'bg-brand-50 border-brand-300 text-brand-700 dark:bg-brand-900/20 dark:border-brand-700 dark:text-brand-300'
                    : 'bg-canvas border-hairline text-muted hover:border-brand-300 dark:bg-gray-900 dark:border-gray-700'">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                </svg>
                Instruksi Pasca Bedah
            </button>

            <button type="button" x-on:click="subTab = 'siteMarking'"
                class="inline-flex items-center gap-2 px-3.5 py-2 text-base font-medium rounded-xl border transition"
                :class="subTab === 'siteMarking'
                    ? 'bg-brand-50 border-brand-300 text-brand-700 dark:bg-brand-900/20 dark:border-brand-700 dark:text-brand-300'
                    : 'bg-canvas border-hairline text-muted hover:border-brand-300 dark:bg-gray-900 dark:border-gray-700'">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                Penandaan Lokasi (Site Marking)
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

    {{-- ══ SUB-PANEL: INSTRUKSI PASCA BEDAH ══ --}}
    <div x-show="subTab === 'instruksiPascaBedah'" x-transition.opacity.duration.200ms style="display:none">
        <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.instruksi-pasca-bedah-ri.rm-instruksi-pasca-bedah-ri-actions
            :riHdrNo="$riHdrNo" :disabled="$disabled"
            wire:key="instruksi-pasca-bedah-ri-{{ $riHdrNo ?? 'init' }}" />
    </div>

    {{-- ══ SUB-PANEL: SITE MARKING ══ --}}
    <div x-show="subTab === 'siteMarking'" x-transition.opacity.duration.200ms style="display:none">
        <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.site-marking-ri.rm-site-marking-ri-actions
            :riHdrNo="$riHdrNo" :disabled="$disabled"
            wire:key="site-marking-ri-{{ $riHdrNo ?? 'init' }}" />
    </div>

</div>
