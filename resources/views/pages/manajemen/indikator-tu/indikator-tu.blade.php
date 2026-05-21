<?php

use Livewire\Component;

new class extends Component {
    //
};
?>

<div>
    <x-page-title
        title="Indikator Tu"
        subtitle="Oversight Kas, Hutang &amp; Administrasi — Supervisor Tu / Manager Umum" />

    <div class="w-full min-h-[calc(100vh-5rem-72px)] bg-white dark:bg-gray-800">
        <div class="px-6 py-10 space-y-8">

            {{-- SECTION 1: KAS HARIAN --}}
            <div class="max-w-3xl mx-auto">
                <h3 class="mb-3 text-sm font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400">
                    Kas Harian
                </h3>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <a href="{{ route('keuangan.penerimaan-kas-tu') }}" wire:navigate
                        class="flex items-start gap-3 p-4 transition-colors bg-white border border-gray-200 group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-emerald-50 text-emerald-700 group-hover:bg-emerald-100 dark:bg-emerald-900/30 dark:text-emerald-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-gray-800 dark:text-gray-100">Penerimaan Kas TU</div>
                            <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                Kas masuk di luar transaksi pelayanan
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('keuangan.pengeluaran-kas-tu') }}" wire:navigate
                        class="flex items-start gap-3 p-4 transition-colors bg-white border border-gray-200 group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-rose-50 text-rose-700 group-hover:bg-rose-100 dark:bg-rose-900/30 dark:text-rose-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-gray-800 dark:text-gray-100">Pengeluaran Kas TU</div>
                            <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                Kas keluar di luar transaksi pelayanan
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('keuangan.saldo-kas') }}" wire:navigate
                        class="flex items-start gap-3 p-4 transition-colors bg-white border border-gray-200 group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-amber-50 text-amber-700 group-hover:bg-amber-100 dark:bg-amber-900/30 dark:text-amber-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2zm0 0V5a2 2 0 012-2h4l2 2h4a2 2 0 012 2v0" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-gray-800 dark:text-gray-100">Saldo Kas</div>
                            <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                Posisi saldo kas/bank per tanggal
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            {{-- SECTION 2: HUTANG & SETOR DP SUPPLIER --}}
            <div class="max-w-3xl mx-auto">
                <h3 class="mb-3 text-sm font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400">
                    Hutang &amp; Setor DP Supplier
                </h3>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <a href="{{ route('keuangan.pembayaran-hutang-pbf') }}" wire:navigate
                        class="flex items-start gap-3 p-4 transition-colors bg-white border border-gray-200 group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-violet-50 text-violet-700 group-hover:bg-violet-100 dark:bg-violet-900/30 dark:text-violet-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-gray-800 dark:text-gray-100">Pembayaran Hutang PBF</div>
                            <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                Pelunasan / angsuran hutang supplier obat
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('keuangan.pembayaran-hutang-non-medis') }}" wire:navigate
                        class="flex items-start gap-3 p-4 transition-colors bg-white border border-gray-200 group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-yellow-50 text-yellow-700 group-hover:bg-yellow-100 dark:bg-yellow-900/30 dark:text-yellow-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-gray-800 dark:text-gray-100">Pembayaran Hutang Non-Medis</div>
                            <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                Pelunasan / angsuran hutang supplier non-medis
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('keuangan.topup-supplier-pbf') }}" wire:navigate
                        class="flex items-start gap-3 p-4 transition-colors bg-white border border-gray-200 group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-violet-50 text-violet-700 group-hover:bg-violet-100 dark:bg-violet-900/30 dark:text-violet-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-gray-800 dark:text-gray-100">Setor DP Supplier PBF</div>
                            <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                Uang muka ke supplier obat (PBF)
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('keuangan.topup-supplier-non-medis') }}" wire:navigate
                        class="flex items-start gap-3 p-4 transition-colors bg-white border border-gray-200 group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-yellow-50 text-yellow-700 group-hover:bg-yellow-100 dark:bg-yellow-900/30 dark:text-yellow-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-gray-800 dark:text-gray-100">Setor DP Supplier Non-Medis</div>
                            <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                Uang muka ke supplier non-medis
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            {{-- SECTION 3: STOK NON-MEDIS & ADMINISTRASI --}}
            <div class="max-w-3xl mx-auto">
                <h3 class="mb-3 text-sm font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400">
                    Stok Non-Medis &amp; Administrasi
                </h3>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <a href="{{ route('gudang.kartu-stock-non') }}" wire:navigate
                        class="flex items-start gap-3 p-4 transition-colors bg-white border border-gray-200 group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-yellow-50 text-yellow-700 group-hover:bg-yellow-100 dark:bg-yellow-900/30 dark:text-yellow-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-gray-800 dark:text-gray-100">Kartu Stock Non-Medis</div>
                            <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                Riwayat mutasi stok &mdash; Gudang Non-Medis
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('manajemen.sirs.ri.laporan-rl-3-19-cara-bayar') }}" wire:navigate
                        class="flex items-start gap-3 p-4 transition-colors bg-white border border-gray-200 group rounded-xl hover:bg-brand-green/10 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-brand-lime/15">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-emerald-50 text-emerald-700 group-hover:bg-emerald-100 dark:bg-emerald-900/30 dark:text-emerald-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <div class="font-semibold text-gray-800 dark:text-gray-100">RL 3.19 Cara Bayar</div>
                            <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                Laporan SIRS Online &mdash; cara bayar pasien
                            </div>
                        </div>
                    </a>
                </div>
            </div>

        </div>
    </div>
</div>
