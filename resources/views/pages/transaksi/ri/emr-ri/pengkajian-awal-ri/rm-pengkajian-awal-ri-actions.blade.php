<?php
// resources/views/pages/transaksi/ri/emr-ri/pengkajian-awal/rm-pengkajian-awal-ri-actions.blade.php

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Carbon\Carbon;
use Livewire\Attributes\On;

new class extends Component {
    use EmrRITrait, WithRenderVersioningTrait;

    public bool    $isFormLocked = false;
    public ?string $riHdrNo      = null;
    public array   $dataDaftarRi = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-pengkajian-awal-ri'];

    /* ── Default struktur ── */
    public array $pengkajianAwalDefault = [
        'bagian1DataUmum' => [
            'kondisiSaatMasuk' => '', 'asalPasien' => ['pilihan' => '', 'keterangan' => ''],
            'diagnosaMasuk' => '', 'dpjp' => '',
            'barangBerharga' => ['pilihan' => '', 'catatan' => ''],
            'alatBantu'     => ['pilihan' => '', 'keterangan' => '', 'catatan' => ''],
        ],
        'bagian2RiwayatPasien' => [
            'riwayatPenyakitOperasiCedera' => ['pilihan' => '', 'keterangan' => '', 'deskripsi' => ''],
            'kebiasaan' => [
                'merokok'     => ['pilihan' => '', 'detail' => ['jenis' => '', 'jumlahPerHari' => '']],
                'alkoholObat' => ['pilihan' => '', 'detail' => ['jenis' => '', 'jumlahPerHari' => '']],
            ],
            'vaksinasi'       => ['influenza' => ['pilihan' => ''], 'pneumonia' => ['pilihan' => '']],
            'riwayatKeluarga' => ['pilihan' => '', 'keterangan' => ''],
        ],
        'bagian3PsikososialDanEkonomi' => [
            'agamaKepercayaan'  => ['pilihan' => '', 'keterangan' => ''],
            'statusPernikahan'  => ['pilihan' => ''],
            'tempatTinggal'     => ['pilihan' => '', 'keterangan' => ''],
            'aktivitas'         => ['pilihan' => ''],
            'statusEmosional'   => ['pilihan' => '', 'keterangan' => ''],
            'keluargaDekat'     => ['nama' => '', 'hubungan' => '', 'telp' => ''],
            'informasiDidapatDari' => ['pilihan' => '', 'keterangan' => ''],
        ],
        'bagian4PemeriksaanFisik' => [
            'tandaVital' => [
                'sistolik' => '', 'distolik' => '', 'frekuensiNafas' => '', 'frekuensiNadi' => '',
                'suhu' => '', 'spo2' => '', 'gda' => '', 'tb' => '', 'bb' => '',
            ],
            'keluhanUtama' => '',
            'pemeriksaanSistemOrgan' => [
                'mataTelingaHidungTenggorokan' => ['pilihan' => '', 'keterangan' => ''],
                'paru'       => ['pilihan' => '', 'keterangan' => ''],
                'jantung'    => ['pilihan' => '', 'keterangan' => ''],
                'neurologi'  => ['tingkatKesadaran' => ['pilihan' => ''], 'gcs' => ''],
                'gastrointestinal'       => ['pilihan' => '', 'keterangan' => ''],
                'genitourinaria'         => ['pilihan' => '', 'keterangan' => ''],
                'muskuloskeletalDanKulit'=> ['pilihan' => '', 'keterangan' => ''],
            ],
        ],
        'bagian5CatatanDanTandaTangan' => [
            'catatanUmum' => '', 'petugasPengkaji' => '', 'petugasPengkajiCode' => '', 'jamPengkaji' => '',
        ],
        'levelingDokter' => [],
    ];

    public function mount(): void
    {
        $this->registerAreas(['modal-pengkajian-awal-ri']);
    }

    #[On('open-rm-pengkajian-awal-ri')]
    public function open(string $riHdrNo): void
    {
        if (empty($riHdrNo)) return;
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

        $this->incrementVersion('modal-pengkajian-awal-ri');

        $riStatus = DB::scalar("select ri_status from rstxn_rihdrs where rihdr_no=:r", ['r' => $riHdrNo]);
        $this->isFormLocked = ($riStatus !== 'I');
    }

    public function store(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, form terkunci.');
            return;
        }
        try {
            $this->withRiLock(function () {
                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                $fresh['pengkajianAwalPasienRawatInap'] = $this->dataDaftarRi['pengkajianAwalPasienRawatInap'] ?? [];
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
            });
            $this->afterSave('Pengkajian Awal berhasil disimpan.');
        } catch (LockTimeoutException) {
            $this->dispatch('toast', type: 'error', message: 'Sistem sibuk, coba lagi.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    public function setPetugasPengkaji(): void
    {
        if (!auth()->user()->hasRole('Perawat')) {
            $this->dispatch('toast', type: 'error', message: 'Hanya Perawat yang dapat melakukan TTD.');
            return;
        }
        $this->dataDaftarRi['pengkajianAwalPasienRawatInap']['bagian5CatatanDanTandaTangan']['petugasPengkaji']     = auth()->user()->myuser_name;
        $this->dataDaftarRi['pengkajianAwalPasienRawatInap']['bagian5CatatanDanTandaTangan']['petugasPengkajiCode'] = auth()->user()->myuser_code;
        $this->dataDaftarRi['pengkajianAwalPasienRawatInap']['bagian5CatatanDanTandaTangan']['jamPengkaji']         = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        $this->store();
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
    }

    private function withRiLock(callable $fn): void
    {
        Cache::lock("ri:{$this->riHdrNo}", 10)->block(5, function () use ($fn) {
            DB::transaction(function () use ($fn) {
                $this->lockRIRow($this->riHdrNo); // row-level lock Oracle
                $fn();
            }, 5);
        });
    }
};
?>

<div class="space-y-4" wire:key="{{ $this->renderKey('modal-pengkajian-awal-ri', [$riHdrNo ?? 'new']) }}">

    @if ($isFormLocked)
        <div class="flex items-center gap-2 px-4 py-2.5 mb-2 rounded-lg
                    bg-amber-50 border border-amber-200 text-amber-800
                    dark:bg-amber-900/20 dark:border-amber-700 dark:text-amber-300 text-sm">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            </svg>
            Pasien sudah pulang — form dalam mode <strong>read-only</strong>.
        </div>
    @endif

    {{-- ── BAGIAN 1: Data Umum ── --}}
    <x-border-form title="Data Umum" align="start" bgcolor="bg-gray-50">
        <div class="mt-3 grid grid-cols-2 gap-4">

            <div>
                <x-input-label value="Kondisi Saat Masuk" />
                <x-select-input wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian1DataUmum.kondisiSaatMasuk"
                    class="w-full mt-1" :disabled="$isFormLocked">
                    <option value="">— Pilih —</option>
                    @foreach (['mandiri','dibantu','tirahBaring'] as $opt)
                        <option value="{{ $opt }}">{{ ucfirst($opt) }}</option>
                    @endforeach
                </x-select-input>
            </div>

            <div>
                <x-input-label value="Diagnosa Masuk" />
                <x-text-input wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian1DataUmum.diagnosaMasuk"
                    class="w-full mt-1" placeholder="Diagnosa masuk..." :disabled="$isFormLocked" />
            </div>

            <div>
                <x-input-label value="Asal Pasien" />
                <x-select-input wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian1DataUmum.asalPasien.pilihan"
                    class="w-full mt-1" :disabled="$isFormLocked">
                    <option value="">— Pilih —</option>
                    @foreach (['poliklinik','igd','kamarOperasi','lainnya'] as $opt)
                        <option value="{{ $opt }}">{{ ucfirst($opt) }}</option>
                    @endforeach
                </x-select-input>
                @if (($dataDaftarRi['pengkajianAwalPasienRawatInap']['bagian1DataUmum']['asalPasien']['pilihan'] ?? '') === 'lainnya')
                    <x-text-input wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian1DataUmum.asalPasien.keterangan"
                        class="w-full mt-1" placeholder="Keterangan..." :disabled="$isFormLocked" />
                @endif
            </div>

            <div>
                <x-input-label value="Barang Berharga" />
                <x-select-input wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian1DataUmum.barangBerharga.pilihan"
                    class="w-full mt-1" :disabled="$isFormLocked">
                    <option value="">— Pilih —</option>
                    <option value="ada">Ada</option>
                    <option value="tidakAda">Tidak Ada</option>
                </x-select-input>
            </div>

        </div>
    </x-border-form>

    {{-- ── BAGIAN 4: TTV & Pemeriksaan Fisik ── --}}
    <x-border-form title="Tanda Vital & Pemeriksaan Fisik" align="start" bgcolor="bg-gray-50">
        <div class="mt-3 grid grid-cols-3 gap-3">
            @foreach ([
                ['key'=>'sistolik',      'label'=>'Sistolik (mmHg)'],
                ['key'=>'distolik',      'label'=>'Diastolik (mmHg)'],
                ['key'=>'frekuensiNadi', 'label'=>'Nadi (x/mnt)'],
                ['key'=>'frekuensiNafas','label'=>'Nafas (x/mnt)'],
                ['key'=>'suhu',          'label'=>'Suhu (°C)'],
                ['key'=>'spo2',          'label'=>'SPO2 (%)'],
                ['key'=>'gda',           'label'=>'GDA (g/dl)'],
                ['key'=>'bb',            'label'=>'BB (Kg)'],
                ['key'=>'tb',            'label'=>'TB (Cm)'],
            ] as $ttv)
                <div>
                    <x-input-label value="{{ $ttv['label'] }}" />
                    <x-text-input
                        wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian4PemeriksaanFisik.tandaVital.{{ $ttv['key'] }}"
                        class="w-full mt-1" type="number" step="any" :disabled="$isFormLocked" />
                </div>
            @endforeach
        </div>

        <div class="mt-3">
            <x-input-label value="Keluhan Utama" />
            <x-textarea
                wire:model.live="dataDaftarRi.pengkajianAwalPasienRawatInap.bagian4PemeriksaanFisik.keluhanUtama"
                class="w-full mt-1" rows="2" :disabled="$isFormLocked" placeholder="Keluhan utama pasien..." />
        </div>
    </x-border-form>

    {{-- ── BAGIAN 5: TTD ── --}}
    <x-border-form title="Tanda Tangan Perawat Pengkaji" align="start" bgcolor="bg-gray-50">
        <div class="mt-3 flex items-center gap-4">
            <div class="flex-1">
                <x-input-label value="Petugas Pengkaji" />
                <x-text-input
                    value="{{ $dataDaftarRi['pengkajianAwalPasienRawatInap']['bagian5CatatanDanTandaTangan']['petugasPengkaji'] ?? '-' }}"
                    class="w-full mt-1" readonly />
            </div>
            <div class="flex-1">
                <x-input-label value="Jam Pengkajian" />
                <x-text-input
                    value="{{ $dataDaftarRi['pengkajianAwalPasienRawatInap']['bagian5CatatanDanTandaTangan']['jamPengkaji'] ?? '-' }}"
                    class="w-full mt-1" readonly />
            </div>
            @if (!$isFormLocked)
                @role('Perawat')
                    <div class="pt-5">
                        <x-primary-button wire:click="setPetugasPengkaji" type="button">
                            TTD Saya
                        </x-primary-button>
                    </div>
                @endrole
            @endif
        </div>
    </x-border-form>

    {{-- TOMBOL SIMPAN --}}
    @if (!$isFormLocked)
        <div class="flex justify-end pt-2">
            <x-primary-button wire:click="store" type="button">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Simpan Pengkajian Awal
            </x-primary-button>
        </div>
    @endif

</div>
