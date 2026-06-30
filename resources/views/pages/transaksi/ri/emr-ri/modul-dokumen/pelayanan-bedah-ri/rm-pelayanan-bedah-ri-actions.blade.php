<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/pelayanan-bedah-ri/rm-pelayanan-bedah-ri-actions.blade.php
//
// Umbrella "Pelayanan Bedah (PAB)" — wadah sub-navigasi form bedah/anestesi RI.
// Urutan sub-nav mengikuti alur episode bedah: pra-operasi → operasi → pasca-operasi.
// Tambahkan form berikutnya sebagai entri baru pada array $subForms (posisi fase sesuai).

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

@php
    // Definisi sub-nav (urut kronologis). icon = path SVG. step/pengisi dipakai panduan.
    $subForms = [
        ['key' => 'pengkajianPreOp', 'label' => 'Pengkajian Pre Operasi', 'fase' => 'Pra-operasi', 'pengisi' => 'Perawat ruangan', 'ket' => 'Persiapan pasien (puasa/cukur/premedikasi) & serah-terima kelengkapan ke OK', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'],
        ['key' => 'praAnestesi', 'label' => 'Pengkajian Pra Anestesi', 'fase' => 'Pra-operasi', 'pengisi' => 'Dokter anestesi', 'ket' => 'Anamnese, jalan nafas (Mallampati), status ASA, rencana teknik anestesi', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
        ['key' => 'siteMarking', 'label' => 'Penandaan Lokasi (Site Marking)', 'fase' => 'Pra-operasi', 'pengisi' => 'Operator + 2 perawat', 'ket' => 'Tandai sisi/lokasi operasi, verifikasi Perawat Ruangan, Perawat Kamar Bedah & Operator', 'icon' => 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z'],
        ['key' => 'praInduksi', 'label' => 'Asesmen Pra Induksi', 'fase' => 'Pra-induksi', 'pengisi' => 'Dokter anestesi', 'ket' => 'Re-cek kondisi terkini sesaat sebelum induksi + obat pre-medikasi', 'icon' => 'M13 10V3L4 14h7v7l9-11h-7z'],
        ['key' => 'laporanOperasi', 'label' => 'Laporan Operasi (BAP)', 'fase' => 'Pasca-operasi', 'pengisi' => 'Operator / DPJP bedah', 'ket' => 'Isi LENGKAP segera setelah operasi, sebelum pasien dipindah ke ruang lain', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
        ['key' => 'laporanAnestesi', 'label' => 'Laporan Anestesi', 'fase' => 'Pasca-operasi', 'pengisi' => 'Ahli anestesiologi', 'ket' => 'Teknik anestesi, monitoring sistem organ, masalah & keadaan akhir', 'icon' => 'M3 12h4l2 5 4-10 2 5h6'],
        ['key' => 'pascaAnestesi', 'label' => 'Monitoring Pasca Anestesi', 'fase' => 'Pasca-operasi', 'pengisi' => 'Petugas Recovery Room', 'ket' => 'Skor Aldrete (umum) / Bromage (regional-spinal), nyeri, rekomendasi pindah', 'icon' => 'M3 12h4l2 5 4-10 2 5h6'],
        ['key' => 'instruksiPascaBedah', 'label' => 'Instruksi Pasca Bedah', 'fase' => 'Pasca-operasi', 'pengisi' => 'Ahli anestesiologi', 'ket' => 'Penanganan nyeri/mual, antibiotik, obat, minum, infus, monitor tanda vital', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01'],
    ];

    $infoMap = collect($subForms)->keyBy('key')->map(fn($s) => ['label' => $s['label'], 'fase' => $s['fase'], 'pengisi' => $s['pengisi'], 'ket' => $s['ket']]);
@endphp

<div x-data="{ subTab: 'pengkajianPreOp', showGuide: false, info: @js($infoMap) }">

    {{-- ══ PANDUAN PENGISIAN (collapsible) ══ --}}
    <div class="mb-4 border border-brand-200 rounded-xl bg-brand-50/60 dark:bg-brand-900/10 dark:border-brand-800">
        <button type="button" x-on:click="showGuide = !showGuide"
            class="flex items-center justify-between w-full px-4 py-2.5 text-left">
            <span class="flex items-center gap-2 text-base font-semibold text-brand-700 dark:text-brand-300">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Panduan Pengisian — Alur & Cara Isi
            </span>
            <svg class="w-4 h-4 text-brand-600 transition-transform" :class="showGuide && 'rotate-180'" fill="none"
                stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <div x-show="showGuide" x-collapse style="display:none" class="px-4 pb-4 space-y-3">
            {{-- Langkah berurutan --}}
            <div>
                <p class="mb-1.5 text-sm font-semibold text-ink dark:text-gray-200">Urutan pengisian (alur episode bedah):</p>
                <ol class="space-y-1 text-sm text-body dark:text-gray-300 list-decimal pl-5">
                    @foreach ($subForms as $sf)
                        <li>
                            <button type="button" x-on:click="subTab = '{{ $sf['key'] }}'; showGuide = false"
                                class="font-medium text-brand-700 underline-offset-2 hover:underline dark:text-brand-300">{{ $sf['label'] }}</button>
                            <span class="text-muted dark:text-gray-400"> — {{ $sf['pengisi'] }} · <em>{{ $sf['fase'] }}</em>: {{ $sf['ket'] }}</span>
                        </li>
                    @endforeach
                </ol>
            </div>

            {{-- Cara isi umum --}}
            <div class="pt-2 border-t border-brand-200/60 dark:border-brand-800/60">
                <p class="mb-1.5 text-sm font-semibold text-ink dark:text-gray-200">Cara isi tiap form:</p>
                <ul class="space-y-1 text-sm text-body dark:text-gray-300 list-disc pl-5">
                    <li>Klik <b>Buka Formulir</b> pada form yang dipilih.</li>
                    <li>Isi kolom bertanda <b>*</b> (wajib). Untuk tanggal/jam, klik tombol <b>jam</b> agar terisi waktu sekarang.</li>
                    <li>Bubuhkan <b>Tanda Tangan</b> (tombol "TTD sebagai …") — otomatis mengambil nama & TTD user yang login. Site Marking butuh TTD perawat via tanda tangan layar.</li>
                    <li>Klik <b>Simpan</b>. Satu pasien bisa punya lebih dari satu entri (mis. operasi berulang).</li>
                    <li>Entri tersimpan bisa di-<b>Cetak</b> (PDF) atau <b>Hapus</b> dari tabel di bawah form.</li>
                    <li>Jika EMR sudah <b>terkunci</b> (Read Only), form hanya bisa dilihat & dicetak, tidak bisa diubah.</li>
                </ul>
            </div>
        </div>
    </div>

    {{-- ══ SUB-NAV (urut kronologis) ══ --}}
    <div class="mb-3">
        <div class="flex flex-wrap gap-2">
            @foreach ($subForms as $sf)
                <button type="button" x-on:click="subTab = '{{ $sf['key'] }}'"
                    class="inline-flex items-center gap-2 px-3.5 py-2 text-base font-medium rounded-xl border transition"
                    :class="subTab === '{{ $sf['key'] }}'
                        ? 'bg-brand-50 border-brand-300 text-brand-700 dark:bg-brand-900/20 dark:border-brand-700 dark:text-brand-300'
                        : 'bg-canvas border-hairline text-muted hover:border-brand-300 dark:bg-gray-900 dark:border-gray-700'">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $sf['icon'] }}" />
                    </svg>
                    {{ $sf['label'] }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- ══ HINT FORM AKTIF (diisi oleh / fase / keterangan) ══ --}}
    <div class="flex flex-wrap items-center gap-x-2 gap-y-1 px-3 py-2 mb-4 text-sm rounded-lg bg-surface-soft border border-hairline text-muted dark:bg-gray-800/60 dark:border-gray-700 dark:text-gray-400">
        <svg class="w-4 h-4 shrink-0 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span class="font-semibold text-ink dark:text-gray-200" x-text="info[subTab]?.label"></span>
        <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-brand-100 text-brand-700 dark:bg-brand-900/30 dark:text-brand-300" x-text="info[subTab]?.fase"></span>
        <span>· Diisi oleh: <b class="text-body dark:text-gray-300" x-text="info[subTab]?.pengisi"></b></span>
        <span class="w-full sm:w-auto sm:before:content-['·'] sm:before:mr-2" x-text="info[subTab]?.ket"></span>
    </div>

    {{-- ① PRA-OPERASI ───────────────────────────────────────── --}}

    {{-- Pengkajian Pre Operasi (keperawatan) --}}
    <div x-show="subTab === 'pengkajianPreOp'" x-transition.opacity.duration.200ms>
        <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.pengkajian-pre-op-ri.rm-pengkajian-pre-op-ri-actions
            :riHdrNo="$riHdrNo" :disabled="$disabled"
            wire:key="pengkajian-pre-op-ri-{{ $riHdrNo ?? 'init' }}" />
    </div>

    {{-- Pengkajian Pra Anestesi --}}
    <div x-show="subTab === 'praAnestesi'" x-transition.opacity.duration.200ms style="display:none">
        <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.pra-anestesi-ri.rm-pra-anestesi-ri-actions
            :riHdrNo="$riHdrNo" :disabled="$disabled"
            wire:key="pra-anestesi-ri-{{ $riHdrNo ?? 'init' }}" />
    </div>

    {{-- Penandaan Lokasi / Site Marking --}}
    <div x-show="subTab === 'siteMarking'" x-transition.opacity.duration.200ms style="display:none">
        <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.site-marking-ri.rm-site-marking-ri-actions
            :riHdrNo="$riHdrNo" :disabled="$disabled"
            wire:key="site-marking-ri-{{ $riHdrNo ?? 'init' }}" />
    </div>

    {{-- Asesmen Pra Induksi --}}
    <div x-show="subTab === 'praInduksi'" x-transition.opacity.duration.200ms style="display:none">
        <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.pra-induksi-ri.rm-pra-induksi-ri-actions
            :riHdrNo="$riHdrNo" :disabled="$disabled"
            wire:key="pra-induksi-ri-{{ $riHdrNo ?? 'init' }}" />
    </div>

    {{-- ② / ③ OPERASI & PASCA-OPERASI ────────────────────────── --}}

    {{-- Laporan Operasi (BAP) --}}
    <div x-show="subTab === 'laporanOperasi'" x-transition.opacity.duration.200ms style="display:none">
        <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.laporan-operasi-ri.rm-laporan-operasi-ri-actions
            :riHdrNo="$riHdrNo" :disabled="$disabled"
            wire:key="laporan-operasi-ri-{{ $riHdrNo ?? 'init' }}" />
    </div>

    {{-- Laporan Anestesi --}}
    <div x-show="subTab === 'laporanAnestesi'" x-transition.opacity.duration.200ms style="display:none">
        <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.laporan-anestesi-ri.rm-laporan-anestesi-ri-actions
            :riHdrNo="$riHdrNo" :disabled="$disabled"
            wire:key="laporan-anestesi-ri-{{ $riHdrNo ?? 'init' }}" />
    </div>

    {{-- Monitoring Pasca Anestesi --}}
    <div x-show="subTab === 'pascaAnestesi'" x-transition.opacity.duration.200ms style="display:none">
        <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.pasca-anestesi-ri.rm-pasca-anestesi-ri-actions
            :riHdrNo="$riHdrNo" :disabled="$disabled"
            wire:key="pasca-anestesi-ri-{{ $riHdrNo ?? 'init' }}" />
    </div>

    {{-- Instruksi Pasca Bedah --}}
    <div x-show="subTab === 'instruksiPascaBedah'" x-transition.opacity.duration.200ms style="display:none">
        <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.instruksi-pasca-bedah-ri.rm-instruksi-pasca-bedah-ri-actions
            :riHdrNo="$riHdrNo" :disabled="$disabled"
            wire:key="instruksi-pasca-bedah-ri-{{ $riHdrNo ?? 'init' }}" />
    </div>

</div>
