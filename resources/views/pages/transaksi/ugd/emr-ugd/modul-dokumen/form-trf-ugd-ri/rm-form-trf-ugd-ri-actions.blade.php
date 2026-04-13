<?php
// resources/views/pages/transaksi/ugd/emr-ugd/modul-dokumen/form-trf-ugd-ri/rm-form-trf-ugd-ri-actions.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use EmrUGDTrait, MasterPasienTrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $dataDaftarUGD = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-trf-ugd-ri'];

    // ── Form entry leveling dokter ──
    public array $levelingDokter = [
        'drId' => '',
        'drName' => '',
        'poliId' => '',
        'poliDesc' => '',
        'tglEntry' => '',
        'levelDokter' => 'Utama',
    ];

    // ── Form entry alat terpasang ──
    public array $alat = [
        'jenis' => '',
        'lokasi' => '',
        'ukuran' => '',
        'keterangan' => '',
    ];

    public array $levelDokterOptions = [['value' => 'Utama', 'label' => 'Utama'], ['value' => 'RawatGabung', 'label' => 'Rawat Gabung']];

    public array $kondisiKlinisOptions = [['value' => 0, 'label' => 'Derajat 0'], ['value' => 1, 'label' => 'Derajat 1'], ['value' => 2, 'label' => 'Derajat 2'], ['value' => 3, 'label' => 'Derajat 3']];

    // ── Top-level sync variables (Livewire 4 tidak support wire:model pada deeply nested) ──
    public int $kondisiKlinis = 0;
    public string $levelDokterSelected = 'Utama';

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas(['modal-trf-ugd-ri']);
    }

    public function rendering(): void
    {
        $default = $this->getDefaultTrfUgd($this->dataDaftarUGD);
        $current = $this->dataDaftarUGD['trfUgd'] ?? [];
        $this->dataDaftarUGD['trfUgd'] = array_replace_recursive($default, $current);
    }

    /* ===============================
     | LOV DOKTER SELECTED
     =============================== */
    #[On('lov.selected.dokter-trf-ugd-ri')]
    public function onDokterSelected(string $target, array $payload): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only.');
            return;
        }

        $this->levelingDokter['drId'] = $payload['dr_id'] ?? '';
        $this->levelingDokter['drName'] = $payload['dr_name'] ?? '';
        $this->levelingDokter['poliId'] = $payload['poli_id'] ?? '';
        $this->levelingDokter['poliDesc'] = $payload['poli_desc'] ?? '';
        $this->levelingDokter['kdpolibpjs'] = $payload['kd_dr_bpjs'] ?? '';
        $this->levelingDokter['kddrbpjs'] = $payload['kd_poli_bpjs'] ?? '';
        $this->incrementVersion('modal-trf-ugd-ri');
    }

    /* ===============================
     | LOV ROOM SELECTED
     =============================== */
    #[On('lov.selected.room-trf-ugd-ri')]
    public function onRoomSelected(string $target, array $payload): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only.');
            return;
        }

        $this->dataDaftarUGD['trfUgd']['pindahKeRuangan'] = $payload['room_name'] ?? '';
        $this->dataDaftarUGD['trfUgd']['pindahKeRoomId'] = $payload['room_id'] ?? '';
        $this->dataDaftarUGD['trfUgd']['pindahKeBedNo'] = $payload['bed_no'] ?? '';
        $this->incrementVersion('modal-trf-ugd-ri');
    }

    /* ===============================
     | OPEN
     =============================== */
    #[On('open-rm-form-trf-ugd-ri')]
    public function openTrfUgdRi(int $rjNo): void
    {
        if (empty($rjNo)) {
            return;
        }

        $this->rjNo = $rjNo;
        $this->resetForm();
        $this->resetValidation();

        $data = $this->findDataUGD($rjNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.');
            return;
        }

        $this->dataDaftarUGD = $data;
        $this->dataDaftarUGD['trfUgd'] ??= $this->getDefaultTrfUgd($data);

        // pindahDariRuangan selalu UGD
        $this->dataDaftarUGD['trfUgd']['pindahDariRuangan'] = 'UGD';

        // Sync top-level variables dari nested data
        $this->kondisiKlinis = (int) ($this->dataDaftarUGD['trfUgd']['kondisiKlinis'] ?? 0);
        $this->levelDokterSelected = $this->levelingDokter['levelDokter'] ?? 'Utama';

        $this->isFormLocked = $this->checkEmrUGDStatus($rjNo);
        $this->incrementVersion('modal-trf-ugd-ri');
    }

    /* ===============================
     | VALIDATION
     =============================== */
    protected function rules(): array
    {
        return [
            'levelingDokter.drId' => 'required|string|max:10',
            'levelingDokter.drName' => 'required|string|max:200',
            'levelingDokter.poliId' => 'required|string|max:10',
            'levelingDokter.poliDesc' => 'required|string|max:50',
            'levelingDokter.tglEntry' => 'required|date_format:d/m/Y H:i:s',
            'levelingDokter.levelDokter' => 'required|in:Utama,RawatGabung',
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'date_format' => ':attribute harus format dd/mm/yyyy HH:ii:ss.',
            'in' => ':attribute hanya boleh Utama atau RawatGabung.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'levelingDokter.drId' => 'ID Dokter',
            'levelingDokter.drName' => 'Nama Dokter',
            'levelingDokter.poliId' => 'ID Poli',
            'levelingDokter.poliDesc' => 'Nama Poli',
            'levelingDokter.tglEntry' => 'Tanggal Entry',
            'levelingDokter.levelDokter' => 'Level Dokter',
        ];
    }

    /* ===============================
     | UPDATED HOOKS — sync top-level → nested
     =============================== */
    public function updated(string $name, mixed $value): void
    {
        if ($name === 'kondisiKlinis') {
            $this->dataDaftarUGD['trfUgd']['kondisiKlinis'] = (int) $value;
        }

        if ($name === 'levelDokterSelected') {
            $this->levelingDokter['levelDokter'] = $value;
        }
    }

    /* ===============================
     | SAVE GLOBAL
     =============================== */
    #[On('save-rm-form-trf-ugd-ri')]
    public function save(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }

        try {
            DB::transaction(function () {
                $this->lockUGDRow($this->rjNo);

                $data = $this->findDataUGD($this->rjNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data UGD tidak ditemukan, simpan dibatalkan.');
                }

                $data['trfUgd'] = array_replace($data['trfUgd'] ?? [], $this->dataDaftarUGD['trfUgd'] ?? []);

                $this->updateJsonUGD($this->rjNo, $data);
                $this->dataDaftarUGD = $data;
            });

            $this->incrementVersion('modal-trf-ugd-ri');
            $this->dispatch('toast', type: 'success', message: 'Data Transfer UGD berhasil disimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | LEVELING DOKTER — ADD
     =============================== */
    public function addLevelingDokter(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }

        $this->levelingDokter['tglEntry'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        $this->validate();

        $exists = collect($this->dataDaftarUGD['trfUgd']['levelingDokter'] ?? [])->firstWhere('tglEntry', $this->levelingDokter['tglEntry']);

        if ($exists) {
            $this->dispatch('toast', type: 'error', message: 'Data leveling dokter pada waktu tersebut sudah ada.');
            return;
        }

        try {
            DB::transaction(function () {
                $this->lockUGDRow($this->rjNo);

                $data = $this->findDataUGD($this->rjNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data UGD tidak ditemukan.');
                }

                $data['trfUgd']['levelingDokter'][] = [
                    'drId' => $this->levelingDokter['drId'],
                    'drName' => $this->levelingDokter['drName'],
                    'poliId' => $this->levelingDokter['poliId'],
                    'poliDesc' => $this->levelingDokter['poliDesc'],
                    'tglEntry' => $this->levelingDokter['tglEntry'],
                    'levelDokter' => $this->levelingDokter['levelDokter'],
                ];

                $data['trfUgd']['levelingDokterLog'] = [
                    'userLogDesc' => 'Tambah ' . $this->levelingDokter['drName'] . ' - ' . $this->levelingDokter['levelDokter'],
                    'userLog' => auth()->user()->myuser_name ?? '',
                    'userLogDate' => Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'),
                ];

                $this->updateJsonUGD($this->rjNo, $data);
                $this->dataDaftarUGD = $data;
            });

            $this->resetLevelingDokter();
            $this->incrementVersion('modal-trf-ugd-ri');
            $this->dispatch('toast', type: 'success', message: 'Leveling dokter berhasil ditambahkan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | LEVELING DOKTER — REMOVE
     =============================== */
    public function removeLevelingDokter(string $tglEntry): void
    {
        if ($this->isFormLocked) {
            return;
        }

        try {
            DB::transaction(function () use ($tglEntry) {
                $this->lockUGDRow($this->rjNo);

                $data = $this->findDataUGD($this->rjNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data UGD tidak ditemukan.');
                }

                $data['trfUgd']['levelingDokter'] = collect($data['trfUgd']['levelingDokter'] ?? [])
                    ->reject(fn($row) => $row['tglEntry'] === $tglEntry)
                    ->values()
                    ->toArray();

                $data['trfUgd']['levelingDokterLog'] = [
                    'userLogDesc' => 'Hapus leveling dokter',
                    'userLog' => auth()->user()->myuser_name ?? '',
                    'userLogDate' => Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'),
                ];

                $this->updateJsonUGD($this->rjNo, $data);
                $this->dataDaftarUGD = $data;
            });

            $this->incrementVersion('modal-trf-ugd-ri');
            $this->dispatch('toast', type: 'success', message: 'Leveling dokter berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | LEVELING DOKTER — SET LEVEL
     =============================== */
    public function setLevelDokter(string $tglEntry, string $level): void
    {
        if ($this->isFormLocked) {
            return;
        }
        if (!in_array($level, ['Utama', 'RawatGabung'])) {
            return;
        }

        try {
            DB::transaction(function () use ($tglEntry, $level) {
                $this->lockUGDRow($this->rjNo);

                $data = $this->findDataUGD($this->rjNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data UGD tidak ditemukan.');
                }

                $list = &$data['trfUgd']['levelingDokter'];
                foreach ($list as &$item) {
                    if ($item['tglEntry'] === $tglEntry) {
                        if ($item['levelDokter'] === $level) {
                            $this->dispatch('toast', type: 'error', message: "Status dokter sudah {$level}.");
                            return;
                        }
                        $data['trfUgd']['levelingDokterLog'] = [
                            'userLogDesc' => 'Ubah level ' . ($item['drName'] ?? '-') . ' → ' . $level,
                            'userLog' => auth()->user()->myuser_name ?? '',
                            'userLogDate' => Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'),
                        ];
                        $item['levelDokter'] = $level;
                        break;
                    }
                }
                unset($item);

                $this->updateJsonUGD($this->rjNo, $data);
                $this->dataDaftarUGD = $data;
            });

            $this->incrementVersion('modal-trf-ugd-ri');
            $this->dispatch('toast', type: 'success', message: "Level dokter diubah ke {$level}.");
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | ALAT TERPASANG — ADD
     =============================== */
    public function addAlatTerpasang(): void
    {
        if ($this->isFormLocked) {
            return;
        }

        $this->validateOnly('alat.jenis', ['alat.jenis' => 'required|string|max:100'], ['alat.jenis.required' => 'Jenis alat wajib diisi.']);

        try {
            DB::transaction(function () {
                $this->lockUGDRow($this->rjNo);

                $data = $this->findDataUGD($this->rjNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data UGD tidak ditemukan.');
                }

                $data['trfUgd']['alatYangTerpasang'][] = [
                    'jenis' => trim($this->alat['jenis']),
                    'lokasi' => trim($this->alat['lokasi']),
                    'ukuran' => trim($this->alat['ukuran']),
                    'keterangan' => trim($this->alat['keterangan']),
                ];

                $this->updateJsonUGD($this->rjNo, $data);
                $this->dataDaftarUGD = $data;
            });

            $this->reset(['alat']);
            $this->incrementVersion('modal-trf-ugd-ri');
            $this->dispatch('toast', type: 'success', message: 'Alat terpasang berhasil ditambahkan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | ALAT TERPASANG — REMOVE
     =============================== */
    public function removeAlatTerpasang(int $index): void
    {
        if ($this->isFormLocked) {
            return;
        }

        try {
            DB::transaction(function () use ($index) {
                $this->lockUGDRow($this->rjNo);

                $data = $this->findDataUGD($this->rjNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data UGD tidak ditemukan.');
                }

                $data['trfUgd']['alatYangTerpasang'] = collect($data['trfUgd']['alatYangTerpasang'] ?? [])
                    ->forget($index)
                    ->values()
                    ->toArray();

                $this->updateJsonUGD($this->rjNo, $data);
                $this->dataDaftarUGD = $data;
            });

            $this->incrementVersion('modal-trf-ugd-ri');
            $this->dispatch('toast', type: 'success', message: 'Alat terpasang berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | PETUGAS PENGIRIM & PENERIMA
     =============================== */
    public function setPetugasPengirim(): void
    {
        if (!empty($this->dataDaftarUGD['trfUgd']['petugasPengirim'])) {
            $this->dispatch('toast', type: 'error', message: 'Petugas Pengirim sudah diisi sebelumnya.');
            return;
        }

        $this->dataDaftarUGD['trfUgd']['petugasPengirim'] = auth()->user()->myuser_name ?? '';
        $this->dataDaftarUGD['trfUgd']['petugasPengirimCode'] = auth()->user()->myuser_code ?? '';
        $this->dataDaftarUGD['trfUgd']['petugasPengirimDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        $this->save();
    }

    public function setPetugasPenerima(): void
    {
        if (!empty($this->dataDaftarUGD['trfUgd']['petugasPenerima'])) {
            $this->dispatch('toast', type: 'error', message: 'Petugas Penerima sudah diisi sebelumnya.');
            return;
        }

        $this->dataDaftarUGD['trfUgd']['petugasPenerima'] = auth()->user()->myuser_name ?? '';
        $this->dataDaftarUGD['trfUgd']['petugasPenerimaCode'] = auth()->user()->myuser_code ?? '';
        $this->dataDaftarUGD['trfUgd']['petugasPenerimaDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        $this->save();
    }

    /* ===============================
     | SET TGL PINDAH
     =============================== */
    public function setTglPindah(): void
    {
        $this->dataDaftarUGD['trfUgd']['tglPindah'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        $this->incrementVersion('modal-trf-ugd-ri');
    }

    /* ===============================
     | CETAK — dispatch ke child component
     =============================== */
    public function cetakTrfPasienUgd(): void
    {
        if (!$this->rjNo) {
            $this->dispatch('toast', type: 'error', message: 'Nomor UGD tidak ditemukan.');
            return;
        }

        $this->dispatch('cetak-form-trf-ugd-ri.open', rjNo: $this->rjNo);
    }

    /* ===============================
     | DEFAULT STRUCTURE
     =============================== */
    private function getDefaultTrfUgd(array $data): array
    {
        $keluhanUtama = trim((string) data_get($data, 'anamnesa.keluhanUtama.keluhanUtama', ''));
        $alergi = trim((string) data_get($data, 'anamnesa.alergi.alergi', ''));
        $dxFree = trim((string) data_get($data, 'diagnosisFreeText', ''));
        $terapiText = (string) data_get($data, 'perencanaan.terapi.terapi', '');
        $terapiUgd = array_values(array_filter(array_map('trim', explode("\n", $terapiText))));

        return [
            'keluhanUtama' => $keluhanUtama,
            'temuanSignifikan' => '',
            'alergi' => $alergi,
            'diagnosisFreeText' => $dxFree,
            'terapiUgd' => $terapiUgd,
            'levelingDokter' => $data['trfUgd']['levelingDokter'] ?? [],
            'pindahDariRuangan' => 'UGD',
            'pindahKeRuangan' => '',
            'pindahKeRoomId' => '',
            'pindahKeBedNo' => '',
            'tglPindah' => '',
            'kondisiKlinis' => 0,
            'fasilitas' => '',
            'fasilitasPendukung' => '',
            'alasanPindah' => '',
            'metodePemindahanPasien' => '',
            'kondisiSaatDikirim' => ['sistolik' => '', 'diastolik' => '', 'frekuensiNafas' => '', 'frekuensiNadi' => '', 'suhu' => '', 'spo2' => '', 'gda' => '', 'gcs' => '', 'keadaanPasien' => ''],
            'kondisiSaatDiterima' => ['sistolik' => '', 'diastolik' => '', 'frekuensiNafas' => '', 'frekuensiNadi' => '', 'suhu' => '', 'spo2' => '', 'gda' => '', 'gcs' => '', 'keadaanPasien' => ''],
            'alatYangTerpasang' => [],
            'rencanaPerawatan' => ['observasi' => '', 'pembatasanCairan' => '', 'balanceCairan' => '', 'lainLain' => '', 'diet' => ''],
            'petugasPengirim' => '',
            'petugasPengirimCode' => '',
            'petugasPengirimDate' => '',
            'petugasPenerima' => '',
            'petugasPenerimaCode' => '',
            'petugasPenerimaDate' => '',
        ];
    }

    /* ===============================
     | HELPERS
     =============================== */
    public function resetLevelingDokter(): void
    {
        $this->reset(['levelingDokter']);
        $this->levelingDokter['levelDokter'] = 'Utama';
        $this->resetValidation();
        $this->incrementVersion('modal-trf-ugd-ri');
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->dataDaftarUGD = [];
        $this->kondisiKlinis = 0;
        $this->levelDokterSelected = 'Utama';
        $this->reset(['levelingDokter', 'alat']);
        $this->levelingDokter['levelDokter'] = 'Utama';
    }
};
?>

<div>
    <div class="flex flex-col w-full" wire:key="{{ $this->renderKey('modal-trf-ugd-ri', [$rjNo ?? 'new']) }}">
        <div
            class="w-full p-4 space-y-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

            @if ($isFormLocked)
                <div
                    class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    EMR terkunci — data tidak dapat diubah.
                </div>
            @endif

            @if (isset($dataDaftarUGD['trfUgd']))

                {{-- ══ RINGKASAN KLINIS ══ --}}
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div
                        class="p-4 border border-gray-200 rounded-2xl dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40">
                        <h3 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Keluhan Utama &amp;
                            Alergi</h3>
                        <div class="space-y-3">
                            <div>
                                <x-input-label value="Keluhan Utama" class="mb-1" />
                                <x-textarea wire:model="dataDaftarUGD.trfUgd.keluhanUtama" rows="3"
                                    :disabled="$isFormLocked" />
                            </div>
                            <div>
                                <x-input-label value="Temuan Signifikan" class="mb-1" />
                                <x-textarea wire:model="dataDaftarUGD.trfUgd.temuanSignifikan" rows="3"
                                    :disabled="$isFormLocked" />
                            </div>
                            <div>
                                <x-input-label value="Alergi" class="mb-1" />
                                <x-textarea wire:model="dataDaftarUGD.trfUgd.alergi" rows="2" :disabled="$isFormLocked" />
                            </div>
                        </div>
                    </div>

                    <div
                        class="p-4 border border-gray-200 rounded-2xl dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40">
                        <h3 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Diagnosis &amp; Terapi
                            UGD</h3>
                        <div class="space-y-3">
                            <div>
                                <x-input-label value="Diagnosis (Free Text)" class="mb-1" />
                                <x-textarea wire:model="dataDaftarUGD.trfUgd.diagnosisFreeText" rows="3"
                                    :disabled="$isFormLocked" />
                            </div>
                            <div>
                                <x-input-label value="Terapi UGD" class="mb-1" />
                                <x-textarea wire:model="dataDaftarUGD.trfUgd.terapiUgd" rows="3"
                                    placeholder="Tuliskan terapi UGD..." :disabled="$isFormLocked" />
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ══ LEVELING DOKTER ══ --}}
                <div class="p-4 border border-gray-200 rounded-2xl dark:border-gray-700">
                    <h3 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Leveling Dokter</h3>

                    @if (!$isFormLocked)
                        <div
                            class="p-4 mb-4 rounded-xl bg-gray-50 dark:bg-gray-800/40 border border-gray-200 dark:border-gray-700">
                            <div class="grid grid-cols-12 gap-3 items-end">

                                <div class="col-span-12 md:col-span-5">
                                    @if (empty($levelingDokter['drId']))
                                        <livewire:lov.dokter.lov-dokter target="dokter-trf-ugd-ri" label="Pilih Dokter"
                                            wire:key="lov-dokter-trf-ugd-ri-{{ $rjNo }}-{{ $renderVersions['modal-trf-ugd-ri'] ?? 0 }}" />
                                    @else
                                        <x-input-label value="Nama Dokter" class="mb-1" />
                                        <div class="flex items-center gap-2">
                                            <x-text-input wire:model="levelingDokter.drName" disabled
                                                class="grow text-sm" />
                                            <x-secondary-button type="button"
                                                wire:click="$set('levelingDokter.drId', '')"
                                                class="text-xs whitespace-nowrap shrink-0">
                                                Ganti
                                            </x-secondary-button>
                                        </div>
                                        <x-input-error :messages="$errors->get('levelingDokter.drId')" class="mt-1" />
                                        <x-input-error :messages="$errors->get('levelingDokter.drName')" class="mt-1" />
                                    @endif
                                </div>

                                <div class="col-span-12 md:col-span-3">
                                    <x-input-label value="Poli" class="mb-1" />
                                    <x-text-input wire:model="levelingDokter.poliDesc" disabled
                                        class="w-full text-sm" />
                                    <x-input-error :messages="$errors->get('levelingDokter.poliId')" class="mt-1" />
                                    <x-input-error :messages="$errors->get('levelingDokter.poliDesc')" class="mt-1" />
                                </div>

                                <div class="col-span-12 md:col-span-2">
                                    <x-input-label value="Level Dokter *" class="mb-1" />
                                    <div class="grid grid-cols-2 gap-2 mt-1">
                                        @foreach ($levelDokterOptions as $opt)
                                            <x-radio-button :label="$opt['label']" :value="$opt['value']"
                                                name="levelingDokter.levelDokter"
                                                wire:model.live="levelDokterSelected" />
                                        @endforeach
                                    </div>
                                    <x-input-error :messages="$errors->get('levelingDokter.levelDokter')" class="mt-1" />
                                    <x-input-error :messages="$errors->get('levelingDokter.tglEntry')" class="mt-1" />
                                </div>

                                <div class="col-span-12 md:col-span-2 flex gap-2">
                                    <x-primary-button wire:click.prevent="addLevelingDokter"
                                        wire:loading.attr="disabled" wire:target="addLevelingDokter"
                                        class="flex-1 justify-center gap-1">
                                        <span wire:loading.remove wire:target="addLevelingDokter">Tambah</span>
                                        <span wire:loading wire:target="addLevelingDokter"><x-loading
                                                class="w-4 h-4" /></span>
                                    </x-primary-button>
                                    <x-secondary-button wire:click.prevent="resetLevelingDokter" type="button"
                                        class="!p-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </x-secondary-button>
                                </div>
                            </div>
                        </div>
                    @endif

                    @php $levelingList = $dataDaftarUGD['trfUgd']['levelingDokter'] ?? []; @endphp
                    <div class="overflow-hidden border border-gray-200 rounded-xl dark:border-gray-700">
                        <table class="w-full text-sm text-left">
                            <thead
                                class="text-xs font-semibold text-gray-500 uppercase bg-gray-50 dark:bg-gray-800/50 dark:text-gray-400">
                                <tr>
                                    <th class="px-4 py-3">Nama Dokter</th>
                                    <th class="px-4 py-3">Poli</th>
                                    <th class="px-4 py-3">Tgl Entry</th>
                                    <th class="px-4 py-3 text-center">Level</th>
                                    @if (!$isFormLocked)
                                        <th class="px-4 py-3 text-center">Aksi</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse ($levelingList as $dok)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40 transition">
                                        <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-200">
                                            {{ $dok['drName'] ?? '-' }}</td>
                                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                            {{ $dok['poliDesc'] ?? '-' }}</td>
                                        <td class="px-4 py-3 text-xs text-gray-500">{{ $dok['tglEntry'] ?? '-' }}</td>
                                        <td class="px-4 py-3 text-center">
                                            <x-badge
                                                variant="{{ ($dok['levelDokter'] ?? '') === 'Utama' ? 'success' : 'info' }}">
                                                {{ $dok['levelDokter'] ?? '-' }}
                                            </x-badge>
                                        </td>
                                        @if (!$isFormLocked)
                                            <td class="px-4 py-3 text-center">
                                                <div class="flex items-center justify-center gap-1">
                                                    <x-secondary-button type="button"
                                                        wire:click="setLevelDokter('{{ $dok['tglEntry'] }}', 'Utama')"
                                                        class="px-2 py-1 text-xs">Utama</x-secondary-button>
                                                    <x-secondary-button type="button"
                                                        wire:click="setLevelDokter('{{ $dok['tglEntry'] }}', 'RawatGabung')"
                                                        class="px-2 py-1 text-xs">RawatGabung</x-secondary-button>
                                                    <button type="button"
                                                        wire:click="removeLevelingDokter('{{ $dok['tglEntry'] }}')"
                                                        wire:confirm="Hapus dokter ini?"
                                                        class="inline-flex items-center justify-center w-7 h-7 text-red-500 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $isFormLocked ? 4 : 5 }}"
                                            class="px-4 py-8 text-sm text-center text-gray-400">Belum ada leveling
                                            dokter</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- ══ DATA PEMINDAHAN ══ --}}
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

                    <div
                        class="p-4 border border-gray-200 rounded-2xl dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40">
                        <h3 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Data Pemindahan Pasien
                        </h3>
                        <div class="space-y-3">
                            <div>
                                <x-input-label value="Pindah dari Ruangan" class="mb-1" />
                                <x-text-input value="UGD" disabled class="w-full bg-gray-100 dark:bg-gray-800" />
                            </div>
                            <div>
                                <x-input-label value="Pindah ke Ruangan *" class="mb-1" />
                                @if (!$isFormLocked)
                                    <livewire:lov.room.lov-room target="room-trf-ugd-ri" label=""
                                        placeholder="Ketik nama ruangan / bed..." :initialRoomId="$dataDaftarUGD['trfUgd']['pindahKeRoomId'] ?? null"
                                        wire:key="lov-room-trf-ugd-ri-{{ $rjNo }}-{{ $renderVersions['modal-trf-ugd-ri'] ?? 0 }}" />
                                @else
                                    <x-text-input :value="($dataDaftarUGD['trfUgd']['pindahKeRuangan'] ?? '') .
                                        (!empty($dataDaftarUGD['trfUgd']['pindahKeBedNo'])
                                            ? ' — Bed ' . $dataDaftarUGD['trfUgd']['pindahKeBedNo']
                                            : '')" disabled class="w-full" />
                                @endif
                                @if (!empty($dataDaftarUGD['trfUgd']['pindahKeRoomId']))
                                    <div class="flex gap-2 mt-1 text-xs text-gray-500">
                                        <span>ID: {{ $dataDaftarUGD['trfUgd']['pindahKeRoomId'] }}</span>
                                        @if (!empty($dataDaftarUGD['trfUgd']['pindahKeBedNo']))
                                            <span>• Bed: {{ $dataDaftarUGD['trfUgd']['pindahKeBedNo'] }}</span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                            <div>
                                <x-input-label value="Tanggal / Jam Pindah" class="mb-1" />
                                <div class="flex items-center gap-2">
                                    <x-text-input wire:model="dataDaftarUGD.trfUgd.tglPindah"
                                        placeholder="dd/mm/yyyy hh:mm:ss" class="grow" :disabled="$isFormLocked" />
                                    @if (!$isFormLocked)
                                        <x-secondary-button wire:click="setTglPindah" type="button"
                                            class="text-xs whitespace-nowrap">Set sekarang</x-secondary-button>
                                    @endif
                                </div>
                            </div>
                            <div>
                                <x-input-label value="Alasan Pindah" class="mb-1" />
                                <x-textarea wire:model="dataDaftarUGD.trfUgd.alasanPindah" rows="2"
                                    :disabled="$isFormLocked" />
                            </div>
                            <div>
                                <x-input-label value="Metode Pemindahan" class="mb-1" />
                                <x-textarea wire:model="dataDaftarUGD.trfUgd.metodePemindahanPasien" rows="2"
                                    placeholder="Brankar / Kursi roda / Jalan sendiri..." :disabled="$isFormLocked" />
                            </div>
                        </div>
                    </div>

                    <div
                        class="p-4 border border-gray-200 rounded-2xl dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40">
                        <h3 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Kondisi &amp; Fasilitas
                        </h3>
                        <div class="space-y-3">
                            <div>
                                <x-input-label value="Kondisi Klinis (Derajat 0–3)" class="mb-1" />
                                <div class="grid grid-cols-4 gap-2 mt-1">
                                    @foreach ($kondisiKlinisOptions as $opt)
                                        <x-radio-button :label="$opt['label']" :value="$opt['value']" name="kondisiKlinis"
                                            wire:model.live="kondisiKlinis" :disabled="$isFormLocked" />
                                    @endforeach
                                </div>
                                @php
                                    $derajat = $kondisiKlinis;
                                    $keteranganDerajat = [
                                        0 => [
                                            'label' => 'Stabil, tanpa keluhan berat.',
                                            'class' =>
                                                'bg-green-50 border-green-300 text-green-800 dark:bg-green-900/20 dark:text-green-300',
                                        ],
                                        1 => [
                                            'label' => 'Keluhan ringan-sedang, perlu observasi.',
                                            'class' =>
                                                'bg-yellow-50 border-yellow-300 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-300',
                                        ],
                                        2 => [
                                            'label' => 'Kondisi sedang, risiko memburuk, perlu tindakan.',
                                            'class' =>
                                                'bg-orange-50 border-orange-300 text-orange-800 dark:bg-orange-900/20 dark:text-orange-300',
                                        ],
                                        3 => [
                                            'label' => 'Gawat Darurat, mengancam jiwa, perlu tindakan segera.',
                                            'class' =>
                                                'bg-red-50 border-red-300 text-red-800 dark:bg-red-900/20 dark:text-red-300',
                                        ],
                                    ];
                                @endphp
                                <div
                                    class="p-2 mt-2 text-xs border rounded-lg {{ $keteranganDerajat[$derajat]['class'] }}">
                                    {{ $keteranganDerajat[$derajat]['label'] }}
                                </div>
                            </div>
                            <div>
                                <x-input-label value="Fasilitas yang Dibutuhkan" class="mb-1" />
                                <x-textarea wire:model="dataDaftarUGD.trfUgd.fasilitas" rows="2"
                                    :disabled="$isFormLocked" />
                            </div>
                            <div>
                                <x-input-label value="Fasilitas Pendukung" class="mb-1" />
                                <x-textarea wire:model="dataDaftarUGD.trfUgd.fasilitasPendukung" rows="2"
                                    :disabled="$isFormLocked" />
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ══ KONDISI TTV ══ --}}
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    @foreach (['kondisiSaatDikirim' => 'Kondisi Saat Dikirim (TTV)', 'kondisiSaatDiterima' => 'Kondisi Saat Diterima (TTV)'] as $key => $label)
                        <div
                            class="p-4 border border-gray-200 rounded-2xl dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40">
                            <h3 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">
                                {{ $label }}</h3>
                            <div class="space-y-2 text-sm">
                                @foreach ([['field' => 'sistolik', 'label' => 'TD Sistolik', 'unit' => 'mmHg', 'ph' => 'Sys'], ['field' => 'diastolik', 'label' => 'TD Diastolik', 'unit' => 'mmHg', 'ph' => 'Dia'], ['field' => 'frekuensiNadi', 'label' => 'Nadi', 'unit' => 'x/mnt', 'ph' => 'x/mnt'], ['field' => 'frekuensiNafas', 'label' => 'Nafas', 'unit' => 'x/mnt', 'ph' => 'x/mnt'], ['field' => 'suhu', 'label' => 'Suhu', 'unit' => '°C', 'ph' => '°C'], ['field' => 'spo2', 'label' => 'SpO₂', 'unit' => '%', 'ph' => '%'], ['field' => 'gda', 'label' => 'GDA', 'unit' => 'mg/dL', 'ph' => 'mg/dL'], ['field' => 'gcs', 'label' => 'GCS', 'unit' => '', 'ph' => 'E V M']] as $ttv)
                                    <div class="flex items-center gap-2">
                                        <span class="w-24 text-xs text-gray-500 shrink-0">{{ $ttv['label'] }}</span>
                                        <x-text-input
                                            wire:model="dataDaftarUGD.trfUgd.{{ $key }}.{{ $ttv['field'] }}"
                                            placeholder="{{ $ttv['ph'] }}" class="w-20 text-sm text-center"
                                            :disabled="$isFormLocked" />
                                        @if ($ttv['unit'])
                                            <span class="text-xs text-gray-400">{{ $ttv['unit'] }}</span>
                                        @endif
                                    </div>
                                @endforeach
                                <div class="mt-1">
                                    <x-input-label value="Keadaan Umum" class="mb-1 !text-xs" />
                                    <x-textarea wire:model="dataDaftarUGD.trfUgd.{{ $key }}.keadaanPasien"
                                        rows="2" :disabled="$isFormLocked" />
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- ══ RENCANA PERAWATAN ══ --}}
                <div class="p-4 border border-gray-200 rounded-2xl dark:border-gray-700">
                    <h3 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Rencana Perawatan</h3>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        @foreach ([['field' => 'observasi', 'label' => 'Observasi'], ['field' => 'pembatasanCairan', 'label' => 'Pembatasan Cairan'], ['field' => 'balanceCairan', 'label' => 'Balance Cairan'], ['field' => 'diet', 'label' => 'Diet']] as $rp)
                            <div>
                                <x-input-label value="{{ $rp['label'] }}" class="mb-1" />
                                <x-textarea wire:model="dataDaftarUGD.trfUgd.rencanaPerawatan.{{ $rp['field'] }}"
                                    rows="2" :disabled="$isFormLocked" />
                            </div>
                        @endforeach
                        <div class="md:col-span-2">
                            <x-input-label value="Lain-lain" class="mb-1" />
                            <x-textarea wire:model="dataDaftarUGD.trfUgd.rencanaPerawatan.lainLain" rows="2"
                                :disabled="$isFormLocked" />
                        </div>
                    </div>
                </div>

                {{-- ══ ALAT YANG TERPASANG ══ --}}
                <div class="p-4 border border-gray-200 rounded-2xl dark:border-gray-700">
                    <h3 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Alat yang Terpasang</h3>

                    @if (!$isFormLocked)
                        <div class="grid grid-cols-2 gap-3 mb-3 md:grid-cols-4">
                            <div>
                                <x-input-label value="Jenis Alat *" class="mb-1" />
                                <x-text-input wire:model="alat.jenis" placeholder="IV Line / Kateter..."
                                    class="w-full" />
                                <x-input-error :messages="$errors->get('alat.jenis')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Lokasi" class="mb-1" />
                                <x-text-input wire:model="alat.lokasi" placeholder="Tangan kanan, NGT..."
                                    class="w-full" />
                            </div>
                            <div>
                                <x-input-label value="Ukuran" class="mb-1" />
                                <x-text-input wire:model="alat.ukuran" placeholder="20G / 10Fr..." class="w-full" />
                            </div>
                            <div>
                                <x-input-label value="Keterangan" class="mb-1" />
                                <x-text-input wire:model="alat.keterangan" placeholder="Terpasang baik..."
                                    class="w-full" />
                            </div>
                        </div>
                        <x-primary-button wire:click.prevent="addAlatTerpasang" wire:loading.attr="disabled"
                            wire:target="addAlatTerpasang" class="mb-3 gap-2">
                            <span wire:loading.remove wire:target="addAlatTerpasang">Tambah Alat</span>
                            <span wire:loading wire:target="addAlatTerpasang"><x-loading class="w-4 h-4" /></span>
                        </x-primary-button>
                    @endif

                    @php $alatList = $dataDaftarUGD['trfUgd']['alatYangTerpasang'] ?? []; @endphp
                    @if (!empty($alatList))
                        <div class="space-y-2">
                            @foreach ($alatList as $idx => $alatItem)
                                <div
                                    class="flex items-center justify-between p-3 text-sm border border-gray-200 rounded-xl dark:border-gray-700 bg-white dark:bg-gray-900">
                                    <div>
                                        <span
                                            class="font-semibold text-gray-800 dark:text-gray-200">{{ $alatItem['jenis'] ?? '-' }}</span>
                                        @if (!empty($alatItem['ukuran']))
                                            <span class="ml-1 text-gray-500">({{ $alatItem['ukuran'] }})</span>
                                        @endif
                                        @if (!empty($alatItem['lokasi']))
                                            <div class="text-xs text-gray-500">Lokasi: {{ $alatItem['lokasi'] }}</div>
                                        @endif
                                        @if (!empty($alatItem['keterangan']))
                                            <div class="text-xs text-gray-400">{{ $alatItem['keterangan'] }}</div>
                                        @endif
                                    </div>
                                    @if (!$isFormLocked)
                                        <button type="button" wire:click="removeAlatTerpasang({{ $idx }})"
                                            wire:confirm="Hapus alat ini?"
                                            class="inline-flex items-center justify-center w-8 h-8 text-red-500 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm italic text-gray-400">Belum ada alat terpasang yang dicatat.</p>
                    @endif
                </div>

                {{-- ══ PETUGAS PENGIRIM & PENERIMA ══ --}}
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    @foreach ([['key' => 'petugasPengirim', 'dateKey' => 'petugasPengirimDate', 'label' => 'Petugas Pengirim', 'method' => 'setPetugasPengirim'], ['key' => 'petugasPenerima', 'dateKey' => 'petugasPenerimaDate', 'label' => 'Petugas Penerima', 'method' => 'setPetugasPenerima']] as $petugas)
                        <div
                            class="p-4 border border-gray-200 rounded-2xl dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40">
                            <x-input-label value="{{ $petugas['label'] }}" class="mb-2" />
                            @php
                                $nama = $dataDaftarUGD['trfUgd'][$petugas['key']] ?? '';
                                $tglTtd = $dataDaftarUGD['trfUgd'][$petugas['dateKey']] ?? '';
                            @endphp
                            @if (empty($nama))
                                @if (!$isFormLocked)
                                    <x-primary-button wire:click.prevent="{{ $petugas['method'] }}" class="gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 012.828 2.828L11.828 15.828a4 4 0 01-2.828 1.172H7v-2a4 4 0 011.172-2.828z" />
                                        </svg>
                                        TTD {{ $petugas['label'] }}
                                    </x-primary-button>
                                @else
                                    <p class="text-sm italic text-gray-400">Belum ditandatangani.</p>
                                @endif
                            @else
                                <div
                                    class="p-3 text-center bg-white border border-gray-200 rounded-xl dark:bg-gray-900 dark:border-gray-700">
                                    <div class="font-semibold text-gray-800 dark:text-gray-200">{{ $nama }}
                                    </div>
                                    <div class="mt-1 text-xs text-gray-500">{{ $tglTtd }}</div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                {{-- ══ TOMBOL SIMPAN & CETAK ══ --}}
                <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
                    <x-secondary-button wire:click.prevent="cetakTrfPasienUgd" wire:loading.attr="disabled"
                        wire:target="cetakTrfPasienUgd" class="gap-2 min-w-[160px] justify-center">
                        <span wire:loading.remove wire:target="cetakTrfPasienUgd">
                            <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M6 9V4h12v5m-2 4h2a2 2 0 002-2v-1a2 2 0 00-2-2H6a2 2 0 00-2 2v1a2 2 0 002 2h2m8 0v5H8v-5h8z" />
                            </svg>
                            Cetak Form Transfer
                        </span>
                        <span wire:loading wire:target="cetakTrfPasienUgd"><x-loading class="w-4 h-4" />
                            Mencetak...</span>
                    </x-secondary-button>

                    @if (!$isFormLocked)
                        <x-primary-button wire:click.prevent="save" wire:loading.attr="disabled" wire:target="save"
                            class="gap-2 min-w-[160px] justify-center">
                            <span wire:loading.remove wire:target="save">
                                <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1-4l-4 4-4-4m4 4V4" />
                                </svg>
                                Simpan Form Transfer
                            </span>
                            <span wire:loading wire:target="save"><x-loading class="w-4 h-4" /> Menyimpan...</span>
                        </x-primary-button>
                    @endif
                </div>
            @else
                <div class="flex flex-col items-center justify-center py-16 text-gray-300 dark:text-gray-600">
                    <svg class="w-10 h-10 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <p class="text-sm font-medium">Data UGD belum dimuat</p>
                </div>
            @endif

        </div>
    </div>

    {{-- Cetak component — dengerin event cetak-form-trf-ugd-ri.open --}}
    <livewire:pages::components.modul-dokumen.u-g-d.form-trf-ugd-ri.cetak-form-trf-ugd-ri
        wire:key="cetak-form-trf-ugd-ri-{{ $rjNo ?? 'init' }}" />
</div>
