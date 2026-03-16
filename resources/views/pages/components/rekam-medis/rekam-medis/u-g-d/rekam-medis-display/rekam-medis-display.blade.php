<?php
// resources/views/livewire/pages/transaksi/ugd/rekam-medis-display-ugd/rekam-medis-display-ugd.blade.php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use Livewire\Attributes\Reactive;

new class extends Component {
    use WithPagination, EmrUGDTrait, EmrRJTrait;

    /* =======================
     | Filter & Pagination
     * ======================= */
    #[Reactive]
    public string $regNo = '';
    public string $searchKeyword = '';
    public string $filterTahun = '';
    public string $filterLayanan = '';
    public int $itemsPerPage = 3;

    /* =======================
     | Properties untuk Copy Resep
     * ======================= */
    public string $rjNoRefCopyTo = ''; // No. UGD yang sedang aktif (tujuan copy)

    /* =======================
     | Mount
     * ======================= */
    public function mount(string $regNo = '', int $rjNoRefCopyTo = 0): void
    {
        $this->regNo = $regNo;
        $this->rjNoRefCopyTo = $rjNoRefCopyTo;
    }

    /* =======================
     | Load Pasien dari luar
     * ======================= */
    public function loadPasien($regNo): void
    {
        $this->regNo = $regNo;
        $this->resetPage();
    }

    /* =======================
     | Reset filters
     * ======================= */
    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterTahun', 'filterLayanan']);
        $this->resetPage();
    }

    /* =======================
     | Updated hooks
     * ======================= */
    public function updatedSearchKeyword(): void
    {
        $this->resetPage();
    }
    public function updatedFilterTahun(): void
    {
        $this->resetPage();
    }
    public function updatedFilterLayanan(): void
    {
        $this->resetPage();
    }
    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
    }

    /* =======================
     | Copy Resep (dari RJ ke UGD aktif)
     * ======================= */
    public function copyResep(string $txnNo, string $layananStatus): void
    {
        if ($layananStatus !== 'RJ') {
            $this->dispatch('toast', type: 'error', message: 'Copy resep hanya untuk Rawat Jalan.');
            return;
        }

        if (empty($this->rjNoRefCopyTo)) {
            $this->dispatch('toast', type: 'error', message: 'Transaksi tujuan belum ditentukan.');
            return;
        }

        if ($txnNo === (string) $this->rjNoRefCopyTo) {
            $this->dispatch('toast', type: 'error', message: 'Tidak dapat copy ke transaksi yang sama.');
            return;
        }

        if ($this->checkUGDStatus($this->rjNoRefCopyTo)) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }

        try {
            DB::transaction(function () use ($txnNo) {
                // Destination = UGD, Source = RJ
                $to = $this->findDataUGD($this->rjNoRefCopyTo);
                $from = $this->findDataRJ($txnNo);

                if (!$to || !$from) {
                    throw new \Exception('Data kunjungan tidak ditemukan.');
                }

                $eresepFrom = $from['eresep'] ?? [];
                $eresepRacikanFrom = $from['eresepRacikan'] ?? [];

                // ── RESEP BIASA ──
                if (!empty($eresepFrom)) {
                    DB::table('rstxn_rjobats')->where('rj_no', $this->rjNoRefCopyTo)->delete();

                    $maxDtl = (int) DB::table('rstxn_rjobats')->selectRaw('nvl(max(rjobat_dtl), 0) as max_dtl')->value('max_dtl');

                    $to['eresep'] = $eresepFrom;

                    foreach ($to['eresep'] as $key => $item) {
                        $maxDtl++;

                        $productPrice = DB::table('immst_products')->where('product_id', $item['productId'])->value('sales_price') ?? 0;

                        DB::table('rstxn_rjobats')->insert([
                            'rjobat_dtl' => $maxDtl,
                            'rj_no' => $this->rjNoRefCopyTo,
                            'product_id' => $item['productId'],
                            'qty' => $item['qty'],
                            'price' => $productPrice,
                            'rj_carapakai' => $item['signaX'],
                            'rj_kapsul' => $item['signaHari'],
                            'rj_takar' => 'Tablet',
                            'catatan_khusus' => $item['catatanKhusus'],
                            'rj_ket' => $item['catatanKhusus'],
                            'exp_date' => DB::raw("to_date('{$to['rjDate']}','dd/mm/yyyy hh24:mi:ss')+30"),
                            'etiket_status' => 1,
                        ]);

                        $to['eresep'][$key]['rjObatDtl'] = $maxDtl;
                        $to['eresep'][$key]['productPrice'] = $productPrice;
                        $to['eresep'][$key]['rjNo'] = $this->rjNoRefCopyTo;
                    }
                }

                // ── RESEP RACIKAN ──
                if (!empty($eresepRacikanFrom)) {
                    DB::table('rstxn_rjobatracikans')->where('rj_no', $this->rjNoRefCopyTo)->delete();

                    $maxDtlRacikan = (int) DB::table('rstxn_rjobatracikans')->selectRaw('nvl(max(rjobat_dtl), 0) as max_dtl')->value('max_dtl');

                    $to['eresepRacikan'] = $eresepRacikanFrom;

                    foreach ($to['eresepRacikan'] as $key => $item) {
                        if (!isset($item['jenisKeterangan'])) {
                            continue;
                        }

                        $maxDtlRacikan++;

                        DB::table('rstxn_rjobatracikans')->insert([
                            'rjobat_dtl' => $maxDtlRacikan,
                            'rj_no' => $this->rjNoRefCopyTo,
                            'product_name' => $item['productName'],
                            'sedia' => $item['sedia'],
                            'dosis' => $item['dosis'] ?? '',
                            'qty' => $item['qty'],
                            'catatan' => $item['catatan'],
                            'catatan_khusus' => $item['catatanKhusus'],
                            'no_racikan' => $item['noRacikan'],
                            'rj_takar' => 'Tablet',
                            'exp_date' => DB::raw("to_date('{$to['rjDate']}','dd/mm/yyyy hh24:mi:ss')+30"),
                            'etiket_status' => 1,
                        ]);

                        $to['eresepRacikan'][$key]['rjObatDtl'] = $maxDtlRacikan;
                        $to['eresepRacikan'][$key]['rjNo'] = $this->rjNoRefCopyTo;
                    }
                }

                // ── BUILD TEKS TERAPI ──
                $eresepText = collect($to['eresep'] ?? [])
                    ->map(function ($item) {
                        $catatan = $item['catatanKhusus'] ? " ({$item['catatanKhusus']})" : '';
                        return "R/ {$item['productName']} | No. {$item['qty']} | S {$item['signaX']}dd{$item['signaHari']}{$catatan}";
                    })
                    ->implode(PHP_EOL);

                $eresepRacikanText = collect($to['eresepRacikan'] ?? [])
                    ->filter(fn($item) => isset($item['jenisKeterangan']))
                    ->map(function ($item) {
                        $jmlRacikan = $item['qty'] ? "Jml Racikan {$item['qty']} | {$item['catatan']} | S {$item['catatanKhusus']}" . PHP_EOL : '';
                        return "{$item['noRacikan']}/ {$item['productName']} - " . ($item['dosis'] ?? '') . PHP_EOL . $jmlRacikan;
                    })
                    ->implode('');

                $to['perencanaan']['terapi']['terapi'] = $eresepText . PHP_EOL . $eresepRacikanText;

                $this->updateJsonUGD($this->rjNoRefCopyTo, $to);
            });

            $this->dispatch('toast', type: 'success', message: 'Copy resep berhasil.');
            $this->dispatch('emr-ugd.eresep.open', rjNo: $this->rjNoRefCopyTo);
            $this->dispatch('open-eresep-non-racikan-ugd', rjNo: $this->rjNoRefCopyTo);
            $this->dispatch('open-eresep-racikan-ugd', rjNo: $this->rjNoRefCopyTo);
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal copy resep: ' . $e->getMessage());
        }
    }

    /* =======================
     | Get daftar tahun untuk filter
     * ======================= */
    #[Computed]
    public function tahunList()
    {
        if (!$this->regNo) {
            return collect();
        }

        return DB::table('rsview_ermstatus')->select(DB::raw('DISTINCT EXTRACT(YEAR FROM txn_date) as tahun'))->where('reg_no', $this->regNo)->orderBy('tahun', 'desc')->pluck('tahun');
    }

    /* =======================
     | Base Query
     * ======================= */
    #[Computed]
    public function baseQuery()
    {
        if (!$this->regNo) {
            return collect();
        }

        $searchKeyword = trim($this->searchKeyword);

        $queryBuilder = DB::table('rsview_ermstatus')
            ->select(
                DB::raw("to_char(txn_date,'dd/mm/yyyy hh24:mi:ss') AS txn_date"),
                DB::raw("to_char(txn_date,'yyyymmddhh24miss') AS txn_date1"),
                'txn_no',
                'reg_no',
                'reg_name',
                'erm_status',
                'layanan_status',
                'poli',
                'kd_dr_bpjs',
                'nokartu_bpjs',
                DB::raw("(CASE
                    WHEN layanan_status='RJ' THEN (
                        SELECT datadaftarpolirj_json FROM rsview_rjkasir WHERE rj_no = txn_no
                    )
                    WHEN layanan_status='UGD' THEN (
                        SELECT datadaftarugd_json FROM rsview_ugdkasir WHERE rj_no = txn_no
                    )
                    WHEN layanan_status='RI' THEN (
                        SELECT datadaftarri_json FROM rsview_rihdrs WHERE rihdr_no = txn_no
                    )
                    ELSE NULL
                END) AS datadaftar_json"),
            )
            ->where('reg_no', $this->regNo);

        if ($this->filterTahun) {
            $queryBuilder->whereYear('txn_date', $this->filterTahun);
        }
        if ($this->filterLayanan) {
            $queryBuilder->where('layanan_status', $this->filterLayanan);
        }

        if ($searchKeyword !== '') {
            $upper = mb_strtoupper($searchKeyword);
            $queryBuilder->where(function ($q) use ($upper, $searchKeyword) {
                if (ctype_digit($searchKeyword)) {
                    $q->orWhere('txn_no', 'like', "%{$searchKeyword}%");
                }
                $q->orWhereRaw('UPPER(poli) LIKE ?', ["%{$upper}%"])
                    ->orWhereRaw('UPPER(kd_dr_bpjs) LIKE ?', ["%{$upper}%"])
                    ->orWhereRaw('UPPER(nokartu_bpjs) LIKE ?', ["%{$upper}%"]);
            });
        }

        return $queryBuilder->orderBy('txn_date1', 'desc')->orderBy('layanan_status', 'desc')->orderBy('poli', 'asc');
    }

    /* =======================
     | Rows dengan Pagination
     * ======================= */
    #[Computed]
    public function rows()
    {
        if (!$this->regNo) {
            return collect();
        }
        return $this->baseQuery()->paginate($this->itemsPerPage);
    }

    /* =======================
     | Stats kunjungan
     * ======================= */
    #[Computed]
    public function statsKunjungan()
    {
        if (!$this->regNo) {
            return ['total' => 0, 'rj' => 0, 'ugd' => 0, 'ri' => 0];
        }

        $stats = DB::table('rsview_ermstatus')->select(DB::raw('COUNT(*) as total'), DB::raw("SUM(CASE WHEN layanan_status='RJ'  THEN 1 ELSE 0 END) as rj"), DB::raw("SUM(CASE WHEN layanan_status='UGD' THEN 1 ELSE 0 END) as ugd"), DB::raw("SUM(CASE WHEN layanan_status='RI'  THEN 1 ELSE 0 END) as ri"))->where('reg_no', $this->regNo)->first();

        return [
            'total' => $stats->total ?? 0,
            'rj' => $stats->rj ?? 0,
            'ugd' => $stats->ugd ?? 0,
            'ri' => $stats->ri ?? 0,
        ];
    }

    public function openRekamMedisRj(int $rjNo): void
    {
        $this->dispatch('cetak-rekam-medis-ugd.open', rjNo: $rjNo);
    }
};
?>

