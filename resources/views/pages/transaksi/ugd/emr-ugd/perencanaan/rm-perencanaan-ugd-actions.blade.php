<?php
// resources/views/pages/transaksi/ugd/emr-ugd/perencanaan/rm-perencanaan-ugd-actions.blade.php

use Livewire\Component;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Carbon\Carbon;

new class extends Component {
    use EmrUGDTrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $dataDaftarUGD = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-perencanaan-ugd'];

    // Untuk modal E-Resep
    public string $isOpenModeEresepRJ = 'insert';
    public string $activeTabRacikanNonRacikan = 'NonRacikan';
    public array $EmrMenuRacikanNonRacikan = [['ermMenuId' => 'NonRacikan', 'ermMenuName' => 'NonRacikan'], ['ermMenuId' => 'Racikan', 'ermMenuName' => 'Racikan']];

    /* ===============================
     | OPEN
     =============================== */
    #[On('open-rm-perencanaan-ugd')]
    public function openPerencanaan($rjNo): void
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

        if (!isset($this->dataDaftarUGD['perencanaan'])) {
            $this->dataDaftarUGD['perencanaan'] = $this->getDefaultPerencanaan();
        }

        $this->incrementVersion('modal-perencanaan-ugd');

        if ($this->checkEmrUGDStatus($rjNo)) {
            $this->isFormLocked = true;
        }
    }

    public function openModalEresepUGD(): void
    {
        if (!$this->rjNo) {
            $this->dispatch('toast', type: 'error', message: 'Nomor kunjungan tidak ditemukan.');
            return;
        }

        $this->dispatch('emr-ugd.eresep.open', rjNo: $this->rjNo);
        $this->dispatch('open-eresep-non-racikan-ugd', rjNo: $this->rjNo);
        $this->dispatch('open-eresep-racikan-ugd', rjNo: $this->rjNo);
    }

    /* ===============================
     | DEFAULT STRUCTURE
     =============================== */
    private function getDefaultPerencanaan(): array
    {
        return [
            'pengkajianMedisTab' => 'Petugas Medis',
            'pengkajianMedis' => [
                'waktuPemeriksaan' => '',
                'selesaiPemeriksaan' => '',
                'drPemeriksa' => '',
            ],

            'tindakLanjutTab' => 'Tindak Lanjut',
            'tindakLanjut' => [
                'tindakLanjut' => '',
                'keteranganTindakLanjut' => '',
                'tindakLanjutOptions' => [['tindakLanjut' => 'MRS'], ['tindakLanjut' => 'Kontrol'], ['tindakLanjut' => 'Rujuk'], ['tindakLanjut' => 'Perawatan Selesai'], ['tindakLanjut' => 'PRB'], ['tindakLanjut' => 'Lain-lain']],
            ],

            'terapiTab' => 'Terapi',
            'terapi' => [
                'terapi' => '',
            ],
        ];
    }

    /* ===============================
     | CLOSE MODAL
     =============================== */
    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'rm-perencanaan-ugd-actions');
    }

    /* ===============================
     | VALIDATION
     =============================== */
    protected function rules(): array
    {
        return [
            'dataDaftarUGD.perencanaan.pengkajianMedis.waktuPemeriksaan' => 'nullable|date_format:d/m/Y H:i:s',
            'dataDaftarUGD.perencanaan.pengkajianMedis.selesaiPemeriksaan' => 'nullable|date_format:d/m/Y H:i:s',
            'dataDaftarUGD.perencanaan.rawatInap.tanggal' => 'nullable|date_format:d/m/Y',
        ];
    }

    protected function messages(): array
    {
        return [
            'dataDaftarUGD.perencanaan.pengkajianMedis.waktuPemeriksaan.date_format' => ':attribute harus dalam format dd/mm/yyyy hh:mi:ss',
            'dataDaftarUGD.perencanaan.pengkajianMedis.selesaiPemeriksaan.date_format' => ':attribute harus dalam format dd/mm/yyyy hh:mi:ss',
            'dataDaftarUGD.perencanaan.rawatInap.tanggal.date_format' => ':attribute harus dalam format dd/mm/yyyy',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'dataDaftarUGD.perencanaan.pengkajianMedis.waktuPemeriksaan' => 'Waktu Pemeriksaan',
            'dataDaftarUGD.perencanaan.pengkajianMedis.selesaiPemeriksaan' => 'Selesai Pemeriksaan',
            'dataDaftarUGD.perencanaan.rawatInap.tanggal' => 'Tanggal Rawat Inap',
        ];
    }

    /* ===============================
     | SAVE
     =============================== */
    #[On('save-rm-perencanaan-ugd')]
    public function save(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat menyimpan data.');
            return;
        }

        $this->validate();

        try {
            DB::transaction(function () {
                $data = $this->findDataUGD($this->rjNo) ?? [];

                if (empty($data)) {
                    $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan, simpan dibatalkan.');
                    return;
                }

                $data['perencanaan'] = $this->dataDaftarUGD['perencanaan'] ?? [];
                $this->updateJsonUGD($this->rjNo, $data);
            });

            $this->afterSave('Perencanaan berhasil disimpan.');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | VALIDASI SEBELUM DOKTER TTD
     =============================== */
    private function validateBeforeDrPemeriksa(): void
    {
        $rules = [
            'dataDaftarUGD.pemeriksaan.tandaVital.frekuensiNadi' => 'required|numeric',
            'dataDaftarUGD.pemeriksaan.tandaVital.frekuensiNafas' => 'required|numeric',
            'dataDaftarUGD.pemeriksaan.tandaVital.suhu' => 'required|numeric',
            'dataDaftarUGD.pemeriksaan.nutrisi.bb' => 'required|numeric',
            'dataDaftarUGD.pemeriksaan.nutrisi.tb' => 'required|numeric',
            'dataDaftarUGD.pemeriksaan.nutrisi.imt' => 'required|numeric',
            'dataDaftarUGD.anamnesa.pengkajianPerawatan.jamDatang' => 'required|date_format:d/m/Y H:i:s',
        ];

        try {
            $this->validate($rules, []);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak dapat melakukan TTD-E karena data pemeriksaan belum lengkap.');
            throw $e;
        }
    }

    /* ===============================
     | SET DOKTER PEMERIKSA (TTD)
     =============================== */
    public function setDrPemeriksa(): void
    {
        if ($this->isFormLocked) {
            return;
        }

        $myUserCodeActive = auth()->user()->myuser_code;
        $myUserNameActive = auth()->user()->myuser_name;

        try {
            $this->validateBeforeDrPemeriksa();

            if (auth()->user()->hasRole('Dokter')) {
                if (($this->dataDaftarUGD['drId'] ?? '') == $myUserCodeActive) {
                    $drDesc = $this->dataDaftarUGD['drDesc'] ?? 'Dokter Pemeriksa';

                    $this->dataDaftarUGD['perencanaan']['pengkajianMedis']['drPemeriksa'] = $drDesc;

                    if (empty($this->dataDaftarUGD['perencanaan']['pengkajianMedis']['waktuPemeriksaan'])) {
                        $this->dataDaftarUGD['perencanaan']['pengkajianMedis']['waktuPemeriksaan'] = Carbon::now()->format('d/m/Y H:i:s');
                    }

                    if (empty($this->dataDaftarUGD['perencanaan']['pengkajianMedis']['selesaiPemeriksaan'])) {
                        $this->dataDaftarUGD['perencanaan']['pengkajianMedis']['selesaiPemeriksaan'] = Carbon::now()->format('d/m/Y H:i:s');
                    }

                    // Update status EMR UGD
                    $this->dataDaftarUGD['ermStatus'] = 'L';

                    DB::table('rstxn_ugdhdrs')
                        ->where('rj_no', '=', $this->rjNo)
                        ->update(['erm_status' => $this->dataDaftarUGD['ermStatus']]);

                    $this->save();

                    $this->dispatch('toast', type: 'success', message: 'TTD-E berhasil.');
                } else {
                    $this->dispatch('toast', type: 'error', message: 'Anda tidak dapat melakukan TTD-E karena Bukan Pasien ' . $myUserNameActive);
                }
            } else {
                $this->dispatch('toast', type: 'error', message: 'Anda tidak dapat melakukan TTD-E karena User Role ' . $myUserNameActive . ' Bukan Dokter');
            }
        } catch (\Exception $e) {
            // Validation error already handled in validateBeforeDrPemeriksa
        }
    }

    /* ===============================
     | SET STATUS PRB
     =============================== */
    public function setStatusPRB(): void
    {
        if ($this->isFormLocked) {
            return;
        }

        $statusPRB = isset($this->dataDaftarUGD['statusPRB']['penanggungJawab']['statusPRB']) ? !$this->dataDaftarUGD['statusPRB']['penanggungJawab']['statusPRB'] : 1;

        $this->dataDaftarUGD['statusPRB']['penanggungJawab'] = [
            'statusPRB' => $statusPRB,
            'userLog' => auth()->user()->myuser_name,
            'userLogDate' => now()->format('d/m/Y H:i:s'),
            'userLogCode' => auth()->user()->myuser_code,
        ];

        if ($statusPRB) {
            $this->dataDaftarUGD['perencanaan']['tindakLanjut']['tindakLanjut'] = 'PRB';
        }

        $this->save();
    }

    /* ---- Helpers ---- */
    private function afterSave(string $message): void
    {
        $this->incrementVersion('modal-perencanaan-ugd');
        $this->dispatch('toast', type: 'success', message: $message);
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
    }

    public function mount(): void
    {
        $this->registerAreas(['modal-perencanaan-ugd']);
    }
};
?>

