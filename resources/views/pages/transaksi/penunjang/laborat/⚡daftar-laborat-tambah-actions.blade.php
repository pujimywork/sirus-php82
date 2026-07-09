<?php
// resources/views/pages/transaksi/penunjang/laborat/⚡daftar-laborat-tambah-actions.blade.php
//
// Sibling action component — TAMBAH PEMERIKSAAN LABORATORIUM (dari sisi laborat).
// Setara fitur "Tambah Pemeriksaan Radiologi": petugas lab bisa menambahkan order
// untuk pasien yang AKTIF hari ini ketika ruangan tidak mengirim order lewat sistem.
//   RJ/UGD → rj_status='A' & rj_date hari ini
//   RI     → ri_status='I' (tanpa filter tgl; pasien bisa masuk hari sebelumnya)
//
// Listener event dari halaman utama:
//   - laborat.tambah.open  (source)
//
// Insert mengikuti pola EMR ruangan (rm-laborat-{rj,ugd,ri}-actions):
//   header  → lbtxn_checkuphdrs (checkup_no = NVL(MAX)+1, status_rjri = source,
//             checkup_status 'P', ref_no = rj_no/rihdr_no, dr_id numerik)
//   detail  → lbtxn_checkupdtls (+ child items berdasarkan clabitem_group)
//
// Setelah insert sukses, dispatch 'refresh-after-lab.saved' ke parent (sudah di-listen).

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use App\Support\OracleLob;
use App\Http\Traits\WithValidationToast\WithValidationToastTrait;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Txn\Ri\EmrRITrait;

