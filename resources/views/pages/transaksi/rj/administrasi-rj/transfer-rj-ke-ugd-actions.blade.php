<?php
// resources/views/pages/transaksi/rj/administrasi-rj/transfer-rj-ke-ugd-actions.blade.php
//
// Komponen TERSENDIRI untuk memproses "Transfer ke UGD" dari kasir/administrasi RJ.
// Meniru pola transfer-ugd-ke-ri-actions (UGD→RI): dibuka via x-modal. Tombol di kasir-rj
// men-dispatch 'open-transfer-rj-ke-ugd' → komponen ini membuka modal 'transfer-rj-ke-ugd'.
// Beda dari UGD→RI: cara masuk dari master UGD (rsmst_entryugds), klaim default dari RJ,
// TIDAK ada pemilihan ruangan/bed (UGD tak punya kamar seperti RI).

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\QueryException;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use EmrRJTrait, WithRenderVersioningTrait;

    public ?int $rjNo = null;
    public ?string $regName = null;

    // ── Cara Masuk UGD (master rsmst_entryugds) ──
    public string $transferEntryId = '3'; // default DARI RS LAIN (mengikuti kebutuhan transfer RJ→UGD)
    public array $transferEntryOptions = [];

    // ── Dokter UGD ──
    // Dulu dr_id UGD disalin mentah dari dr_id RJ, jadi dokter UGD otomatis dokter poli
    // asal. Sekarang bisa dipilih; default tetap dokter RJ supaya perilaku lama tak
    // berubah diam-diam kalau petugas tak memilih apa-apa.
    public ?string $transferDrId = null;
    public ?string $transferDrName = null;

    // ── Jenis Klaim UGD (wajib dipilih saat transfer; default = klaim RJ) ──
    public string $transferKlaimId = 'UM';
    public array $klaimOptions = [['klaimId' => 'UM', 'klaimDesc' => 'UMUM'], ['klaimId' => 'JM', 'klaimDesc' => 'BPJS'], ['klaimId' => 'JR', 'klaimDesc' => 'JASA RAHARJA'], ['klaimId' => 'JML', 'klaimDesc' => 'Asuransi Lain'], ['klaimId' => 'KR', 'klaimDesc' => 'Kronis']];

    // ── Rincian biaya RJ yang akan dipindahkan ke UGD ──
    public array $biayaRJ = [];
    public int $totalBiayaRJ = 0;

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-transfer-rj-ke-ugd'];

    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);
    }

    /* ===============================
     | OPEN — dipicu dari kasir RJ
     =============================== */
    #[On('open-transfer-rj-ke-ugd')]
    public function openTransfer(int $rjNo): void
    {
        if (empty($rjNo)) {
            return;
        }

        $this->rjNo = $rjNo;
        $this->resetTransferState();
        $this->resetValidation();

        // Guard: RJ masih aktif
        if ($this->checkRJStatus($rjNo)) {
            $this->dispatch('toast', type: 'error', message: 'RJ sudah diproses, tidak bisa ditransfer.');
            return;
        }

        // Guard: lab belum selesai
        if ($this->checkLabPendingRJ($rjNo)) {
            $this->dispatch('toast', type: 'error', message: 'Hasil Laborat belum selesai, transfer tidak bisa dilakukan.');
            return;
        }

        // Guard: belum pernah ditransfer
        if (DB::table('rstxn_ugdbiayaselamadirjs')->where('rj_no', $rjNo)->exists()) {
            $this->dispatch('toast', type: 'error', message: 'Transfer ke UGD sudah pernah dilakukan untuk RJ ini.');
            return;
        }

        // Nama pasien + klaim RJ (dipakai sbg default pilihan klaim UGD)
        $hdr = DB::table('rstxn_rjhdrs as h')
            ->leftJoin('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->where('h.rj_no', $rjNo)
            ->leftJoin('rsmst_doctors as d', 'd.dr_id', '=', 'h.dr_id')
            ->select('p.reg_name', 'h.klaim_id', 'h.dr_id', 'd.dr_name')
            ->first();
        $this->regName = $hdr->reg_name ?? null;
        $this->transferKlaimId = $hdr->klaim_id ?: 'UM';

        // Default dokter UGD = dokter RJ (perilaku lama), kini bisa diganti lewat LOV.
        $this->transferDrId = $hdr->dr_id ?? null;
        $this->transferDrName = $hdr->dr_name ?? null;

        // Rincian biaya RJ (yang akan dipindahkan ke UGD)
        $this->biayaRJ = $this->calculateRJCosts($rjNo);
        $this->totalBiayaRJ = array_sum($this->biayaRJ);

        // Opsi Cara Masuk UGD (master rsmst_entryugds — sama dengan form Daftar UGD)
        $this->transferEntryOptions = DB::table('rsmst_entryugds')
            ->select('entry_id', 'entry_desc')
            ->orderBy('entry_id')
            ->get()
            ->map(fn($e) => ['entryId' => (string) $e->entry_id, 'entryDesc' => $e->entry_desc])
            ->toArray();

        $this->incrementVersion('modal-transfer-rj-ke-ugd');
        $this->dispatch('open-modal', name: 'transfer-rj-ke-ugd');
    }

    private function resetTransferState(): void
    {
        $this->transferEntryId = '3';
        $this->biayaRJ = [];
        $this->totalBiayaRJ = 0;
        // Wajib ikut di-reset: komponen di-mount sekali per halaman & dipakai berulang,
        // tanpa ini dokter pasien sebelumnya terbawa.
        $this->transferDrId = null;
        $this->transferDrName = null;
    }

    /** LOV dokter UGD — payload dari livewire/lov/dokter/lov-dokter. */
    #[On('lov.selected.dokter-transfer-rj-ke-ugd')]
    public function onDokterTransferUGD(string $target, ?array $payload): void
    {
        $this->transferDrId = $payload['dr_id'] ?? null;
        $this->transferDrName = $payload['dr_name'] ?? null;
    }

    #[On('lov.cleared.dokter-transfer-rj-ke-ugd')]
    public function onDokterTransferUGDCleared(string $target): void
    {
        $this->transferDrId = null;
        $this->transferDrName = null;
    }

    /**
     * Tarif kunjungan UGD untuk header baru — MENIRU recomputeAdminPrices() di
     * ⚡daftar-ugd-actions, sumber aturan resminya.
     *
     * ⚠️  Kolom rstxn_ugdhdrs.poli_price NAMANYA menyesatkan: isinya tarif UGD
     *     (rsmst_doctors.ugd_price), bukan tarif poli. Daftar RJ mengisi kolom
     *     senama dari poli_price — beda sumber, nama sama.
     *
     * Sebelum ini, insert header UGD hasil transfer TIDAK menyetel poli_price/rs_admin
     * sama sekali, jadi tarif dokter UGD tak pernah ikut terhitung dari dokter yang
     * dipilih. Terlihat di data: rj_no 198286 dokternya spesialis (ugd_price 100.000,
     * rs_admin 35.000) tapi header-nya 30.000/7.500 — tarif dokter umum.
     *
     * @return array{rsAdmin:int, poliPrice:int}
     */
    private function hitungTarifUGD(?string $drId, string $klaimId): array
    {
        // Kronis ATAU dokter belum dipilih → 0 semua (aturan sama dgn Daftar UGD).
        if ($klaimId === 'KR' || empty($drId)) {
            return ['rsAdmin' => 0, 'poliPrice' => 0];
        }

        $dokter = DB::table('rsmst_doctors')
            ->select('rs_admin', 'ugd_price', 'ugd_price_bpjs')
            ->where('dr_id', $drId)
            ->first();

        $klaimStatus = DB::table('rsmst_klaimtypes')->where('klaim_id', $klaimId)->value('klaim_status') ?? 'UMUM';

        return [
            'rsAdmin' => (int) ($dokter->rs_admin ?? 0),
            'poliPrice' => (int) ($klaimStatus === 'BPJS' ? ($dokter->ugd_price_bpjs ?? 0) : ($dokter->ugd_price ?? 0)),
        ];
    }

    /**
     * Tarif untuk ditampilkan di modal — memakai state pilihan saat ini.
     * Satu sumber dengan yang dipakai saat insert, supaya angka di layar tak pernah
     * beda dengan yang benar-benar tersimpan.
     *
     * @return array{rsAdmin:int, poliPrice:int}
     */
    public function hitungTarifUGDPreview(): array
    {
        return $this->hitungTarifUGD($this->transferDrId, $this->transferKlaimId ?: 'UM');
    }

    /* ===============================
     | PROSES TRANSFER KE UGD
     =============================== */
    public function transferKeUGD(): void
    {
        if (!$this->rjNo) {
            $this->dispatch('toast', type: 'error', message: 'Data transaksi tidak ditemukan.');
            return;
        }

        // Cek RJ masih aktif
        if ($this->checkRJStatus($this->rjNo)) {
            $this->dispatch('toast', type: 'error', message: 'RJ sudah diproses, tidak bisa ditransfer.');
            return;
        }

        // Cek lab pending
        if ($this->checkLabPendingRJ($this->rjNo)) {
            $this->dispatch('toast', type: 'error', message: 'Hasil Laborat belum selesai, transfer tidak bisa dilakukan.');
            return;
        }

        // Cek sudah pernah transfer
        if (DB::table('rstxn_ugdbiayaselamadirjs')->where('rj_no', $this->rjNo)->exists()) {
            $this->dispatch('toast', type: 'error', message: 'Transfer ke UGD sudah pernah dilakukan untuk RJ ini.');
            return;
        }

        try {
            // Global lock + retry: cegah rj_no (RSTXN_UGDHDRS) & tempadm_no kembar antar request,
            // dan toleransi race lintas-sistem (legacy Oracle Dev 6i).
            $maxAttempts = 5;
            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                try {
                    Cache::lock('lock:ugd-rjno-seq', 15)->block(5, function () {
                        DB::transaction(function () {
                            // Lock RJ row
                            $this->lockRJRow($this->rjNo);

                            // Re-check setelah lock
                            if ($this->checkRJStatus($this->rjNo)) {
                                throw new \RuntimeException('Data sudah diproses oleh user lain.');
                            }

                            $rjHdr = DB::table('rstxn_rjhdrs')->where('rj_no', $this->rjNo)->first();
                            if (!$rjHdr) {
                                throw new \RuntimeException('Data RJ tidak ditemukan.');
                            }

                            // Cek lockstatus pasien
                            $pasien = DB::table('rsmst_pasiens')
                                ->where('reg_no', $rjHdr->reg_no)
                                ->lockForUpdate()
                                ->first();

                            if ($pasien->lockstatus && !in_array($pasien->lockstatus, ['RJ', null])) {
                                throw new \RuntimeException("Pasien sedang dalam status {$pasien->lockstatus}, tidak bisa transfer.");
                            }

                            // Hitung biaya RJ
                            $costs = $this->calculateRJCosts($this->rjNo);
                            $totalBiayaRJ = array_sum($costs);

                            // Generate UGD rj_no
                            $ugdRjNo = (int) DB::table('rstxn_ugdhdrs')->max('rj_no') + 1;

                            // Tarif UGD dihitung dari dokter & klaim YANG DIPILIH di modal —
                            // bukan disalin dari RJ. Dokter UGD boleh beda dari dokter poli,
                            // dan tarif UGD-nya pun beda (spesialis 100rb vs umum 30rb).
                            $ugdDrId = $this->transferDrId ?: $rjHdr->dr_id;
                            $ugdKlaimId = $this->transferKlaimId ?: ($rjHdr->klaim_id ?: 'UM');
                            $tarifUGD = $this->hitungTarifUGD($ugdDrId, $ugdKlaimId);

                            // Insert UGD header (minimal — bisa diedit oleh admin UGD)
                            DB::table('rstxn_ugdhdrs')->insert([
                                'rj_no'       => $ugdRjNo,
                                'rj_date'     => $rjHdr->rj_date,
                                'reg_no'      => $rjHdr->reg_no,
                                'klaim_id'    => $ugdKlaimId, // dipilih di modal (default = klaim RJ)
                                // Dokter pilihan; fallback dokter RJ kalau LOV tak disentuh.
                                'dr_id'       => $ugdDrId,
                                'shift'       => $rjHdr->shift,
                                'txn_status'  => 'A',
                                'rj_status'   => 'A',
                                'pass_status' => $rjHdr->pass_status ?? 'O',
                                'sl_codefrom' => '02',
                                'entry_id'    => $this->transferEntryId ?: '3', // Cara Masuk UGD (rsmst_entryugds), dipilih di modal
                                'cek_lab'     => 0,
                                // Tarif kunjungan UGD dokter terpilih (kolom poli_price = tarif UGD).
                                'poli_price'  => $tarifUGD['poliPrice'],
                                'rs_admin'    => $tarifUGD['rsAdmin'],
                                // rj_admin (admin OB) TIDAK di-charge: pasien transfer sudah
                                // membayarnya di RJ — pass_status-nya pun dibawa dari RJ.
                                'rj_admin'    => 0,
                            ]);

                            // Generate tempadm_no
                            $tempadmNo = (int) DB::table('rstxn_ugdtempadmins')->max('tempadm_no') + 1;

                            // Insert temp admin biaya RJ
                            DB::table('rstxn_ugdtempadmins')->insert([
                                'tempadm_no'   => $tempadmNo,
                                'tempadm_date' => $rjHdr->rj_date,
                                'tempadm_flag' => 'RJ',
                                'tempadm_ref'  => $this->rjNo,
                                'rj_no'        => $ugdRjNo,
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

                            // Insert biaya selama di RJ
                            DB::table('rstxn_ugdbiayaselamadirjs')->insert([
                                'rj_no'              => $this->rjNo,
                                'rj_no_rsugd'        => $ugdRjNo,
                                'tanggal_rj'         => $rjHdr->rj_date,
                                'total_biayarj'      => $totalBiayaRJ,
                                'keterangan_biayarj' => 'RAWAT JALAN',
                            ]);

                            // Update RJ status → 'I' (Transfer UGD)
                            DB::table('rstxn_rjhdrs')
                                ->where('rj_no', $this->rjNo)
                                ->update([
                                    'rj_status'  => 'I',
                                    'txn_status' => 'I',
                                ]);

                            // Update lockstatus pasien → UGD
                            DB::table('rsmst_pasiens')
                                ->where('reg_no', $rjHdr->reg_no)
                                ->update(['lockstatus' => 'UGD']);

                            $this->appendAdminLogRJ($this->rjNo, 'Transfer ke UGD #' . $ugdRjNo . ' (total biaya RJ Rp ' . number_format($totalBiayaRJ, 0, ',', '.') . ')');
                        });
                    });
                    break;
                } catch (QueryException $e) {
                    if (str_contains($e->getMessage(), 'ORA-00001') && $attempt < $maxAttempts) {
                        usleep(random_int(50_000, 200_000));
                        continue;
                    }
                    throw $e;
                }
            }

            // Tutup modal + refresh kasir/administrasi RJ
            $this->dispatch('close-modal', name: 'transfer-rj-ke-ugd');
            $this->dispatch('rj-transferred-to-ugd', rjNo: $this->rjNo);
            $this->dispatch('administrasi-rj.updated');
            $this->dispatch('toast', type: 'success', message: 'Transfer biaya RJ ke UGD berhasil.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal transfer ke UGD: ' . $e->getMessage());
        }
    }
};
?>

<div>
    <x-modal name="transfer-rj-ke-ugd" size="full" height="full" focusable>
        <div class="flex flex-col h-full" wire:key="{{ $this->renderKey('modal-transfer-rj-ke-ugd', [$rjNo ?? 'new']) }}">

            {{-- ═══════════ HEADER — identitas pasien (gaya EMR RJ) ═══════════ --}}
            <div class="relative px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>
                <div class="relative flex items-start justify-between gap-4">
                    <div class="flex-1 min-w-0">
                        <p class="mb-1 text-xs font-semibold tracking-wide uppercase text-teal-600 dark:text-teal-400">
                            Transfer ke Gawat Darurat
                        </p>
                        @if ($rjNo)
                            <livewire:pages::transaksi.rj.display-pasien-rj.display-pasien-rj :rjNo="$rjNo"
                                wire:key="transfer-rj-ke-ugd-display-pasien-{{ $rjNo }}" />
                        @endif
                    </div>
                    <x-icon-button color="gray" type="button" x-on:click="$dispatch('close-modal', { name: 'transfer-rj-ke-ugd' })"
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

                    {{-- ── TOTAL ADMINISTRASI (rincian biaya RJ yang dipindahkan) ── --}}
                    @php
                        // Label & urutan mengikuti rincian Administrasi/Kasir RJ
                        $biayaRows = [
                            'RS Admin'      => $biayaRJ['rsAdmin'] ?? 0,
                            'Admin OB'      => $biayaRJ['rjAdmin'] ?? 0,
                            'Uang Periksa'  => $biayaRJ['poliPrice'] ?? 0,
                            'Jasa Karyawan' => $biayaRJ['actePrice'] ?? 0,
                            'Jasa Dokter'   => $biayaRJ['actdPrice'] ?? 0,
                            'Jasa Medis'    => $biayaRJ['actpPrice'] ?? 0,
                            'Obat'          => $biayaRJ['obat'] ?? 0,
                            'Laboratorium'  => $biayaRJ['lab'] ?? 0,
                            'Radiologi'     => $biayaRJ['rad'] ?? 0,
                            'Lain-Lain'     => $biayaRJ['other'] ?? 0,
                        ];
                    @endphp
                    <div class="lg:col-span-3 overflow-hidden bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                        <div class="flex flex-wrap items-center gap-3 px-5 py-4 bg-gradient-to-r from-teal-600 to-teal-500 dark:from-teal-700 dark:to-teal-600">
                            <div>
                                <p class="text-xs font-medium tracking-wide uppercase text-white/80">Total Administrasi RJ</p>
                                <p class="text-2xl font-bold text-white">Rp {{ number_format($totalBiayaRJ) }}</p>
                            </div>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-semibold text-white rounded-full bg-white/20">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                </svg>
                                Dipindahkan ke Gawat Darurat
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
                            Seluruh biaya Rawat Jalan di atas akan dipindahkan menjadi tagihan Gawat Darurat.
                        </p>
                    </div>

                    {{-- ── FORM TUJUAN UGD (Cara Masuk + Klaim; tanpa ruangan/bed) ── --}}
                    <div class="lg:col-span-2 p-5 space-y-4 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                        <h3 class="text-sm font-semibold text-ink dark:text-gray-200">Tujuan Gawat Darurat</h3>

                        {{-- 1) Cara Masuk UGD --}}
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-muted dark:text-gray-400">Cara Masuk</label>
                            <x-select-input wire:model="transferEntryId" class="w-full text-sm">
                                @foreach ($transferEntryOptions as $opt)
                                    <option value="{{ $opt['entryId'] }}">{{ $opt['entryDesc'] }}</option>
                                @endforeach
                            </x-select-input>
                        </div>

                        {{-- 2) Jenis Klaim UGD (kartu-tombol; state murni dari $transferKlaimId) --}}
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-muted dark:text-gray-400">Jenis Klaim</label>
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

                        {{-- 3) Dokter UGD --}}
                        <div class="w-full">
                            <label class="mb-1 block text-xs font-semibold text-muted dark:text-gray-400">Dokter
                                UGD</label>
                            <livewire:lov.dokter.lov-dokter target="dokter-transfer-rj-ke-ugd"
                                :initialDrId="$transferDrId" label="Cari Dokter UGD"
                                placeholder="Ketik nama/kode dokter..."
                                wire:key="lov-dokter-transfer-rj-ke-ugd-{{ $rjNo }}-{{ $renderVersions['modal-transfer-rj-ke-ugd'] ?? 0 }}" />
                            <p class="mt-1 text-xs text-muted dark:text-gray-400">
                                Default = dokter poli asal
                                @if ($transferDrName)
                                    (<span class="font-medium">{{ $transferDrName }}</span>)
                                @endif
                                . Ganti bila di UGD ditangani dokter lain.
                            </p>

                            {{-- Tarif ikut dokter & klaim terpilih — tampilkan supaya petugas
                                 tahu yang akan ditagih SEBELUM menekan Transfer. --}}
                            @php $tarifPreview = $this->hitungTarifUGDPreview(); @endphp
                            <div
                                class="flex flex-wrap items-center gap-x-4 gap-y-1 px-3 py-2 mt-2 text-xs border rounded-lg bg-surface-soft border-hairline dark:bg-gray-800/40 dark:border-gray-700">
                                <span class="text-muted dark:text-gray-400">Tarif UGD dokter ini:</span>
                                <span class="font-semibold text-teal-700 dark:text-teal-300">
                                    Rp {{ number_format($tarifPreview['poliPrice']) }}
                                </span>
                                <span class="text-muted dark:text-gray-400">Adm RS:</span>
                                <span class="font-semibold text-teal-700 dark:text-teal-300">
                                    Rp {{ number_format($tarifPreview['rsAdmin']) }}
                                </span>
                                <span class="text-muted-soft">(mengikuti klaim
                                    {{ collect($klaimOptions)->firstWhere('klaimId', $transferKlaimId)['klaimDesc'] ?? $transferKlaimId }})</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ═══════════ FOOTER (sticky) ═══════════ --}}
            <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-4 border-t border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-700">
                <div class="text-sm text-muted dark:text-gray-400">
                    Total <strong class="text-teal-700 dark:text-teal-300">Rp {{ number_format($totalBiayaRJ) }}</strong>
                    akan dipindahkan ke Gawat Darurat.
                </div>
                <div class="flex items-center gap-3">
                    <x-secondary-button type="button" x-on:click="$dispatch('close-modal', { name: 'transfer-rj-ke-ugd' })">
                        Batal
                    </x-secondary-button>
                    <x-confirm-button variant="warning" :action="'transferKeUGD()'" title="Transfer ke UGD"
                        message="Yakin ingin mentransfer biaya RJ (Rp {{ number_format($totalBiayaRJ) }}) ke Gawat Darurat? Status RJ akan menjadi 'Transfer UGD' dan seluruh biaya dipindahkan ke UGD."
                        confirmText="Ya, transfer" cancelText="Batal">
                        Konfirmasi Transfer ke UGD
                    </x-confirm-button>
                </div>
            </div>
        </div>
    </x-modal>
</div>
