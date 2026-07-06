<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/vk-kebidanan-ri/rm-vk-kebidanan-ri-actions.blade.php
//
// Umbrella "VK / Kebidanan" — wadah sub-navigasi seluruh dokumen kebidanan RI.
// Urutan sub-nav mengikuti alur: Obstetri (kehamilan→bersalin→nifas) → Ginekologi → Neonatal.
// Tambah dokumen berikutnya sebagai entri baru pada array $subForms (posisi alur sesuai).

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
    $iconDoc  = 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z';
    $iconTbl  = 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2';
    $iconChart = 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z';
    $iconCheck = 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z';
    $iconHeart = 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z';
    $iconBaby  = 'M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z';

    // Definisi sub-nav (urut alur kebidanan). key = subTab. livewire di-include per key di bawah.
    $subForms = [
        ['key' => 'pengkajianObstetri', 'label' => 'Pengkajian Awal Obstetri', 'fase' => 'Obstetri', 'pengisi' => 'Bidan / Dokter', 'ket' => 'Identitas, status obstetri (G-P-A), riwayat persalinan, pemeriksaan dalam, skrining PP 1.2', 'icon' => $iconDoc],
        ['key' => 'riwayatObstetri', 'label' => 'Riwayat Obstetri', 'fase' => 'Obstetri', 'pengisi' => 'Bidan', 'ket' => 'Tabel riwayat kehamilan & persalinan yang lalu', 'icon' => $iconTbl],
        ['key' => 'observasiPersalinan', 'label' => 'Observasi Persalinan', 'fase' => 'Intranatal', 'pengisi' => 'Bidan', 'ket' => 'Pemantauan Kala I–II berulang (TD/N/RR/S/DJJ/His/EWS)', 'icon' => $iconChart],
        ['key' => 'laporanPersalinan', 'label' => 'Laporan Tindakan Persalinan', 'fase' => 'Intranatal', 'pengisi' => 'Dokter / Bidan', 'ket' => 'Partus, bayi, plasenta, perlukaan, Kala IV, IMD/rawat gabung', 'icon' => $iconDoc],
        ['key' => 'indikatorSc', 'label' => 'Indikator Proses SC', 'fase' => 'Audit', 'pengisi' => 'Dokter', 'ket' => 'Checklist mutu 15 indikator + klasifikasi + indikasi SC', 'icon' => $iconCheck],
        ['key' => 'observasiNifas', 'label' => 'Observasi Nifas', 'fase' => 'Postnatal', 'pengisi' => 'Bidan', 'ket' => 'Pemantauan masa nifas (TFU, lochia, laktasi, luka, EWS)', 'icon' => $iconHeart],
        ['key' => 'pengkajianGinekologi', 'label' => 'Pengkajian Awal Ginekologi', 'fase' => 'Ginekologi', 'pengisi' => 'Bidan / Dokter', 'ket' => 'Keluhan, riwayat haid/KB, pemeriksaan dalam (VT/RT/Inspeculo), diagnosa', 'icon' => $iconDoc],
        ['key' => 'pengkajianBayi', 'label' => 'Pengkajian Awal Bayi', 'fase' => 'Neonatal', 'pengisi' => 'Dokter', 'ket' => 'APGAR, pemeriksaan fisik, antropometri, diagnosa & rencana', 'icon' => $iconBaby],
        ['key' => 'pengkajianNeonatalPerawat', 'label' => 'Pengkajian Keperawatan Neonatal', 'fase' => 'Neonatal', 'pengisi' => 'Perawat / Bidan', 'ket' => 'Head-to-toe, review sistem B1–B6, skala nyeri NIPS, diagnosa keperawatan', 'icon' => $iconBaby],
        ['key' => 'identifikasiBayi', 'label' => 'Identifikasi Bayi', 'fase' => 'Neonatal', 'pengisi' => 'Perawat / Bidan', 'ket' => 'Gelang bayi, identitas ibu-bayi, serah-terima', 'icon' => $iconBaby],
        ['key' => 'catatanTerapiNeonatal', 'label' => 'Catatan Terapi & Perencanaan', 'fase' => 'Neonatal', 'pengisi' => 'Dokter / Perawat', 'ket' => 'Terapi dokter + perencanaan & tindakan keperawatan', 'icon' => $iconTbl],
    ];

    $infoMap = collect($subForms)->keyBy('key')->map(fn($s) => ['label' => $s['label'], 'fase' => $s['fase'], 'pengisi' => $s['pengisi'], 'ket' => $s['ket']]);
