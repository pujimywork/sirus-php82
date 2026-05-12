<?php
// resources/views/pages/transaksi/ri/emr-ri/pengkajian-awal/rm-pengkajian-awal-ri-actions.blade.php

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Livewire\Attributes\On;

new class extends Component {
    use EmrRITrait, EmrUGDTrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?string $riHdrNo = null;
    public array $dataDaftarRi = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-pengkajian-awal-ri'];

    public array $pengkajianAwalDefault = [
        'bagian1DataUmum' => [
            'kondisiSaatMasuk' => '',
            'asalPasien' => ['pilihan' => '', 'keterangan' => ''],
            'diagnosaMasuk' => '',
            'dpjp' => '',
            'barangBerharga' => ['pilihan' => '', 'catatan' => ''],
            'alatBantu' => ['pilihan' => '', 'keterangan' => '', 'catatan' => ''],
        ],
        'bagian2RiwayatPasien' => [
            'riwayatPenyakitOperasiCedera' => ['pilihan' => '', 'keterangan' => '', 'deskripsi' => ''],
            'kebiasaan' => [
                'merokok' => ['pilihan' => '', 'detail' => ['jenis' => '', 'jumlahPerHari' => '']],
                'alkoholObat' => ['pilihan' => '', 'detail' => ['jenis' => '', 'jumlahPerHari' => '']],
            ],
            'vaksinasi' => ['influenza' => ['pilihan' => ''], 'pneumonia' => ['pilihan' => '']],
            'riwayatKeluarga' => ['pilihan' => '', 'keterangan' => ''],
        ],
        'bagian3PsikososialDanEkonomi' => [
            'agamaKepercayaan' => ['pilihan' => '', 'keterangan' => ''],
            'statusPernikahan' => ['pilihan' => ''],
            'tempatTinggal' => ['pilihan' => '', 'keterangan' => ''],
            'aktivitas' => ['pilihan' => ''],
            'statusEmosional' => ['pilihan' => '', 'keterangan' => ''],
            'keluargaDekat' => ['nama' => '', 'hubungan' => '', 'telp' => ''],
            'informasiDidapatDari' => ['pilihan' => '', 'keterangan' => ''],
        ],
        'bagian4PemeriksaanFisik' => [
            'tandaVital' => [
                'sistolik' => '',
                'distolik' => '',
                'frekuensiNafas' => '',
                'frekuensiNadi' => '',
                'suhu' => '',
                'spo2' => '',
                'gda' => '',
                'tb' => '',
                'bb' => '',
            ],
            'keluhanUtama' => '',
            'pemeriksaanSistemOrgan' => [
                'mataTelingaHidungTenggorokan' => ['pilihan' => '', 'keterangan' => ''],
                'paru' => ['pilihan' => '', 'keterangan' => ''],
                'jantung' => ['pilihan' => '', 'keterangan' => ''],
                'neurologi' => ['tingkatKesadaran' => ['pilihan' => ''], 'gcs' => ''],
                'gastrointestinal' => ['pilihan' => '', 'keterangan' => ''],
                'genitourinaria' => ['pilihan' => '', 'keterangan' => ''],
                'muskuloskeletalDanKulit' => ['pilihan' => '', 'keterangan' => ''],
            ],
        ],
        'bagian5CatatanDanTandaTangan' => [
            'catatanUmum' => '',
            'petugasPengkaji' => '',
            'petugasPengkajiCode' => '',
            'jamPengkaji' => '',
        ],
        'levelingDokter' => [],
    ];

    public array $levelingDokter = [
        'drId' => '',
        'drName' => '',
        'poliId' => '',
        'poliDesc' => '',
        'tglEntry' => '',
        'levelDokter' => 'Utama',
    ];

    public function mount(): void
    {
        $this->registerAreas(['modal-pengkajian-awal-ri']);
    }

    /**
     * Copy fields perawat dari kunjungan UGD (sumber) ke Pengkajian Awal RI ini.
     * Triggered dari rekam-medis-display saat user klik "Copy Asesmen Perawat" di row UGD.
     *
     * Yang di-copy:
     * - Keluhan Utama → bagian4PemeriksaanFisik.keluhanUtama
     * - Tanda Vital (sistolik/distolik/nadi/nafas/suhu/spo2/gda) → bagian4PemeriksaanFisik.tandaVital.*
     * - BB & TB (dari pemeriksaan.nutrisi) → bagian4PemeriksaanFisik.tandaVital.{bb,tb}
     *
     * Mode: FILL-ONLY — hanya isi field yang masih KOSONG di RI.
     * Field RI yang sudah ada nilainya TIDAK akan ditimpa (jaga kerja perawat sebelumnya).
     * Kalau perawat mau ganti, edit manual.
     *
     * Catatan: hasil copy hanya update memory state — user wajib klik tombol "Simpan"
     * untuk persist ke datadaftarri_json.
     */
    #[On('request-copy-assessment-from-ugd-perawat')]
    public function copyFromUGDPerawat(int $rjNoUGD): void
    {
        if (!$this->riHdrNo) {
            $this->dispatch('toast', type: 'error', message: 'Buka Pengkajian Awal terlebih dahulu sebelum copy.');
            return;
        }

        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, form terkunci.');
            return;
        }

        // Fetch data UGD via trait helper
        $ugd = $this->findDataUGD($rjNoUGD);
        if (empty($ugd)) {
            $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.');
            return;
        }

        // Pastikan target struct sudah init
        $this->dataDaftarRi['pengkajianAwalPasienRawatInap'] ??= $this->pengkajianAwalDefault;
        $this->dataDaftarRi['pengkajianAwalPasienRawatInap']['bagian4PemeriksaanFisik'] ??= [];
        $this->dataDaftarRi['pengkajianAwalPasienRawatInap']['bagian4PemeriksaanFisik']['tandaVital'] ??= [];

        $b4 = &$this->dataDaftarRi['pengkajianAwalPasienRawatInap']['bagian4PemeriksaanFisik'];

        $copied = 0;
        $skipped = 0;

        // Helper closure: fill-only — hanya set kalau target masih kosong DAN source ada nilai
        $fill = function (string &$target, string $sourceVal) use (&$copied, &$skipped): void {
            $sourceVal = trim($sourceVal);
            if ($sourceVal === '') {
                return; // source kosong, skip
            }
            if (trim($target) !== '') {
                $skipped++;
                return; // target sudah ada nilai, jaga
            }
            $target = $sourceVal;
            $copied++;
        };

        // 1) Keluhan Utama
        $b4['keluhanUtama'] ??= '';
        $fill($b4['keluhanUtama'], (string) data_get($ugd, 'anamnesa.keluhanUtama.keluhanUtama', ''));

        // 2) Tanda Vital
        $ttv = data_get($ugd, 'pemeriksaan.tandaVital', []);
        foreach (['sistolik', 'distolik', 'frekuensiNadi', 'frekuensiNafas', 'suhu', 'spo2', 'gda'] as $field) {
            $b4['tandaVital'][$field] ??= '';
            $fill($b4['tandaVital'][$field], (string) data_get($ttv, $field, ''));
        }

        // 3) BB & TB dari nutrisi
        $b4['tandaVital']['bb'] ??= '';
        $b4['tandaVital']['tb'] ??= '';
        $fill($b4['tandaVital']['bb'], (string) data_get($ugd, 'pemeriksaan.nutrisi.bb', ''));
        $fill($b4['tandaVital']['tb'], (string) data_get($ugd, 'pemeriksaan.nutrisi.tb', ''));

        unset($b4);

        $this->incrementVersion('modal-pengkajian-awal-ri');

        $msg = "Asesmen UGD: {$copied} field disalin";
        if ($skipped > 0) {
            $msg .= ", {$skipped} field di-skip (sudah ada nilai)";
        }
        $msg .= '. Klik Simpan untuk persist.';
        $this->dispatch('toast', type: 'success', message: $msg);
    }

    #[On('open-rm-pengkajian-awal-ri')]
    public function open(string $riHdrNo): void
    {
        if (empty($riHdrNo)) {
            return;
        }

        $this->riHdrNo = $riHdrNo;
        $this->resetForm();
        $this->resetValidation();

        $data = $this->findDataRI($riHdrNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }

        $this->dataDaftarRi = $data;
        $this->dataDaftarRi['pengkajianAwalPasienRawatInap'] ??= $this->pengkajianAwalDefault;

        $this->isFormLocked = $this->checkEmrRIStatus($riHdrNo); // ← trait

        $this->incrementVersion('modal-pengkajian-awal-ri');
    }

    #[On('save-rm-pengkajian-awal-ri')]
    public function save(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, form terkunci.');
            return;
        }

        try {
            DB::transaction(function () {
                // ← trait pattern
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                $fresh['pengkajianAwalPasienRawatInap'] = $this->dataDaftarRi['pengkajianAwalPasienRawatInap'] ?? [];
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
            });
            $this->afterSave('Pengkajian Awal berhasil disimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    public function setPetugasPengkaji(): void
    {
        if (!auth()->user()->hasAnyRole(['Perawat', 'Admin'])) {
            $this->dispatch('toast', type: 'error', message: 'Hanya Perawat / Admin yang dapat melakukan TTD.');
            return;
        }

        $this->dataDaftarRi['pengkajianAwalPasienRawatInap']['bagian5CatatanDanTandaTangan']['petugasPengkaji'] = auth()->user()->myuser_name;
        $this->dataDaftarRi['pengkajianAwalPasienRawatInap']['bagian5CatatanDanTandaTangan']['petugasPengkajiCode'] = auth()->user()->myuser_code;
        $this->dataDaftarRi['pengkajianAwalPasienRawatInap']['bagian5CatatanDanTandaTangan']['jamPengkaji'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        $this->save();
    }

    #[On('lov.selected.leveling-dokter-ri')]
    public function onDokterSelected(string $target, array $payload): void
    {
        $this->levelingDokter['drId'] = $payload['dr_id'] ?? '';
        $this->levelingDokter['drName'] = $payload['dr_name'] ?? '';
        $this->levelingDokter['poliId'] = $payload['poli_id'] ?? '';
        $this->levelingDokter['poliDesc'] = $payload['poli_desc'] ?? '';

        $sudahAdaUtama = collect($this->dataDaftarRi['pengkajianAwalPasienRawatInap']['levelingDokter'] ?? [])->contains('levelDokter', 'Utama');

        $this->levelingDokter['levelDokter'] = $sudahAdaUtama ? 'RawatGabung' : 'Utama';
    }

    public function addLevelingDokter(): void
    {
        $this->levelingDokter['tglEntry'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        $this->validate(
            [
                'levelingDokter.drId' => 'required|string|max:10',
                'levelingDokter.drName' => 'required|string|max:200',
                'levelingDokter.poliId' => 'required|string|max:10',
                'levelingDokter.poliDesc' => 'required|string|max:50',
                'levelingDokter.levelDokter' => 'required|in:Utama,RawatGabung',
            ],
            [
                'levelingDokter.drId.required' => 'Dokter harus dipilih.',
                'levelingDokter.poliId.required' => 'Poli harus dipilih.',
                'levelingDokter.levelDokter.required' => 'Level dokter harus dipilih.',
            ],
        );

        $existing = collect($this->dataDaftarRi['pengkajianAwalPasienRawatInap']['levelingDokter'] ?? [])
            ->where('drId', $this->levelingDokter['drId'])
            ->count();

        if ($existing) {
            $this->dispatch('toast', type: 'error', message: 'Dokter sudah ada dalam daftar.');
            return;
        }

        $this->dataDaftarRi['pengkajianAwalPasienRawatInap']['levelingDokter'][] = [
            'drId' => $this->levelingDokter['drId'],
            'drName' => $this->levelingDokter['drName'],
            'poliId' => $this->levelingDokter['poliId'],
            'poliDesc' => $this->levelingDokter['poliDesc'],
            'tglEntry' => $this->levelingDokter['tglEntry'],
            'levelDokter' => $this->levelingDokter['levelDokter'],
        ];

        $this->save();
        $this->reset(['levelingDokter']);
        $this->levelingDokter['levelDokter'] = 'Utama';
    }

    public function removeLevelingDokter(string $tglEntry): void
    {
        $list = collect($this->dataDaftarRi['pengkajianAwalPasienRawatInap']['levelingDokter'] ?? []);
        $this->dataDaftarRi['pengkajianAwalPasienRawatInap']['levelingDokter'] = $list->where('tglEntry', '!=', $tglEntry)->values()->toArray();
        $this->save();
    }

    public function setLevelDokter(int $index, string $level): void
    {
        $list = &$this->dataDaftarRi['pengkajianAwalPasienRawatInap']['levelingDokter'];
        if (!isset($list[$index])) {
            $this->dispatch('toast', type: 'error', message: 'Data tidak ditemukan.');
            return;
        }
        if (($list[$index]['levelDokter'] ?? '') === $level) {
            $this->dispatch('toast', type: 'error', message: "Sudah berstatus {$level}.");
            return;
        }
        $list[$index]['levelDokter'] = $level;
        $this->save();
    }

    private function afterSave(string $msg): void
    {
        $this->incrementVersion('modal-pengkajian-awal-ri');
        $this->dispatch('toast', type: 'success', message: $msg);
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->reset(['levelingDokter']);
        $this->levelingDokter['levelDokter'] = 'Utama';
    }
};
?>

<div class="space-y-4" wire:key="{{ $this->renderKey('modal-pengkajian-awal-ri', [$riHdrNo ?? 'new']) }}">

    {{-- ── Read-only banner ── --}}
    @if ($isFormLocked)
        <div
            class="flex items-center gap-2 px-4 py-2.5 rounded-lg
                    bg-amber-50 border border-amber-200 text-amber-800
                    dark:bg-amber-900/20 dark:border-amber-700 dark:text-amber-300 text-sm">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
            </svg>
            Pasien sudah pulang — form dalam mode <strong>read-only</strong>.
        </div>
    @endif

    {{-- ══════════════════════════════════════════
    | BAGIAN 1 — DATA UMUM
    ══════════════════════════════════════════ --}}
    <x-border-form title="Bagian 1 — Data Umum" align="start" bgcolor="bg-gray-50" :collapsible="true" :open="true">
        <div class="mt-3 grid grid-cols-4 gap-2">

            {{-- Kondisi Saat Masuk --}}
            <div>
                <x-input-label value="Kondisi Saat Masuk" />
                <x-select-input
                    wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian1DataUmum.kondisiSaatMasuk"
                    class="w-full mt-1" :disabled="$isFormLocked">
                    <option value="">— Pilih —</option>
                    <option value="mandiri">Mandiri</option>
                    <option value="dibantu">Dibantu</option>
                    <option value="tirahBaring">Tirah Baring</option>
                </x-select-input>
            </div>

            {{-- Diagnosa Masuk --}}
            <div>
                <x-input-label value="Diagnosa Masuk" />
                <x-text-input wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian1DataUmum.diagnosaMasuk"
                    class="w-full mt-1" placeholder="Diagnosa masuk..." :disabled="$isFormLocked" />
            </div>

            {{-- Asal Pasien --}}
            <div>
                <x-input-label value="Asal Pasien" />
                <x-select-input
                    wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian1DataUmum.asalPasien.pilihan"
                    class="w-full mt-1" :disabled="$isFormLocked">
                    <option value="">— Pilih —</option>
                    <option value="poliklinik">Poliklinik</option>
                    <option value="igd">IGD</option>
                    <option value="kamarOperasi">Kamar Operasi</option>
                    <option value="lainnya">Lainnya</option>
                </x-select-input>
                @if (($dataDaftarRi['pengkajianAwalPasienRawatInap']['bagian1DataUmum']['asalPasien']['pilihan'] ?? '') === 'lainnya')
                    <x-text-input
                        wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian1DataUmum.asalPasien.keterangan"
                        class="w-full mt-1" placeholder="Keterangan asal pasien..." :disabled="$isFormLocked" />
                @endif
            </div>

            {{-- DPJP --}}
            <div>
                <x-input-label value="DPJP" />
                <x-text-input wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian1DataUmum.dpjp"
                    class="w-full mt-1" placeholder="Nama DPJP..." :disabled="$isFormLocked" />
            </div>

            {{-- Barang Berharga --}}
            <div>
                <x-input-label value="Barang Berharga" />
                <x-select-input
                    wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian1DataUmum.barangBerharga.pilihan"
                    class="w-full mt-1" :disabled="$isFormLocked">
                    <option value="">— Pilih —</option>
                    <option value="ada">Ada</option>
                    <option value="tidakAda">Tidak Ada</option>
                </x-select-input>
                @if (($dataDaftarRi['pengkajianAwalPasienRawatInap']['bagian1DataUmum']['barangBerharga']['pilihan'] ?? '') === 'ada')
                    <x-text-input
                        wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian1DataUmum.barangBerharga.catatan"
                        class="w-full mt-1" placeholder="Catatan barang berharga..." :disabled="$isFormLocked" />
                @endif
            </div>

            {{-- Alat Bantu --}}
            <div>
                <x-input-label value="Alat Bantu" />
                <x-select-input
                    wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian1DataUmum.alatBantu.pilihan"
                    class="w-full mt-1" :disabled="$isFormLocked">
                    <option value="">— Pilih —</option>
                    <option value="kacamata">Kacamata</option>
                    <option value="gigiPalsu">Gigi Palsu</option>
                    <option value="alatBantuDengar">Alat Bantu Dengar</option>
                    <option value="lainnya">Lainnya</option>
                </x-select-input>
                @if (($dataDaftarRi['pengkajianAwalPasienRawatInap']['bagian1DataUmum']['alatBantu']['pilihan'] ?? '') === 'lainnya')
                    <x-text-input
                        wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian1DataUmum.alatBantu.keterangan"
                        class="w-full mt-1" placeholder="Keterangan alat bantu..." :disabled="$isFormLocked" />
                @endif
                @if (!empty($dataDaftarRi['pengkajianAwalPasienRawatInap']['bagian1DataUmum']['alatBantu']['pilihan']))
                    <x-text-input
                        wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian1DataUmum.alatBantu.catatan"
                        class="w-full mt-1" placeholder="Catatan alat bantu..." :disabled="$isFormLocked" />
                @endif
            </div>

        </div>
    </x-border-form>

    {{-- ══════════════════════════════════════════
    | BAGIAN 2 — RIWAYAT PASIEN
    ══════════════════════════════════════════ --}}
    <x-border-form title="Bagian 2 — Riwayat Pasien" align="start" bgcolor="bg-gray-50" :collapsible="true" :open="false">
        <div class="mt-3 space-y-4">

            {{-- Riwayat Penyakit / Operasi / Cedera --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <x-input-label value="Riwayat Penyakit / Operasi / Cedera" />
                    <x-select-input
                        wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian2RiwayatPasien.riwayatPenyakitOperasiCedera.pilihan"
                        class="w-full mt-1" :disabled="$isFormLocked">
                        <option value="">— Pilih —</option>
                        <option value="hipertensi">Hipertensi</option>
                        <option value="diabetes">Diabetes</option>
                        <option value="asma">Asma</option>
                        <option value="stroke">Stroke</option>
                        <option value="penyakitJantung">Penyakit Jantung</option>
                        <option value="lainnya">Lainnya</option>
                    </x-select-input>
                    @if (
                        ($dataDaftarRi['pengkajianAwalPasienRawatInap']['bagian2RiwayatPasien']['riwayatPenyakitOperasiCedera'][
                            'pilihan'
                        ] ??
                            '') ===
                            'lainnya')
                        <x-text-input
                            wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian2RiwayatPasien.riwayatPenyakitOperasiCedera.keterangan"
                            class="w-full mt-1" placeholder="Keterangan riwayat..." :disabled="$isFormLocked" />
                    @endif
                </div>
                <div>
                    <x-input-label value="Deskripsi Riwayat" />
                    <x-textarea
                        wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian2RiwayatPasien.riwayatPenyakitOperasiCedera.deskripsi"
                        class="w-full mt-1" rows="3" placeholder="Deskripsi riwayat penyakit..."
                        :disabled="$isFormLocked" />
                </div>
            </div>

            {{-- Kebiasaan: Merokok --}}
            <div class="p-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                <p class="text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2 uppercase tracking-wide">Kebiasaan
                    Merokok</p>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <x-input-label value="Status" />
                        <x-select-input
                            wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian2RiwayatPasien.kebiasaan.merokok.pilihan"
                            class="w-full mt-1" :disabled="$isFormLocked">
                            <option value="">— Pilih —</option>
                            <option value="ya">Ya</option>
                            <option value="tidak">Tidak</option>
                            <option value="berhenti">Berhenti</option>
                        </x-select-input>
                    </div>
                    @if (in_array(
                            $dataDaftarRi['pengkajianAwalPasienRawatInap']['bagian2RiwayatPasien']['kebiasaan']['merokok']['pilihan'] ?? '',
                            ['ya', 'berhenti']))
                        <div>
                            <x-input-label value="Jenis Rokok" />
                            <x-text-input
                                wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian2RiwayatPasien.kebiasaan.merokok.detail.jenis"
                                class="w-full mt-1" placeholder="Filter, Kretek, dll..." :disabled="$isFormLocked" />
                        </div>
                        <div>
                            <x-input-label value="Jumlah/Hari (batang)" />
                            <x-text-input
                                wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian2RiwayatPasien.kebiasaan.merokok.detail.jumlahPerHari"
                                class="w-full mt-1" type="number" step="1" min="0"
                                :disabled="$isFormLocked" />
                        </div>
                    @endif
                </div>
            </div>

            {{-- Kebiasaan: Alkohol/Obat --}}
            <div class="p-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                <p class="text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2 uppercase tracking-wide">
                    Kebiasaan Alkohol / Obat-obatan</p>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <x-input-label value="Status" />
                        <x-select-input
                            wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian2RiwayatPasien.kebiasaan.alkoholObat.pilihan"
                            class="w-full mt-1" :disabled="$isFormLocked">
                            <option value="">— Pilih —</option>
                            <option value="ya">Ya</option>
                            <option value="tidak">Tidak</option>
                            <option value="berhenti">Berhenti</option>
                        </x-select-input>
                    </div>
                    @if (in_array(
                            $dataDaftarRi['pengkajianAwalPasienRawatInap']['bagian2RiwayatPasien']['kebiasaan']['alkoholObat']['pilihan'] ??
                                '',
                            ['ya', 'berhenti']))
                        <div>
                            <x-input-label value="Jenis" />
                            <x-text-input
                                wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian2RiwayatPasien.kebiasaan.alkoholObat.detail.jenis"
                                class="w-full mt-1" placeholder="Jenis alkohol/obat..." :disabled="$isFormLocked" />
                        </div>
                        <div>
                            <x-input-label value="Jumlah/Hari" />
                            <x-text-input
                                wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian2RiwayatPasien.kebiasaan.alkoholObat.detail.jumlahPerHari"
                                class="w-full mt-1" type="number" step="any" min="0"
                                :disabled="$isFormLocked" />
                        </div>
                    @endif
                </div>
            </div>

            {{-- Vaksinasi --}}
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <x-input-label value="Vaksinasi Influenza" />
                    <x-select-input
                        wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian2RiwayatPasien.vaksinasi.influenza.pilihan"
                        class="w-full mt-1" :disabled="$isFormLocked">
                        <option value="">— Pilih —</option>
                        <option value="ya">Ya</option>
                        <option value="tidak">Tidak</option>
                        <option value="menolak">Menolak</option>
                    </x-select-input>
                </div>
                <div>
                    <x-input-label value="Vaksinasi Pneumonia" />
                    <x-select-input
                        wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian2RiwayatPasien.vaksinasi.pneumonia.pilihan"
                        class="w-full mt-1" :disabled="$isFormLocked">
                        <option value="">— Pilih —</option>
                        <option value="ya">Ya</option>
                        <option value="tidak">Tidak</option>
                        <option value="menolak">Menolak</option>
                    </x-select-input>
                </div>
            </div>

            {{-- Riwayat Keluarga --}}
            <div class="grid grid-cols-1 gap-4">
                <div>
                    <x-input-label value="Riwayat Penyakit Keluarga" />
                    <x-select-input
                        wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian2RiwayatPasien.riwayatKeluarga.pilihan"
                        class="w-full mt-1" :disabled="$isFormLocked">
                        <option value="">— Pilih —</option>
                        <option value="penyakitJantung">Penyakit Jantung</option>
                        <option value="hipertensi">Hipertensi</option>
                        <option value="diabetes">Diabetes</option>
                        <option value="stroke">Stroke</option>
                        <option value="lainnya">Lainnya</option>
                    </x-select-input>
                </div>
                @if (
                    ($dataDaftarRi['pengkajianAwalPasienRawatInap']['bagian2RiwayatPasien']['riwayatKeluarga']['pilihan'] ?? '') ===
                        'lainnya')
                    <div>
                        <x-input-label value="Keterangan Riwayat Keluarga" />
                        <x-text-input
                            wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian2RiwayatPasien.riwayatKeluarga.keterangan"
                            class="w-full mt-1" placeholder="Keterangan..." :disabled="$isFormLocked" />
                    </div>
                @endif
            </div>

        </div>
    </x-border-form>

    {{-- ══════════════════════════════════════════
    | BAGIAN 3 — PSIKOSOSIAL & EKONOMI
    ══════════════════════════════════════════ --}}
    <x-border-form title="Bagian 3 — Psikososial & Ekonomi" align="start" bgcolor="bg-gray-50" :collapsible="true" :open="false">
        <div class="mt-3 grid grid-cols-6 gap-2">

            {{-- Agama / Kepercayaan --}}
            <div>
                <x-input-label value="Agama / Kepercayaan" />
                <x-select-input
                    wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian3PsikososialDanEkonomi.agamaKepercayaan.pilihan"
                    class="w-full mt-1" :disabled="$isFormLocked">
                    <option value="">— Pilih —</option>
                    <option value="islam">Islam</option>
                    <option value="kristen">Kristen</option>
                    <option value="hindu">Hindu</option>
                    <option value="budha">Budha</option>
                    <option value="lainnya">Lainnya</option>
                </x-select-input>
                @if (
                    ($dataDaftarRi['pengkajianAwalPasienRawatInap']['bagian3PsikososialDanEkonomi']['agamaKepercayaan']['pilihan'] ??
                        '') ===
                        'lainnya')
                    <x-text-input
                        wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian3PsikososialDanEkonomi.agamaKepercayaan.keterangan"
                        class="w-full mt-1" placeholder="Keterangan agama..." :disabled="$isFormLocked" />
                @endif
            </div>

            {{-- Status Pernikahan --}}
            <div>
                <x-input-label value="Status Pernikahan" />
                <x-select-input
                    wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian3PsikososialDanEkonomi.statusPernikahan.pilihan"
                    class="w-full mt-1" :disabled="$isFormLocked">
                    <option value="">— Pilih —</option>
                    <option value="menikah">Menikah</option>
                    <option value="belumMenikah">Belum Menikah</option>
                    <option value="dudaJanda">Duda / Janda</option>
                </x-select-input>
            </div>

            {{-- Tempat Tinggal --}}
            <div>
                <x-input-label value="Tempat Tinggal" />
                <x-select-input
                    wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian3PsikososialDanEkonomi.tempatTinggal.pilihan"
                    class="w-full mt-1" :disabled="$isFormLocked">
                    <option value="">— Pilih —</option>
                    <option value="rumah">Rumah</option>
                    <option value="panti">Panti</option>
                    <option value="lainnya">Lainnya</option>
                </x-select-input>
                @if (
                    ($dataDaftarRi['pengkajianAwalPasienRawatInap']['bagian3PsikososialDanEkonomi']['tempatTinggal']['pilihan'] ??
                        '') ===
                        'lainnya')
                    <x-text-input
                        wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian3PsikososialDanEkonomi.tempatTinggal.keterangan"
                        class="w-full mt-1" placeholder="Keterangan tempat tinggal..." :disabled="$isFormLocked" />
                @endif
            </div>

            {{-- Aktivitas --}}
            <div>
                <x-input-label value="Aktivitas" />
                <x-select-input
                    wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian3PsikososialDanEkonomi.aktivitas.pilihan"
                    class="w-full mt-1" :disabled="$isFormLocked">
                    <option value="">— Pilih —</option>
                    <option value="mandiri">Mandiri</option>
                    <option value="dibantu">Dibantu</option>
                    <option value="tirahBaring">Tirah Baring</option>
                </x-select-input>
            </div>

            {{-- Status Emosional --}}
            <div>
                <x-input-label value="Status Emosional" />
                <x-select-input
                    wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian3PsikososialDanEkonomi.statusEmosional.pilihan"
                    class="w-full mt-1" :disabled="$isFormLocked">
                    <option value="">— Pilih —</option>
                    <option value="kooperatif">Kooperatif</option>
                    <option value="cemas">Cemas</option>
                    <option value="depresi">Depresi</option>
                    <option value="lainnya">Lainnya</option>
                </x-select-input>
                @if (
                    ($dataDaftarRi['pengkajianAwalPasienRawatInap']['bagian3PsikososialDanEkonomi']['statusEmosional']['pilihan'] ??
                        '') ===
                        'lainnya')
                    <x-text-input
                        wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian3PsikososialDanEkonomi.statusEmosional.keterangan"
                        class="w-full mt-1" placeholder="Keterangan status emosional..." :disabled="$isFormLocked" />
                @endif
            </div>

            {{-- Informasi Didapat Dari --}}
            <div>
                <x-input-label value="Informasi Didapat Dari" />
                <x-select-input
                    wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian3PsikososialDanEkonomi.informasiDidapatDari.pilihan"
                    class="w-full mt-1" :disabled="$isFormLocked">
                    <option value="">— Pilih —</option>
                    <option value="pasien">Pasien</option>
                    <option value="keluarga">Keluarga</option>
                    <option value="lainnya">Lainnya</option>
                </x-select-input>
                @if (
                    ($dataDaftarRi['pengkajianAwalPasienRawatInap']['bagian3PsikososialDanEkonomi']['informasiDidapatDari'][
                        'pilihan'
                    ] ??
                        '') ===
                        'lainnya')
                    <x-text-input
                        wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian3PsikososialDanEkonomi.informasiDidapatDari.keterangan"
                        class="w-full mt-1" placeholder="Keterangan sumber informasi..." :disabled="$isFormLocked" />
                @endif
            </div>

        </div>

        {{-- Keluarga Dekat --}}
        <div class="mt-4 p-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
            <p class="text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2 uppercase tracking-wide">Keluarga
                Dekat yang Dapat Dihubungi</p>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <x-input-label value="Nama" />
                    <x-text-input
                        wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian3PsikososialDanEkonomi.keluargaDekat.nama"
                        class="w-full mt-1" placeholder="Nama keluarga..." :disabled="$isFormLocked" />
                </div>
                <div>
                    <x-input-label value="Hubungan" />
                    <x-text-input
                        wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian3PsikososialDanEkonomi.keluargaDekat.hubungan"
                        class="w-full mt-1" placeholder="Ayah, Ibu, Suami, dll..." :disabled="$isFormLocked" />
                </div>
                <div>
                    <x-input-label value="No. Telepon" />
                    <x-text-input
                        wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian3PsikososialDanEkonomi.keluargaDekat.telp"
                        class="w-full mt-1" placeholder="08xxxxxxxxxx" :disabled="$isFormLocked" />
                </div>
            </div>
        </div>
    </x-border-form>

    {{-- ══════════════════════════════════════════
    | BAGIAN 4 — TTV & PEMERIKSAAN FISIK
    ══════════════════════════════════════════ --}}
    <x-border-form title="Bagian 4 — Tanda Vital & Pemeriksaan Fisik" align="start" bgcolor="bg-gray-50" :collapsible="true" :open="false">

        {{-- TTV --}}
        <div class="mt-3 grid grid-cols-9 gap-2">
            @foreach ([['key' => 'sistolik', 'label' => 'Sistolik (mmHg)'], ['key' => 'distolik', 'label' => 'Diastolik (mmHg)'], ['key' => 'frekuensiNadi', 'label' => 'Nadi (x/mnt)'], ['key' => 'frekuensiNafas', 'label' => 'Nafas (x/mnt)'], ['key' => 'suhu', 'label' => 'Suhu (°C)'], ['key' => 'spo2', 'label' => 'SPO2 (%)'], ['key' => 'gda', 'label' => 'GDA (g/dl)'], ['key' => 'bb', 'label' => 'BB (Kg)'], ['key' => 'tb', 'label' => 'TB (Cm)']] as $ttv)
                <div>
                    <x-input-label value="{{ $ttv['label'] }}" />
                    <x-text-input
                        wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian4PemeriksaanFisik.tandaVital.{{ $ttv['key'] }}"
                        class="w-full mt-1" type="number" step="any" :disabled="$isFormLocked" />
                </div>
            @endforeach
        </div>

        {{-- Keluhan Utama --}}
        <div class="mt-3">
            <x-input-label value="Keluhan Utama" />
            <x-textarea
                wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian4PemeriksaanFisik.keluhanUtama"
                class="w-full mt-1" rows="2" placeholder="Keluhan utama pasien..." :disabled="$isFormLocked" />
        </div>

        {{-- Pemeriksaan Sistem Organ --}}
        <div class="mt-4">
            <p class="text-xs font-semibold text-gray-600 dark:text-gray-400 mb-3 uppercase tracking-wide">Pemeriksaan
                Sistem Organ</p>

            @php
                $organSystems = [
                    [
                        'path' => 'mataTelingaHidungTenggorokan',
                        'label' => 'Mata, Telinga, Hidung & Tenggorokan',
                        'opts' => [
                            'normal' => 'Normal',
                            'gangguanVisus' => 'Gangguan Visus',
                            'tuli' => 'Tuli',
                            'lainnya' => 'Lainnya',
                        ],
                    ],
                    [
                        'path' => 'paru',
                        'label' => 'Paru',
                        'opts' => [
                            'normal' => 'Normal',
                            'ronki' => 'Ronki',
                            'wheezing' => 'Wheezing',
                            'lainnya' => 'Lainnya',
                        ],
                    ],
                    [
                        'path' => 'jantung',
                        'label' => 'Jantung',
                        'opts' => [
                            'normal' => 'Normal',
                            'takikardi' => 'Takikardi',
                            'bradikardi' => 'Bradikardi',
                            'lainnya' => 'Lainnya',
                        ],
                    ],
                    [
                        'path' => 'gastrointestinal',
                        'label' => 'Gastrointestinal',
                        'opts' => [
                            'normal' => 'Normal',
                            'distensi' => 'Distensi',
                            'diare' => 'Diare',
                            'konstipasi' => 'Konstipasi',
                            'lainnya' => 'Lainnya',
                        ],
                    ],
                    [
                        'path' => 'genitourinaria',
                        'label' => 'Genitourinaria',
                        'opts' => [
                            'normal' => 'Normal',
                            'hematuria' => 'Hematuria',
                            'inkontinensia' => 'Inkontinensia',
                            'lainnya' => 'Lainnya',
                        ],
                    ],
                    [
                        'path' => 'muskuloskeletalDanKulit',
                        'label' => 'Muskuloskeletal & Kulit',
                        'opts' => [
                            'normal' => 'Normal',
                            'deformitas' => 'Deformitas',
                            'luka' => 'Luka',
                            'lainnya' => 'Lainnya',
                        ],
                    ],
                ];
            @endphp

            <div class="grid grid-cols-8 gap-2">
                @foreach ($organSystems as $organ)
                    @php
                        $currentPilihan =
                            $dataDaftarRi['pengkajianAwalPasienRawatInap']['bagian4PemeriksaanFisik'][
                                'pemeriksaanSistemOrgan'
                            ][$organ['path']]['pilihan'] ?? '';
                    @endphp
                    <div class="p-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                        <x-input-label value="{{ $organ['label'] }}" />
                        <x-select-input
                            wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian4PemeriksaanFisik.pemeriksaanSistemOrgan.{{ $organ['path'] }}.pilihan"
                            class="w-full mt-1" :disabled="$isFormLocked">
                            <option value="">— Pilih —</option>
                            @foreach ($organ['opts'] as $val => $label)
                                <option value="{{ $val }}">{{ $label }}</option>
                            @endforeach
                        </x-select-input>
                        @if ($currentPilihan === 'lainnya')
                            <x-text-input
                                wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian4PemeriksaanFisik.pemeriksaanSistemOrgan.{{ $organ['path'] }}.keterangan"
                                class="w-full mt-1" placeholder="Keterangan..." :disabled="$isFormLocked" />
                        @endif
                    </div>
                @endforeach

                {{-- Neurologi (special — punya GCS) --}}
                @php
                    $neuroKesadaran =
                        $dataDaftarRi['pengkajianAwalPasienRawatInap']['bagian4PemeriksaanFisik'][
                            'pemeriksaanSistemOrgan'
                        ]['neurologi']['tingkatKesadaran']['pilihan'] ?? '';
                @endphp
                <div
                    class="p-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 col-span-2">
                    <p class="text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2">Neurologi</p>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <x-input-label value="Tingkat Kesadaran" />
                            <x-select-input
                                wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian4PemeriksaanFisik.pemeriksaanSistemOrgan.neurologi.tingkatKesadaran.pilihan"
                                class="w-full mt-1" :disabled="$isFormLocked">
                                <option value="">— Pilih —</option>
                                <option value="komposMentis">Kompos Mentis</option>
                                <option value="apatis">Apatis</option>
                                <option value="somnolen">Somnolen</option>
                                <option value="sopor">Sopor</option>
                                <option value="koma">Koma</option>
                                <option value="delirium">Delirium</option>
                            </x-select-input>
                        </div>
                        <div>
                            <x-input-label value="GCS (E/V/M)" />
                            <x-text-input
                                wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian4PemeriksaanFisik.pemeriksaanSistemOrgan.neurologi.gcs"
                                class="w-full mt-1" placeholder="Contoh: E4V5M6" :disabled="$isFormLocked" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-border-form>

    {{-- ══════════════════════════════════════════
    | LEVELING DOKTER
    ══════════════════════════════════════════ --}}
    <x-border-form title="Leveling Dokter (DPJP)" align="start" bgcolor="bg-gray-50" :collapsible="true" :open="false">

        {{-- Tabel Leveling Dokter --}}
        @php $levelingList = $dataDaftarRi['pengkajianAwalPasienRawatInap']['levelingDokter'] ?? []; @endphp

        @if (count($levelingList) > 0)
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-xs border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                    <thead class="bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 uppercase">
                        <tr>
                            <th class="px-3 py-2 text-left">Dokter</th>
                            <th class="px-3 py-2 text-left">Poli</th>
                            <th class="px-3 py-2 text-left">Level</th>
                            <th class="px-3 py-2 text-left">Tgl Entry</th>
                            @if (!$isFormLocked)
                                <th class="px-3 py-2 text-center">Aksi</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($levelingList as $idx => $dr)
                            <tr class="bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-3 py-2 font-medium text-gray-900 dark:text-gray-100">
                                    {{ $dr['drName'] ?? '-' }}</td>
                                <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $dr['poliDesc'] ?? '-' }}
                                </td>
                                <td class="px-3 py-2">
                                    @if (($dr['levelDokter'] ?? '') === 'Utama')
                                        <span
                                            class="px-2 py-0.5 rounded-full bg-brand/10 text-brand font-semibold text-xs">Utama</span>
                                    @else
                                        <span
                                            class="px-2 py-0.5 rounded-full bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300 font-semibold text-xs">Rawat
                                            Gabung</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 font-mono text-gray-500">{{ $dr['tglEntry'] ?? '-' }}</td>
                                @if (!$isFormLocked)
                                    <td class="px-3 py-2 text-center">
                                        <div class="flex items-center justify-center gap-1">
                                            @if (($dr['levelDokter'] ?? '') !== 'Utama')
                                                <x-ghost-button type="button" class="!text-xs !py-0.5 !px-2"
                                                    wire:click="setLevelDokter({{ $idx }}, 'Utama')">
                                                    Utama
                                                </x-ghost-button>
                                            @endif
                                            @if (($dr['levelDokter'] ?? '') !== 'RawatGabung')
                                                <x-ghost-button type="button" class="!text-xs !py-0.5 !px-2"
                                                    wire:click="setLevelDokter({{ $idx }}, 'RawatGabung')">
                                                    RG
                                                </x-ghost-button>
                                            @endif
                                            <x-danger-button type="button" class="!text-xs !py-0.5 !px-2"
                                                wire:click="removeLevelingDokter('{{ $dr['tglEntry'] }}')"
                                                wire:confirm="Hapus dokter ini dari daftar?">
                                                Hapus
                                            </x-danger-button>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="mt-3 text-xs text-gray-400 italic">Belum ada dokter dalam daftar leveling.</p>
        @endif

        {{-- Form Tambah Leveling Dokter --}}
        @if (!$isFormLocked)
            <div
                class="mt-4 p-3 rounded-lg border border-dashed border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800/50">
                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-3 uppercase tracking-wide">Tambah
                    Dokter</p>
                <div class="grid grid-cols-5 gap-3 items-end">

                    {{-- LOV Dokter --}}
                    <div class="col-span-2">
                        <livewire:lov.dokter.lov-dokter target="leveling-dokter-ri" label="Cari Dokter"
                            placeholder="Ketik nama/kode dokter..." :initialDrId="$levelingDokter['drId'] ?? null"
                            wire:key="lov-dokter-leveling-ri-{{ $riHdrNo }}" />
                        <x-input-error :messages="$errors->get('levelingDokter.drId')" class="mt-1" />

                        {{-- Preview dokter terpilih --}}
                        @if (!empty($levelingDokter['drName']))
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Poli: <span
                                    class="font-medium text-gray-700 dark:text-gray-300">{{ $levelingDokter['poliDesc'] ?? '-' }}</span>
                            </p>
                        @endif
                    </div>

                    {{-- Level Dokter --}}
                    <div class="col-span-2">
                        <x-input-label value="Level Dokter" />
                        <x-select-input wire:model.live="levelingDokter.levelDokter" class="w-full mt-1">
                            <option value="Utama">Utama</option>
                            <option value="RawatGabung">Rawat Gabung</option>
                        </x-select-input>
                        <x-input-error :messages="$errors->get('levelingDokter.levelDokter')" class="mt-1" />
                    </div>

                    {{-- Tombol Tambah --}}
                    <div class="col-span-1">
                        <x-primary-button type="button" wire:click="addLevelingDokter" :disabled="empty($levelingDokter['drId'])">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4v16m8-8H4" />
                            </svg>
                            Tambah Dokter
                        </x-primary-button>
                    </div>

                </div>
            </div>
        @endif
    </x-border-form>

    {{-- ══════════════════════════════════════════
    | BAGIAN 5 — CATATAN & TTD
    ══════════════════════════════════════════ --}}
    <x-border-form title="Bagian 5 — Catatan & Tanda Tangan" align="start" bgcolor="bg-gray-50" :collapsible="true" :open="false">

        {{-- Catatan Umum --}}
        <div class="mt-3">
            <x-input-label value="Catatan Umum" />
            <x-textarea
                wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian5CatatanDanTandaTangan.catatanUmum"
                class="w-full mt-1" rows="2" placeholder="Catatan tambahan..." :disabled="$isFormLocked" />
        </div>

        {{-- TTD Perawat --}}
        <div class="mt-3 flex items-center gap-4">
            <div class="flex-1">
                <x-input-label value="Petugas Pengkaji" />
                <x-text-input
                    value="{{ $dataDaftarRi['pengkajianAwalPasienRawatInap']['bagian5CatatanDanTandaTangan']['petugasPengkaji'] ?? '-' }}"
                    class="w-full mt-1" :disabled="true" readonly />
            </div>
            <div class="flex-1">
                <x-input-label value="Jam Pengkajian" />
                <x-text-input
                    value="{{ $dataDaftarRi['pengkajianAwalPasienRawatInap']['bagian5CatatanDanTandaTangan']['jamPengkaji'] ?? '-' }}"
                    class="w-full mt-1" :disabled="true" readonly />
            </div>
            @if (!$isFormLocked)
                @hasanyrole('Perawat|Admin')
                    <div class="pt-5">
                        <x-primary-button wire:click="setPetugasPengkaji" type="button">
                            TTD Saya
                        </x-primary-button>
                    </div>
                @endhasanyrole
            @endif
        </div>
    </x-border-form>

    {{-- ── TOMBOL SIMPAN ── --}}
    @if (!$isFormLocked)
        <div class="flex justify-end pt-2">
            <x-primary-button wire:click="save" type="button" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save" class="flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Simpan Pengkajian Awal
                </span>
                <span wire:loading wire:target="save" class="flex items-center gap-1">
                    <x-loading /> Menyimpan...
                </span>
            </x-primary-button>
        </div>
    @endif

</div>
