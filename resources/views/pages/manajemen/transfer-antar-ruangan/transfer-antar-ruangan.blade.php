<?php

use Livewire\Component;

new class extends Component {
    /**
     * Mode breakdown:
     *   - 'bulanan' → tab "Tahunan"     (1 tahun, breakdown per bulan)
     *   - 'tahunan' → tab "Multi-Tahun" (range tahun, breakdown per tahun)
     */
    public string $mode = 'bulanan';

    public function setMode(string $mode): void
    {
        if (in_array($mode, ['bulanan', 'tahunan'], true)) {
            $this->mode = $mode;
        }
    }
};
?>

<div>
    <header class="bg-white shadow dark:bg-gray-800">
        <div class="w-full px-4 py-2 sm:px-6 lg:px-8 flex items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                    Laporan Transfer Antar Ruangan
                </h2>
                <p class="text-base text-gray-700 dark:text-gray-700">
                    Rekap transfer stok antar ruangan &mdash; <span class="font-medium">Tahunan</span> (1 tahun, breakdown per bulan)
                    atau <span class="font-medium">Multi-Tahun</span> (rentang yyyy&ndash;yyyy, breakdown per tahun).
                    <span class="text-gray-400">Gabungan medis &amp; non-medis, atau filter per kategori.</span>
                </p>
            </div>
            <a href="{{ route('manajemen.indikator-pelayanan') }}" wire:navigate
                class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-200 dark:border-gray-700 dark:hover:bg-gray-800 shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Kembali
            </a>
        </div>
    </header>

    <div class="w-full min-h-[calc(100vh-5rem-72px)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6">

            {{-- TAB SWITCHER (sticky) --}}
            <div class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center gap-3">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Periode:</span>
                    <div class="inline-flex rounded-lg border border-gray-300 dark:border-gray-600 overflow-hidden">
                        <button type="button" wire:click="setMode('bulanan')"
                            class="px-4 py-2 text-sm font-medium transition-colors
                                {{ $mode === 'bulanan'
                                    ? 'bg-brand-green text-white dark:bg-brand-lime dark:text-slate-900'
                                    : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800' }}"
                            title="1 tahun — breakdown per bulan (Januari–Desember)">
                            Tahunan
                        </button>
                        <button type="button" wire:click="setMode('tahunan')"
                            class="px-4 py-2 text-sm font-medium transition-colors border-l border-gray-300 dark:border-gray-600
                                {{ $mode === 'tahunan'
                                    ? 'bg-brand-green text-white dark:bg-brand-lime dark:text-slate-900'
                                    : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800' }}"
                            title="Rentang tahun yyyy–yyyy — breakdown per tahun">
                            Multi-Tahun
                        </button>
                    </div>
                </div>
            </div>

            {{-- Shared Chart.js loader — register window.chartTransferAntarRuangan() sekali --}}
            @once
                <script>
                    window.chartTransferAntarRuangan = function(data) {
                        return {
                            chart: null,
                            _loadChartJs() {
                                return new Promise((resolve, reject) => {
                                    if (window.Chart) return resolve();
                                    let tag = document.querySelector('script[data-chartjs]');
                                    if (tag) {
                                        tag.addEventListener('load', resolve);
                                        tag.addEventListener('error', reject);
                                        return;
                                    }
                                    tag = document.createElement('script');
                                    tag.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
                                    tag.defer = tag.async = true;
                                    tag.dataset.chartjs = '';
                                    tag.onload = resolve;
                                    tag.onerror = reject;
                                    document.head.appendChild(tag);
                                });
                            },
                            async init() {
                                await this._loadChartJs();
                                const canvas = this.$refs.canvas;
                                if (!canvas || this.chart) return;
                                this.chart = new Chart(canvas.getContext('2d'), {
                                    type: 'bar',
                                    data: {
                                        labels: data.labels,
                                        datasets: [
                                            {
                                                label: 'Sudah Diproses',
                                                data: data.posted,
                                                backgroundColor: 'rgba(16,185,129,0.75)',
                                                borderColor: 'rgb(5,150,105)',
                                                borderWidth: 1,
                                                stack: 'status',
                                            },
                                            {
                                                label: 'Belum Diproses',
                                                data: data.draft,
                                                backgroundColor: 'rgba(245,158,11,0.75)',
                                                borderColor: 'rgb(217,119,6)',
                                                borderWidth: 1,
                                                stack: 'status',
                                            },
                                            {
                                                label: 'Dibatalkan',
                                                data: data.batal,
                                                backgroundColor: 'rgba(244,63,94,0.75)',
                                                borderColor: 'rgb(225,29,72)',
                                                borderWidth: 1,
                                                stack: 'status',
                                            },
                                            {
                                                type: 'line',
                                                label: 'Total Transfer',
                                                data: data.total_trf,
                                                borderColor: 'rgb(37,99,235)',
                                                backgroundColor: 'rgba(37,99,235,0.1)',
                                                borderWidth: 2,
                                                pointRadius: 3,
                                                tension: 0.3,
                                                fill: false,
                                            },
                                        ],
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        interaction: { mode: 'index', intersect: false },
                                        plugins: {
                                            legend: { position: 'top', align: 'end' },
                                            tooltip: {
                                                callbacks: {
                                                    footer: (items) => {
                                                        const idx = items[0].dataIndex;
                                                        return `Total Qty: ${(data.total_qty[idx] || 0).toLocaleString('id-ID')}`;
                                                    },
                                                },
                                            },
                                        },
                                        scales: {
                                            x: { stacked: true, grid: { display: false } },
                                            y: {
                                                stacked: true,
                                                beginAtZero: true,
                                                ticks: { callback: (v) => v.toLocaleString('id-ID') },
                                            },
                                        },
                                    },
                                });
                            },
                            destroy() {
                                if (this.chart) {
                                    this.chart.destroy();
                                    this.chart = null;
                                }
                            },
                        };
                    };
                </script>
            @endonce

            {{-- CHILD CONTENT --}}
            @if ($mode === 'bulanan')
                <livewire:pages::manajemen.transfer-antar-ruangan.transfer-antar-ruangan-bulanan wire:key="transfer-antar-ruangan-bulanan" />
            @else
                <livewire:pages::manajemen.transfer-antar-ruangan.transfer-antar-ruangan-tahunan wire:key="transfer-antar-ruangan-tahunan" />
            @endif

        </div>
    </div>
</div>
