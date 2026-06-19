<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;

    /*
     | Modul: Upload Hasil Radiologi
     | Halaman utama — render tabel + filter. Aksi upload/generate
     | ditangani sibling component: <livewire:...upload-radiologi-actions>
     | yang listen via #[On('radiologi.foto.open' / 'radiologi.hasil.open' / 'radiologi.generate.open')].
     |
     | Sumber per source:
     |   RJ  → rstxn_rjrads     (PK rad_dtl,  ref rj_no)
     |   UGD → rstxn_ugdrads    (PK rad_dtl,  ref rj_no)
     |   RI  → rstxn_riradiologs(PK rirad_no, ref rihdr_no)
     */

    public string $searchKeyword = '';
    public string $filterSource = 'RJ';
    public string $filterUpload = ''; // '' (semua) | 'belum_foto' | 'belum_pdf' | 'belum' (any) | 'lengkap'
    public string $filterMode = 'bulanan'; // 'bulanan' | 'harian'
    public string $filterBulan = ''; // mm/yyyy (mode bulanan)
    public string $filterTanggal = ''; // dd/mm/yyyy (mode harian)
    public int $itemsPerPage = 15;

    public function mount(): void
    {
        // Default: bulan & tanggal saat ini
        $this->filterBulan = Carbon::now()->format('m/Y');
        $this->filterTanggal = Carbon::now()->format('d/m/Y');
    }

    public function updatedSearchKeyword(): void
    {
        $this->resetPage();
    }
    public function updatedFilterSource(): void
    {
        $this->resetPage();
    }
    public function updatedFilterUpload(): void
    {
        $this->resetPage();
    }
    public function updatedFilterMode(): void
    {
        $this->resetPage();
    }
    public function updatedFilterBulan(): void
    {
        $this->resetPage();
    }
    public function updatedFilterTanggal(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword']);
        $this->filterSource = 'RJ';
        $this->filterUpload = '';
        $this->filterMode = 'bulanan';
        $this->filterBulan = Carbon::now()->format('m/Y');
        $this->filterTanggal = Carbon::now()->format('d/m/Y');
        $this->resetPage();
    }

    /* Rentang tanggal aktif — harian = 1 hari, bulanan = 1 bulan (atas waktu_entry). */
    private function dateRange(): array
    {
        if ($this->filterMode === 'harian') {
            try {
                $tanggal = Carbon::createFromFormat('d/m/Y', trim($this->filterTanggal))->startOfDay();
            } catch (\Exception $e) {
                $tanggal = Carbon::now()->startOfDay();
            }
            return [$tanggal, (clone $tanggal)->endOfDay()];
        }
        try {
            $tanggal = Carbon::createFromFormat('m/Y', trim($this->filterBulan))->startOfMonth();
        } catch (\Exception $e) {
            $tanggal = Carbon::now()->startOfMonth();
        }
        return [$tanggal, (clone $tanggal)->endOfMonth()];
    }

    /* ===============================
     | QUERY — single source per request (toggle filterSource)
     =============================== */
    #[Computed]
    public function rows()
    {
        $sumber = $this->filterSource;

        // Kolom identitas pasien yang sama dipakai di 3 query — birth_date jadi string,
        // umur_format dihitung di Oracle via SQL biar ringan & konsisten.
        $pasienCols = [
            'p.reg_no',
            'p.reg_name',
            'p.sex',
            'p.address',
            DB::raw("to_char(p.birth_date,'dd/mm/yyyy') as birth_date"),
            DB::raw("CASE WHEN p.birth_date IS NOT NULL THEN
                trunc(months_between(sysdate, p.birth_date) / 12) || ' Thn ' ||
                trunc(mod(months_between(sysdate, p.birth_date), 12)) || ' Bln ' ||
                trunc(sysdate - add_months(p.birth_date, trunc(months_between(sysdate, p.birth_date)))) || ' Hr'
                ELSE NULL END as umur_format"),
        ];

        if ($sumber === 'RJ') {
            $query = DB::table('rstxn_rjrads as r')
                ->join('rsmst_radiologis as m', 'r.rad_id', '=', 'm.rad_id')
                ->join('rstxn_rjhdrs as h', 'r.rj_no', '=', 'h.rj_no')
                ->leftJoin('rsmst_pasiens as p', 'h.reg_no', '=', 'p.reg_no')
                ->select(array_merge([DB::raw("'RJ' as src"), 'r.rad_dtl as dtl_no', 'r.rj_no as ref_no'], $pasienCols, ['m.rad_desc', 'r.rad_price', 'r.dr_pengirim', 'r.dr_radiologi', 'r.klinis_desc', 'r.rad_upload_pdf', 'r.rad_upload_pdf_foto', 'r.keterangan', DB::raw('CAST(r.hasil_bacaan AS VARCHAR2(4000)) as hasil_bacaan'), 'r.waktu_entry']));
        } elseif ($sumber === 'UGD') {
            $query = DB::table('rstxn_ugdrads as r')
                ->join('rsmst_radiologis as m', 'r.rad_id', '=', 'm.rad_id')
                ->join('rstxn_ugdhdrs as h', 'r.rj_no', '=', 'h.rj_no')
                ->leftJoin('rsmst_pasiens as p', 'h.reg_no', '=', 'p.reg_no')
                ->select(array_merge([DB::raw("'UGD' as src"), 'r.rad_dtl as dtl_no', 'r.rj_no as ref_no'], $pasienCols, ['m.rad_desc', 'r.rad_price', 'r.dr_pengirim', 'r.dr_radiologi', 'r.klinis_desc', 'r.rad_upload_pdf', 'r.rad_upload_pdf_foto', 'r.keterangan', DB::raw('CAST(r.hasil_bacaan AS VARCHAR2(4000)) as hasil_bacaan'), 'r.waktu_entry']));
        } else {
            // RI
            $query = DB::table('rstxn_riradiologs as r')
                ->join('rsmst_radiologis as m', 'r.rad_id', '=', 'm.rad_id')
                ->join('rstxn_rihdrs as h', 'r.rihdr_no', '=', 'h.rihdr_no')
                ->leftJoin('rsmst_pasiens as p', 'h.reg_no', '=', 'p.reg_no')
                ->select(array_merge([DB::raw("'RI' as src"), 'r.rirad_no as dtl_no', 'r.rihdr_no as ref_no'], $pasienCols, ['m.rad_desc', 'r.rirad_price as rad_price', 'r.dr_pengirim', 'r.dr_radiologi', 'r.klinis_desc', 'r.rad_upload_pdf', 'r.rad_upload_pdf_foto', 'r.keterangan', DB::raw('CAST(r.hasil_bacaan AS VARCHAR2(4000)) as hasil_bacaan'), 'r.waktu_entry']));
        }

        // Filter status upload
        if ($this->filterUpload === 'belum_foto') {
            $query->whereNull('r.rad_upload_pdf_foto');
        } elseif ($this->filterUpload === 'belum_pdf') {
            $query->whereNull('r.rad_upload_pdf');
        } elseif ($this->filterUpload === 'belum') {
            $query->where(function ($subQuery) {
                $subQuery->whereNull('r.rad_upload_pdf_foto')->orWhereNull('r.rad_upload_pdf');
            });
        } elseif ($this->filterUpload === 'lengkap') {
            $query->whereNotNull('r.rad_upload_pdf_foto')->whereNotNull('r.rad_upload_pdf');
        }

        $keyword = trim($this->searchKeyword);
        if ($keyword !== '') {
            $keywordUpper = '%' . mb_strtoupper($keyword) . '%';
            $query->where(function ($subQuery) use ($keyword, $keywordUpper) {
                $subQuery->whereRaw('UPPER(p.reg_name) LIKE ?', [$keywordUpper])
                    ->orWhereRaw('TO_CHAR(p.reg_no) LIKE ?', ['%' . $keyword . '%'])
                    ->orWhereRaw('UPPER(m.rad_desc) LIKE ?', [$keywordUpper]);
            });
        }

        // Filter rentang tanggal — bulanan (1 bulan) / harian (1 hari) atas waktu_entry
        [$awal, $akhir] = $this->dateRange();
        $query->whereBetween('r.waktu_entry', [$awal, $akhir]);

        return $query
            ->orderByDesc('r.waktu_entry')
            ->orderByDesc('r.' . ($sumber === 'RI' ? 'rirad_no' : 'rad_dtl'))
            ->paginate($this->itemsPerPage);
    }

    /* ===============================
     | UPDATE KETERANGAN — inline edit per row
     =============================== */
    public function updateKeterangan(string $source, int $dtlNo, int $refNo, string $value): void
    {
        $this->updateRadColumn($source, $dtlNo, $refNo, 'keterangan', $value, 'Keterangan');
    }

    /* ===============================
     | UPDATE DR. PENGIRIM — inline edit per row (free-text nama dokter)
     =============================== */
    public function updateDrPengirim(string $source, int $dtlNo, int $refNo, string $value): void
    {
        $this->updateRadColumn($source, $dtlNo, $refNo, 'dr_pengirim', $value, 'Dokter Pengirim');
    }

    private function updateRadColumn(string $source, int $dtlNo, int $refNo, string $column, string $value, string $label): void
    {
        $value = trim($value);
        $payload = $value === '' ? null : $value;

        try {
            if ($source === 'RJ') {
                DB::table('rstxn_rjrads')->where('rad_dtl', $dtlNo)->where('rj_no', $refNo)->update([$column => $payload]);
            } elseif ($source === 'UGD') {
                DB::table('rstxn_ugdrads')->where('rad_dtl', $dtlNo)->where('rj_no', $refNo)->update([$column => $payload]);
            } elseif ($source === 'RI') {
                DB::table('rstxn_riradiologs')->where('rirad_no', $dtlNo)->where('rihdr_no', $refNo)->update([$column => $payload]);
            }
            $this->dispatch('toast', type: 'success', message: $label . ' disimpan.');
            unset($this->rows);
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal simpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | LISTENER — invalidate rows() setelah upload/generate dari sibling actions
     =============================== */
    #[On('radiologi-refresh')]
    public function refreshRows(): void
    {
        unset($this->rows);
    }
};
?>

<div>
    <x-page-title
        title="Upload Hasil Radiologi"
        subtitle="Upload foto radiologi &amp; hasil bacaan PDF untuk order pemeriksaan" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div
                class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3">

                    {{-- SEARCH (icon prefix) --}}
                    <div class="w-full sm:flex-1">
                        <x-input-label value="Pencarian" class="sr-only" />
                        <div class="relative mt-1">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-body" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <x-text-input wire:model.live.debounce.300ms="searchKeyword" class="block w-full pl-10"
                                placeholder="Cari No RM / Nama Pasien / Pemeriksaan..." />
                        </div>
                    </div>

                    {{-- MODE FILTER: Bulanan / Harian --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Mode" />
                        <div class="inline-flex mt-1 overflow-hidden border border-gray-300 rounded-lg dark:border-gray-600">
                            <button type="button" wire:click="$set('filterMode', 'bulanan')"
                                class="px-3 py-1.5 text-xs font-medium transition-colors
                                    {{ $filterMode === 'bulanan' ? 'bg-brand text-white dark:bg-brand-lime dark:text-gray-900' : 'bg-canvas text-muted hover:bg-surface-soft dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                                Bulanan
                            </button>
                            <button type="button" wire:click="$set('filterMode', 'harian')"
                                class="px-3 py-1.5 text-xs font-medium transition-colors border-l border-gray-300 dark:border-gray-600
                                    {{ $filterMode === 'harian' ? 'bg-brand text-white dark:bg-brand-lime dark:text-gray-900' : 'bg-canvas text-muted hover:bg-surface-soft dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                                Harian
                            </button>
                        </div>
                    </div>

                    {{-- BULAN / TANGGAL (Tgl Order) — icon calendar --}}
                    @if ($filterMode === 'bulanan')
                        <div class="w-full sm:w-auto">
                            <x-input-label value="Bulan (Tgl Order)" />
                            <div class="relative mt-1">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <svg class="w-4 h-4 text-body" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <x-text-input wire:model.live.debounce.500ms="filterBulan"
                                    class="block w-full pl-10 sm:w-32" placeholder="mm/yyyy" maxlength="7" />
                            </div>
                        </div>
                    @else
                        <div class="w-full sm:w-auto">
                            <x-input-label value="Tanggal (Tgl Order)" />
                            <div class="relative mt-1">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <svg class="w-4 h-4 text-body" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <x-text-input wire:model.live.debounce.500ms="filterTanggal"
                                    class="block w-full pl-10 sm:w-40" placeholder="dd/mm/yyyy" maxlength="10" />
                            </div>
                        </div>
                    @endif

                    {{-- SUMBER --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Sumber" />
                        <x-select-input wire:model.live="filterSource" class="w-full mt-1 sm:w-44">
                            <option value="RJ">Rawat Jalan</option>
                            <option value="UGD">Unit Gawat Darurat</option>
                            <option value="RI">Rawat Inap</option>
                        </x-select-input>
                    </div>

                    {{-- STATUS UPLOAD --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Status Upload" />
                        <x-select-input wire:model.live="filterUpload" class="w-full mt-1 sm:w-44">
                            <option value="">Semua</option>
                            <option value="belum">Belum lengkap</option>
                            <option value="belum_foto">Foto belum upload</option>
                            <option value="belum_pdf">Hasil belum upload</option>
                            <option value="lengkap">Sudah lengkap</option>
                        </x-select-input>
                    </div>

                    {{-- RIGHT ACTIONS --}}
                    <div class="flex items-center gap-2 ml-auto">
                        {{-- Tombol standar Refresh + Reset (komponen; tanpa label kolom) --}}
                        <x-toolbar-refresh-reset :label="null" />
                        <div class="w-28">
                            <x-select-input wire:model.live="itemsPerPage">
                                <option value="10">10</option>
                                <option value="15">15</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </x-select-input>
                        </div>
                    </div>
                </div>
            </div>

            {{-- TABLE WRAPPER: card --}}
            <div
                class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                {{-- TABLE SCROLL AREA (sticky thead, card-style rows) --}}
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="min-w-full text-base -mt-3 border-separate border-spacing-y-3">
                        <thead class="sticky top-0 z-10 [&_th]:bg-surface-card dark:[&_th]:bg-gray-800">
                            <tr
                                class="text-sm font-semibold tracking-wide text-left text-muted uppercase dark:text-gray-300">
                                <th class="px-6 py-3 whitespace-nowrap">Tgl Order &amp; Sumber</th>
                                <th class="px-6 py-3">Pasien</th>
                                <th class="px-6 py-3">Pemeriksaan</th>
                                <th class="px-6 py-3">Permintaan</th>
                                <th class="px-6 py-3 text-center">Foto</th>
                                <th class="px-6 py-3 text-center">Hasil Bacaan</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($this->rows as $row)
                                @php
                                    $isFotoOk = !empty($row->rad_upload_pdf_foto);
                                    $isHasilOk = !empty($row->rad_upload_pdf);
                                    $isLengkap = $isFotoOk && $isHasilOk;

                                    // Standar baru: file di private disk, akses via route('files.show').
                                    // Backward-compat: row lama berisi full path 'Radiologi/Foto/x.pdf' (public legacy)
                                    // → fallback ke asset('storage/...').
                                    $fotoUrl = $isFotoOk
                                        ? (str_contains($row->rad_upload_pdf_foto, '/')
                                            ? asset('storage/' . $row->rad_upload_pdf_foto)
                                            : route('files.show', ['path' => 'mount/penunjang/radiologi/' . $row->rad_upload_pdf_foto]))
                                        : null;
                                    $hasilUrl = $isHasilOk
                                        ? (str_contains($row->rad_upload_pdf, '/')
                                            ? asset('storage/' . $row->rad_upload_pdf)
                                            : route('files.show', ['path' => 'mount/penunjang/radiologi/' . $row->rad_upload_pdf]))
                                        : null;
                                @endphp
                                <tr wire:key="rad-row-{{ $row->src }}-{{ $row->dtl_no }}-{{ $row->ref_no }}"
                                    class="transition rounded-2xl shadow-sm ring-1 ring-hairline dark:ring-gray-700
                                    {{ $isLengkap
                                        ? 'bg-canvas dark:bg-gray-900 hover:shadow-lg hover:bg-surface-soft dark:hover:bg-gray-800'
                                        : 'bg-amber-50 dark:bg-amber-900/10 hover:shadow-md hover:bg-amber-100 dark:hover:bg-amber-900/20 border-l-4 border-amber-400' }}">

                                    {{-- TGL ORDER & SUMBER --}}
                                    <td class="px-6 py-6 space-y-2 align-top whitespace-nowrap">
                                        <div class="text-base font-semibold text-body dark:text-gray-200">
                                            {{ $row->waktu_entry ? \Carbon\Carbon::parse($row->waktu_entry)->format('d/m/Y H:i') : '-' }}
                                        </div>
                                        <div>
                                            <x-badge variant="alternative">{{ $row->src }}</x-badge>
                                        </div>
                                    </td>

                                    {{-- PASIEN --}}
                                    <td class="px-6 py-6 space-y-1 align-top">
                                        <div class="text-base font-medium text-body dark:text-gray-300">
                                            {{ $row->reg_no ?? '-' }}
                                        </div>
                                        <div class="text-lg font-semibold text-brand dark:text-white">
                                            {{ $row->reg_name ?? '-' }} /
                                            ({{ $row->sex === 'L' ? 'Laki-Laki' : ($row->sex === 'P' ? 'Perempuan' : '-') }})
                                        </div>
                                        <div class="text-sm text-body dark:text-gray-400">
                                            {{ $row->birth_date ?? '-' }}
                                            @if (!empty($row->umur_format))
                                                <span class="text-muted">({{ $row->umur_format }})</span>
                                            @endif
                                        </div>
                                        @if (!empty($row->address))
                                            <div class="text-sm text-muted dark:text-gray-400">
                                                {{ $row->address }}
                                            </div>
                                        @endif
                                    </td>

                                    {{-- PEMERIKSAAN --}}
                                    <td class="px-6 py-6 align-top space-y-1">
                                        <div class="font-semibold text-brand dark:text-emerald-400">
                                            {{ $row->rad_desc }}
                                        </div>
                                        @if (!empty($row->klinis_desc))
                                            <div class="text-sm max-w-xs">
                                                <span class="text-muted">Klinis:</span>
                                                <span class="ml-1 font-medium text-amber-700 dark:text-amber-400"
                                                    title="{{ $row->klinis_desc }}">{{ $row->klinis_desc }}</span>
                                            </div>
                                        @endif
                                    </td>

                                    {{-- DR. PENGIRIM & KETERANGAN (stack atas-bawah) --}}
                                    <td class="px-6 py-6 align-top space-y-2">
                                        <div>
                                            <x-input-label value="Dokter Pengirim" class="text-xs" />
                                            <x-text-input :value="$row->dr_pengirim"
                                                wire:change="updateDrPengirim('{{ $row->src }}', {{ $row->dtl_no }}, {{ $row->ref_no }}, $event.target.value)"
                                                placeholder="Nama dokter pengirim" class="mt-1" />
                                        </div>
                                        <div>
                                            <x-input-label value="Keterangan" class="text-xs" />
                                            <x-text-input :value="$row->keterangan"
                                                wire:change="updateKeterangan('{{ $row->src }}', {{ $row->dtl_no }}, {{ $row->ref_no }}, $event.target.value)"
                                                placeholder="contoh: AP/lateral, sebelum kontras" class="mt-1" />
                                        </div>
                                    </td>

                                    {{-- FOTO --}}
                                    <td class="px-6 py-6 text-center align-middle whitespace-nowrap">
                                        <div class="inline-flex items-center gap-1">
                                            @if ($isFotoOk)
                                                <a href="{{ $fotoUrl }}" target="_blank"
                                                    class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-md bg-brand-green/10 text-brand-green border border-brand-green/20 hover:bg-brand-green/20">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                    </svg>
                                                    Lihat
                                                </a>
                                                <x-secondary-button type="button"
                                                    wire:click="$dispatch('radiologi.foto.open', { source: '{{ $row->src }}', dtlNo: {{ $row->dtl_no }}, refNo: {{ $row->ref_no }} })"
                                                    class="px-3 py-1.5 text-sm">
                                                    <span class="flex items-center gap-1.5">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                        </svg>
                                                        Replace
                                                    </span>
                                                </x-secondary-button>
                                            @else
                                                <x-primary-button type="button"
                                                    wire:click="$dispatch('radiologi.foto.open', { source: '{{ $row->src }}', dtlNo: {{ $row->dtl_no }}, refNo: {{ $row->ref_no }} })"
                                                    class="px-3 py-1.5 text-sm">
                                                    <span class="flex items-center gap-1.5">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                                        </svg>
                                                        Upload
                                                    </span>
                                                </x-primary-button>
                                            @endif
                                        </div>
                                    </td>

                                    {{-- HASIL BACAAN --}}
                                    <td class="px-6 py-6 text-center align-middle whitespace-nowrap">
                                        <div class="inline-flex items-center gap-1">
                                            @if ($isHasilOk)
                                                <a href="{{ $hasilUrl }}" target="_blank"
                                                    class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-md bg-brand-green/10 text-brand-green border border-brand-green/20 hover:bg-brand-green/20">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                    </svg>
                                                    Lihat
                                                </a>
                                            @endif
                                            @if ($isHasilOk || !empty($row->hasil_bacaan))
                                                {{-- Sudah ada hasil (PDF terupload / bacaan tergenerate) → secondary, pembeda dari yg belum (ala Replace di Foto) --}}
                                                <x-secondary-button type="button"
                                                    wire:click="$dispatch('radiologi.bacaan.generate.open', { source: '{{ $row->src }}', dtlNo: {{ $row->dtl_no }}, refNo: {{ $row->ref_no }} })"
                                                    class="px-3 py-1.5 text-sm">
                                                    <span class="flex items-center gap-1.5">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                        </svg>
                                                        {{ !empty($row->hasil_bacaan) ? 'Edit' : 'Generate' }}
                                                    </span>
                                                </x-secondary-button>
                                            @else
                                                <x-primary-button type="button"
                                                    wire:click="$dispatch('radiologi.bacaan.generate.open', { source: '{{ $row->src }}', dtlNo: {{ $row->dtl_no }}, refNo: {{ $row->ref_no }} })"
                                                    class="px-3 py-1.5 text-sm">
                                                    <span class="flex items-center gap-1.5">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                        </svg>
                                                        Generate
                                                    </span>
                                                </x-primary-button>
                                            @endif
                                            <x-secondary-button type="button"
                                                wire:click="$dispatch('radiologi.bacaan.upload.open', { source: '{{ $row->src }}', dtlNo: {{ $row->dtl_no }}, refNo: {{ $row->ref_no }} })"
                                                class="px-3 py-1.5 text-sm">
                                                <span class="flex items-center gap-1.5">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                                    </svg>
                                                    Upload
                                                </span>
                                            </x-secondary-button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6"
                                        class="px-6 py-10 text-center text-muted dark:text-gray-400 bg-canvas dark:bg-gray-900 rounded-2xl">
                                        Tidak ada order radiologi.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- PAGINATION STICKY di bawah card --}}
                <div
                    class="sticky bottom-0 z-10 px-4 py-3 bg-canvas border-t border-hairline rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>

            {{-- Sibling actions — dipisah supaya domain Foto vs Hasil Bacaan independen --}}
            <livewire:pages::transaksi.penunjang.radiologi.upload-radiologi-foto-actions
                wire:key="upload-radiologi-foto-actions" />
            <livewire:pages::transaksi.penunjang.radiologi.upload-radiologi-bacaan-actions
                wire:key="upload-radiologi-bacaan-actions" />

        </div> {{-- /.px-6 pt-2 pb-6 (inner) --}}
    </div> {{-- /.w-full min-h (outer) --}}
</div> {{-- /root --}}
