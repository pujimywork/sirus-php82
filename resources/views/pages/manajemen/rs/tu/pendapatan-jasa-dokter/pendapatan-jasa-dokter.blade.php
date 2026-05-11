<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new class extends Component {
    public string $filterBulan = '';
    public string $filterDokter = '';
    public string $filterKlaim = '';

    public function mount(): void
    {
        $this->filterBulan = Carbon::now()->format('m/Y');
    }

    public function resetFilters(): void
    {
        $this->reset(['filterDokter', 'filterKlaim']);
        $this->filterBulan = Carbon::now()->format('m/Y');
    }

    #[Computed]
    public function dokterList()
    {
        // Hanya dokter yang punya transaksi di bulan terpilih
        return DB::table('rsview_newdocsalaries as v')
            ->select('v.dr_id', DB::raw('MAX(v.dr_name) as dr_name'))
            ->whereRaw("to_char(to_date(v.doc_date, 'dd/mm/yyyy'), 'mm/yyyy') = ?", [$this->filterBulan])
            ->groupBy('v.dr_id')
            ->orderBy('dr_name')
            ->get();
    }

    #[Computed]
    public function rows()
    {
        if ($this->filterDokter === '') {
            return collect();
        }

        $query = DB::table('rsview_newdocsalaries as v')
            ->select([
                'v.group_doc',
                'v.desc_doc',
                'v.dr_id',
                'v.dr_name',
                DB::raw('SUM(v.doc_nominal) AS doc_nominal'),
                DB::raw('k.klaim_status AS klaim_status'),
                DB::raw('COUNT(*) AS jumlah'),
            ])
            ->join('rsmst_klaimtypes as k', 'v.klaim_id', '=', 'k.klaim_id')
            ->where('v.dr_id', $this->filterDokter)
            ->whereRaw("to_char(to_date(v.doc_date, 'dd/mm/yyyy'), 'mm/yyyy') = ?", [$this->filterBulan])
            ->groupBy('v.group_doc', 'v.desc_doc', 'v.dr_id', 'v.dr_name', 'k.klaim_status')
            ->orderBy('v.dr_id')
            ->orderBy('klaim_status')
            ->orderByRaw('SUM(v.doc_nominal) DESC');

        if ($this->filterKlaim === 'BPJS') {
            $query->where('k.klaim_status', 'BPJS');
        } elseif ($this->filterKlaim === 'UMUM') {
            $query->where('k.klaim_status', '!=', 'BPJS');
        }

        $rows = $query->get();

        foreach ($rows as $row) {
            $this->enrichWithApproval($row);
        }

        return $rows;
    }

    /**
     * Untuk setiap row group, tarik daftar txn-nya, decode JSON pendaftaran sesuai desc_doc,
     * dan klasifikasikan disetujui / tidak-disetujui berdasarkan flag umbalBpjs.disetujui.
     */
    private function enrichWithApproval(\stdClass $row): void
    {
        $txnList = DB::table('rsview_newdocsalaries as v')
            ->select('v.txn_no', 'v.group_doc', 'v.desc_doc', 'k.klaim_status', DB::raw('SUM(v.doc_nominal) AS doc_nominal'))
            ->join('rsmst_klaimtypes as k', 'v.klaim_id', '=', 'k.klaim_id')
            ->whereRaw("to_char(to_date(v.doc_date, 'dd/mm/yyyy'), 'mm/yyyy') = ?", [$this->filterBulan])
            ->where('v.group_doc', $row->group_doc)
            ->where('v.desc_doc', $row->desc_doc)
            ->where('v.dr_id', $row->dr_id)
            ->where('v.dr_name', $row->dr_name)
            ->where('k.klaim_status', $row->klaim_status)
            ->groupBy('v.txn_no', 'v.group_doc', 'v.desc_doc', 'k.klaim_status')
            ->orderBy('v.txn_no')
            ->get();

        $disetujui = 0;
        $tidakDisetujui = [];

        foreach ($txnList as $txn) {
            if ($txn->klaim_status === 'BPJS') {
                $source = $this->fetchSourceJson($txn->desc_doc, $txn->txn_no);
                $jsonString = $source->datadaftarri_json ?? $source->datadaftarpolirj_json ?? $source->datadaftarugd_json ?? '{}';
                $vnoSep = $source->vno_sep ?? null;
                $json = json_decode($jsonString, true) ?: [];
                $approved = isset($json['umbalBpjs']['disetujui']);

                if ($approved) {
                    $disetujui += (int) $txn->doc_nominal;
                } else {
                    $tidakDisetujui[] = [
                        'txn_no'      => $txn->txn_no,
                        'desc_doc'    => $txn->desc_doc,
                        'vno_sep'     => $vnoSep ?? '',
                        'doc_nominal' => $txn->doc_nominal,
                    ];
                }
            } elseif ($txn->klaim_status === 'UMUM') {
                $disetujui += (int) $txn->doc_nominal;
            }
        }

        $row->disetujui = $disetujui;
        $row->tidak_disetujui = $tidakDisetujui;
    }

    /**
     * Ambil JSON pendaftaran + vno_sep berdasarkan tipe transaksi (desc_doc).
     */
    private function fetchSourceJson(string $descDoc, $txnNo): ?\stdClass
    {
        switch ($descDoc) {
            case 'OPERATOR':
            case 'ANASTESI':
                return DB::table('rstxn_oks as a')
                    ->join('rstxn_rihdrs as b', 'a.rihdr_no', '=', 'b.rihdr_no')
                    ->select('b.datadaftarri_json', 'b.rihdr_no', 'b.vno_sep')
                    ->where('a.ok_reg', $txnNo)
                    ->first();

            case 'VISIT':
                return DB::table('rstxn_rivisits as v')
                    ->join('rstxn_rihdrs as ri', 'v.rihdr_no', '=', 'ri.rihdr_no')
                    ->select('ri.datadaftarri_json', 'ri.rihdr_no', 'ri.vno_sep')
                    ->where('v.visit_no', $txnNo)
                    ->first();

            case 'KONSUL':
                return DB::table('rstxn_rikonsuls as k')
                    ->join('rstxn_rihdrs as ri', 'k.rihdr_no', '=', 'ri.rihdr_no')
                    ->select('ri.datadaftarri_json', 'ri.rihdr_no', 'ri.vno_sep')
                    ->where('k.konsul_no', $txnNo)
                    ->first();

            case 'JD RI':
                return DB::table('rstxn_riactdocs as a')
                    ->join('rstxn_rihdrs as ri', 'a.rihdr_no', '=', 'ri.rihdr_no')
                    ->select('ri.datadaftarri_json', 'ri.rihdr_no', 'ri.vno_sep')
                    ->where('a.actd_no', $txnNo)
                    ->first();

            case 'UP UGD':
                return DB::table('rstxn_ugdhdrs as u')
                    ->select('u.datadaftarugd_json', 'u.rj_no', 'u.vno_sep')
                    ->where('u.rj_no', $txnNo)
                    ->first();

            case 'JD UGD':
                return DB::table('rstxn_ugdaccdocs as a')
                    ->join('rstxn_ugdhdrs as u', 'a.rj_no', '=', 'u.rj_no')
                    ->select('u.datadaftarugd_json', 'u.rj_no', 'u.vno_sep')
                    ->where('a.rjhn_dtl', $txnNo)
                    ->first();

            case 'UP UGDTRF':
            case 'JD UGDTRF':
            case 'UP RJTRF':
            case 'JD RJTRF':
                // Transfer ke RI → cari di rihdrs
                return DB::table('rstxn_rihdrs as ri')
                    ->select('ri.datadaftarri_json', 'ri.rihdr_no', 'ri.vno_sep')
                    ->where('ri.rihdr_no', $txnNo)
                    ->first();

            case 'UP RJ':
                return DB::table('rstxn_rjhdrs as rj')
                    ->select('rj.datadaftarpolirj_json', 'rj.rj_no', 'rj.vno_sep')
                    ->where('rj.rj_no', $txnNo)
                    ->first();

            case 'JD RJ':
                return DB::table('rstxn_rjaccdocs as a')
                    ->join('rstxn_rjhdrs as rj', 'a.rj_no', '=', 'rj.rj_no')
                    ->select('rj.datadaftarpolirj_json', 'rj.rj_no', 'rj.vno_sep')
                    ->where('a.rjhn_dtl', $txnNo)
                    ->first();

            case 'UP KLINIK':
            case 'JD KLINIK':
                return DB::table('rstxn_rjhdrks as rjk')
                    ->select('rjk.datadaftarpolirj_json', 'rjk.rj_no', 'rjk.vno_sep')
                    ->where('rjk.rj_no', $txnNo)
                    ->first();

            default:
                return null;
        }
    }
};
?>

