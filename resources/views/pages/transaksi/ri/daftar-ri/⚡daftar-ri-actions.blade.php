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

    /* ---- Bangsal / Ruang / Bed ---- */
    public string $bangsalId = '';
    public array $bangsalOptions = [];
    public array $roomOptions = [];
    public array $bedOptions = [];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas(['modal', 'pasien', 'dokter', 'bangsal']);
        $this->dataDaftarRi = $this->getDefaultRITemplate();

        $this->entryOptions = DB::table('rsmst_entryugds')->select('entry_id', 'entry_desc')->orderBy('entry_id')->get()->map(fn($r) => ['entryId' => (string) $r->entry_id, 'entryDesc' => $r->entry_desc])->toArray();

        $this->bangsalOptions = DB::table('rsmst_bangsals')->select('bangsal_id', 'bangsal_name')->orderBy('bangsal_name')->get()->map(fn($r) => ['bangsalId' => $r->bangsal_id, 'bangsalName' => $r->bangsal_name])->toArray();
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
            ->whereRaw('? BETWEEN shift_start AND shift_end', [$now->format('H:i:s')])
            ->first();
        $this->dataDaftarRi['shift'] = (string) ($findShift->shift ?? 1);

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
        $this->loadRoomOptions($this->bangsalId);
        $this->loadBedOptions($this->dataDaftarRi['roomId'] ?? '');

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

        try {
            $isBpjs = ($this->dataDaftarRi['klaimStatus'] ?? '') === 'BPJS' || ($this->dataDaftarRi['klaimId'] ?? '') === 'JM';
            if ($isBpjs) {
                $this->handleSepCreation();
            }

            $message = '';
            if ($this->formMode === 'create') {
                Cache::lock("lock:rstxn_rihdrs:{$riHdrNo}", 15)->block(5, function () use ($riHdrNo, &$message) {
                    DB::transaction(function () use ($riHdrNo, &$message) {
                        DB::table('rstxn_rihdrs')->insert($this->buildPayload($riHdrNo, 'create'));
                        $this->updateJsonData($riHdrNo);
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
     =============================== */
    private function buildPayload(int|string $riHdrNo, string $mode): array
    {
        $base = [
            'rihdr_no' => $riHdrNo,
            'entry_date' => DB::raw("to_date('{$this->dataDaftarRi['entryDate']}','dd/mm/yyyy hh24:mi:ss')"),
            'reg_no' => $this->dataDaftarRi['regNo'],
            'nobooking' => $this->dataDaftarRi['noBooking'],
            'no_antrian' => $this->dataDaftarRi['noAntrian'] ?? 1,
            'klaim_id' => $this->dataDaftarRi['klaimId'],
            'entry_id' => $this->dataDaftarRi['entryId'],
            'dr_id' => $this->dataDaftarRi['drId'],
            'poli_id' => $this->dataDaftarRi['poliId'] ?? null,
            'bangsal_id' => $this->dataDaftarRi['bangsalId'],
            'room_id' => $this->dataDaftarRi['roomId'],
            'bed_no' => $this->dataDaftarRi['bedNo'],
            'shift' => $this->dataDaftarRi['shift'] ?? 1,
            'ri_status' => $this->dataDaftarRi['riStatus'] ?? 'I',
            'erm_status' => $this->dataDaftarRi['ermStatus'] ?? 'A',
            'sl_codefrom' => $this->dataDaftarRi['slCodeFrom'] ?? '01',
            'vno_sep' => $this->dataDaftarRi['sep']['noSep'] ?? '',
            'no_referensi' => $this->dataDaftarRi['noReferensi'] ?? '',
        ];
        if ($mode === 'create') {
            $base['before_after'] = 'B';
            $base['death_status'] = 'N';
            $base['txn_status'] = 'A';
        }
        return $base;
    }

    /* ===============================
     | SET DATA PRIMER
     =============================== */
    private function setDataPrimer(): void
    {
        $data = &$this->dataDaftarRi;
        $data['entryId'] = $this->entryId;
        $data['entryDesc'] = collect($this->entryOptions)->firstWhere('entryId', $this->entryId)['entryDesc'] ?? '';
        $data['bangsalId'] = $this->bangsalId;

        if (empty($data['noBooking'])) {
            $data['noBooking'] = Carbon::now()->format('YmdHis') . 'RSIMRI';
        }
        if (empty($data['riHdrNo'])) {
            $maxNo = DB::table('rstxn_rihdrs')->max('rihdr_no');
            $data['riHdrNo'] = $maxNo ? $maxNo + 1 : 1;
        }
        if (empty($data['noAntrian'])) {
            $tglAntrian = Carbon::createFromFormat('d/m/Y H:i:s', $data['entryDate'])->format('dmY');
            $count = DB::table('rstxn_rihdrs')
                ->where('dr_id', $data['drId'])
                ->whereRaw("to_char(entry_date,'ddmmyyyy') = ?", [$tglAntrian])
                ->count();
            $data['noAntrian'] = $count + 1;
        }
    }

    /* ===============================
     | VALIDATE DATA RI
     =============================== */
    private function validateDataRI(): void
    {
        $this->validate([
            'dataDaftarRi.regNo' => 'bail|required|exists:rsmst_pasiens,reg_no',
            'dataDaftarRi.drId' => 'required|exists:rsmst_doctors,dr_id',
            'dataDaftarRi.drDesc' => 'required|string',
            'dataDaftarRi.entryDate' => 'required|date_format:d/m/Y H:i:s',
            'dataDaftarRi.riHdrNo' => 'required|numeric',
            'dataDaftarRi.bangsalId' => 'required|exists:rsmst_bangsals,bangsal_id',
            'dataDaftarRi.roomId' => 'required',
            'dataDaftarRi.bedNo' => 'required',
            'dataDaftarRi.shift' => 'required|in:1,2,3',
            'dataDaftarRi.noAntrian' => 'required|numeric|min:1',
            'dataDaftarRi.noBooking' => 'required|string',
            'dataDaftarRi.slCodeFrom' => 'required|in:01,02',
            'dataDaftarRi.riStatus' => 'required|in:I,L,P',
            'dataDaftarRi.klaimId' => 'required|exists:rsmst_klaimtypes,klaim_id',
            'dataDaftarRi.noReferensi' => 'nullable|string|min:3|max:19',
        ]);
    }

    /* ===============================
     | UPDATE JSON DATA
     =============================== */
    private function updateJsonData(int|string $riHdrNo): void
    {
        $allowedFields = ['regNo', 'drId', 'drDesc', 'klaimId', 'klaimStatus', 'entryId', 'entryDesc', 'entryDate', 'shift', 'noAntrian', 'noBooking', 'slCodeFrom', 'riStatus', 'ermStatus', 'bangsalId', 'bangsalDesc', 'roomId', 'roomDesc', 'bedNo', 'noReferensi', 'sep', 'spri', 'poliId', 'poliDesc', 'kddrbpjs', 'kdpolibpjs'];
        if ($this->formMode === 'create') {
            $this->updateJsonRI((int) $riHdrNo, $this->dataDaftarRi);
            return;
        }
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

    /* ---- SEP Handlers ---- */
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

    /* ---- Bangsal / Ruang / Bed ---- */
    public function updatedBangsalId(string $value): void
    {
        $this->dataDaftarRi['bangsalId'] = $value;
        $this->dataDaftarRi['bangsalDesc'] = collect($this->bangsalOptions)->firstWhere('bangsalId', $value)['bangsalName'] ?? '';
        $this->dataDaftarRi['roomId'] = '';
        $this->dataDaftarRi['roomDesc'] = '';
        $this->dataDaftarRi['bedNo'] = '';
        $this->loadRoomOptions($value);
        $this->bedOptions = [];
        $this->incrementVersion('bangsal');
    }

    public function updatedDataDaftarRiRoomId(string $value): void
    {
        $this->dataDaftarRi['roomDesc'] = collect($this->roomOptions)->firstWhere('roomId', $value)['roomName'] ?? '';
        $this->dataDaftarRi['bedNo'] = '';
        $this->loadBedOptions($value);
        $this->incrementVersion('bangsal');
    }

    private function loadRoomOptions(string $bangsalId): void
    {
        if (!$bangsalId) {
            $this->roomOptions = [];
            return;
        }
        $this->roomOptions = DB::table('rsmst_rooms')->where('bangsal_id', $bangsalId)->select('room_id', 'room_name')->orderBy('room_name')->get()->map(fn($r) => ['roomId' => $r->room_id, 'roomName' => $r->room_name])->toArray();
    }

    private function loadBedOptions(string $roomId): void
    {
        if (!$roomId) {
            $this->bedOptions = [];
            return;
        }
        $this->bedOptions = DB::table('rsmst_beds')->where('room_id', $roomId)->where('bed_status', 'A')->select('bed_no', 'bed_desc')->orderBy('bed_no')->get()->map(fn($r) => ['bedNo' => $r->bed_no, 'bedDesc' => $r->bed_desc ?? $r->bed_no])->toArray();
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

    #[On('sep-generated-ri')]
    public function handleSepGenerated(array $reqSep): void
    {
        $this->dataDaftarRi['sep']['reqSep'] = $reqSep;
        $noRujukan = $reqSep['request']['t_sep']['rujukan']['noRujukan'] ?? null;
        if ($noRujukan) {
            $this->dataDaftarRi['noReferensi'] = $noRujukan;
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
        $this->dispatch('cetak-sep-ri.open', noSep: $this->dataDaftarRi['sep']['noSep']);
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
    }

    /* ---- Helpers ---- */
    protected function resetForm(): void
    {
        $this->reset(['riHdrNo', 'dataDaftarRi', 'dataPasien', 'roomOptions', 'bedOptions']);
        $this->resetVersion();
        $this->klaimId = 'UM';
        $this->entryId = '1';
        $this->bangsalId = '';
        $this->formMode = 'create';
        $this->isFormLocked = false;
    }

    private function syncFromDataDaftarRI(): void
    {
        $this->klaimId = $this->dataDaftarRi['klaimId'] ?? 'UM';
        $this->entryId = $this->dataDaftarRi['entryId'] ?? '1';
        $this->bangsalId = $this->dataDaftarRi['bangsalId'] ?? '';
    }
};
?>

<div>
    <x-modal name="ri-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal', [$formMode, $riHdrNo ?? 'new']) }}">

            {{-- ============================================================
                 HEADER MODAL
                 ============================================================ --}}
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
                    {{-- Tanggal & Shift --}}
                    <div class="flex items-end gap-3">
                        <div>
                            <x-input-label value="Tanggal Masuk RI" />
                            <x-text-input wire:model.live="dataDaftarRi.entryDate" class="block w-52"
                                :disabled="$isFormLocked" />
                            <x-input-error :messages="$errors->get('dataDaftarRi.entryDate')" class="mt-1" />
                        </div>
                        <div class="w-32">
                            <x-input-label value="Shift" />
                            <x-select-input wire:model.live="dataDaftarRi.shift" class="w-full" :disabled="$isFormLocked">
                                <option value="">-- Shift --</option>
                                <option value="1">Shift 1</option>
                                <option value="2">Shift 2</option>
                                <option value="3">Shift 3</option>
                            </x-select-input>
                        </div>
                    </div>
                    <x-secondary-button type="button" wire:click="closeModal" class="!p-2 shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-secondary-button>
                </div>
            </div>

            {{-- ============================================================
                 STICKY DISPLAY PASIEN — muncul setelah pasien dipilih
                 Referensi: pola emr-ri display-pasien header
                 ============================================================ --}}
            @if (!empty($dataPasien['pasien']['regNo']))
                <div id="DataPasien"
                    class="sticky top-0 z-20 px-4 py-2 bg-white border-b border-gray-200
                           dark:bg-gray-900 dark:border-gray-700 shrink-0"
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

                        {{-- Kolom 1: Identitas Pasien --}}
                        <div class="min-w-0">
                            <div class="text-base font-semibold text-gray-700 dark:text-gray-300">
                                {{ $dataPasien['pasien']['regNo'] }}
                            </div>
                            <div class="text-2xl font-semibold text-primary dark:text-white truncate">
                                {{ strtoupper($dataPasien['pasien']['regName'] ?? '-') }}
                                / ({{ $dataPasien['pasien']['jenisKelamin']['jenisKelaminDesc'] ?? '-' }})
                                / {{ $dataPasien['pasien']['thn'] ?? '-' }} Thn
                            </div>
                            <div class="font-normal text-sm text-gray-700 dark:text-gray-400 truncate">
                                {{ $dataPasien['pasien']['identitas']['alamat'] ?? '-' }}
                            </div>
                        </div>

                        {{-- Kolom 2: Leveling Dokter --}}
                        <div class="text-sm">
                            @if (!empty($dataDaftarRi['drDesc']))
                                <div class="text-xs text-gray-500 dark:text-gray-400">DPJP Utama</div>
                                <div class="font-semibold text-gray-800 dark:text-gray-200">
                                    {{ $dataDaftarRi['drDesc'] }}
                                </div>
                            @endif
                            @if (!empty($dataDaftarRi['pengkajianAwalPasienRawatInap']['levelingDokter']))
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Tim Dokter:</div>
                                @foreach ($dataDaftarRi['pengkajianAwalPasienRawatInap']['levelingDokter'] as $ld)
                                    <div class="text-xs text-gray-700 dark:text-gray-300">
                                        {{ $ld['drDesc'] ?? '-' }}
                                        <span class="text-gray-400">({{ $ld['levelingDesc'] ?? '-' }})</span>
                                    </div>
                                @endforeach
                            @else
                                <div class="text-xs text-gray-400 mt-1">—</div>
                            @endif
                        </div>

                        {{-- Kolom 3: Kamar & Klaim --}}
                        <div class="px-2 text-sm text-gray-900 dark:text-gray-100">
                            <p class="text-right text-gray-500 dark:text-gray-400">
                                {{ $dataDaftarRi['bangsalDesc'] ?? '-' }}
                            </p>
                            <p class="font-semibold text-right">
                                {{ $dataDaftarRi['roomDesc'] ?? '-' }}
                                / Bed: {{ $dataDaftarRi['bedNo'] ?? '-' }}
                                &nbsp;
                                <x-badge :variant="$badgeVariant">{{ $klaimDesc }}</x-badge>
                            </p>
                            <p class="text-right text-xs text-gray-500 dark:text-gray-400">
                                Tgl Masuk: {{ $dataDaftarRi['entryDate'] ?? '-' }}
                            </p>
                        </div>

                    </div>
                </div>
            @endif

            {{-- ============================================================
                 BODY
                 ============================================================ --}}
            <div class="flex-1 overflow-y-auto px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20" x-data
                x-on:focus-cari-pasien-ri.window="$nextTick(() => setTimeout(() => $refs.lovPasienRi?.querySelector('input')?.focus(), 150))"
                x-on:focus-cari-dokter-ri.window="$nextTick(() => setTimeout(() => $refs.lovDokterRi?.querySelector('input')?.focus(), 150))">

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">

                    {{-- KOLOM 1: Pasien & Dokter --}}
                    <div
                        class="p-6 space-y-5 bg-white border border-gray-200 shadow-sm rounded-2xl
                                dark:bg-gray-900 dark:border-gray-700">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Data Pasien & Dokter
                        </h3>

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
                                class="px-3 py-2 text-xs border border-gray-200 rounded-lg bg-gray-50
                                        dark:bg-gray-800 dark:border-gray-700">
                                <span class="font-semibold">Kode Dr BPJS:</span> {{ $dataDaftarRi['kddrbpjs'] }}
                                &nbsp;|&nbsp;
                                <span class="font-semibold">Poli:</span>
                                {{ $dataDaftarRi['poliDesc'] ?? '-' }} ({{ $dataDaftarRi['kdpolibpjs'] ?? '-' }})
                            </div>
                        @endif
                    </div>

                    {{-- KOLOM 2: Kamar / Bangsal --}}
                    <div class="p-6 space-y-5 bg-white border border-gray-200 shadow-sm rounded-2xl
                                dark:bg-gray-900 dark:border-gray-700"
                        wire:key="{{ $this->renderKey('bangsal', [$bangsalId, $dataDaftarRi['roomId'] ?? '']) }}">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Kamar & Bangsal
                        </h3>

                        <div>
                            <x-input-label value="Bangsal" />
                            <x-select-input wire:model.live="bangsalId" class="w-full mt-1" :disabled="$isFormLocked">
                                <option value="">-- Pilih Bangsal --</option>
                                @foreach ($bangsalOptions as $bangsal)
                                    <option value="{{ $bangsal['bangsalId'] }}">{{ $bangsal['bangsalName'] }}</option>
                                @endforeach
                            </x-select-input>
                            <x-input-error :messages="$errors->get('dataDaftarRi.bangsalId')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label value="Ruang / Kelas" />
                            <x-select-input wire:model.live="dataDaftarRi.roomId" class="w-full mt-1"
                                :disabled="$isFormLocked || empty($bangsalId)">
                                <option value="">-- Pilih Ruang --</option>
                                @foreach ($roomOptions as $room)
                                    <option value="{{ $room['roomId'] }}">{{ $room['roomName'] }}</option>
                                @endforeach
                            </x-select-input>
                            <x-input-error :messages="$errors->get('dataDaftarRi.roomId')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label value="Nomor Bed" />
                            <x-select-input wire:model.live="dataDaftarRi.bedNo" class="w-full mt-1"
                                :disabled="$isFormLocked || empty($dataDaftarRi['roomId'])">
                                <option value="">-- Pilih Bed --</option>
                                @foreach ($bedOptions as $bed)
                                    <option value="{{ $bed['bedNo'] }}">{{ $bed['bedDesc'] }}</option>
                                @endforeach
                            </x-select-input>
                            <x-input-error :messages="$errors->get('dataDaftarRi.bedNo')" class="mt-1" />
                        </div>

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
                    </div>

                    {{-- KOLOM 3: Klaim & BPJS --}}
                    <div
                        class="p-6 space-y-5 bg-white border border-gray-200 shadow-sm rounded-2xl
                                dark:bg-gray-900 dark:border-gray-700">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Klaim & BPJS
                        </h3>

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
                                <p class="mt-1 text-xs text-gray-400">
                                    Isi nomor rujukan dari Faskes Tingkat Pertama (FKTP).
                                </p>
                                <x-input-error :messages="$errors->get('dataDaftarRi.noReferensi')" class="mt-1" />
                            </div>

                            <div class="space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-secondary-button type="button" wire:click="openVclaimModal"
                                        class="gap-2 text-xs">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        Kelola SEP RI
                                    </x-secondary-button>

                                    @if (!empty($dataDaftarRi['sep']['noSep']))
                                        <x-secondary-button type="button" wire:click="cetakSEP"
                                            class="gap-2 text-xs" title="Cetak SEP">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                            </svg>
                                        </x-secondary-button>
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
                                                class="ml-2 font-mono text-sm font-semibold text-blue-800 dark:text-blue-200">
                                                {{ $dataDaftarRi['sep']['noSep'] }}
                                            </span>
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
                                            class="ml-1 font-mono text-sm font-semibold text-purple-800 dark:text-purple-200">
                                            {{ $dataDaftarRi['spri']['noSPRIBPJS'] }}
                                        </span>
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

            {{-- ============================================================
                 FOOTER
                 ============================================================ --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-white border-t border-gray-200
                        dark:bg-gray-900 dark:border-gray-700 shrink-0">
                <div class="flex justify-between gap-3">
                    <a href="{{ route('master.pasien') }}" wire:navigate>
                        <x-primary-button type="button">Master Pasien</x-primary-button>
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
</div>
