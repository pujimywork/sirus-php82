<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new class extends Component {
    public string $filterBulan = '';
    public string $filterSource = '';
    public string $filterKlaim = '';
    public string $searchKeyword = '';

    public function mount(): void
    {
        $this->filterBulan = Carbon::now()->format('m/Y');
    }

    public function resetFilters(): void
    {
        $this->reset(['filterSource', 'filterKlaim', 'searchKeyword']);
        $this->filterBulan = Carbon::now()->format('m/Y');
    }

    private function dateRange(): array
    {
        try {
            $d = Carbon::createFromFormat('m/Y', trim($this->filterBulan))->startOfMonth();
        } catch (\Exception $e) {
            $d = Carbon::now()->startOfMonth();
        }
        return [$d, (clone $d)->endOfMonth()];
    }

    private function applyKlaimFilter($query, string $klaimAlias = 'k'): void
    {
        if ($this->filterKlaim === 'BPJS') {
            $query->where("{$klaimAlias}.klaim_status", 'BPJS');
        } elseif ($this->filterKlaim === 'UMUM') {
            $query->where("{$klaimAlias}.klaim_status", '!=', 'BPJS');
        }
    }

    #[Computed]
    public function rows()
    {
        [$start, $end] = $this->dateRange();
        $items = collect();

        if ($this->filterSource === '' || $this->filterSource === 'RJ') {
            // RJ: hanya pelayanan yang sudah Selesai (rj_status='L')
            $rj = DB::table('rstxn_rjactemps as rja')
                ->join('rstxn_rjhdrs as h', 'h.rj_no', '=', 'rja.rj_no')
                ->join('rsmst_actemps as a', 'a.acte_id', '=', 'rja.acte_id')
                ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id')
                ->select([
                    DB::raw("'RJ' as source"),
                    'rja.acte_id',
                    'a.acte_desc',
                    DB::raw('rja.acte_price as price'),
                    DB::raw('1 as qty'),
                    'h.klaim_id',
                    'k.klaim_status',
                    'h.vno_sep',
                    DB::raw("'rj_no:'||h.rj_no as txn_ref"),
                    DB::raw('h.datadaftarpolirj_json as datadaftar_json'),
                ])
                ->where('h.rj_status', 'L')
                ->whereBetween('h.rj_date', [$start, $end]);
            $this->applyKlaimFilter($rj);
            $items = $items->merge($rj->get());
        }

        if ($this->filterSource === '' || $this->filterSource === 'UGD') {
            // UGD: hanya pelayanan yang sudah Selesai (rj_status='L')
            $ugd = DB::table('rstxn_ugdactemps as rja')
                ->join('rstxn_ugdhdrs as h', 'h.rj_no', '=', 'rja.rj_no')
                ->join('rsmst_actemps as a', 'a.acte_id', '=', 'rja.acte_id')
                ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'h.klaim_id')
                ->select([
                    DB::raw("'UGD' as source"),
                    'rja.acte_id',
                    'a.acte_desc',
                    DB::raw('rja.acte_price as price'),
                    DB::raw('1 as qty'),
                    'h.klaim_id',
                    'k.klaim_status',
                    'h.vno_sep',
                    DB::raw("'rj_no:'||h.rj_no as txn_ref"),
                    DB::raw('h.datadaftarugd_json as datadaftar_json'),
                ])
                ->where('h.rj_status', 'L')
                ->whereBetween('h.rj_date', [$start, $end]);
            $this->applyKlaimFilter($ugd);
            $items = $items->merge($ugd->get());
        }

        if ($items->isEmpty()) {
            return collect();
        }

        // Filter search by nama jasa
        $kw = mb_strtoupper(trim($this->searchKeyword));
        if ($kw !== '' && mb_strlen($kw) >= 2) {
            $items = $items->filter(fn($r) => str_contains(mb_strtoupper((string) $r->acte_desc), $kw))->values();
        }

        // Aggregate group by acte_id + source + klaim_status
        $grouped = $items->groupBy(fn($r) => $r->acte_id . '|' . $r->source . '|' . ($r->klaim_status ?? '-'));

        return $grouped->map(function ($group) {
            $first = $group->first();
            $totalLines = $group->count();
            $totalPendapatan = $group->sum(fn($r) => (int) $r->price * (int) $r->qty);
            $disetujui = 0;
            $tidakDisetujui = [];

            foreach ($group as $r) {
                $lineTotal = (int) $r->price * (int) $r->qty;
                if (($r->klaim_status ?? '') === 'BPJS') {
                    $json = json_decode($r->datadaftar_json ?? '{}', true) ?: [];
                    $approved = isset($json['umbalBpjs']['disetujui']);
                    if ($approved) {
                        $disetujui += $lineTotal;
                    } else {
                        $tidakDisetujui[] = [
                            'txn_ref'     => $r->txn_ref,
                            'vno_sep'     => $r->vno_sep ?? '',
                            'doc_nominal' => $lineTotal,
                        ];
                    }
                } else {
                    $disetujui += $lineTotal;
                }
            }

            return (object) [
                'source'          => $first->source,
                'acte_id'         => $first->acte_id,
                'acte_desc'       => $first->acte_desc,
                'klaim_status'    => $first->klaim_status,
                'jumlah'          => $totalLines,
                'pendapatan'      => $totalPendapatan,
                'disetujui'       => $disetujui,
                'tidak_disetujui' => $tidakDisetujui,
            ];
        })->sortBy([
            fn($a, $b) => strcmp($a->source, $b->source),
            fn($a, $b) => strcmp((string) $a->klaim_status, (string) $b->klaim_status),
            fn($a, $b) => $b->pendapatan <=> $a->pendapatan,
        ])->values();
    }
};
?>

