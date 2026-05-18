<?php

use Livewire\Component;

new class extends Component {
    //
};

?>

{{--
    Modal panduan kriteria kelengkapan EMR Rawat Jalan.
    Dibuka dari tombol info di toolbar daftar-rj (atau di mana saja yang dispatch
    'open-modal' dengan name='info-kelengkapan-emr-rj').

    Sumber bobot & field WAJIB: App\Http\Traits\Txn\Rj\EmrCompletenessRJTrait
    Bila trait di-update, sinkronkan konten panduan ini.
--}}

<div>
    <x-modal name="info-kelengkapan-emr-rj" size="2xl" focusable>
        <div class="flex flex-col">

            {{-- HEADER --}}
            <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/30">
                            <svg class="w-5 h-5 text-emerald-700 dark:text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                Panduan Kelengkapan EMR Rawat Jalan
                            </h2>
                            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                Persentase mencapai <span class="font-semibold text-emerald-600 dark:text-emerald-400">100%</span> jika seluruh field wajib di bawah ini terisi.
                            </p>
                        </div>
                    </div>
                    <x-icon-button color="gray" type="button"
                        x-on:click="$dispatch('close-modal', { name: 'info-kelengkapan-emr-rj' })">
                        <span class="sr-only">Tutup</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="px-6 py-5 space-y-5 max-h-[70vh] overflow-y-auto">

                {{-- Legenda warna --}}
                <div class="p-3 border border-gray-200 rounded-lg bg-gray-50 dark:bg-gray-900/40 dark:border-gray-700">
                    <p class="mb-2 text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Legenda Warna Progress</p>
                    <div class="flex flex-wrap items-center gap-4 text-sm">
                        <div class="flex items-center gap-2">
                            <span class="inline-block w-4 h-2 rounded bg-rose-400/80 dark:bg-rose-400"></span>
                            <span class="text-gray-700 dark:text-gray-300">&lt; 50% — kurang</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="inline-block w-4 h-2 rounded bg-amber-400/80 dark:bg-amber-400"></span>
                            <span class="text-gray-700 dark:text-gray-300">50–79% — sedang</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="inline-block w-4 h-2 rounded bg-emerald-500/80 dark:bg-emerald-400"></span>
                            <span class="text-gray-700 dark:text-gray-300">≥ 80% — lengkap</span>
                        </div>
                    </div>
                </div>

                {{-- Aturan umum "terisi" --}}
                <div class="p-3 border-l-4 border-blue-500 rounded bg-blue-50 dark:bg-blue-900/20">
                    <p class="text-sm text-gray-700 dark:text-gray-300">
                        <span class="font-semibold">Aturan field "screening" (alergi, riwayat penyakit dahulu):</span>
                        boleh diisi <em>"Tidak ada"</em> bila negatif — yang penting <strong>jangan dibiarkan kosong</strong>.
                        Dokter wajib explicit mendokumentasikan negatif sebagai bagian dari pengkajian.
                    </p>
                </div>

                {{-- S — Anamnesa --}}
                <div class="overflow-hidden border border-gray-200 rounded-lg dark:border-gray-700">
                    <div class="flex items-center justify-between px-4 py-2 bg-blue-50 dark:bg-blue-900/20">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center justify-center w-6 h-6 text-sm font-bold text-blue-700 bg-blue-100 rounded-full dark:bg-blue-900/40 dark:text-blue-300">S</span>
                            <span class="font-semibold text-gray-800 dark:text-gray-200">Anamnesa</span>
                        </div>
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Bobot 15%</span>
                    </div>
                    <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                        <li class="flex items-start gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300">
                            <span class="text-emerald-600 dark:text-emerald-400">✓</span>
                            <div><strong>Keluhan utama</strong> — alasan kunjungan (konten klinis real)</div>
                        </li>
                        <li class="flex items-start gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300">
                            <span class="text-emerald-600 dark:text-emerald-400">✓</span>
                            <div><strong>Riwayat penyakit sekarang</strong> — kronologi keluhan saat ini</div>
                        </li>
                        <li class="flex items-start gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300">
                            <span class="text-emerald-600 dark:text-emerald-400">✓</span>
                            <div><strong>Alergi</strong> <span class="text-xs text-gray-500">(screening — boleh "Tidak ada")</span></div>
                        </li>
                        <li class="flex items-start gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300">
                            <span class="text-emerald-600 dark:text-emerald-400">✓</span>
                            <div><strong>Riwayat penyakit dahulu</strong> <span class="text-xs text-gray-500">(screening — boleh "Tidak ada")</span></div>
                        </li>
                        <li class="flex items-start gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300">
                            <span class="text-emerald-600 dark:text-emerald-400">✓</span>
                            <div><strong>Waktu datang</strong> (jam datang) — audit trail</div>
                        </li>
                    </ul>
                </div>

                {{-- O — Pemeriksaan --}}
                <div class="overflow-hidden border border-gray-200 rounded-lg dark:border-gray-700">
                    <div class="flex items-center justify-between px-4 py-2 bg-emerald-50 dark:bg-emerald-900/20">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center justify-center w-6 h-6 text-sm font-bold rounded-full text-emerald-700 bg-emerald-100 dark:bg-emerald-900/40 dark:text-emerald-300">O</span>
                            <span class="font-semibold text-gray-800 dark:text-gray-200">Pemeriksaan</span>
                        </div>
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Bobot 25%</span>
                    </div>
                    <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                        <li class="flex items-start gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300">
                            <span class="text-emerald-600 dark:text-emerald-400">✓</span>
                            <div><strong>Frekuensi nadi</strong>, <strong>frekuensi napas</strong>, <strong>suhu</strong></div>
                        </li>
                        <li class="flex items-start gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300">
                            <span class="text-emerald-600 dark:text-emerald-400">✓</span>
                            <div><strong>Tekanan darah</strong> (sistolik & distolik)</div>
                        </li>
                        <li class="flex items-start gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300">
                            <span class="text-emerald-600 dark:text-emerald-400">✓</span>
                            <div><strong>Tingkat kesadaran</strong> (Alert/Voice/Pain/Unresponsive, dst)</div>
                        </li>
                        <li class="flex items-start gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300">
                            <span class="text-emerald-600 dark:text-emerald-400">✓</span>
                            <div><strong>Berat badan</strong> & <strong>tinggi badan</strong></div>
                        </li>
                    </ul>
                </div>

                {{-- A — Diagnosa --}}
                <div class="overflow-hidden border border-gray-200 rounded-lg dark:border-gray-700">
                    <div class="flex items-center justify-between px-4 py-2 bg-amber-50 dark:bg-amber-900/20">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center justify-center w-6 h-6 text-sm font-bold rounded-full text-amber-700 bg-amber-100 dark:bg-amber-900/40 dark:text-amber-300">A</span>
                            <span class="font-semibold text-gray-800 dark:text-gray-200">Diagnosa</span>
                        </div>
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Bobot 25%</span>
                    </div>
                    <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                        <li class="flex items-start gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300">
                            <span class="text-emerald-600 dark:text-emerald-400">✓</span>
                            <div>Minimal <strong>1 diagnosa</strong> — ICD-10 <em>ATAU</em> diagnosa bebas (free-text)</div>
                        </li>
                    </ul>
                </div>

                {{-- P — Perencanaan --}}
                <div class="overflow-hidden border border-gray-200 rounded-lg dark:border-gray-700">
                    <div class="flex items-center justify-between px-4 py-2 bg-rose-50 dark:bg-rose-900/20">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center justify-center w-6 h-6 text-sm font-bold rounded-full text-rose-700 bg-rose-100 dark:bg-rose-900/40 dark:text-rose-300">P</span>
                            <span class="font-semibold text-gray-800 dark:text-gray-200">Perencanaan</span>
                        </div>
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Bobot 25%</span>
                    </div>
                    <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                        <li class="flex items-start gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300">
                            <span class="text-emerald-600 dark:text-emerald-400">✓</span>
                            <div><strong>Terapi</strong> (auto-isi dari E-Resep) <em>ATAU</em> <strong>tindak lanjut</strong> (MRS / Kontrol / Rujuk / PRB / Perawatan Selesai)</div>
                        </li>
                        <li class="flex items-start gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300">
                            <span class="text-emerald-600 dark:text-emerald-400">✓</span>
                            <div><strong>TTD dokter pemeriksa</strong> (wajib — Permenkes 24/2022)</div>
                        </li>
                    </ul>
                </div>

                {{-- N — Penilaian --}}
                <div class="overflow-hidden border border-gray-200 rounded-lg dark:border-gray-700">
                    <div class="flex items-center justify-between px-4 py-2 bg-purple-50 dark:bg-purple-900/20">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center justify-center w-6 h-6 text-sm font-bold text-purple-700 bg-purple-100 rounded-full dark:bg-purple-900/40 dark:text-purple-300">N</span>
                            <span class="font-semibold text-gray-800 dark:text-gray-200">Penilaian</span>
                        </div>
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Bobot 10%</span>
                    </div>
                    <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                        <li class="flex items-start gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300">
                            <span class="text-emerald-600 dark:text-emerald-400">✓</span>
                            <div><strong>Penilaian nyeri</strong> — minimal 1 entry (NRS / BPS / NIPS / FLACC / VAS)</div>
                        </li>
                        <li class="flex items-start gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300">
                            <span class="text-emerald-600 dark:text-emerald-400">✓</span>
                            <div><strong>Penilaian risiko jatuh</strong> — minimal 1 entry (Morse / Humpty Dumpty)</div>
                        </li>
                    </ul>
                </div>

                {{-- Catatan tambahan --}}
                <div class="p-3 border border-gray-200 rounded-lg bg-gray-50 dark:bg-gray-900/40 dark:border-gray-700">
                    <p class="text-xs text-gray-600 dark:text-gray-400">
                        <span class="font-semibold">Catatan:</span> Field-field di luar daftar ini (mis. EEG / EMG / Raven Test, anamnesa lanjutan, anatomi 29 part tubuh)
                        bersifat opsional / spesialistik — <em>tidak</em> dihitung ke persentase.
                        Standar mengikuti <strong>Permenkes 24/2022</strong> tentang RME & <strong>SNARS Ed. 1.1</strong>.
                    </p>
                </div>

            </div>

            {{-- FOOTER --}}
            <div class="flex justify-end px-6 py-4 border-t border-gray-200 bg-gray-50 dark:bg-gray-900/40 dark:border-gray-700">
                <x-secondary-button type="button"
                    x-on:click="$dispatch('close-modal', { name: 'info-kelengkapan-emr-rj' })">
                    Mengerti
                </x-secondary-button>
            </div>

        </div>
    </x-modal>
</div>
