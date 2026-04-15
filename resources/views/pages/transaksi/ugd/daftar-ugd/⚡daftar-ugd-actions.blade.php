<?php
// resources/views/pages/transaksi/ugd/daftar-ugd/daftar-ugd-actions.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\BPJS\VclaimTrait; // FIX #1: import tetap ada untuk use trait

new class extends Component {
    // CATATAN: VclaimTrait tidak di-use di sini untuk menghindari potensi method conflict
    // dengan trait lain (sendResponse, sendError, signature, dll bisa bentrok).
    // Gunakan VclaimTrait::method() static call langsung.
    use EmrUGDTrait, MasterPasienTrait, WithRenderVersioningTrait;

    public string $formMode = 'create';
    public bool $isFormLocked = false;

    public ?string $rjNo = null;
    public array $dataDaftarUGD = [];
    public array $dataPasien = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal', 'pasien', 'dokter'];

    /* ---- Klaim ---- */
    public string $klaimId = 'UM';
    public array $klaimOptions = [['klaimId' => 'UM', 'klaimDesc' => 'UMUM'], ['klaimId' => 'JM', 'klaimDesc' => 'BPJS'], ['klaimId' => 'JR', 'klaimDesc' => 'JASA RAHARJA'], ['klaimId' => 'JML', 'klaimDesc' => 'Asuransi Lain'], ['klaimId' => 'KR', 'klaimDesc' => 'Kronis']];

    /* ---- Cara Masuk UGD ---- */
    public string $entryId = '5';
    public array $entryOptions = [];

    /* ---- Status Lanjutan ---- */
    public string $statusLanjutan = 'BS';

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas(['modal', 'pasien', 'dokter']);
        $this->dataDaftarUGD = $this->getDefaultUGDTemplate();

        $this->entryOptions = DB::table('rsmst_entryugds')
            ->select('entry_id', 'entry_desc', 'rujukan_status')
            ->orderBy('entry_id')
            ->get()
            ->map(
                fn($r) => [
                    'entryId' => (string) $r->entry_id,
                    'entryDesc' => $r->entry_desc,
                    'rujukanStatus' => $r->rujukan_status,
                ],
            )
            ->toArray();
    }

    /* ===============================
     | OPEN CREATE
     =============================== */
    #[On('daftar-ugd.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode = 'create';
        $this->resetValidation();

        $this->dataDaftarUGD = $this->getDefaultUGDTemplate();

        $now = Carbon::now();
        $this->dataDaftarUGD['rjDate'] = $now->format('d/m/Y H:i:s');

        $findShift = DB::table('rstxn_shiftctls')
            ->select('shift')
            ->whereNotNull('shift_start')
            ->whereNotNull('shift_end')
            ->where('shift_start', '!=', '')
            ->where('shift_end', '!=', '')
            ->whereRaw('? BETWEEN shift_start AND shift_end', [$now->format('H:i:s')])
            ->first();

        $this->dataDaftarUGD['shift'] = (string) ($findShift?->shift ?? 1);
        $this->dataDaftarUGD['entryId'] = $this->entryId;
        $this->dataDaftarUGD['entryDesc'] = collect($this->entryOptions)->firstWhere('entryId', $this->entryId)['entryDesc'] ?? '';

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'ugd-actions');
        $this->dispatch('focus-cari-pasien-ugd');
    }

    /* ===============================
     | OPEN EDIT
     =============================== */
    #[On('daftar-ugd.openEdit')]
    public function openEdit(string $rjNo): void
    {
        $this->resetForm();
        $this->rjNo = $rjNo;
        $this->formMode = 'edit';
        $this->resetValidation();

        $data = $this->findDataUGD($rjNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.');
            return;
        }

        if ($this->checkUGDStatus($rjNo)) {
            $this->isFormLocked = true;
            $this->dispatch('toast', type: 'warning', message: 'Data UGD ini sudah selesai dan tidak bisa diubah.');
        }

        $this->dataDaftarUGD = $data;
        $this->dataPasien = $this->findDataMasterPasien($this->dataDaftarUGD['regNo'] ?? '');
        $this->syncFromDataDaftarUGD();

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'ugd-actions');
    }

    /* ===============================
     | CLOSE MODAL
     =============================== */
    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'ugd-actions');
    }

    /* ===============================
     | SAVE
     |
     | Pola:
     |   1. Guard read-only
     |   2. setDataPrimer() + validateDataUGD()
     |   3. SEP API DI LUAR transaksi
     |   4. DB::transaction: lock (edit only) + insert/update + updateJsonData()
     |   5. afterSave() DI LUAR transaksi
     =============================== */
    public function save(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }

        $this->setDataPrimer();
        $this->validateDataUGD();

        $rjNo = $this->dataDaftarUGD['rjNo'] ?? null;
        if (!$rjNo) {
            $this->dispatch('toast', type: 'error', message: 'Nomor RJ tidak valid.');
            return;
        }

        try {
            // ============================================================
            // 1. SEP API — di luar transaksi
            // ============================================================
            $isBpjs = ($this->dataDaftarUGD['klaimStatus'] ?? '') === 'BPJS' || ($this->dataDaftarUGD['klaimId'] ?? '') === 'JM';

            if ($isBpjs) {
                $this->handleSepCreation();
            }

            // ============================================================
            // 2. DB TRANSACTION
            // ============================================================
            $message = '';

            if ($this->formMode === 'create') {
                Cache::lock("lock:rstxn_ugdhdrs:{$rjNo}", 15)->block(5, function () use ($rjNo, &$message) {
                    DB::transaction(function () use ($rjNo, &$message) {
                        DB::table('rstxn_ugdhdrs')->insert($this->buildPayload($rjNo, 'create'));
                        $this->updateJsonData($rjNo);
                        $message = 'Data UGD berhasil disimpan.';
                    });
                });
            } else {
                DB::transaction(function () use ($rjNo, &$message) {
                    $this->lockUGDRow($rjNo);
                    DB::table('rstxn_ugdhdrs')->where('rj_no', $rjNo)->update($this->buildPayload($rjNo, 'update'));
                    $this->updateJsonData($rjNo);
                    $message = 'Data UGD berhasil diperbarui.';
                });
            }

            // ============================================================
            // 3. AFTER SAVE — di luar transaksi
            // ============================================================
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
     =============================== */
    private function buildPayload(int|string $rjNo, string $mode): array
    {
        $base = [
            'rj_no' => $rjNo,
            'rj_date' => DB::raw("to_date('{$this->dataDaftarUGD['rjDate']}','dd/mm/yyyy hh24:mi:ss')"),
            'reg_no' => $this->dataDaftarUGD['regNo'],
            'nobooking' => $this->dataDaftarUGD['noBooking'],
            'no_antrian' => $this->dataDaftarUGD['noAntrian'] ?? 1,
            'klaim_id' => $this->dataDaftarUGD['klaimId'],
            'entry_id' => $this->dataDaftarUGD['entryId'],
            'poli_id' => null,
            'dr_id' => $this->dataDaftarUGD['drId'],
            'shift' => $this->dataDaftarUGD['shift'] ?? 3,
            'txn_status' => $this->dataDaftarUGD['txnStatus'] ?? 'A',
            'rj_status' => $this->dataDaftarUGD['rjStatus'] ?? 'A',
            'erm_status' => $this->dataDaftarUGD['ermStatus'] ?? 'A',
            'pass_status' => ($this->dataDaftarUGD['passStatus'] ?? 'O') === 'N' ? 'N' : 'O',
            'cek_lab' => $this->dataDaftarUGD['cekLab'] ?? 0,
            'sl_codefrom' => $this->dataDaftarUGD['slCodeFrom'] ?? '02',
            'kunjungan_internal_status' => $this->dataDaftarUGD['kunjunganInternalStatus'] ?? 0,
            'push_antrian_bpjs_status' => null,
            'push_antrian_bpjs_json' => null,
            'waktu_masuk_pelayanan' => DB::raw("to_date('{$this->dataDaftarUGD['rjDate']}','dd/mm/yyyy hh24:mi:ss')"),
            'vno_sep' => $this->dataDaftarUGD['sep']['noSep'] ?? '',
        ];

        if ($mode === 'create') {
            $base['status_lanjutan'] = 'BS';
            $base['death_on_igd_status'] = 'N';
            $base['before_after'] = 'B';
            $base['out_desc'] = 'RAWAT';
        }

        return $base;
    }

    /* ===============================
     | SET DATA PRIMER
     =============================== */
    private function setDataPrimer(): void
    {
        $data = &$this->dataDaftarUGD;

        $data['entryId'] = $this->entryId;
        $data['entryDesc'] = collect($this->entryOptions)->firstWhere('entryId', $this->entryId)['entryDesc'] ?? '';

        if (empty($data['noBooking'])) {
            $data['noBooking'] = Carbon::now()->format('YmdHis') . 'RSIM';
        }

        if (empty($data['rjNo'])) {
            $maxRjNo = DB::table('rstxn_ugdhdrs')->max('rj_no');
            $data['rjNo'] = $maxRjNo ? $maxRjNo + 1 : 1;
        }

        if (empty($data['noAntrian'])) {
            if (!empty($data['klaimId']) && $data['klaimId'] !== 'KR') {
                $tglAntrian = Carbon::createFromFormat('d/m/Y H:i:s', $data['rjDate'])->format('dmY');
                $count = DB::table('rstxn_ugdhdrs')
                    ->where('dr_id', $data['drId'])
                    ->where('klaim_id', '!=', 'KR')
                    ->whereRaw("to_char(rj_date,'ddmmyyyy') = ?", [$tglAntrian])
                    ->count();
                $data['noAntrian'] = $count + 1;
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
     | VALIDATE DATA UGD
     |
     | ⚠️  UGD: noReferensi TIDAK required meski BPJS
     =============================== */
    private function validateDataUGD(): void
    {
        $this->validate([
            'dataDaftarUGD.regNo' => 'bail|required|exists:rsmst_pasiens,reg_no',
            'dataDaftarUGD.drId' => 'required|exists:rsmst_doctors,dr_id',
            'dataDaftarUGD.drDesc' => 'required|string',
            'dataDaftarUGD.rjDate' => 'required|date_format:d/m/Y H:i:s',
            'dataDaftarUGD.rjNo' => 'required|numeric',
            'dataDaftarUGD.shift' => 'required|in:1,2,3',
            'dataDaftarUGD.noAntrian' => 'required|numeric|min:1|max:999',
            'dataDaftarUGD.noBooking' => 'required|string',
            'dataDaftarUGD.slCodeFrom' => 'required|in:01,02',
            'dataDaftarUGD.rjStatus' => 'required|in:A,L,I',
            'dataDaftarUGD.txnStatus' => 'required|in:A,L,H',
            'dataDaftarUGD.ermStatus' => 'required|in:A,L',
            'dataDaftarUGD.cekLab' => 'required|in:0,1',
            'dataDaftarUGD.kunjunganInternalStatus' => 'required|in:0,1',
            'dataDaftarUGD.klaimId' => 'required|exists:rsmst_klaimtypes,klaim_id',
            'dataDaftarUGD.noReferensi' => 'nullable|string|min:3|max:19',
        ]);
    }

    /* ===============================
     | UPDATE JSON DATA
     =============================== */
    private function updateJsonData(int|string $rjNo): void
    {
        $allowedFields = ['regNo', 'drId', 'drDesc', 'klaimId', 'klaimStatus', 'entryId', 'entryDesc', 'rjDate', 'shift', 'noAntrian', 'noBooking', 'slCodeFrom', 'passStatus', 'rjStatus', 'txnStatus', 'ermStatus', 'cekLab', 'kunjunganInternalStatus', 'noReferensi', 'taskIdPelayanan', 'sep'];

        if ($this->formMode === 'create') {
            $this->updateJsonUGD((int) $rjNo, $this->dataDaftarUGD);
            return;
        }

        $existing = $this->findDataUGD($rjNo);

        if (empty($existing)) {
            throw new \RuntimeException('Data UGD tidak ditemukan saat update JSON, simpan dibatalkan.');
        }

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $this->dataDaftarUGD)) {
                $existing[$field] = $this->dataDaftarUGD[$field];
            }
        }

        $this->updateJsonUGD((int) $rjNo, $existing);
    }

    /* ===============================
     | AFTER SAVE
     =============================== */
    private function afterSave(string $message): void
    {
        // Jika create → switch ke edit mode, tetap di modal
        if ($this->formMode === 'create') {
            $this->formMode = 'edit';
            $this->rjNo = $this->dataDaftarUGD['rjNo'];
        }

        $this->syncFromDataDaftarUGD();

        $noSep = $this->dataDaftarUGD['sep']['noSep'] ?? '';
        $sepInfo = $noSep ? " | SEP: {$noSep}" : '';

        $this->dispatch('toast', type: 'success', message: $message . $sepInfo);
        $this->dispatch('refresh-after-ugd.saved');
    }

    /* ===============================
     | DB ERROR HANDLER
     =============================== */
    private function handleDatabaseError(QueryException $e): void
    {
        $code = $e->errorInfo[1] ?? 0;
        $map = [
            1 => 'Duplikasi data, record sudah ada.',
            1400 => 'Field wajib tidak boleh kosong.',
            2291 => 'Data referensi tidak valid.',
            2292 => 'Data sedang digunakan.',
        ];
        $this->dispatch('toast', type: 'error', message: $map[$code] ?? 'Kesalahan database: ' . $e->getMessage());
    }

    /* ===============================
     | SEP HANDLERS — di luar transaksi
     =============================== */
    private function handleSepCreation(): void
    {
        $sudahAdaSEP = !empty($this->dataDaftarUGD['sep']['noSep']);
        $hasReqSep = !empty($this->dataDaftarUGD['sep']['reqSep']);

        if (!$sudahAdaSEP && $hasReqSep) {
            $this->pushInsertSEP($this->dataDaftarUGD['sep']['reqSep']);
        } elseif ($sudahAdaSEP && $hasReqSep) {
            $this->pushUpdateSEP($this->dataDaftarUGD['sep']['reqSep']);
        }
    }

    private function pushInsertSEP(array $reqSep): void
    {
        if (empty($reqSep)) {
            return;
        }
        try {
            // Static call — VclaimTrait tidak di-use di class ini (potensi method conflict)
            $response = VclaimTrait::sep_insert($reqSep)->getOriginalContent();
            $code = $response['metadata']['code'] ?? 500;

            if ($code == 200) {
                $sepData = $response['response']['sep'] ?? null;
                if ($sepData) {
                    $this->dataDaftarUGD['sep']['noSep'] = $sepData['noSep'] ?? '';
                    $this->dataDaftarUGD['sep']['resSep'] = $sepData;
                    $this->dispatch('toast', type: 'success', message: "SEP berhasil dibuat: {$sepData['noSep']}");
                }
            } else {
                $msg = $response['metadata']['message'] ?? 'Gagal membuat SEP';
                $this->dispatch('toast', type: 'error', message: "SEP gagal ({$code}): {$msg}");
            }
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Error SEP: ' . $e->getMessage());
        }
    }

    private function pushUpdateSEP(array $reqSep): void
    {
        if (empty($reqSep) || empty($this->dataDaftarUGD['sep']['noSep'])) {
            return;
        }
        try {
            $noSep = $this->dataDaftarUGD['sep']['noSep'];
            $t = $reqSep['request']['t_sep'] ?? [];

            $payload = [
                'request' => [
                    't_sep' => [
                        'noSep' => $noSep,
                        'klsRawat' => $t['klsRawat'] ?? [],
                        'noMR' => $t['noMR'] ?? '',
                        'catatan' => $t['catatan'] ?? '',
                        'diagAwal' => $t['diagAwal'] ?? '',
                        'poli' => ['tujuan' => $t['poli']['tujuan'] ?? 'IGD', 'eksekutif' => '0'],
                        'cob' => ['cob' => '0'],
                        'katarak' => ['katarak' => '0'],
                        'jaminan' => $t['jaminan'] ?? ['lakaLantas' => '0'],
                        'dpjpLayan' => $t['dpjpLayan'] ?? '',
                        'noTelp' => $t['noTelp'] ?? '',
                        'user' => 'siRUS',
                    ],
                ],
            ];

            // Static call — VclaimTrait tidak di-use di class ini (potensi method conflict)
            $response = VclaimTrait::sep_update($payload)->getOriginalContent();
            $code = $response['metadata']['code'] ?? 500;
            $msg = $response['metadata']['message'] ?? '';
            $this->dispatch('toast', type: $code == 200 ? 'success' : 'error', message: "Update SEP ({$code}): {$msg}");
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Error Update SEP: ' . $e->getMessage());
        }
    }

    /* ===============================
     | LOV LISTENERS
     =============================== */
    #[On('lov.selected.ugdFormPasien')]
    public function ugdFormPasien(string $target, array $payload): void
    {
        $this->dataDaftarUGD['regNo'] = $payload['reg_no'] ?? '';
        $this->dataDaftarUGD['regName'] = $payload['reg_name'] ?? '';
        $this->dataPasien = $this->findDataMasterPasien($this->dataDaftarUGD['regNo']);
        $this->incrementVersion('pasien');
        $this->incrementVersion('modal');
        $this->dispatch('focus-cari-dokter-ugd');
    }

    #[On('lov.selected.ugdFormDokter')]
    public function ugdFormDokter(string $target, array $payload): void
    {
        $this->dataDaftarUGD['drId'] = $payload['dr_id'] ?? '';
        $this->dataDaftarUGD['drDesc'] = $payload['dr_name'] ?? '';
        $this->dataDaftarUGD['kddrbpjs'] = $payload['kd_dr_bpjs'] ?? '';
        $this->dataDaftarUGD['kdpolibpjs'] = $payload['kd_poli_bpjs'] ?? '';
        $this->incrementVersion('dokter');
        $this->incrementVersion('modal');
    }

    #[On('sep-generated-ugd')]
    public function handleSepGenerated(array $reqSep): void
    {
        $this->dataDaftarUGD['sep']['reqSep'] = $reqSep;

        $noRujukan = $reqSep['request']['t_sep']['rujukan']['noRujukan'] ?? null;
        if ($noRujukan) {
            $this->dataDaftarUGD['noReferensi'] = $noRujukan;
        }

        $this->incrementVersion('modal');
    }

    /* ===============================
     | VCLAIM
     =============================== */
    public function openVclaimModal(): void
    {
        if (empty($this->dataDaftarUGD['regNo'])) {
            $this->dispatch('toast', type: 'error', message: 'Pilih pasien terlebih dahulu.');
            return;
        }

        $isBpjs = ($this->dataDaftarUGD['klaimStatus'] ?? '') === 'BPJS' || ($this->dataDaftarUGD['klaimId'] ?? '') === 'JM';

        if (!$isBpjs) {
            $this->dispatch('toast', type: 'error', message: 'Fitur SEP hanya untuk pasien BPJS.');
            return;
        }

        $this->dispatch('open-vclaim-modal-ugd', rjNo: $this->rjNo, regNo: $this->dataDaftarUGD['regNo'], drId: $this->dataDaftarUGD['drId'], drDesc: $this->dataDaftarUGD['drDesc'], poliId: 'UGD', poliDesc: 'Instalasi Gawat Darurat', kdpolibpjs: $this->dataDaftarUGD['kdpolibpjs'] ?? null, noReferensi: $this->dataDaftarUGD['noReferensi'] ?? null, sepData: $this->dataDaftarUGD['sep'] ?? []);
    }

    public function cetakSEP(): void
    {
        if (empty($this->dataDaftarUGD['sep']['noSep'])) {
            $this->dispatch('toast', type: 'error', message: 'Tidak ada SEP untuk dicetak.');
            return;
        }
        $this->dispatch('cetak-sep-ugd.open', rjNo: $this->rjNo);
    }

    /* ===============================
     | UPDATED HOOKS
     =============================== */
    public function updated(string $name, mixed $value): void
    {
        if ($name === 'klaimId') {
            $this->klaimId = $value;
            $this->dataDaftarUGD['klaimId'] = $value;
            $this->dataDaftarUGD['klaimStatus'] = DB::table('rsmst_klaimtypes')->where('klaim_id', $value)->value('klaim_status') ?? 'UMUM';
            $this->incrementVersion('modal');
        }
    }

    /* ===============================
     | HELPERS
     =============================== */
    protected function resetForm(): void
    {
        $this->reset(['rjNo', 'dataDaftarUGD', 'dataPasien']);
        $this->resetVersion();
        $this->klaimId = 'UM';
        $this->entryId = '5';
        $this->statusLanjutan = 'BS';
        $this->formMode = 'create';
        $this->isFormLocked = false;
    }

    private function syncFromDataDaftarUGD(): void
    {
        $this->klaimId = $this->dataDaftarUGD['klaimId'] ?? 'UM';
        $this->entryId = $this->dataDaftarUGD['entryId'] ?? '5';
        $this->statusLanjutan = $this->dataDaftarUGD['statusLanjutan'] ?? 'BS';
    }
};
?>