new class extends Component {
    use WithPagination, WithValidationToastTrait;
    // Audit log terpadu — pakai helper appendAdminLog{RJ,UGD,RI} yang sudah ada.
    use EmrRJTrait, EmrUGDTrait, EmrRITrait;

    public string $source = 'RJ';

    // Pasien terpilih
    public string $patientSearch = '';
    public ?string $filterBangsal = null; // filter bangsal — hanya untuk source RI
    public ?int $selectedRefNo = null;
    public array $selectedPatient = []; // reg_no, reg_name, sex, birth_date, umur_format, address

    // Dokter pengirim + keterangan klinis
    public ?string $drId = null;
    public ?string $klinisDesc = null;

    // Pemilihan item pemeriksaan (keranjang)
    public string $searchItem = '';
    public array $selectedItems = []; // [ clabitem_id => [...item] ]

    /* ===============================
     | OPEN / CLOSE MODAL
     =============================== */
    #[On('laborat.tambah.open')]
    public function openTambahModal(string $source = 'RJ'): void
    {
        $this->source = in_array($source, ['RJ', 'UGD', 'RI'], true) ? $source : 'RJ';
        $this->resetState();
        $this->dispatch('open-modal', name: 'lab-tambah');
    }

    public function closeTambahModal(): void
    {
        $this->dispatch('close-modal', name: 'lab-tambah');
        $this->resetState();
    }

    private function resetState(): void
    {
        $this->reset(['patientSearch', 'filterBangsal', 'selectedRefNo', 'selectedPatient', 'drId', 'klinisDesc', 'searchItem', 'selectedItems']);
        $this->resetValidation();
        $this->resetPage();
    }

    public function setSource(string $source): void
    {
        if (!in_array($source, ['RJ', 'UGD', 'RI'], true) || $source === $this->source) {
            return;
        }
        $this->source = $source;
        // Pasien & keranjang tergantung sumber → reset saat berganti.
        $this->reset(['patientSearch', 'filterBangsal', 'selectedRefNo', 'selectedPatient', 'drId', 'klinisDesc', 'searchItem', 'selectedItems']);
        $this->resetValidation();
        $this->resetPage();
        unset($this->activePatients);
    }

    /* ===============================
     | DAFTAR PASIEN AKTIF HARI INI (per source)
     =============================== */
    #[Computed]
    public function activePatients()
    {
        $keyword = trim($this->patientSearch);

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

        if ($this->source === 'RJ') {
            $query = DB::table('rstxn_rjhdrs as h')
                ->join('rsmst_pasiens as p', 'h.reg_no', '=', 'p.reg_no')
                ->leftJoin('rsmst_polis as po', 'po.poli_id', '=', 'h.poli_id')
                ->leftJoin('rsmst_doctors as dr', 'dr.dr_id', '=', 'h.dr_id')
                ->leftJoin('rsmst_klaimtypes as kt', 'kt.klaim_id', '=', 'h.klaim_id')
                ->whereRaw("NVL(h.rj_status,'A') = 'A'")
                ->whereRaw('TRUNC(h.rj_date) = TRUNC(sysdate)')
                ->select(array_merge([
                    'h.rj_no as ref_no', 'po.poli_desc', 'dr.dr_name as dokter_name', 'kt.klaim_desc', 'h.no_antrian',
                    DB::raw("to_char(h.rj_date,'dd/mm/yyyy hh24:mi:ss') as masuk_date"),
                ], $pasienCols));
        } elseif ($this->source === 'UGD') {
            $query = DB::table('rstxn_ugdhdrs as h')
                ->join('rsmst_pasiens as p', 'h.reg_no', '=', 'p.reg_no')
                ->leftJoin('rsmst_doctors as dr', 'dr.dr_id', '=', 'h.dr_id')
                ->leftJoin('rsmst_klaimtypes as kt', 'kt.klaim_id', '=', 'h.klaim_id')
                ->leftJoin('rsmst_entrytypes as et', 'et.entry_id', '=', 'h.entry_id')
                ->whereRaw("NVL(h.rj_status,'A') = 'A'")
                ->whereRaw('TRUNC(h.rj_date) = TRUNC(sysdate)')
                ->select(array_merge([
                    'h.rj_no as ref_no', 'dr.dr_name as dokter_name', 'kt.klaim_desc', 'et.entry_desc', 'h.no_antrian',
                    DB::raw("to_char(h.rj_date,'dd/mm/yyyy hh24:mi:ss') as masuk_date"),
                ], $pasienCols));
        } else {
            // RI — aktif = ri_status 'I' (tanpa filter tanggal)
            $query = DB::table('rstxn_rihdrs as h')
                ->join('rsmst_pasiens as p', 'h.reg_no', '=', 'p.reg_no')
                ->leftJoin('rsmst_rooms as rm', 'rm.room_id', '=', 'h.room_id')
                ->leftJoin('rsmst_bangsals as bg', 'bg.bangsal_id', '=', 'rm.bangsal_id')
                ->leftJoin('rsmst_doctors as dr', 'dr.dr_id', '=', 'h.dr_id')
                ->leftJoin('rsmst_klaimtypes as kt', 'kt.klaim_id', '=', 'h.klaim_id')
                ->whereRaw("NVL(h.ri_status,'I') = 'I'")
                ->select(array_merge([
                    'h.rihdr_no as ref_no', 'bg.bangsal_name', 'rm.room_name', 'h.bed_no',
                    'dr.dr_name as penerima_name', 'kt.klaim_desc',
                    DB::raw("to_char(h.entry_date,'dd/mm/yyyy hh24:mi:ss') as masuk_date"),
                ], $pasienCols));

            // Filter bangsal (hanya RI)
            if (filled($this->filterBangsal)) {
                $query->where('rm.bangsal_id', $this->filterBangsal);
            }
        }

        if ($keyword !== '') {
            $keywordUpper = '%' . mb_strtoupper($keyword) . '%';
            $query->where(function ($q) use ($keyword, $keywordUpper) {
                $q->whereRaw('UPPER(p.reg_name) LIKE ?', [$keywordUpper])->orWhereRaw('TO_CHAR(p.reg_no) LIKE ?', ['%' . $keyword . '%']);
            });
        }

        $results = $query->orderBy('p.reg_name')->limit(30)->get();

        // RI: enrich tiap baris dgn daftar DPJP (leveling dokter) — batch 1 query JSON, decode PHP.
        if ($this->source === 'RI' && $results->isNotEmpty()) {
            $rawMap = DB::table('rstxn_rihdrs')
                ->whereIn('rihdr_no', $results->pluck('ref_no')->all())
                ->get(['rihdr_no', 'datadaftarri_json'])
                ->keyBy('rihdr_no');
            foreach ($results as $r) {
                $r->leveling_dokter_list = $this->levelingListFromRaw($rawMap[$r->ref_no]->datadaftarri_json ?? null, (int) $r->ref_no);
            }
        }

        return $results;
    }

    /* Opsi bangsal (hanya bangsal dgn pasien RI aktif) — pola daftar-ri. */
    #[Computed]
    public function bangsalOptions()
    {
        return DB::table('rsview_rihdrs')
            ->select('bangsal_id', DB::raw('MAX(bangsal_name) as bangsal_name'))
            ->where(DB::raw("NVL(ri_status,'I')"), 'I')
            ->whereNotNull('bangsal_id')
            ->groupBy('bangsal_id')
            ->orderBy('bangsal_name')
            ->get();
    }

    public function selectPatient(int $refNo, array $patient): void
    {
        $this->selectedRefNo = $refNo;
        // leveling_dokter_list (DPJP) sudah dibawa dari baris (identity) — tak perlu baca ulang.
        $this->selectedPatient = $patient;

        // Default dokter pengirim = dokter kunjungan (RJ/UGD) / DPJP (RI) — tinggal ganti kalau perlu.
        $default = $this->defaultDoctorId();
        $this->drId = $default !== null ? (string) $default : null;
        unset($this->relatedDoctors);
    }

    public function changePatient(): void
    {
        $this->reset(['selectedRefNo', 'selectedPatient', 'drId', 'klinisDesc']);
        unset($this->relatedDoctors);
    }

    /* Dokter terkait pasien terpilih (untuk dropdown Dokter Pengirim).
       RJ/UGD: dokter kunjungan header. RI: DPJP ∪ visite ∪ jasa ∪ leveling dokter (aktif). */
    #[Computed]
    public function relatedDoctors()
    {
        if (!$this->selectedRefNo) {
            return collect();
        }

        if ($this->source === 'RI') {
            $drIds = collect()
                ->merge(DB::table('rstxn_rihdrs')->where('rihdr_no', $this->selectedRefNo)->pluck('dr_id'))
                ->merge(DB::table('rstxn_rivisits')->where('rihdr_no', $this->selectedRefNo)->pluck('dr_id'))
                ->merge(DB::table('rstxn_riactdocs')->where('rihdr_no', $this->selectedRefNo)->pluck('dr_id'))
                ->merge($this->levelingDokterIds($this->selectedRefNo))
                ->filter(fn($id) => filled($id))
                ->map(fn($id) => (string) $id)
                ->unique()
                ->values();

            if ($drIds->isEmpty()) {
                return collect();
            }

            return DB::table('rsmst_doctors as d')
                ->whereIn('d.dr_id', $drIds->all())
                ->where('d.active_status', '1')
                ->select('d.dr_id', 'd.dr_name')
                ->distinct()
                ->orderBy('d.dr_name')
                ->get();
        }

        // RJ / UGD — dokter kunjungan (ref = rj_no di kedua header)
        $hdrTable = $this->source === 'UGD' ? 'rstxn_ugdhdrs' : 'rstxn_rjhdrs';
        return DB::table('rsmst_doctors as d')
            ->join($hdrTable . ' as h', 'h.dr_id', '=', 'd.dr_id')
            ->where('h.rj_no', $this->selectedRefNo)
            ->select('d.dr_id', 'd.dr_name')
            ->distinct()
            ->orderBy('d.dr_name')
            ->get();
    }

    /* dr_id leveling dokter DPJP dari EMR JSON RI (pengkajian awal). CLOB via OracleLob +
       json_decode PHP — Oracle tak support JSON_VALUE. */
    private function levelingDokterIds(string $rihdrNo): array
    {
        $row = DB::table('rstxn_rihdrs')->select('datadaftarri_json')->where('rihdr_no', $rihdrNo)->first();
        if (!$row) {
            return [];
        }
        try {
            $jsonRaw = OracleLob::read($row->datadaftarri_json ?? null, 'rstxn_rihdrs', 'rihdr_no', $rihdrNo, 'datadaftarri_json');
            $data = $jsonRaw !== '' ? json_decode($jsonRaw, true) : null;
        } catch (\Throwable) {
            return [];
        }
        return collect($data['pengkajianAwalPasienRawatInap']['levelingDokter'] ?? [])->pluck('drId')->all();
    }

    /* Daftar DPJP RI (leveling dokter: drName + levelDokter) dari raw JSON CLOB — mirip kartu Daftar RI.
       Dipakai batch di activePatients (raw dari 1 query). */
    private function levelingListFromRaw($raw, int $rihdrNo): array
    {
        try {
            $jsonRaw = OracleLob::read($raw, 'rstxn_rihdrs', 'rihdr_no', $rihdrNo, 'datadaftarri_json');
            $data = $jsonRaw !== '' ? json_decode($jsonRaw, true) : null;
        } catch (\Throwable) {
            return [];
        }
        return collect($data['pengkajianAwalPasienRawatInap']['levelingDokter'] ?? [])
            ->filter(fn($ld) => filled($ld['drName'] ?? null))
            ->map(fn($ld) => ['drName' => $ld['drName'] ?? '', 'levelDokter' => $ld['levelDokter'] ?? ''])
            ->values()
            ->all();
    }

    private function defaultDoctorId(): ?string
    {
        if ($this->source === 'RI') {
            return DB::table('rstxn_rihdrs')->where('rihdr_no', $this->selectedRefNo)->value('dr_id');
        }
        $hdrTable = $this->source === 'UGD' ? 'rstxn_ugdhdrs' : 'rstxn_rjhdrs';
        return DB::table($hdrTable)->where('rj_no', $this->selectedRefNo)->value('dr_id');
    }

    /* ===============================
     | ITEM PEMERIKSAAN LAB (paginated)
     =============================== */
    #[Computed]
    public function items()
    {
        $search = trim($this->searchItem);

        return DB::table('lbmst_clabitems')
            ->select('clabitem_id', 'clabitem_desc', 'price', 'clabitem_group', 'item_code')
            ->whereNull('clabitem_group')
            ->whereNotNull('clabitem_desc')
            ->when($search, fn($q) => $q->whereRaw('UPPER(clabitem_desc) LIKE ?', ['%' . mb_strtoupper($search) . '%']))
            ->orderBy('clabitem_desc', 'asc')
            ->paginate(15);
    }

    public function toggleItem(string $id, string $desc, ?float $price, ?string $itemCode): void
    {
        if (isset($this->selectedItems[$id])) {
            unset($this->selectedItems[$id]);
        } else {
            $this->selectedItems[$id] = [
                'clabitem_id' => $id,
                'clabitem_desc' => $desc,
                'price' => $price,
                'item_code' => $itemCode,
            ];
        }
    }

    public function isSelected(string $id): bool
    {
        return isset($this->selectedItems[$id]);
    }

    public function removeSelected(string $id): void
    {
        unset($this->selectedItems[$id]);
    }

    public function updatedSearchItem(): void
    {
        $this->resetPage();
    }

    public function updatedPatientSearch(): void
    {
        unset($this->activePatients);
    }

    /* ===============================
     | INSERT ORDER LAB
     =============================== */
    public function insertLab(): void
    {
        if (!$this->selectedRefNo) {
            $this->dispatch('toast', type: 'error', message: 'Pilih pasien dulu.');
            return;
        }
        if (empty($this->selectedItems)) {
            $this->dispatch('toast', type: 'warning', message: 'Pilih minimal satu item pemeriksaan.');
            return;
        }

        $this->validateWithToast(
            [
                'drId' => 'bail|required',
                'klinisDesc' => 'bail|required|string',
            ],
            [
                'drId.required' => 'Dokter pengirim harus dipilih.',
                'klinisDesc.required' => 'Diagnosis/Keterangan Klinis harus diisi.',
            ],
        );

        $klinis = trim((string) $this->klinisDesc);

        try {
            DB::transaction(function () use ($klinis) {
                // Lock header + pastikan pasien masih aktif (belum pulang); ambil reg_no dari baris terkunci.
                $regNo = $this->lockHeaderRegNo();

                $checkupNo = DB::scalar('SELECT NVL(MAX(TO_NUMBER(checkup_no)) + 1, 1) FROM lbtxn_checkuphdrs');

                DB::table('lbtxn_checkuphdrs')->insert([
                    'checkup_no' => $checkupNo,
                    'reg_no' => $regNo,
                    'dr_id' => $this->drId,
                    'checkup_date' => DB::raw('sysdate'),
                    'status_rjri' => $this->source,
                    'checkup_status' => 'P',
                    'ref_no' => $this->selectedRefNo,
                    'klinis_desc' => $klinis,
                ]);

                foreach ($this->selectedItems as $item) {
                    $this->insertItemAndChildren($checkupNo, $item);
                }

                // Audit log terpadu (tab "Log Aktivitas") — dari sisi laborat.
                $namaItem = collect($this->selectedItems)->pluck('clabitem_desc')->implode(', ');
                $this->appendLog('Tambah Order Lab (dari laborat) - ' . $namaItem);
            });

            $count = count($this->selectedItems);
            $this->dispatch('toast', type: 'success', message: $count . ' item laboratorium berhasil ditambahkan.');
            $this->dispatch('refresh-after-lab.saved');
            $this->closeTambahModal();
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menambah: ' . $e->getMessage());
        }
    }

    // Lock baris header + verifikasi pasien masih aktif; kembalikan reg_no.
    private function lockHeaderRegNo(): string
    {
        if ($this->source === 'RI') {
            $row = DB::table('rstxn_rihdrs')->select('reg_no')->where('rihdr_no', $this->selectedRefNo)->whereRaw("NVL(ri_status,'I') = 'I'")->lockForUpdate()->first();
        } else {
            $table = $this->source === 'UGD' ? 'rstxn_ugdhdrs' : 'rstxn_rjhdrs';
            $row = DB::table($table)->select('reg_no')->where('rj_no', $this->selectedRefNo)->whereRaw("NVL(rj_status,'A') = 'A'")->lockForUpdate()->first();
        }

        if (!$row) {
            throw new \RuntimeException('Pasien #' . $this->selectedRefNo . ' tidak ditemukan / sudah tidak aktif (mungkin sudah pulang).');
        }

        return (string) $row->reg_no;
    }

    // Arahkan ke appendAdminLog{RJ,UGD,RI} sesuai sumber (dipanggil di dalam transaksi, header sudah di-lock).
    private function appendLog(string $keterangan): void
    {
        $ref = (int) $this->selectedRefNo;
        if ($this->source === 'UGD') {
            $this->appendAdminLogUGD($ref, $keterangan, 'MR');
        } elseif ($this->source === 'RI') {
            $this->appendAdminLogRI($ref, $keterangan, 'MR');
        } else {
            $this->appendAdminLogRJ($ref, $keterangan, 'MR');
        }
    }

    // Insert satu item + child items (clabitem_group) ke lbtxn_checkupdtls.
    private function insertItemAndChildren(int $checkupNo, array $item): void
    {
        $dtlNo = DB::scalar('SELECT NVL(TO_NUMBER(MAX(checkup_dtl)) + 1, 1) FROM lbtxn_checkupdtls');

        DB::table('lbtxn_checkupdtls')->insert([
            'clabitem_id' => $item['clabitem_id'],
            'checkup_no' => $checkupNo,
            'checkup_dtl' => $dtlNo,
            'lab_item_code' => $item['item_code'],
            'price' => $item['price'],
        ]);

        $children = DB::table('lbmst_clabitems')->select('clabitem_id', 'item_code', 'price')->where('clabitem_group', $item['clabitem_id'])->orderBy('item_seq', 'asc')->orderBy('clabitem_desc', 'asc')->get();

        foreach ($children as $child) {
            $childDtlNo = DB::scalar('SELECT NVL(TO_NUMBER(MAX(checkup_dtl)) + 1, 1) FROM lbtxn_checkupdtls');

            DB::table('lbtxn_checkupdtls')->insert([
                'clabitem_id' => $child->clabitem_id,
                'checkup_no' => $checkupNo,
                'checkup_dtl' => $childDtlNo,
                'lab_item_code' => $child->item_code,
                'price' => $child->price,
            ]);
        }
    }
};
?>