@endphp

<div x-data="{ subTab: 'pengkajianObstetri', showGuide: false, info: @js($infoMap) }">

    {{-- ══ PANDUAN PENGISIAN (collapsible) ══ --}}
    <div class="mb-4 border border-brand-200 rounded-xl bg-brand-50/60 dark:bg-brand-900/10 dark:border-brand-800">
        <button type="button" x-on:click="showGuide = !showGuide"
            class="flex items-center justify-between w-full px-4 py-2.5 text-left">
            <span class="flex items-center gap-2 text-base font-semibold text-brand-700 dark:text-brand-300">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Panduan Pengisian — Alur Kebidanan
            </span>
            <svg class="w-4 h-4 text-brand-600 transition-transform" :class="showGuide && 'rotate-180'" fill="none"
                stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <div x-show="showGuide" x-collapse style="display:none" class="px-4 pb-4 space-y-3">
            <div>
                <p class="mb-1.5 text-sm font-semibold text-ink dark:text-gray-200">Urutan pengisian (alur kebidanan):</p>
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
            <div class="pt-2 border-t border-brand-200/60 dark:border-brand-800/60">
                <p class="mb-1.5 text-sm font-semibold text-ink dark:text-gray-200">Cara isi tiap form:</p>
                <ul class="space-y-1 text-sm text-body dark:text-gray-300 list-disc pl-5">
                    <li>Klik <b>Buka Formulir</b> pada form yang dipilih.</li>
                    <li>Isi kolom wajib; untuk tanggal/jam gunakan tombol <b>Now</b> agar terisi waktu sekarang.</li>
                    <li>Pilih <b>Pengisi</b> (Bidan/Dokter/Perawat) sesuai peran.</li>
                    <li>Klik <b>Simpan</b>. Satu pasien bisa punya beberapa entri (mis. observasi berulang).</li>
                    <li>Entri tersimpan bisa di-<b>Cetak</b> (PDF) atau <b>Hapus</b> dari tabel di bawah form.</li>
                    <li>Jika EMR sudah <b>terkunci</b> (Read Only), form hanya bisa dilihat & dicetak.</li>
                </ul>
            </div>
        </div>
    </div>

    {{-- ══ SUB-NAV (urut alur) — x-tabs variant chip ══ --}}
    <x-tabs variant="chip" class="flex-wrap mb-3">
        @foreach ($subForms as $sf)
            <x-tab active-expr="subTab === '{{ $sf['key'] }}'" x-on:click="subTab = '{{ $sf['key'] }}'"
                class="inline-flex items-center gap-2">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $sf['icon'] }}" />
                </svg>
                {{ $sf['label'] }}
            </x-tab>
        @endforeach
    </x-tabs>

    {{-- ══ HINT FORM AKTIF ══ --}}
    <div class="flex flex-wrap items-center gap-x-2 gap-y-1 px-3 py-2 mb-4 text-sm rounded-lg bg-surface-soft border border-hairline text-muted dark:bg-gray-800/60 dark:border-gray-700 dark:text-gray-400">
        <svg class="w-4 h-4 shrink-0 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span class="font-semibold text-ink dark:text-gray-200" x-text="info[subTab]?.label"></span>
        <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-brand-100 text-brand-700 dark:bg-brand-900/30 dark:text-brand-300" x-text="info[subTab]?.fase"></span>
        <span class="w-full sm:w-auto sm:before:content-['·'] sm:before:mr-2" x-text="info[subTab]?.ket"></span>
    </div>

    {{-- ══ KONTEN PER SUB-TAB ══ --}}

    {{-- ① OBSTETRI ─────────────────────────── --}}
    <div x-show="subTab === 'pengkajianObstetri'" x-transition.opacity.duration.200ms>
        <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.pengkajian-awal-obstetri-ri.rm-pengkajian-awal-obstetri-ri-actions
            :riHdrNo="$riHdrNo" :disabled="$disabled" wire:key="vk-pengkajian-obstetri-{{ $riHdrNo ?? 'init' }}" />
    </div>
    <div x-show="subTab === 'riwayatObstetri'" x-transition.opacity.duration.200ms style="display:none">
        <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.riwayat-obstetri-ri.rm-riwayat-obstetri-ri-actions
            :riHdrNo="$riHdrNo" :disabled="$disabled" wire:key="vk-riwayat-obstetri-{{ $riHdrNo ?? 'init' }}" />
    </div>
    <div x-show="subTab === 'observasiPersalinan'" x-transition.opacity.duration.200ms style="display:none">
        <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.observasi-persalinan-ri.rm-observasi-persalinan-ri-actions
            :riHdrNo="$riHdrNo" :disabled="$disabled" wire:key="vk-observasi-persalinan-{{ $riHdrNo ?? 'init' }}" />
    </div>
    <div x-show="subTab === 'laporanPersalinan'" x-transition.opacity.duration.200ms style="display:none">
        <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.laporan-persalinan-ri.rm-laporan-persalinan-ri-actions
            :riHdrNo="$riHdrNo" :disabled="$disabled" wire:key="vk-laporan-persalinan-{{ $riHdrNo ?? 'init' }}" />
    </div>
    <div x-show="subTab === 'indikatorSc'" x-transition.opacity.duration.200ms style="display:none">
        <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.indikator-sc-ri.rm-indikator-sc-ri-actions
            :riHdrNo="$riHdrNo" :disabled="$disabled" wire:key="vk-indikator-sc-{{ $riHdrNo ?? 'init' }}" />
    </div>
    <div x-show="subTab === 'observasiNifas'" x-transition.opacity.duration.200ms style="display:none">
        <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.observasi-nifas-ri.rm-observasi-nifas-ri-actions
            :riHdrNo="$riHdrNo" :disabled="$disabled" wire:key="vk-observasi-nifas-{{ $riHdrNo ?? 'init' }}" />
    </div>

    {{-- ② GINEKOLOGI ─────────────────────────── --}}
    <div x-show="subTab === 'pengkajianGinekologi'" x-transition.opacity.duration.200ms style="display:none">
        <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.pengkajian-awal-ginekologi-ri.rm-pengkajian-awal-ginekologi-ri-actions
            :riHdrNo="$riHdrNo" :disabled="$disabled" wire:key="vk-pengkajian-ginekologi-{{ $riHdrNo ?? 'init' }}" />
    </div>

    {{-- ③ NEONATAL ─────────────────────────── --}}
    <div x-show="subTab === 'pengkajianBayi'" x-transition.opacity.duration.200ms style="display:none">
        <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.pengkajian-awal-bayi-ri.rm-pengkajian-awal-bayi-ri-actions
            :riHdrNo="$riHdrNo" :disabled="$disabled" wire:key="vk-pengkajian-bayi-{{ $riHdrNo ?? 'init' }}" />
    </div>
    <div x-show="subTab === 'pengkajianNeonatalPerawat'" x-transition.opacity.duration.200ms style="display:none">
        <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.pengkajian-neonatal-perawat-ri.rm-pengkajian-neonatal-perawat-ri-actions
            :riHdrNo="$riHdrNo" :disabled="$disabled" wire:key="vk-pengkajian-neonatal-perawat-{{ $riHdrNo ?? 'init' }}" />
    </div>
    <div x-show="subTab === 'identifikasiBayi'" x-transition.opacity.duration.200ms style="display:none">
        <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.identifikasi-bayi-ri.rm-identifikasi-bayi-ri-actions
            :riHdrNo="$riHdrNo" :disabled="$disabled" wire:key="vk-identifikasi-bayi-{{ $riHdrNo ?? 'init' }}" />
    </div>
    <div x-show="subTab === 'catatanTerapiNeonatal'" x-transition.opacity.duration.200ms style="display:none">
        <livewire:pages::transaksi.ri.emr-ri.modul-dokumen.catatan-terapi-neonatal-ri.rm-catatan-terapi-neonatal-ri-actions
            :riHdrNo="$riHdrNo" :disabled="$disabled" wire:key="vk-catatan-terapi-neonatal-{{ $riHdrNo ?? 'init' }}" />
    </div>

</div>
