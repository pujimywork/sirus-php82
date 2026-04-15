<?php
// resources/views/pages/transaksi/ugd/emr-ugd/modul-dokumen/form-penjaminan/rm-form-penjaminan-actions.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use EmrUGDTrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $dataDaftarUGD = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-form-penjaminan'];

    public array $newForm = [
        'tanggalFormPenjaminan' => '',
        'pembuatNama' => '',
        'pembuatUmur' => '',
        'pembuatJenisKelamin' => '',
        'pembuatAlamat' => '',
        'hubunganDenganPasien' => '',
        'jenisPenjamin' => '',
        'asuransiLain' => '',
        'bpjsKlausulDisetujui' => false,
        'kelasKamar' => '',
        'orientasiKamarDijelaskan' => false,
        'namaSaksiKeluarga' => '',
        'namaPetugas' => '',
        'kodePetugas' => '',
        'petugasDate' => '',
    ];

    public string $signature = '';
    public string $signatureSaksi = '';

    public array $jenisPenjaminOptions = [['id' => 'BPJS_KESEHATAN', 'desc' => 'BPJS Kesehatan'], ['id' => 'BPJS_KETENAGAKERJAAN', 'desc' => 'BPJS Ketenagakerjaan'], ['id' => 'ASABRI_TASPEN', 'desc' => 'ASABRI / TASPEN'], ['id' => 'JASA_RAHARJA', 'desc' => 'Jasa Raharja'], ['id' => 'ASURANSI_LAIN', 'desc' => 'Asuransi Lain'], ['id' => 'TANPA_KARTU', 'desc' => 'Tidak memiliki Kartu Penjaminan']];

    public array $kelasKamarOptions = [
        'VIP' => ['nama' => 'VIP', 'tarif' => 700000, 'tarifLabel' => 'Rp 700.000 / hari'],
        'KELAS_I' => ['nama' => 'Kelas I', 'tarif' => 275000, 'tarifLabel' => 'Rp 275.000 / hari'],
        'KELAS_II' => ['nama' => 'Kelas II', 'tarif' => 175000, 'tarifLabel' => 'Rp 175.000 / hari'],
        'KELAS_III' => ['nama' => 'Kelas III', 'tarif' => 175000, 'tarifLabel' => 'Rp 175.000 / hari'],
    ];

    public array $hubunganOptions = ['Pasien Sendiri', 'Suami/Istri', 'Orang Tua', 'Anak', 'Saudara', 'Lainnya'];
    public array $jenisKelaminOptions = [['value' => 'L', 'label' => 'Laki-laki'], ['value' => 'P', 'label' => 'Perempuan']];

    public array $listForm = [];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas(['modal-form-penjaminan']);
    }

    /* ===============================
     | OPEN
     =============================== */
    #[On('open-rm-form-penjaminan')]
    public function openFormPenjaminan(int $rjNo): void
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
        if (!isset($this->dataDaftarUGD['formPenjaminanOrientasiKamar']) || !is_array($this->dataDaftarUGD['formPenjaminanOrientasiKamar'])) {
            $this->dataDaftarUGD['formPenjaminanOrientasiKamar'] = [];
        }
        $this->listForm = $this->dataDaftarUGD['formPenjaminanOrientasiKamar'];
        $this->isFormLocked = $this->checkEmrUGDStatus($rjNo);
        $this->incrementVersion('modal-form-penjaminan');
    }

    /* ===============================
     | VALIDATION
     =============================== */
    protected function rules(): array
    {
        $rules = [
            'newForm.tanggalFormPenjaminan' => 'required|date_format:d/m/Y H:i:s',
            'newForm.pembuatNama' => 'required|string|max:200',
            'newForm.pembuatUmur' => 'required|numeric',
            'newForm.pembuatJenisKelamin' => 'required|in:L,P',
            'newForm.pembuatAlamat' => 'required|string',
            'newForm.hubunganDenganPasien' => 'required|string|max:200',
            'newForm.jenisPenjamin' => 'required|in:BPJS_KESEHATAN,BPJS_KETENAGAKERJAAN,ASABRI_TASPEN,JASA_RAHARJA,ASURANSI_LAIN,TANPA_KARTU',
            'newForm.asuransiLain' => 'required_if:newForm.jenisPenjamin,ASURANSI_LAIN',
            'newForm.kelasKamar' => 'required|in:VIP,KELAS_I,KELAS_II,KELAS_III',
            'newForm.orientasiKamarDijelaskan' => 'accepted',
            'newForm.namaSaksiKeluarga' => 'required|string|max:200',
            'signature' => 'required|string',
            'signatureSaksi' => 'required|string',
        ];

        if (($this->newForm['jenisPenjamin'] ?? '') === 'BPJS_KESEHATAN') {
            $rules['newForm.bpjsKlausulDisetujui'] = 'accepted';
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'required_if' => ':attribute wajib diisi.',
            'numeric' => ':attribute harus berupa angka.',
            'in' => ':attribute tidak valid.',
            'accepted' => ':attribute wajib disetujui.',
            'date_format' => ':attribute harus dengan format dd/mm/yyyy hh24:mi:ss',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'newForm.tanggalFormPenjaminan' => 'Tanggal Form Pernyataan Penjaminan',
            'newForm.pembuatNama' => 'Nama pembuat pernyataan',
            'newForm.pembuatUmur' => 'Umur pembuat pernyataan',
            'newForm.pembuatJenisKelamin' => 'Jenis kelamin pembuat pernyataan',
            'newForm.pembuatAlamat' => 'Alamat pembuat pernyataan',
            'newForm.hubunganDenganPasien' => 'Hubungan dengan pasien',
            'newForm.jenisPenjamin' => 'Jenis kartu penjaminan',
            'newForm.asuransiLain' => 'Nama asuransi lain',
            'newForm.kelasKamar' => 'Kelas kamar yang dipilih',
            'newForm.orientasiKamarDijelaskan' => 'Orientasi fasilitas kamar',
            'newForm.namaSaksiKeluarga' => 'Nama saksi keluarga',
            'newForm.bpjsKlausulDisetujui' => 'Persetujuan ketentuan penjaminan BPJS Kesehatan',
            'signature' => 'Tanda tangan pembuat pernyataan',
            'signatureSaksi' => 'Tanda tangan saksi keluarga',
        ];
    }

    /* ===============================
     | UPDATED HOOKS
     =============================== */
    public function updated($name, $value): void
    {
        if ($name === 'newForm.jenisPenjamin' && $value !== 'BPJS_KESEHATAN') {
            $this->newForm['bpjsKlausulDisetujui'] = false;
        }
    }

    /* ===============================
     | SET TANGGAL FORM
     =============================== */
    public function setTanggalForm(): void
    {
        $this->newForm['tanggalFormPenjaminan'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        $this->incrementVersion('modal-form-penjaminan');
    }

    /* ===============================
     | SET SIGNATURES
     =============================== */
    public function setSignature(string $dataUrl): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->signature = $dataUrl;
        $this->incrementVersion('modal-form-penjaminan');
    }

    public function clearSignature(): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->signature = '';
        $this->incrementVersion('modal-form-penjaminan');
    }

    public function setSignatureSaksi(string $dataUrl): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->signatureSaksi = $dataUrl;
        $this->incrementVersion('modal-form-penjaminan');
    }

    public function clearSignatureSaksi(): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->signatureSaksi = '';
        $this->incrementVersion('modal-form-penjaminan');
    }

    /* ===============================
     | SET PETUGAS
     =============================== */
    public function setPetugas(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }

        if (!empty($this->newForm['namaPetugas'])) {
            $this->dispatch('toast', type: 'warning', message: 'Data petugas sudah ada.');
            return;
        }

        $this->newForm['namaPetugas'] = auth()->user()->myuser_name ?? '';
        $this->newForm['kodePetugas'] = auth()->user()->myuser_code ?? '';
        $this->newForm['petugasDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        $this->dispatch('toast', type: 'success', message: 'Data petugas berhasil ditambahkan.');
    }

    /* ===============================
     | SAVE NEW FORM
     =============================== */
    #[On('save-rm-form-penjaminan')]
    public function save(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }

        if (empty($this->signature)) {
            $this->dispatch('toast', type: 'error', message: 'Tanda tangan pembuat pernyataan belum diisi.');
            return;
        }
        if (empty($this->signatureSaksi)) {
            $this->dispatch('toast', type: 'error', message: 'Tanda tangan saksi keluarga belum diisi.');
            return;
        }

        $this->validate();

        $now = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        $entry = array_merge($this->newForm, [
            'signaturePembuat' => $this->signature,
            'signaturePembuatDate' => $now,
            'signatureSaksiKeluarga' => $this->signatureSaksi,
            'signatureSaksiKeluargaDate' => $now,
        ]);

        try {
            DB::transaction(function () use ($entry) {
                $this->lockUGDRow($this->rjNo);

                $data = $this->findDataUGD($this->rjNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data UGD tidak ditemukan, simpan dibatalkan.');
                }

                if (!isset($data['formPenjaminanOrientasiKamar']) || !is_array($data['formPenjaminanOrientasiKamar'])) {
                    $data['formPenjaminanOrientasiKamar'] = [];
                }

                $data['formPenjaminanOrientasiKamar'][] = $entry;

                $this->updateJsonUGD($this->rjNo, $data);
                $this->dataDaftarUGD = $data;
                $this->listForm = $data['formPenjaminanOrientasiKamar'];
            });

            $this->incrementVersion('modal-form-penjaminan');
            $this->dispatch('toast', type: 'success', message: 'Form Pernyataan Kepemilikan Kartu Penjaminan Biaya tersimpan.');
            $this->dispatch('refresh-modul-dokumen-ugd-data', rjNo: $this->rjNo);

            $this->resetNewForm();
            $this->signature = '';
            $this->signatureSaksi = '';
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | CETAK
     =============================== */
    public function cetak(string $signaturePembuatDate): void
    {
        if (!$this->rjNo) {
            $this->dispatch('toast', type: 'error', message: 'Nomor UGD tidak ditemukan.');
            return;
        }

        $form = collect($this->listForm)->firstWhere('signaturePembuatDate', $signaturePembuatDate);
        if (!$form) {
            $this->dispatch('toast', type: 'error', message: 'Data form tidak ditemukan.');
            return;
        }

        $this->dispatch('cetak-form-penjaminan.open', rjNo: $this->rjNo, signaturePembuatDate: $signaturePembuatDate);
    }

    /* ===============================
     | HAPUS
     =============================== */
    public function hapus(string $signaturePembuatDate): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menghapus.');
            return;
        }

        try {
            DB::transaction(function () use ($signaturePembuatDate) {
                $this->lockUGDRow($this->rjNo);

                $data = $this->findDataUGD($this->rjNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data UGD tidak ditemukan.');
                }

                if (!isset($data['formPenjaminanOrientasiKamar'])) {
                    throw new \RuntimeException('Data form tidak ditemukan.');
                }

                $data['formPenjaminanOrientasiKamar'] = collect($data['formPenjaminanOrientasiKamar'])->reject(fn($item) => ($item['signaturePembuatDate'] ?? '') === $signaturePembuatDate)->values()->toArray();

                $this->updateJsonUGD($this->rjNo, $data);
                $this->dataDaftarUGD = $data;
                $this->listForm = $data['formPenjaminanOrientasiKamar'];
            });

            $this->incrementVersion('modal-form-penjaminan');
            $this->dispatch('toast', type: 'success', message: 'Form berhasil dihapus.');
            $this->dispatch('refresh-modul-dokumen-ugd-data', rjNo: $this->rjNo);
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    /* ===============================
     | RESET
     =============================== */
    private function resetNewForm(): void
    {
        $this->newForm = [
            'tanggalFormPenjaminan' => '',
            'pembuatNama' => '',
            'pembuatUmur' => '',
            'pembuatJenisKelamin' => '',
            'pembuatAlamat' => '',
            'hubunganDenganPasien' => '',
            'jenisPenjamin' => '',
            'asuransiLain' => '',
            'bpjsKlausulDisetujui' => false,
            'kelasKamar' => '',
            'orientasiKamarDijelaskan' => false,
            'namaSaksiKeluarga' => '',
            'namaPetugas' => '',
            'kodePetugas' => '',
            'petugasDate' => '',
        ];
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->dataDaftarUGD = [];
        $this->listForm = [];
        $this->resetNewForm();
        $this->signature = '';
        $this->signatureSaksi = '';
    }
};
?>