<div>
    {{-- CONTAINER --}}
    <div class="flex flex-col w-full" wire:key="{{ $this->renderKey('modal-perencanaan-ugd', [$rjNo ?? 'new']) }}">

        <div class="w-full mx-auto">
            <div
                class="w-full p-4 space-y-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                @if (isset($dataDaftarUGD['perencanaan']))
                    <div class="w-full">
                        <div x-data="{ activeTab: '{{ $dataDaftarUGD['perencanaan']['pengkajianMedisTab'] ?? 'Petugas Medis' }}' }" class="w-full">

                            {{-- TAB NAVIGATION --}}
                            <div class="w-full px-2 mb-2 border-b border-gray-200 dark:border-gray-700">
                                <ul
                                    class="flex flex-wrap w-full -mb-px text-xs font-medium text-center text-gray-500 dark:text-gray-400">

                                    <li class="mr-2">
                                        <label
                                            class="inline-block px-4 py-2 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                            :class="activeTab === '{{ $dataDaftarUGD['perencanaan']['pengkajianMedisTab'] ?? 'Petugas Medis' }}'
                                                ? 'text-primary border-primary bg-gray-100' : ''"
                                            @click="activeTab = '{{ $dataDaftarUGD['perencanaan']['pengkajianMedisTab'] ?? 'Petugas Medis' }}'">
                                            {{ $dataDaftarUGD['perencanaan']['pengkajianMedisTab'] ?? 'Petugas Medis' }}
                                        </label>
                                    </li>

                                    <li class="mr-2">
                                        <label
                                            class="inline-block px-4 py-2 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                            :class="activeTab === '{{ $dataDaftarUGD['perencanaan']['tindakLanjutTab'] ?? 'Tindak Lanjut' }}'
                                                ? 'text-primary border-primary bg-gray-100' : ''"
                                            @click="activeTab = '{{ $dataDaftarUGD['perencanaan']['tindakLanjutTab'] ?? 'Tindak Lanjut' }}'">
                                            {{ $dataDaftarUGD['perencanaan']['tindakLanjutTab'] ?? 'Tindak Lanjut' }}
                                        </label>
                                    </li>

                                </ul>
                            </div>

                            {{-- TAB CONTENTS --}}
                            <div class="w-full p-4">

                                {{-- PETUGAS MEDIS --}}
                                <div class="w-full"
                                    x-show.transition.in.opacity.duration.600="activeTab === '{{ $dataDaftarUGD['perencanaan']['pengkajianMedisTab'] ?? 'Petugas Medis' }}'">
                                    @include('pages.transaksi.ugd.emr-ugd.perencanaan.tabs.petugas-medis-tab')
                                </div>

                                {{-- TINDAK LANJUT --}}
                                <div class="w-full"
                                    x-show.transition.in.opacity.duration.600="activeTab === '{{ $dataDaftarUGD['perencanaan']['tindakLanjutTab'] ?? 'Tindak Lanjut' }}'">
                                    @include('pages.transaksi.ugd.emr-ugd.perencanaan.tabs.tindak-lanjut-tab')
                                </div>

                            </div>
                        </div>
                    </div>
                @endif

            </div>
        </div>
    </div>

    {{-- Eresep UGD --}}
    <livewire:pages::transaksi.ugd.eresep-ugd.eresep-ugd :rjNo="$rjNo" wire:key="eresep-ugd-{{ $rjNo }}" />
</div>
