<?php

use Livewire\Component;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Carbon\Carbon;

new class extends Component {
    use EmrRJTrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $dataDaftarPoliRJ = [];

    // renderVersions
    public array $renderVersions = [];
    protected array $renderAreas = ['modal-suket-rj'];

    /* ===============================
     | OPEN REKAM MEDIS - SUKET
     =============================== */
    #[On('open-rm-suket-rj')]
    public function openSuket($rjNo): void
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

        // Initialize suket data if not exists
        if (!isset($this->dataDaftarPoliRJ['suket'])) {
            $this->dataDaftarPoliRJ['suket'] = $this->getDefaultSuket();
        }

        // 🔥 INCREMENT: Refresh seluruh modal suket
        $this->incrementVersion('modal-suket-rj');

        // Cek status lock
        if ($this->checkEmrRJStatus($rjNo)) {
            $this->isFormLocked = true;
        }
    }

    /* ===============================
     | GET DEFAULT SUKET STRUCTURE
     =============================== */
    private function getDefaultSuket(): array
    {
        $rjDate = Carbon::parse($this->dataDaftarPoliRJ['rjDate']);
        $hariIni = $rjDate->format('d/m/Y');
        $besok = $rjDate->copy()->addDay()->format('d/m/Y');

        return [
            'suketSehatTab' => 'Suket Sehat',
            'suketSehat' => [
                'suketSehat' => '',
            ],

            'suketIstirahatTab' => 'Suket Istirahat',
            'suketIstirahat' => [
                'mulaiIstirahat' => $hariIni,
                'mulaiIstirahatOptions' => [['mulaiIstirahat' => $hariIni . ' (Hari Ini)'], ['mulaiIstirahat' => $besok . ' (Besok)']],
                'suketIstirahatHari' => '2',
                'suketIstirahat' => '',
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
        $this->dispatch('close-modal', name: 'rm-suket-rj-actions');
    }

    /* ===============================
     | VALIDATION RULES
     =============================== */
    protected function rules(): array
    {
        return [
            'dataDaftarPoliRJ.suket.suketIstirahat.suketIstirahatHari' => 'nullable|integer|min:1',
        ];
    }

    protected function messages(): array
    {
        return [
            'dataDaftarPoliRJ.suket.suketIstirahat.suketIstirahatHari.integer' => ':attribute harus berupa angka.',
            'dataDaftarPoliRJ.suket.suketIstirahat.suketIstirahatHari.min' => ':attribute minimal 1 hari.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'dataDaftarPoliRJ.suket.suketIstirahat.suketIstirahatHari' => 'Jumlah Hari Istirahat',
        ];
    }

    /* ===============================
     | SAVE SUKET
     =============================== */
    #[On('save-rm-suket-rj')]
    public function save(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat menyimpan data.');
            return;
        }

        $this->validate();

        try {
            DB::transaction(function () {
                // ✅ Ambil existing data dari DB
                $data = $this->findDataRJ($this->rjNo) ?? [];

                // ✅ Guard: jika data kosong, batalkan — hindari overwrite JSON dengan array kosong
                if (empty($data)) {
                    $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan, simpan dibatalkan.');
                    return;
                }

                // ✅ Set hanya key 'suket', key lain tidak tersentuh
                $data['suket'] = $this->dataDaftarPoliRJ['suket'] ?? [];

                $this->updateJsonRJ($this->rjNo, $data);
            });

            $this->afterSave('Surat Keterangan berhasil disimpan.');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | AFTER SAVE
     =============================== */
    private function afterSave(string $message): void
    {
        // 🔥 INCREMENT: Refresh seluruh modal suket
        $this->incrementVersion('modal-suket-rj');

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
    public function mount(): void
    {
        $this->registerAreas(['modal-suket-rj']);
    }
};

?>

<div>
    {{-- CONTAINER UTAMA - SATU-SATUNYA WIRE:KEY --}}
    <div class="flex flex-col w-full" wire:key="{{ $this->renderKey('modal-suket-rj', [$rjNo ?? 'new']) }}">

        {{-- BODY --}}
        <div class="w-full mx-auto">
            <div
                class="w-full p-4 space-y-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                @if (isset($dataDaftarPoliRJ['suket']))
                    <div class="w-full">
                        <div id="SuketRawatJalan" x-data="{ activeTab: '{{ $dataDaftarPoliRJ['suket']['suketSehatTab'] ?? 'Suket Sehat' }}' }" class="w-full">

                            {{-- TAB NAVIGATION --}}
                            <div class="w-full px-2 mb-2 border-b border-gray-200 dark:border-gray-700">
                                <ul
                                    class="flex flex-wrap w-full -mb-px text-xs font-medium text-center text-gray-500 dark:text-gray-400">

                                    {{-- SUKET SEHAT TAB --}}
                                    <li class="mr-2">
                                        <label
                                            class="inline-block px-4 py-2 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                            :class="activeTab === '{{ $dataDaftarPoliRJ['suket']['suketSehatTab'] ?? 'Suket Sehat' }}'
                                                ?
                                                'text-primary border-primary bg-gray-100' : ''"
                                            @click="activeTab = '{{ $dataDaftarPoliRJ['suket']['suketSehatTab'] ?? 'Suket Sehat' }}'">
                                            {{ $dataDaftarPoliRJ['suket']['suketSehatTab'] ?? 'Suket Sehat' }}
                                        </label>
                                    </li>

                                    {{-- SUKET ISTIRAHAT TAB --}}
                                    <li class="mr-2">
                                        <label
                                            class="inline-block px-4 py-2 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                            :class="activeTab === '{{ $dataDaftarPoliRJ['suket']['suketIstirahatTab'] ?? 'Suket Istirahat' }}'
                                                ?
                                                'text-primary border-primary bg-gray-100' : ''"
                                            @click="activeTab = '{{ $dataDaftarPoliRJ['suket']['suketIstirahatTab'] ?? 'Suket Istirahat' }}'">
                                            {{ $dataDaftarPoliRJ['suket']['suketIstirahatTab'] ?? 'Suket Istirahat' }}
                                        </label>
                                    </li>

                                </ul>
                            </div>

                            {{-- TAB CONTENTS --}}
                            <div class="w-full p-4">

                                {{-- SUKET SEHAT TAB CONTENT --}}
                                @if (isset($dataDaftarPoliRJ['suket']['suketSehatTab']))
                                    <div class="w-full"
                                        x-show.transition.in.opacity.duration.600="activeTab === '{{ $dataDaftarPoliRJ['suket']['suketSehatTab'] ?? 'Suket Sehat' }}'">

                                        @include('pages.transaksi.rj.emr-rj.modul-dokumen.suket.tab.suket-sehat-tab')
                                    </div>
                                @endif

                                {{-- SUKET ISTIRAHAT TAB CONTENT --}}
                                @if (isset($dataDaftarPoliRJ['suket']['suketIstirahatTab']))
                                    <div class="w-full"
                                        x-show.transition.in.opacity.duration.600="activeTab === '{{ $dataDaftarPoliRJ['suket']['suketIstirahatTab'] ?? 'Suket Istirahat' }}'">

                                        @include('pages.transaksi.rj.emr-rj.modul-dokumen.suket.tab.suket-istirahat-tab')
                                    </div>
                                @endif

                            </div>
                        </div>
                    </div>
                @endif

            </div>
        </div>



    </div>
</div>