<div>
    <x-page-title
        title="Pendapatan Jasa Karyawan"
        subtitle="Rekap revenue paket jasa karyawan per bulan — RJ / UGD (RI tidak tersedia)" />

    <div class="w-full min-h-[calc(100vh-5rem)] bg-canvas dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6">

            <div class="flex flex-wrap items-center justify-end gap-2 mb-4">
                <a href="{{ route('manajemen.monitoring-keuangan') }}" wire:navigate
                    class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-body bg-canvas border border-gray-300 rounded-lg hover:bg-surface-soft dark:bg-gray-900 dark:text-gray-200 dark:border-gray-700 dark:hover:bg-gray-800 shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Kembali
                </a>
            </div>

            {{-- TOOLBAR --}}
            <div class="sticky z-30 px-4 py-3 bg-canvas border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3">

                    <div class="w-full sm:flex-1">
                        <x-input-label value="Cari Paket" class="sr-only" />
                        <x-text-input wire:model.live.debounce.300ms="searchKeyword"
                            placeholder="Cari nama paket jasa karyawan..." class="block w-full" />
                    </div>

                    <div class="w-full sm:w-auto">
                        <x-input-label value="Bulan" />
                        <x-text-input type="text" wire:model.live.debounce.500ms="filterBulan"
                            class="mt-1 block w-full sm:w-32" placeholder="mm/yyyy" maxlength="7" />
                    </div>

                    <div class="w-full sm:w-auto">
                        <x-input-label value="Modul" />
                        <x-select-input wire:model.live="filterSource" class="mt-1 block w-full sm:w-32">
                            <option value="">Semua</option>
                            <option value="RJ">RJ</option>
                            <option value="UGD">UGD</option>
                        </x-select-input>
                    </div>

                    <div class="w-full sm:w-auto">
                        <x-input-label value="Klaim" />
                        <x-select-input wire:model.live="filterKlaim" class="mt-1 block w-full sm:w-32">
                            <option value="">Semua</option>
                            <option value="BPJS">BPJS</option>
                            <option value="UMUM">UMUM</option>
                        </x-select-input>
                    </div>

                    <div class="ml-auto flex items-center gap-2">
                        <x-secondary-button type="button" wire:click="resetFilters" class="whitespace-nowrap">
                            Reset
                        </x-secondary-button>
                    </div>
                </div>
            </div>

            {{-- TABLE --}}
            <div class="mt-4 overflow-auto">
                @php
                    $rows = $this->rows;
                    $overallPendapatan = 0;
                    $overallDisetujui = 0;
                @endphp

                @if ($rows->isEmpty())
                    <div class="p-6 text-sm text-center text-muted dark:text-gray-400 border border-dashed border-gray-300 dark:border-gray-700 rounded-xl">
                        Tidak ada data jasa karyawan untuk periode <strong>{{ $filterBulan }}</strong>.
                    </div>
                @else
                    <table class="w-full text-sm text-left text-body dark:text-gray-300 table-auto">
                        <thead class="sticky top-0 text-xs text-ink uppercase bg-surface-soft dark:bg-gray-900 dark:text-gray-100">
                            <tr>
                                <th class="px-4 py-3 text-left">Modul</th>
                                <th class="px-4 py-3 text-left">Klaim</th>
                                <th class="px-4 py-3 text-left">Paket Jasa Karyawan</th>
                                <th class="px-4 py-3 text-right">Jumlah Trx</th>
                                <th class="px-4 py-3 text-right">Pendapatan</th>
                                <th class="px-4 py-3 text-right">Disetujui</th>
                            </tr>
                        </thead>

                        <tbody class="bg-canvas dark:bg-gray-800">
                            @foreach ($rows->groupBy('source') as $source => $sourceRows)
                                @php
                                    $sourcePendapatan = $sourceRows->sum('pendapatan');
                                    $sourceDisetujui = $sourceRows->sum('disetujui');
                                    $overallPendapatan += $sourcePendapatan;
                                    $overallDisetujui += $sourceDisetujui;
                                @endphp

                                <tr class="font-bold text-emerald-700 dark:text-emerald-300 bg-emerald-50/50 dark:bg-emerald-900/20">
                                    <td colspan="6" class="px-4 py-2">
                                        Modul: {{ $source }}
                                    </td>
                                </tr>

                                @foreach ($sourceRows->groupBy('klaim_status') as $klaimStatus => $klaimRows)
                                    @php
                                        $klaimPendapatan = $klaimRows->sum('pendapatan');
                                        $klaimDisetujui = $klaimRows->sum('disetujui');
                                    @endphp

                                    @foreach ($klaimRows as $row)
                                        <tr class="hover:bg-surface-soft dark:hover:bg-gray-700/50">
                                            <td class="px-4 py-1 whitespace-nowrap"></td>
                                            <td class="px-4 py-1 whitespace-nowrap">{{ $klaimStatus ?: '-' }}</td>
                                            <td class="px-4 py-1">{{ $row->acte_desc }}</td>
                                            <td class="px-4 py-1 text-right font-mono">{{ number_format($row->jumlah, 0, ',', '.') }}</td>
                                            <td class="px-4 py-1 text-right font-mono">{{ number_format($row->pendapatan, 0, ',', '.') }}</td>
                                            <td class="px-4 py-1 text-right whitespace-nowrap">
                                                <span class="font-mono">{{ number_format($row->disetujui, 0, ',', '.') }}</span>
                                                @forelse ($row->tidak_disetujui as $td)
                                                    <div class="text-xs text-red-600 dark:text-red-400">
                                                        ({{ number_format($td['doc_nominal'], 0, ',', '.') }} / sep:
                                                        {{ $td['vno_sep'] ?: '—' }})
                                                    </div>
                                                @empty
                                                    <div class="text-xs text-emerald-600 dark:text-emerald-400">Semua OK</div>
                                                @endforelse
                                            </td>
                                        </tr>
                                    @endforeach

                                    <tr class="font-semibold bg-surface-soft dark:bg-gray-700/40">
                                        <td colspan="4" class="px-4 py-2 text-xs text-right uppercase tracking-wide">
                                            Subtotal {{ $source }} &mdash; {{ $klaimStatus ?: '-' }}
                                        </td>
                                        <td class="px-4 py-2 text-right font-mono">
                                            {{ number_format($klaimPendapatan, 0, ',', '.') }}
                                        </td>
                                        <td class="px-4 py-2 text-right font-mono">
                                            {{ number_format($klaimDisetujui, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @endforeach

                                <tr class="font-bold bg-emerald-100/60 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-200">
                                    <td colspan="4" class="px-4 py-2 text-right">
                                        Subtotal Modul {{ $source }}
                                    </td>
                                    <td class="px-4 py-2 text-right font-mono">
                                        {{ number_format($sourcePendapatan, 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-2 text-right font-mono">
                                        {{ number_format($sourceDisetujui, 0, ',', '.') }}
                                    </td>
                                </tr>
                            @endforeach

                            <tr class="font-bold bg-gray-300 dark:bg-gray-600 text-ink dark:text-gray-100">
                                <td colspan="4" class="px-4 py-3 text-right">
                                    Total Semua Modul &amp; Klaim
                                </td>
                                <td class="px-4 py-3 text-right font-mono">
                                    {{ number_format($overallPendapatan, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono">
                                    {{ number_format($overallDisetujui, 0, ',', '.') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</div>
