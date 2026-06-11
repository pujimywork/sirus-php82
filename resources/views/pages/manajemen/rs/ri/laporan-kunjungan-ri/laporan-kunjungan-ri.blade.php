<?php

use Livewire\Component;

new class extends Component {
    /**
     * Internal mode = granularity breakdown:
     *   - 'bulanan' → tab "Tahunan"     (1 tahun, baris per bulan)
     *   - 'tahunan' → tab "Multi-Tahun" (range tahun, baris per tahun)
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
    <x-page-title
        title="Laporan Kunjungan Rawat Inap"
        subtitle="Rekap pasien RI yang pulang — Tahunan (1 tahun, breakdown per bulan) atau Multi-Tahun (rentang yyyy–yyyy, breakdown per tahun). Filter berdasarkan tanggal pulang (exit_date), pasien Kronis di-exclude." />

    <div class="w-full min-h-[calc(100vh-5rem)] bg-canvas dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6">

            {{-- TAB SWITCHER (sticky) --}}
            <div class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center gap-3">
                    <span class="text-sm font-medium text-body dark:text-gray-300">Periode:</span>
                    <div class="inline-flex rounded-lg border border-gray-300 dark:border-gray-600 overflow-hidden">
                        <button type="button" wire:click="setMode('bulanan')"
                            class="px-4 py-2 text-sm font-medium transition-colors
                                {{ $mode === 'bulanan'
                                    ? 'bg-brand-green text-white dark:bg-brand-lime dark:text-slate-900'
                                    : 'bg-canvas text-body hover:bg-surface-soft dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800' }}"
                            title="1 tahun — breakdown per bulan (Januari–Desember)">
                            Tahunan
                        </button>
                        <button type="button" wire:click="setMode('tahunan')"
                            class="px-4 py-2 text-sm font-medium transition-colors border-l border-gray-300 dark:border-gray-600
                                {{ $mode === 'tahunan'
                                    ? 'bg-brand-green text-white dark:bg-brand-lime dark:text-slate-900'
                                    : 'bg-canvas text-body hover:bg-surface-soft dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800' }}"
                            title="Rentang tahun yyyy–yyyy — breakdown per tahun">
                            Multi-Tahun
                        </button>
                    </div>
                </div>
            </div>

            {{-- Shared Chart.js loader untuk RI — bar BPJS/UMUM grouped + line ALOS overlay --}}
            @once
                <script>
                    window.chartKunjunganRI = function(data) {
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
                                                label: 'BPJS',
                                                data: data.bpjs,
                                                backgroundColor: 'rgba(16,185,129,0.75)',
                                                borderColor: 'rgb(5,150,105)',
                                                borderWidth: 1,
                                                yAxisID: 'y',
                                            },
                                            {
                                                label: 'UMUM',
                                                data: data.umum,
                                                backgroundColor: 'rgba(245,158,11,0.75)',
                                                borderColor: 'rgb(217,119,6)',
                                                borderWidth: 1,
                                                yAxisID: 'y',
                                            },
                                            {
                                                type: 'line',
                                                label: 'ALOS (hari)',
                                                data: data.alos,
                                                borderColor: 'rgb(147,51,234)',
                                                backgroundColor: 'rgba(147,51,234,0.1)',
                                                borderWidth: 2,
                                                pointRadius: 3,
                                                tension: 0.3,
                                                fill: false,
                                                yAxisID: 'y1',
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
                                                        return [
                                                            `Total: ${(data.total[idx] || 0).toLocaleString('id-ID')}`,
                                                            `Selesai: ${(data.selesai[idx] || 0).toLocaleString('id-ID')}`,
                                                        ];
                                                    },
                                                },
                                            },
                                        },
                                        scales: {
                                            x: { grid: { display: false } },
                                            y: {
                                                type: 'linear', position: 'left',
                                                beginAtZero: true,
                                                ticks: { callback: (v) => v.toLocaleString('id-ID') },
                                                title: { display: true, text: 'Pasien' },
                                            },
                                            y1: {
                                                type: 'linear', position: 'right',
                                                beginAtZero: true,
                                                grid: { display: false },
                                                ticks: { callback: (v) => v + 'h' },
                                                title: { display: true, text: 'ALOS (hari)' },
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
                <livewire:pages::manajemen.rs.ri.laporan-kunjungan-ri.laporan-kunjungan-ri-bulanan wire:key="laporan-kunjungan-ri-bulanan" />
            @else
                <livewire:pages::manajemen.rs.ri.laporan-kunjungan-ri.laporan-kunjungan-ri-tahunan wire:key="laporan-kunjungan-ri-tahunan" />
            @endif

        </div>
    </div>
</div>