<div>
    <div class="flex flex-col w-full" wire:key="{{ $this->renderKey('modal-form-penjaminan', [$rjNo ?? 'new']) }}">

        @if ($isFormLocked)
            <div
                class="flex items-center gap-2 px-4 py-2.5 mb-4 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                EMR terkunci — data tidak dapat diubah.
            </div>
        @endif

        {{-- FORM BARU --}}
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">

            {{-- KOLOM KIRI --}}
            <div
                class="p-4 space-y-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                <h3
                    class="text-sm font-semibold text-gray-700 dark:text-gray-300 pb-2 border-b border-gray-100 dark:border-gray-800">
                    Data Pernyataan & Penjaminan
                </h3>

                {{-- Tanggal Form --}}
                <div>
                    <x-input-label value="Tanggal Form Pernyataan *" class="mb-1" />
                    <div class="flex gap-2">
                        <x-text-input wire:model.live="newForm.tanggalFormPenjaminan" placeholder="dd/mm/yyyy hh:ii:ss"
                            :disabled="$isFormLocked" class="flex-1" />
                        <x-primary-button type="button" wire:click="setTanggalForm" wire:loading.attr="disabled"
                            :disabled="$isFormLocked">
                            Sekarang
                        </x-primary-button>
                    </div>
                    <x-input-error :messages="$errors->get('newForm.tanggalFormPenjaminan')" class="mt-1" />
                </div>

                {{-- Nama Pembuat --}}
                <div>
                    <x-input-label value="Nama Pembuat Pernyataan *" class="mb-1" />
                    <x-text-input wire:model.live="newForm.pembuatNama" placeholder="Nama lengkap..." :disabled="$isFormLocked"
                        class="w-full" />
                    <x-input-error :messages="$errors->get('newForm.pembuatNama')" class="mt-1" />
                </div>

                {{-- Umur & Jenis Kelamin --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <x-input-label value="Umur *" class="mb-1" />
                        <x-text-input wire:model.live="newForm.pembuatUmur" type="number" placeholder="Tahun"
                            :disabled="$isFormLocked" />
                        <x-input-error :messages="$errors->get('newForm.pembuatUmur')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label value="Jenis Kelamin *" class="mb-1" />
                        <x-select-input wire:model.live="newForm.pembuatJenisKelamin" :disabled="$isFormLocked">
                            <option value="">Pilih</option>
                            @foreach ($jenisKelaminOptions as $opt)
                                <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                            @endforeach
                        </x-select-input>
                        <x-input-error :messages="$errors->get('newForm.pembuatJenisKelamin')" class="mt-1" />
                    </div>
                </div>

                {{-- Alamat --}}
                <div>
                    <x-input-label value="Alamat *" class="mb-1" />
                    <x-textarea wire:model.live="newForm.pembuatAlamat" rows="2" :disabled="$isFormLocked" />
                    <x-input-error :messages="$errors->get('newForm.pembuatAlamat')" class="mt-1" />
                </div>

                {{-- Hubungan --}}
                <div>
                    <x-input-label value="Hubungan dengan Pasien *" class="mb-1" />
                    <x-select-input wire:model.live="newForm.hubunganDenganPasien" :disabled="$isFormLocked">
                        <option value="">Pilih</option>
                        @foreach ($hubunganOptions as $opt)
                            <option value="{{ $opt }}">{{ $opt }}</option>
                        @endforeach
                    </x-select-input>
                    <x-input-error :messages="$errors->get('newForm.hubunganDenganPasien')" class="mt-1" />
                </div>

                {{-- Jenis Penjamin --}}
                <div>
                    <x-input-label value="Jenis Kartu Penjaminan *" class="mb-1" />
                    <x-select-input wire:model.live="newForm.jenisPenjamin" :disabled="$isFormLocked">
                        <option value="">Pilih</option>
                        @foreach ($jenisPenjaminOptions as $opt)
                            <option value="{{ $opt['id'] }}">{{ $opt['desc'] }}</option>
                        @endforeach
                    </x-select-input>
                    <x-input-error :messages="$errors->get('newForm.jenisPenjamin')" class="mt-1" />
                </div>

                {{-- Asuransi Lain (conditional) --}}
                @if (($newForm['jenisPenjamin'] ?? '') === 'ASURANSI_LAIN')
                    <div>
                        <x-input-label value="Nama Asuransi Lain *" class="mb-1" />
                        <x-text-input wire:model.live="newForm.asuransiLain"
                            placeholder="Contoh: Allianz, Prudential, dll" :disabled="$isFormLocked" class="w-full" />
                        <x-input-error :messages="$errors->get('newForm.asuransiLain')" class="mt-1" />
                    </div>
                @endif

                {{-- BPJS Klausul (conditional) --}}
                @if (($newForm['jenisPenjamin'] ?? '') === 'BPJS_KESEHATAN')
                    <div>
                        <x-toggle wire:model.live="newForm.bpjsKlausulDisetujui" trueValue="1" falseValue="0"
                            label="Saya menyetujui ketentuan penjaminan BPJS Kesehatan sesuai dengan peraturan yang berlaku."
                            :disabled="$isFormLocked" />
                        <x-input-error :messages="$errors->get('newForm.bpjsKlausulDisetujui')" class="mt-1" />
                    </div>
                @endif

                {{-- Kelas Kamar --}}
                <div>
                    <x-input-label value="Pilih Kelas Kamar *" class="mb-1" />
                    <x-select-input wire:model.live="newForm.kelasKamar" :disabled="$isFormLocked">
                        <option value="">Pilih</option>
                        @foreach ($kelasKamarOptions as $key => $opt)
                            <option value="{{ $key }}">{{ $opt['nama'] }} — {{ $opt['tarifLabel'] }}</option>
                        @endforeach
                    </x-select-input>
                    <x-input-error :messages="$errors->get('newForm.kelasKamar')" class="mt-1" />
                </div>

                {{-- Orientasi Kamar --}}
                <div>
                    <x-toggle wire:model.live="newForm.orientasiKamarDijelaskan" trueValue="1" falseValue="0"
                        label="Saya telah mendapatkan penjelasan mengenai fasilitas kamar yang dipilih beserta tarifnya."
                        :disabled="$isFormLocked" />
                    <x-input-error :messages="$errors->get('newForm.orientasiKamarDijelaskan')" class="mt-1" />
                </div>

                {{-- Nama Saksi Keluarga --}}
                <div>
                    <x-input-label value="Nama Saksi Keluarga *" class="mb-1" />
                    <x-text-input wire:model.live="newForm.namaSaksiKeluarga" placeholder="Nama lengkap saksi..."
                        :disabled="$isFormLocked" class="w-full" />
                    <x-input-error :messages="$errors->get('newForm.namaSaksiKeluarga')" class="mt-1" />
                </div>

                {{-- Petugas Rumah Sakit --}}
                <div class="pt-3 border-t border-gray-100 dark:border-gray-800">
                    <x-input-label value="Petugas Rumah Sakit" class="mb-2" />
                    @if (empty($newForm['namaPetugas']))
                        @if (!$isFormLocked)
                            <x-primary-button wire:click.prevent="setPetugas" wire:loading.attr="disabled"
                                wire:target="setPetugas" class="gap-2">
                                <span wire:loading.remove wire:target="setPetugas">
                                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 012.828 2.828L11.828 15.828a4 4 0 01-2.828 1.172H7v-2a4 4 0 011.172-2.828z" />
                                    </svg>
                                    TTD sebagai Petugas RS
                                </span>
                                <span wire:loading wire:target="setPetugas">
                                    <x-loading class="w-4 h-4" /> Menyimpan...
                                </span>
                            </x-primary-button>
                        @else
                            <p class="text-sm italic text-gray-400">Belum ditandatangani.</p>
                        @endif
                    @else
                        <div
                            class="p-3 text-center bg-gray-50 border border-gray-200 rounded-xl dark:bg-gray-800 dark:border-gray-700">
                            <div class="font-semibold text-gray-800 dark:text-gray-200">
                                {{ $newForm['namaPetugas'] }}</div>
                            @if (!empty($newForm['kodePetugas']))
                                <div class="text-xs text-gray-500 mt-0.5">Kode: {{ $newForm['kodePetugas'] }}</div>
                            @endif
                            <div class="mt-1 text-xs text-gray-500">{{ $newForm['petugasDate'] ?? '-' }}</div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- KOLOM KANAN: Tanda Tangan --}}
            <div class="space-y-4">

                {{-- TTD Pembuat --}}
                <div
                    class="p-4 space-y-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                    <h3
                        class="text-sm font-semibold text-gray-700 dark:text-gray-300 pb-2 border-b border-gray-100 dark:border-gray-800">
                        Tanda Tangan Pembuat Pernyataan
                    </h3>
                    <x-input-error :messages="$errors->get('signature')" class="mb-2" />

                    @if (!empty($signature))
                        <div class="text-center">
                            <p class="text-xs text-gray-500 mb-2">TTD akan disimpan saat simpan.</p>
                            <x-signature.signature-result :signature="$signature" :date="$signatureDate ?? ''" :disabled="$isFormLocked"
                                wireMethod="clearSignature" />
                        </div>
                    @elseif (!$isFormLocked)
                        <x-signature.signature-pad wireMethod="setSignature" />
                    @else
                        <p class="text-sm italic text-gray-400">Belum ditandatangani.</p>
                    @endif
                </div>

                {{-- TTD Saksi Keluarga --}}
                <div
                    class="p-4 space-y-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                    <h3
                        class="text-sm font-semibold text-gray-700 dark:text-gray-300 pb-2 border-b border-gray-100 dark:border-gray-800">
                        Tanda Tangan Saksi Keluarga
                    </h3>
                    <x-input-error :messages="$errors->get('signatureSaksi')" class="mb-2" />

                    @if (!empty($signatureSaksi))
                        <x-signature.signature-result :signature="$signatureSaksi" :date="$signatureSaksiDate ?? ''" :disabled="$isFormLocked"
                            wireMethod="clearSignatureSaksi" />
                    @elseif (!$isFormLocked)
                        <x-signature.signature-pad wireMethod="setSignatureSaksi" />
                    @else
                        <p class="text-sm italic text-gray-400">Belum ditandatangani.</p>
                    @endif
                </div>

            </div>
        </div>

        {{-- FOOTER ACTIONS --}}
        <div class="flex items-center justify-end gap-3 mt-4">
            @if (!$isFormLocked)
                <x-secondary-button wire:click="cetak('')" wire:loading.attr="disabled" wire:target="cetak"
                    class="gap-2">
                    <span wire:loading.remove wire:target="cetak">
                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 9V4h12v5m-2 4h2a2 2 0 002-2v-1a2 2 0 00-2-2H6a2 2 0 00-2 2v1a2 2 0 002 2h2m8 0v5H8v-5h8z" />
                        </svg>
                        Cetak
                    </span>
                    <span wire:loading wire:target="cetak"><x-loading class="w-4 h-4" /></span>
                </x-secondary-button>

                <x-primary-button wire:click.prevent="save" wire:loading.attr="disabled" wire:target="save"
                    class="gap-2 min-w-[120px] justify-center">
                    <span wire:loading.remove wire:target="save">Simpan Form Penjaminan</span>
                    <span wire:loading wire:target="save"><x-loading class="w-4 h-4" /> Menyimpan...</span>
                </x-primary-button>
            @else
                <x-secondary-button wire:click="cetak('')" class="gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 9V4h12v5m-2 4h2a2 2 0 002-2v-1a2 2 0 00-2-2H6a2 2 0 00-2 2v1a2 2 0 002 2h2m8 0v5H8v-5h8z" />
                    </svg>
                    Cetak
                </x-secondary-button>
            @endif
        </div>

        {{-- DAFTAR FORM TERSIMPAN --}}
        @if (count($listForm) > 0)
            <div class="mt-6 overflow-x-auto">
                <h3
                    class="text-sm font-semibold text-gray-700 dark:text-gray-300 pb-2 border-b border-gray-100 dark:border-gray-800 mb-3">
                    Daftar Form Pernyataan Tersimpan
                </h3>
                <table class="min-w-full text-sm border border-gray-200 rounded-lg dark:border-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr class="text-left text-gray-600 dark:text-gray-300">
                            <th class="px-4 py-2 border-b">Tanggal Form</th>
                            <th class="px-4 py-2 border-b">Pembuat</th>
                            <th class="px-4 py-2 border-b">Jenis Penjamin</th>
                            <th class="px-4 py-2 border-b">Kelas Kamar</th>
                            <th class="px-4 py-2 border-b text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($listForm as $form)
                            <tr
                                class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">
                                <td class="px-4 py-2">{{ $form['tanggalFormPenjaminan'] ?? '-' }}</td>
                                <td class="px-4 py-2 font-medium text-gray-800 dark:text-gray-200">
                                    {{ $form['pembuatNama'] ?? '-' }}
                                </td>
                                <td class="px-4 py-2 text-gray-600 dark:text-gray-400">
                                    @php
                                        $jenis = collect($jenisPenjaminOptions)->firstWhere(
                                            'id',
                                            $form['jenisPenjamin'] ?? '',
                                        );
                                        $jenisDesc = $jenis ? $jenis['desc'] : $form['jenisPenjamin'] ?? '-';
                                    @endphp
                                    {{ $jenisDesc }}
                                    @if (($form['jenisPenjamin'] ?? '') === 'ASURANSI_LAIN' && !empty($form['asuransiLain']))
                                        <span class="text-xs text-gray-500">({{ $form['asuransiLain'] }})</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-gray-600 dark:text-gray-400">
                                    {{ $kelasKamarOptions[$form['kelasKamar']]['nama'] ?? ($form['kelasKamar'] ?? '-') }}
                                </td>
                                <td class="px-4 py-2 text-center space-x-2">
                                    <x-secondary-button wire:click="cetak('{{ $form['signaturePembuatDate'] }}')"
                                        class="text-xs py-1 px-2">
                                        <svg class="w-3.5 h-3.5 mr-1 inline" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M6 9V4h12v5m-2 4h2a2 2 0 002-2v-1a2 2 0 00-2-2H6a2 2 0 00-2 2v1a2 2 0 002 2h2m8 0v5H8v-5h8z" />
                                        </svg>
                                        Cetak
                                    </x-secondary-button>
                                    @if (!$isFormLocked)
                                        <x-confirm-button variant="danger" :action="'hapus(\'' . $form['signaturePembuatDate'] . '\')'"
                                            title="Hapus Form Penjaminan"
                                            message="Yakin hapus form penjaminan ini? Data yang sudah ditandatangani akan dihapus."
                                            confirmText="Ya, hapus" cancelText="Batal"
                                            class="text-xs py-1 px-2">
                                            <svg class="w-3.5 h-3.5 mr-1 inline" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                            Hapus
                                        </x-confirm-button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

    </div>

    {{-- Cetak component --}}
    <livewire:pages::components.modul-dokumen.u-g-d.form-penjaminan.cetak-form-penjaminan
        wire:key="cetak-form-penjaminan-{{ $rjNo ?? 'init' }}" />
</div>
