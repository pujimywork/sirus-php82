<?php
// resources/views/pages/transaksi/ugd/administrasi-ugd/transfer-ri-ugd-actions.blade.php
//
// Komponen TERSENDIRI untuk memproses "Transfer ke RI" (rawat inap) dari kasir UGD.
// Dibuka via x-modal (pola sama seperti buka EMR UGD): tombol di kasir-ugd men-dispatch
// 'open-transfer-ri-ugd' → komponen ini membuka modal 'transfer-ri-ugd'.

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use EmrUGDTrait, WithRenderVersioningTrait;

    public ?int $rjNo = null;
    public ?string $regName = null;

    // ── Pilihan ruangan & bed ──
    public ?string $transferRoomId = null;
    public ?string $transferRoomName = null;
    public ?string $transferBedNo = null;
    public array $availableBeds = [];
    public bool $forceOccupiedBed = false;

    // ── Cara Masuk RI (master rsmst_entrytypes) ──
    public string $transferEntryId = '7'; // default MELALUI IGD
    public array $transferEntryOptions = [];

    // ── Jenis Klaim RI (wajib dipilih saat transfer; default = klaim UGD) ──
    public string $transferKlaimId = 'UM';
    public array $klaimOptions = [['klaimId' => 'UM', 'klaimDesc' => 'UMUM'], ['klaimId' => 'JM', 'klaimDesc' => 'BPJS'], ['klaimId' => 'JR', 'klaimDesc' => 'JASA RAHARJA'], ['klaimId' => 'JML', 'klaimDesc' => 'Asuransi Lain'], ['klaimId' => 'KR', 'klaimDesc' => 'Kronis']];

    // ── Rincian biaya UGD yang akan dipindahkan ke RI ──
    public array $biayaUGD = [];
    public int $transferRJ = 0;      // biaya RJ yg ditransfer ke UGD (rstxn_ugdtempadmins) — pos "Transfer"
    public int $totalBiayaUGD = 0;   // total lengkap = biaya UGD sendiri + Transfer (sama dgn sumTotalRJ kasir)

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-transfer-ri-ugd'];

    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);
    }

    /* ===============================
     | OPEN — dipicu dari kasir UGD
     =============================== */
    #[On('open-transfer-ri-ugd')]
    public function openTransfer(int $rjNo): void
    {
        if (empty($rjNo)) {
            return;
        }

        $this->rjNo = $rjNo;
        $this->resetTransferState();
        $this->resetValidation();

        // Guard: UGD masih aktif
        if ($this->checkUGDStatus($rjNo)) {
            $this->dispatch('toast', type: 'error', message: 'UGD sudah diproses, tidak bisa ditransfer.');
            return;
        }

        // Guard: belum pernah ditransfer
        if (DB::table('rstxn_ribiayaselamadugds')->where('rj_no', $rjNo)->exists()) {
            $this->dispatch('toast', type: 'error', message: 'Transfer ke RI sudah pernah dilakukan untuk UGD ini.');
            return;
        }

        // Nama pasien + klaim UGD (dipakai sbg default pilihan klaim RI)
        $hdr = DB::table('rstxn_ugdhdrs as h')
            ->leftJoin('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->where('h.rj_no', $rjNo)
            ->select('p.reg_name', 'h.klaim_id')
            ->first();
        $this->regName = $hdr->reg_name ?? null;
        $this->transferKlaimId = $hdr->klaim_id ?: 'UM';

        // Rincian biaya UGD (yang akan dipindahkan ke RI)
        $this->biayaUGD = $this->calculateUGDCosts($rjNo);

        // Pos "Transfer" = biaya RJ yang sebelumnya ditransfer ke UGD (rstxn_ugdtempadmins) —
        // ikut dipindahkan ke RI (cascade di transferKeRI). Konsisten dgn sumtrfRJ/sumTotalRJ kasir.
        $this->transferRJ = (int) DB::table('rstxn_ugdtempadmins')
            ->where('rj_no', $rjNo)
            ->selectRaw('nvl(sum(rj_admin + poli_price + acte_price + actp_price + actd_price + obat + lab + rad + other + rs_admin), 0) as total')
            ->value('total');

        $this->totalBiayaUGD = array_sum($this->biayaUGD) + $this->transferRJ;

        // Opsi Cara Masuk RI (master rsmst_entrytypes — sama dengan form Daftar RI)
        $this->transferEntryOptions = DB::table('rsmst_entrytypes')
            ->select('entry_id', 'entry_desc')
            ->orderBy('entry_id')
            ->get()
            ->map(fn($e) => ['entryId' => (string) $e->entry_id, 'entryDesc' => $e->entry_desc])
            ->toArray();

        $this->incrementVersion('modal-transfer-ri-ugd');
        $this->dispatch('open-modal', name: 'transfer-ri-ugd');
    }

    private function resetTransferState(): void
    {
        $this->transferRoomId = null;
        $this->transferRoomName = null;
        $this->transferBedNo = null;
        $this->availableBeds = [];
        $this->forceOccupiedBed = false;
        $this->transferEntryId = '7';
    }

    /* ===============================
     | LOV ROOM
     =============================== */
    #[On('lov.selected.room-transfer-ri')]
    public function onRoomTransferRI(string $target, ?array $payload): void
    {
        if ($payload) {
            $this->transferRoomId = $payload['room_id'] ?? null;
            $this->transferRoomName = $payload['room_name'] ?? null;
            $this->transferBedNo = $payload['bed_no'] ?? null;
            $this->loadBedsForRoom($this->transferRoomId);
        } else {
            $this->transferRoomId = null;
            $this->transferRoomName = null;
            $this->transferBedNo = null;
            $this->availableBeds = [];
        }
    }

    private function loadBedsForRoom(?string $roomId): void
    {
        if (!$roomId) {
            $this->availableBeds = [];
            return;
        }

        $rows = DB::table('rsmst_beds as b')
            ->leftJoin('rsmst_trfrooms as t', function ($j) {
                $j->on('t.room_id', '=', 'b.room_id')
                  ->on('t.bed_no', '=', 'b.bed_no')
                  ->whereNull('t.end_date');
            })
            ->select('b.bed_no', 'b.bed_desc', 't.rihdr_no as occupied_by')
            ->where('b.room_id', $roomId)
            ->orderBy('b.bed_no')
            ->get();

        $this->availableBeds = $rows->map(fn($r) => [
            'bed_no'      => $r->bed_no,
            'bed_desc'    => $r->bed_desc,
            'is_occupied' => !is_null($r->occupied_by),
            'occupied_by' => $r->occupied_by,
        ])->toArray();
    }

    public function selectTransferBed(string $bedNo): void
    {
        $this->transferBedNo = $bedNo;
    }

    public function isTransferBedOccupied(): bool
    {
        if (!$this->transferBedNo) {
            return false;
        }
        foreach ($this->availableBeds as $b) {
            if ($b['bed_no'] === $this->transferBedNo) {
                return $b['is_occupied'];
            }
        }
        return false;
    }

    public function getTransferBedOccupant(): ?int
    {
        if (!$this->transferBedNo) {
            return null;
        }
        foreach ($this->availableBeds as $b) {
            if ($b['bed_no'] === $this->transferBedNo && $b['is_occupied']) {
                return $b['occupied_by'] ?? null;
            }
        }
        return null;
    }

    /* ===============================
     | PROSES TRANSFER KE RI
     =============================== */
    public function transferKeRI(): void
    {
        if (!$this->rjNo) {
            $this->dispatch('toast', type: 'error', message: 'Data transaksi tidak ditemukan.');
            return;
        }

        // Cek UGD masih aktif
        if ($this->checkUGDStatus($this->rjNo)) {
            $this->dispatch('toast', type: 'error', message: 'UGD sudah diproses, tidak bisa ditransfer.');
            return;
        }

        // Cek lab pending
        if ($this->checkLabPendingUGD($this->rjNo)) {
            $this->dispatch('toast', type: 'error', message: 'Hasil Laborat belum selesai, transfer tidak bisa dilakukan.');
            return;
        }

        // Cek sudah pernah transfer
        if (DB::table('rstxn_ribiayaselamadugds')->where('rj_no', $this->rjNo)->exists()) {
            $this->dispatch('toast', type: 'error', message: 'Transfer ke RI sudah pernah dilakukan untuk UGD ini.');
            return;
        }

        // Cek room & bed dipilih
        if (empty($this->transferRoomId) || empty($this->transferBedNo)) {
            $this->dispatch('toast', type: 'error', message: 'Pilih ruangan dan bed terlebih dahulu.');
            return;
        }

        try {
            DB::transaction(function () {
                // Lock UGD row
                $this->lockUGDRow($this->rjNo);

                // Re-check
                if ($this->checkUGDStatus($this->rjNo)) {
                    throw new \RuntimeException('Data sudah diproses oleh user lain.');
                }

                $ugdHdr = DB::table('rstxn_ugdhdrs')->where('rj_no', $this->rjNo)->first();
                if (!$ugdHdr) {
                    throw new \RuntimeException('Data UGD tidak ditemukan.');
                }

                // Cek lockstatus pasien
                $pasien = DB::table('rsmst_pasiens')
                    ->where('reg_no', $ugdHdr->reg_no)
                    ->lockForUpdate()
                    ->first();

                if ($pasien->lockstatus && !in_array($pasien->lockstatus, ['UGD', null])) {
                    throw new \RuntimeException("Pasien sedang dalam status {$pasien->lockstatus}, tidak bisa transfer.");
                }

                // Hitung biaya UGD
                $costs = $this->calculateUGDCosts($this->rjNo);
                $totalBiayaUGD = array_sum($costs);

                // Generate RI rihdr_no
                $riHdrNo = (int) DB::table('rstxn_rihdrs')->max('rihdr_no') + 1;

                // Ambil shift saat ini
                $now = Carbon::now();
                $findShift = DB::table('rstxn_shiftctls')
                    ->select('shift')
                    ->whereNotNull('shift_start')->whereNotNull('shift_end')
                    ->whereRaw('? BETWEEN shift_start AND shift_end', [$now->format('H:i:s')])
                    ->first();

                // Insert RI header
                DB::table('rstxn_rihdrs')->insert([
                    'rihdr_no'    => $riHdrNo,
                    'reg_no'      => $ugdHdr->reg_no,
                    'entry_date'  => DB::raw('SYSDATE'),
                    'entry_id'    => $this->transferEntryId ?: '7', // Cara Masuk RI (default MELALUI IGD)
                    'dr_id'       => $ugdHdr->dr_id,
                    'room_id'     => $this->transferRoomId,
                    'bed_no'      => $this->transferBedNo,
                    'klaim_id'    => $this->transferKlaimId ?: 'UM', // klaim dipilih di modal (default = klaim UGD)
                    'shift'       => (string) ($findShift?->shift ?? 1),
                    'ri_status'   => 'I',
                    'erm_status'  => 'A',
                    'ri_total'    => 0,
                    'ri_diskon'   => 0,
                    'ri_bayar'    => 0,
                    'ri_titip'    => 0,
                    'admin_status' => '0',
                    'admin_age'   => 0,
                    'police_case' => '0',
                    'trf_gudang_status' => '0',
                    'push_antrian_bpjs_status' => '0',
                ]);

                // Insert rsmst_trfrooms
                $room = DB::table('rsmst_rooms')
                    ->where('room_id', $this->transferRoomId)
                    ->select('room_price', 'perawatan_price', 'common_service')
                    ->first();

                $maxTrfr = (int) DB::table('rsmst_trfrooms')->max('trfr_no') + 1;

                DB::table('rsmst_trfrooms')->insert([
                    'trfr_no'         => $maxTrfr,
                    'rihdr_no'        => $riHdrNo,
                    'room_id'         => $this->transferRoomId,
                    'start_date'      => DB::raw('SYSDATE'),
                    'bed_no'          => $this->transferBedNo,
                    'room_price'      => $room->room_price ?? 0,
                    'perawatan_price' => $room->perawatan_price ?? 0,
                    'common_service'  => $room->common_service ?? 0,
                ]);

                // Insert biaya UGD sendiri ke rstxn_ritempadmins
                $tempadmNo = (int) DB::table('rstxn_ritempadmins')->max('tempadm_no') + 1;

                DB::table('rstxn_ritempadmins')->insert([
                    'tempadm_no'   => $tempadmNo,
                    'tempadm_date' => DB::raw('SYSDATE'),
                    'tempadm_flag' => 'UGD',
                    'tempadm_ref'  => $this->rjNo,
                    'rihdr_no'     => $riHdrNo,
                    'rj_admin'     => $costs['rjAdmin'],
                    'poli_price'   => $costs['poliPrice'],
                    'acte_price'   => $costs['actePrice'],
                    'actp_price'   => $costs['actpPrice'],
                    'actd_price'   => $costs['actdPrice'],
                    'obat'         => $costs['obat'],
                    'lab'          => $costs['lab'],
                    'rad'          => $costs['rad'],
                    'other'        => $costs['other'],
                    'rs_admin'     => $costs['rsAdmin'],
                ]);

                // Copy biaya RJ dari rstxn_ugdtempadmins ke rstxn_ritempadmins (cascade)
                $ugdTemps = DB::table('rstxn_ugdtempadmins')
                    ->where('rj_no', $this->rjNo)
                    ->get();

                foreach ($ugdTemps as $temp) {
                    $tempadmNo++;
                    DB::table('rstxn_ritempadmins')->insert([
                        'tempadm_no'   => $tempadmNo,
                        'tempadm_date' => $temp->tempadm_date,
                        'tempadm_flag' => $temp->tempadm_flag,
                        'tempadm_ref'  => $temp->tempadm_ref,
                        'rihdr_no'     => $riHdrNo,
                        'rj_admin'     => $temp->rj_admin,
                        'poli_price'   => $temp->poli_price,
                        'acte_price'   => $temp->acte_price,
                        'actp_price'   => $temp->actp_price,
                        'actd_price'   => $temp->actd_price,
                        'obat'         => $temp->obat,
                        'lab'          => $temp->lab,
                        'rad'          => $temp->rad,
                        'other'        => $temp->other,
                        'rs_admin'     => $temp->rs_admin,
                    ]);
                }

                // Hapus rstxn_ugdtempadmins (sudah di-copy ke RI)
                DB::table('rstxn_ugdtempadmins')->where('rj_no', $this->rjNo)->delete();

                // Insert link table
                DB::table('rstxn_ribiayaselamadugds')->insert([
                    'rj_no'              => $this->rjNo,
                    'ugd_no_rsri'        => $riHdrNo,
                    'tanggal_ugd'        => $ugdHdr->rj_date,
                    'total_biayaugd'     => $totalBiayaUGD,
                    'keterangan_biayaugd' => 'UNIT GAWAT DARURAT',
                ]);

                // Update UGD status → 'I'
                DB::table('rstxn_ugdhdrs')
                    ->where('rj_no', $this->rjNo)
                    ->update([
                        'rj_status'  => 'I',
                        'txn_status' => 'I',
                    ]);

                // Update lockstatus pasien → RI
                DB::table('rsmst_pasiens')
                    ->where('reg_no', $ugdHdr->reg_no)
                    ->update(['lockstatus' => 'RI']);

                $this->appendAdminLogUGD($this->rjNo, 'Transfer ke RI #' . $riHdrNo . ' (total biaya UGD Rp ' . number_format($totalBiayaUGD, 0, ',', '.') . ')');
            });

            // Tutup modal + refresh: kasir (lock), sibling admin, & list pelayanan
            $this->dispatch('close-modal', name: 'transfer-ri-ugd');
            $this->dispatch('ugd-transferred-to-ri', rjNo: $this->rjNo);
            $this->dispatch('administrasi-ugd.updated');
            $this->dispatch('refresh-after-ugd.saved');   // list pelayanan UGD
            $this->dispatch('refresh-after-kasir.saved'); // list antrian kasir UGD
            $this->dispatch('toast', type: 'success', message: 'Transfer biaya UGD ke RI berhasil.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal transfer ke RI: ' . $e->getMessage());
        }
    }
};
?>

