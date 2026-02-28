<?php

use Livewire\Component;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

new class extends Component {
    use EmrRJTrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $dataDaftarPoliRJ = [];

    // renderVersions
    public array $renderVersions = [];
    protected array $renderAreas = ['modal-perencanaan-rj'];

    // Untuk modal E-Resep
    public string $isOpenModeEresepRJ = 'insert';
    public string $activeTabRacikanNonRacikan = 'NonRacikan';
    public array $EmrMenuRacikanNonRacikan = [['ermMenuId' => 'NonRacikan', 'ermMenuName' => 'NonRacikan'], ['ermMenuId' => 'Racikan', 'ermMenuName' => 'Racikan']];

    /* ===============================
     | OPEN REKAM MEDIS - PERENCANAAN
     =============================== */
    #[On('open-rm-perencanaan-rj')]
    public function openPerencanaan($rjNo): void
    {
        if (empty($rjNo)) {
            return;
        }

        $this->rjNo = $rjNo;

        $this->resetForm();
        $this->resetValidation();

        // Ambil data kunjungan RJ
        $dataDaftarPoliRJ = $this->findDataRJ($rjNo);

        if (!$dataDaftarPoliRJ) {
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Jalan tidak ditemukan.');
            return;
        }

        $this->dataDaftarPoliRJ = $dataDaftarPoliRJ;

        // Initialize perencanaan data if not exists
        if (!isset($this->dataDaftarPoliRJ['perencanaan'])) {
            $this->dataDaftarPoliRJ['perencanaan'] = $this->getDefaultPerencanaan();
        }

        // 🔥 INCREMENT: Refresh seluruh modal perencanaan
        $this->incrementVersion('modal-perencanaan-rj');

        // Cek status lock
        if ($this->checkEmrRJStatus($rjNo)) {
            $this->isFormLocked = true;
        }
    }

    public function openModalEresepRJ(): void
    {
        if (!$this->rjNo) {
            $this->dispatch('toast', type: 'error', message: 'Nomor kunjungan tidak ditemukan.');
            return;
        }

        $this->dispatch('emr-rj.eresep.open', rjNo: $this->rjNo);
    }
    /* ===============================
     | GET DEFAULT PERENCANAAN STRUCTURE
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

            // 'rawatInapTab' => 'Rawat Inap',
            // 'rawatInap' => [
            //     'noRef' => '',
            //     'tanggal' => '', //dd/mm/yyyy
            //     'keterangan' => '',
            // ],

            // 'dischargePlanningTab' => 'Discharge Planning', // TIDAK DIPAKAI
            // 'dischargePlanning' => [                         // TIDAK DIPAKAI
            //     'pelayananBerkelanjutan' => [
            //         'pelayananBerkelanjutan' => 'Tidak Ada',
            //         'pelayananBerkelanjutanOption' => [
            //             ['pelayananBerkelanjutan' => 'Tidak Ada'],
            //             ['pelayananBerkelanjutan' => 'Ada']
            //         ],
            //     ],
            //     'pelayananBerkelanjutanOpsi' => [
            //         'rawatLuka' => [],
            //         'dm' => [],
            //         'ppok' => [],
            //         'hivAids' => [],
            //         'dmTerapiInsulin' => [],
            //         'ckd' => [],
            //         'tb' => [],
            //         'stroke' => [],
            //         'kemoterapi' => [],
            //     ],

            //     'penggunaanAlatBantu' => [
            //         'penggunaanAlatBantu' => 'Tidak Ada',
            //         'penggunaanAlatBantuOption' => [
            //             ['penggunaanAlatBantu' => 'Tidak Ada'],
            //             ['penggunaanAlatBantu' => 'Ada']
            //         ],
            //     ],
            //     'penggunaanAlatBantuOpsi' => [
            //         'kateterUrin' => [],
            //         'ngt' => [],
            //         'traechotomy' => [],
            //         'colostomy' => [],
            //     ],
            // ],
        ];
    }

    /* ===============================
     | GENERATE TERAPI DARI RESEP
     =============================== */
    private function generateTerapiFromResep(): void
    {
        $eresep = '';
        if (isset($this->dataDaftarPoliRJ['eresep'])) {
            foreach ($this->dataDaftarPoliRJ['eresep'] as $value) {
                $catatanKhusus = $value['catatanKhusus'] ?? '' ? ' (' . $value['catatanKhusus'] . ')' : '';
                $eresep .= 'R/' . ' ' . ($value['productName'] ?? '') . ' | No. ' . ($value['qty'] ?? '') . ' | S ' . ($value['signaX'] ?? '') . 'dd' . ($value['signaHari'] ?? '') . $catatanKhusus . PHP_EOL;
            }
        }

        $eresepRacikan = '';
        if (isset($this->dataDaftarPoliRJ['eresepRacikan'])) {
            foreach ($this->dataDaftarPoliRJ['eresepRacikan'] as $value) {
                if (isset($value['jenisKeterangan'])) {
                    $catatan = $value['catatan'] ?? '';
                    $catatanKhusus = $value['catatanKhusus'] ?? '';
                    $noRacikan = $value['noRacikan'] ?? '';
                    $productName = $value['productName'] ?? '';
                    $jmlRacikan = $value['qty'] ?? '' ? 'Jml Racikan ' . $value['qty'] . ' | ' . $catatan . ' | S ' . $catatanKhusus . PHP_EOL : '';
                    $dosis = $value['dosis'] ?? '';
                    $eresepRacikan .= $noRacikan . '/ ' . $productName . ' - ' . $dosis . PHP_EOL . $jmlRacikan;
                }
            }
        }

        $this->dataDaftarPoliRJ['perencanaan']['terapi']['terapi'] = $eresep . $eresepRacikan;
    }

    /* ===============================
     | CLOSE MODAL
     =============================== */
    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'rm-perencanaan-actions');
    }

    /* ===============================
     | RULES VALIDATION
     =============================== */
    protected function rules(): array
    {
        return [
            'dataDaftarPoliRJ.perencanaan.pengkajianMedis.waktuPemeriksaan' => 'nullable|date_format:d/m/Y H:i:s',
            'dataDaftarPoliRJ.perencanaan.pengkajianMedis.selesaiPemeriksaan' => 'nullable|date_format:d/m/Y H:i:s',
            'dataDaftarPoliRJ.perencanaan.rawatInap.tanggal' => 'nullable|date_format:d/m/Y',
        ];
    }

    protected function messages(): array
    {
        return [
            'dataDaftarPoliRJ.perencanaan.pengkajianMedis.waktuPemeriksaan.date_format' => ':attribute harus dalam format dd/mm/yyyy hh:mi:ss',
            'dataDaftarPoliRJ.perencanaan.pengkajianMedis.selesaiPemeriksaan.date_format' => ':attribute harus dalam format dd/mm/yyyy hh:mi:ss',
            'dataDaftarPoliRJ.perencanaan.rawatInap.tanggal.date_format' => ':attribute harus dalam format dd/mm/yyyy',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'dataDaftarPoliRJ.perencanaan.pengkajianMedis.waktuPemeriksaan' => 'Waktu Pemeriksaan',
            'dataDaftarPoliRJ.perencanaan.pengkajianMedis.selesaiPemeriksaan' => 'Selesai Pemeriksaan',
            'dataDaftarPoliRJ.perencanaan.rawatInap.tanggal' => 'Tanggal Rawat Inap',
        ];
    }

    /* ===============================
     | SAVE PERENCANAAN
     =============================== */
    #[On('save-rm-perencanaan-rj')]
    public function save(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat menyimpan data.');
            return;
        }

        $this->validate();

        try {
            DB::transaction(function () {
                // Whitelist field perencanaan yang boleh diupdate
                $allowedPerencanaanFields = ['perencanaan'];

                // Untuk update, ambil data existing dari database
                $existingData = $this->findDataRJ($this->rjNo);

                // Ambil hanya field perencanaan yang diizinkan dari form
                $formPerencanaan = array_intersect_key($this->dataDaftarPoliRJ ?? [], array_flip($allowedPerencanaanFields));

                // Merge perencanaan data: existing diupdate dengan form data
                $mergedData = array_replace_recursive($existingData ?? [], $formPerencanaan);
                // Update RJ with merged data
                $this->updateJsonRJ($this->rjNo, $mergedData);
            });

            $this->afterSave('Perencanaan berhasil disimpan.');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | SET WAKTU PEMERIKSAAN
     =============================== */
    public function setWaktuPemeriksaan($time): void
    {
        if (!$this->isFormLocked) {
            $this->dataDaftarPoliRJ['perencanaan']['pengkajianMedis']['waktuPemeriksaan'] = $time;
            $this->incrementVersion('modal-perencanaan-rj');
        }
    }

    /* ===============================
     | SET SELESAI PEMERIKSAAN
     =============================== */
    public function setSelesaiPemeriksaan($time): void
    {
        if (!$this->isFormLocked) {
            $this->dataDaftarPoliRJ['perencanaan']['pengkajianMedis']['selesaiPemeriksaan'] = $time;
            $this->incrementVersion('modal-perencanaan-rj');
        }
    }

    /* ===============================
     | VALIDASI SEBELUM DOKTER TTD
     =============================== */
    private function validateBeforeDrPemeriksa(): void
    {
        $rules = [
            'dataDaftarPoliRJ.pemeriksaan.tandaVital.frekuensiNadi' => 'required|numeric',
            'dataDaftarPoliRJ.pemeriksaan.tandaVital.frekuensiNafas' => 'required|numeric',
            'dataDaftarPoliRJ.pemeriksaan.tandaVital.suhu' => 'required|numeric',
            'dataDaftarPoliRJ.pemeriksaan.nutrisi.bb' => 'required|numeric',
            'dataDaftarPoliRJ.pemeriksaan.nutrisi.tb' => 'required|numeric',
            'dataDaftarPoliRJ.pemeriksaan.nutrisi.imt' => 'required|numeric',
            'dataDaftarPoliRJ.anamnesa.pengkajianPerawatan.jamDatang' => 'required|date_format:d/m/Y H:i:s',
        ];

        $messages = [];

        try {
            $this->validate($rules, $messages);
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
            // Validasi data pemeriksaan sudah lengkap
            $this->validateBeforeDrPemeriksa();

            if (auth()->user()->hasRole('Dokter')) {
                if (($this->dataDaftarPoliRJ['drId'] ?? '') == $myUserCodeActive) {
                    $drDesc = $this->dataDaftarPoliRJ['drDesc'] ?? 'Dokter Pemeriksa';

                    $this->dataDaftarPoliRJ['perencanaan']['pengkajianMedis']['drPemeriksa'] = $drDesc;

                    // Update status ERM
                    $this->dataDaftarPoliRJ['ermStatus'] = 'L';

                    DB::table('rstxn_rjhdrs')
                        ->where('rj_no', '=', $this->rjNo)
                        ->update(['erm_status' => $this->dataDaftarPoliRJ['ermStatus']]);

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

        $statusPRB = isset($this->dataDaftarPoliRJ['statusPRB']['penanggungJawab']['statusPRB']) ? !$this->dataDaftarPoliRJ['statusPRB']['penanggungJawab']['statusPRB'] : 1;

        $this->dataDaftarPoliRJ['statusPRB']['penanggungJawab'] = [
            'statusPRB' => $statusPRB,
            'userLog' => auth()->user()->myuser_name,
            'userLogDate' => now()->format('d/m/Y H:i:s'),
            'userLogCode' => auth()->user()->myuser_code,
        ];

        if ($statusPRB) {
            $this->dataDaftarPoliRJ['perencanaan']['tindakLanjut']['tindakLanjut'] = 'PRB';
        }

        $this->save();
    }

    public function simpanTerapi(): void
    {
        $this->generateTerapiFromResep();
        $this->save();
        $this->closeModalEresepRJ();
    }

    /* ===============================
     | AFTER SAVE
     =============================== */
    private function afterSave(string $message): void
    {
        $this->incrementVersion('modal-perencanaan-rj');
        $this->dispatch('toast', type: 'success', message: $message);
    }

    /* ===============================
     | RESET FORM
     =============================== */
    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
    }

    /* ===============================
     | MOUNT
     =============================== */
    public function mount()
    {
        $this->registerAreas(['modal-perencanaan-rj']);
    }
};

?>

<div>
    {{-- CONTAINER UTAMA --}}
    <div class="flex flex-col w-full" wire:key="{{ $this->renderKey('modal-perencanaan-rj', [$rjNo ?? 'new']) }}">

        {{-- BODY --}}
        <div class="w-full mx-auto">
            <div
                class="w-full p-4 space-y-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                {{-- jika perencanaan ada --}}
                @if (isset($dataDaftarPoliRJ['perencanaan']))
                    <div class="w-full">
                        <div id="TransaksiRawatJalan" x-data="{ activeTab: '{{ $dataDaftarPoliRJ['perencanaan']['pengkajianMedisTab'] ?? 'Petugas Medis' }}' }" class="w-full">

                            {{-- TAB NAVIGATION --}}
                            <div class="w-full px-2 mb-2 border-b border-gray-200 dark:border-gray-700">
                                <ul
                                    class="flex flex-wrap w-full -mb-px text-xs font-medium text-center text-gray-500 dark:text-gray-400">

                                    {{-- PETUGAS MEDIS TAB --}}
                                    <li class="mr-2">
                                        <label
                                            class="inline-block px-4 py-2 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                            :class="activeTab === '{{ $dataDaftarPoliRJ['perencanaan']['pengkajianMedisTab'] ?? 'Petugas Medis' }}'
                                                ?
                                                'text-primary border-primary bg-gray-100' : ''"
                                            @click="activeTab ='{{ $dataDaftarPoliRJ['perencanaan']['pengkajianMedisTab'] ?? 'Petugas Medis' }}'">
                                            {{ $dataDaftarPoliRJ['perencanaan']['pengkajianMedisTab'] ?? 'Petugas Medis' }}
                                        </label>
                                    </li>

                                    {{-- TINDAK LANJUT TAB --}}
                                    <li class="mr-2">
                                        <label
                                            class="inline-block px-4 py-2 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                            :class="activeTab === '{{ $dataDaftarPoliRJ['perencanaan']['tindakLanjutTab'] ?? 'Tindak Lanjut' }}'
                                                ?
                                                'text-primary border-primary bg-gray-100' : ''"
                                            @click="activeTab ='{{ $dataDaftarPoliRJ['perencanaan']['tindakLanjutTab'] ?? 'Tindak Lanjut' }}'">
                                            {{ $dataDaftarPoliRJ['perencanaan']['tindakLanjutTab'] ?? 'Tindak Lanjut' }}
                                        </label>
                                    </li>

                                    {{-- TERAPI TAB --}}
                                    <li class="mr-2">
                                        <label
                                            class="inline-block px-4 py-2 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                            :class="activeTab === '{{ $dataDaftarPoliRJ['perencanaan']['terapiTab'] ?? 'Terapi' }}'
                                                ?
                                                'text-primary border-primary bg-gray-100' : ''"
                                            @click="activeTab ='{{ $dataDaftarPoliRJ['perencanaan']['terapiTab'] ?? 'Terapi' }}'">
                                            {{ $dataDaftarPoliRJ['perencanaan']['terapiTab'] ?? 'Terapi' }}
                                        </label>
                                    </li>

                                    {{-- RAWAT INAP TAB --}}
                                    {{-- <li class="mr-2">
                                        <label
                                            class="inline-block px-4 py-2 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                            :class="activeTab === '{{ $dataDaftarPoliRJ['perencanaan']['rawatInapTab'] ?? 'Rawat Inap' }}'
                                                ?
                                                'text-primary border-primary bg-gray-100' : ''"
                                            @click="activeTab ='{{ $dataDaftarPoliRJ['perencanaan']['rawatInapTab'] ?? 'Rawat Inap' }}'">
                                            {{ $dataDaftarPoliRJ['perencanaan']['rawatInapTab'] ?? 'Rawat Inap' }}
                                        </label>
                                    </li> --}}

                                    {{-- DISCHARGE PLANNING TAB --}}
                                    {{-- <li class="mr-2">
                                        <label
                                            class="inline-block px-4 py-2 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                            :class="activeTab === '{{ $dataDaftarPoliRJ['perencanaan']['dischargePlanningTab'] ?? 'Discharge Planning' }}'
                                                ?
                                                'text-primary border-primary bg-gray-100' : ''"
                                            @click="activeTab ='{{ $dataDaftarPoliRJ['perencanaan']['dischargePlanningTab'] ?? 'Discharge Planning' }}'">
                                            {{ $dataDaftarPoliRJ['perencanaan']['dischargePlanningTab'] ?? 'Discharge Planning' }}
                                        </label>
                                    </li> --}}
                                </ul>
                            </div>

                            {{-- TAB CONTENTS --}}
                            <div class="w-full p-4">
                                {{-- PETUGAS MEDIS TAB --}}
                                @if (isset($dataDaftarPoliRJ['perencanaan']['pengkajianMedisTab']))
                                    <div class="w-full"
                                        x-show.transition.in.opacity.duration.600="activeTab === '{{ $dataDaftarPoliRJ['perencanaan']['pengkajianMedisTab'] ?? 'Petugas Medis' }}'">
                                        @include('pages.transaksi.rj.emr-rj.perencanaan.tabs.petugas-medis-tab')
                                    </div>
                                @endif

                                {{-- TINDAK LANJUT TAB --}}
                                @if (isset($dataDaftarPoliRJ['perencanaan']['tindakLanjutTab']))
                                    <div class="w-full"
                                        x-show.transition.in.opacity.duration.600="activeTab === '{{ $dataDaftarPoliRJ['perencanaan']['tindakLanjutTab'] ?? 'Tindak Lanjut' }}'">
                                        @include('pages.transaksi.rj.emr-rj.perencanaan.tabs.tindak-lanjut-tab')
                                    </div>
                                @endif

                                {{-- TERAPI TAB --}}
                                @if (isset($dataDaftarPoliRJ['perencanaan']['terapiTab']))
                                    <div class="w-full"
                                        x-show.transition.in.opacity.duration.600="activeTab === '{{ $dataDaftarPoliRJ['perencanaan']['terapiTab'] ?? 'Terapi' }}'">
                                        @include('pages.transaksi.rj.emr-rj.perencanaan.tabs.terapi-tab')
                                    </div>
                                @endif

                                {{-- RAWAT INAP TAB --}}
                                {{-- @if (isset($dataDaftarPoliRJ['perencanaan']['rawatInapTab']))
                                    <div class="w-full"
                                        x-show.transition.in.opacity.duration.600="activeTab === '{{ $dataDaftarPoliRJ['perencanaan']['rawatInapTab'] ?? 'Rawat Inap' }}'">
                                        @include('pages.transaksi.rj.emr-rj.perencanaan.tabs.rawat-inap-tab')
                                    </div>
                                @endif --}}

                                {{-- DISCHARGE PLANNING TAB --}}
                                {{-- @if (isset($dataDaftarPoliRJ['perencanaan']['dischargePlanningTab']))
                                    <div class="w-full"
                                        x-show.transition.in.opacity.duration.600="activeTab === '{{ $dataDaftarPoliRJ['perencanaan']['dischargePlanningTab'] ?? 'Discharge Planning' }}'">
                                        @include('pages.transaksi.rj.emr-rj.perencanaan.tabs.discharge-planning-tab')
                                    </div>
                                @endif --}}
                            </div>


                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>


    {{-- Eresep RJ --}}
    <livewire:pages::transaksi.rj.eresep-rj.eresep-rj :rjNo="$rjNo" wire:key="eresep-rj-{{ $rjNo }}" />
</div>
