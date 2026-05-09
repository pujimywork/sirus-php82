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
        <div class="px-6 py-10 space-y-6">

            {{-- Pintasan laporan terkait --}}
            <div class="max-w-3xl mx-auto">
                <h3 class="mb-3 text-sm font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400">
                    Laporan Terkait
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
                </div>
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