<div>
    <x-modal name="transfer-ri-ugd" size="full" height="full" focusable>
        <div class="flex flex-col h-full" wire:key="{{ $this->renderKey('modal-transfer-ri-ugd', [$rjNo ?? 'new']) }}">

            {{-- ═══════════ HEADER — identitas pasien (gaya EMR UGD) ═══════════ --}}
            <div class="relative px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>
                <div class="relative flex items-start justify-between gap-4">
                    <div class="flex-1 min-w-0">
                        <p class="mb-1 text-xs font-semibold tracking-wide uppercase text-teal-600 dark:text-teal-400">
                            Transfer ke Rawat Inap
                        </p>
                        @if ($rjNo)
                            <livewire:pages::transaksi.ugd.display-pasien-ugd.display-pasien-ugd :rjNo="$rjNo"
                                wire:key="transfer-ri-display-pasien-{{ $rjNo }}" />
                        @endif
                    </div>
                    <x-icon-button color="gray" type="button" x-on:click="$dispatch('close-modal', { name: 'transfer-ri-ugd' })"
                        class="shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- ═══════════ BODY (scroll) ═══════════ --}}
            <div class="flex-1 px-4 py-5 overflow-y-auto bg-surface-soft/70 dark:bg-gray-950/20">
                <div class="grid w-full grid-cols-1 gap-5 lg:grid-cols-5 lg:items-start">

                    {{-- ── TOTAL ADMINISTRASI (rincian biaya UGD yang dipindahkan) ── --}}
                    @php
                        // Label & urutan mengikuti rincian Administrasi/Kasir UGD (sumTotalRJ)
                        $biayaRows = [
                            'RS Admin'      => $biayaUGD['rsAdmin'] ?? 0,
                            'Admin OB'      => $biayaUGD['rjAdmin'] ?? 0,
                            'Uang Periksa'  => $biayaUGD['poliPrice'] ?? 0,
                            'Jasa Karyawan' => $biayaUGD['actePrice'] ?? 0,
                            'Jasa Dokter'   => $biayaUGD['actdPrice'] ?? 0,
                            'Jasa Medis'    => $biayaUGD['actpPrice'] ?? 0,
                            'Obat'          => $biayaUGD['obat'] ?? 0,
                            'Laboratorium'  => $biayaUGD['lab'] ?? 0,
                            'Radiologi'     => $biayaUGD['rad'] ?? 0,
                            'Lain-Lain'     => $biayaUGD['other'] ?? 0,
                            'Transfer'      => $transferRJ,
                        ];
                    @endphp
                    <div class="lg:col-span-3 overflow-hidden bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                        <div class="flex flex-wrap items-center gap-3 px-5 py-4 bg-gradient-to-r from-teal-600 to-teal-500 dark:from-teal-700 dark:to-teal-600">
                            <div>
                                <p class="text-xs font-medium tracking-wide uppercase text-white/80">Total Administrasi UGD</p>
                                <p class="text-2xl font-bold text-white">Rp {{ number_format($totalBiayaUGD) }}</p>
                            </div>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-semibold text-white rounded-full bg-white/20">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                </svg>
                                Dipindahkan ke Rawat Inap
                            </span>
                        </div>
                        <div class="grid grid-cols-2 gap-px sm:grid-cols-3 lg:grid-cols-5 bg-hairline dark:bg-gray-700">
                            @foreach ($biayaRows as $label => $nominal)
                                <div class="px-3 py-2.5 bg-canvas dark:bg-gray-900">
                                    <p class="text-[11px] leading-tight text-muted dark:text-gray-400">{{ $label }}</p>
                                    <p class="text-sm font-semibold text-body dark:text-gray-200">Rp {{ number_format($nominal) }}</p>
                                </div>
                            @endforeach
                        </div>
                        <p class="px-5 py-2 text-[11px] text-muted-soft border-t border-hairline dark:border-gray-700">
                            Termasuk biaya Rawat Jalan bila pasien berasal dari transfer RJ. Seluruh biaya di atas akan menjadi tagihan Rawat Inap.
                        </p>
                    </div>

                    {{-- ── FORM RUANGAN & BED ── --}}
                    <div class="lg:col-span-2 p-5 space-y-4 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                        <h3 class="text-sm font-semibold text-ink dark:text-gray-200">Tujuan Rawat Inap</h3>

                        {{-- 1) Cara Masuk RI (atas sendiri) --}}
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-muted dark:text-gray-400">Cara Masuk</label>
                            <x-select-input wire:model="transferEntryId" class="w-full text-sm">
                                @foreach ($transferEntryOptions as $opt)
                                    <option value="{{ $opt['entryId'] }}">{{ $opt['entryDesc'] }}</option>
                                @endforeach
                            </x-select-input>
                        </div>

                        {{-- 2) Jenis Klaim RI (grid 3) --}}
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-muted dark:text-gray-400">Jenis Klaim</label>
                            {{-- Kartu-tombol: seluruh body clickable via wire:click $set; state murni dari $transferKlaimId
                                 (solusi lokal — TIDAK mengubah komponen x-radio-button bersama) --}}
                            <div class="grid grid-cols-3 gap-2">
                                @foreach ($klaimOptions as $klaim)
                                    @php $selKlaim = $transferKlaimId === (string) $klaim['klaimId']; @endphp
                                    <button type="button"
                                        wire:click="$set('transferKlaimId', '{{ $klaim['klaimId'] }}')"
                                        class="flex items-center gap-2 p-3 text-left border rounded-lg transition
                                            {{ $selKlaim
                                                ? 'border-teal-500 bg-teal-50 dark:bg-teal-900/20 ring-1 ring-teal-500'
                                                : 'border-hairline bg-canvas hover:bg-surface-soft dark:border-gray-700 dark:bg-gray-900 dark:hover:bg-gray-800' }}">
                                        <span class="flex items-center justify-center w-4 h-4 border-2 rounded-full shrink-0
                                            {{ $selKlaim ? 'border-teal-600' : 'border-gray-400 dark:border-gray-500' }}">
                                            @if ($selKlaim)
                                                <span class="w-2 h-2 rounded-full bg-teal-600"></span>
                                            @endif
                                        </span>
                                        <span class="text-sm {{ $selKlaim ? 'font-semibold text-teal-800 dark:text-teal-200' : 'text-body dark:text-gray-300' }}">
                                            {{ $klaim['klaimDesc'] }}
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- 3) Cari Ruangan (bawah sendiri) --}}
                        <div class="w-full">
                            <label class="mb-1 block text-xs font-semibold text-muted dark:text-gray-400">Ruangan</label>
                            <livewire:lov.room.lov-room target="room-transfer-ri"
                                wire:key="lov-room-transfer-ri-{{ $rjNo }}-{{ $renderVersions['modal-transfer-ri-ugd'] ?? 0 }}" />
                        </div>

                        {{-- Pilih Bed --}}
                        @if ($transferRoomId && !empty($availableBeds))
                            <div>
                                <p class="mb-1.5 text-xs font-semibold text-muted dark:text-gray-400">Pilih Bed Tersedia</p>
                                <div class="flex flex-wrap items-center gap-2">
                                    @foreach ($availableBeds as $bed)
                                        @php
                                            $isOcc = $bed['is_occupied'];
                                            $isSel = $transferBedNo === $bed['bed_no'];
                                            $clickable = !$isOcc || $forceOccupiedBed;
                                        @endphp
                                        <button wire:key="bed-{{ $bed['bed_no'] }}" type="button"
                                            @if ($clickable) wire:click="selectTransferBed('{{ $bed['bed_no'] }}')" @endif
                                            @disabled(!$clickable)
                                            title="{{ $bed['bed_desc'] ?? '' }}{{ $isOcc ? ' — terpakai oleh RI #' . $bed['occupied_by'] : '' }}"
                                            class="px-3 py-1.5 rounded-lg text-xs font-mono font-semibold border transition
                                                {{ $isSel
                                                    ? ($isOcc
                                                        ? 'bg-amber-500 text-white border-amber-500 ring-2 ring-amber-300 dark:ring-amber-700'
                                                        : 'bg-blue-600 text-white border-blue-600 ring-2 ring-blue-300 dark:ring-blue-700')
                                                    : ($isOcc
                                                        ? ($forceOccupiedBed
                                                            ? 'bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-300 border-amber-300 dark:border-amber-700 hover:border-amber-500'
                                                            : 'bg-surface-soft dark:bg-gray-800 text-muted-soft dark:text-gray-600 border-hairline dark:border-gray-700 cursor-not-allowed line-through')
                                                        : 'bg-canvas dark:bg-gray-900 text-body dark:text-gray-300 border-gray-300 dark:border-gray-700 hover:border-blue-500 hover:text-blue-600') }}">
                                            Bed {{ $bed['bed_no'] }}
                                            @if ($isOcc)
                                                <span class="ml-1 text-[10px]">· RI #{{ $bed['occupied_by'] }}</span>
                                            @endif
                                        </button>
                                    @endforeach
                                    <x-toggle wire:model.live="forceOccupiedBed" :trueValue="true" :falseValue="false"
                                        label="Paksa pilih bed terpakai" class="ml-2" />
                                </div>
                                @if ($this->isTransferBedOccupied())
                                    <div class="mt-2 flex items-start gap-1.5 px-2.5 py-1.5 text-[11px] text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-900/20 border border-amber-300 dark:border-amber-700 rounded-lg">
                                        <svg class="w-3.5 h-3.5 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span>Bed dipilih masih ditempati RI #{{ $this->getTransferBedOccupant() }}. Pastikan koordinasi sebelum konfirmasi transfer.</span>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- ═══════════ FOOTER (sticky) ═══════════ --}}
            <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-4 border-t border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-700">
                <div class="text-sm text-muted dark:text-gray-400">
                    @if ($transferRoomId && $transferBedNo)
                        Tujuan: <strong class="text-body dark:text-gray-200">{{ $transferRoomName }}</strong>
                        · Bed <strong class="text-body dark:text-gray-200">{{ $transferBedNo }}</strong>
                        · Total <strong class="text-teal-700 dark:text-teal-300">Rp {{ number_format($totalBiayaUGD) }}</strong>
                    @else
                        <span class="text-muted-soft">Pilih ruangan &amp; bed untuk melanjutkan.</span>
                    @endif
                </div>
                <div class="flex items-center gap-3">
                    <x-secondary-button type="button" x-on:click="$dispatch('close-modal', { name: 'transfer-ri-ugd' })">
                        Batal
                    </x-secondary-button>
                    @if ($transferRoomId && $transferBedNo)
                        <x-confirm-button variant="warning" :action="'transferKeRI()'" title="Transfer ke RI"
                            message="Yakin ingin mentransfer biaya UGD (Rp {{ number_format($totalBiayaUGD) }}) ke Rawat Inap? Pasien akan masuk ruangan {{ $transferRoomName }} bed {{ $transferBedNo }}."
                            confirmText="Ya, transfer" cancelText="Batal">
                            Konfirmasi Transfer ke RI
                        </x-confirm-button>
                    @endif
                </div>
            </div>
        </div>
    </x-modal>
</div>