<div>
    <div class="flex flex-col w-full">
        <div class="w-full mx-auto">
            <div
                class="w-full p-4 space-y-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                <!-- Table Resume Medis -->
                <div class="flex flex-col my-2">
                    <div class="overflow-x-auto rounded-lg">
                        <div class="w-full">
                            <div class="mb-2 overflow-hidden shadow sm:rounded-lg">
                                <table class="w-full text-sm text-left text-gray-500 table-auto dark:text-gray-400">
                                    <thead
                                        class="text-sm text-gray-700 uppercase bg-gray-100 dark:bg-gray-700 dark:text-gray-400">
                                        <tr>
                                            <th scope="col" class="px-4 py-3">
                                                <div class="flex items-center space-x-2">
                                                    <span>Resume Medis Pasien</span>
                                                    @if ($regNo && $this->rows->total() > 0)
                                                        <span
                                                            class="px-2 py-0.5 text-sm bg-blue-100 rounded-full text-brand">
                                                            {{ $this->rows->total() }} Kunjungan
                                                        </span>
                                                    @endif
                                                </div>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800">
                                        @forelse ($this->rows as $myQData)
                                            <tr class="border-b group dark:border-gray-700">
                                                @php
                                                    $datadaftar_json =
                                                        json_decode($myQData->datadaftar_json, true) ?? [];
                                                    $isRI = ($myQData->layanan_status ?? '') === 'RI';
                                                    $isRJ = ($myQData->layanan_status ?? '') === 'RJ';
                                                    $isUGD = ($myQData->layanan_status ?? '') === 'UGD';

                                                    $statusIcon = $isRI
                                                        ? '🏥'
                                                        : ($isUGD
                                                            ? '🚑'
                                                            : ($isRJ
                                                                ? '👤'
                                                                : '📋'));
                                                    $statusClass = $isRI
                                                        ? 'text-purple-600'
                                                        : ($isUGD
                                                            ? 'text-red-600'
                                                            : ($isRJ
                                                                ? 'text-blue-600'
                                                                : 'text-gray-600'));
                                                    $statusText = $isRI
                                                        ? 'Rawat Inap'
                                                        : ($isUGD
                                                            ? 'UGD'
                                                            : ($isRJ
                                                                ? 'Rawat Jalan'
                                                                : '-'));
                                                @endphp

                                                <td
                                                    class="px-4 py-4 text-gray-900 transition-colors group-hover:bg-gray-50">

                                                    <div class="flex justify-between gap-1 min-w-0">
                                                        <div class="flex items-center min-w-0 gap-2 flex-wrap">
                                                            <span class="text-2xl shrink-0">{{ $statusIcon }}</span>
                                                            <div
                                                                class="flex items-center flex-wrap min-w-0 gap-x-2 gap-y-1">
                                                                <span
                                                                    class="font-bold shrink-0 {{ $statusClass }}">{{ $statusText }}</span>
                                                                <span class="text-gray-400 shrink-0">|</span>

                                                                @if (!empty($datadaftar_json['statusPRB']['penanggungJawab']['statusPRB']))
                                                                    <span
                                                                        class="px-2 py-0.5 text-sm font-bold text-white bg-gray-800 rounded-full shrink-0">PRB</span>
                                                                @endif

                                                                @if (($datadaftar_json['ermStatus'] ?? '') == 'L')
                                                                    <span
                                                                        class="px-2 py-0.5 text-sm font-medium text-green-700 bg-green-100 rounded-full shrink-0">Selesai</span>
                                                                @endif
                                                            </div>
                                                        </div>
                                                        <div class="text-sm text-gray-500 pl-9">
                                                            {{ $myQData->txn_date }}
                                                        </div>
                                                    </div>

                                                    <div class="flex mt-2 space-x-4 text-sm">
                                                        <div class="flex items-center space-x-1">
                                                            <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none"
                                                                stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2"
                                                                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                                            </svg>
                                                            <span>Poli: <span
                                                                    class="font-medium">{{ $myQData->poli ?? '-' }}</span></span>
                                                        </div>
                                                    </div>

                                                    <div class="grid grid-cols-1 gap-3 mt-3">
                                                        {{-- ICD10 --}}
                                                        <div class="p-2 rounded bg-gray-50">
                                                            <div class="flex items-center mb-1 space-x-1">
                                                                <svg class="w-3 h-3 text-blue-600 shrink-0"
                                                                    fill="none" stroke="currentColor"
                                                                    viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                                        stroke-width="2"
                                                                        d="M9 12h6m-6 4h6m2-10h-6a2 2 0 00-2 2v14a2 2 0 002 2h6a2 2 0 002-2V6a2 2 0 00-2-2z" />
                                                                </svg>
                                                                <span class="text-sm font-semibold">ICD10:</span>
                                                            </div>
                                                            <div class="text-sm">
                                                                @if (!empty($datadaftar_json['diagnosis']))
                                                                    @foreach (collect($datadaftar_json['diagnosis'])->take(2) as $diag)
                                                                        <div>{{ $diag['diagId'] ?? '' }} -
                                                                            {{ Str::limit($diag['diagDesc'] ?? '', 30) }}
                                                                        </div>
                                                                    @endforeach
                                                                    @if (count($datadaftar_json['diagnosis']) > 2)
                                                                        <div class="text-gray-400">
                                                                            +{{ count($datadaftar_json['diagnosis']) - 2 }}
                                                                            lainnya</div>
                                                                    @endif
                                                                @else
                                                                    <span class="text-gray-400">-</span>
                                                                @endif
                                                            </div>
                                                        </div>

                                                        {{-- Diagnosis Dokter --}}
                                                        <div class="p-2 rounded bg-gray-50">
                                                            <div class="flex items-center mb-1 space-x-1">
                                                                <svg class="w-3 h-3 text-green-600 shrink-0"
                                                                    fill="none" stroke="currentColor"
                                                                    viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                                        stroke-width="2"
                                                                        d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                                </svg>
                                                                <span class="text-sm font-semibold">Dx Dokter:</span>
                                                            </div>
                                                            <div class="text-sm whitespace-pre-line">
                                                                {{ $datadaftar_json['diagnosisFreeText'] ?? '-' }}
                                                            </div>
                                                        </div>

                                                        {{-- Terapi --}}
                                                        <div class="p-2 rounded bg-gray-50">
                                                            <div class="flex items-center mb-1 space-x-1">
                                                                <svg class="w-3 h-3 text-purple-600 shrink-0"
                                                                    fill="none" stroke="currentColor"
                                                                    viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                                        stroke-width="2"
                                                                        d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                                                </svg>
                                                                <span class="text-sm font-semibold">Terapi:</span>
                                                            </div>
                                                            <div class="text-sm break-words whitespace-pre-line">
                                                                {{ data_get($datadaftar_json, 'perencanaan.terapi.terapi', '-') }}
                                                            </div>
                                                        </div>
                                                    </div>

                                                    {{-- Actions --}}
                                                    @role(['Dokter', 'Admin', 'Perawat'])
                                                        <div class="grid grid-cols-2 gap-2 mt-3">
                                                            <div class="grid grid-cols-1 gap-2">
                                                                @if ($isRJ)
                                                                    <x-primary-button type="button"
                                                                        wire:click="copyResep('{{ $myQData->txn_no }}','{{ $myQData->layanan_status }}')"
                                                                        class="text-sm px-3 py-1.5">
                                                                        <svg class="w-4 h-4 mr-1" fill="none"
                                                                            stroke="currentColor" viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round"
                                                                                stroke-linejoin="round" stroke-width="2"
                                                                                d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                                                                        </svg>
                                                                        Copy Resep
                                                                    </x-primary-button>

                                                                    <x-info-button type="button"
                                                                        wire:click="openRekamMedisRj({{ $myQData->txn_no }})"
                                                                        wire:loading.attr="disabled"
                                                                        wire:target="openRekamMedisRj({{ $myQData->txn_no }})">
                                                                        <span wire:loading.remove
                                                                            wire:target="openRekamMedisRj({{ $myQData->txn_no }})"
                                                                            class="flex items-center gap-1">
                                                                            <svg class="w-4 h-4" fill="none"
                                                                                stroke="currentColor" viewBox="0 0 24 24">
                                                                                <path stroke-linecap="round"
                                                                                    stroke-linejoin="round" stroke-width="2"
                                                                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                                            </svg>
                                                                            Resume Medis
                                                                        </span>
                                                                        <span wire:loading
                                                                            wire:target="openRekamMedisRj({{ $myQData->txn_no }})"
                                                                            class="flex items-center gap-1">
                                                                            <x-loading /> Memuat...
                                                                        </span>
                                                                    </x-info-button>
                                                                @endif
                                                            </div>

                                                            @if (!empty($datadaftar_json['sep']['noSep']))
                                                                <span class="text-sm text-right text-gray-500 break-all">
                                                                    SEP: {{ $datadaftar_json['sep']['noSep'] }}
                                                                </span>
                                                            @endif
                                                        </div>
                                                    @endrole
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td class="px-4 py-8 text-center">
                                                    @if ($regNo)
                                                        <svg class="w-12 h-12 mx-auto text-gray-300" fill="none"
                                                            stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M9 12h6m-6 4h6m2-10h-6a2 2 0 00-2 2v14a2 2 0 002 2h6a2 2 0 002-2V6a2 2 0 00-2-2z" />
                                                        </svg>
                                                        <p class="mt-2 text-gray-500">Tidak ada data kunjungan</p>
                                                    @else
                                                        <p class="text-gray-500">Silakan pilih pasien terlebih dahulu
                                                        </p>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            @if ($regNo && $this->rows->hasPages())
                                <div class="mt-4 w-full overflow-x-auto">
                                    {{ $this->rows->links() }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Toolbar --}}
                <div
                    class="flex flex-wrap items-center justify-between gap-3 p-3 mb-3 bg-white border border-gray-200 rounded-lg dark:bg-gray-800 dark:border-gray-700">
                    <div class="flex items-center space-x-3">
                        <div class="flex items-center space-x-2">
                            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <span class="text-sm font-medium">No. RM: <span
                                    class="font-semibold text-brand">{{ $regNo ?: '-' }}</span></span>
                        </div>

                        @if ($regNo)
                            <div class="flex space-x-2">
                                <span
                                    class="px-2 py-1 text-sm bg-blue-100 rounded-full text-brand">{{ $this->statsKunjungan['total'] }}
                                    Total</span>
                                <span class="px-2 py-1 text-sm text-green-700 bg-green-100 rounded-full">RJ:
                                    {{ $this->statsKunjungan['rj'] }}</span>
                                <span class="px-2 py-1 text-sm text-red-700 bg-red-100 rounded-full">UGD:
                                    {{ $this->statsKunjungan['ugd'] }}</span>
                                <span class="px-2 py-1 text-sm text-purple-700 bg-purple-100 rounded-full">RI:
                                    {{ $this->statsKunjungan['ri'] }}</span>
                            </div>
                        @endif
                    </div>
                </div>

            </div>
        </div>
    </div>

    <livewire:pages::components.rekam-medis.u-g-d.cetak-rekam-medis.cetak-rekam-medis-open
        wire:key="u-g-d.rekam-medis.cetak-rekam-medis-open-ugd" />
</div>
