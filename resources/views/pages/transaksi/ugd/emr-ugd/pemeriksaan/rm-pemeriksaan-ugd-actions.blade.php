<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\WithValidationToast\WithValidationToastTrait;

new class extends Component {
    use EmrUGDTrait, WithRenderVersioningTrait, WithValidationToastTrait, WithFileUploads;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $dataDaftarUGD = [];

    // ── Upload Penunjang ──
    public $filePDF = null;
    public string $descPDF = '';
    public string $viewFilePDF = '';

    public $suspekAkibatKerja;

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-pemeriksaan-ugd'];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas(['modal-pemeriksaan-ugd']);
    }

    public function rendering(): void
    {
        $default = $this->getDefaultPemeriksaan();
        $current = $this->dataDaftarUGD['pemeriksaan'] ?? [];
        $this->dataDaftarUGD['pemeriksaan'] = array_replace_recursive($default, $current);
    }

    /* ===============================
     | OPEN
     =============================== */
    #[On('open-rm-pemeriksaan-ugd')]
    public function openPemeriksaan($rjNo): void
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

        $this->dataDaftarUGD['pemeriksaan'] ??= $this->getDefaultPemeriksaan();

        if (isset($this->dataDaftarUGD['pemeriksaan']['suspekAkibatKerja']['suspekAkibatKerja'])) {
            $this->suspekAkibatKerja = $this->dataDaftarUGD['pemeriksaan']['suspekAkibatKerja']['suspekAkibatKerja'];
        }

        $this->isFormLocked = $this->checkEmrUGDStatus($rjNo);
        $this->incrementVersion('modal-pemeriksaan-ugd');
        $this->dispatch('open-modal', name: 'rm-pemeriksaan-ugd-actions');
    }

    /* ===============================
     | CLOSE
     =============================== */
    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'rm-pemeriksaan-ugd-actions');
    }

    /* ===============================
     | VALIDATION
     =============================== */
    protected function rules(): array
    {
        $pre = 'dataDaftarUGD.pemeriksaan';
        return [
            "{$pre}.tandaVital.waktuPemeriksaan" => 'date_format:d/m/Y H:i:s',
            "{$pre}.tandaVital.sistolik" => 'nullable|numeric',
            "{$pre}.tandaVital.distolik" => 'nullable|numeric',
            "{$pre}.tandaVital.frekuensiNadi" => 'required|numeric',
            "{$pre}.tandaVital.frekuensiNafas" => 'required|numeric',
            "{$pre}.tandaVital.suhu" => 'required|numeric',
            "{$pre}.tandaVital.spo2" => 'nullable|numeric|min:0|max:100',
            "{$pre}.tandaVital.gda" => 'nullable|numeric|min:0',
            "{$pre}.nutrisi.bb" => 'required|numeric|min:0|max:300',
            "{$pre}.nutrisi.tb" => 'required|numeric|min:0|max:300',
            "{$pre}.nutrisi.imt" => 'required|numeric|min:0',
            "{$pre}.nutrisi.lk" => 'nullable|numeric|min:0|max:100',
            "{$pre}.nutrisi.lila" => 'nullable|numeric|min:0|max:100',
        ];
    }

    protected function messages(): array
    {
        $pre = 'dataDaftarUGD.pemeriksaan';
        return [
            "{$pre}.tandaVital.waktuPemeriksaan.date_format" => ':attribute harus dalam format dd/mm/yyyy hh:mi:ss',
            "{$pre}.tandaVital.frekuensiNadi.required" => ':attribute wajib diisi',
            "{$pre}.tandaVital.frekuensiNadi.numeric" => ':attribute harus berupa angka',
            "{$pre}.tandaVital.frekuensiNafas.required" => ':attribute wajib diisi',
            "{$pre}.tandaVital.frekuensiNafas.numeric" => ':attribute harus berupa angka',
            "{$pre}.tandaVital.suhu.required" => ':attribute wajib diisi',
            "{$pre}.tandaVital.suhu.numeric" => ':attribute harus berupa angka',
            "{$pre}.tandaVital.sistolik.numeric" => ':attribute harus berupa angka',
            "{$pre}.tandaVital.distolik.numeric" => ':attribute harus berupa angka',
            "{$pre}.tandaVital.spo2.numeric" => ':attribute harus berupa angka',
            "{$pre}.tandaVital.spo2.min" => ':attribute tidak boleh kurang dari 0',
            "{$pre}.tandaVital.spo2.max" => ':attribute tidak boleh lebih dari 100',
            "{$pre}.tandaVital.gda.numeric" => ':attribute harus berupa angka',
            "{$pre}.tandaVital.gda.min" => ':attribute tidak boleh kurang dari 0',
            "{$pre}.nutrisi.bb.required" => ':attribute wajib diisi',
            "{$pre}.nutrisi.bb.numeric" => ':attribute harus berupa angka',
            "{$pre}.nutrisi.bb.min" => ':attribute tidak boleh kurang dari 0 kg',
            "{$pre}.nutrisi.bb.max" => ':attribute tidak boleh lebih dari 300 kg',
            "{$pre}.nutrisi.tb.required" => ':attribute wajib diisi',
            "{$pre}.nutrisi.tb.numeric" => ':attribute harus berupa angka',
            "{$pre}.nutrisi.tb.min" => ':attribute tidak boleh kurang dari 0 cm',
            "{$pre}.nutrisi.tb.max" => ':attribute tidak boleh lebih dari 300 cm',
            "{$pre}.nutrisi.imt.required" => ':attribute wajib diisi',
            "{$pre}.nutrisi.imt.numeric" => ':attribute harus berupa angka',
            "{$pre}.nutrisi.imt.min" => ':attribute tidak boleh kurang dari 0',
            "{$pre}.nutrisi.lk.numeric" => ':attribute harus berupa angka',
            "{$pre}.nutrisi.lk.min" => ':attribute tidak boleh kurang dari 0 cm',
            "{$pre}.nutrisi.lk.max" => ':attribute tidak boleh lebih dari 100 cm',
            "{$pre}.nutrisi.lila.numeric" => ':attribute harus berupa angka',
            "{$pre}.nutrisi.lila.min" => ':attribute tidak boleh kurang dari 0 cm',
            "{$pre}.nutrisi.lila.max" => ':attribute tidak boleh lebih dari 100 cm',
        ];
    }

    protected function validationAttributes(): array
    {
        $pre = 'dataDaftarUGD.pemeriksaan';
        return [
            "{$pre}.tandaVital.waktuPemeriksaan" => 'Waktu Pemeriksaan',
            "{$pre}.tandaVital.sistolik" => 'Sistolik',
            "{$pre}.tandaVital.distolik" => 'Distolik',
            "{$pre}.tandaVital.frekuensiNadi" => 'Frekuensi Nadi',
            "{$pre}.tandaVital.frekuensiNafas" => 'Frekuensi Nafas',
            "{$pre}.tandaVital.suhu" => 'Suhu',
            "{$pre}.tandaVital.spo2" => 'SpO2',
            "{$pre}.tandaVital.gda" => 'GDA',
            "{$pre}.nutrisi.bb" => 'Berat Badan',
            "{$pre}.nutrisi.tb" => 'Tinggi Badan',
            "{$pre}.nutrisi.imt" => 'Indeks Massa Tubuh',
            "{$pre}.nutrisi.lk" => 'Lingkar Kepala',
            "{$pre}.nutrisi.lila" => 'Lingkar Lengan Atas',
        ];
    }

    /* ===============================
     | SAVE
     =============================== */
    #[On('save-rm-pemeriksaan-ugd')]
    public function save(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }

        $this->validateWithToast();

        try {
            DB::transaction(function () {
                // 1. Lock row dulu
                $this->lockUGDRow($this->rjNo);

                // 2. Baca data terkini setelah lock
                $data = $this->findDataUGD($this->rjNo) ?? [];

                if (empty($data)) {
                    throw new \RuntimeException('Data UGD tidak ditemukan, simpan dibatalkan.');
                }

                // 3. Patch hanya key pemeriksaan
                $data['pemeriksaan'] = $this->dataDaftarUGD['pemeriksaan'] ?? [];

                $this->updateJsonUGD($this->rjNo, $data);
                $this->dataDaftarUGD = $data;
            });

            // 4. Notify — di luar transaksi
            $this->afterSave('Pemeriksaan berhasil disimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | UPLOAD HASIL PENUNJANG
     =============================== */
    public function uploadHasilPenunjang(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form terkunci, tidak dapat upload.');
            return;
        }

        $this->validateWithToast(
            [
                'filePDF' => 'required|file|mimes:pdf|max:10240',
                'descPDF' => 'required|string|max:255',
            ],
            [
                'filePDF.required' => 'File PDF wajib dipilih.',
                'filePDF.mimes' => 'File harus berformat PDF.',
                'filePDF.max' => 'Ukuran file maksimal 10 MB.',
                'descPDF.required' => 'Keterangan wajib diisi.',
                'descPDF.max' => 'Keterangan maksimal 255 karakter.',
            ],
        );

        try {
            DB::transaction(function () {
                // 1. Lock row dulu
                $this->lockUGDRow($this->rjNo);

                // 2. Baca data terkini setelah lock
                $data = $this->findDataUGD($this->rjNo) ?? [];

                if (empty($data)) {
                    throw new \RuntimeException('Data UGD tidak ditemukan.');
                }

                // 3. Simpan file ke storage
                $path = $this->filePDF->store('uploadHasilPenunjang', 'local');

                // 4. Append ke array penunjang
                $data['pemeriksaan']['uploadHasilPenunjang'][] = [
                    'file' => $path,
                    'desc' => $this->descPDF,
                    'tglUpload' => now()->timezone(config('app.timezone'))->format('d/m/Y H:i:s'),
                    'penanggungJawab' => [
                        'userLog' => auth()->user()->myuser_name,
                        'userLogDate' => now()->timezone(config('app.timezone'))->format('d/m/Y H:i:s'),
                        'userLogCode' => auth()->user()->myuser_code,
                    ],
                ];

                $this->updateJsonUGD($this->rjNo, $data);
                $this->dataDaftarUGD = $data;
            });

            // 5. Reset + notify — di luar transaksi
            $this->reset(['filePDF', 'descPDF']);
            $this->resetValidation(['filePDF', 'descPDF']);
            $this->incrementVersion('modal-pemeriksaan-ugd');
            $this->dispatch('toast', type: 'success', message: 'File penunjang berhasil diupload.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal upload: ' . $e->getMessage());
        }
    }

    /* ===============================
     | DELETE HASIL PENUNJANG
     =============================== */
    public function deleteHasilPenunjang(string $file): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form terkunci, tidak dapat menghapus.');
            return;
        }

        try {
            DB::transaction(function () use ($file) {
                // 1. Lock row dulu
                $this->lockUGDRow($this->rjNo);

                // 2. Baca data terkini setelah lock
                $data = $this->findDataUGD($this->rjNo) ?? [];

                if (empty($data)) {
                    throw new \RuntimeException('Data UGD tidak ditemukan.');
                }

                // 3. Hapus file dari storage
                if (Storage::disk('local')->exists($file)) {
                    Storage::disk('local')->delete($file);
                }

                // 4. Hapus dari array
                $data['pemeriksaan']['uploadHasilPenunjang'] = collect($data['pemeriksaan']['uploadHasilPenunjang'] ?? [])
                    ->filter(fn($item) => ($item['file'] ?? '') !== $file)
                    ->values()
                    ->toArray();

                $this->updateJsonUGD($this->rjNo, $data);
                $this->dataDaftarUGD = $data;
            });

            // 5. Notify — di luar transaksi
            $this->incrementVersion('modal-pemeriksaan-ugd');
            $this->dispatch('toast', type: 'success', message: 'File berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus file: ' . $e->getMessage());
        }
    }

    /* ===============================
     | VIEW PENUNJANG PDF
     =============================== */
    public function openModalViewPenunjang(string $file): void
    {
        $fullPath = storage_path('/penunjang/upload/' . ltrim($file, '/'));
        if (!file_exists($fullPath)) {
            $this->dispatch('toast', type: 'error', message: 'File tidak ditemukan di server.');
            return;
        }
        $this->viewFilePDF = 'data:application/pdf;base64,' . base64_encode(file_get_contents($fullPath));
        $this->dispatch('open-modal', name: 'view-penunjang-pdf');
    }

    public function closeModalViewPenunjang(): void
    {
        $this->viewFilePDF = '';
        $this->dispatch('close-modal', name: 'view-penunjang-pdf');
    }

    /* ===============================
     | TTD PERAWAT PEMERIKSA
     =============================== */
    public function setPerawatPemeriksa(): void
    {
        if ($this->isFormLocked) {
            return;
        }

        if (
            !auth()
                ->user()
                ->hasAnyRole(['Perawat', 'Dokter', 'Admin'])
        ) {
            $this->dispatch('toast', type: 'error', message: 'Hanya Perawat / Dokter / Admin yang dapat melakukan TTD-E.');
            return;
        }

        $this->dataDaftarUGD['pemeriksaan']['tandaVital']['perawatPemeriksa'] = auth()->user()->myuser_name;
        $this->dataDaftarUGD['pemeriksaan']['tandaVital']['perawatPemeriksaCode'] = auth()->user()->myuser_code;
        $this->incrementVersion('modal-pemeriksaan-ugd');
    }

    public function setWaktuPemeriksaan(string $time): void
    {
        if (!$this->isFormLocked) {
            $this->dataDaftarUGD['pemeriksaan']['tandaVital']['waktuPemeriksaan'] = $time;
            $this->incrementVersion('modal-pemeriksaan-ugd');
        }
    }

    /* ===============================
     | LABORAT / RADIOLOGI LISTENERS
     =============================== */
    #[On('laborat-kirim-penunjang')]
    public function terimaPenunjangLaborat(string $text): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }

        try {
            DB::transaction(function () use ($text) {
                // Lock row dulu
                $this->lockUGDRow($this->rjNo);

                $data = $this->findDataUGD($this->rjNo) ?? [];
                if (empty($data)) {
                    throw new \RuntimeException('Data UGD tidak ditemukan.');
                }

                $existing = $data['pemeriksaan']['penunjang'] ?? '';
                $data['pemeriksaan']['penunjang'] = trim(($existing ? $existing . "\n" : '') . $text);

                $this->updateJsonUGD($this->rjNo, $data);
                $this->dataDaftarUGD = $data;
            });

            // Notify + increment — di luar transaksi
            $this->incrementVersion('modal-pemeriksaan-ugd');
            $this->dispatch('toast', type: 'success', message: 'Data laboratorium berhasil dikirim ke Penunjang.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    #[On('laborat-order-terkirim')]
    public function terimaLaboratOrder(): void
    {
        $data = $this->findDataUGD($this->rjNo);
        if ($data) {
            $this->dataDaftarUGD['pemeriksaan']['pemeriksaanPenunjang'] = $data['pemeriksaan']['pemeriksaanPenunjang'] ?? [];
        }
        $this->incrementVersion('modal-pemeriksaan-ugd');
    }

    #[On('radiologi-order-terkirim')]
    public function terimaRadiologiOrder(): void
    {
        $data = $this->findDataUGD($this->rjNo);
        if ($data) {
            $this->dataDaftarUGD['pemeriksaan']['pemeriksaanPenunjang'] = $data['pemeriksaan']['pemeriksaanPenunjang'] ?? [];
        }
        $this->incrementVersion('modal-pemeriksaan-ugd');
    }

    /* ===============================
     | UPDATED HOOKS
     =============================== */
    public function updated(string $propertyName, mixed $value): void
    {
        if (str_contains($propertyName, 'pemeriksaan.nutrisi.bb') || str_contains($propertyName, 'pemeriksaan.nutrisi.tb')) {
            $this->hitungIMT();
        }

        if ($propertyName === 'suspekAkibatKerja') {
            $this->suspekAkibatKerja = $value;
            $this->dataDaftarUGD['pemeriksaan']['suspekAkibatKerja']['suspekAkibatKerja'] = $value;
        }
    }

    /* ===============================
     | DEFAULT STRUCTURES
     =============================== */
    private function getDefaultPemeriksaan(): array
    {
        return [
            'umumTab' => 'Umum',
            'tandaVital' => [
                'keadaanUmum' => '',
                'tingkatKesadaran' => '',
                'tingkatKesadaranOptions' => [['tingkatKesadaran' => 'Sadar Baik / Alert'], ['tingkatKesadaran' => 'Berespon Dengan Kata-Kata / Voice'], ['tingkatKesadaran' => 'Hanya Beresponse Jika Dirangsang Nyeri / Pain'], ['tingkatKesadaran' => 'Pasien Tidak Sadar / Unresponsive'], ['tingkatKesadaran' => 'Gelisah Atau Bingung'], ['tingkatKesadaran' => 'Acute Confusional States']],
                'sistolik' => '',
                'distolik' => '',
                'frekuensiNafas' => '',
                'frekuensiNadi' => '',
                'suhu' => '',
                'spo2' => '',
                'gda' => '',
                'waktuPemeriksaan' => '',
            ],
            'nutrisi' => [
                'bb' => '',
                'tb' => '',
                'imt' => '',
                'lk' => '',
                'lila' => '',
            ],
            'fungsional' => [
                'alatBantu' => '',
                'prothesa' => '',
                'cacatTubuh' => '',
            ],
            'fisik' => '',
            'anatomi' => $this->defaultAnatomi(),
            'suspekAkibatKerja' => [
                'suspekAkibatKerja' => '',
                'keteranganSuspekAkibatKerja' => '',
                'suspekAkibatKerjaOptions' => [['suspekAkibatKerja' => 'Ya'], ['suspekAkibatKerja' => 'Tidak']],
            ],
            'FisikujiFungsi' => ['FisikujiFungsi' => ''],
            'penunjang' => '',
            'uploadHasilPenunjang' => [],
        ];
    }

    private function defaultAnatomi(): array
    {
        $parts = ['kepala', 'mata', 'telinga', 'hidung', 'rambut', 'bibir', 'gigiGeligi', 'lidah', 'langitLangit', 'leher', 'tenggorokan', 'tonsil', 'dada', 'payudarah', 'punggung', 'perut', 'genital', 'anus', 'lenganAtas', 'lenganBawah', 'jariTangan', 'kukuTangan', 'persendianTangan', 'tungkaiAtas', 'tungkaiBawah', 'jariKaki', 'kukuKaki', 'persendianKaki', 'faring'];

        $opts = [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']];

        return collect($parts)
            ->mapWithKeys(
                fn($p) => [
                    $p => ['kelainan' => 'Tidak Diperiksa', 'kelainanOptions' => $opts, 'desc' => ''],
                ],
            )
            ->all();
    }

    /* ===============================
     | HELPERS
     =============================== */
    private function hitungIMT(): void
    {
        $bb = $this->dataDaftarUGD['pemeriksaan']['nutrisi']['bb'] ?? 0;
        $tb = $this->dataDaftarUGD['pemeriksaan']['nutrisi']['tb'] ?? 0;

        if ($bb > 0 && $tb > 0) {
            $tbM = $tb / 100;
            $this->dataDaftarUGD['pemeriksaan']['nutrisi']['imt'] = round($bb / ($tbM * $tbM), 2);
        }
    }

    private function afterSave(string $message): void
    {
        $this->incrementVersion('modal-pemeriksaan-ugd');
        $this->dispatch('toast', type: 'success', message: $message);
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->filePDF = null;
        $this->descPDF = '';
        $this->viewFilePDF = '';
    }
};
?>

<div>
    <div class="flex flex-col w-full" wire:key="{{ $this->renderKey('modal-pemeriksaan-ugd', [$rjNo ?? 'new']) }}">
        <div class="w-full mx-auto">
            <div
                class="w-full p-4 space-y-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                @if (isset($dataDaftarUGD['pemeriksaan']))
                    <div class="w-full mb-1">
                        <div class="grid grid-cols-1">
                            <div class="px-2">
                                <div x-data="{ activeTab: 'Umum' }">

                                    {{-- TAB NAVIGATION --}}
                                    <div class="px-2 border-b border-gray-200 dark:border-gray-700">
                                        <ul
                                            class="flex flex-wrap -mb-px text-xs font-medium text-center text-gray-500 dark:text-gray-400">

                                            <li class="mr-2">
                                                <label
                                                    class="inline-block p-4 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                                    :class="activeTab === '{{ $dataDaftarUGD['pemeriksaan']['umumTab'] ?? 'Umum' }}'
                                                        ? 'text-primary border-primary bg-gray-100' : ''"
                                                    @click="activeTab = '{{ $dataDaftarUGD['pemeriksaan']['umumTab'] ?? 'Umum' }}'">
                                                    {{ $dataDaftarUGD['pemeriksaan']['umumTab'] ?? 'Umum' }}
                                                </label>
                                            </li>

                                            <li class="mr-2">
                                                <label
                                                    class="inline-block p-4 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                                    :class="activeTab === 'Anatomi' ?
                                                        'text-primary border-primary bg-gray-100' : ''"
                                                    @click="activeTab = 'Anatomi'">
                                                    Anatomi
                                                </label>
                                            </li>

                                            <li class="mr-2">
                                                <label
                                                    class="inline-block p-4 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                                    :class="activeTab === 'PenunjangHasil' ?
                                                        'text-primary border-primary bg-gray-100' : ''"
                                                    @click="activeTab = 'PenunjangHasil'">
                                                    Pelayanan Penunjang
                                                </label>
                                            </li>

                                            <li class="mr-2">
                                                <label
                                                    class="inline-block p-4 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                                    :class="activeTab === 'UploadPenunjangHasil' ?
                                                        'text-primary border-primary bg-gray-100' : ''"
                                                    @click="activeTab = 'UploadPenunjangHasil'">
                                                    Upload Penunjang
                                                </label>
                                            </li>

                                            <li class="mr-2">
                                                <label
                                                    class="inline-flex items-center gap-2 p-4 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                                    :class="activeTab === 'HasilPenunjang' ?
                                                        'text-primary border-primary bg-gray-100 dark:bg-gray-800' : ''"
                                                    @click="activeTab = 'HasilPenunjang'">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
                                                    </svg>
                                                    Hasil Penunjang
                                                </label>
                                            </li>

                                        </ul>
                                    </div>

                                    {{-- UMUM --}}
                                    <div class="p-2 rounded-lg bg-gray-50 dark:bg-gray-800"
                                        x-show.transition.in.opacity.duration.600="activeTab === '{{ $dataDaftarUGD['pemeriksaan']['umumTab'] ?? 'Umum' }}'">
                                        @include('pages.transaksi.ugd.emr-ugd.pemeriksaan.tabs.umum-tab')
                                    </div>

                                    {{-- ANATOMI --}}
                                    <div class="p-2 rounded-lg bg-gray-50 dark:bg-gray-800"
                                        x-show.transition.in.opacity.duration.600="activeTab === 'Anatomi'">
                                        @include('pages.transaksi.ugd.emr-ugd.pemeriksaan.tabs.anatomi-tab')
                                    </div>

                                    {{-- PELAYANAN PENUNJANG --}}
                                    <div class="p-2 rounded-lg bg-gray-50 dark:bg-gray-800"
                                        x-show.transition.in.opacity.duration.600="activeTab === 'PenunjangHasil'">
                                        @include('pages.transaksi.ugd.emr-ugd.pemeriksaan.tabs.pelayanan-penunjang-tab')
                                    </div>

                                    {{-- UPLOAD PENUNJANG --}}
                                    <div class="p-2 rounded-lg bg-gray-50 dark:bg-gray-800"
                                        x-show.transition.in.opacity.duration.600="activeTab === 'UploadPenunjangHasil'">
                                        @include('pages.transaksi.ugd.emr-ugd.pemeriksaan.tabs.upload-pelayanan-penunjang-tab')
                                    </div>

                                    {{-- HASIL PENUNJANG --}}
                                    <div class="p-2 rounded-lg bg-gray-50 dark:bg-gray-800"
                                        x-show.transition.in.opacity.duration.600="activeTab === 'HasilPenunjang'">
                                        @include('pages.transaksi.ugd.emr-ugd.pemeriksaan.tabs.hasil-penunjang-tab')
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                @endif

            </div>
        </div>
    </div>
</div>
