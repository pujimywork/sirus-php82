<?php

use Livewire\Component;

new class extends Component {
    //
};
?>

<div>
    <x-page-title
        title="Monitoring Keuangan"
        subtitle="Pendapatan jasa, kas/bank, dan arus keuangan rumah sakit" />

    <div class="w-full min-h-[calc(100vh-5rem)] bg-white dark:bg-gray-800">
        <div class="px-6 py-10 space-y-8">

            {{-- ═══════════════════════════════════════════════════════ --}}
            {{-- SECTION 0: REKAP TOTAL                                  --}}
            {{-- ═══════════════════════════════════════════════════════ --}}
            <div class="max-w-3xl mx-auto">
                <h3 class="mb-3 text-sm font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400">
                    Rekap Total
                </h3>
                <div class="grid grid-cols-1 gap-3">
                    <a href="{{ route('manajemen.rs.tu.pendapatan-rs') }}" wire:navigate
                        class="flex items-start gap-3 p-4 transition-colors bg-white border border-gray-200 group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-slate-100 text-slate-700 group-hover:bg-slate-200 dark:bg-slate-700/40 dark:text-slate-200">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-gray-800 dark:text-gray-100">Pendapatan RS Keseluruhan</div>
                            <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                Total revenue RJ + UGD + RI &mdash; tahunan / multi-tahun, breakdown per modul
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            {{-- ═══════════════════════════════════════════════════════ --}}
            {{-- SECTION 1: PENDAPATAN JASA                             --}}
            {{-- ═══════════════════════════════════════════════════════ --}}
            <div class="max-w-3xl mx-auto">
                <h3 class="mb-3 text-sm font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400">
                    Pendapatan Jasa
                </h3>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <a href="{{ route('manajemen.rs.tu.pendapatan-jasa-dokter') }}" wire:navigate
                        class="flex items-start gap-3 p-4 transition-colors bg-white border border-gray-200 group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-emerald-50 text-emerald-700 group-hover:bg-emerald-100 dark:bg-emerald-900/30 dark:text-emerald-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-gray-800 dark:text-gray-100">Pendapatan Jasa Dokter</div>
                            <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                Rekap jasa medis dokter per bulan &mdash; disetujui / belum disetujui BPJS
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('manajemen.rs.tu.pendapatan-jasa-medis') }}" wire:navigate
                        class="flex items-start gap-3 p-4 transition-colors bg-white border border-gray-200 group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-sky-50 text-sky-700 group-hover:bg-sky-100 dark:bg-sky-900/30 dark:text-sky-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-gray-800 dark:text-gray-100">Pendapatan Jasa Medis</div>
                            <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                Revenue paket jasa medis per bulan &mdash; RJ / UGD / RI
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('manajemen.rs.tu.pendapatan-jasa-karyawan') }}" wire:navigate
                        class="flex items-start gap-3 p-4 transition-colors bg-white border border-gray-200 group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-amber-50 text-amber-700 group-hover:bg-amber-100 dark:bg-amber-900/30 dark:text-amber-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-gray-800 dark:text-gray-100">Pendapatan Jasa Karyawan</div>
                            <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                Revenue paket jasa karyawan per bulan &mdash; RJ / UGD
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            {{-- ═══════════════════════════════════════════════════════ --}}
            {{-- SECTION 2: KAS & BANK (placeholder)                     --}}
            {{-- ═══════════════════════════════════════════════════════ --}}
            <div class="max-w-3xl mx-auto">
                <h3 class="mb-3 text-sm font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400">
                    Kas &amp; Bank
                </h3>
                <div class="p-6 text-center bg-white border border-dashed border-gray-300 rounded-xl dark:border-gray-600 dark:bg-gray-900">
                    <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Dalam pengembangan</h4>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Posisi saldo kas/bank real-time, arus kas masuk &amp; keluar per hari / bulan / tahun.
                    </p>
                </div>
            </div>

        </div>
    </div>
</div>
