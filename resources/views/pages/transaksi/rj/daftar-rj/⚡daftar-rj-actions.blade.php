<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use App\Http\Traits\BPJS\AntrianTrait;
use Carbon\Carbon;
use App\Http\Traits\BPJS\VclaimTrait;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    // CATATAN: VclaimTrait & AntrianTrait TIDAK di-use di sini karena keduanya
    // mendefinisikan method yang sama (sendResponse, sendError, signature, dll).
    // Jika dipakai bersamaan dalam satu class → PHP fatal error "conflict".
    // Solusi: gunakan static call langsung VclaimTrait::method() & AntrianTrait::method()
    // — PHP mengizinkan static call ke trait tanpa harus use-nya di class.
    use EmrRJTrait, MasterPasienTrait, WithRenderVersioningTrait;

    public string $formMode = 'create';
    public bool $isFormLocked = false;

    public ?string $rjNo = null;
    public ?string $kronisNotice = null;
    public array $dataDaftarPoliRJ = ['passStatus' => 'O'];
    public array $dataPasien = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal', 'pasien', 'dokter'];

    public string $klaimId = 'UM';
    public array $klaimOptions = [['klaimId' => 'UM', 'klaimDesc' => 'UMUM'], ['klaimId' => 'JM', 'klaimDesc' => 'BPJS'], ['klaimId' => 'JR', 'klaimDesc' => 'JASA RAHARJA'], ['klaimId' => 'JML', 'klaimDesc' => 'Asuransi Lain'], ['klaimId' => 'KR', 'klaimDesc' => 'Kronis']];

    public string $kunjunganId = '1';
    public array $kunjunganOptions = [['kunjunganId' => '1', 'kunjunganDesc' => 'Rujukan FKTP'], ['kunjunganId' => '2', 'kunjunganDesc' => 'Rujukan Internal'], ['kunjunganId' => '3', 'kunjunganDesc' => 'Kontrol'], ['kunjunganId' => '4', 'kunjunganDesc' => 'Rujukan Antar RS']];

    public string $kontrol12 = '1';
    public array $kontrol12Options = [['kontrol12' => '1', 'kontrol12Desc' => 'Faskes Tingkat 1'], ['kontrol12' => '2', 'kontrol12Desc' => 'Faskes Tingkat 2 RS']];

    public string $internal12 = '1';
    public array $internal12Options = [['internal12' => '1', 'internal12Desc' => 'Faskes Tingkat 1'], ['internal12' => '2', 'internal12Desc' => 'Faskes Tingkat 2 RS']];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas(['modal', 'pasien', 'dokter']);
    }

    /* ===============================
     | OPEN CREATE
     =============================== */
    #[On('daftar-rj.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode = 'create';
        $this->resetValidation();

        $this->dataDaftarPoliRJ = $this->getDefaultRJTemplate();

        $now = Carbon::now();
        $this->dataDaftarPoliRJ['rjDate'] = $now->format('d/m/Y H:i:s');

        $findShift = DB::table('rstxn_shiftctls')
            ->select('shift')
            ->whereRaw('? BETWEEN shift_start AND shift_end', [$now->format('H:i:s')])
            ->first();

        $this->dataDaftarPoliRJ['shift'] = (string) ($findShift->shift ?? 3);

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'rj-actions');
        $this->dispatch('focus-cari-pasien');
    }

    /* ===============================
     | OPEN EDIT
     =============================== */
    #[On('daftar-rj.openEdit')]
    public function openEdit(string $rjNo): void
    {
        $this->resetForm();
        $this->formMode = 'edit';
        $this->resetValidation();

        $data = $this->findDataRJ($rjNo);

        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Jalan tidak ditemukan.');
            return;
        }

        if ($this->checkRJStatus($rjNo)) {
            $this->isFormLocked = true;
            $this->dispatch('toast', type: 'warning', message: 'Data Rawat Jalan ini sudah selesai dan tidak bisa diubah.');
        }

        $this->dataDaftarPoliRJ = $data;
        $this->dataPasien = $this->findDataMasterPasien($this->dataDaftarPoliRJ['regNo'] ?? '');
        $this->syncFromDataDaftarPoliRJ();

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'rj-actions');

        if (empty($this->dataDaftarPoliRJ['regNo'])) {
            $this->dispatch('focus-cari-pasien');
        }
    }

    /* ===============================
     | CLOSE MODAL
     =============================== */
    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'rj-actions');
    }

    /* ===============================
     | SAVE
     =============================== */
    public function save(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat menyimpan data.');
            return;
        }

        $this->setDataPrimer();
        $this->validateDataRJ();

        $rjNo = $this->dataDaftarPoliRJ['rjNo'] ?? null;

        if (!$rjNo) {
            $this->dispatch('toast', type: 'error', message: 'Nomor RJ tidak valid.');
            return;
        }

        try {
            // ============================================================
            // 1. QUERY isPoliSpesialis SEKALI — dipakai di step 2 & 3
            // ============================================================
            $isPoliSpesialis = DB::table('rsmst_polis')->where('poli_id', $this->dataDaftarPoliRJ['poliId'])->where('spesialis_status', '1')->exists();

            // ============================================================
            // 2. BPJS ANTRIAN — hanya untuk poli spesialis, bukan KR
            // ============================================================
            if ($this->dataDaftarPoliRJ['klaimId'] !== 'KR' && $isPoliSpesialis) {
                $this->pushDataAntrian($isPoliSpesialis);
            }

            // ============================================================
            // 3. BPJS SEP — cek dengan mempertimbangkan isPoliSpesialis
            // ============================================================
            $isBpjs = ($this->dataDaftarPoliRJ['klaimStatus'] ?? '') === 'BPJS' || ($this->dataDaftarPoliRJ['klaimId'] ?? '') === 'JM';

            if ($isBpjs) {
                $statusTambahPendaftaran = $this->dataDaftarPoliRJ['taskIdPelayanan']['tambahPendaftaran'] ?? '';
                $antrianSudahOk = $statusTambahPendaftaran == 200 || $statusTambahPendaftaran == 208;

                // SEP diblok HANYA jika poli spesialis DAN antrian belum berhasil
                if ($isPoliSpesialis && !$antrianSudahOk) {
                    $this->dispatch('toast', type: 'warning', message: 'Harap selesaikan tambah antrian BPJS terlebih dahulu sebelum membuat SEP.', title: 'Antrian Belum Terdaftar', position: 'top-right', duration: 6000);
                    // Lanjut simpan data tanpa SEP — tidak return di sini
                    // agar data RJ tetap tersimpan meski SEP belum ada
                } else {
                    $this->handleSepCreation();
                }
            }

            // ============================================================
            // 4. DB TRANSACTION
            // ============================================================
            $message = '';

            if ($this->formMode === 'create') {
                Cache::lock("lock:rstxn_rjhdrs:{$rjNo}", 15)->block(5, function () use ($rjNo, &$message) {
                    DB::transaction(function () use ($rjNo, &$message) {
                        DB::table('rstxn_rjhdrs')->insert($this->buildPayload($rjNo));
                        $this->updateJsonData($rjNo);
                        $message = 'Data Rawat Jalan berhasil disimpan.';
                    });
                });
            } else {
                DB::transaction(function () use ($rjNo, &$message) {
                    $this->lockRJRow($rjNo);
                    DB::table('rstxn_rjhdrs')->where('rj_no', $rjNo)->update($this->buildPayload($rjNo));
                    $this->updateJsonData($rjNo);
                    $message = 'Data Rawat Jalan berhasil diperbarui.';
                });
            }

            // ============================================================
            // 5. AFTER SAVE
            // ============================================================
            $this->afterSave($message);
        } catch (LockTimeoutException $e) {
            $this->dispatch('toast', type: 'error', message: 'Sistem sedang sibuk, silakan coba lagi.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (QueryException $e) {
            $this->handleDatabaseError($e);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan data: ' . $e->getMessage());
        }
    }

    /* ===============================
 | PUSH DATA ANTRIAN BPJS — REFACTORED
 | Menerima $isPoliSpesialis dari save() agar tidak query ulang
 =============================== */
    private function pushDataAntrian(bool $isPoliSpesialis = false): void
    {
        if ($this->dataDaftarPoliRJ['klaimId'] === 'KR') {
            return;
        }

        if (!$isPoliSpesialis) {
            return;
        }

        // Skip jika sudah berhasil sebelumnya
        $statusTambahPendaftaran = $this->dataDaftarPoliRJ['taskIdPelayanan']['tambahPendaftaran'] ?? '';
        if ($statusTambahPendaftaran == 200 || $statusTambahPendaftaran == 208) {
            return;
        }

        try {
            $dataAntrian = $this->prepareDataAntrian();
            $response = AntrianTrait::tambah_antrean($dataAntrian)->getOriginalContent();
            $code = $response['metadata']['code'] ?? '';
            $message = $response['metadata']['message'] ?? '';
            $isSuccess = $code == 200 || $code == 208;

            // Simpan kode ke taskIdPelayanan
            $this->dataDaftarPoliRJ['taskIdPelayanan']['tambahPendaftaran'] = $code;

            // Log selalu — sukses maupun gagal — untuk audit trail
            \Log::info('BPJS Antrian tambah_antrean', [
                'rjNo' => $this->dataDaftarPoliRJ['rjNo'] ?? null,
                'noBooking' => $dataAntrian['kodebooking'] ?? null,
                'poliId' => $dataAntrian['kodepoli'] ?? null,
                'code' => $code,
                'message' => $message,
                // Tampilkan errors validator jika ada (code 201)
                'errors' => $response['response'] ?? null,
                'payload_nik' => $dataAntrian['nik'] ?? null,
                'payload_nohp' => $dataAntrian['nohp'] ?? null,
            ]);

            $this->dispatch('toast', type: $isSuccess ? 'success' : 'error', message: 'Tambah Pendaftaran: ' . $message, title: $isSuccess ? 'Berhasil' : 'Gagal', position: 'top-right', duration: 5000);

            $this->updateTaskId1And2();
            $this->updateTaskId3();
        } catch (\Exception $e) {
            $this->handleAntrianError($e);
        }
    }

    /* ===============================
 | PREPARE DATA ANTRIAN — SANITASI NIK & NOHP
 =============================== */
    private function prepareDataAntrian(): array
    {
        $rjDate = Carbon::createFromFormat('d/m/Y H:i:s', $this->dataDaftarPoliRJ['rjDate']);

        $jadwalPraktek = $this->getJadwalPraktek($rjDate);
        $jamPraktek = substr($jadwalPraktek['mulai_praktek'], 0, 5) . '-' . substr($jadwalPraktek['selesai_praktek'], 0, 5);
        $estimasiDilayani = $rjDate->copy()->valueOf();

        $kuotaTotal = $jadwalPraktek['kuota'];
        $noAntrian = (int) $this->dataDaftarPoliRJ['noAntrian'];
        $sisaKuota = max(0, $kuotaTotal - $noAntrian);

        if ($sisaKuota <= 0) {
            $this->dispatch('toast', type: 'warning', message: "PERINGATAN: Kuota praktek telah habis! (Kuota: {$kuotaTotal}, No. Antrian: {$noAntrian})", title: 'Kuota Habis', position: 'top-end');
        }

        // ── Sanitasi NIK ─────────────────────────────────────────────
        // Strip semua non-digit, pastikan 16 karakter persis untuk JKN
        $rawNik = $this->dataPasien['pasien']['identitas']['nik'] ?? '';
        $nik = preg_replace('/\D/', '', $rawNik);

        // ── Sanitasi NoHP ────────────────────────────────────────────
        // Strip semua non-digit (+62 / 62 → 08, spasi, strip, titik)
        $rawNohp = $this->dataPasien['pasien']['kontak']['nomerTelponSelulerPasien'] ?? '';
        $nohp = preg_replace('/\D/', '', $rawNohp);
        if (str_starts_with($nohp, '62')) {
            $nohp = '0' . substr($nohp, 2);
        }
        // Fallback: jika kosong setelah sanitasi, kirim '0' agar tidak gagal required
        if (empty($nohp)) {
            $nohp = '0';
            \Log::warning('BPJS Antrian: nomor HP pasien kosong', [
                'regNo' => $this->dataDaftarPoliRJ['regNo'] ?? null,
                'rawNohp' => $rawNohp,
            ]);
        }

        dd([
            'kodebooking' => $this->dataDaftarPoliRJ['noBooking'],
            'jenispasien' => $this->getJenisPasien(),
            'nomorkartu' => $this->getNomorKartu(),
            'nik' => $nik,
            'nohp' => $nohp,
            'kodepoli' => $this->getKodePoli(),
            'namapoli' => $this->dataDaftarPoliRJ['poliDesc'],
            'pasienbaru' => (int) ($this->dataDaftarPoliRJ['passStatus'] === 'N'),
            'norm' => $this->dataDaftarPoliRJ['regNo'],
            'tanggalperiksa' => $rjDate->format('Y-m-d'),
            'kodedokter' => $this->getKodeDokter(),
            'namadokter' => $this->dataDaftarPoliRJ['drDesc'],
            'jampraktek' => $jamPraktek,
            'jeniskunjungan' => $this->getJenisKunjunganBPJS(),
            'nomorreferensi' => $this->dataDaftarPoliRJ['noReferensi'] ?? '',
            'nomorantrean' => $this->dataDaftarPoliRJ['noAntrian'],
            'angkaantrean' => (int) $this->dataDaftarPoliRJ['noAntrian'],
            'estimasidilayani' => $estimasiDilayani,
            'sisakuotajkn' => $sisaKuota,
            'kuotajkn' => $kuotaTotal,
            'sisakuotanonjkn' => $sisaKuota,
            'kuotanonjkn' => $kuotaTotal,
            'keterangan' => 'Peserta harap 30 menit lebih awal guna pencatatan administrasi.',
        ]);
        return [
            'kodebooking' => $this->dataDaftarPoliRJ['noBooking'],
            'jenispasien' => $this->getJenisPasien(),
            'nomorkartu' => $this->getNomorKartu(),
            'nik' => $nik,
            'nohp' => $nohp,
            'kodepoli' => $this->getKodePoli(),
            'namapoli' => $this->dataDaftarPoliRJ['poliDesc'],
            'pasienbaru' => (int) ($this->dataDaftarPoliRJ['passStatus'] === 'N'),
            'norm' => $this->dataDaftarPoliRJ['regNo'],
            'tanggalperiksa' => $rjDate->format('Y-m-d'),
            'kodedokter' => $this->getKodeDokter(),
            'namadokter' => $this->dataDaftarPoliRJ['drDesc'],
            'jampraktek' => $jamPraktek,
            'jeniskunjungan' => $this->getJenisKunjunganBPJS(),
            'nomorreferensi' => $this->dataDaftarPoliRJ['noReferensi'] ?? '',
            'nomorantrean' => $this->dataDaftarPoliRJ['noAntrian'],
            'angkaantrean' => (int) $this->dataDaftarPoliRJ['noAntrian'],
            'estimasidilayani' => $estimasiDilayani,
            'sisakuotajkn' => $sisaKuota,
            'kuotajkn' => $kuotaTotal,
            'sisakuotanonjkn' => $sisaKuota,
            'kuotanonjkn' => $kuotaTotal,
            'keterangan' => 'Peserta harap 30 menit lebih awal guna pencatatan administrasi.',
        ];
    }

    /* ===============================
     | BUILD PAYLOAD
     =============================== */
    private function buildPayload(string $rjNo): array
    {
        return [
            'rj_no' => $rjNo,
            'rj_date' => DB::raw("to_date('" . $this->dataDaftarPoliRJ['rjDate'] . "','dd/mm/yyyy hh24:mi:ss')"),
            'reg_no' => $this->dataDaftarPoliRJ['regNo'],
            'nobooking' => $this->dataDaftarPoliRJ['noBooking'],
            'no_antrian' => $this->dataDaftarPoliRJ['noAntrian'],
            'klaim_id' => $this->dataDaftarPoliRJ['klaimId'],
            'poli_id' => $this->dataDaftarPoliRJ['poliId'],
            'dr_id' => $this->dataDaftarPoliRJ['drId'],
            'shift' => $this->dataDaftarPoliRJ['shift'],
            'txn_status' => $this->dataDaftarPoliRJ['txnStatus'] ?? 'A',
            'rj_status' => $this->dataDaftarPoliRJ['rjStatus'] ?? 'A',
            'erm_status' => $this->dataDaftarPoliRJ['ermStatus'] ?? 'A',
            'pass_status' => $this->dataDaftarPoliRJ['passStatus'] ?? 'O',
            'cek_lab' => $this->dataDaftarPoliRJ['cekLab'] ?? '0',
            'sl_codefrom' => $this->dataDaftarPoliRJ['slCodeFrom'] ?? '02',
            'kunjungan_internal_status' => $this->dataDaftarPoliRJ['kunjunganInternalStatus'] ?? '0',
            'waktu_masuk_pelayanan' => DB::raw("to_date('" . $this->dataDaftarPoliRJ['rjDate'] . "','dd/mm/yyyy hh24:mi:ss')"),
            'vno_sep' => $this->dataDaftarPoliRJ['sep']['noSep'] ?? '',
        ];
    }

    /* ===============================
     | SET DATA PRIMER
     =============================== */
    private function setDataPrimer(): void
    {
        $data = &$this->dataDaftarPoliRJ;

        if (!empty($data['kunjunganId']) && $data['kunjunganId'] == 2) {
            $data['kunjunganInternalStatus'] = '1';
        }

        if (empty($data['noBooking'])) {
            $data['noBooking'] = Carbon::now()->format('YmdHis') . 'RSIM';
        }

        if (empty($data['rjNo'])) {
            $maxRjNo = DB::table('rstxn_rjhdrs')->max('rj_no');
            $data['rjNo'] = $maxRjNo ? $maxRjNo + 1 : 1;
        }

        if (empty($data['noAntrian'])) {
            if (!empty($data['klaimId']) && $data['klaimId'] !== 'KR') {
                if (!empty($data['rjDate']) && !empty($data['drId'])) {
                    $tglAntrian = Carbon::createFromFormat('d/m/Y H:i:s', $data['rjDate'])->format('dmY');
                    $noUrutAntrian = DB::table('rstxn_rjhdrs')
                        ->where('dr_id', $data['drId'])
                        ->where('klaim_id', '!=', 'KR')
                        ->whereRaw("to_char(rj_date, 'ddmmyyyy') = ?", [$tglAntrian])
                        ->count();
                    $data['noAntrian'] = $noUrutAntrian + 1;
                }
            } else {
                $data['noAntrian'] = 999;
            }
        }

        $data['taskIdPelayanan'] ??= [];

        if (empty($data['taskIdPelayanan']['taskId3']) && !empty($data['rjDate'])) {
            $data['taskIdPelayanan']['taskId3'] = $data['rjDate'];
        }
    }

    /* ===============================
     | VALIDATE DATA RJ
     =============================== */
    private function validateDataRJ(): array
    {
        $attributes = [
            'dataDaftarPoliRJ.regNo' => 'Nomor Registrasi Pasien',
            'dataDaftarPoliRJ.drId' => 'ID Dokter',
            'dataDaftarPoliRJ.drDesc' => 'Nama Dokter',
            'dataDaftarPoliRJ.poliId' => 'ID Poli',
            'dataDaftarPoliRJ.poliDesc' => 'Nama Poli',
            'dataDaftarPoliRJ.rjDate' => 'Tanggal Kunjungan',
            'dataDaftarPoliRJ.rjNo' => 'Nomor Kunjungan',
            'dataDaftarPoliRJ.shift' => 'Shift',
            'dataDaftarPoliRJ.noAntrian' => 'Nomor Antrian',
            'dataDaftarPoliRJ.noBooking' => 'Nomor Booking',
            'dataDaftarPoliRJ.slCodeFrom' => 'Kode Sumber',
            'dataDaftarPoliRJ.noReferensi' => 'Nomor Referensi',
            'dataDaftarPoliRJ.klaimId' => 'ID Klaim',
        ];

        $rules = [
            'dataDaftarPoliRJ.regNo' => 'bail|required|exists:rsmst_pasiens,reg_no',
            'dataDaftarPoliRJ.drId' => 'required|exists:rsmst_doctors,dr_id',
            'dataDaftarPoliRJ.drDesc' => 'required|string',
            'dataDaftarPoliRJ.poliId' => 'required|exists:rsmst_polis,poli_id',
            'dataDaftarPoliRJ.poliDesc' => 'required|string',
            'dataDaftarPoliRJ.kddrbpjs' => 'nullable|string',
            'dataDaftarPoliRJ.kdpolibpjs' => 'nullable|string',
            'dataDaftarPoliRJ.rjDate' => 'required|date_format:d/m/Y H:i:s',
            'dataDaftarPoliRJ.rjNo' => 'required|numeric',
            'dataDaftarPoliRJ.shift' => 'required|in:1,2,3',
            'dataDaftarPoliRJ.noAntrian' => 'required|numeric|min:1|max:999',
            'dataDaftarPoliRJ.noBooking' => 'required|string',
            'dataDaftarPoliRJ.slCodeFrom' => 'required|in:01,02',
            'dataDaftarPoliRJ.passStatus' => 'nullable|in:N,O',
            'dataDaftarPoliRJ.rjStatus' => 'required|in:A,L,I,F',
            'dataDaftarPoliRJ.txnStatus' => 'required|in:A,L,H',
            'dataDaftarPoliRJ.ermStatus' => 'required|in:A,L',
            'dataDaftarPoliRJ.cekLab' => 'required|in:0,1',
            'dataDaftarPoliRJ.kunjunganInternalStatus' => 'required|in:0,1',
            'dataDaftarPoliRJ.noReferensi' => 'nullable|string|min:3|max:19',
            'dataDaftarPoliRJ.klaimId' => 'required|exists:rsmst_klaimtypes,klaim_id',
        ];

        if (($this->dataDaftarPoliRJ['klaimStatus'] ?? '') === 'BPJS' || ($this->dataDaftarPoliRJ['klaimId'] ?? '') === 'JM') {
            $rules['dataDaftarPoliRJ.noReferensi'] = 'bail|required|string|min:3|max:19';
        }

        if (($this->dataDaftarPoliRJ['klaimStatus'] ?? '') === 'KRONIS') {
            $rules['dataDaftarPoliRJ.noAntrian'] = 'required|numeric';
        }

        return $this->validate($rules, [], $attributes);
    }

    /* ===============================
     | UPDATE JSON DATA
     =============================== */
    private function updateJsonData(string $rjNo): void
    {
        $allowedFields = ['regNo', 'drId', 'drDesc', 'poliId', 'poliDesc', 'kddrbpjs', 'kdpolibpjs', 'klaimId', 'kunjunganId', 'rjDate', 'shift', 'noAntrian', 'noBooking', 'slCodeFrom', 'passStatus', 'rjStatus', 'txnStatus', 'ermStatus', 'cekLab', 'kunjunganInternalStatus', 'noReferensi', 'postInap', 'internal12', 'internal12Desc', 'kontrol12', 'kontrol12Desc', 'taskIdPelayanan', 'sep', 'klaimStatus'];

        if ($this->formMode === 'create') {
            $this->updateJsonRJ($rjNo, $this->dataDaftarPoliRJ);
            return;
        }

        $existingData = $this->findDataRJ($rjNo);

        if (empty($existingData)) {
            throw new \RuntimeException('Data RJ tidak ditemukan, simpan dibatalkan.');
        }

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $this->dataDaftarPoliRJ)) {
                $existingData[$field] = $this->dataDaftarPoliRJ[$field];
            }
        }

        $this->updateJsonRJ($rjNo, $existingData);
    }

    /* ===============================
     | AFTER SAVE
     =============================== */
    private function afterSave(string $message): void
    {
        if ($this->formMode === 'edit') {
            $this->syncFromDataDaftarPoliRJ();
        }

        $this->dispatch('toast', type: 'success', message: $message);
        $this->closeModal();
        $this->dispatch('refresh-after-rj.saved');
    }

    /* ===============================
     | DB ERROR HANDLER
     =============================== */
    private function handleDatabaseError(QueryException $e): void
    {
        $errorCode = $e->errorInfo[1] ?? 0;

        $message = match ($errorCode) {
            1 => 'Duplikasi data, record sudah ada.',
            1400 => 'Field wajib tidak boleh kosong.',
            2291 => 'Data referensi tidak valid.',
            2292 => 'Data sedang digunakan, tidak dapat diubah.',
            8177 => 'Kesalahan constraint, periksa kembali data.',
            default => 'Kesalahan database: ' . $e->getMessage(),
        };

        $this->dispatch('toast', type: 'error', message: $message);

        \Log::error('Database error in save: ' . $e->getMessage(), [
            'rjNo' => $this->dataDaftarPoliRJ['rjNo'] ?? null,
            'formMode' => $this->formMode,
        ]);
    }

    private function getJadwalPraktek(Carbon $rjDate): array
    {
        $dayMapping = ['Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3, 'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6, 'Sunday' => 7];
        $dayId = $dayMapping[$rjDate->format('l')] ?? 8;

        $jadwal = DB::table('scmst_scpolis')->select('scmst_scpolis.dr_id', DB::raw("nvl(mulai_praktek, '07:00:00') as mulai_praktek"), DB::raw("nvl(selesai_praktek, '13:00:00') as selesai_praktek"), DB::raw('nvl(kuota, 30) as kuota'))->where('dr_id', $this->dataDaftarPoliRJ['drId'])->where('poli_id', $this->dataDaftarPoliRJ['poliId'])->where('day_id', $dayId)->where('sc_poli_status_', 1)->orderBy('no_urut')->first();

        return $jadwal ? ['mulai_praktek' => $jadwal->mulai_praktek, 'selesai_praktek' => $jadwal->selesai_praktek, 'kuota' => (int) $jadwal->kuota] : ['mulai_praktek' => '07:00:00', 'selesai_praktek' => '13:00:00', 'kuota' => 30];
    }

    private function getJenisPasien(): string
    {
        return $this->dataDaftarPoliRJ['klaimId'] === 'JM' ? 'JKN' : 'NON JKN';
    }

    private function getNomorKartu(): string
    {
        return $this->dataDaftarPoliRJ['klaimId'] === 'JM' ? $this->dataPasien['pasien']['identitas']['idbpjs'] ?? '' : '';
    }

    private function getKodePoli(): string
    {
        return $this->dataDaftarPoliRJ['kdpolibpjs'] ?? $this->dataDaftarPoliRJ['poliId'];
    }

    private function getKodeDokter(): string
    {
        return $this->dataDaftarPoliRJ['kddrbpjs'] ?? $this->dataDaftarPoliRJ['drId'];
    }

    private function getJenisKunjunganBPJS(): string
    {
        return match ($this->dataDaftarPoliRJ['kunjunganId'] ?? '1') {
            '2' => '2',
            '3' => '3',
            '4' => '4',
            default => '1',
        };
    }

    private function updateTaskId3(): void
    {
        if (empty($this->dataDaftarPoliRJ['taskIdPelayanan']['taskId3'])) {
            $this->dataDaftarPoliRJ['taskIdPelayanan']['taskId3'] = $this->dataDaftarPoliRJ['rjDate'];
        }

        $status = $this->dataDaftarPoliRJ['taskIdPelayanan']['taskId3Status'] ?? '';
        if ($status == 200 || $status == 208) {
            $this->dispatch('toast', type: 'info', message: 'TaskId 3 sudah pernah dikirim ke BPJS.');
            return;
        }

        $waktu = Carbon::createFromFormat('d/m/Y H:i:s', $this->dataDaftarPoliRJ['taskIdPelayanan']['taskId3'], config('app.timezone'))->timestamp * 1000;
        $code3 = $this->pushDataTaskId($this->dataDaftarPoliRJ['noBooking'], 3, $waktu);
        $this->dataDaftarPoliRJ['taskIdPelayanan']['taskId3Status'] = $code3;
    }

    private function handleSepCreation(): void
    {
        $isBpjs = ($this->dataDaftarPoliRJ['klaimStatus'] ?? '') === 'BPJS' || ($this->dataDaftarPoliRJ['klaimId'] ?? '') === 'JM';
        if (!$isBpjs) {
            return;
        }

        $sudahAdaSEP = !empty($this->dataDaftarPoliRJ['sep']['noSep']);

        if (!$sudahAdaSEP && !empty($this->dataDaftarPoliRJ['sep']['reqSep'])) {
            $this->pushInsertSEP($this->dataDaftarPoliRJ['sep']['reqSep']);
        } elseif ($sudahAdaSEP && !empty($this->dataDaftarPoliRJ['sep']['reqSep'])) {
            $this->pushUpdateSEP($this->dataDaftarPoliRJ['sep']['reqSep']);
        }
    }

    private function updateTaskId1And2(): void
    {
        if (empty($this->dataPasien['pasien']['regDate'])) {
            return;
        }

        try {
            $rjFormatted = Carbon::createFromFormat('d/m/Y H:i:s', $this->dataDaftarPoliRJ['rjDate'])->format('Ymd');
            $regFormatted = Carbon::createFromFormat('d/m/Y H:i:s', $this->dataPasien['pasien']['regDate'])->format('Ymd');

            if ($rjFormatted === $regFormatted) {
                $this->dataDaftarPoliRJ['taskIdPelayanan']['taskId1'] = $this->dataPasien['pasien']['regDate'];
                $waktu1 = Carbon::createFromFormat('d/m/Y H:i:s', $this->dataDaftarPoliRJ['taskIdPelayanan']['taskId1'], config('app.timezone'))->timestamp * 1000;
                $code1 = $this->pushDataTaskId($this->dataDaftarPoliRJ['noBooking'], 1, $waktu1);
                $this->dataDaftarPoliRJ['taskIdPelayanan']['taskId1Status'] = $code1;

                if (!empty($this->dataPasien['pasien']['regDateStore'])) {
                    $this->dataDaftarPoliRJ['taskIdPelayanan']['taskId2'] = $this->dataPasien['pasien']['regDateStore'];
                    $waktu2 = Carbon::createFromFormat('d/m/Y H:i:s', $this->dataDaftarPoliRJ['taskIdPelayanan']['taskId2'], config('app.timezone'))->timestamp * 1000;
                    $code2 = $this->pushDataTaskId($this->dataDaftarPoliRJ['noBooking'], 2, $waktu2);
                    $this->dataDaftarPoliRJ['taskIdPelayanan']['taskId2Status'] = $code2;
                }
            }
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'warning', message: 'Gagal update Task ID: ' . $e->getMessage(), title: 'Warning');
        }
    }

    private function pushDataTaskId($noBooking, $taskId, $time): int|string
    {
        // Static call — tidak bisa $this-> karena trait conflict
        $response = AntrianTrait::update_antrean($noBooking, $taskId, $time, '')->getOriginalContent();
        $code = $response['metadata']['code'] ?? '';
        $message = $response['metadata']['message'] ?? '';
        $isSuccess = $code == 200 || $code == 208;

        $this->dispatch('toast', type: $isSuccess ? 'success' : 'error', message: "Task Id {$taskId} {$code} {$message}", title: $isSuccess ? 'Berhasil' : 'Gagal');

        return $code;
    }

    private function handleAntrianError(\Exception $e): void
    {
        $this->dispatch('toast', type: 'error', message: 'Gagal push antrian BPJS: ' . $e->getMessage());
    }

    /* ===============================
     | PUSH INSERT SEP
     | Static call VclaimTrait:: — tidak bisa use bersamaan dengan AntrianTrait (method conflict)
     =============================== */
    private function pushInsertSEP(array $reqSep): void
    {
        if (empty($reqSep)) {
            $this->dispatch('toast', type: 'warning', message: 'Data request SEP kosong, tidak dapat membuat SEP.');
            return;
        }

        try {
            // Static call — tidak bisa $this-> karena VclaimTrait & AntrianTrait conflict
            $response = VclaimTrait::sep_insert($reqSep)->getOriginalContent();
            $code = $response['metadata']['code'] ?? 500;

            if ($code == 200) {
                $this->handleInsertSepSuccess($response, $reqSep);
            } else {
                $this->handleInsertSepError($response);
            }
        } catch (\Exception $e) {
            $this->handleInsertSepException($e);
        }
    }

    private function handleInsertSepSuccess(array $response, array $reqSep): void
    {
        $sepData = $response['response']['sep'] ?? null;

        if (!$sepData) {
            $this->dispatch('toast', type: 'error', message: 'Response SEP tidak valid: data SEP tidak ditemukan.');
            return;
        }

        $this->dataDaftarPoliRJ['sep'] = [
            'noSep' => $sepData['noSep'] ?? '',
            'reqSep' => $reqSep,
            'resSep' => $sepData,
            'created_at' => Carbon::now()->format('d/m/Y H:i:s'),
        ];

        if (isset($reqSep['request']['t_sep']['rujukan']['noRujukan'])) {
            $this->dataDaftarPoliRJ['noReferensi'] = $reqSep['request']['t_sep']['rujukan']['noRujukan'];
        }

        $this->dispatch('toast', type: 'success', message: "SEP berhasil dibuat: {$sepData['noSep']}");
        $this->incrementVersion('modal');
    }

    private function handleInsertSepError(array $response): void
    {
        $code = $response['metadata']['code'] ?? 500;
        $message = $response['metadata']['message'] ?? 'Gagal membuat SEP';
        $this->dispatch('toast', type: 'error', message: "Gagal membuat SEP: {$message} ({$code})");
    }

    private function handleInsertSepException(\Exception $e): void
    {
        $this->dispatch('toast', type: 'error', message: 'Terjadi kesalahan saat menghubungi server BPJS: ' . $e->getMessage());
    }

    /* ===============================
     | PUSH UPDATE SEP
     | Static call VclaimTrait:: — tidak bisa use bersamaan dengan AntrianTrait (method conflict)
     =============================== */
    private function pushUpdateSEP(array $reqSepUpdate): void
    {
        if (empty($reqSepUpdate)) {
            return;
        }

        try {
            $reqUpdate = $this->formatUpdateSepRequest($reqSepUpdate);
            // Static call — tidak bisa $this-> karena VclaimTrait & AntrianTrait conflict
            $response = VclaimTrait::sep_update($reqUpdate)->getOriginalContent();
            $code = $response['metadata']['code'] ?? 500;

            if ($code == 200) {
                $this->handleUpdateSepSuccess($response);
            } else {
                $this->handleUpdateSepError($response);
            }
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal update SEP: ' . $e->getMessage());
        }
    }

    private function formatUpdateSepRequest(array $reqSepUpdate): array
    {
        $noSep = $reqSepUpdate['request']['t_sep']['noSep'] ?? ($this->dataDaftarPoliRJ['sep']['noSep'] ?? '');

        if (empty($noSep)) {
            throw new \RuntimeException('Nomor SEP tidak ditemukan untuk update.');
        }

        $t = $reqSepUpdate['request']['t_sep'];

        return [
            'request' => [
                't_sep' => [
                    'noSep' => $noSep,
                    'klsRawat' => [
                        'klsRawatHak' => $t['klsRawat']['klsRawatHak'] ?? '',
                        'klsRawatNaik' => $t['klsRawat']['klsRawatNaik'] ?? '',
                        'pembiayaan' => $t['klsRawat']['pembiayaan'] ?? '',
                        'penanggungJawab' => $t['klsRawat']['penanggungJawab'] ?? '',
                    ],
                    'noMR' => $t['noMR'] ?? '',
                    'catatan' => $t['catatan'] ?? '',
                    'diagAwal' => $t['diagAwal'] ?? '',
                    'poli' => ['tujuan' => $t['poli']['tujuan'] ?? '', 'eksekutif' => $t['poli']['eksekutif'] ?? '0'],
                    'cob' => ['cob' => $t['cob']['cob'] ?? '0'],
                    'katarak' => ['katarak' => $t['katarak']['katarak'] ?? '0'],
                    'jaminan' => [
                        'lakaLantas' => $t['jaminan']['lakaLantas'] ?? '0',
                        'penjamin' => [
                            'tglKejadian' => $t['jaminan']['penjamin']['tglKejadian'] ?? '',
                            'keterangan' => $t['jaminan']['penjamin']['keterangan'] ?? '',
                            'suplesi' => [
                                'suplesi' => $t['jaminan']['penjamin']['suplesi']['suplesi'] ?? '0',
                                'noSepSuplesi' => $t['jaminan']['penjamin']['suplesi']['noSepSuplesi'] ?? '',
                                'lokasiLaka' => [
                                    'kdPropinsi' => $t['jaminan']['penjamin']['suplesi']['lokasiLaka']['kdPropinsi'] ?? '',
                                    'kdKabupaten' => $t['jaminan']['penjamin']['suplesi']['lokasiLaka']['kdKabupaten'] ?? '',
                                    'kdKecamatan' => $t['jaminan']['penjamin']['suplesi']['lokasiLaka']['kdKecamatan'] ?? '',
                                ],
                            ],
                        ],
                    ],
                    'dpjpLayan' => $t['dpjpLayan'] ?? '',
                    'noTelp' => $t['noTelp'] ?? '',
                    'user' => 'siRUS',
                ],
            ],
        ];
    }

    private function handleUpdateSepSuccess(array $response): void
    {
        $code = $response['metadata']['code'] ?? 200;
        $message = $response['metadata']['message'] ?? 'SEP berhasil diupdate';
        $this->dispatch('toast', type: 'success', message: "Update SEP ({$code}): {$message}");
        $this->dataDaftarPoliRJ['sep']['updated_at'] = Carbon::now()->format('d/m/Y H:i:s');
    }

    private function handleUpdateSepError(array $response): void
    {
        $code = $response['metadata']['code'] ?? 500;
        $message = $response['metadata']['message'] ?? 'Gagal update SEP';
        $this->dispatch('toast', type: 'error', message: "Update SEP gagal ({$code}): {$message}");
    }

    /* ===============================
     | LOV HANDLERS
     =============================== */
    #[On('lov.selected.rjFormPasien')]
    public function rjFormPasien(string $target, array $payload): void
    {
        $this->dataDaftarPoliRJ['regNo'] = $payload['reg_no'] ?? '';
        $this->dataDaftarPoliRJ['regName'] = $payload['reg_name'] ?? '';
        $this->dataPasien = $this->findDataMasterPasien($this->dataDaftarPoliRJ['regNo'] ?? '');
        $this->incrementVersion('pasien');
        $this->incrementVersion('modal');
        $this->dispatch('focus-cari-dokter');
    }

    #[On('lov.selected.rjFormDokter')]
    public function rjFormDokter(string $target, array $payload): void
    {
        $this->dataDaftarPoliRJ['drId']      = $payload['dr_id'] ?? '';
        $this->dataDaftarPoliRJ['drDesc']    = $payload['dr_name'] ?? '';
        $this->dataDaftarPoliRJ['poliId']    = $payload['poli_id'] ?? '';
        $this->dataDaftarPoliRJ['poliDesc']  = $payload['poli_desc'] ?? '';
        $this->dataDaftarPoliRJ['kddrbpjs']  = $payload['kd_dr_bpjs'] ?? '';
        $this->dataDaftarPoliRJ['kdpolibpjs'] = $payload['kd_poli_bpjs'] ?? '';
        $this->incrementVersion('dokter');
        $this->incrementVersion('modal');
        $this->dispatch('focus-klaim-options');
    }

    /* ===============================
     | SEP HANDLERS
     =============================== */
    #[On('sep-generated')]
    public function handleSepGenerated($reqSep): void
    {
        $this->dataDaftarPoliRJ['sep']['reqSep'] = $reqSep;
        $this->dataDaftarPoliRJ['noReferensi'] = $reqSep['request']['t_sep']['rujukan']['noRujukan'] ?? ($this->dataDaftarPoliRJ['noReferensi'] ?? null);
        $this->incrementVersion('modal');
        $this->dispatch('toast', type: 'success', message: 'Request SEP berhasil diterima');
    }

    public function openVclaimModal(): void
    {
        if (empty($this->dataDaftarPoliRJ['regNo'])) {
            $this->dispatch('toast', type: 'error', message: 'Silakan pilih pasien terlebih dahulu.');
            return;
        }

        $isBpjs = ($this->dataDaftarPoliRJ['klaimStatus'] ?? '') === 'BPJS' || ($this->dataDaftarPoliRJ['klaimId'] ?? '') === 'JM';

        if (!$isBpjs) {
            $this->dispatch('toast', type: 'error', message: 'Fitur SEP hanya untuk pasien BPJS (Jenis Klaim JM).');
            return;
        }

        if (empty($this->dataDaftarPoliRJ['drId'])) {
            $this->dispatch('toast', type: 'error', message: 'Silakan pilih dokter/poli terlebih dahulu.');
            return;
        }

        $this->dispatch('open-vclaim-modal', rjNo: $this->rjNo, regNo: $this->dataDaftarPoliRJ['regNo'], drId: $this->dataDaftarPoliRJ['drId'], drDesc: $this->dataDaftarPoliRJ['drDesc'], poliId: $this->dataDaftarPoliRJ['poliId'], poliDesc: $this->dataDaftarPoliRJ['poliDesc'], kdpolibpjs: $this->dataDaftarPoliRJ['kdpolibpjs'] ?? null, kunjunganId: $this->kunjunganId, kontrol12: $this->kontrol12, internal12: $this->internal12, postInap: $this->dataDaftarPoliRJ['postInap'] ?? false, noReferensi: $this->dataDaftarPoliRJ['noReferensi'] ?? null, sepData: $this->dataDaftarPoliRJ['sep'] ?? []);
    }

    public function cetakSEP(): void
    {
        if (empty($this->dataDaftarPoliRJ['sep']['noSep'])) {
            $this->dispatch('toast', type: 'error', message: 'Tidak ada SEP untuk dicetak.');
            return;
        }
        $this->dispatch('cetak-sep-rj.open', noSep: $this->dataDaftarPoliRJ['sep']['noSep']);
    }

    /* ===============================
     | UPDATED HOOKS
     =============================== */
    public function updated($name, $value): void
    {
        if (in_array($name, ['dataDaftarPoliRJ.regNo'])) {
            $this->incrementVersion('pasien');
            $this->incrementVersion('modal');
        }

        if ($name === 'dataDaftarPoliRJ.drId') {
            $this->incrementVersion('dokter');
            $this->incrementVersion('modal');
        }

        if (in_array($name, ['klaimId', 'kunjunganId', 'kontrol12', 'internal12'])) {
            $this->incrementVersion('modal');
        }

        if ($name === 'klaimId') {
            $this->klaimId = $value;
            $this->dataDaftarPoliRJ['klaimId'] = $value;
            $this->dataDaftarPoliRJ['klaimStatus'] = DB::table('rsmst_klaimtypes')->where('klaim_id', $value)->value('klaim_status') ?? 'UMUM';
            $this->kunjunganId = '1';
            $this->dataDaftarPoliRJ['kunjunganId'] = '1';
            $this->resetKontrolInternal();
        }

        if ($name === 'kunjunganId') {
            $this->kunjunganId = $value;
            $this->dataDaftarPoliRJ['kunjunganId'] = $value;
            $this->dataDaftarPoliRJ['postInap'] = false;
            $this->resetKontrolInternal();
            $this->dispatch('focus-no-referensi');
        }

        if ($name === 'kontrol12') {
            $this->kontrol12 = $value;
            $this->dataDaftarPoliRJ['kontrol12'] = $value;
            $this->dataDaftarPoliRJ['kontrol12Desc'] = collect($this->kontrol12Options)->first(fn($o) => $o['kontrol12'] === $value)['kontrol12Desc'] ?? '-';
        }

        if ($name === 'internal12') {
            $this->internal12 = $value;
            $this->dataDaftarPoliRJ['internal12'] = $value;
            $this->dataDaftarPoliRJ['internal12Desc'] = collect($this->internal12Options)->first(fn($o) => $o['internal12'] === $value)['internal12Desc'] ?? '-';
        }
    }

    /* ===============================
     | HELPERS
     =============================== */
    private function resetKontrolInternal(): void
    {
        $this->kontrol12 = '1';
        $this->internal12 = '1';
        $this->dataDaftarPoliRJ['kontrol12'] = '1';
        $this->dataDaftarPoliRJ['internal12'] = '1';
        $this->dataDaftarPoliRJ['kontrol12Desc'] = collect($this->kontrol12Options)->first(fn($o) => $o['kontrol12'] === '1')['kontrol12Desc'] ?? '-';
        $this->dataDaftarPoliRJ['internal12Desc'] = collect($this->internal12Options)->first(fn($o) => $o['internal12'] === '1')['internal12Desc'] ?? '-';
    }

    private function syncFromDataDaftarPoliRJ(): void
    {
        $this->klaimId = $this->dataDaftarPoliRJ['klaimId'] ?? 'UM';
        $this->kunjunganId = $this->dataDaftarPoliRJ['kunjunganId'] ?? '1';
        $this->kontrol12 = $this->dataDaftarPoliRJ['kontrol12'] ?? '1';
        $this->internal12 = $this->dataDaftarPoliRJ['internal12'] ?? '1';

        $this->dataDaftarPoliRJ['kontrol12Desc'] = collect($this->kontrol12Options)->first(fn($o) => $o['kontrol12'] === $this->kontrol12)['kontrol12Desc'] ?? '-';
        $this->dataDaftarPoliRJ['internal12Desc'] = collect($this->internal12Options)->first(fn($o) => $o['internal12'] === $this->internal12)['internal12Desc'] ?? '-';
    }

    protected function resetForm(): void
    {
        $this->reset(['rjNo', 'dataDaftarPoliRJ']);
        $this->resetVersion();
        $this->klaimId = 'UM';
        $this->kunjunganId = '1';
        $this->kontrol12 = '1';
        $this->internal12 = '1';
        $this->formMode = 'create';

        $this->dataDaftarPoliRJ['rjDate'] = Carbon::now()->format('d/m/Y H:i:s');
        $this->dataDaftarPoliRJ['regNo'] = '';
        $this->dataDaftarPoliRJ['regName'] = '';
        $this->dataDaftarPoliRJ['drId'] = null;
        $this->dataDaftarPoliRJ['drDesc'] = '';
        $this->dataDaftarPoliRJ['poliId'] = null;
        $this->dataDaftarPoliRJ['poliDesc'] = '';
        $this->dataDaftarPoliRJ['passStatus'] = 'O';
    }
};
?>
{{-- Blade template tidak ada perubahan --}}
<div>
    <x-modal name="rj-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal', [$formMode, $rjNo ?? 'new']) }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div
                                class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                                <img src="{{ asset('images/Logogram black solid.png') }}" alt="RSI Madinah"
                                    class="block w-6 h-6 dark:hidden" />
                                <img src="{{ asset('images/Logogram white solid.png') }}" alt="RSI Madinah"
                                    class="hidden w-6 h-6 dark:block" />
                            </div>
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $formMode === 'edit' ? 'Ubah Data Rawat Jalan' : 'Tambah Data Rawat Jalan' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Kelola data pendaftaran dan
                                    pelayanan pasien rawat jalan.</p>
                            </div>
                        </div>
                        <div class="flex gap-2 mt-3">
                            <x-badge
                                :variant="$formMode === 'edit' ? 'warning' : 'success'">{{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Tambah' }}</x-badge>
                            @if ($isFormLocked)
                                <x-badge variant="danger">Read Only</x-badge>
                            @endif
                        </div>
                    </div>
                    <div class="flex gap-4">
                        <div class="flex-1">
                            <x-input-label value="Tanggal RJ" />
                            <x-text-input wire:model.live="dataDaftarPoliRJ.rjDate" class="block w-full"
                                :error="$errors->has('dataDaftarPoliRJ.rjDate')" :disabled="$isFormLocked" />
                            <x-input-error :messages="$errors->get('dataDaftarPoliRJ.rjDate')" class="mt-1" />
                        </div>
                        <div class="w-36">
                            <x-input-label value="Shift" />
                            <x-select-input wire:model.live="dataDaftarPoliRJ.shift" class="w-full mt-1 sm:w-36"
                                :error="$errors->has('dataDaftarPoliRJ.shift')" :disabled="$isFormLocked">
                                <option value="">-- Pilih Shift --</option>
                                <option value="1">Shift 1</option>
                                <option value="2">Shift 2</option>
                                <option value="3">Shift 3</option>
                            </x-select-input>
                            <x-input-error :messages="$errors->get('dataDaftarPoliRJ.shift')" class="mt-1" />
                        </div>
                    </div>
                    <x-secondary-button type="button" wire:click="closeModal" class="!p-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-secondary-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20" x-data
                x-on:focus-cari-pasien.window="$nextTick(() => setTimeout(() => $refs.lovPasien?.querySelector('input')?.focus(), 150))"
                x-on:focus-cari-dokter.window="$nextTick(() => setTimeout(() => $refs.lovDokter?.querySelector('input')?.focus(), 150))"
                x-on:focus-klaim-options.window="$nextTick(() => setTimeout(() => $refs.klaimOptions?.querySelector('input[type=radio]')?.focus(), 150))"
                x-on:focus-no-referensi.window="$nextTick(() => setTimeout(() => $refs.inputNoReferensi?.querySelector('input')?.focus(), 150))">
                <div class="max-w-full mx-auto">
                    <div class="p-1 space-y-1">
                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">

                            {{-- KOLOM KIRI --}}
                            <div
                                class="p-6 space-y-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                                <div>
                                    <div class="mt-2">
                                        <x-toggle wire:model.live="dataDaftarPoliRJ.passStatus" trueValue="N"
                                            falseValue="O" label="Pasien Baru" :disabled="$isFormLocked" />
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Jika tidak dicentang maka
                                        dianggap Pasien Lama.</p>
                                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.passStatus')" class="mt-1" />
                                </div>
                                <div class="mt-2" x-ref="lovPasien">
                                    <livewire:lov.pasien.lov-pasien target="rjFormPasien" :initialRegNo="$dataDaftarPoliRJ['regNo'] ?? ''"
                                        :disabled="$isFormLocked" />
                                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.regNo')" class="mt-1" />
                                </div>
                                <div class="mt-2" x-ref="lovDokter">
                                    <livewire:lov.dokter.lov-dokter label="Cari Dokter - Poli" target="rjFormDokter"
                                        :initialDrId="$dataDaftarPoliRJ['drId'] ?? null" :disabled="$isFormLocked" />
                                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.drId')" class="mt-1" />
                                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.drDesc')" class="mt-1" />
                                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.poliId')" class="mt-1" />
                                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.poliDesc')" class="mt-1" />
                                </div>
                                <div x-ref="klaimOptions">
                                    <x-input-label value="Jenis Klaim" />
                                    <div class="grid grid-cols-5 gap-2 mt-2">
                                        @foreach ($klaimOptions ?? [] as $klaim)
                                            <x-radio-button :label="$klaim['klaimDesc']" :value="(string) $klaim['klaimId']" name="klaimId"
                                                wire:model.live="klaimId" :disabled="$isFormLocked" />
                                        @endforeach
                                    </div>
                                    <x-input-error :messages="$errors->get('dataDaftarPoliRJ.klaimId')" class="mt-1" />
                                </div>
                            </div>

                            {{-- KOLOM KANAN --}}
                            <div
                                class="p-6 space-y-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                                @if (($dataDaftarPoliRJ['klaimStatus'] ?? '') === 'BPJS' || ($dataDaftarPoliRJ['klaimId'] ?? '') === 'JM')
                                    <div>
                                        <x-input-label value="Jenis Kunjungan" />
                                        <div class="grid grid-cols-4 gap-2">
                                            @foreach ($kunjunganOptions ?? [] as $kunjungan)
                                                <x-radio-button :label="$kunjungan['kunjunganDesc']" :value="$kunjungan['kunjunganId']" name="kunjunganId"
                                                    wire:model.live="kunjunganId" :disabled="$isFormLocked" />
                                            @endforeach
                                        </div>
                                        <div class="mt-2">
                                            @if (($dataDaftarPoliRJ['kunjunganId'] ?? '') === '3')
                                                <x-toggle wire:model.live="dataDaftarPoliRJ.postInap" trueValue="1"
                                                    falseValue="0" label="Post Inap" :disabled="$isFormLocked" />
                                            @endif
                                            <div class="grid grid-cols-2 gap-2 mt-2">
                                                @if ($kunjunganId === '2')
                                                    @foreach ($internal12Options ?? [] as $internal)
                                                        <x-radio-button :label="__($internal['internal12Desc'])"
                                                            value="{{ $internal['internal12'] }}" name="internal12"
                                                            wire:model.live="internal12" :disabled="$isFormLocked" />
                                                    @endforeach
                                                @endif
                                                @if ($kunjunganId === '3')
                                                    @foreach ($kontrol12Options ?? [] as $kontrol)
                                                        <x-radio-button :label="__($kontrol['kontrol12Desc'])"
                                                            value="{{ $kontrol['kontrol12'] }}" name="kontrol12"
                                                            wire:model.live="kontrol12" :disabled="$isFormLocked" />
                                                    @endforeach
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="space-y-3">
                                        <div class="grid" x-ref="inputNoReferensi">
                                            <x-input-label value="No Referensi" />
                                            <x-text-input wire:model.live="dataDaftarPoliRJ.noReferensi"
                                                :disabled="$isFormLocked" />
                                            <x-input-error :messages="$errors->get('dataDaftarPoliRJ.noReferensi')" />
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">di isi dgn : (No
                                                Rujukan untuk FKTP/FKTL) (SKDP untuk Kontrol/Rujukan Internal)</p>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-2 mt-2">
                                            <x-secondary-button type="button" wire:click="openVclaimModal"
                                                class="gap-2 text-xs">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                                Kelola SEP BPJS
                                            </x-secondary-button>
                                            @if (!empty($dataDaftarPoliRJ['sep']['noSep']))
                                                <div
                                                    class="flex items-center gap-2 px-3 py-1 text-xs text-green-700 bg-green-100 rounded-full dark:bg-green-900/30 dark:text-green-300">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    SEP: {{ $dataDaftarPoliRJ['sep']['noSep'] }}
                                                </div>
                                                <x-secondary-button type="button" wire:click="cetakSEP"
                                                    class="gap-2 text-xs" title="Cetak SEP">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                    </svg>
                                                </x-secondary-button>
                                            @endif
                                        </div>
                                        @if (!empty($dataDaftarPoliRJ['sep']['noSep']))
                                            <div
                                                class="flex items-center gap-2 px-3 py-2 mt-1 text-sm border border-blue-200 rounded-lg bg-blue-50 dark:bg-blue-900/20 dark:border-blue-800">
                                                <svg class="w-5 h-5 text-blue-500" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <div class="flex-1">
                                                    <span
                                                        class="text-xs font-medium text-blue-700 dark:text-blue-300">SEP
                                                        Aktif:</span>
                                                    <span
                                                        class="ml-2 font-mono text-sm font-semibold text-blue-800 dark:text-blue-200">{{ $dataDaftarPoliRJ['sep']['noSep'] }}</span>
                                                </div>
                                                <span
                                                    class="text-xs text-blue-600 dark:text-blue-400">{{ Carbon::parse($dataDaftarPoliRJ['sep']['resSep']['tglSEP'] ?? now())->format('d/m/Y') }}</span>
                                            </div>
                                        @endif
                                        <livewire:pages::transaksi.rj.daftar-rj.vclaim-rj-actions :initialRjNo="$rjNo ?? null"
                                            wire:key="vclaim-rj-actions-{{ $rjNo ?? 'new' }}" />
                                        <div class="grid">
                                            <x-input-label value="No SEP" />
                                            <x-text-input wire:model.live="dataDaftarPoliRJ.sep.noSep"
                                                :disabled="$isFormLocked" x-ref="inputNoSep" />
                                            <x-input-error :messages="$errors->get('dataDaftarPoliRJ.sep.noSep')" class="mt-1" />
                                        </div>
                                    </div>
                                @endif
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-between gap-3">
                    <a href="{{ route('master.pasien') }}" wire:navigate>
                        <x-primary-button type="button">Master Pasien</x-primary-button>
                    </a>
                    <div class="flex justify-between gap-3">
                        <x-secondary-button wire:click="closeModal">Batal</x-secondary-button>
                        <x-primary-button x-ref="btnSimpan" wire:click.prevent="save()" class="min-w-[120px]"
                            wire:loading.attr="disabled" :disabled="$isFormLocked">
                            <span wire:loading.remove>
                                <svg class="inline w-4 h-4 mr-1 -ml-1" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1-4l-4 4-4-4m4 4V4" />
                                </svg>
                                {{ $isFormLocked ? 'Read Only' : 'Simpan' }}
                            </span>
                            <span wire:loading><x-loading /> Menyimpan...</span>
                        </x-primary-button>
                    </div>
                </div>
            </div>

        </div>
    </x-modal>
</div>