<div>
    <header class="bg-white shadow dark:bg-gray-800">
        <div class="w-full px-4 py-2 sm:px-6 lg:px-8 flex items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                    Pendapatan Jasa Dokter
                </h2>
                <p class="text-base text-gray-700 dark:text-gray-400">
                    Rekap jasa medis dokter per bulan &mdash; rincian disetujui / belum disetujui BPJS
                </p>
            </div>
            <a href="{{ route('manajemen.monitoring-keuangan') }}" wire:navigate
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

            {{-- TOOLBAR --}}
            <div class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3">

                    {{-- FILTER BULAN --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Bulan" />
                        <x-text-input type="text" wire:model.live.debounce.500ms="filterBulan"
                            class="mt-1 block w-full sm:w-32" placeholder="mm/yyyy" maxlength="7" />
                    </div>

                    {{-- FILTER DOKTER --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Dokter" />
                        <x-select-input wire:model.live="filterDokter" class="mt-1 block w-full sm:w-64">
                            <option value="">— Pilih Dokter —</option>
                            @foreach ($this->dokterList as $dokter)
                                <option value="{{ $dokter->dr_id }}">{{ $dokter->dr_name }}</option>
                            @endforeach
                        </x-select-input>
                    </div>

                    {{-- FILTER KLAIM --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Klaim" />
                        <x-select-input wire:model.live="filterKlaim" class="mt-1 block w-full sm:w-32">
                            <option value="">Semua</option>
                            <option value="BPJS">BPJS</option>
                            <option value="UMUM">UMUM</option>
                        </x-select-input>
                    </div>

                    {{-- ACTIONS --}}
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
                    $overallNominal = 0;
                    $overallDisetujui = 0;
                @endphp

                @if ($this->filterDokter === '')
                    <div class="p-6 text-sm text-center text-gray-500 dark:text-gray-400 border border-dashed border-gray-300 dark:border-gray-700 rounded-xl">
                        Silakan pilih dokter untuk menampilkan rekap pendapatan jasa.
                    </div>
                @elseif ($rows->isEmpty())
                    <div class="p-6 text-sm text-center text-gray-500 dark:text-gray-400 border border-dashed border-gray-300 dark:border-gray-700 rounded-xl">
                        Tidak ada data jasa dokter untuk periode <strong>{{ $filterBulan }}</strong>.
                    </div>
                @else
                    <table class="w-full text-sm text-left text-gray-700 dark:text-gray-300 table-auto">
                        <thead class="sticky top-0 text-xs text-gray-900 uppercase bg-gray-100 dark:bg-gray-900 dark:text-gray-100">
                            <tr>
                                <th class="w-1/5 px-4 py-3 text-left">Dokter</th>
                                <th class="w-1/6 px-4 py-3 text-left">Klaim</th>
                                <th class="w-1/4 px-4 py-3 text-left">Description</th>
                                <th class="w-1/6 px-4 py-3 text-right">Jasa Dokter</th>
                                <th class="w-1/4 px-4 py-3 text-right">Disetujui</th>
                            </tr>
                        </thead>

                        <tbody class="bg-white dark:bg-gray-800">
                            @foreach ($rows->groupBy('dr_id') as $drId => $doctorRows)
                                @php
                                    $drName = $doctorRows->first()->dr_name;
                                    $doctorNominal = $doctorRows->sum('doc_nominal');
                                    $doctorDisetujui = $doctorRows->sum('disetujui');
                                    $overallNominal += $doctorNominal;
                                    $overallDisetujui += $doctorDisetujui;
                                @endphp

                                <tr class="font-bold text-emerald-700 dark:text-emerald-300 bg-emerald-50/50 dark:bg-emerald-900/20">
                                    <td colspan="5" class="px-4 py-2">
                                        Dokter: {{ $drId }} &mdash; {{ $drName }}
                                    </td>
                                </tr>

                                @foreach ($doctorRows->groupBy('klaim_status') as $klaimStatus => $klaimRows)
                                    @php
                                        $klaimNominal = $klaimRows->sum('doc_nominal');
                                        $klaimDisetujui = $klaimRows->sum('disetujui');
                                    @endphp

                                    @foreach ($klaimRows as $row)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                            <td class="px-4 py-1 whitespace-nowrap"></td>
                                            <td class="px-4 py-1 whitespace-nowrap">{{ $klaimStatus }}</td>
                                            <td class="px-4 py-1 whitespace-nowrap">{{ $row->desc_doc }}</td>
                                            <td class="px-4 py-1 text-right whitespace-nowrap font-mono">
                                                {{ number_format($row->doc_nominal, 0, ',', '.') }}
                                            </td>
                                            <td class="px-4 py-1 text-right whitespace-nowrap">
                                                <span class="font-mono">{{ number_format($row->disetujui, 0, ',', '.') }}</span>
                                                @forelse ($row->tidak_disetujui as $td)
                                                    <div class="text-xs text-red-600 dark:text-red-400">
                                                        ({{ number_format($td['doc_nominal'], 0, ',', '.') }} / sep:
                                                        {{ $td['vno_sep'] ?: '—' }})
                                                        {{ $td['desc_doc'] }}
                                                    </div>
                                                @empty
                                                    <div class="text-xs text-emerald-600 dark:text-emerald-400">Semua OK</div>
                                                @endforelse
                                            </td>
                                        </tr>
                                    @endforeach

                                    <tr class="font-semibold bg-gray-50 dark:bg-gray-700/40">
                                        <td colspan="3" class="px-4 py-2 text-xs text-right uppercase tracking-wide">
                                            Subtotal {{ $klaimStatus }}
                                        </td>
                                        <td class="px-4 py-2 text-right font-mono">
                                            {{ number_format($klaimNominal, 0, ',', '.') }}
                                        </td>
                                        <td class="px-4 py-2 text-right font-mono">
                                            {{ number_format($klaimDisetujui, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @endforeach

                                <tr class="font-bold bg-emerald-100/60 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-200">
                                    <td colspan="3" class="px-4 py-2 text-right">
                                        Subtotal Dokter {{ $drName }}
                                    </td>
                                    <td class="px-4 py-2 text-right font-mono">
                                        {{ number_format($doctorNominal, 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-2 text-right font-mono">
                                        {{ number_format($doctorDisetujui, 0, ',', '.') }}
                                    </td>
                                </tr>
                            @endforeach

                            <tr class="font-bold bg-gray-300 dark:bg-gray-600 text-gray-900 dark:text-gray-100">
                                <td colspan="3" class="px-4 py-3 text-right">
                                    Total Semua Dokter &amp; Klaim
                                </td>
                                <td class="px-4 py-3 text-right font-mono">
                                    {{ number_format($overallNominal, 0, ',', '.') }}
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
