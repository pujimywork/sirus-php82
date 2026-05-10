<?php

use Livewire\Component;

new class extends Component {
    //
};
?>

<div>
    <header class="bg-white shadow dark:bg-gray-800">
        <div class="w-full px-4 py-2 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                Indikator Pelayanan
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                BOR / ALOS / TOI / BTO &mdash; tren bulanan & tahunan
            </p>
        </div>
    </header>

    <div class="w-full min-h-[calc(100vh-5rem-72px)] bg-white dark:bg-gray-800">
        <div class="px-6 py-10 space-y-8">

            {{-- ═══════════════════════════════════════════════════════ --}}
            {{-- SECTION 1: LAPORAN INTERNAL                             --}}
            {{-- ═══════════════════════════════════════════════════════ --}}
            <div class="max-w-3xl mx-auto">
                <h3 class="mb-3 text-sm font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400">
                    Laporan Internal
                </h3>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <a href="{{ route('manajemen.rj.laporan-task-id-rj') }}" wire:navigate
                        class="flex items-start gap-3 p-4 transition-colors bg-white border border-gray-200 group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-emerald-50 text-emerald-700 group-hover:bg-emerald-100 dark:bg-emerald-900/30 dark:text-emerald-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9 17v-6m4 6V7m4 10v-4M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-gray-800 dark:text-gray-100">Laporan Task ID Antrian RJ</div>
                            <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                Rekap waktu pelayanan BPJS Antrol (Task 1&ndash;7) per bulan
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('manajemen.ugd.laporan-task-id-ugd') }}" wire:navigate
                        class="flex items-start gap-3 p-4 transition-colors bg-white border border-gray-200 group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-rose-50 text-rose-700 group-hover:bg-rose-100 dark:bg-rose-900/30 dark:text-rose-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-gray-800 dark:text-gray-100">Laporan Task ID Antrian UGD</div>
                            <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                Rekap waktu Masuk UGD &rarr; Masuk Apotek &rarr; Obat Selesai per bulan
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('manajemen.rj.laporan-kunjungan-rj') }}" wire:navigate
                        class="flex items-start gap-3 p-4 transition-colors bg-white border border-gray-200 group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-blue-50 text-blue-700 group-hover:bg-blue-100 dark:bg-blue-900/30 dark:text-blue-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-gray-800 dark:text-gray-100">Laporan Kunjungan RJ</div>
                            <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                Tahunan / Multi-Tahun &mdash; BPJS/UMUM, pasien baru/lama, breakdown poli
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('manajemen.ugd.laporan-kunjungan-ugd') }}" wire:navigate
                        class="flex items-start gap-3 p-4 transition-colors bg-white border border-gray-200 group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-orange-50 text-orange-700 group-hover:bg-orange-100 dark:bg-orange-900/30 dark:text-orange-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-gray-800 dark:text-gray-100">Laporan Kunjungan UGD</div>
                            <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                Tahunan / Multi-Tahun &mdash; BPJS/UMUM, pasien baru/lama, breakdown dokter, triase P1-P4
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('manajemen.ri.laporan-kunjungan-ri') }}" wire:navigate
                        class="flex items-start gap-3 p-4 transition-colors bg-white border border-gray-200 group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-purple-50 text-purple-700 group-hover:bg-purple-100 dark:bg-purple-900/30 dark:text-purple-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-gray-800 dark:text-gray-100">Laporan Kunjungan RI</div>
                            <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                Tahunan / Multi-Tahun &mdash; BPJS/UMUM, ALOS, breakdown bangsal (filter exit_date)
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('manajemen.lab.laporan-permintaan-lab') }}" wire:navigate
                        class="flex items-start gap-3 p-4 transition-colors bg-white border border-gray-200 group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-cyan-50 text-cyan-700 group-hover:bg-cyan-100 dark:bg-cyan-900/30 dark:text-cyan-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-gray-800 dark:text-gray-100">Laporan Permintaan Lab</div>
                            <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                Gabungan RJ/UGD/RI &mdash; total, BPJS/UMUM, revenue, top dokter pengirim
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('manajemen.rad.laporan-permintaan-rad') }}" wire:navigate
                        class="flex items-start gap-3 p-4 transition-colors bg-white border border-gray-200 group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-pink-50 text-pink-700 group-hover:bg-pink-100 dark:bg-pink-900/30 dark:text-pink-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-gray-800 dark:text-gray-100">Laporan Permintaan Radiologi</div>
                            <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                Gabungan RJ/UGD/RI &mdash; total, BPJS/UMUM, revenue, top dokter pengirim
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('manajemen.lab.laporan-pemeriksaan-lab') }}" wire:navigate
                        class="flex items-start gap-3 p-4 transition-colors bg-white border border-gray-200 group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-teal-50 text-teal-700 group-hover:bg-teal-100 dark:bg-teal-900/30 dark:text-teal-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-gray-800 dark:text-gray-100">Laporan Pemeriksaan Lab</div>
                            <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                Item-level &mdash; volume servis, top 20 item lab terbanyak (Hb, GDS, dst.)
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('manajemen.rad.laporan-pemeriksaan-rad') }}" wire:navigate
                        class="flex items-start gap-3 p-4 transition-colors bg-white border border-gray-200 group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-fuchsia-50 text-fuchsia-700 group-hover:bg-fuchsia-100 dark:bg-fuchsia-900/30 dark:text-fuchsia-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-gray-800 dark:text-gray-100">Laporan Pemeriksaan Radiologi</div>
                            <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                Item-level &mdash; volume servis, top 20 item radiologi terbanyak (Foto Thorax, USG, dst.)
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            {{-- ═══════════════════════════════════════════════════════ --}}
            {{-- SECTION 2: LAPORAN SIRS ONLINE (KEMENKES)               --}}
            {{-- ═══════════════════════════════════════════════════════ --}}
            <div class="max-w-3xl mx-auto">
                <div class="mb-3 flex items-center gap-2">
                    <h3 class="text-sm font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400">
                        Laporan SIRS Online
                    </h3>
                    <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-medium rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                        Kemenkes
                    </span>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <a href="{{ route('manajemen.ri.laporan-rl-3-2-rawat-inap') }}" wire:navigate
                        class="flex items-start gap-3 p-4 transition-colors bg-white border border-gray-200 group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-indigo-50 text-indigo-700 group-hover:bg-indigo-100 dark:bg-indigo-900/30 dark:text-indigo-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9 17v-6h13M9 11V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-2H9z" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-gray-800 dark:text-gray-100">RL 3.2 Rawat Inap</div>
                            <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                Rekap RI per jenis pelayanan, per bulan (37 row, 17 metrik)
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('manajemen.ugd.laporan-rl-3-3-rawat-darurat') }}" wire:navigate
                        class="flex items-start gap-3 p-4 transition-colors bg-white border border-gray-200 group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-red-50 text-red-700 group-hover:bg-red-100 dark:bg-red-900/30 dark:text-red-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-gray-800 dark:text-gray-100">RL 3.3 Rawat Darurat</div>
                            <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                Rekap UGD/IGD per jenis pelayanan, per bulan (13 row, 12 metrik)
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('manajemen.laporan-rl-3-4-pengunjung') }}" wire:navigate
                        class="flex items-start gap-3 p-4 transition-colors bg-white border border-gray-200 group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-sky-50 text-sky-700 group-hover:bg-sky-100 dark:bg-sky-900/30 dark:text-sky-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-gray-800 dark:text-gray-100">RL 3.4 Pengunjung</div>
                            <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                Pasien unik per bulan: Baru / Lama (lintas RJ+UGD+RI)
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('manajemen.laporan-rl-3-5-kunjungan') }}" wire:navigate
                        class="flex items-start gap-3 p-4 transition-colors bg-white border border-gray-200 group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-teal-50 text-teal-700 group-hover:bg-teal-100 dark:bg-teal-900/30 dark:text-teal-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-gray-800 dark:text-gray-100">RL 3.5 Kunjungan</div>
                            <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                Rekap visit RJ + UGD per jenis kegiatan, dalam/luar kota
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('manajemen.laporan-rl-3-8-laboratorium') }}" wire:navigate
                        class="flex items-start gap-3 p-4 transition-colors bg-white border border-gray-200 group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-cyan-50 text-cyan-700 group-hover:bg-cyan-100 dark:bg-cyan-900/30 dark:text-cyan-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-gray-800 dark:text-gray-100">RL 3.8 Laboratorium</div>
                            <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                Rekap pemeriksaan lab per jenis (138 jenis, gender, rata-rata/hari)
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('manajemen.laporan-rl-3-9-radiologi') }}" wire:navigate
                        class="flex items-start gap-3 p-4 transition-colors bg-white border border-gray-200 group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-pink-50 text-pink-700 group-hover:bg-pink-100 dark:bg-pink-900/30 dark:text-pink-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-gray-800 dark:text-gray-100">RL 3.9 Radiologi</div>
                            <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                Rekap rad per jenis (Foto/CT/USG/MRI/Radioterapi, 19 jenis)
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('manajemen.laporan-rl-3-15-kesehatan-jiwa') }}" wire:navigate
                        class="flex items-start gap-3 p-4 transition-colors bg-white border border-gray-200 group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-violet-50 text-violet-700 group-hover:bg-violet-100 dark:bg-violet-900/30 dark:text-violet-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-gray-800 dark:text-gray-100">RL 3.15 Kesehatan Jiwa</div>
                            <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                Tahunan &mdash; rekap kunjungan POLI PSIKIATRI per jenis kegiatan (8 jenis)
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('manajemen.laporan-rl-3-19-cara-bayar') }}" wire:navigate
                        class="flex items-start gap-3 p-4 transition-colors bg-white border border-gray-200 group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-amber-50 text-amber-700 group-hover:bg-amber-100 dark:bg-amber-900/30 dark:text-amber-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-gray-800 dark:text-gray-100">RL 3.19 Cara Bayar</div>
                            <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                Tahunan &mdash; rekap pasien per cara bayar (BPJS/UMUM/Asuransi/dll, 9 cara bayar)
                            </div>
                        </div>
                    </a>
                </div>
                <p class="mt-3 text-[10px] text-gray-400 dark:text-gray-500 text-center">
                    Format SIRS Online Kemenkes &mdash; <a href="https://sirs6.kemkes.go.id" class="hover:underline" target="_blank" rel="noopener">sirs6.kemkes.go.id</a>
                </p>
            </div>

            {{-- Placeholder konten utama --}}
            <div class="max-w-2xl p-6 mx-auto text-center bg-white border border-dashed border-gray-300 rounded-xl dark:border-gray-600 dark:bg-gray-900">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Halaman dalam pengembangan</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Modul ini akan menampilkan grafik BOR (Bed Occupancy Rate), ALOS (Average Length of Stay),
                    TOI (Turn Over Interval), dan BTO (Bed Turn Over) dengan filter periode harian / bulanan / tahunan.
                </p>
            </div>

        </div>
    </div>
</div>
