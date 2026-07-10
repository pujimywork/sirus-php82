<?php

use Livewire\Component;

new class extends Component {
    //
};
?>

<div>
    <x-page-title
        title="Indikator Penunjang"
        subtitle="Oversight Laboratorium, Radiologi &amp; Apotek — Supervisor Penunjang / Manager Umum" />

    <div class="w-full min-h-[calc(100vh-5rem)] bg-canvas dark:bg-gray-800">
        <div class="px-6 py-10 space-y-8">

            {{-- SECTION 1: LABORATORIUM --}}
            <div class="max-w-3xl mx-auto">
                <h3 class="mb-3 text-sm font-semibold tracking-wider text-muted uppercase dark:text-gray-400">
                    Laboratorium
                </h3>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <a href="{{ route('manajemen.rs.penunjang.lab.laporan-permintaan-lab') }}" wire:navigate
                        class="flex items-start gap-3 p-4 transition-colors bg-canvas border border-hairline group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-cyan-50 text-cyan-700 group-hover:bg-cyan-100 dark:bg-cyan-900/30 dark:text-cyan-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-ink dark:text-gray-100">Laporan Permintaan Lab</div>
                            <div class="mt-0.5 text-xs text-muted dark:text-gray-400">
                                Rekap order pemeriksaan lab per bulan
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('manajemen.rs.penunjang.lab.laporan-pemeriksaan-lab') }}" wire:navigate
                        class="flex items-start gap-3 p-4 transition-colors bg-canvas border border-hairline group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-cyan-50 text-cyan-700 group-hover:bg-cyan-100 dark:bg-cyan-900/30 dark:text-cyan-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-ink dark:text-gray-100">Laporan Pemeriksaan Lab</div>
                            <div class="mt-0.5 text-xs text-muted dark:text-gray-400">
                                Hasil & detail pemeriksaan per bulan
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('manajemen.rs.penunjang.lab.laporan-pemeriksaan-dalam-luar') }}" wire:navigate
                        class="flex items-start gap-3 p-4 transition-colors bg-canvas border border-hairline group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-cyan-50 text-cyan-700 group-hover:bg-cyan-100 dark:bg-cyan-900/30 dark:text-cyan-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12h4l3 8 4-16 3 8h4" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-ink dark:text-gray-100">Pemeriksaan Lab Dalam &amp; Luar</div>
                            <div class="mt-0.5 text-xs text-muted dark:text-gray-400">
                                Detail per pasien + rekap — RS sendiri vs rujukan
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('manajemen.sirs.penunjang.laporan-rl-3-8-laboratorium') }}" wire:navigate
                        class="flex items-start gap-3 p-4 transition-colors bg-canvas border border-hairline group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-emerald-50 text-emerald-700 group-hover:bg-emerald-100 dark:bg-emerald-900/30 dark:text-emerald-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-ink dark:text-gray-100">RL 3.8 Laboratorium</div>
                            <div class="mt-0.5 text-xs text-muted dark:text-gray-400">
                                Laporan SIRS Online &mdash; pemeriksaan lab
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            {{-- SECTION 2: RADIOLOGI --}}
            <div class="max-w-3xl mx-auto">
                <h3 class="mb-3 text-sm font-semibold tracking-wider text-muted uppercase dark:text-gray-400">
                    Radiologi
                </h3>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <a href="{{ route('manajemen.rs.penunjang.rad.laporan-permintaan-rad') }}" wire:navigate
                        class="flex items-start gap-3 p-4 transition-colors bg-canvas border border-hairline group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-fuchsia-50 text-fuchsia-700 group-hover:bg-fuchsia-100 dark:bg-fuchsia-900/30 dark:text-fuchsia-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-ink dark:text-gray-100">Laporan Permintaan Radiologi</div>
                            <div class="mt-0.5 text-xs text-muted dark:text-gray-400">
                                Rekap order pemeriksaan rad per bulan
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('manajemen.rs.penunjang.rad.laporan-pemeriksaan-rad') }}" wire:navigate
                        class="flex items-start gap-3 p-4 transition-colors bg-canvas border border-hairline group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-fuchsia-50 text-fuchsia-700 group-hover:bg-fuchsia-100 dark:bg-fuchsia-900/30 dark:text-fuchsia-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-ink dark:text-gray-100">Laporan Pemeriksaan Radiologi</div>
                            <div class="mt-0.5 text-xs text-muted dark:text-gray-400">
                                Hasil & detail pemeriksaan rad per bulan
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('manajemen.sirs.penunjang.laporan-rl-3-9-radiologi') }}" wire:navigate
                        class="flex items-start gap-3 p-4 transition-colors bg-canvas border border-hairline group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-emerald-50 text-emerald-700 group-hover:bg-emerald-100 dark:bg-emerald-900/30 dark:text-emerald-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-ink dark:text-gray-100">RL 3.9 Radiologi</div>
                            <div class="mt-0.5 text-xs text-muted dark:text-gray-400">
                                Laporan SIRS Online &mdash; pemeriksaan rad
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            {{-- SECTION 3: APOTEK / OBAT --}}
            <div class="max-w-3xl mx-auto">
                <h3 class="mb-3 text-sm font-semibold tracking-wider text-muted uppercase dark:text-gray-400">
                    Apotek &amp; Stok Obat
                </h3>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <a href="{{ route('manajemen.mutasi-obat') }}" wire:navigate
                        class="flex items-start gap-3 p-4 transition-colors bg-canvas border border-hairline group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-amber-50 text-amber-700 group-hover:bg-amber-100 dark:bg-amber-900/30 dark:text-amber-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-ink dark:text-gray-100">Mutasi Obat</div>
                            <div class="mt-0.5 text-xs text-muted dark:text-gray-400">
                                Keluar masuk obat per gudang & per unit
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('gudang.kartu-stock') }}" wire:navigate
                        class="flex items-start gap-3 p-4 transition-colors bg-canvas border border-hairline group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-lime-50 text-lime-700 group-hover:bg-lime-100 dark:bg-lime-900/30 dark:text-lime-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-ink dark:text-gray-100">Kartu Stock Gudang Medis</div>
                            <div class="mt-0.5 text-xs text-muted dark:text-gray-400">
                                Riwayat mutasi stok per produk &mdash; Gudang Medis
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('gudang.kartu-stock-apt') }}" wire:navigate
                        class="flex items-start gap-3 p-4 transition-colors bg-canvas border border-hairline group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-amber-50 text-amber-700 group-hover:bg-amber-100 dark:bg-amber-900/30 dark:text-amber-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-ink dark:text-gray-100">Kartu Stock Apotek</div>
                            <div class="mt-0.5 text-xs text-muted dark:text-gray-400">
                                Riwayat mutasi stok per produk &mdash; Apotek
                            </div>
                        </div>
                    </a>
                </div>
            </div>

        </div>
    </div>
</div>
