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
    public string $filterBulan = ''; // format mm/yyyy, default = bulan ini
    public int $itemsPerPage = 15;

    public function mount(): void
    {
        // Default bulan = bulan saat ini (mm/yyyy)
        $this->filterBulan = Carbon::now()->format('m/Y');
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
    public function updatedFilterBulan(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword']);
        $this->filterSource = 'RJ';
        $this->filterUpload = '';
        $this->filterBulan = Carbon::now()->format('m/Y');
        $this->resetPage();
    }

    /* ===============================
     | QUERY — single source per request (toggle filterSource)
     =============================== */
    #[Computed]
    public function rows()
    {
        $src = $this->filterSource;

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

        if ($src === 'RJ') {
            $q = DB::table('rstxn_rjrads as r')
                ->join('rsmst_radiologis as m', 'r.rad_id', '=', 'm.rad_id')
                ->join('rstxn_rjhdrs as h', 'r.rj_no', '=', 'h.rj_no')
                ->leftJoin('rsmst_pasiens as p', 'h.reg_no', '=', 'p.reg_no')
                ->select(array_merge([DB::raw("'RJ' as src"), 'r.rad_dtl as dtl_no', 'r.rj_no as ref_no'], $pasienCols, ['m.rad_desc', 'r.rad_price', 'r.dr_pengirim', 'r.dr_radiologi', 'r.rad_upload_pdf', 'r.rad_upload_pdf_foto', 'r.keterangan', DB::raw('CAST(r.hasil_bacaan AS VARCHAR2(4000)) as hasil_bacaan'), 'r.waktu_entry']));
        } elseif ($src === 'UGD') {
            $q = DB::table('rstxn_ugdrads as r')
                ->join('rsmst_radiologis as m', 'r.rad_id', '=', 'm.rad_id')
                ->join('rstxn_ugdhdrs as h', 'r.rj_no', '=', 'h.rj_no')
                ->leftJoin('rsmst_pasiens as p', 'h.reg_no', '=', 'p.reg_no')
                ->select(array_merge([DB::raw("'UGD' as src"), 'r.rad_dtl as dtl_no', 'r.rj_no as ref_no'], $pasienCols, ['m.rad_desc', 'r.rad_price', 'r.dr_pengirim', 'r.dr_radiologi', 'r.rad_upload_pdf', 'r.rad_upload_pdf_foto', 'r.keterangan', DB::raw('CAST(r.hasil_bacaan AS VARCHAR2(4000)) as hasil_bacaan'), 'r.waktu_entry']));
        } else {
            // RI
            $q = DB::table('rstxn_riradiologs as r')
                ->join('rsmst_radiologis as m', 'r.rad_id', '=', 'm.rad_id')
                ->join('rstxn_rihdrs as h', 'r.rihdr_no', '=', 'h.rihdr_no')
                ->leftJoin('rsmst_pasiens as p', 'h.reg_no', '=', 'p.reg_no')
                ->select(array_merge([DB::raw("'RI' as src"), 'r.rirad_no as dtl_no', 'r.rihdr_no as ref_no'], $pasienCols, ['m.rad_desc', 'r.rirad_price as rad_price', 'r.dr_pengirim', 'r.dr_radiologi', 'r.rad_upload_pdf', 'r.rad_upload_pdf_foto', 'r.keterangan', DB::raw('CAST(r.hasil_bacaan AS VARCHAR2(4000)) as hasil_bacaan'), 'r.waktu_entry']));
        }

        // Filter status upload
        if ($this->filterUpload === 'belum_foto') {
            $q->whereNull('r.rad_upload_pdf_foto');
        } elseif ($this->filterUpload === 'belum_pdf') {
            $q->whereNull('r.rad_upload_pdf');
        } elseif ($this->filterUpload === 'belum') {
            $q->where(function ($w) {
                $w->whereNull('r.rad_upload_pdf_foto')->orWhereNull('r.rad_upload_pdf');
            });
        } elseif ($this->filterUpload === 'lengkap') {
            $q->whereNotNull('r.rad_upload_pdf_foto')->whereNotNull('r.rad_upload_pdf');
        }

        $kw = trim($this->searchKeyword);
        if ($kw !== '') {
            $up = '%' . mb_strtoupper($kw) . '%';
            $q->where(function ($w) use ($kw, $up) {
                $w->whereRaw('UPPER(p.reg_name) LIKE ?', [$up])
                    ->orWhereRaw('TO_CHAR(p.reg_no) LIKE ?', ['%' . $kw . '%'])
                    ->orWhereRaw('UPPER(m.rad_desc) LIKE ?', [$up]);
            });
        }

        // Filter bulan (format mm/yyyy) → EXTRACT month + year dari waktu_entry
        $bulan = trim($this->filterBulan);
        if (preg_match('/^(\d{1,2})\/(\d{4})$/', $bulan, $m)) {
            $bln = (int) $m[1];
            $thn = (int) $m[2];
            if ($bln >= 1 && $bln <= 12) {
                $q->whereRaw('EXTRACT(MONTH FROM r.waktu_entry) = ?', [$bln])->whereRaw('EXTRACT(YEAR FROM r.waktu_entry) = ?', [$thn]);
            }
        }

        return $q
            ->orderByDesc('r.waktu_entry')
            ->orderByDesc('r.' . ($src === 'RI' ? 'rirad_no' : 'rad_dtl'))
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

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-white dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div
                class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-end gap-3">

                    {{-- SEARCH (icon prefix) --}}
                    <div class="w-full sm:flex-1">
                        <x-input-label value="Pencarian" class="sr-only" />
                        <div class="relative mt-1">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-gray-700" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <x-text-input wire:model.live.debounce.300ms="searchKeyword" class="block w-full pl-10"
                                placeholder="Cari No RM / Nama Pasien / Pemeriksaan..." />
                        </div>
                    </div>

                    {{-- BULAN (icon calendar) --}}
                    <div class="w-full sm:w-auto">
                        <x-input-label value="Bulan" />
                        <div class="relative mt-1">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-gray-700" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <x-text-input wire:model.live.debounce.500ms="filterBulan"
                                class="block w-full pl-10 sm:w-32" placeholder="mm/yyyy" maxlength="7" />
                        </div>
                    </div>

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
                        <x-secondary-button type="button" wire:click="resetFilters" class="whitespace-nowrap">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Reset
                        </x-secondary-button>
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
                class="mt-4 flex flex-col flex-1 min-h-0 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                {{-- TABLE SCROLL AREA (sticky thead, card-style rows) --}}
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="min-w-full text-base border-separate border-spacing-y-3">
                        <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800">
                            <tr
                                class="text-sm font-semibold tracking-wide text-left text-gray-600 uppercase dark:text-gray-300">
                                <th class="px-6 py-3 whitespace-nowrap">Tgl Order &amp; Sumber</th>
                                <th class="px-6 py-3">Pasien</th>
                                <th class="px-6 py-3">Pemeriksaan</th>
                                <th class="px-6 py-3">Permintaan</th>
                                <th class="px-6 py-3 text-center">Foto</th>
                                <th class="px-6 py-3 text-center">Hasil Bacaan</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($this->rows as $r)
                                @php
                                    $isFotoOk = !empty($r->rad_upload_pdf_foto);
                                    $isHasilOk = !empty($r->rad_upload_pdf);
                                    $isLengkap = $isFotoOk && $isHasilOk;

                                    // Standar baru: file di private disk, akses via route('files.show').
                                    // Backward-compat: row lama berisi full path 'Radiologi/Foto/x.pdf' (public legacy)
                                    // → fallback ke asset('storage/...').
                                    $fotoUrl = $isFotoOk
                                        ? (str_contains($r->rad_upload_pdf_foto, '/')
                                            ? asset('storage/' . $r->rad_upload_pdf_foto)
                                            : route('files.show', ['path' => 'mount/penunjang/radiologi/' . $r->rad_upload_pdf_foto]))
                                        : null;
                                    $hasilUrl = $isHasilOk
                                        ? (str_contains($r->rad_upload_pdf, '/')
                                            ? asset('storage/' . $r->rad_upload_pdf)
                                            : route('files.show', ['path' => 'mount/penunjang/radiologi/' . $r->rad_upload_pdf]))
                                        : null;
                                @endphp
                                <tr wire:key="rad-row-{{ $r->src }}-{{ $r->dtl_no }}-{{ $r->ref_no }}"
                                    class="transition rounded-2xl shadow-sm ring-1 ring-gray-200 dark:ring-gray-700
                                    {{ $isLengkap
                                        ? 'bg-white dark:bg-gray-900 hover:shadow-lg hover:bg-green-50 dark:hover:bg-gray-800'
                                        : 'bg-amber-50 dark:bg-amber-900/10 hover:shadow-md hover:bg-amber-100 dark:hover:bg-amber-900/20 border-l-4 border-amber-400' }}">

                                    {{-- TGL ORDER & SUMBER --}}
                                    <td class="px-6 py-6 space-y-2 align-top whitespace-nowrap">
                                        <div class="text-base font-semibold text-gray-700 dark:text-gray-200">
                                            {{ $r->waktu_entry ? \Carbon\Carbon::parse($r->waktu_entry)->format('d/m/Y H:i') : '-' }}
                                        </div>
                                        <div>
                                            <x-badge variant="alternative">{{ $r->src }}</x-badge>
                                        </div>
                                    </td>

                                    {{-- PASIEN --}}
                                    <td class="px-6 py-6 space-y-1 align-top">
                                        <div class="text-base font-medium text-gray-700 dark:text-gray-300">
                                            {{ $r->reg_no ?? '-' }}
                                        </div>
                                        <div class="text-lg font-semibold text-brand dark:text-white">
                                            {{ $r->reg_name ?? '-' }} /
                                            ({{ $r->sex === 'L' ? 'Laki-Laki' : ($r->sex === 'P' ? 'Perempuan' : '-') }})
                                        </div>
                                        <div class="text-sm text-gray-700 dark:text-gray-400">
                                            {{ $r->birth_date ?? '-' }}
                                            @if (!empty($r->umur_format))
                                                <span class="text-gray-500">({{ $r->umur_format }})</span>
                                            @endif
                                        </div>
                                        @if (!empty($r->address))
                                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                                {{ $r->address }}
                                            </div>
                                        @endif
                                    </td>

                                    {{-- PEMERIKSAAN --}}
                                    <td class="px-6 py-6 align-top">
                                        <div class="font-semibold text-brand dark:text-emerald-400">
                                            {{ $r->rad_desc }}
                                        </div>
                                    </td>

                                    {{-- DR. PENGIRIM & KETERANGAN (stack atas-bawah) --}}
                                    <td class="px-6 py-6 align-top space-y-2">
                                        <div>
                                            <x-input-label value="Dokter Pengirim" class="text-xs" />
                                            <x-text-input :value="$r->dr_pengirim"
                                                wire:change="updateDrPengirim('{{ $r->src }}', {{ $r->dtl_no }}, {{ $r->ref_no }}, $event.target.value)"
                                                placeholder="Nama dokter pengirim" class="mt-1" />
                                        </div>
                                        <div>
                                            <x-input-label value="Keterangan" class="text-xs" />
                                            <x-text-input :value="$r->keterangan"
                                                wire:change="updateKeterangan('{{ $r->src }}', {{ $r->dtl_no }}, {{ $r->ref_no }}, $event.target.value)"
                                                placeholder="contoh: AP/lateral, sebelum kontras" class="mt-1" />
                                        </div>
                                    </td>

                                    {{-- FOTO --}}
                                    <td class="px-6 py-6 text-center align-middle whitespace-nowrap">
                                        <div class="inline-flex items-center gap-1">
                                            @if ($isFotoOk)
                                                <a href="{{ $fotoUrl }}" target="_blank"
                                                    class="inline-flex items-center justify-center px-3 py-1.5 text-sm font-medium rounded-md bg-brand-green/10 text-brand-green border border-brand-green/20 hover:bg-brand-green/20">
                                                    Lihat
                                                </a>
                                                <x-secondary-button type="button"
                                                    wire:click="$dispatch('radiologi.foto.open', { source: '{{ $r->src }}', dtlNo: {{ $r->dtl_no }}, refNo: {{ $r->ref_no }} })"
                                                    class="px-3 py-1.5 text-sm">Replace</x-secondary-button>
                                            @else
                                                <x-primary-button type="button"
                                                    wire:click="$dispatch('radiologi.foto.open', { source: '{{ $r->src }}', dtlNo: {{ $r->dtl_no }}, refNo: {{ $r->ref_no }} })"
                                                    class="px-3 py-1.5 text-sm">Upload</x-primary-button>
                                            @endif
                                        </div>
                                    </td>

                                    {{-- HASIL BACAAN --}}
                                    <td class="px-6 py-6 text-center align-middle whitespace-nowrap">
                                        <div class="inline-flex items-center gap-1">
                                            @if ($isHasilOk)
                                                <a href="{{ $hasilUrl }}" target="_blank"
                                                    class="inline-flex items-center justify-center px-3 py-1.5 text-sm font-medium rounded-md bg-brand-green/10 text-brand-green border border-brand-green/20 hover:bg-brand-green/20">
                                                    Lihat
                                                </a>
                                            @endif
                                            <x-primary-button type="button"
                                                wire:click="$dispatch('radiologi.bacaan.generate.open', { source: '{{ $r->src }}', dtlNo: {{ $r->dtl_no }}, refNo: {{ $r->ref_no }} })"
                                                class="px-3 py-1.5 text-sm">{{ !empty($r->hasil_bacaan) ? 'Edit' : 'Generate' }}</x-primary-button>
                                            <x-secondary-button type="button"
                                                wire:click="$dispatch('radiologi.bacaan.upload.open', { source: '{{ $r->src }}', dtlNo: {{ $r->dtl_no }}, refNo: {{ $r->ref_no }} })"
                                                class="px-3 py-1.5 text-sm">Upload</x-secondary-button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6"
                                        class="px-6 py-10 text-center text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-900 rounded-2xl">
                                        Tidak ada order radiologi.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- PAGINATION STICKY di bawah card --}}
                <div
                    class="sticky bottom-0 z-10 px-4 py-3 bg-white border-t border-gray-200 rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
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
