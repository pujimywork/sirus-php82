<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use Livewire\Attributes\Reactive;

new class extends Component {
    use WithPagination;

    // Trait
    use EmrRJTrait;

    /* =======================
     | Filter & Pagination
     * ======================= */
    #[Reactive]
    public string $regNo = '';
    public string $searchKeyword = '';
    public string $filterTahun = '';
    public string $filterLayanan = '';
    public int $itemsPerPage = 3;

    // i-Care
    public bool $isOpenRekamMedisicare = false;
    public string $icareUrlResponse = '';

    /* =======================
     | Mount
     * ======================= */
    public function mount($regNo = ''): void
    {
        $this->regNo = $regNo;
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
     | Open/Close i-Care
     * ======================= */
    public function openModalicare(): void
    {
        $this->isOpenRekamMedisicare = true;
    }

    public function closeModalicare(): void
    {
        $this->isOpenRekamMedisicare = false;
        $this->icareUrlResponse = '';
    }

    /* =======================
     | Copy Resep
     * ======================= */
    public function copyResep($txnNo, $layananStatus): void
    {
        if ($layananStatus !== 'RJ') {
            $this->dispatch('toast', type: 'error', message: 'Copy resep hanya untuk Rawat Jalan');
            return;
        }

        try {
            // Implementasi copy resep di sini
            $this->dispatch('toast', type: 'success', message: 'Copy Resep berhasil');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal copy resep: ' . $e->getMessage());
        }
    }

    /* =======================
     | i-Care BPJS
     * ======================= */
    public function myiCare($nokartuBpjs, $noSep): void
    {
        if (!$noSep) {
            $this->dispatch('toast', type: 'error', message: 'Belum Terbit SEP.');
            return;
        }

        // Cek kode dokter BPJS
        $kodeDokter = DB::table('rsmst_doctors')
            ->select('kd_dr_bpjs')
            ->where('rsmst_doctors.dr_id', auth()->user()->myuser_code)
            ->first();

        if (!$kodeDokter || !$kodeDokter->kd_dr_bpjs) {
            $this->dispatch('toast', type: 'error', message: 'Dokter tidak memiliki hak akses untuk I-Care.');
            return;
        }

        try {
            // Panggil trait iCare
            $response = $this->icare($nokartuBpjs, $kodeDokter->kd_dr_bpjs)->getOriginalContent();

            if (($response['metadata']['code'] ?? 400) == 200) {
                $this->icareUrlResponse = $response['response']['url'] ?? '';
                $this->openModalicare();
            } else {
                $this->dispatch('toast', type: 'error', message: $response['metadata']['message'] ?? 'Gagal mengakses i-Care');
            }
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal mengakses i-Care: ' . $e->getMessage());
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
     | Base Query - Computed
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
                        SELECT datadaftarpolirj_json
                        FROM rsview_rjkasir
                        WHERE rj_no = txn_no
                    )
                    WHEN layanan_status='UGD' THEN (
                        SELECT datadaftarugd_json
                        FROM rsview_ugdkasir
                        WHERE rj_no = txn_no
                    )
                    WHEN layanan_status='RI' THEN (
                        SELECT datadaftarri_json
                        FROM rsview_rihdrs
                        WHERE rihdr_no = txn_no
                    )
                    ELSE NULL
                END) AS datadaftar_json"),
            )
            ->where('reg_no', $this->regNo);

        // Filter tahun
        if ($this->filterTahun) {
            $queryBuilder->whereYear('txn_date', $this->filterTahun);
        }

        // Filter layanan
        if ($this->filterLayanan) {
            $queryBuilder->where('layanan_status', $this->filterLayanan);
        }

        // Search keyword
        if ($searchKeyword !== '') {
            $uppercaseKeyword = mb_strtoupper($searchKeyword);

            $queryBuilder->where(function ($subQuery) use ($uppercaseKeyword, $searchKeyword) {
                if (ctype_digit($searchKeyword)) {
                    $subQuery->orWhere('txn_no', 'like', "%{$searchKeyword}%");
                }

                $subQuery
                    ->orWhereRaw('UPPER(poli) LIKE ?', ["%{$uppercaseKeyword}%"])
                    ->orWhereRaw('UPPER(kd_dr_bpjs) LIKE ?', ["%{$uppercaseKeyword}%"])
                    ->orWhereRaw('UPPER(nokartu_bpjs) LIKE ?', ["%{$uppercaseKeyword}%"]);
            });
        }

        return $queryBuilder->orderBy('txn_date1', 'desc')->orderBy('layanan_status', 'desc')->orderBy('poli', 'asc');
    }

    /* =======================
     | Rows - Computed dengan Pagination
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
     | Stats kunjungan - Computed
     * ======================= */
    #[Computed]
    public function statsKunjungan()
    {
        if (!$this->regNo) {
            return [
                'total' => 0,
                'rj' => 0,
                'ugd' => 0,
                'ri' => 0,
            ];
        }

        $stats = DB::table('rsview_ermstatus')->select(DB::raw('COUNT(*) as total'), DB::raw("SUM(CASE WHEN layanan_status='RJ' THEN 1 ELSE 0 END) as rj"), DB::raw("SUM(CASE WHEN layanan_status='UGD' THEN 1 ELSE 0 END) as ugd"), DB::raw("SUM(CASE WHEN layanan_status='RI' THEN 1 ELSE 0 END) as ri"))->where('reg_no', $this->regNo)->first();

        return [
            'total' => $stats->total ?? 0,
            'rj' => $stats->rj ?? 0,
            'ugd' => $stats->ugd ?? 0,
            'ri' => $stats->ri ?? 0,
        ];
    }

    public function CetakRekamMedisRj($rjNo): void
    {
        $this->dispatch('cetak-rekam-medis.open', rjNo: $rjNo);
    }
};

