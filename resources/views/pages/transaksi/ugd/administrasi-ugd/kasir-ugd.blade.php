<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use EmrUGDTrait, WithRenderVersioningTrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['kasir-ugd'];

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $dataDaftarUGD = [];

    // ── Ringkasan Biaya ──
    public int $rjTotal = 0;
    public ?int $rjDiskon = 0;
    public int $dspTotalAll = 0;
    public int $sudahBayar = 0;
    public int $rjSisa = 0;

    // ── Input Kasir ──
    public ?string $accId = null;
    public ?string $accName = null;
    public ?int $bayar = null;
    public int $kembalian = 0;

    // ── Status Transaksi ──
    public ?string $txnStatus = null;

    // ── Transfer ke RI ──
    public bool $showTransferRI = false;
    public ?string $transferRoomId = null;
    public ?string $transferRoomName = null;
    public ?string $transferBedNo = null;

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);

        if ($this->rjNo) {
            $this->loadKasirUGD($this->rjNo);
        } else {
            $this->isFormLocked = false;
        }
    }

    /* ===============================
     | LISTENER
     =============================== */
    #[On('administrasi-kasir-ugd.updated')]
    public function onAdministrasiKasirUpdated(): void
    {
        if ($this->rjNo) {
            $this->loadKasirUGD($this->rjNo);
        }
    }

    /* ===============================
     | LOV KAS
     =============================== */
    #[On('lov.selected.kas-kasir-ugd')]
    public function onKasSelected(string $target, ?array $payload): void
    {
        $this->accId = $payload['acc_id'] ?? null;
        $this->accName = $payload['acc_name'] ?? null;
        $this->resetErrorBag('accId');
        $this->dispatch('focus-input-bayar');
    }

    /* ===============================
     | LOAD KASIR
     =============================== */
    public function loadKasirUGD($rjNo): void
    {
        $this->resetKasir();
        $this->rjNo = $rjNo;
        $this->resetValidation();

        $this->dataDaftarUGD = $this->findDataUGD($rjNo) ?? [];

        if (empty($this->dataDaftarUGD)) {
            $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.');
            return;
        }

        $hdr = DB::table('rstxn_ugdhdrs')->select('rj_status', 'txn_status', 'rj_diskon', 'acc_id')->where('rj_no', $rjNo)->first();

        if (!$hdr) {
            $this->dispatch('toast', type: 'error', message: 'Data transaksi tidak ditemukan.');
            return;
        }

        if ($this->checkUGDStatus($rjNo)) {
            $this->isFormLocked = true;
        }

        $this->txnStatus = $hdr->rj_status;
        $this->rjDiskon = (int) ($hdr->rj_diskon ?? 0);

        if ($hdr->acc_id) {
            $this->accId = $hdr->acc_id;
            $this->accName = DB::table('acmst_accounts')->where('acc_id', $hdr->acc_id)->value('acc_name') ?? $hdr->acc_id;
        }

        $this->hitungTotal();
        $this->incrementVersion('kasir-ugd');
    }

    private function resetKasir(): void
    {
        $this->reset(['rjNo', 'dataDaftarUGD', 'bayar', 'accId', 'accName', 'txnStatus']);
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->rjTotal = 0;
        $this->rjDiskon = 0;
        $this->dspTotalAll = 0;
        $this->sudahBayar = 0;
        $this->rjSisa = 0;
        $this->kembalian = 0;
    }

    /* ===============================
     | HITUNG TOTAL
     =============================== */
    public function hitungTotal(): void
    {
        if (!$this->rjNo) {
            return;
        }

        $costs = $this->calculateUGDCosts($this->rjNo);
        $this->rjTotal = array_sum($costs);

        $this->recalcSisa();
    }

    private function recalcSisa(): void
    {
        $this->dspTotalAll = max(0, $this->rjTotal - $this->rjDiskon);
        $this->sudahBayar = (int) DB::table('rstxn_ugdcashins')->where('rj_no', $this->rjNo)->sum('rjc_nominal');
        $this->rjSisa = max(0, $this->dspTotalAll - $this->sudahBayar);
        $this->hitungKembalian();
    }

    /* ===============================
     | REAKTIF
     =============================== */
    public function updatedRjDiskon(): void
    {
        $this->rjDiskon = max(0, (int) $this->rjDiskon);
        $this->recalcSisa();
    }

    public function updatedBayar(): void
    {
        $this->hitungKembalian();
    }

    private function hitungKembalian(): void
    {
        $bayar = (int) ($this->bayar ?? 0);
        $this->kembalian = $bayar >= $this->rjSisa ? $bayar - $this->rjSisa : 0;
    }

    /* ===============================
     | VALIDASI
     =============================== */
    protected function rules(): array
    {
        return [
            'accId' => ['required', 'string'],
            'bayar' => ['required', 'integer', 'min:1'],
            'rjDiskon' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function messages(): array
    {
        return [
            'accId.required' => 'Akun kas belum dipilih.',
            'bayar.required' => 'Kolom Bayar masih kosong.',
            'bayar.min' => 'Nominal bayar harus lebih dari 0.',
        ];
    }

    /* ===============================
     | POST TRANSAKSI
     =============================== */
    public function postTransaksi(): void
    {
        // 1. Read-only guard
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }

        // 2. Cek akun kas user — pakai user_kas (bukan smmst_kases)
        $cekAkunKas = DB::table('user_kas')
            ->where('user_id', auth()->id())
            ->count();

        if ($cekAkunKas === 0) {
            $this->dispatch('toast', type: 'error', message: 'Akun kas anda belum terkonfigurasi. Hubungi administrator.');
            return;
        }

        // 3. Guard header
        if (!DB::table('rstxn_ugdhdrs')->where('rj_no', $this->rjNo)->exists()) {
            $this->dispatch('toast', type: 'error', message: 'Data transaksi tidak ditemukan.');
            return;
        }

        // 4. Cek status UGD sebelum lock
        if ($this->checkUGDStatus($this->rjNo)) {
            $this->dispatch('toast', type: 'info', message: 'Data sudah diproses.');
            return;
        }

        // 5. Validasi form
        $this->validate();

        // 6. Cek lab pending
        if ($this->checkLabPending($this->rjNo, 'UGD')) {
            $this->dispatch('toast', type: 'error', message: 'Hasil Lab belum selesai, pembayaran tidak bisa diproses.');
            return;
        }

        // 7. Ambil emp_id dari users — tidak perlu query smmst_users lagi
        $empId = auth()->user()->emp_id;

        if (!$empId) {
            $this->dispatch('toast', type: 'error', message: 'EMP ID belum diisi di profil user. Hubungi administrator.');
            return;
        }

        $bayar = (int) $this->bayar;
        $dspTotalAll = $this->rjSisa;
        $newTxnStatus = null;

        try {
            DB::transaction(function () use ($bayar, $dspTotalAll, $empId, &$newTxnStatus) {
                $this->lockUGDRow($this->rjNo);

                if ($this->checkUGDStatus($this->rjNo)) {
                    throw new \RuntimeException('Data sudah diproses oleh user lain.');
                }

                $rjHdr = DB::table('rstxn_ugdhdrs')->where('rj_no', $this->rjNo)->first();

                $cashRow = [
                    'acc_id' => $this->accId,
                    'rjc_dtl' => DB::raw('rjcdtl_seq.nextval'),
                    'rjc_date' => $rjHdr->rj_date,
                    'rjc_desc' => $rjHdr->reg_no . ' / ' . $rjHdr->rj_no,
                    'emp_id' => $empId,
                    'rj_no' => $this->rjNo,
                    'shift' => $rjHdr->shift,
                ];

                if ($bayar < $dspTotalAll) {
                    // CICILAN
                    DB::table('rstxn_ugdcashins')->insert(array_merge($cashRow, ['rjc_nominal' => $bayar]));
                    DB::table('rstxn_ugdhdrs')
                        ->where('rj_no', $this->rjNo)
                        ->update([
                            'txn_status' => 'H',
                            'pay_date' => null,
                            'acc_id' => $this->accId,
                            'rj_diskon' => $this->rjDiskon,
                            'rj_status' => 'L',
                            'emp_id' => $empId,
                        ]);
                    $newTxnStatus = 'H';
                } else {
                    // LUNAS
                    if ($this->rjTotal > 0) {
                        DB::table('rstxn_ugdcashins')->insert(array_merge($cashRow, ['rjc_nominal' => $dspTotalAll]));
                    }
                    DB::table('rstxn_ugdhdrs')
                        ->where('rj_no', $this->rjNo)
                        ->update([
                            'txn_status' => 'L',
                            'pay_date' => $rjHdr->rj_date,
                            'acc_id' => $this->accId,
                            'rj_diskon' => $this->rjDiskon,
                            'rj_status' => 'L',
                            'emp_id' => $empId,
                        ]);
                    $newTxnStatus = 'L';

                    DB::table('rsmst_pasiens')
                        ->where('reg_no', $rjHdr->reg_no)
                        ->update(['lockstatus' => null]);
                }
            });

            $this->txnStatus = $newTxnStatus;
            $this->hitungTotal();
            $this->isFormLocked = true;
            $this->bayar = null;
            $this->kembalian = 0;
            $this->incrementVersion('kasir-ugd');

            $msg = $newTxnStatus === 'L' ? 'Pembayaran lunas berhasil disimpan.' : 'Pembayaran sebagian (cicilan) berhasil disimpan.';

            $this->dispatch('toast', type: 'success', message: $msg);
            $this->dispatch('administrasi-ugd.updated');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal memproses pembayaran: ' . $e->getMessage());
        }
    }

    /* ===============================
     | BATAL TRANSAKSI
     =============================== */
    public function batalTransaksi(): void
    {
        if (!auth()->user()->hasAnyRole(['Admin', 'Tu'])) {
            $this->dispatch('toast', type: 'error', message: 'Hanya Admin dan TU yang dapat membatalkan transaksi.');
            return;
        }

        if (!$this->rjNo) {
            $this->dispatch('toast', type: 'error', message: 'Data transaksi tidak ditemukan.');
            return;
        }

        // Cek lab pending sebelum batal
        if ($this->checkLabPending($this->rjNo, 'UGD')) {
            $this->dispatch('toast', type: 'error', message: 'Hasil Lab belum selesai, transaksi tidak bisa dibatalkan.');
            return;
        }

        $hdr = DB::table('rstxn_ugdhdrs')->select('rj_status', 'txn_status', 'reg_no')->where('rj_no', $this->rjNo)->first();

        if (!$hdr) {
            $this->dispatch('toast', type: 'error', message: 'Data transaksi tidak ditemukan.');
            return;
        }

        try {
            DB::transaction(function () use ($hdr) {
                DB::table('rstxn_ugdcashins')->where('rj_no', $this->rjNo)->delete();

                DB::table('rstxn_ugdhdrs')
                    ->where('rj_no', $this->rjNo)
                    ->update([
                        'txn_status' => 'A',
                        'pay_date' => null,
                        'acc_id' => null,
                        'rj_diskon' => 0,
                        'rj_status' => 'A',
                        'emp_id' => null,
                    ]);

                if ($hdr->reg_no) {
                    DB::table('rsmst_pasiens')
                        ->where('reg_no', $hdr->reg_no)
                        ->update(['lockstatus' => null]);
                }
            });

            $this->txnStatus = null;
            $this->rjDiskon = 0;
            $this->accId = null;
            $this->accName = null;
            $this->bayar = null;
            $this->kembalian = 0;
            $this->isFormLocked = false;

            $this->hitungTotal();
            $this->incrementVersion('kasir-ugd');

            $this->dispatch('toast', type: 'success', message: 'Transaksi berhasil dibatalkan.');
            $this->dispatch('administrasi-ugd.updated');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal membatalkan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | LOV ROOM LISTENER (untuk transfer RI)
     =============================== */
    #[On('lov.selected.room-transfer-ri')]
    public function onRoomTransferRI(string $target, ?array $payload): void
    {
        if ($payload) {
            $this->transferRoomId = $payload['room_id'] ?? null;
            $this->transferRoomName = $payload['room_name'] ?? null;
            $this->transferBedNo = $payload['bed_no'] ?? null;
        } else {
            $this->transferRoomId = null;
            $this->transferRoomName = null;
            $this->transferBedNo = null;
        }
    }

    /* ===============================
     | TRANSFER KE RI
     =============================== */
    public function toggleTransferRI(): void
    {
        $this->showTransferRI = !$this->showTransferRI;
        $this->transferRoomId = null;
        $this->transferRoomName = null;
        $this->transferBedNo = null;
    }

    public function transferKeRI(): void
    {
        if (!$this->rjNo) {
            $this->dispatch('toast', type: 'error', message: 'Data transaksi tidak ditemukan.');
            return;
        }

        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Transaksi sudah selesai, tidak bisa ditransfer.');
            return;
        }

        // Cek UGD masih aktif
        if ($this->checkUGDStatus($this->rjNo)) {
            $this->dispatch('toast', type: 'error', message: 'UGD sudah diproses, tidak bisa ditransfer.');
            return;
        }

        // Cek lab pending
        if ($this->checkLabPending($this->rjNo, 'UGD')) {
            $this->dispatch('toast', type: 'error', message: 'Hasil Lab belum selesai, transfer tidak bisa dilakukan.');
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
                    'entry_id'    => '5', // dari UGD
                    'dr_id'       => $ugdHdr->dr_id,
                    'room_id'     => $this->transferRoomId,
                    'bed_no'      => $this->transferBedNo,
                    'klaim_id'    => $ugdHdr->klaim_id,
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
            });

            $this->isFormLocked = true;
            $this->txnStatus = 'I';
            $this->showTransferRI = false;
            $this->hitungTotal();
            $this->incrementVersion('kasir-ugd');

            $this->dispatch('toast', type: 'success', message: 'Transfer biaya UGD ke RI berhasil.');
            $this->dispatch('administrasi-ugd.updated');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal transfer ke RI: ' . $e->getMessage());
        }
    }

    /* ===============================
     | BATAL TRANSFER RI
     =============================== */
    public function batalTransferRI(): void
    {
        if (!auth()->user()->hasAnyRole(['Admin', 'Tu'])) {
            $this->dispatch('toast', type: 'error', message: 'Hanya Admin dan TU yang dapat membatalkan transfer.');
            return;
        }

        if (!$this->rjNo) {
            $this->dispatch('toast', type: 'error', message: 'Data transaksi tidak ditemukan.');
            return;
        }

        $transfer = DB::table('rstxn_ribiayaselamadugds')
            ->where('rj_no', $this->rjNo)
            ->first();

        if (!$transfer) {
            $this->dispatch('toast', type: 'error', message: 'Tidak ada data transfer untuk UGD ini.');
            return;
        }

        $riHdrNo = $transfer->ugd_no_rsri;

        // Cek status RI masih aktif
        $riHdr = DB::table('rstxn_rihdrs')->where('rihdr_no', $riHdrNo)->first();
        if ($riHdr && !in_array($riHdr->ri_status, ['I'])) {
            $this->dispatch('toast', type: 'error', message: 'RI #' . $riHdrNo . ' sudah diproses (status: ' . $riHdr->ri_status . '). Tidak bisa dibatalkan.');
            return;
        }

        // Cek RI belum ada transaksi
        $riAdaTransaksi =
            DB::table('rstxn_rivisits')->where('rihdr_no', $riHdrNo)->exists()
            || DB::table('rstxn_rikonsuls')->where('rihdr_no', $riHdrNo)->exists()
            || DB::table('rstxn_riactparams')->where('rihdr_no', $riHdrNo)->exists()
            || DB::table('rstxn_riactdocs')->where('rihdr_no', $riHdrNo)->exists()
            || DB::table('rstxn_rilabs')->where('rihdr_no', $riHdrNo)->exists()
            || DB::table('rstxn_riradiologs')->where('rihdr_no', $riHdrNo)->exists()
            || DB::table('rstxn_rioks')->where('rihdr_no', $riHdrNo)->exists()
            || DB::table('rstxn_riobats')->where('rihdr_no', $riHdrNo)->exists()
            || DB::table('rstxn_riothers')->where('rihdr_no', $riHdrNo)->exists()
            || DB::table('rstxn_ripaymentdtls')->where('rihdr_no', $riHdrNo)->exists();

        if ($riAdaTransaksi) {
            $this->dispatch('toast', type: 'error', message: 'RI #' . $riHdrNo . ' sudah ada transaksi. Tidak bisa dibatalkan.');
            return;
        }

        // Cek lab UGD pending
        if ($this->checkLabPending($this->rjNo, 'UGD')) {
            $this->dispatch('toast', type: 'error', message: 'Hasil Lab UGD belum selesai, batal transfer tidak bisa dilakukan.');
            return;
        }

        try {
            DB::transaction(function () use ($riHdrNo) {
                $this->lockUGDRow($this->rjNo);

                $ugdHdr = DB::table('rstxn_ugdhdrs')->where('rj_no', $this->rjNo)->first();
                if (!$ugdHdr || $ugdHdr->rj_status !== 'I') {
                    throw new \RuntimeException('Status UGD bukan Transfer/Inap, tidak bisa dibatalkan.');
                }

                // Restore rstxn_ugdtempadmins dari rstxn_ritempadmins (kecuali flag='UGD' yang biaya UGD sendiri)
                $riTemps = DB::table('rstxn_ritempadmins')
                    ->where('rihdr_no', $riHdrNo)
                    ->where('tempadm_flag', '!=', 'UGD')
                    ->get();

                $ugdTempNo = (int) DB::table('rstxn_ugdtempadmins')->max('tempadm_no') + 1;
                foreach ($riTemps as $temp) {
                    DB::table('rstxn_ugdtempadmins')->insert([
                        'tempadm_no'   => $ugdTempNo++,
                        'tempadm_date' => $temp->tempadm_date,
                        'tempadm_flag' => $temp->tempadm_flag,
                        'tempadm_ref'  => $temp->tempadm_ref,
                        'rj_no'        => $this->rjNo,
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

                // Hapus data RI
                DB::table('rstxn_ritempadmins')->where('rihdr_no', $riHdrNo)->delete();
                DB::table('rsmst_trfrooms')->where('rihdr_no', $riHdrNo)->delete();
                DB::table('rstxn_ribiayaselamadugds')->where('rj_no', $this->rjNo)->delete();
                DB::table('rstxn_rihdrs')->where('rihdr_no', $riHdrNo)->delete();

                // Kembalikan status UGD → 'A'
                DB::table('rstxn_ugdhdrs')
                    ->where('rj_no', $this->rjNo)
                    ->update([
                        'rj_status'  => 'A',
                        'txn_status' => 'A',
                    ]);

                // Kembalikan lockstatus pasien → 'UGD'
                DB::table('rsmst_pasiens')
                    ->where('reg_no', $ugdHdr->reg_no)
                    ->update(['lockstatus' => 'UGD']);
            });

            $this->isFormLocked = false;
            $this->txnStatus = 'A';
            $this->hitungTotal();
            $this->incrementVersion('kasir-ugd');

            $this->dispatch('toast', type: 'success', message: 'Batal transfer berhasil. UGD kembali aktif.');
            $this->dispatch('administrasi-ugd.updated');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal batal transfer: ' . $e->getMessage());
        }
    }
};
?>

<div class="space-y-4" wire:key="{{ $this->renderKey('kasir-ugd', [$rjNo ?? 'new']) }}">

    {{-- LOCKED BANNER --}}
    @if ($isFormLocked)
        <div
            class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
            Transaksi sudah lunas — data terkunci, tidak dapat diubah.
        </div>
    @endif

    {{-- RINGKASAN BIAYA --}}
    <div class="p-4 border border-gray-200 rounded-2xl dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40">
        <div class="flex items-stretch gap-3">

            <div
                class="flex-1 px-4 py-3 bg-white border border-gray-200 rounded-xl dark:bg-gray-900 dark:border-gray-700">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Total Tagihan</p>
                <p class="text-base font-bold text-gray-800 dark:text-gray-100">Rp {{ number_format($rjTotal) }}</p>
            </div>

            <div class="flex items-center text-gray-300 dark:text-gray-600">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </div>

            <div
                class="flex-1 px-4 py-3 border border-amber-200 rounded-xl dark:border-amber-800/40 bg-amber-50 dark:bg-amber-900/10">
                <p class="mb-1 text-xs font-medium text-amber-600 dark:text-amber-400">
                    Diskon @if (!$isFormLocked)
                        <span class="opacity-60">(dapat diubah)</span>
                    @endif
                </p>
                @if (!$isFormLocked)
                    <x-text-input wire:model.live="rjDiskon" type="number" min="0"
                        class="w-full px-0 py-0 text-base font-bold text-amber-700 bg-transparent border-0
                            dark:text-amber-300 focus:ring-0 focus:outline-none
                            [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none
                            [&::-webkit-inner-spin-button]:appearance-none"
                        placeholder="0" x-on:keyup.enter="$dispatch('focus-lov-kas-kasir-ugd')" />
                    <x-input-error :messages="$errors->get('rjDiskon')" class="mt-1" />
                @else
                    <p class="text-base font-bold text-amber-700 dark:text-amber-300">Rp {{ number_format($rjDiskon) }}
                    </p>
                @endif
            </div>

            <div class="flex items-center text-gray-300 dark:text-gray-600">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </div>

            <div
                class="flex-1 px-4 py-3 border border-blue-200 rounded-xl dark:border-blue-800/40 bg-blue-50 dark:bg-blue-900/10">
                <p class="text-xs text-blue-600 dark:text-blue-400 mb-0.5">Setelah Diskon</p>
                <p class="text-base font-bold text-blue-700 dark:text-blue-300">Rp {{ number_format($dspTotalAll) }}</p>
            </div>

            <div class="flex items-center text-gray-300 dark:text-gray-600">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </div>

            @if ($sudahBayar > 0)
                <div
                    class="flex-1 px-4 py-3 border border-violet-200 rounded-xl dark:border-violet-800/40 bg-violet-50 dark:bg-violet-900/10">
                    <p class="text-xs text-violet-600 dark:text-violet-400 mb-0.5">Sudah Dibayar</p>
                    <p class="text-base font-bold text-violet-700 dark:text-violet-300">Rp
                        {{ number_format($sudahBayar) }}</p>
                </div>
                <div class="flex items-center text-gray-300 dark:text-gray-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </div>
            @endif

            <div
                class="flex-1 px-4 py-3 border rounded-xl
                {{ $rjSisa > 0
                    ? 'border-rose-200 dark:border-rose-800/40 bg-rose-50 dark:bg-rose-900/10'
                    : 'border-emerald-200 dark:border-emerald-800/40 bg-emerald-50 dark:bg-emerald-900/10' }}">
                <p
                    class="text-xs mb-0.5 {{ $rjSisa > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                    Sisa Tagihan
                </p>
                <p
                    class="text-base font-bold {{ $rjSisa > 0 ? 'text-rose-700 dark:text-rose-300' : 'text-emerald-700 dark:text-emerald-300' }}">
                    Rp {{ number_format($rjSisa) }}
                </p>
            </div>

        </div>
    </div>

    {{-- FORM INPUT PEMBAYARAN --}}
    <div class="p-4 border border-gray-200 rounded-2xl dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40" x-data
        x-on:focus-input-bayar.window="$nextTick(() => $refs.inputBayar?.focus())">

        @if ($isFormLocked)
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <p class="text-sm italic text-gray-400 dark:text-gray-600">Form input dinonaktifkan.</p>
                    @hasanyrole('Admin|Tu')
                    <div class="flex gap-2">
                        @if ($txnStatus === 'I')
                            <x-confirm-button variant="warning" :action="'batalTransferRI()'" title="Batal Transfer RI"
                                message="Yakin ingin membatalkan transfer ke RI? Data RI yang dibuat dari transfer akan dihapus dan UGD kembali aktif. Hanya bisa jika RI belum ada transaksi."
                                confirmText="Ya, batalkan transfer" cancelText="Batal">
                                Batal Transfer RI
                            </x-confirm-button>
                        @else
                            <x-confirm-button variant="danger" :action="'batalTransaksi()'" title="Batal Transaksi"
                                message="Yakin ingin membatalkan transaksi? Semua data pembayaran akan dihapus."
                                confirmText="Ya, batalkan" cancelText="Batal">
                                Batal Transaksi
                            </x-confirm-button>
                        @endif
                    </div>
                    @endhasanyrole
                </div>

                @if ($txnStatus === 'I')
                    <div class="flex items-start gap-2 px-3 py-2 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg dark:bg-amber-900/20 dark:border-amber-700 dark:text-amber-300">
                        <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div>
                            <p class="font-semibold">Status: Sudah ditransfer ke Rawat Inap</p>
                            <p class="mt-1">Biaya UGD (termasuk biaya RJ jika ada) telah dipindahkan ke RI. Jika perlu membatalkan transfer:</p>
                            <ol class="mt-1 ml-4 space-y-0.5 list-decimal">
                                <li>Pastikan di RI <strong>belum ada transaksi</strong> apapun (visit, konsul, lab, radiologi, OK, obat, lain-lain, pembayaran).</li>
                                <li>Pastikan <strong>hasil lab UGD sudah selesai</strong> (tidak ada lab pending).</li>
                                <li>Klik tombol <strong>"Batal Transfer RI"</strong> di atas, lalu konfirmasi.</li>
                                <li>Status UGD akan kembali aktif, data RI dihapus, dan biaya cascade (dari RJ) dikembalikan ke UGD.</li>
                            </ol>
                        </div>
                    </div>
                @elseif ($txnStatus === 'L')
                    <div class="flex items-start gap-2 px-3 py-2 text-xs text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg dark:bg-emerald-900/20 dark:border-emerald-700 dark:text-emerald-300">
                        <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p class="font-semibold">Status: Lunas</p>
                    </div>
                @elseif ($txnStatus === 'H')
                    <div class="flex items-start gap-2 px-3 py-2 text-xs text-violet-700 bg-violet-50 border border-violet-200 rounded-lg dark:bg-violet-900/20 dark:border-violet-700 dark:text-violet-300">
                        <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p class="font-semibold">Status: Cicilan (Belum Lunas)</p>
                    </div>
                @endif
            </div>
        @else
            {{-- Panduan penggunaan --}}
            @if ($txnStatus === null || $txnStatus === 'A')
                <div class="flex items-start gap-2 px-3 py-2 mb-3 text-xs text-gray-600 bg-gray-100 border border-gray-200 rounded-lg dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400">
                    <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div>
                        <p class="font-semibold text-gray-700 dark:text-gray-300">Panduan Kasir UGD:</p>
                        <ul class="mt-1 space-y-0.5 list-disc list-inside">
                            <li><strong>Post Transaksi</strong> — Pilih Akun Kas, isi nominal bayar, lalu klik "Post Transaksi". Bisa cicilan atau lunas.</li>
                            <li><strong>Transfer ke RI</strong> — Jika pasien UGD perlu rawat inap, klik "Transfer ke RI", pilih ruangan & bed, lalu konfirmasi. Seluruh biaya UGD (termasuk biaya RJ jika ada) akan dipindahkan ke RI.</li>
                        </ul>
                    </div>
                </div>
            @endif

            <div class="flex items-end gap-3">

                {{-- LOV Akun Kas — tipe="ugd" agar hanya tampil kas aktif untuk UGD --}}
                <div class="w-80"
                    x-on:focus-lov-kas-kasir-ugd.window="$nextTick(() => $el.querySelector('input')?.focus())">
                    <livewire:lov.kas.lov-kas target="kas-kasir-ugd" tipe="ugd" label="Akun Kas" :initialAccId="$accId"
                        wire:key="lov-kas-kasir-ugd-{{ $rjNo }}-{{ $renderVersions['kasir-ugd'] ?? 0 }}" />
                    <x-input-error :messages="$errors->get('accId')" class="mt-1" />
                </div>

                {{-- Input Bayar --}}
                <div class="w-52">
                    <x-input-label value="Nominal Bayar (Rp)" class="mb-1" />
                    <x-text-input type="number" wire:model.live="bayar" placeholder="0"
                        class="w-full font-mono text-right" min="1" x-ref="inputBayar"
                        x-on:keyup.enter="$wire.postTransaksi()" />
                    <x-input-error :messages="$errors->get('bayar')" class="mt-1" />
                </div>

                {{-- Kembalian / Kurang Bayar --}}
                @if ((int) ($bayar ?? 0) >= $rjSisa && $rjSisa > 0)
                    <div
                        class="flex-1 px-4 py-2.5 rounded-xl border border-emerald-200 dark:border-emerald-800/40 bg-emerald-50 dark:bg-emerald-900/10">
                        <p class="text-xs font-medium text-emerald-600 dark:text-emerald-400">Kembalian</p>
                        <p class="text-lg font-bold text-emerald-700 dark:text-emerald-300">Rp
                            {{ number_format($kembalian) }}</p>
                    </div>
                @elseif ((int) ($bayar ?? 0) > 0 && (int) ($bayar ?? 0) < $rjSisa)
                    <div
                        class="flex-1 px-4 py-2.5 rounded-xl border border-amber-200 dark:border-amber-800/40 bg-amber-50 dark:bg-amber-900/10">
                        <p class="text-xs font-medium text-amber-600 dark:text-amber-400">Kurang Bayar</p>
                        <p class="text-lg font-bold text-amber-700 dark:text-amber-300">Rp
                            {{ number_format($rjSisa - (int) ($bayar ?? 0)) }}</p>
                    </div>
                @else
                    <div class="flex-1"></div>
                @endif

                {{-- Tombol Post & Transfer --}}
                <div class="flex gap-2 pb-0.5">
                    <x-primary-button wire:click="postTransaksi" wire:loading.attr="disabled"
                        wire:target="postTransaksi">
                        <span wire:loading.remove wire:target="postTransaksi">Post Transaksi</span>
                        <span wire:loading wire:target="postTransaksi"><x-loading /></span>
                    </x-primary-button>

                    @if ($txnStatus === null || $txnStatus === 'A')
                        <x-secondary-button wire:click="toggleTransferRI">
                            {{ $showTransferRI ? 'Tutup Transfer' : 'Transfer ke RI' }}
                        </x-secondary-button>
                    @endif
                </div>

            </div>

            {{-- Badge status pembayaran --}}
            @if ((int) ($bayar ?? 0) >= $rjSisa && $rjSisa > 0)
                <div class="flex items-center gap-1.5 mt-3">
                    <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                        Pembayaran akan diproses sebagai LUNAS
                    </span>
                </div>
            @elseif ((int) ($bayar ?? 0) > 0 && (int) ($bayar ?? 0) < $rjSisa)
                <div class="flex items-center gap-1.5 mt-3">
                    <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-xs font-semibold text-amber-600 dark:text-amber-400">
                        Pembayaran akan diproses sebagai CICILAN — sisa Rp
                        {{ number_format($rjSisa - (int) ($bayar ?? 0)) }}
                    </span>
                </div>
            @endif

            {{-- PANEL TRANSFER KE RI --}}
            @if ($showTransferRI)
                <div class="p-4 mt-3 space-y-3 border border-blue-200 rounded-xl bg-blue-50 dark:bg-blue-900/20 dark:border-blue-700">
                    <p class="text-sm font-semibold text-blue-700 dark:text-blue-300">Transfer ke Rawat Inap — Pilih Ruangan & Bed</p>

                    <div class="w-full">
                        <livewire:lov.room.lov-room target="room-transfer-ri"
                            wire:key="lov-room-transfer-ri-{{ $rjNo }}-{{ $renderVersions['kasir-ugd'] ?? 0 }}" />
                    </div>

                    @if ($transferRoomId && $transferBedNo)
                        <div class="flex items-center gap-3">
                            <span class="text-sm text-blue-700 dark:text-blue-300">
                                Ruangan: <strong>{{ $transferRoomName }}</strong> | Bed: <strong>{{ $transferBedNo }}</strong>
                            </span>
                            <x-confirm-button variant="warning" :action="'transferKeRI()'" title="Transfer ke RI"
                                message="Yakin ingin mentransfer biaya UGD ke Rawat Inap? Pasien akan masuk ruangan {{ $transferRoomName }} bed {{ $transferBedNo }}."
                                confirmText="Ya, transfer" cancelText="Batal">
                                Konfirmasi Transfer ke RI
                            </x-confirm-button>
                        </div>
                    @else
                        <p class="text-xs text-blue-500 dark:text-blue-400">Cari dan pilih ruangan/bed di atas untuk melanjutkan transfer.</p>
                    @endif
                </div>
            @endif
        @endif

    </div>

    {{-- TABEL RIWAYAT PEMBAYARAN --}}
    <div class="overflow-hidden bg-white border border-gray-200 rounded-2xl dark:border-gray-700 dark:bg-gray-900">

        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Riwayat Pembayaran</h3>
            @php $cashins = DB::table('rstxn_ugdcashins')->where('rj_no', $rjNo)->orderBy('rjc_date')->get(); @endphp
            <x-badge variant="gray">{{ $cashins->count() }} transaksi</x-badge>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead
                    class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400 bg-gray-50 dark:bg-gray-800/50">
                    <tr>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3">Akun Kas</th>
                        <th class="px-4 py-3">Keterangan</th>
                        <th class="px-4 py-3 text-right">Nominal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($cashins as $cash)
                        <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800/40">
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                {{ Carbon::parse($cash->rjc_date)->format('d/m/Y') }}
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                {{ $cash->acc_id }}
                            </td>
                            <td class="px-4 py-3 text-gray-800 dark:text-gray-200">{{ $cash->rjc_desc }}</td>
                            <td
                                class="px-4 py-3 font-semibold text-right text-gray-800 dark:text-gray-200 whitespace-nowrap">
                                Rp {{ number_format($cash->rjc_nominal) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4"
                                class="px-4 py-10 text-sm text-center text-gray-400 dark:text-gray-600">
                                <svg class="w-8 h-8 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                Belum ada riwayat pembayaran
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                @if ($cashins->isNotEmpty())
                    <tfoot class="border-t border-gray-200 bg-gray-50 dark:bg-gray-800/50 dark:border-gray-700">
                        <tr>
                            <td colspan="3"
                                class="px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400">
                                Total Dibayar
                            </td>
                            <td class="px-4 py-3 text-sm font-bold text-right text-brand-green dark:text-brand-lime">
                                Rp {{ number_format($cashins->sum('rjc_nominal')) }}
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

</div>