<div>
    <x-modal name="ugd-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal', [$formMode, $rjNo ?? 'new']) }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06]"
                    style="background-image:radial-gradient(currentColor 1px,transparent 1px);background-size:14px 14px;">
                </div>
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-red-500/10">
                                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $formMode === 'edit' ? 'Ubah Data UGD' : 'Tambah Data UGD' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Kelola pendaftaran dan
                                    pelayanan pasien Unit Gawat Darurat.</p>
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

                    {{-- Tanggal & Shift --}}
                    <div class="flex gap-4">
                        <div class="flex-1">
                            <x-input-label value="Tanggal Masuk UGD" />
                            <x-text-input wire:model.live="dataDaftarUGD.rjDate" class="block w-full"
                                :disabled="$isFormLocked" />
                            <x-input-error :messages="$errors->get('dataDaftarUGD.rjDate')" class="mt-1" />
                        </div>
                        <div class="w-36">
                            <x-input-label value="Shift" />
                            <x-select-input wire:model.live="dataDaftarUGD.shift" class="w-full mt-1" :disabled="$isFormLocked">
                                <option value="">-- Shift --</option>
                                <option value="1">Shift 1</option>
                                <option value="2">Shift 2</option>
                                <option value="3">Shift 3</option>
                            </x-select-input>
                            <x-input-error :messages="$errors->get('dataDaftarUGD.shift')" class="mt-1" />
                        </div>
                    </div>

                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20" x-data
                x-on:focus-cari-pasien-ugd.window="$nextTick(() => setTimeout(() => $refs.lovPasienUgd?.querySelector('input')?.focus(), 150))"
                x-on:focus-cari-dokter-ugd.window="$nextTick(() => setTimeout(() => $refs.lovDokterUgd?.querySelector('input')?.focus(), 150))">

                <div class="grid grid-cols-1 gap-4 max-w-full mx-auto lg:grid-cols-2">

                    {{-- KOLOM KIRI --}}
                    <div
                        class="p-6 space-y-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                        <div>
                            <x-toggle wire:model.live="dataDaftarUGD.passStatus" trueValue="N" falseValue="O"
                                label="Pasien Baru" :disabled="$isFormLocked" />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Tidak dicentang = Pasien Lama.</p>
                        </div>
                        <div x-ref="lovPasienUgd"
                            x-on:keydown.enter.prevent="$nextTick(() => $refs.lovDokterUgd?.querySelector('input')?.focus())">
                            <livewire:lov.pasien.lov-pasien target="ugdFormPasien" :initialRegNo="$dataDaftarUGD['regNo'] ?? ''"
                                :disabled="$isFormLocked" />
                            <x-input-error :messages="$errors->get('dataDaftarUGD.regNo')" class="mt-1" />
                        </div>
                        <div x-ref="lovDokterUgd">
                            <livewire:lov.dokter.lov-dokter label="Cari Dokter UGD" target="ugdFormDokter"
                                :initialDrId="$dataDaftarUGD['drId'] ?? null" :disabled="$isFormLocked" />
                            <x-input-error :messages="$errors->get('dataDaftarUGD.drId')" class="mt-1" />
                            <x-input-error :messages="$errors->get('dataDaftarUGD.drDesc')" class="mt-1" />
                        </div>
                    </div>

                    {{-- KOLOM KANAN --}}
                    <div
                        class="p-6 space-y-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                        <div>
                            <x-input-label value="Cara Masuk UGD" />
                            <div class="grid grid-cols-2 gap-2 mt-2 sm:grid-cols-3">
                                @foreach ($entryOptions as $entry)
                                    <x-radio-button :label="$entry['entryDesc']" :value="$entry['entryId']" name="entryId"
                                        wire:model.live="entryId" :disabled="$isFormLocked" />
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <x-input-label value="Jenis Klaim" />
                            <div class="grid grid-cols-5 gap-2 mt-2">
                                @foreach ($klaimOptions as $klaim)
                                    <x-radio-button :label="$klaim['klaimDesc']" :value="(string) $klaim['klaimId']" name="klaimId"
                                        wire:model.live="klaimId" :disabled="$isFormLocked" />
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('dataDaftarUGD.klaimId')" class="mt-1" />
                        </div>

                        @if (($dataDaftarUGD['klaimStatus'] ?? '') === 'BPJS' || ($dataDaftarUGD['klaimId'] ?? '') === 'JM')

                            {{-- SEP --}}
                            <div class="space-y-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-info-button type="button" wire:click="openVclaimModal"
                                        class="gap-2 text-xs">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        Kelola SEP BPJS
                                    </x-info-button>

                                    @if (!empty($dataDaftarUGD['sep']['noSep']))
                                        <x-icon-button color="blue" type="button" wire:click="cetakSEP"
                                            title="Cetak SEP">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                            </svg>
                                        </x-icon-button>
                                        <div
                                            class="flex items-center gap-2 px-3 py-1 text-xs text-green-700 bg-green-100 rounded-full dark:bg-green-900/30 dark:text-green-300">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            SEP: {{ $dataDaftarUGD['sep']['noSep'] }}
                                        </div>
                                    @endif
                                </div>

                                @if (!empty($dataDaftarUGD['sep']['noSep']))
                                    <div
                                        class="flex items-center gap-2 px-3 py-2 text-sm border border-blue-200 rounded-lg bg-blue-50 dark:bg-blue-900/20 dark:border-blue-800">
                                        <svg class="w-5 h-5 text-blue-500 shrink-0" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <div class="flex-1">
                                            <span class="text-xs font-medium text-blue-700 dark:text-blue-300">SEP
                                                Aktif:</span>
                                            <span
                                                class="ml-2 font-mono text-sm font-semibold text-blue-800 dark:text-blue-200">{{ $dataDaftarUGD['sep']['noSep'] }}</span>
                                        </div>
                                        <span class="text-xs text-blue-600 dark:text-blue-400">
                                            {{ Carbon::parse($dataDaftarUGD['sep']['resSep']['tglSEP'] ?? now())->format('d/m/Y') }}
                                        </span>
                                    </div>
                                @endif

                                <livewire:pages::transaksi.ugd.daftar-ugd.vclaim-ugd-actions :rjNo="$rjNo ?? null"
                                    wire:key="vclaim-ugd-actions-{{ $rjNo ?? 'new' }}" />

                                <div>
                                    <x-input-label value="No SEP" />
                                    <x-text-input wire:model.live="dataDaftarUGD.sep.noSep" class="block w-full mt-1"
                                        :disabled="$isFormLocked" />
                                </div>
                            </div>
                        @endif

                        @if (!empty($dataDaftarUGD['kddrbpjs']))
                            <div
                                class="px-3 py-2 text-xs border border-gray-200 rounded-lg bg-gray-50 dark:bg-gray-800 dark:border-gray-700">
                                <span class="font-semibold">Kode Dr BPJS:</span> {{ $dataDaftarUGD['kddrbpjs'] }}
                                <span class="ml-3 font-semibold">Kode Poli BPJS:</span>
                                {{ $dataDaftarUGD['kdpolibpjs'] ?? '-' }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
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
                        <x-secondary-button wire:click="closeModal">
                            Batal
                        </x-secondary-button>
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
    <livewire:pages::components.modul-dokumen.b-p-j-s.cetak-sep.cetak-sep wire:key="cetak-sep-ugd" />
</div>