?>

<div>
    {{-- CONTAINER UTAMA - SATU-SATUNYA WIRE:KEY --}}
    <div class="flex flex-col w-full">
        {{-- BODY --}}
        <div class="w-full mx-auto">
            <div
                class="w-full p-4 space-y-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                {{-- Filters --}}
                {{-- @if ($regNo)
                    <div class="grid grid-cols-1 gap-2 mb-4 md:grid-cols-6">
                        <div class="col-span-3">
                            <x-input-label value="Cari" class="text-sm" />
                            <x-text-input wire:model.live.debounce.300ms="searchKeyword" class="w-full"
                                placeholder="Kunjungan / Poli / Dr ..." />
                        </div>

                        <div>
                            <x-input-label value="Tahun" class="text-sm" />
                            <x-select-input wire:model.live="filterTahun" class="w-full">
                                <option value="">Semua Tahun</option>
                                @foreach ($this->tahunList as $tahun)
                                    <option value="{{ $tahun }}">{{ $tahun }}</option>
                                @endforeach
                            </x-select-input>
                        </div>

                        <div class="col-span-2">
                            <x-input-label value="Jenis Layanan" class="text-sm" />
                            <x-select-input wire:model.live="filterLayanan" class="w-full">
                                <option value="">Semua</option>
                                <option value="RJ">Rawat Jalan</option>
                                <option value="UGD">UGD</option>
                                <option value="RI">Rawat Inap</option>
                            </x-select-input>
                        </div>

                        <div>
                            <x-input-label value="Tampil" class="text-sm" />
                            <x-select-input wire:model.live="itemsPerPage" class="w-full">
                                <option value="3">3</option>
                                <option value="5">5</option>
                                <option value="10">10</option>
                                <option value="20">20</option>
                                <option value="50">50</option>
                            </x-select-input>
                        </div>
                    </div>
                    @if ($regNo)
                        <x-secondary-button wire:click="resetFilters">
                            Reset Filter
                        </x-secondary-button>
                    @endif
                @endif --}}

                <!-- Table Resume Medis -->
                <div class="flex flex-col my-2">
                    <div class="overflow-x-auto rounded-lg">
                        <div class="inline-block min-w-full align-middle">
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
                                                    {{-- Header --}}
                                                    <div class="flex items-start justify-between">
                                                        <div class="flex items-center space-x-2">
                                                            <span class="text-2xl">{{ $statusIcon }}</span>
                                                            <div>
                                                                <div class="flex items-center space-x-2">
                                                                    <span
                                                                        class="font-bold {{ $statusClass }}">{{ $statusText }}</span>
                                                                    <span class="text-gray-400">|</span>
                                                                    <span
                                                                        class="font-medium">{{ $myQData->reg_name }}</span>

                                                                    @if (!empty($datadaftar_json['statusPRB']['penanggungJawab']['statusPRB']))
                                                                        <span
                                                                            class="px-2 py-0.5 text-sm font-bold text-white bg-gray-800 rounded-full">PRB</span>
                                                                    @endif

                                                                    @if (($datadaftar_json['ermStatus'] ?? '') == 'L')
                                                                        <span
                                                                            class="px-2 py-0.5 text-sm font-medium text-green-700 bg-green-100 rounded-full">Selesai</span>
                                                                    @endif
                                                                </div>

                                                            </div>
                                                        </div>
                                                        <div class="text-sm text-right text-gray-500">
                                                            <div>{{ $myQData->txn_date }}</div>
                                                        </div>
                                                    </div>

                                                    {{-- Poli & Dokter --}}
                                                    <div class="flex mt-2 space-x-4 text-sm">
                                                        <div class="flex items-center space-x-1">
                                                            <svg class="w-4 h-4 text-gray-400" fill="none"
                                                                stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2"
                                                                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                                            </svg>
                                                            <span>Poli: <span
                                                                    class="font-medium">{{ $myQData->poli ?? '-' }}</span></span>
                                                        </div>

                                                    </div>

                                                    {{-- Diagnosis & Terapi --}}
                                                    <div class="grid grid-cols-1 gap-3 mt-3">
                                                        {{-- ICD10 --}}
                                                        <div class="p-2 rounded bg-gray-50">
                                                            <div class="flex items-center mb-1 space-x-1">
                                                                <svg class="w-3 h-3 text-blue-600" fill="none"
                                                                    stroke="currentColor" viewBox="0 0 24 24">
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
                                                                <svg class="w-3 h-3 text-green-600" fill="none"
                                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                                        stroke-width="2"
                                                                        d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                                </svg>
                                                                <span class="text-sm font-semibold">Dx Dokter:</span>
                                                            </div>
                                                            <div class="text-sm">
                                                                <div class="text-sm whitespace-pre-line">
                                                                    @if (!empty($datadaftar_json['diagnosisFreeText']))
                                                                        {{ $datadaftar_json['diagnosisFreeText'] }}
                                                                    @else
                                                                        <span class="text-gray-400">-</span>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </div>

                                                        {{-- Terapi --}}
                                                        <div class="p-2 rounded bg-gray-50">
                                                            <div class="flex items-center mb-1 space-x-1">
                                                                <svg class="w-3 h-3 text-purple-600" fill="none"
                                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                                        stroke-width="2"
                                                                        d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                                                </svg>
                                                                <span class="text-sm font-semibold">Terapi:</span>
                                                            </div>
                                                            <div class="text-sm">
                                                                @php
                                                                    $terapi = data_get(
                                                                        $datadaftar_json,
                                                                        'perencanaan.terapi.terapi',
                                                                        '-',
                                                                    );
                                                                @endphp

                                                                <div class="text-sm break-words whitespace-pre-line">
                                                                    {{ $terapi }}
                                                                </div>
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

                                                                    <x-primary-button type="button"
                                                                        wire:click="CetakRekamMedisRj('{{ $myQData->txn_no }}')"
                                                                        wire:loading.attr="disabled"
                                                                        wire:target="CetakRekamMedisRj('{{ $myQData->txn_no }}')">
                                                                        <span wire:loading.remove
                                                                            wire:target="CetakRekamMedisRj('{{ $myQData->txn_no }}')"
                                                                            class="flex items-center gap-1">
                                                                            <svg class="w-4 h-4" fill="none"
                                                                                stroke="currentColor" viewBox="0 0 24 24">
                                                                                <path stroke-linecap="round"
                                                                                    stroke-linejoin="round" stroke-width="2"
                                                                                    d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                                            </svg>
                                                                            Cetak Rekam Medis
                                                                        </span>
                                                                        <span wire:loading
                                                                            wire:target="CetakRekamMedisRj('{{ $myQData->txn_no }}')"
                                                                            class="flex items-center gap-1">
                                                                            <x-loading />
                                                                            Memuat...
                                                                        </span>
                                                                    </x-primary-button>
                                                                @endif

                                                                @if (!empty($datadaftar_json['sep']['noSep']))
                                                                    <x-primary-button type="button"
                                                                        wire:click="myiCare('{{ $myQData->nokartu_bpjs }}','{{ $datadaftar_json['sep']['noSep'] }}')"
                                                                        class="text-sm px-3 py-1.5" variant="info">
                                                                        <svg class="w-4 h-4 mr-1" fill="none"
                                                                            stroke="currentColor" viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round"
                                                                                stroke-linejoin="round" stroke-width="2"
                                                                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                                                        </svg>
                                                                        i-Care
                                                                    </x-primary-button>
                                                                @endif


                                                            </div>

                                                            @if (!empty($datadaftar_json['sep']['noSep']))
                                                                <span class="text-sm text-right text-gray-500">
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

                            {{-- Pagination --}}
                            @if ($regNo && $this->rows->hasPages())
                                <div class="mt-4">
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

    {{-- Modal i-Care --}}
    @if ($isOpenRekamMedisicare)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center overflow-x-hidden overflow-y-auto outline-none focus:outline-none">
            <div class="relative w-auto max-w-4xl mx-auto my-6">
                <div
                    class="relative flex flex-col w-full bg-white border-0 rounded-lg shadow-lg outline-none focus:outline-none">
                    <div
                        class="flex items-start justify-between p-5 border-b border-solid rounded-t border-blueGray-200">
                        <h3 class="text-2xl font-semibold">i-Care</h3>
                        <button wire:click="closeModalicare"
                            class="float-right p-1 ml-auto text-3xl font-semibold leading-none text-black bg-transparent border-0 outline-none focus:outline-none">
                            <span class="block w-6 h-6 text-2xl text-black">×</span>
                        </button>
                    </div>
                    <div class="relative flex-auto p-6">
                        <iframe src="{{ $icareUrlResponse }}" class="w-full h-[600px] border-0"></iframe>
                    </div>
                </div>
            </div>
        </div>
        <div class="fixed inset-0 z-40 bg-black opacity-25"></div>
    @endif


    <livewire:pages::components.rekam-medis.rekam-medis.cetak-rekam-medis.cetak-rekam-medis
        wire:key="r-j.rekam-medis.cetak-rekam-medis" />
</div>
