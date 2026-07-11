<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Txn\Ri\EmrRITrait;

new class extends Component {
    use WithPagination;
    // EMR traits utk lock{RJ,UGD,RI}Row + appendAdminLog{RJ,UGD,RI} (audit log batal order ke induk).
    use EmrRJTrait, EmrUGDTrait, EmrRITrait;

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
    public string $filterSource = 'ALL'; // default Semua (union RJ+UGD+RI), sama seperti list Laborat
    public string $filterUpload = ''; // '' (semua) | 'belum_foto' | 'belum_pdf' | 'belum' (any) | 'lengkap'
    public string $filterMode = 'harian'; // 'bulanan' | 'harian' — default harian (pelayanan)
    public string $filterBulan = ''; // mm/yyyy (mode bulanan)
    public string $filterTanggal = ''; // dd/mm/yyyy (mode harian)
    public int $itemsPerPage = 15;

    // Inline edit tarif (toggle Edit/Simpan) — hanya satu baris aktif via editingKey "SRC-dtl-ref"
    public ?string $editingKey = null;
    public $editTarif = null;

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
        $this->filterSource = 'ALL';
        $this->filterUpload = '';
        $this->filterMode = 'harian';
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

        // SEMUA sumber → union RJ+UGD+RI (setiap subquery difilter + ditambah waktu_sort utk urut gabungan)
        if ($this->filterSource === 'ALL') {
            $rj = $this->applyRadFilters($this->baseQueryRJ($pasienCols), 'rj_date');
            $ugd = $this->applyRadFilters($this->baseQueryUGD($pasienCols), 'rj_date');
            $ri = $this->applyRadFilters($this->baseQueryRI($pasienCols), 'entry_date');

            $union = $rj->unionAll($ugd)->unionAll($ri);

            return DB::query()->fromSub($union, 't')
                ->orderByDesc('t.waktu_sort')
                ->orderByDesc('t.dtl_no')
                ->paginate($this->itemsPerPage);
        }

        // Query dasar per sumber — dipisah ke fungsi masing2 biar eksplisit & mudah diaudit.
        // $headerDateCol = tgl fallback saat waktu_entry NULL; $dtlCol = PK detail (utk sort).
        if ($this->filterSource === 'UGD') {
            $query = $this->baseQueryUGD($pasienCols);
            $headerDateCol = 'rj_date';
            $dtlCol = 'rad_dtl';
        } elseif ($this->filterSource === 'RI') {
            $query = $this->baseQueryRI($pasienCols);
            $headerDateCol = 'entry_date';
            $dtlCol = 'rirad_no';
        } else {
            $query = $this->baseQueryRJ($pasienCols);
            $headerDateCol = 'rj_date';
            $dtlCol = 'rad_dtl';
        }

        $this->applyRadFilters($query, $headerDateCol);

        return $query
            ->orderByRaw("NVL(r.waktu_entry, h.$headerDateCol) DESC")
            ->orderByDesc('r.' . $dtlCol)
            ->paginate($this->itemsPerPage);
    }

    /* Terapkan filter (upload/keyword/tanggal) + tambah kolom waktu_efektif & waktu_sort.
       Dipakai single-source & tiap subquery UNION (source=ALL). $headerDateCol = fallback tgl header. */
    private function applyRadFilters($query, string $headerDateCol)
    {
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

        // Rentang tanggal — atas waktu_entry, fallback ke tgl header (RJ/UGD: rj_date, RI: entry_date).
        // Banyak order legacy (Oracle Dev 6i) waktu_entry-nya NULL → dulu tak pernah muncul (BETWEEN buang NULL).
        [$awal, $akhir] = $this->dateRange();
        $query
            ->addSelect(DB::raw("to_char(NVL(r.waktu_entry, h.$headerDateCol),'dd/mm/yyyy hh24:mi') as waktu_efektif"))
            ->addSelect(DB::raw("to_char(NVL(r.waktu_entry, h.$headerDateCol),'yyyymmddhh24mi') as waktu_sort"))
            ->whereRaw("NVL(r.waktu_entry, h.$headerDateCol) BETWEEN ? AND ?", [$awal, $akhir]);

        return $query;
    }

    /* Query dasar per sumber — table + join + select. Beda hanya tabel & sebagian kolom;
       kolom yang di-alias (dtl_no/ref_no/rad_price/hdr_status) diseragamkan supaya
       template & filter (rows()) tak perlu tahu sumbernya. */
    private function baseQueryRJ(array $pasienCols)
    {
        return DB::table('rstxn_rjrads as r')
            ->join('rsmst_radiologis as m', 'r.rad_id', '=', 'm.rad_id')
            ->join('rstxn_rjhdrs as h', 'r.rj_no', '=', 'h.rj_no')
            ->leftJoin('rsmst_pasiens as p', 'h.reg_no', '=', 'p.reg_no')
            ->select(array_merge([DB::raw("'RJ' as src"), 'r.rad_dtl as dtl_no', 'r.rj_no as ref_no'], $pasienCols, ['m.rad_desc', 'r.rad_price', 'r.dr_pengirim', 'r.dr_radiologi', 'r.klinis_desc', 'r.rad_upload_pdf', 'r.rad_upload_pdf_foto', 'r.keterangan', DB::raw('DBMS_LOB.GETLENGTH(r.hasil_bacaan) as hasil_bacaan'), 'r.waktu_entry', 'h.rj_status as hdr_status']));
    }

    private function baseQueryUGD(array $pasienCols)
    {
        return DB::table('rstxn_ugdrads as r')
            ->join('rsmst_radiologis as m', 'r.rad_id', '=', 'm.rad_id')
            ->join('rstxn_ugdhdrs as h', 'r.rj_no', '=', 'h.rj_no')
            ->leftJoin('rsmst_pasiens as p', 'h.reg_no', '=', 'p.reg_no')
            ->select(array_merge([DB::raw("'UGD' as src"), 'r.rad_dtl as dtl_no', 'r.rj_no as ref_no'], $pasienCols, ['m.rad_desc', 'r.rad_price', 'r.dr_pengirim', 'r.dr_radiologi', 'r.klinis_desc', 'r.rad_upload_pdf', 'r.rad_upload_pdf_foto', 'r.keterangan', DB::raw('DBMS_LOB.GETLENGTH(r.hasil_bacaan) as hasil_bacaan'), 'r.waktu_entry', 'h.rj_status as hdr_status']));
    }

    private function baseQueryRI(array $pasienCols)
    {
        return DB::table('rstxn_riradiologs as r')
            ->join('rsmst_radiologis as m', 'r.rad_id', '=', 'm.rad_id')
            ->join('rstxn_rihdrs as h', 'r.rihdr_no', '=', 'h.rihdr_no')
            ->leftJoin('rsmst_pasiens as p', 'h.reg_no', '=', 'p.reg_no')
            ->select(array_merge([DB::raw("'RI' as src"), 'r.rirad_no as dtl_no', 'r.rihdr_no as ref_no'], $pasienCols, ['m.rad_desc', 'r.rirad_price as rad_price', 'r.dr_pengirim', 'r.dr_radiologi', 'r.klinis_desc', 'r.rad_upload_pdf', 'r.rad_upload_pdf_foto', 'r.keterangan', DB::raw('DBMS_LOB.GETLENGTH(r.hasil_bacaan) as hasil_bacaan'), 'r.waktu_entry', 'h.ri_status as hdr_status']));
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

    /* Audit log edit inline → userLogs transaksi induk (kategori MR). Panggil DI
       DALAM DB::transaction (lock parent dulu). Selaras logKeParent modul lab. */
    private function logEditKeParent(string $source, int $refNo, string $keterangan): void
    {
        if ($source === 'RJ') {
            $this->lockRJRow($refNo);
            $this->appendAdminLogRJ($refNo, $keterangan, 'MR');
        } elseif ($source === 'UGD') {
            $this->lockUGDRow($refNo);
            $this->appendAdminLogUGD($refNo, $keterangan, 'MR');
        } elseif ($source === 'RI') {
            $this->lockRIRow($refNo);
            $this->appendAdminLogRI($refNo, $keterangan, 'MR');
        }
    }

    private function updateRadColumn(string $source, int $dtlNo, int $refNo, string $column, string $value, string $label): void
    {
        $value = trim($value);
        $payload = $value === '' ? null : $value;

        try {
            DB::transaction(function () use ($source, $dtlNo, $refNo, $column, $payload, $label) {
                if ($source === 'RJ') {
                    DB::table('rstxn_rjrads')->where('rad_dtl', $dtlNo)->where('rj_no', $refNo)->update([$column => $payload]);
                } elseif ($source === 'UGD') {
                    DB::table('rstxn_ugdrads')->where('rad_dtl', $dtlNo)->where('rj_no', $refNo)->update([$column => $payload]);
                } elseif ($source === 'RI') {
                    DB::table('rstxn_riradiologs')->where('rirad_no', $dtlNo)->where('rihdr_no', $refNo)->update([$column => $payload]);
                }
                $this->logEditKeParent($source, $refNo, 'Ubah ' . $label . ' Radiologi #' . $dtlNo);
            });
            $this->dispatch('toast', type: 'success', message: $label . ' disimpan.');
            unset($this->rows);
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal simpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | EDIT TARIF — toggle Edit/Simpan per baris.
     | Terkunci kalau pasien sudah pulang (lock transaksi, sama dgn kasir):
     |   RJ/UGD → rj_status harus 'A'  | RI → ri_status harus 'I'
     | Cek lock di server (authoritative) — jangan percaya state client.
     =============================== */
    public function startEditTarif(string $source, int $dtlNo, int $refNo, $price): void
    {
        if ($this->isRefLocked($source, $refNo)) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang — tarif terkunci.');
            unset($this->rows);
            return;
        }

        $this->editingKey = $source . '-' . $dtlNo . '-' . $refNo;
        $this->editTarif = (int) $price;
    }

    public function cancelEditTarif(): void
    {
        $this->editingKey = null;
        $this->editTarif = null;
    }

    public function saveTarif(): void
    {
        if ($this->editingKey === null) {
            return;
        }

        [$source, $dtlNo, $refNo] = explode('-', $this->editingKey, 3);
        $dtlNo = (int) $dtlNo;
        $refNo = (int) $refNo;

        // Re-check lock di server — pasien bisa saja baru pulang sejak baris dibuka.
        if ($this->isRefLocked($source, $refNo)) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang — tarif terkunci.');
            $this->cancelEditTarif();
            unset($this->rows);
            return;
        }

        $value = $this->editTarif;
        if ($value === '' || $value === null || !is_numeric($value) || (float) $value < 0) {
            $this->dispatch('toast', type: 'error', message: 'Tarif harus berupa angka ≥ 0.');
            return;
        }
        $payload = 0 + $value;

        try {
            DB::transaction(function () use ($source, $dtlNo, $refNo, $payload) {
                if ($source === 'RJ') {
                    DB::table('rstxn_rjrads')->where('rad_dtl', $dtlNo)->where('rj_no', $refNo)->update(['rad_price' => $payload]);
                } elseif ($source === 'UGD') {
                    DB::table('rstxn_ugdrads')->where('rad_dtl', $dtlNo)->where('rj_no', $refNo)->update(['rad_price' => $payload]);
                } elseif ($source === 'RI') {
                    DB::table('rstxn_riradiologs')->where('rirad_no', $dtlNo)->where('rihdr_no', $refNo)->update(['rirad_price' => $payload]);
                }
                $this->logEditKeParent($source, (int) $refNo, 'Ubah Tarif Radiologi #' . $dtlNo . ' → Rp' . number_format((int) $payload));
            });
            $this->dispatch('toast', type: 'success', message: 'Tarif disimpan.');
            $this->cancelEditTarif();
            unset($this->rows);
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal simpan: ' . $e->getMessage());
        }
    }

    /* Lock transaksi per order: pasien sudah pulang → tarif tidak boleh diubah.
       Selaras checkRJStatus/checkUGDStatus/checkRIStatus di EmrTrait kasir:
       status kosong dianggap belum pulang (tidak terkunci). */
    private function isRefLocked(string $source, int $refNo): bool
    {
        if ($source === 'RI') {
            $row = DB::table('rstxn_rihdrs')->select('ri_status')->where('rihdr_no', $refNo)->first();
            return $row && !empty($row->ri_status) && $row->ri_status !== 'I';
        }

        $table = $source === 'UGD' ? 'rstxn_ugdhdrs' : 'rstxn_rjhdrs';
        $row = DB::table($table)->select('rj_status')->where('rj_no', $refNo)->first();
        return $row && !empty($row->rj_status) && $row->rj_status !== 'A';
    }

    /* Batal order hanya boleh ATASAN — Admin + Supervisor Penunjang (staff Radiologi
       tidak boleh batal sendiri, harus eskalasi). Seragam dgn isAllowedBatal modul lab. */
    private function isAllowedBatal(): bool
    {
        $user = auth()->user();
        return $user && $user->hasAnyRole(['Admin', 'Supervisor Penunjang']);
    }

    /* Batalkan (hapus permanen) order radiologi di program penunjang. Hapus baris
       rstxn_*rads (sekaligus biaya) sesuai source. Guard role + lock induk
       (isRefLocked: pasien pulang → tak boleh) + audit log ke userLogs induk. */
    public function batalkanOrder(string $source, int $dtlNo, int $refNo): void
    {
        if (!$this->isAllowedBatal()) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berhak membatalkan order (hanya Admin / Supervisor Penunjang).');
            return;
        }
        if ($this->isRefLocked($source, $refNo)) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang — order terkunci, tidak bisa dibatalkan.');
            return;
        }

        try {
            DB::transaction(function () use ($source, $dtlNo, $refNo) {
                if ($source === 'RJ') {
                    $this->lockRJRow($refNo);
                    DB::table('rstxn_rjrads')->where('rad_dtl', $dtlNo)->where('rj_no', $refNo)->delete();
                    $this->appendAdminLogRJ($refNo, 'Batal Order Radiologi #' . $dtlNo, 'MR');
                } elseif ($source === 'UGD') {
                    $this->lockUGDRow($refNo);
                    DB::table('rstxn_ugdrads')->where('rad_dtl', $dtlNo)->where('rj_no', $refNo)->delete();
                    $this->appendAdminLogUGD($refNo, 'Batal Order Radiologi #' . $dtlNo, 'MR');
                } elseif ($source === 'RI') {
                    $this->lockRIRow($refNo);
                    DB::table('rstxn_riradiologs')->where('rirad_no', $dtlNo)->where('rihdr_no', $refNo)->delete();
                    $this->appendAdminLogRI($refNo, 'Batal Order Radiologi #' . $dtlNo, 'MR');
                }
            });

            $this->dispatch('toast', type: 'success', message: 'Order radiologi berhasil dibatalkan.');
            unset($this->rows);
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal membatalkan: ' . $e->getMessage());
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
                            <option value="ALL">Semua Sumber</option>
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
                        {{-- Tambah pemeriksaan radiologi utk pasien aktif hari ini (sumber aktif) --}}
                        <x-primary-button type="button"
                            wire:click="$dispatch('radiologi.tambah.open', { source: '{{ $filterSource === 'ALL' ? 'RJ' : $filterSource }}' })"
                            class="gap-1.5 whitespace-nowrap">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4v16m8-8H4" />
                            </svg>
                            Tambah Pemeriksaan
                        </x-primary-button>

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
                                <th class="px-6 py-3 whitespace-nowrap">Tgl Order &amp; Pasien</th>
                                <th class="px-6 py-3">Pemeriksaan</th>
                                <th class="px-6 py-3">Permintaan</th>
                                <th class="px-6 py-3">Foto &amp; Hasil Bacaan</th>
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

                                    // Lock tarif: pasien sudah pulang (RJ/UGD rj_status<>'A', RI ri_status<>'I').
                                    // Status kosong = belum pulang = tidak terkunci.
                                    $isTarifLocked =
                                        !empty($row->hdr_status) &&
                                        ($row->src === 'RI' ? $row->hdr_status !== 'I' : $row->hdr_status !== 'A');

                                    // Status pasien per sumber — kode 'I' beda arti:
                                    // RJ 'I'=Transfer UGD, UGD 'I'=Transfer Inap, RI 'I'=Dirawat.
                                    // Selaras pelayanan-rj / pelayanan-ugd / daftar-ri. Kosong = aktif.
                                    $statusPasienKode = $row->hdr_status !== null && $row->hdr_status !== '' ? $row->hdr_status : ($row->src === 'RI' ? 'I' : 'A');
                                    if ($row->src === 'RI') {
                                        [$statusPasienLabel, $statusPasienVariant] = match ($statusPasienKode) {
                                            'I' => ['Dirawat', 'brand'],
                                            'P' => ['Pulang', 'success'],
                                            'F' => ['Batal', 'danger'],
                                            default => [$statusPasienKode, 'alternative'],
                                        };
                                    } else {
                                        [$statusPasienLabel, $statusPasienVariant] = match ($statusPasienKode) {
                                            'A' => ['Antrian', 'alternative'],
                                            'L' => ['Selesai', 'success'],
                                            'I' => [$row->src === 'UGD' ? 'Transfer Inap' : 'Transfer UGD', 'brand'],
                                            'F' => ['Batal', 'danger'],
                                            default => [$statusPasienKode, 'alternative'],
                                        };
                                    }
                                @endphp
                                <tr wire:key="rad-row-{{ $row->src }}-{{ $row->dtl_no }}-{{ $row->ref_no }}"
                                    class="transition rounded-2xl shadow-sm ring-1 ring-hairline dark:ring-gray-700
                                    {{ $isLengkap
                                        ? 'bg-canvas dark:bg-gray-900 hover:shadow-lg hover:bg-surface-soft dark:hover:bg-gray-800'
                                        : 'bg-amber-50 dark:bg-amber-900/10 hover:shadow-md hover:bg-amber-100 dark:hover:bg-amber-900/20 border-l-4 border-amber-400' }}">

                                    {{-- TGL ORDER & PASIEN (digabung 1 kolom) --}}
                                    <td class="px-6 py-6 space-y-1 align-top">
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono text-sm text-body dark:text-gray-300 whitespace-nowrap">
                                                {{ $row->waktu_efektif ?? '-' }}
                                            </span>
                                            <x-badge :variant="['RJ' => 'info', 'UGD' => 'danger', 'RI' => 'purple'][$row->src] ?? 'alternative'">{{ ['RJ' => 'Rawat Jalan', 'UGD' => 'UGD', 'RI' => 'Rawat Inap'][$row->src] ?? $row->src }}</x-badge>
                                        </div>
                                        <div class="pt-1 text-base font-medium text-body dark:text-gray-300">
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
                                        <div class="mb-1">
                                            <x-badge :variant="$statusPasienVariant">{{ $statusPasienLabel }}</x-badge>
                                        </div>
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

                                        {{-- TARIF — toggle Edit/Simpan; terkunci setelah pasien pulang --}}
                                        @php
                                            $isEditingTarif = $editingKey === $row->src . '-' . $row->dtl_no . '-' . $row->ref_no;
                                        @endphp
                                        <div class="pt-1">
                                            <x-input-label value="Tarif" class="text-xs" />
                                            @if ($isTarifLocked)
                                                <div class="flex items-center gap-1.5 mt-1">
                                                    <span class="font-semibold text-ink dark:text-gray-200">
                                                        Rp {{ number_format((float) $row->rad_price) }}
                                                    </span>
                                                    <span
                                                        class="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] font-medium rounded-md text-amber-700 bg-amber-50 border border-amber-200 dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300"
                                                        title="Status pasien: {{ $statusPasienLabel }} — tarif terkunci (billing pindah)">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                                        </svg>
                                                        Terkunci
                                                    </span>
                                                </div>
                                            @elseif ($isEditingTarif)
                                                <div class="flex items-center gap-1.5 mt-1">
                                                    <div class="w-32">
                                                        <x-text-input-number wire:model="editTarif"
                                                            x-init="$nextTick(() => { $el.focus(); $el.select(); })"
                                                            x-on:keydown.enter.prevent="$el.blur(); $wire.saveTarif()" />
                                                    </div>
                                                    <x-primary-button type="button" wire:click="saveTarif"
                                                        wire:loading.attr="disabled" wire:target="saveTarif"
                                                        class="px-3 py-1 text-xs">
                                                        <span wire:loading.remove wire:target="saveTarif"
                                                            class="inline-flex items-center gap-1">
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2" d="M5 13l4 4L19 7" />
                                                            </svg>
                                                            Simpan
                                                        </span>
                                                        <span wire:loading wire:target="saveTarif"><x-loading class="w-4 h-4" /></span>
                                                    </x-primary-button>
                                                    <x-secondary-button type="button" wire:click="cancelEditTarif"
                                                        class="px-3 py-1 text-xs">
                                                        Batal
                                                    </x-secondary-button>
                                                </div>
                                            @else
                                                <div class="flex items-center gap-1.5 mt-1">
                                                    <span class="font-semibold text-ink dark:text-gray-200">
                                                        Rp {{ number_format((float) $row->rad_price) }}
                                                    </span>
                                                    <x-secondary-button type="button"
                                                        wire:click="startEditTarif('{{ $row->src }}', {{ $row->dtl_no }}, {{ $row->ref_no }}, {{ (int) $row->rad_price }})"
                                                        class="px-3 py-1 text-xs">
                                                        <span class="inline-flex items-center gap-1">
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2"
                                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                            </svg>
                                                            Edit
                                                        </span>
                                                    </x-secondary-button>
                                                </div>
                                            @endif
                                        </div>
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

                                    {{-- FOTO & HASIL BACAAN (digabung 1 kolom, stack atas-bawah + garis pemisah) --}}
                                    <td class="px-6 py-6 align-top whitespace-nowrap">
                                        {{-- FOTO --}}
                                        <div>
                                            <x-input-label value="Foto" class="mb-1.5 text-xs" />
                                            <div class="flex flex-wrap items-center gap-1">
                                            @if ($isFotoOk)
                                                <button type="button"
                                                    wire:click="$dispatch('radiologi.view.open', { file: '{{ addslashes($row->rad_upload_pdf_foto) }}', title: 'Foto Radiologi — {{ addslashes($row->rad_desc) }}' })"
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
                                                </button>
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
                                        </div>

                                        {{-- HASIL BACAAN --}}
                                        <div class="pt-3 mt-3 border-t border-hairline dark:border-gray-700">
                                            <x-input-label value="Hasil Bacaan" class="mb-1.5 text-xs" />
                                            <div class="flex flex-wrap items-center gap-1">
                                            @if ($isHasilOk)
                                                <button type="button"
                                                    wire:click="$dispatch('radiologi.view.open', { file: '{{ addslashes($row->rad_upload_pdf) }}', title: 'Hasil Radiologi — {{ addslashes($row->rad_desc) }}' })"
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
                                                </button>
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
                                        </div>

                                        {{-- BATALKAN ORDER — hanya ATASAN (Admin + Supervisor Penunjang); staff Radiologi tak bisa.
                                             Hapus record permanen; lock (pasien pulang) & audit log server-side. --}}
                                        @hasanyrole(['Admin', 'Supervisor Penunjang'])
                                            <div class="pt-3 mt-3 border-t border-hairline dark:border-gray-700">
                                                <x-confirm-button variant="danger" :disabled="$isTarifLocked"
                                                    action="batalkanOrder('{{ $row->src }}', {{ $row->dtl_no }}, {{ $row->ref_no }})"
                                                    title="Batalkan Order Radiologi"
                                                    message="Batalkan order radiologi ini? Order akan dihapus permanen."
                                                    confirmText="Ya, batalkan" cancelText="Batal" class="text-xs">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                    Batalkan Order
                                                </x-confirm-button>
                                            </div>
                                        @endhasanyrole
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4"
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
            <livewire:pages::transaksi.penunjang.radiologi.upload-radiologi-tambah-actions
                wire:key="upload-radiologi-tambah-actions" />

            {{-- Sibling: viewer file foto/hasil di dalam modal (listen radiologi.view.open) --}}
            <livewire:pages::transaksi.penunjang.radiologi.upload-radiologi-view-actions
                wire:key="upload-radiologi-view-actions" />

        </div> {{-- /.px-6 pt-2 pb-6 (inner) --}}
    </div> {{-- /.w-full min-h (outer) --}}
</div> {{-- /root --}}
