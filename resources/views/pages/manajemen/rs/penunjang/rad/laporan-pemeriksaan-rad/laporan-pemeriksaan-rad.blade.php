<?php

use Livewire\Component;

new class extends Component {
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
        title="Laporan Pemeriksaan Radiologi"
        subtitle="Volume pelayanan radiologi — Tahunan / Multi-Tahun + ranking item radiologi terbanyak. Sumber: rstxn_*rads/riradiologs (item-level), filter rj_date / exit_date." />

    <div class="w-full min-h-[calc(100vh-5rem)] bg-white dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6">
            <div class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center gap-3">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Periode:</span>
                    <div class="inline-flex rounded-lg border border-gray-300 dark:border-gray-600 overflow-hidden">
                        <button type="button" wire:click="setMode('bulanan')"
                            class="px-4 py-2 text-sm font-medium transition-colors
                                {{ $mode === 'bulanan' ? 'bg-brand-green text-white dark:bg-brand-lime dark:text-slate-900' : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800' }}">
                            Tahunan
                        </button>
                        <button type="button" wire:click="setMode('tahunan')"
                            class="px-4 py-2 text-sm font-medium transition-colors border-l border-gray-300 dark:border-gray-600
                                {{ $mode === 'tahunan' ? 'bg-brand-green text-white dark:bg-brand-lime dark:text-slate-900' : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800' }}">
                            Multi-Tahun
                        </button>
                    </div>
                </div>
            </div>

            {{-- chartPermintaan factory di-share dari module Permintaan, tapi register juga di sini for safety --}}
            @once
                <script>
                    if (typeof window.chartPermintaan === 'undefined') {
                        window.chartPermintaan = function(data) {
                            return {
                                chart: null,
                                _loadChartJs() {
                                    return new Promise((resolve, reject) => {
                                        if (window.Chart) return resolve();
                                        let tag = document.querySelector('script[data-chartjs]');
                                        if (tag) { tag.addEventListener('load', resolve); tag.addEventListener('error', reject); return; }
                                        tag = document.createElement('script');
                                        tag.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
                                        tag.defer = tag.async = true; tag.dataset.chartjs = '';
                                        tag.onload = resolve; tag.onerror = reject;
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
                                                { label: 'RJ',  data: data.rj,  backgroundColor: 'rgba(16,185,129,0.75)', borderColor: 'rgb(5,150,105)',  borderWidth: 1 },
                                                { label: 'UGD', data: data.ugd, backgroundColor: 'rgba(245,158,11,0.75)', borderColor: 'rgb(217,119,6)',  borderWidth: 1 },
                                                { label: 'RI',  data: data.ri,  backgroundColor: 'rgba(147,51,234,0.75)', borderColor: 'rgb(126,34,206)', borderWidth: 1 },
                                            ],
                                        },
                                        options: {
                                            responsive: true, maintainAspectRatio: false,
                                            interaction: { mode: 'index', intersect: false },
                                            plugins: {
                                                legend: { position: 'top', align: 'end' },
                                                tooltip: { callbacks: { footer: (items) => `Total: ${(data.total[items[0].dataIndex] || 0).toLocaleString('id-ID')}` } },
                                            },
                                            scales: {
                                                x: { grid: { display: false } },
                                                y: { beginAtZero: true, ticks: { callback: (v) => v.toLocaleString('id-ID') } },
                                            },
                                        },
                                    });
                                },
                                destroy() { if (this.chart) { this.chart.destroy(); this.chart = null; } },
                            };
                        };
                    }
                </script>
            @endonce

            @if ($mode === 'bulanan')
                <livewire:pages::manajemen.rs.penunjang.rad.laporan-pemeriksaan-rad.laporan-pemeriksaan-rad-bulanan wire:key="laporan-pemeriksaan-rad-bulanan" />
            @else
                <livewire:pages::manajemen.rs.penunjang.rad.laporan-pemeriksaan-rad.laporan-pemeriksaan-rad-tahunan wire:key="laporan-pemeriksaan-rad-tahunan" />
            @endif
        </div>
    </div>
</div>