<div>
    <x-modal name="lab-tambah" size="full" height="full" focusable>
        <div class="flex flex-col h-full">

            {{-- HEADER --}}
            <div class="px-6 py-5 border-b bg-surface-soft border-hairline dark:bg-gray-900 dark:border-gray-700 shrink-0">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                                <img src="{{ asset('images/Logogram black solid.png') }}" alt="RSI Madinah"
                                    class="block w-6 h-6 dark:hidden" />
                                <img src="{{ asset('images/Logogram white solid.png') }}" alt="RSI Madinah"
                                    class="hidden w-6 h-6 dark:block" />
                            </div>
                            <div>
                                <h2 class="ds-display-sm dark:text-gray-100">
                                    Tambah Pemeriksaan Laboratorium
                                </h2>
                                <p class="mt-0.5 text-sm text-muted dark:text-gray-400">
                                    Pasien aktif hari ini
                                    @if ($source === 'RI')
                                        (sedang dirawat)
                                    @else
                                        (terdaftar &amp; belum selesai)
                                    @endif
                                </p>
                            </div>
                        </div>

                        {{-- Toggle sumber --}}
                        <div class="inline-flex mt-3 overflow-hidden border rounded-lg border-hairline dark:border-gray-700">
                            @foreach (['RJ' => 'Rawat Jalan', 'UGD' => 'UGD', 'RI' => 'Rawat Inap'] as $key => $label)
                                <button type="button" wire:click="setSource('{{ $key }}')"
                                    @class([
                                        'px-3 py-1.5 text-sm font-medium transition',
                                        'bg-brand-green text-white' => $source === $key,
                                        'bg-canvas text-body hover:bg-surface-soft dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800' => $source !== $key,
                                    ])>
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <x-icon-button color="gray" type="button" wire:click="closeTambahModal" class="shrink-0">
                        <span class="sr-only">Close</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex flex-col flex-1 min-h-0 overflow-y-auto">

                {{-- STEP 1: PILIH PASIEN --}}
                <div class="px-6 py-5 border-b border-hairline dark:border-gray-700">
                    <x-input-label value="1. Pasien" class="mb-1.5" />

                    @if ($selectedRefNo)
                        @php
                            $selSex = ($selectedPatient['sex'] ?? null) === 'L' ? 'Laki-Laki' : (($selectedPatient['sex'] ?? null) === 'P' ? 'Perempuan' : '-');
                        @endphp
                        <div class="flex items-start justify-between gap-3 px-4 py-3 border rounded-xl border-brand-green/30 bg-brand-green/5 dark:border-brand-lime/30 dark:bg-brand-lime/5">
                            <div class="grid flex-1 gap-4 leading-tight sm:grid-cols-2">
                                {{-- Kiri: identitas --}}
                                <div>
                                    <div class="text-base font-medium text-body dark:text-gray-300">{{ $selectedPatient['reg_no'] ?? '-' }}</div>
                                    <div class="text-lg font-semibold text-brand dark:text-white">{{ $selectedPatient['reg_name'] ?? '-' }} / ({{ $selSex }})</div>
                                    <div class="text-sm text-body dark:text-gray-400">
                                        {{ $selectedPatient['birth_date'] ?? '-' }}@if (!empty($selectedPatient['umur_format'])) <span class="text-muted">({{ $selectedPatient['umur_format'] }})</span>@endif
                                    </div>
                                    @if (!empty($selectedPatient['address']))
                                        <div class="text-sm text-muted dark:text-gray-400">{{ $selectedPatient['address'] }}</div>
                                    @endif
                                </div>
                                {{-- Kanan: detail per-sumber (RI/RJ/UGD) --}}
                                <div class="pt-2 border-t sm:pt-0 sm:border-t-0 sm:border-l border-brand-green/20 dark:border-brand-lime/20 sm:pl-4">
                                    @include('pages.transaksi.penunjang._patient-detail', ['p' => $selectedPatient, 'source' => $source])
                                </div>
                            </div>
                            <x-secondary-button type="button" wire:click="changePatient" class="px-3 py-1 text-xs shrink-0">
                                Ganti
                            </x-secondary-button>
                        </div>
                    @else
                        {{-- Search + Filter bangsal (kanan-kiri) --}}
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <div class="relative flex-1">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <svg class="w-4 h-4 text-body" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                                <x-text-input wire:model.live.debounce.300ms="patientSearch" class="block w-full pl-10"
                                    placeholder="Cari No RM / Nama pasien aktif..." />
                            </div>
                            @if ($source === 'RI')
                                <x-select-input wire:model.live="filterBangsal" class="text-sm sm:w-56">
                                    <option value="">Semua Bangsal</option>
                                    @foreach ($this->bangsalOptions as $bangsal)
                                        <option value="{{ $bangsal->bangsal_id }}">{{ $bangsal->bangsal_name }}</option>
                                    @endforeach
                                </x-select-input>
                            @endif
                        </div>

                        {{-- Tabel pasien — gaya Daftar RI (kolom Identitas | Kamar & Dokter) --}}
                        <div class="mt-2 overflow-y-auto border border-hairline rounded-xl dark:border-gray-700 max-h-72">
                            <table class="w-full text-sm">
                                <tbody class="divide-y divide-hairline-soft dark:divide-gray-800">
                                    @forelse ($this->activePatients as $pasien)
                                        @php
                                            $sexLabel = $pasien->sex === 'L' ? 'Laki-Laki' : ($pasien->sex === 'P' ? 'Perempuan' : '-');
                                            $identity = [
                                                'reg_no' => $pasien->reg_no,
                                                'reg_name' => $pasien->reg_name,
                                                'sex' => $pasien->sex,
                                                'birth_date' => $pasien->birth_date,
                                                'umur_format' => $pasien->umur_format,
                                                'address' => $pasien->address,
                                                'bangsal_name' => $pasien->bangsal_name ?? null,
                                                'room_name' => $pasien->room_name ?? null,
                                                'bed_no' => $pasien->bed_no ?? null,
                                                'penerima_name' => $pasien->penerima_name ?? null,
                                                'leveling_dokter_list' => $pasien->leveling_dokter_list ?? [],
                                                'poli_desc' => $pasien->poli_desc ?? null,
                                                'dokter_name' => $pasien->dokter_name ?? null,
                                                'entry_desc' => $pasien->entry_desc ?? null,
                                                'no_antrian' => $pasien->no_antrian ?? null,
                                                'klaim_desc' => $pasien->klaim_desc ?? null,
                                                'masuk_date' => $pasien->masuk_date ?? null,
                                            ];
                                        @endphp
                                        <tr wire:key="ap-{{ $source }}-{{ $pasien->ref_no }}"
                                            wire:click="selectPatient({{ $pasien->ref_no }}, @js($identity))"
                                            class="transition cursor-pointer hover:bg-surface-soft dark:hover:bg-gray-800/60">
                                            {{-- Identitas --}}
                                            <td class="px-4 py-3 leading-tight align-top">
                                                <div class="text-base font-medium text-body dark:text-gray-300">{{ $pasien->reg_no }}</div>
                                                <div class="text-lg font-semibold text-brand dark:text-white">{{ $pasien->reg_name }} / ({{ $sexLabel }})</div>
                                                <div class="text-sm text-body dark:text-gray-400">
                                                    {{ $pasien->birth_date ?? '-' }}@if (!empty($pasien->umur_format)) <span class="text-muted">({{ $pasien->umur_format }})</span>@endif
                                                </div>
                                                @if (!empty($pasien->address))
                                                    <div class="text-sm text-muted dark:text-gray-400">{{ $pasien->address }}</div>
                                                @endif
                                            </td>
                                            {{-- Detail per-sumber (RI: kamar/DPJP; RJ: poli/dokter; UGD: dokter/cara masuk) --}}
                                            <td class="px-4 py-3 leading-tight align-top whitespace-nowrap">
                                                @include('pages.transaksi.penunjang._patient-detail', ['p' => $identity, 'source' => $source])
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="2" class="px-4 py-6 text-sm text-center text-muted-soft dark:text-gray-600">
                                                Tidak ada pasien aktif @if (trim($patientSearch) !== '') cocok dengan "{{ $patientSearch }}" @endif.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                {{-- STEP 2: PILIH PEMERIKSAAN + DIAGNOSIS (aktif setelah pasien dipilih) --}}
                <div class="flex flex-col flex-1 min-h-0 lg:flex-row" @class(['opacity-50 pointer-events-none' => !$selectedRefNo])>

                    {{-- KIRI: Search + Item Grid --}}
                    <div class="flex flex-col flex-1 min-h-0">
                        <div class="px-6 py-3 border-b border-hairline-soft dark:border-gray-700">
                            <x-input-label value="2. Pemeriksaan" class="mb-1.5" />
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <svg class="w-4 h-4 text-muted-soft" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                                <x-text-input wire:model.live.debounce.300ms="searchItem" class="block w-full pl-10"
                                    placeholder="Cari item pemeriksaan..." />
                            </div>
                        </div>

                        <div class="flex-1 p-5 overflow-y-auto bg-surface-soft/70 dark:bg-gray-950/20">
                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-4">
                                @forelse ($this->items as $item)
                                    @php $selected = $this->isSelected($item->clabitem_id); @endphp
                                    <button type="button"
                                        wire:click="toggleItem('{{ $item->clabitem_id }}', '{{ addslashes($item->clabitem_desc) }}', {{ $item->price ?? 'null' }}, '{{ $item->item_code }}')"
                                        class="relative flex flex-col items-center justify-center p-3 rounded-xl border-2 text-center transition-all
                                            {{ $selected
                                                ? 'border-brand-green bg-brand-green/10 text-brand-green shadow-sm'
                                                : 'border-hairline bg-canvas hover:border-brand-green/40 hover:bg-brand-green/5 text-body dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300' }}">
                                        @if ($selected)
                                            <span class="absolute top-1.5 right-1.5 flex items-center justify-center w-4 h-4 bg-brand-green rounded-full">
                                                <svg class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                                </svg>
                                            </span>
                                        @endif
                                        <p class="text-sm font-medium leading-tight">{{ $item->clabitem_desc }}</p>
                                        @if ($item->price)
                                            <p class="mt-1 text-[10px] {{ $selected ? 'text-brand-green/70' : 'text-muted-soft' }}">
                                                {{ number_format($item->price) }}
                                            </p>
                                        @endif
                                    </button>
                                @empty
                                    <div class="py-12 text-center text-muted-soft col-span-full">
                                        <p class="text-base">Tidak ada item ditemukan</p>
                                    </div>
                                @endforelse
                            </div>

                            @if ($this->items->hasPages())
                                <div class="mt-4">
                                    {{ $this->items->links() }}
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- KANAN: Dokter + Diagnosis + Keranjang --}}
                    <div class="flex flex-col w-full min-h-0 border-t lg:w-96 shrink-0 lg:border-t-0 lg:border-l border-hairline dark:border-gray-700 bg-canvas dark:bg-gray-900">

                        {{-- Dokter Pengirim --}}
                        <div class="px-5 py-3 border-b border-hairline-soft dark:border-gray-700">
                            <x-input-label value="Dokter Pengirim" required class="text-xs" />
                            <x-select-input wire:model="drId" class="mt-1 text-sm">
                                <option value="">— Pilih dokter pengirim —</option>
                                @foreach ($this->relatedDoctors as $dr)
                                    <option value="{{ $dr->dr_id }}">{{ $dr->dr_name }}</option>
                                @endforeach
                            </x-select-input>
                            <x-input-error :messages="$errors->get('drId')" class="mt-1" />
                        </div>

                        {{-- Diagnosis / Keterangan Klinis --}}
                        <div class="px-5 py-3 border-b border-hairline-soft dark:border-gray-700">
                            <x-input-label value="Diagnosis/Keterangan Klinis" required class="text-xs" />
                            <x-textarea wire:model="klinisDesc" rows="2"
                                placeholder="Diagnosis kerja / keterangan klinis pasien..."
                                :error="$errors->has('klinisDesc')" class="mt-1 text-sm" />
                            <x-input-error :messages="$errors->get('klinisDesc')" class="mt-1" />
                        </div>

                        {{-- Header keranjang --}}
                        <div class="flex items-center justify-between px-5 pt-3 pb-1.5">
                            <p class="text-sm font-semibold text-ink dark:text-gray-100">Item Dipilih</p>
                            @if (!empty($selectedItems))
                                <span class="px-2 py-0.5 text-xs font-semibold text-brand-green bg-brand-green/10 border border-brand-green/30 rounded-full">
                                    {{ count($selectedItems) }}
                                </span>
                            @endif
                        </div>

                        {{-- List item dipilih --}}
                        <div class="flex-1 px-5 pb-4 space-y-1.5 overflow-y-auto">
                            @forelse ($selectedItems as $id => $sel)
                                <div class="flex items-start justify-between gap-2 p-2.5 border rounded-lg border-brand-green/20 bg-brand-green/5">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium leading-tight text-brand-green">{{ $sel['clabitem_desc'] }}</p>
                                        @if ($sel['price'])
                                            <p class="mt-0.5 text-[11px] text-brand-green/60">{{ number_format($sel['price']) }}</p>
                                        @endif
                                    </div>
                                    <button type="button" wire:click="removeSelected('{{ $id }}')"
                                        class="mt-0.5 shrink-0 text-muted-soft hover:text-red-500 transition-colors">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </div>
                            @empty
                                <div class="flex flex-col items-center justify-center h-full py-10 text-center text-muted-soft">
                                    <p class="text-sm font-medium">Belum ada item dipilih</p>
                                    <p class="mt-0.5 text-xs text-muted-soft">Klik item di kiri untuk memilih</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 flex items-center justify-end gap-2 px-6 py-3 border-t border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-700 shrink-0">
                <x-secondary-button type="button" wire:click="closeTambahModal">Tutup</x-secondary-button>
                <x-primary-button type="button" wire:click="insertLab" wire:loading.attr="disabled"
                    wire:target="insertLab" :disabled="!$selectedRefNo || empty($selectedItems)">
                    <span wire:loading.remove wire:target="insertLab" class="inline-flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Tambah Order
                    </span>
                    <span wire:loading wire:target="insertLab"><x-loading class="w-4 h-4" /></span>
                </x-primary-button>
            </div>
        </div>
    </x-modal>
</div>
