<?php
// resources/views/pages/transaksi/ri/daftar-ri/daftar-ri-actions.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\BPJS\VclaimTrait;

new class extends Component {
    // CATATAN: VclaimTrait tidak di-use di sini — static call VclaimTrait::method()
    // cukup karena tidak ada conflict method dengan trait lain yang dipakai.
    use EmrRITrait, MasterPasienTrait, WithRenderVersioningTrait;

    public string $formMode = 'create';
    public bool $isFormLocked = false;

    public ?string $riHdrNo = null;
    public array $dataDaftarRi = [];
    public array $dataPasien = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal', 'pasien', 'dokter', 'bangsal'];

    /* ---- Klaim ---- */
    public string $klaimId = 'UM';
    public array $klaimOptions = [['klaimId' => 'UM', 'klaimDesc' => 'UMUM'], ['klaimId' => 'JM', 'klaimDesc' => 'BPJS'], ['klaimId' => 'JR', 'klaimDesc' => 'JASA RAHARJA'], ['klaimId' => 'JML', 'klaimDesc' => 'Asuransi Lain'], ['klaimId' => 'KR', 'klaimDesc' => 'Kronis']];

    /* ---- Cara Masuk ---- */
    public string $entryId = '1';
    public array $entryOptions = [];

    /* ---- Admin Usia & Kasus Polisi ---- */
    public bool $statusAdminAge = false; // toggle: kenakan biaya admin usia
    public bool $kasusPolisi = false; // toggle: kasus polisi / medikolegal

    /* ---- Bangsal / Ruang / Bed — via LOV Room ---- */
    public string $bangsalId = '';
    public array $bangsalOptions = [];
    // roomOptions & bedOptions tidak lagi dipakai — diganti lov-room component

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas(['modal', 'pasien', 'dokter', 'bangsal']);
        $this->dataDaftarRi = $this->getDefaultRITemplate();

        $this->entryOptions = DB::table('rsmst_entryugds')->select('entry_id', 'entry_desc')->orderBy('entry_id')->get()->map(fn($r) => ['entryId' => (string) $r->entry_id, 'entryDesc' => $r->entry_desc])->toArray();

        // bangsalOptions tidak lagi diload di sini — lov-room sudah handle pencarian kamar+bed
    }

    /* ===============================
     | OPEN CREATE
     =============================== */
    #[On('daftar-ri.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode = 'create';
        $this->resetValidation();
        $this->dataDaftarRi = $this->getDefaultRITemplate();

        $now = Carbon::now();
        $this->dataDaftarRi['entryDate'] = $now->format('d/m/Y H:i:s');
        $this->dataDaftarRi['entryId'] = $this->entryId;
        $this->dataDaftarRi['entryDesc'] = collect($this->entryOptions)->firstWhere('entryId', $this->entryId)['entryDesc'] ?? '';

        $findShift = DB::table('rstxn_shiftctls')
            ->select('shift')
            ->whereNotNull('shift_start')
            ->whereNotNull('shift_end')
            ->whereRaw('? BETWEEN shift_start AND shift_end', [$now->format('H:i:s')])
            ->first();
        $this->dataDaftarRi['shift'] = (string) ($findShift?->shift ?? 1);

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'ri-actions');
        $this->dispatch('focus-cari-pasien-ri');
    }

    /* ===============================
     | OPEN EDIT
     =============================== */
    #[On('daftar-ri.openEdit')]
    public function openEdit(string $riHdrNo): void
    {
        $this->resetForm();
        $this->riHdrNo = $riHdrNo;
        $this->formMode = 'edit';
        $this->resetValidation();

        $data = $this->findDataRI($riHdrNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }

        if ($this->checkRIStatus($riHdrNo)) {
            $this->isFormLocked = true;
            $this->dispatch('toast', type: 'warning', message: 'Data RI ini sudah selesai dan tidak bisa diubah.');
        }

        $this->dataDaftarRi = $data;
        $this->dataPasien = $this->findDataMasterPasien($this->dataDaftarRi['regNo'] ?? '');
        $this->syncFromDataDaftarRI();

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'ri-actions');
    }

    /* ===============================
     | CLOSE MODAL
     =============================== */
    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'ri-actions');
    }

    /* ===============================
     | SAVE
     =============================== */
    public function save(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }

        $this->setDataPrimer();
        $this->validateDataRI();

        $riHdrNo = $this->dataDaftarRi['riHdrNo'] ?? null;
        if (!$riHdrNo) {
            $this->dispatch('toast', type: 'error', message: 'Nomor RI Header tidak valid.');
            return;
        }

        // Cek lockstatus pasien — hanya saat create (bukan edit)
        if ($this->formMode === 'create') {
            $lockError = $this->checkLockStatus($this->dataDaftarRi['regNo'] ?? '');
            if ($lockError) {
                $this->dispatch('toast', type: 'error', message: $lockError);
                return;
            }
        }

        try {
            $message = '';
            if ($this->formMode === 'create') {
                Cache::lock("lock:rstxn_rihdrs:{$riHdrNo}", 15)->block(5, function () use ($riHdrNo, &$message) {
                    DB::transaction(function () use ($riHdrNo, &$message) {
                        DB::table('rstxn_rihdrs')->insert($this->buildPayload($riHdrNo, 'create'));
                        $this->updateJsonData($riHdrNo);
                        $this->insertTrfRoom($riHdrNo);
                        $this->updateLockStatus($this->dataDaftarRi['regNo'], 'RI');
                        $message = 'Data RI berhasil disimpan.';
                    });
                });
            } else {
                DB::transaction(function () use ($riHdrNo, &$message) {
                    $this->lockRIRow($riHdrNo);
                    DB::table('rstxn_rihdrs')->where('rihdr_no', $riHdrNo)->update($this->buildPayload($riHdrNo, 'update'));
                    $this->updateJsonData($riHdrNo);
                    $message = 'Data RI berhasil diperbarui.';
                });
            }

            $this->afterSave($message);
        } catch (LockTimeoutException) {
            $this->dispatch('toast', type: 'error', message: 'Sistem sedang sibuk, silakan coba lagi.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (QueryException $e) {
            $this->handleDatabaseError($e);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | BUILD PAYLOAD
     | Hanya kolom yang ADA di tabel RSTXN_RIHDRS yang dimasukkan.
     | Field seperti noBooking, noAntrian, slCodeFrom, noReferensi,
     | bangsalId, poliId disimpan di JSON (datadaftarri_json) saja.
     =============================== */
    private function buildPayload(int|string $riHdrNo, string $mode): array
    {
        $entryDateRaw = $this->dataDaftarRi['entryDate'] ?? Carbon::now()->format('d/m/Y H:i:s');

        $base = [
            // ── Primary Key ──────────────────────────────────────────────
            'rihdr_no' => $riHdrNo,

            // ── Identitas & Waktu ─────────────────────────────────────────
            'reg_no' => $this->dataDaftarRi['regNo'] ?? '',
            'entry_date' => DB::raw("to_date('{$entryDateRaw}','dd/mm/yyyy hh24:mi:ss')"),
            'entry_id' => $this->dataDaftarRi['entryId'] ?? '1',
            'shift' => $this->dataDaftarRi['shift'] ?? 1,

            // ── Dokter & Kamar ────────────────────────────────────────────
            'dr_id' => $this->dataDaftarRi['drId'] ?? '',
            'room_id' => $this->dataDaftarRi['roomId'] ?? '',
            'bed_no' => $this->dataDaftarRi['bedNo'] ?? '',

            // ── Klaim & SEP ───────────────────────────────────────────────
            'klaim_id' => $this->dataDaftarRi['klaimId'] ?? 'UM',
            'vno_sep' => $this->dataDaftarRi['sep']['noSep'] ?? '',

            // ── Status ────────────────────────────────────────────────────
            'ri_status' => $this->dataDaftarRi['riStatus'] ?? 'I',
            'erm_status' => $this->dataDaftarRi['ermStatus'] ?? 'A',

            // ── Admin Usia ────────────────────────────────────────────────
            // ADMIN_STATUS: '1' = kenakan biaya admin usia, '0' = tidak
            'admin_status' => $this->statusAdminAge ? '1' : '0',
            'admin_age' => $this->dataDaftarRi['adminAge'] ?? 0,

            // ── Kasus Polisi ──────────────────────────────────────────────
            // POLICE_CASE: '1' = kasus polisi/medikolegal, '0' = tidak
            'police_case' => $this->kasusPolisi ? '1' : '0',

            // ── JSON Payload (seluruh dataDaftarRi) ───────────────────────
            // Semua field yang tidak punya kolom sendiri (noBooking, noReferensi,
            // bangsalId, poliId, dll) disimpan di sini.
            'datadaftarri_json' => json_encode($this->dataDaftarRi, JSON_UNESCAPED_UNICODE),
        ];

        if ($mode === 'create') {
            // Default saat pendaftaran baru
            $base['ri_total'] = 0;
            $base['ri_diskon'] = 0;
            $base['ri_bayar'] = 0;
            $base['ri_titip'] = 0;
            $base['status_pulang'] = null;
            $base['exit_date'] = null;
            $base['move_date'] = null;
            $base['police_case'] = $this->kasusPolisi ? '1' : '0';
            $base['pneumonia'] = '0';
            $base['plebitis'] = '0';
            $base['sepsis'] = '0';
            $base['dekubitus'] = '0';
            $base['ilo'] = '0';
            $base['isk'] = '0';
            $base['push_antrian_bpjs_status'] = '0';
            $base['trf_gudang_status'] = '0';
        }

        return $base;
    }

    /* ===============================
     | SET DATA PRIMER
     | Mengisi field yang dibutuhkan sebelum validate & save.
     | noBooking, noAntrian: disimpan ke JSON saja (tidak ada kolomnya di tabel).
     =============================== */
    private function setDataPrimer(): void
    {
        $data = &$this->dataDaftarRi;
        $data['entryId'] = $this->entryId;
        $data['entryDesc'] = collect($this->entryOptions)->firstWhere('entryId', $this->entryId)['entryDesc'] ?? '';
        $data['bangsalId'] = $this->bangsalId;

        // riHdrNo: generate jika belum ada (untuk create)
        if (empty($data['riHdrNo'])) {
            $maxNo = DB::table('rstxn_rihdrs')->max('rihdr_no');
            $data['riHdrNo'] = ($maxNo ?? 0) + 1;
        }

        // noBooking & noAntrian: disimpan ke JSON saja
        if (empty($data['noBooking'])) {
            $data['noBooking'] = Carbon::now()->format('YmdHis') . 'RSIMRI';
        }
        if (empty($data['noAntrian'])) {
            $tglAntrian = Carbon::createFromFormat('d/m/Y H:i:s', $data['entryDate'])->format('dmY');
            $count = DB::table('rstxn_rihdrs')
                ->where('dr_id', $data['drId'])
                ->whereRaw("to_char(entry_date,'ddmmyyyy') = ?", [$tglAntrian])
                ->count();
            $data['noAntrian'] = $count + 1;
        }

        // Sync toggle flags ke dataDaftarRi agar ikut ke JSON
        $data['statusAdminAge'] = $this->statusAdminAge ? '1' : '0';
        $data['kasusPolisi'] = $this->kasusPolisi ? '1' : '0';
    }

    /* ===============================
     | VALIDATE DATA RI
     | Hanya validasi field yang wajib diisi user.
     | Field seperti noBooking, slCodeFrom, noReferensi hanya di JSON — tidak divalidasi DB-level.
     =============================== */
    private function validateDataRI(): void
    {
        $this->validate([
            'dataDaftarRi.regNo' => 'bail|required|exists:rsmst_pasiens,reg_no',
            'dataDaftarRi.drId' => 'required|exists:rsmst_doctors,dr_id',
            'dataDaftarRi.drDesc' => 'required|string',
            'dataDaftarRi.entryDate' => 'required|date_format:d/m/Y H:i:s',
            'dataDaftarRi.riHdrNo' => 'required|numeric',
            'dataDaftarRi.roomId' => 'required|exists:rsmst_rooms,room_id',
            'dataDaftarRi.bedNo' => 'required',
            'dataDaftarRi.shift' => 'required|in:1,2,3',
            'dataDaftarRi.riStatus' => 'required|in:I,L,P',
            'dataDaftarRi.klaimId' => 'required|exists:rsmst_klaimtypes,klaim_id',
            // bangsalId: tidak ada kolom BANGSAL_ID di RSTXN_RIHDRS → tidak divalidasi DB-level
            // noBooking, noAntrian, noReferensi, slCodeFrom: disimpan di JSON saja
        ]);
    }

    /* ===============================
     | UPDATE JSON DATA
     | Menyimpan seluruh dataDaftarRi ke kolom datadaftarri_json.
     | Kolom ini adalah "catch-all" untuk field yang tidak punya kolom sendiri.
     =============================== */
    private function updateJsonData(int|string $riHdrNo): void
    {
        $allowedFields = ['regNo', 'drId', 'drDesc', 'klaimId', 'klaimStatus', 'entryId', 'entryDesc', 'entryDate', 'shift', 'noAntrian', 'noBooking', 'slCodeFrom', 'riStatus', 'ermStatus', 'bangsalId', 'bangsalDesc', 'roomId', 'roomDesc', 'bedNo', 'noReferensi', 'sep', 'spri', 'poliId', 'poliDesc', 'kddrbpjs', 'kdpolibpjs', 'statusAdminAge', 'adminAge', 'kasusPolisi'];

        if ($this->formMode === 'create') {
            // Sudah di-handle oleh buildPayload via datadaftarri_json
            // updateJsonRI() dari EmrRITrait dipanggil jika trait itu juga update via ORM
            $this->updateJsonRI((int) $riHdrNo, $this->dataDaftarRi);
            return;
        }

        // Edit: merge field yang boleh diubah ke data existing
        $existing = $this->findDataRI($riHdrNo);
        if (empty($existing)) {
            throw new \RuntimeException('Data RI tidak ditemukan saat update JSON, simpan dibatalkan.');
        }
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $this->dataDaftarRi)) {
                $existing[$field] = $this->dataDaftarRi[$field];
            }
        }
        $this->updateJsonRI((int) $riHdrNo, $existing);

        // Juga update kolom datadaftarri_json langsung (jika EmrRITrait belum handle)
        DB::table('rstxn_rihdrs')
            ->where('rihdr_no', $riHdrNo)
            ->update(['datadaftarri_json' => json_encode($existing, JSON_UNESCAPED_UNICODE)]);
    }

    private function afterSave(string $message): void
    {
        if ($this->formMode === 'edit') {
            $this->syncFromDataDaftarRI();
        }
        $this->dispatch('toast', type: 'success', message: $message);
        $this->closeModal();
        $this->dispatch('refresh-after-ri.saved');
    }

    private function handleDatabaseError(QueryException $e): void
    {
        $code = $e->errorInfo[1] ?? 0;
        $map = [1 => 'Duplikasi data.', 1400 => 'Field wajib kosong.', 2291 => 'Referensi tidak valid.', 2292 => 'Data sedang digunakan.'];
        $this->dispatch('toast', type: 'error', message: $map[$code] ?? 'Kesalahan database: ' . $e->getMessage());
    }

    /* ===============================
     | CHECK LOCK STATUS PASIEN
     | Sebelum insert RI, pastikan pasien tidak sedang terdaftar di RJ/RI/UGD.
     | Mengembalikan string error jika ada konflik, null jika aman.
     =============================== */
    private function checkLockStatus(string $regNo): ?string
    {
        if (empty($regNo)) {
            return 'Nomor registrasi pasien tidak valid.';
        }

        $pasien = DB::table('rsmst_pasiens')->where('reg_no', $regNo)->select('lockstatus', 'reg_name')->first();

        if (!$pasien) {
            return 'Data pasien tidak ditemukan.';
        }

        $nama = $pasien->reg_name ?? $regNo;
        $lock = strtoupper($pasien->lockstatus ?? '');

        return match ($lock) {
            'RJ' => "Pasien atas nama {$nama} sudah terdaftar di Rawat Jalan.",
            'RI' => "Pasien atas nama {$nama} sudah terdaftar di Rawat Inap.",
            'UGD' => "Pasien atas nama {$nama} sudah terdaftar di UGD.",
            default => null, // aman, boleh lanjut
        };
    }

    /* ===============================
     | INSERT RSMST_TRFROOMS
     | Dijalankan di dalam DB transaction setelah insert rstxn_rihdrs.
     | Mengambil harga kamar dari rsmst_rooms, lalu insert ke rsmst_trfrooms.
     =============================== */
    private function insertTrfRoom(int|string $riHdrNo): void
    {
        $roomId = $this->dataDaftarRi['roomId'] ?? '';
        $bedNo = $this->dataDaftarRi['bedNo'] ?? '';
        $regNo = $this->dataDaftarRi['regNo'] ?? '';

        if (empty($roomId)) {
            return; // tidak ada kamar → skip (seharusnya sudah tervalidasi)
        }

        // Ambil harga kamar dari master
        $room = DB::table('rsmst_rooms')->where('room_id', $roomId)->select('room_price', 'perawatan_price', 'common_service')->first();

        $rPrice = $room->room_price ?? 0;
        $pPrice = $room->perawatan_price ?? 0;
        $cService = $room->common_service ?? 0;

        // Nomor transfer kamar: ambil MAX + 1 (Oracle-safe)
        $maxTrfr = DB::table('rsmst_trfrooms')->max('trfr_no');
        $nextTrfr = ($maxTrfr ?? 0) + 1;

        // entry_date dari dataDaftarRi sudah format d/m/Y H:i:s — convert untuk Oracle
        $entryDateRaw = $this->dataDaftarRi['entryDate'] ?? Carbon::now()->format('d/m/Y H:i:s');

        DB::table('rsmst_trfrooms')->insert([
            'trfr_no' => $nextTrfr,
            'rihdr_no' => $riHdrNo,
            'room_id' => $roomId,
            'start_date' => DB::raw("to_date('{$entryDateRaw}','dd/mm/yyyy hh24:mi:ss')"),
            'bed_no' => $bedNo,
            'room_price' => $rPrice,
            'perawatan_price' => $pPrice,
            'common_service' => $cService,
        ]);
    }

    /* ===============================
     | UPDATE LOCK STATUS PASIEN
     =============================== */
    private function updateLockStatus(string $regNo, string $status): void
    {
        if (empty($regNo)) {
            return;
        }
        DB::table('rsmst_pasiens')
            ->where('reg_no', $regNo)
            ->update(['lockstatus' => $status]);
    }

    /* ---- SEP Handlers (static call — tidak ada AntrianTrait, tidak ada conflict) ---- */
    private function handleSepCreation(): void
    {
        $sudahAdaSEP = !empty($this->dataDaftarRi['sep']['noSep']);
        $hasReqSep = !empty($this->dataDaftarRi['sep']['reqSep']);
        if (!$sudahAdaSEP && $hasReqSep) {
            $this->pushInsertSEP($this->dataDaftarRi['sep']['reqSep']);
        } elseif ($sudahAdaSEP && $hasReqSep) {
            $this->pushUpdateSEP($this->dataDaftarRi['sep']['reqSep']);
        }
    }

    private function pushInsertSEP(array $reqSep): void
    {
        if (empty($reqSep)) {
            return;
        }
        try {
            $response = VclaimTrait::sep_insert($reqSep)->getOriginalContent();
            $code = $response['metadata']['code'] ?? 500;
            if ($code == 200) {
                $sepData = $response['response']['sep'] ?? null;
                if ($sepData) {
                    $this->dataDaftarRi['sep']['noSep'] = $sepData['noSep'] ?? '';
                    $this->dataDaftarRi['sep']['resSep'] = $sepData;
                    $this->dispatch('toast', type: 'success', message: "SEP berhasil dibuat: {$sepData['noSep']}");
                }
            } else {
                $this->dispatch('toast', type: 'error', message: "SEP gagal ({$code}): " . ($response['metadata']['message'] ?? '-'));
            }
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Error SEP: ' . $e->getMessage());
        }
    }

    private function pushUpdateSEP(array $reqSep): void
    {
        if (empty($reqSep) || empty($this->dataDaftarRi['sep']['noSep'])) {
            return;
        }
        try {
            $t = $reqSep['request']['t_sep'] ?? [];
            $payload = [
                'request' => [
                    't_sep' => [
                        'noSep' => $this->dataDaftarRi['sep']['noSep'],
                        'klsRawat' => $t['klsRawat'] ?? [],
                        'noMR' => $t['noMR'] ?? '',
                        'catatan' => $t['catatan'] ?? '',
                        'diagAwal' => $t['diagAwal'] ?? '',
                        'poli' => ['tujuan' => $t['poli']['tujuan'] ?? '', 'eksekutif' => '0'],
                        'cob' => ['cob' => '0'],
                        'katarak' => ['katarak' => '0'],
                        'jaminan' => $t['jaminan'] ?? ['lakaLantas' => '0'],
                        'dpjpLayan' => $t['dpjpLayan'] ?? '',
                        'noTelp' => $t['noTelp'] ?? '',
                        'user' => 'siRUS',
                    ],
                ],
            ];
            $response = VclaimTrait::sep_update($payload)->getOriginalContent();
            $code = $response['metadata']['code'] ?? 500;
            $this->dispatch('toast', type: $code == 200 ? 'success' : 'error', message: "Update SEP ({$code}): " . ($response['metadata']['message'] ?? ''));
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Error Update SEP: ' . $e->getMessage());
        }
    }

    /* ---- LOV Room Listener ---- */
    #[On('lov.selected.riFormRoom')]
    public function riFormRoom(string $target, ?array $payload): void
    {
        if (empty($payload)) {
            // User klik "Ubah" / clear
            $this->dataDaftarRi['roomId'] = '';
            $this->dataDaftarRi['roomDesc'] = '';
            $this->dataDaftarRi['bedNo'] = '';
            $this->dataDaftarRi['bangsalId'] = '';
            $this->dataDaftarRi['bangsalDesc'] = '';
            $this->bangsalId = '';
            $this->incrementVersion('bangsal');
            return;
        }

        $roomId = $payload['room_id'] ?? '';
        $roomName = $payload['room_name'] ?? '';
        $bedNo = $payload['bed_no'] ?? '';

        // Lookup bangsal_id dari rsmst_rooms karena lov-room tidak mengirim bangsal
        $bangsalRow = DB::table('rsmst_rooms as r')->join('rsmst_bangsals as b', 'b.bangsal_id', '=', 'r.bangsal_id')->where('r.room_id', $roomId)->select('r.bangsal_id', 'b.bangsal_name')->first();

        $this->dataDaftarRi['roomId'] = $roomId;
        $this->dataDaftarRi['roomDesc'] = $roomName;
        $this->dataDaftarRi['bedNo'] = $bedNo;
        $this->dataDaftarRi['bangsalId'] = $bangsalRow->bangsal_id ?? '';
        $this->dataDaftarRi['bangsalDesc'] = $bangsalRow->bangsal_name ?? '';
        $this->bangsalId = $bangsalRow->bangsal_id ?? '';

        $this->incrementVersion('bangsal');
    }

    /* ---- LOV Listeners ---- */
    #[On('lov.selected.riFormPasien')]
    public function riFormPasien(string $target, array $payload): void
    {
        $this->dataDaftarRi['regNo'] = $payload['reg_no'] ?? '';
        $this->dataDaftarRi['regName'] = $payload['reg_name'] ?? '';
        $this->dataPasien = $this->findDataMasterPasien($this->dataDaftarRi['regNo']);
        $this->incrementVersion('pasien');
        $this->incrementVersion('modal');
        $this->dispatch('focus-cari-dokter-ri');
    }

    #[On('lov.selected.riFormDokter')]
    public function riFormDokter(string $target, array $payload): void
    {
        $this->dataDaftarRi['drId'] = $payload['dr_id'] ?? '';
        $this->dataDaftarRi['drDesc'] = $payload['dr_name'] ?? '';
        $this->dataDaftarRi['kddrbpjs'] = $payload['kd_dr_bpjs'] ?? '';
        $this->dataDaftarRi['kdpolibpjs'] = $payload['kd_poli_bpjs'] ?? '';
        $this->dataDaftarRi['poliId'] = $payload['poli_id'] ?? '';
        $this->dataDaftarRi['poliDesc'] = $payload['poli_desc'] ?? '';
        $this->incrementVersion('dokter');
        $this->incrementVersion('modal');
    }

    /* ---- SEP & SPRI event dari vclaim-ri-actions ---- */
    #[On('sep-generated-ri')]
    public function handleSepGenerated(array $reqSep, string $noSep = '', array $resSep = []): void
    {
        $this->dataDaftarRi['sep']['reqSep'] = $reqSep;

        if (!empty($noSep)) {
            $this->dataDaftarRi['sep']['noSep'] = $noSep;
            $this->dataDaftarRi['sep']['resSep'] = $resSep;
        }

        $noRujukan = $reqSep['request']['t_sep']['rujukan']['noRujukan'] ?? null;
        if ($noRujukan) {
            $this->dataDaftarRi['noReferensi'] = $noRujukan;
        }

        // Simpan ke JSON agar noSep tidak hilang saat reload
        if ($this->riHdrNo) {
            $this->updateJsonData($this->riHdrNo);
        }

        $this->incrementVersion('modal');
    }

    /**
     * FIX: Handler spri-generated-ri yang sebelumnya MISSING.
     * Dipanggil dari vclaim-ri-actions saat SPRI berhasil disimpan ke BPJS.
     * Menyimpan spriData ke dataDaftarRi agar tersimpan ke JSON.
     */
    #[On('spri-generated-ri')]
    public function handleSpriGenerated(array $spriData): void
    {
        $this->dataDaftarRi['spri'] = $spriData;

        // Sync noReferensi dari noSPRIBPJS jika belum ada rujukan lain
        if (!empty($spriData['noSPRIBPJS']) && empty($this->dataDaftarRi['noReferensi'])) {
            $this->dataDaftarRi['noReferensi'] = $spriData['noSPRIBPJS'];
        }

        // Simpan ke JSON agar SPRI tidak hilang saat modal ditutup/dibuka ulang
        if ($this->riHdrNo) {
            $this->updateJsonData($this->riHdrNo);
        }

        $this->incrementVersion('modal');
    }

    /* ---- Vclaim ---- */
    public function openVclaimModal(): void
    {
        if (empty($this->dataDaftarRi['regNo'])) {
            $this->dispatch('toast', type: 'error', message: 'Pilih pasien terlebih dahulu.');
            return;
        }
        $isBpjs = ($this->dataDaftarRi['klaimStatus'] ?? '') === 'BPJS' || ($this->dataDaftarRi['klaimId'] ?? '') === 'JM';
        if (!$isBpjs) {
            $this->dispatch('toast', type: 'error', message: 'Fitur SEP hanya untuk pasien BPJS.');
            return;
        }
        $this->dispatch('open-vclaim-modal-ri', riHdrNo: $this->riHdrNo, regNo: $this->dataDaftarRi['regNo'], drId: $this->dataDaftarRi['drId'], drDesc: $this->dataDaftarRi['drDesc'], poliId: $this->dataDaftarRi['poliId'] ?? null, poliDesc: $this->dataDaftarRi['poliDesc'] ?? null, kdpolibpjs: $this->dataDaftarRi['kdpolibpjs'] ?? null, noReferensi: $this->dataDaftarRi['noReferensi'] ?? null, sepData: $this->dataDaftarRi['sep'] ?? [], spriData: $this->dataDaftarRi['spri'] ?? []);
    }

    public function cetakSEP(): void
    {
        if (empty($this->dataDaftarRi['sep']['noSep'])) {
            $this->dispatch('toast', type: 'error', message: 'Tidak ada SEP untuk dicetak.');
            return;
        }
        $this->dispatch('cetak-sep-ri.open', riHdrNo: $this->riHdrNo);
    }

    /* ---- Updated Hooks ---- */
    public function updated(string $name, mixed $value): void
    {
        if ($name === 'klaimId') {
            $this->klaimId = $value;
            $this->dataDaftarRi['klaimId'] = $value;
            $this->dataDaftarRi['klaimStatus'] = DB::table('rsmst_klaimtypes')->where('klaim_id', $value)->value('klaim_status') ?? 'UMUM';
            $this->incrementVersion('modal');
        }

        // Toggle Admin Usia: ON → ambil nilai dari rsmst_parameters par_id=3
        //                    OFF → set 0
        if ($name === 'statusAdminAge') {
            if ($this->statusAdminAge) {
                $parValue = DB::table('rsmst_parameters')->where('par_id', 3)->value('par_value');
                $this->dataDaftarRi['adminAge'] = (int) ($parValue ?? 0);
            } else {
                $this->dataDaftarRi['adminAge'] = 0;
            }
            $this->incrementVersion('modal');
        }

        if ($name === 'kasusPolisi') {
            $this->dataDaftarRi['kasusPolisi'] = $this->kasusPolisi ? '1' : '0';
            $this->incrementVersion('modal');
        }
    }

    /* ---- Helpers ---- */
    protected function resetForm(): void
    {
        $this->reset(['riHdrNo', 'dataDaftarRi', 'dataPasien']);
        $this->resetVersion();
        $this->klaimId = 'UM';
        $this->entryId = '1';
        $this->bangsalId = '';
        $this->statusAdminAge = false;
        $this->kasusPolisi = false;
        $this->formMode = 'create';
        $this->isFormLocked = false;
    }

    private function syncFromDataDaftarRI(): void
    {
        $this->riHdrNo = isset($this->dataDaftarRi['riHdrNo']) ? (string) $this->dataDaftarRi['riHdrNo'] : null;
        $this->klaimId = $this->dataDaftarRi['klaimId'] ?? 'UM';
        $this->entryId = $this->dataDaftarRi['entryId'] ?? '1';
        $this->bangsalId = $this->dataDaftarRi['bangsalId'] ?? '';
        $this->statusAdminAge = ($this->dataDaftarRi['statusAdminAge'] ?? '0') === '1';
        $this->kasusPolisi = ($this->dataDaftarRi['kasusPolisi'] ?? '0') === '1';
    }

    /* ===============================
     | iDRG (E-Klaim Kemenkes)
     =============================== */
    public ?string $idrgPdfBase64 = null;
    public ?string $idrgPdfSep = null;
    public string $idrgDischargeStatus = '1';

    #[On('daftar-ri.openIdrg')]
    public function openIdrg(string $riHdrNo): void
    {
        $this->resetForm();
        $this->riHdrNo = $riHdrNo;
        $this->formMode = 'edit';
        $this->isFormLocked = true;

        $data = $this->findDataRI($riHdrNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Inap tidak ditemukan.');
            return;
        }

        $this->dataDaftarRi = $data;

        $isBpjs = ($this->dataDaftarRi['klaimStatus'] ?? '') === 'BPJS' || ($this->dataDaftarRi['klaimId'] ?? '') === 'JM';
        if (!$isBpjs) {
            $this->dispatch('toast', type: 'error', message: 'Kirim iDRG hanya untuk pasien BPJS.');
            return;
        }

        $this->dataPasien = $this->findDataMasterPasien($this->dataDaftarRi['regNo'] ?? '');
        $this->idrgPdfBase64 = null;
        $this->idrgPdfSep = null;
        $this->idrgDischargeStatus = (string) ($data['idrg']['dischargeStatus'] ?? '1');

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'ri-idrg');
    }

    public function updatedIdrgDischargeStatus($value): void
    {
        if (empty($this->riHdrNo)) return;
        $this->dispatch('idrg-claim-ri.set-discharge', riHdrNo: (string) $this->riHdrNo, dischargeStatus: (string) $value);
    }

    public function kirimIdrg(string $step): void
    {
        if (empty($this->riHdrNo)) {
            $this->dispatch('toast', type: 'error', message: 'Silakan pilih data rawat inap terlebih dahulu.');
            return;
        }

        $eventMap = [
            'generate-number'     => 'idrg-claim-ri.generate-number',
            'new-claim'           => 'idrg-claim-ri.new',
            'set-data'            => 'idrg-claim-ri.set-data',
            'delete-claim'        => 'idrg-claim-ri.delete',
            'set-diagnosa-idrg'   => 'idrg-diagnosa-ri.set',
            'set-prosedur-idrg'   => 'idrg-prosedur-ri.set',
            'group-idrg'          => 'idrg-grouping-ri.group',
            'final-idrg'          => 'idrg-grouping-ri.final',
            'reedit-idrg'         => 'idrg-grouping-ri.reedit',
            'import-inacbg'       => 'idrg-grouping-ri.import-inacbg',
            'set-diagnosa-inacbg' => 'idrg-inacbg-ri.set-diagnosa',
            'set-prosedur-inacbg' => 'idrg-inacbg-ri.set-prosedur',
            'group-inacbg-1'      => 'idrg-inacbg-ri.group-stage1',
            'group-inacbg-2'      => 'idrg-inacbg-ri.group-stage2',
            'final-inacbg'        => 'idrg-inacbg-ri.final',
            'reedit-inacbg'       => 'idrg-inacbg-ri.reedit',
            'final-klaim'         => 'idrg-klaim-ri.final',
            'reedit-klaim'        => 'idrg-klaim-ri.reedit',
            'send-klaim'          => 'idrg-klaim-ri.send',
            'print-klaim'         => 'idrg-klaim-ri.print',
            'get-status'          => 'idrg-klaim-ri.get-status',
        ];

        $event = $eventMap[$step] ?? null;
        if (!$event) {
            $this->dispatch('toast', type: 'error', message: 'Step iDRG tidak dikenali: ' . $step);
            return;
        }

        $this->dispatch($event, riHdrNo: (string) $this->riHdrNo);
    }

    #[On('idrg-klaim-ri.pdf-ready')]
    public function onIdrgPdfReady(array $payload): void
    {
        $this->idrgPdfBase64 = $payload['base64'] ?? null;
        $this->idrgPdfSep = $payload['nomorSep'] ?? null;
        $this->incrementVersion('modal');
    }
};
?>

{{-- NOTE: Blade template (HTML) sama persis dengan file asli.
     Copy paste blade dari file asli ke setelah baris ini.
     Hanya PHP class di atas yang berubah (tambahan handler spri-generated-ri). --}}

<div>
    <x-modal name="ri-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal', [$formMode, $riHdrNo ?? 'new']) }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-4 border-b border-gray-200 dark:border-gray-700 shrink-0">
                <div class="absolute inset-0 opacity-[0.06]"
                    style="background-image:radial-gradient(currentColor 1px,transparent 1px);background-size:14px 14px;">
                </div>
                <div class="relative flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-blue-500/10">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                {{ $formMode === 'edit' ? 'Ubah Data Rawat Inap' : 'Tambah Data Rawat Inap' }}
                            </h2>
                            <div class="flex gap-2 mt-1">
                                <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
                                    {{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Tambah' }}
                                </x-badge>
                                @if ($isFormLocked)
                                    <x-badge variant="danger">Read Only</x-badge>
                                @endif
                            </div>
                        </div>
                    </div>

                    <x-icon-button color="gray" type="button" wire:click="closeModal" class="shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- STICKY DISPLAY PASIEN --}}
            @if (!empty($dataPasien['pasien']['regNo']))
                <div id="DataPasien"
                    class="sticky top-0 z-20 px-4 py-2 bg-white border-b border-gray-200 dark:bg-gray-900 dark:border-gray-700 shrink-0"
                    wire:key="{{ $this->renderKey('pasien', [$dataPasien['pasien']['regNo'] ?? '']) }}">
                    @php
                        $klaimId = $dataDaftarRi['klaimId'] ?? '-';
                        $klaimRow = DB::table('rsmst_klaimtypes')
                            ->where('klaim_id', $klaimId)
                            ->select('klaim_status', 'klaim_desc')
                            ->first();
                        $klaimDesc = $klaimRow->klaim_desc ?? 'Asuransi Lain';
                        $badgeVariant = match ($klaimId) {
                            'UM' => 'success',
                            'JM' => 'brand',
                            'KR' => 'warning',
                            default => 'danger',
                        };
                    @endphp
                    <div class="grid grid-cols-3 gap-3 pl-3 py-1 bg-gray-100 dark:bg-gray-800 rounded-lg">
                        <div class="min-w-0">
                            <div class="text-base font-semibold text-gray-700 dark:text-gray-300">
                                {{ $dataPasien['pasien']['regNo'] }}</div>
                            <div class="text-2xl font-semibold text-primary dark:text-white truncate">
                                {{ strtoupper($dataPasien['pasien']['regName'] ?? '-') }}
                                / ({{ $dataPasien['pasien']['jenisKelamin']['jenisKelaminDesc'] ?? '-' }})
                                / {{ $dataPasien['pasien']['thn'] ?? '-' }} Thn
                            </div>
                            <div class="font-normal text-sm text-gray-700 dark:text-gray-400 truncate">
                                {{ $dataPasien['pasien']['identitas']['alamat'] ?? '-' }}</div>
                        </div>
                        <div class="text-sm">
                            @if (!empty($dataDaftarRi['drDesc']))
                                <div class="text-xs text-gray-500 dark:text-gray-400">DPJP Utama</div>
                                <div class="font-semibold text-gray-800 dark:text-gray-200">
                                    {{ $dataDaftarRi['drDesc'] }}</div>
                            @endif
                            @if (!empty($dataDaftarRi['pengkajianAwalPasienRawatInap']['levelingDokter']))
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Tim Dokter:</div>
                                @foreach ($dataDaftarRi['pengkajianAwalPasienRawatInap']['levelingDokter'] as $ld)
                                    <div class="text-xs text-gray-700 dark:text-gray-300">{{ $ld['drDesc'] ?? '-' }}
                                        <span class="text-gray-400">({{ $ld['levelingDesc'] ?? '-' }})</span>
                                    </div>
                                @endforeach
                            @else
                                <div class="text-xs text-gray-400 mt-1">—</div>
                            @endif
                        </div>
                        <div class="px-2 text-sm text-gray-900 dark:text-gray-100">
                            <p class="text-right text-gray-500 dark:text-gray-400">
                                {{ $dataDaftarRi['bangsalDesc'] ?? '-' }}</p>
                            <p class="font-semibold text-right">
                                {{ $dataDaftarRi['roomDesc'] ?? '-' }} / Bed: {{ $dataDaftarRi['bedNo'] ?? '-' }}
                                &nbsp;<x-badge :variant="$badgeVariant">{{ $klaimDesc }}</x-badge>
                            </p>
                            <p class="text-right text-xs text-gray-500 dark:text-gray-400">Tgl Masuk:
                                {{ $dataDaftarRi['entryDate'] ?? '-' }}</p>
                        </div>
                    </div>
                </div>
            @endif

            {{-- BODY --}}
            <div class="flex-1 overflow-y-auto px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20" x-data
                x-on:focus-cari-pasien-ri.window="$nextTick(() => setTimeout(() => $refs.lovPasienRi?.querySelector('input')?.focus(), 150))"
                x-on:focus-cari-dokter-ri.window="$nextTick(() => setTimeout(() => $refs.lovDokterRi?.querySelector('input')?.focus(), 150))">

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">

                    {{-- KOLOM 1: Pasien & Dokter --}}
                    <div
                        class="p-6 space-y-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Data
                            Pasien & Dokter</h3>

                        <div>
                            <x-toggle wire:model.live="dataDaftarRi.passStatus" trueValue="N" falseValue="O"
                                label="Pasien Baru" :disabled="$isFormLocked" />
                            <p class="mt-1 text-xs text-gray-400">Tidak dicentang = Pasien Lama.</p>
                        </div>

                        <div x-ref="lovPasienRi"
                            x-on:keydown.enter.prevent="$nextTick(() => $refs.lovDokterRi?.querySelector('input')?.focus())">
                            <livewire:lov.pasien.lov-pasien target="riFormPasien" :initialRegNo="$dataDaftarRi['regNo'] ?? ''" :disabled="$isFormLocked" />
                            <x-input-error :messages="$errors->get('dataDaftarRi.regNo')" class="mt-1" />
                        </div>

                        <div x-ref="lovDokterRi">
                            <livewire:lov.dokter.lov-dokter label="Cari Dokter DPJP RI" target="riFormDokter"
                                :initialDrId="$dataDaftarRi['drId'] ?? null" :disabled="$isFormLocked" />
                            <x-input-error :messages="$errors->get('dataDaftarRi.drId')" class="mt-1" />
                        </div>

                        @if (!empty($dataDaftarRi['kddrbpjs']))
                            <div
                                class="px-3 py-2 text-xs border border-gray-200 rounded-lg bg-gray-50 dark:bg-gray-800 dark:border-gray-700">
                                <span class="font-semibold">Kode Dr BPJS:</span> {{ $dataDaftarRi['kddrbpjs'] }}
                                &nbsp;|&nbsp;
                                <span class="font-semibold">Poli:</span> {{ $dataDaftarRi['poliDesc'] ?? '-' }}
                                ({{ $dataDaftarRi['kdpolibpjs'] ?? '-' }})
                            </div>
                        @endif
                    </div>

                    {{-- KOLOM 2: Kamar / Bangsal + Toggle --}}
                    <div class="p-6 space-y-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700"
                        wire:key="{{ $this->renderKey('bangsal', [$bangsalId, $dataDaftarRi['roomId'] ?? '']) }}">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Kamar
                            & Bangsal</h3>

                        {{-- LOV Room menggantikan 3 select manual (bangsal → ruang → bed).
                             Query sudah exclude kamar yang terisi (rsview_roominapes).
                             Listener: lov.selected.riFormRoom → riFormRoom() --}}
                        <div>
                            <livewire:lov.room.lov-room target="riFormRoom" label="Cari Ruangan / Bed *"
                                :initialRoomId="$dataDaftarRi['roomId'] ?? null" :disabled="$isFormLocked" />
                            <x-input-error :messages="$errors->get('dataDaftarRi.roomId')" class="mt-1" />
                            <x-input-error :messages="$errors->get('dataDaftarRi.bedNo')" class="mt-1" />
                            <x-input-error :messages="$errors->get('dataDaftarRi.bangsalId')" class="mt-1" />
                        </div>

                        {{-- Bangsal otomatis dari pilihan LOV Room --}}
                        @if (!empty($dataDaftarRi['bangsalDesc']))
                            <div
                                class="px-3 py-2 text-xs border border-gray-200 rounded-lg bg-gray-50 dark:bg-gray-800 dark:border-gray-700">
                                <span class="text-gray-500">Bangsal:</span>
                                <span class="ml-1 font-semibold">{{ $dataDaftarRi['bangsalDesc'] }}</span>
                                <span class="ml-2 text-gray-400">({{ $dataDaftarRi['bangsalId'] ?? '-' }})</span>
                            </div>
                        @endif

                        <div>
                            <x-input-label value="Status RI" />
                            <x-select-input wire:model.live="dataDaftarRi.riStatus" class="w-full mt-1"
                                :disabled="$isFormLocked">
                                <option value="I">Rawat Inap (Aktif)</option>
                                <option value="L">Pulang / Selesai</option>
                                <option value="P">Pindah Kamar</option>
                            </x-select-input>
                            <x-input-error :messages="$errors->get('dataDaftarRi.riStatus')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label value="Cara Masuk" />
                            <div class="grid grid-cols-2 gap-2 mt-2">
                                @foreach ($entryOptions as $entry)
                                    <x-radio-button :label="$entry['entryDesc']" :value="$entry['entryId']" name="entryId"
                                        wire:model.live="entryId" :disabled="$isFormLocked" />
                                @endforeach
                            </div>
                        </div>

                        {{-- Toggle: Admin Usia (par_id=3 dari rsmst_parameters) --}}
                        <div class="p-3 border rounded-lg bg-gray-50 dark:bg-gray-800 dark:border-gray-700 space-y-2">
                            <x-toggle wire:model.live="statusAdminAge" label="Kenakan Biaya Admin Usia"
                                :disabled="$isFormLocked" />
                            @if ($statusAdminAge)
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-gray-500">Nominal:</span>
                                    <span class="text-sm font-semibold text-blue-600 dark:text-blue-400">
                                        Rp {{ number_format($dataDaftarRi['adminAge'] ?? 0, 0, ',', '.') }}
                                    </span>
                                    <span class="text-xs text-gray-400">(parameter par_id = 3)</span>
                                </div>
                            @else
                                <p class="text-xs text-gray-400">Tidak dikenakan biaya admin usia.</p>
                            @endif
                        </div>

                        {{-- Toggle: Kasus Polisi / Medikolegal --}}
                        <div class="p-3 border rounded-lg bg-gray-50 dark:bg-gray-800 dark:border-gray-700">
                            <x-toggle wire:model.live="kasusPolisi" label="Kasus Polisi / Medikolegal"
                                :disabled="$isFormLocked" />
                            @if ($kasusPolisi)
                                <p class="mt-1 text-xs text-amber-600 dark:text-amber-400 font-medium">
                                    ⚠ Kasus ini melibatkan kepolisian / medikolegal.
                                </p>
                            @endif
                        </div>

                    </div>

                    {{-- KOLOM 3: Klaim & BPJS --}}
                    <div
                        class="p-6 space-y-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Klaim & BPJS</h3>

                        <div>
                            <x-input-label value="Jenis Klaim" />
                            <div class="grid grid-cols-3 gap-2 mt-2">
                                @foreach ($klaimOptions as $klaim)
                                    <x-radio-button :label="$klaim['klaimDesc']" :value="(string) $klaim['klaimId']" name="klaimId"
                                        wire:model.live="klaimId" :disabled="$isFormLocked" />
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('dataDaftarRi.klaimId')" class="mt-1" />
                        </div>

                        @if (($dataDaftarRi['klaimStatus'] ?? '') === 'BPJS' || ($dataDaftarRi['klaimId'] ?? '') === 'JM')
                            <div>
                                <x-input-label value="No Referensi / Rujukan" />
                                <x-text-input wire:model.live="dataDaftarRi.noReferensi" class="block w-full mt-1"
                                    :disabled="$isFormLocked" placeholder="No. Rujukan dari FKTP" />
                                <p class="mt-1 text-xs text-gray-400">Isi nomor rujukan dari Faskes Tingkat Pertama
                                    (FKTP).</p>
                                <x-input-error :messages="$errors->get('dataDaftarRi.noReferensi')" class="mt-1" />
                            </div>

                            <div class="space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    {{-- Tombol SPRI / SEP: hanya mode edit + klaim BPJS --}}
                                    @if ($formMode === 'edit' && (($dataDaftarRi['klaimStatus'] ?? '') === 'BPJS' || ($dataDaftarRi['klaimId'] ?? '') === 'JM'))
                                        <x-info-button type="button" wire:click="openVclaimModal"
                                            class="gap-2 text-xs">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            SPRI / SEP
                                        </x-info-button>
                                    @endif

                                    @if (!empty($dataDaftarRi['sep']['noSep']))
                                        <x-icon-button color="blue" type="button" wire:click="cetakSEP"
                                            title="Cetak SEP">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                            </svg>
                                        </x-icon-button>
                                        <div
                                            class="flex items-center gap-1 px-2 py-1 text-xs text-green-700 bg-green-100 rounded-full dark:bg-green-900/30 dark:text-green-300">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            SEP: {{ $dataDaftarRi['sep']['noSep'] }}
                                        </div>
                                    @endif
                                </div>

                                @if (!empty($dataDaftarRi['sep']['noSep']))
                                    <div
                                        class="flex items-center gap-2 px-3 py-2 text-sm border border-blue-200 rounded-lg bg-blue-50 dark:bg-blue-900/20 dark:border-blue-800">
                                        <div class="flex-1">
                                            <span class="text-xs font-medium text-blue-700 dark:text-blue-300">SEP
                                                RI:</span>
                                            <span
                                                class="ml-2 font-mono text-sm font-semibold text-blue-800 dark:text-blue-200">{{ $dataDaftarRi['sep']['noSep'] }}</span>
                                        </div>
                                        <span class="text-xs text-blue-500">
                                            {{ Carbon::parse($dataDaftarRi['sep']['resSep']['tglSEP'] ?? now())->format('d/m/Y') }}
                                        </span>
                                    </div>
                                @endif

                                @if (!empty($dataDaftarRi['spri']['noSPRIBPJS']))
                                    <div
                                        class="flex items-center gap-2 px-3 py-2 text-sm border border-purple-200 rounded-lg bg-purple-50 dark:bg-purple-900/20 dark:border-purple-800">
                                        <span class="text-xs font-medium text-purple-700 dark:text-purple-300">SPRI
                                            BPJS:</span>
                                        <span
                                            class="ml-1 font-mono text-sm font-semibold text-purple-800 dark:text-purple-200">{{ $dataDaftarRi['spri']['noSPRIBPJS'] }}</span>
                                    </div>
                                @endif

                                <livewire:pages::transaksi.ri.daftar-ri.vclaim-ri-actions :riHdrNo="$riHdrNo ?? null"
                                    wire:key="vclaim-ri-actions-{{ $riHdrNo ?? 'new' }}" />

                                <div>
                                    <x-input-label value="No SEP (manual)" />
                                    <x-text-input wire:model.live="dataDaftarRi.sep.noSep" class="block w-full mt-1"
                                        :disabled="$isFormLocked" />
                                </div>
                            </div>
                        @endif
                    </div>

                </div>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700 shrink-0">
                <div class="flex justify-between gap-3">
                    <a href="{{ route('master.pasien') }}" wire:navigate>
                        <x-ghost-button type="button">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            Master Pasien
                        </x-ghost-button>
                    </a>
                    <div class="flex gap-3">
                        <x-secondary-button wire:click="closeModal">Batal</x-secondary-button>
                        <x-primary-button wire:click.prevent="save()" class="min-w-[120px]"
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

    {{-- Cetak SEP --}}
    <livewire:pages::components.modul-dokumen.b-p-j-s.cetak-sep.cetak-sep wire:key="cetak-sep-ri" />

    {{-- iDRG (E-Klaim) Modal --}}
    <x-modal name="ri-idrg" size="full" height="full" focusable>
        @php
            $idrgData = $dataDaftarRi['idrg'] ?? [];
            $hasClaim = !empty($idrgData['nomorSep']);
            $idrgUngroup = !empty($idrgData['idrgUngroupable']);
            $idrgFinal = !empty($idrgData['idrgFinal']);
            $inacbgUngroup = !empty($idrgData['inacbgUngroupable']);
            $inacbgFinal = !empty($idrgData['inacbgFinal']);
            $klaimFinal = !empty($idrgData['klaimFinal']);
            $nomorSepKlaim = $dataDaftarRi['sep']['noSep'] ?? '-';
            $hasSepRaw = !empty($dataDaftarRi['sep']['noSep']);
            $hasClaimNumber = !empty($idrgData['claimNumber']);

            $sections = [
                [
                    'title' => 'A. Setup Klaim',
                    'steps' => [
                        ['step' => 'generate-number', 'num' => '1', 'title' => 'Generate Nomor Klaim', 'desc' => 'Opsional (pasien COVID/KIPI/Bayi Baru Lahir/Co-Insidense)', 'done' => $hasClaimNumber, 'disabled' => $hasClaim || $hasSepRaw],
                        ['step' => 'new-claim', 'num' => '2', 'title' => 'Buat Klaim Baru', 'desc' => 'Registrasi SEP pasien ke E-Klaim Kemenkes', 'done' => $hasClaim, 'disabled' => $hasClaim || (!$hasSepRaw && !$hasClaimNumber)],
                        ['step' => 'set-data', 'num' => '3', 'title' => 'Simpan Data Klaim', 'desc' => 'Kirim tarif RS + tanggal masuk/pulang + kelas rawat + status pulang ke E-Klaim', 'done' => !empty($idrgData['claimDataSavedAt']), 'disabled' => !$hasClaim || $idrgFinal],
                    ],
                ],
                [
                    'title' => 'B. Coding iDRG',
                    'steps' => [
                        ['step' => 'set-diagnosa-idrg', 'num' => '4', 'title' => 'Set Diagnosa iDRG', 'desc' => 'Auto dari EMR diagnosis[]', 'done' => !empty($idrgData['idrgDiagnosa']), 'disabled' => !$hasClaim || $idrgFinal],
                        ['step' => 'set-prosedur-idrg', 'num' => '5', 'title' => 'Set Prosedur iDRG', 'desc' => 'Auto dari EMR procedure[]', 'done' => !empty($idrgData['idrgProsedur']), 'disabled' => !$hasClaim || $idrgFinal],
                        ['step' => 'group-idrg', 'num' => '6', 'title' => 'Grouping iDRG', 'desc' => 'Jalankan grouper iDRG stage 1', 'done' => !empty($idrgData['idrgGroup']), 'disabled' => !$hasClaim || $idrgFinal],
                        ['step' => 'final-idrg', 'num' => '7', 'title' => 'Final iDRG', 'desc' => $idrgUngroup ? 'Tidak bisa dikelompokkan — tidak bisa final' : 'Finalisasi coding iDRG', 'done' => $idrgFinal, 'disabled' => !$hasClaim || empty($idrgData['idrgGroup']) || $idrgUngroup || $idrgFinal],
                        ['step' => 'reedit-idrg', 'num' => '↶', 'title' => 'Edit Ulang iDRG', 'desc' => 'Buka kembali coding iDRG', 'done' => false, 'disabled' => !$idrgFinal, 'visible' => $idrgFinal],
                        ['step' => 'import-inacbg', 'num' => '8', 'title' => 'Import Coding iDRG → INACBG', 'desc' => 'Import keseluruhan sekaligus', 'done' => !empty($idrgData['inacbgImportedAt']), 'disabled' => !$idrgFinal],
                    ],
                ],
                [
                    'title' => 'C. Coding INACBG',
                    'visible' => $idrgFinal,
                    'steps' => [
                        ['step' => 'set-diagnosa-inacbg', 'num' => '9', 'title' => 'Set Diagnosa INACBG', 'desc' => 'Override jika ada kode IM tidak berlaku', 'done' => !empty($idrgData['inacbgDiagnosa']), 'disabled' => !$idrgFinal || $inacbgFinal],
                        ['step' => 'set-prosedur-inacbg', 'num' => '10', 'title' => 'Set Prosedur INACBG', 'desc' => 'Override jika ada kode IM tidak berlaku', 'done' => !empty($idrgData['inacbgProsedur']), 'disabled' => !$idrgFinal || $inacbgFinal],
                        ['step' => 'group-inacbg-1', 'num' => '11', 'title' => 'Grouping INACBG Stage 1', 'desc' => 'Jalankan grouper INACBG stage 1', 'done' => !empty($idrgData['inacbgStage1']), 'disabled' => !$idrgFinal || $inacbgFinal],
                        ['step' => 'group-inacbg-2', 'num' => '12', 'title' => 'Grouping INACBG Stage 2', 'desc' => 'Hanya jika hasil Stage 1 minta pilihan tambahan (implant/prosthesis)', 'done' => !empty($idrgData['inacbgStage2']), 'disabled' => !$idrgFinal || $inacbgFinal || empty($idrgData['inacbgStage1'])],
                        ['step' => 'final-inacbg', 'num' => '13', 'title' => 'Final INACBG', 'desc' => $inacbgUngroup ? 'Tidak bisa dikelompokkan — tidak bisa final' : 'Finalisasi coding INACBG', 'done' => $inacbgFinal, 'disabled' => !$idrgFinal || $inacbgUngroup || $inacbgFinal || empty($idrgData['inacbgStage1'])],
                        ['step' => 'reedit-inacbg', 'num' => '↶', 'title' => 'Edit Ulang INACBG', 'desc' => 'Buka kembali coding INACBG', 'done' => false, 'disabled' => !$inacbgFinal || $klaimFinal, 'visible' => $inacbgFinal],
                    ],
                ],
                [
                    'title' => 'D. Finalisasi Klaim',
                    'visible' => $inacbgFinal,
                    'steps' => [
                        ['step' => 'final-klaim', 'num' => '14', 'title' => 'Final Klaim', 'desc' => 'Finalisasi klaim (otomatis pakai ID Karyawan user login)', 'done' => $klaimFinal, 'disabled' => !$inacbgFinal || $klaimFinal],
                        ['step' => 'reedit-klaim', 'num' => '↶', 'title' => 'Edit Ulang Klaim', 'desc' => 'Buka kembali finalisasi klaim', 'done' => false, 'disabled' => !$klaimFinal, 'visible' => $klaimFinal],
                        ['step' => 'send-klaim', 'num' => '15', 'title' => 'Kirim Klaim ke Data Center', 'desc' => 'Kirim klaim individu ke pusat data Kemenkes', 'done' => !empty($idrgData['sentAt']), 'disabled' => !$klaimFinal],
                        ['step' => 'print-klaim', 'num' => '16', 'title' => 'Cetak Klaim (PDF)', 'desc' => 'Hasil cetak PDF tampil di panel bawah, bisa didownload', 'done' => !empty($this->idrgPdfBase64), 'disabled' => !$klaimFinal],
                        ['step' => 'get-status', 'num' => '?', 'title' => 'Cek Status Klaim', 'desc' => 'Ambil status klaim dari BPJS', 'done' => !empty($idrgData['claimStatus']), 'disabled' => !$klaimFinal],
                    ],
                ],
            ];

            // Discharge status options (Permenkes: 1=Sembuh, 2=Rujuk, 3=APS, 4=Meninggal >48j, 5=Meninggal <48j, 6=Lain-lain)
            $dischargeOptions = [
                '1' => '1 — Sembuh',
                '2' => '2 — Dirujuk',
                '3' => '3 — Atas Permintaan Sendiri (APS)',
                '4' => '4 — Meninggal ≥48 jam',
                '5' => '5 — Meninggal <48 jam',
                '6' => '6 — Lain-lain',
            ];

            $guide = [
                ['key' => 'A', 'title' => 'A. Setup Klaim', 'items' => [
                    ['n' => 1, 'head' => 'Generate Nomor Klaim', 'body' => 'Hanya untuk pasien khusus (COVID-19, KIPI, Bayi Baru Lahir, Co-Insidense). Pasien BPJS biasa pakai SEP yang sudah ada.'],
                    ['n' => 2, 'head' => 'Buat Klaim Baru', 'body' => 'Registrasi SEP pasien ke aplikasi E-Klaim Kemenkes.'],
                    ['n' => 3, 'head' => 'Simpan Data Klaim', 'body' => 'Tarif RS + tanggal masuk/pulang otomatis dari rincian kasir RI. Kelas rawat otomatis dari kamar terakhir pasien. Status pulang pasien dari form di atas — ubah sebelum klik Jalankan.'],
                ]],
                ['key' => 'B', 'title' => 'B. Coding iDRG', 'items' => [
                    ['n' => 4, 'head' => 'Set Diagnosa iDRG', 'body' => 'Otomatis dari daftar diagnosis di EMR (Primary / utama di depan).'],
                    ['n' => 5, 'head' => 'Set Prosedur iDRG', 'body' => 'Otomatis dari daftar prosedur di EMR — mendukung jumlah tindakan (+N) & pemisah sesi operasi (#).'],
                    ['n' => 6, 'head' => 'Grouping iDRG', 'body' => 'Jalankan grouper untuk dapat kode DRG.'],
                    ['n' => 7, 'head' => 'Final iDRG', 'body' => 'Tombol nonaktif kalau hasil grouping tidak bisa dikelompokkan (MDC 36). Pakai Edit Ulang iDRG kalau perlu revisi.'],
                    ['n' => 8, 'head' => 'Import → INACBG', 'body' => 'Import seluruh kode sekaligus.'],
                ]],
                ['key' => 'C', 'title' => 'C. Coding INACBG (setelah iDRG final)', 'items' => [
                    ['n' => 9, 'head' => 'Set Diagnosa / Prosedur INACBG', 'body' => 'Kalau ada kode bertanda "IM tidak berlaku", ganti dengan kode non-IM.'],
                    ['n' => 10, 'head' => 'Grouping INACBG Stage 1', 'body' => 'Hasilnya kode CBG.'],
                    ['n' => 11, 'head' => 'Grouping Stage 2', 'body' => 'Hanya muncul kalau Stage 1 minta pilihan tambahan (implant/prosthesis).'],
                    ['n' => 12, 'head' => 'Final INACBG', 'body' => 'Tombol nonaktif kalau kode hasil diawali "X" (tidak bisa dikelompokkan).'],
                ]],
                ['key' => 'D', 'title' => 'D. Finalisasi Klaim (setelah INACBG final)', 'items' => [
                    ['n' => 14, 'head' => 'Final Klaim', 'body' => 'NIK coder otomatis dari ID Karyawan user yang login. Pastikan user sudah di-assign Karyawan di profil; kalau belum, sistem tampilkan error minta hubungi admin.'],
                    ['n' => 15, 'head' => 'Kirim Klaim', 'body' => 'Kirim klaim individu ke pusat data Kemenkes.'],
                    ['n' => 16, 'head' => 'Cetak Klaim', 'body' => 'PDF tampil di panel bawah, bisa didownload.'],
                ]],
                ['key' => '!', 'title' => '⚠ Penting — RI Ranap Saja', 'items' => [
                    ['n' => '—', 'head' => 'Scope Klaim RI', 'body' => 'iDRG di sini khusus pasien rawat inap yang sudah Pulang (kasir sudah close, tanggal pulang terisi). Jenis rawat: Rawat Inap.'],
                    ['n' => '—', 'head' => 'Status Pulang Pasien', 'body' => 'Wajib dipilih sebelum step 3 (Simpan Data Klaim) — menentukan klasifikasi Permenkes (sembuh / rujuk / APS / meninggal / lain). Bisa diubah ulang sebelum Final Klaim (step 14).'],
                    ['n' => '—', 'head' => 'Setelah iDRG Final', 'body' => 'Form EMR (diagnosa/prosedur) dikunci read-only. Pakai Edit Ulang iDRG kalau perlu revisi.'],
                    ['n' => '—', 'head' => 'Setelah Klaim Final', 'body' => 'Data dikunci penuh sampai Edit Ulang Klaim.'],
                ]],
            ];
        @endphp

        <div class="flex flex-col min-h-0">
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="relative flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand/10 dark:bg-brand-lime/15">
                            <svg class="w-6 h-6 text-brand dark:text-brand-lime" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Kirim iDRG / INACBG (E-Klaim Kemenkes)</h2>
                            @php $sepKosong = empty($nomorSepKlaim) || $nomorSepKlaim === '-'; @endphp
                            <p
                                class="mt-0.5 text-sm {{ $sepKosong ? 'font-semibold text-rose-600 dark:text-rose-400' : 'text-gray-500 dark:text-gray-400' }}">
                                <span class="font-semibold">{{ $dataDaftarRi['regName'] ?? '-' }}</span>
                                &mdash; RM: {{ $dataDaftarRi['regNo'] ?? '-' }}
                                &mdash; RI: {{ $riHdrNo ?? '-' }}
                                &mdash; SEP: <span
                                    class="font-mono font-semibold {{ $sepKosong ? '' : 'text-brand dark:text-brand-lime' }}">{{ $nomorSepKlaim }}</span>
                            </p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Bangsal: <span
                                    class="font-medium text-gray-700 dark:text-gray-300">{{ $dataDaftarRi['bangsalDesc'] ?? '-' }}{{ !empty($dataDaftarRi['bedNo']) ? ' / Bed ' . $dataDaftarRi['bedNo'] : '' }}</span>
                                &mdash; DPJP: <span
                                    class="font-medium text-gray-700 dark:text-gray-300">{{ $dataDaftarRi['drDesc'] ?? '-' }}</span>
                                &mdash; Tgl Masuk: <span class="font-medium text-gray-700 dark:text-gray-300">
                                    {{ !empty($dataDaftarRi['entryDate']) ? substr($dataDaftarRi['entryDate'], 0, 16) : '-' }}
                                </span>
                            </p>
                        </div>
                    </div>
                    <x-icon-button color="gray" type="button"
                        x-on:click="$dispatch('close-modal', { name: 'ri-idrg' })">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>

                {{-- Discharge status form (RI-specific) --}}
                <div class="mt-4 flex flex-wrap items-end gap-3 p-3 rounded-lg bg-amber-50/60 dark:bg-amber-900/10 border border-amber-200/60 dark:border-amber-800/40">
                    <div class="flex-1 min-w-[280px]">
                        <label class="block text-xs font-semibold text-amber-700 dark:text-amber-300 mb-1">
                            Status Pulang Pasien
                        </label>
                        <select wire:model.live="idrgDischargeStatus"
                            class="w-full text-sm border-amber-300 rounded-lg dark:bg-gray-900 dark:border-amber-700"
                            {{ $klaimFinal ? 'disabled' : '' }}>
                            @foreach ($dischargeOptions as $val => $label)
                                <option value="{{ $val }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-[11px] text-amber-600 dark:text-amber-400">
                            Dipakai saat step 3 (Simpan Data Klaim). Ubah sebelum klik "Jalankan" di step tersebut.
                        </p>
                    </div>
                    <div class="text-xs text-amber-700/80 dark:text-amber-300/70 max-w-xs">
                        <div class="font-semibold">Info otomatis:</div>
                        <div><span class="opacity-70">Jenis Rawat:</span> <span class="font-semibold">Rawat Inap</span></div>
                        <div><span class="opacity-70">Kelas Rawat:</span> diambil dari kamar terakhir pasien</div>
                        <div><span class="opacity-70">Tanggal Masuk / Pulang:</span> otomatis dari tanggal masuk RI &amp; tanggal pulang (saat kasir close)</div>
                    </div>
                </div>
            </div>

            <div class="flex-1 px-6 py-6 overflow-y-auto bg-gray-50/70 dark:bg-gray-950/20">
                <div class="max-w-7xl mx-auto space-y-6">
                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

                        {{-- LEFT — Cara Pakai (accordion) --}}
                        <div class="lg:sticky lg:top-0 lg:self-start">
                            <div class="bg-white border border-brand/30 shadow-sm rounded-xl dark:bg-gray-900 dark:border-brand-lime/30">
                                <div class="px-5 py-3 border-b border-brand/20 dark:border-brand-lime/20">
                                    <div class="flex items-center gap-3">
                                        <div class="flex items-center justify-center w-8 h-8 rounded-full bg-brand/10 dark:bg-brand-lime/15">
                                            <svg class="w-4 h-4 text-brand dark:text-brand-lime" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <div class="font-semibold text-gray-800 dark:text-gray-100">Cara Pakai — Alur iDRG / INACBG (Ranap)</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">iDRG dikerjakan paling awal sebelum INACBG. Diagnosa &amp; prosedur ditarik otomatis dari EMR.</div>
                                        </div>
                                    </div>
                                </div>

                                <div x-data="{ activeSec: {{ $hasClaim ? 'null' : "'A'" }} }" class="px-5 py-4">
                                    @foreach ($guide as $g)
                                        <div x-data="{ sec: '{{ $g['key'] }}' }">
                                            <button type="button"
                                                x-on:click="activeSec = (activeSec === sec) ? null : sec"
                                                class="flex items-center w-full gap-3 py-2 mt-1 text-left group/sec">
                                                <h4 class="text-xs font-bold tracking-wider uppercase whitespace-nowrap transition-colors text-gray-400 dark:text-gray-500 group-hover/sec:text-gray-600 dark:group-hover/sec:text-gray-300"
                                                    x-bind:class="activeSec === sec ? 'text-brand dark:text-brand-lime' : ''">
                                                    {{ $g['title'] }}</h4>
                                                <div class="flex-1 h-px bg-gray-200 dark:bg-gray-700"></div>
                                                <svg class="w-3.5 h-3.5 text-gray-400 shrink-0 transition-transform duration-200"
                                                    x-bind:class="activeSec === sec ? 'rotate-0' : '-rotate-90'"
                                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M19 9l-7 7-7-7" />
                                                </svg>
                                            </button>
                                            <div x-show="activeSec === sec"
                                                x-transition:enter="transition ease-out duration-200"
                                                x-transition:enter-start="opacity-0 -translate-y-2"
                                                x-transition:enter-end="opacity-100 translate-y-0"
                                                x-transition:leave="transition ease-in duration-150"
                                                x-transition:leave-start="opacity-100 translate-y-0"
                                                x-transition:leave-end="opacity-0 -translate-y-2"
                                                class="pb-3 space-y-2" style="display: none;">
                                                @foreach ($g['items'] as $item)
                                                    <div class="flex items-start gap-3 p-3 border border-gray-100 rounded-lg bg-gray-50 dark:bg-gray-800/50 dark:border-gray-700">
                                                        <div class="flex items-center justify-center w-7 h-7 rounded-full bg-brand/10 text-brand text-xs font-bold shrink-0 dark:bg-brand-lime/15 dark:text-brand-lime">
                                                            {{ $item['n'] }}</div>
                                                        <div class="text-sm text-gray-700 dark:text-gray-300">
                                                            <div class="font-semibold text-gray-800 dark:text-gray-100">{{ $item['head'] }}</div>
                                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $item['body'] }}</div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        {{-- RIGHT — Tombol aksi --}}
                        <div class="space-y-6">
                            @foreach ($sections as $section)
                                @if (!isset($section['visible']) || $section['visible'])
                                    <div class="space-y-3">
                                        <h3 class="text-sm font-semibold tracking-wide text-brand uppercase dark:text-brand-lime">
                                            {{ $section['title'] }}</h3>
                                        @foreach ($section['steps'] as $s)
                                            @if (!isset($s['visible']) || $s['visible'])
                                                <div class="flex items-center justify-between p-4 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700">
                                                    <div class="flex items-center gap-3">
                                                        <div class="flex items-center justify-center w-8 h-8 rounded-full {{ $s['done'] ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500' }}">
                                                            <span class="text-sm font-bold">{{ $s['num'] }}</span>
                                                        </div>
                                                        <div>
                                                            <div class="font-semibold text-gray-800 dark:text-gray-100">{{ $s['title'] }}</div>
                                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $s['desc'] }}</div>
                                                        </div>
                                                    </div>
                                                    <x-primary-button type="button"
                                                        wire:click="kirimIdrg('{{ $s['step'] }}')"
                                                        wire:loading.attr="disabled" :disabled="$s['disabled']"
                                                        class="!bg-brand hover:!bg-brand/90 {{ $s['done'] ? '!bg-emerald-600' : '' }}">
                                                        <span wire:loading.remove wire:target="kirimIdrg('{{ $s['step'] }}')">
                                                            {{ $s['done'] ? 'Selesai' : 'Jalankan' }}
                                                        </span>
                                                        <span wire:loading wire:target="kirimIdrg('{{ $s['step'] }}')"><x-loading /> ...</span>
                                                    </x-primary-button>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif
                            @endforeach
                        </div>

                    </div>

                    {{-- PDF Viewer --}}
                    @if (!empty($this->idrgPdfBase64))
                        <div class="p-4 bg-white border-2 border-brand/40 shadow-sm rounded-xl dark:bg-gray-900 dark:border-brand-lime/40">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-sm font-semibold text-brand dark:text-brand-lime">PDF Klaim — SEP {{ $this->idrgPdfSep ?? '-' }}</h3>
                                <a href="data:application/pdf;base64,{{ $this->idrgPdfBase64 }}"
                                    download="klaim-ri-{{ $this->idrgPdfSep ?? 'eklaim' }}.pdf"
                                    class="px-3 py-1.5 text-xs font-semibold text-white bg-brand rounded-lg hover:bg-brand/90">
                                    Download PDF</a>
                            </div>
                            <iframe src="data:application/pdf;base64,{{ $this->idrgPdfBase64 }}"
                                class="w-full h-[600px] border border-gray-200 rounded-lg dark:border-gray-700"
                                title="PDF Klaim E-Klaim"></iframe>
                        </div>
                    @endif

                </div>
            </div>
        </div>
    </x-modal>

    {{-- iDRG Components --}}
    <livewire:pages::transaksi.ri.idrg.kirim-claim wire:key="idrg-claim-ri" />
    <livewire:pages::transaksi.ri.idrg.kirim-diagnosa-idrg wire:key="idrg-diagnosa-ri" />
    <livewire:pages::transaksi.ri.idrg.kirim-prosedur-idrg wire:key="idrg-prosedur-ri" />
    <livewire:pages::transaksi.ri.idrg.kirim-grouping-idrg wire:key="idrg-grouping-ri" />
    <livewire:pages::transaksi.ri.idrg.kirim-grouping-inacbg wire:key="idrg-inacbg-ri" />
    <livewire:pages::transaksi.ri.idrg.kirim-final-klaim wire:key="idrg-klaim-ri" />
</div>
