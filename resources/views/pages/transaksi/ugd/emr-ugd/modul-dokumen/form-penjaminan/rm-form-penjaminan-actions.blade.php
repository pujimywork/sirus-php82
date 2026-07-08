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
        'VIP' => [
            'nama' => 'VIP',
            'tarif' => 700000,
            'tarifLabel' => 'Rp 700.000 / hari',
            'fasilitas' => ['1 tempat tidur pasien', 'AC', 'Kamar mandi di dalam', 'Sofa bed penunggu', 'Kulkas', 'Televisi LED', 'Almari', 'Overbed table', 'Dispenser air minum', 'Makan siang 1 penunggu'],
        ],
        'KELAS_I' => [
            'nama' => 'Kelas I',
            'tarif' => 275000,
            'tarifLabel' => 'Rp 275.000 / hari',
            'fasilitas' => ['1 tempat tidur pasien', 'Kamar mandi di dalam', 'Sofa bed penunggu', 'Kulkas', 'Televisi LED', 'Almari', 'Kipas angin', 'Makan siang 1 penunggu'],
        ],
        'KELAS_II' => [
            'nama' => 'Kelas II',
            'tarif' => 175000,
            'tarifLabel' => 'Rp 175.000 / hari',
            'fasilitas' => ['2 tempat tidur pasien', 'Kamar mandi di dalam', 'Kursi penunggu', 'Televisi', 'Almari', 'Kipas angin', 'Makan siang 1 penunggu'],
        ],
        'KELAS_III' => [
            'nama' => 'Kelas III',
            'tarif' => 175000,
            'tarifLabel' => 'Rp 175.000 / hari',
            'fasilitas' => ['4 tempat tidur pasien', 'Kamar mandi di dalam', 'Televisi di luar ruangan', 'Kursi', 'Almari', 'Kipas angin'],
        ],
    ];

    public array $hubunganOptions = ['Pasien Sendiri', 'Suami', 'Istri', 'Orang Tua', 'Anak', 'Saudara', 'Lainnya'];
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

    /** Buka modal form penjaminan (dari kartu ringkasan di tab). */
    public function openModal(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $this->resetValidation();
        $this->dispatch('open-modal', name: "rm-form-penjaminan-{$this->rjNo}");
    }

    /** Tutup modal form penjaminan. */
    public function closeModal(): void
    {
        $this->resetValidation();
        $this->dispatch('close-modal', name: "rm-form-penjaminan-{$this->rjNo}");
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

                $this->appendAdminLogUGD((int) $this->rjNo, 'Tambah Form Penjaminan UGD: ' . ($entry['pembuatNama'] ?? '-') . ' (' . ($entry['signaturePembuatDate'] ?? '-') . ')', 'MR');
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

                $deletedForm = collect($data['formPenjaminanOrientasiKamar'])->firstWhere('signaturePembuatDate', $signaturePembuatDate);
                $deletedPembuat = $deletedForm['pembuatNama'] ?? '-';

                $data['formPenjaminanOrientasiKamar'] = collect($data['formPenjaminanOrientasiKamar'])->reject(fn($item) => ($item['signaturePembuatDate'] ?? '') === $signaturePembuatDate)->values()->toArray();

                $this->updateJsonUGD($this->rjNo, $data);
                $this->dataDaftarUGD = $data;
                $this->listForm = $data['formPenjaminanOrientasiKamar'];

                $this->appendAdminLogUGD((int) $this->rjNo, 'Hapus Form Penjaminan UGD: ' . $deletedPembuat . ' (' . $signaturePembuatDate . ')', 'MR');
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
    {{-- RINGKASAN + TOMBOL (pola General Consent) --}}
    @php $penjaminanCount = count($listForm ?? []); @endphp
    <div class="p-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex-1 space-y-2">
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">Form Penjaminan &amp; Orientasi Kamar</h3>
                    @if ($penjaminanCount > 0)
                        <x-badge variant="success">{{ $penjaminanCount }} tersimpan</x-badge>
                    @endif
                </div>
                <p class="text-base text-muted dark:text-gray-400">
                    Pernyataan kepemilikan kartu penjaminan biaya &amp; orientasi kamar pasien UGD.
                </p>
            </div>
            <div class="flex shrink-0">
                <x-primary-button type="button" wire:click="openModal" wire:loading.attr="disabled"
                    wire:target="openModal" :disabled="!$rjNo" class="gap-2">
                    <span wire:loading.remove wire:target="openModal" class="flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                        Buka Form Penjaminan
                    </span>
                    <span wire:loading wire:target="openModal" class="flex items-center gap-1.5">
                        <x-loading class="w-4 h-4" /> Memuat...
                    </span>
                </x-primary-button>
            </div>
        </div>
    </div>

    {{-- MODAL FORM --}}
    <x-modal name="rm-form-penjaminan-{{ $rjNo ?? 'init' }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]" wire:key="{{ $this->renderKey('modal-form-penjaminan', [$rjNo ?? 'new']) }}">
            {{-- HEADER MODAL --}}
            <div class="flex items-center justify-between gap-4 px-6 py-4 border-b border-hairline bg-surface-soft dark:border-gray-700">
                <h2 class="text-xl font-semibold text-ink dark:text-gray-100">Form Penjaminan &amp; Orientasi Kamar</h2>
                <x-icon-button color="gray" type="button" wire:click="closeModal">
                    <span class="sr-only">Close</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                            clip-rule="evenodd" />
                    </svg>
                </x-icon-button>
            </div>

            {{-- KONTEN (flex-1 → dorong footer sticky ke bawah, pola emr-ugd) --}}
            <div class="flex-1">

            {{-- Display Pasien (selaras General Consent) --}}
            <div class="px-4 pt-4">
                <livewire:pages::transaksi.ugd.display-pasien-ugd.display-pasien-ugd :rjNo="$rjNo"
                    wire:key="penj-ugd-display-pasien-{{ $rjNo ?? 'init' }}" />
            </div>

        @if ($isFormLocked)
            <div
                class="flex items-center gap-2 px-4 py-2.5 mb-4 text-base font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                EMR terkunci — data tidak dapat diubah.
            </div>
        @endif

        <div
            class="p-6 space-y-6 bg-canvas border border-hairline shadow-sm sm:p-8 rounded-2xl dark:bg-gray-900 dark:border-gray-700">

            {{-- ══ DATA PERNYATAAN & PENJAMINAN ══ --}}
            <section class="space-y-4">
                <h3 class="text-base font-semibold text-ink dark:text-gray-200">
                    Data Pernyataan &amp; Penjaminan
                </h3>

                <div>
                    <x-input-label value="Tanggal Form Pernyataan *" class="mb-1" />
                    <div class="flex gap-2">
                        <x-text-input wire:model.live="newForm.tanggalFormPenjaminan" :error="$errors->has('newForm.tanggalFormPenjaminan')" placeholder="dd/mm/yyyy hh:ii:ss"
                            :disabled="$isFormLocked" class="flex-1" />
                        <x-now-button wire:click="setTanggalForm" wire:loading.attr="disabled" :disabled="$isFormLocked" />
                    </div>
                    <x-input-error :messages="$errors->get('newForm.tanggalFormPenjaminan')" class="mt-1" />
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <x-input-label value="Nama Pembuat Pernyataan *" class="mb-1" />
                        <x-text-input wire:model.live="newForm.pembuatNama" :error="$errors->has('newForm.pembuatNama')" placeholder="Nama lengkap..."
                            :disabled="$isFormLocked" class="w-full" />
                        <x-input-error :messages="$errors->get('newForm.pembuatNama')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label value="Hubungan dengan Pasien *" class="mb-1" />
                        <x-select-input wire:model.live="newForm.hubunganDenganPasien" :error="$errors->has('newForm.hubunganDenganPasien')" :disabled="$isFormLocked">
                            <option value="">Pilih</option>
                            @foreach ($hubunganOptions as $opt)
                                <option value="{{ $opt }}">{{ $opt }}</option>
                            @endforeach
                        </x-select-input>
                        <x-input-error :messages="$errors->get('newForm.hubunganDenganPasien')" class="mt-1" />
                    </div>

                </div>

            </section>

            {{-- ══ PENJAMINAN & KAMAR ══ --}}
            <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                <h3 class="text-base font-semibold text-ink dark:text-gray-200">
                    Penjaminan &amp; Kelas Kamar
                </h3>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <x-input-label value="Jenis Kartu Penjaminan *" class="mb-1" />
                        <x-select-input wire:model.live="newForm.jenisPenjamin" :error="$errors->has('newForm.jenisPenjamin')" :disabled="$isFormLocked">
                            <option value="">Pilih</option>
                            @foreach ($jenisPenjaminOptions as $opt)
                                <option value="{{ $opt['id'] }}">{{ $opt['desc'] }}</option>
                            @endforeach
                        </x-select-input>
                        <x-input-error :messages="$errors->get('newForm.jenisPenjamin')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label value="Pilih Kelas Kamar *" class="mb-1" />
                        <x-select-input wire:model.live="newForm.kelasKamar" :error="$errors->has('newForm.kelasKamar')" :disabled="$isFormLocked">
                            <option value="">Pilih</option>
                            @foreach ($kelasKamarOptions as $key => $opt)
                                <option value="{{ $key }}">{{ $opt['nama'] }} — {{ $opt['tarifLabel'] }}
                                </option>
                            @endforeach
                        </x-select-input>
                        <x-input-error :messages="$errors->get('newForm.kelasKamar')" class="mt-1" />
                    </div>
                </div>

                @if (!empty($newForm['kelasKamar']) && isset($kelasKamarOptions[$newForm['kelasKamar']]['fasilitas']))
                    <div
                        class="px-4 py-3 text-base border rounded-xl bg-blue-50 border-blue-200 text-blue-900 dark:bg-blue-900/20 dark:border-blue-700 dark:text-blue-200">
                        <div class="flex items-center gap-2 mb-2 font-semibold">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Fasilitas {{ $kelasKamarOptions[$newForm['kelasKamar']]['nama'] }}
                        </div>
                        <ul class="grid grid-cols-1 gap-1 list-disc list-inside text-sm sm:grid-cols-2">
                            @foreach ($kelasKamarOptions[$newForm['kelasKamar']]['fasilitas'] as $fas)
                                <li>{{ $fas }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (($newForm['jenisPenjamin'] ?? '') === 'ASURANSI_LAIN')
                    <div>
                        <x-input-label value="Nama Asuransi Lain *" class="mb-1" />
                        <x-text-input wire:model.live="newForm.asuransiLain" :error="$errors->has('newForm.asuransiLain')"
                            placeholder="Contoh: Allianz, Prudential, dll" :disabled="$isFormLocked" class="w-full" />
                        <x-input-error :messages="$errors->get('newForm.asuransiLain')" class="mt-1" />
                    </div>
                @endif

                @if (($newForm['jenisPenjamin'] ?? '') === 'BPJS_KESEHATAN')
                    <div>
                        <x-toggle wire:model.live="newForm.bpjsKlausulDisetujui" trueValue="1" falseValue="0"
                            label="Saya menyetujui ketentuan penjaminan BPJS Kesehatan sesuai dengan peraturan yang berlaku."
                            :disabled="$isFormLocked" />
                        <x-input-error :messages="$errors->get('newForm.bpjsKlausulDisetujui')" class="mt-1" />
                    </div>
                @endif

                <div>
                    <x-toggle wire:model.live="newForm.orientasiKamarDijelaskan" trueValue="1" falseValue="0"
                        label="Saya telah mendapatkan penjelasan mengenai fasilitas kamar yang dipilih beserta tarifnya."
                        :disabled="$isFormLocked" />
                    <x-input-error :messages="$errors->get('newForm.orientasiKamarDijelaskan')" class="mt-1" />
                </div>
            </section>

            {{-- ══ TANDA TANGAN ══ --}}
            <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                <h3 class="text-base font-semibold text-ink dark:text-gray-200">
                    Tanda Tangan
                </h3>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    {{-- Pembuat Pernyataan --}}
                    <div class="flex flex-col">
                        <div
                            class="mb-2 text-sm font-semibold tracking-wide text-center text-muted uppercase dark:text-gray-400">
                            Pembuat Pernyataan
                        </div>
                        <x-input-error :messages="$errors->get('signature')" class="mb-2" />
                        @if (!empty($signature))
                            <x-signature.signature-result :signature="$signature" :date="$signatureDate ?? ''" :disabled="$isFormLocked"
                                wireMethod="clearSignature" />
                        @elseif (!$isFormLocked)
                            <x-signature.signature-pad wireMethod="setSignature" />
                        @else
                            <p class="py-8 text-base italic text-center text-muted-soft">Belum ditandatangani.</p>
                        @endif
                    </div>

                    {{-- Saksi Keluarga --}}
                    <div class="flex flex-col">
                        <div
                            class="mb-2 text-sm font-semibold tracking-wide text-center text-muted uppercase dark:text-gray-400">
                            Saksi Keluarga
                        </div>
                        <x-input-error :messages="$errors->get('signatureSaksi')" class="mb-2" />
                        @if (!empty($signatureSaksi))
                            <x-signature.signature-result :signature="$signatureSaksi" :date="$signatureSaksiDate ?? ''" :disabled="$isFormLocked"
                                wireMethod="clearSignatureSaksi" />
                        @elseif (!$isFormLocked)
                            <x-signature.signature-pad wireMethod="setSignatureSaksi" />
                        @else
                            <p class="py-8 text-base italic text-center text-muted-soft">Belum ditandatangani.</p>
                        @endif

                        <div class="mt-3">
                            <x-input-label value="Nama Saksi Keluarga *" class="mb-1" />
                            <x-text-input wire:model.live="newForm.namaSaksiKeluarga" :error="$errors->has('newForm.namaSaksiKeluarga')"
                                placeholder="Nama lengkap saksi..." :disabled="$isFormLocked" class="w-full" />
                            <x-input-error :messages="$errors->get('newForm.namaSaksiKeluarga')" class="mt-1" />
                        </div>
                    </div>

                    {{-- Petugas Rumah Sakit --}}
                    <div class="flex flex-col">
                        <div
                            class="mb-2 text-sm font-semibold tracking-wide text-center text-muted uppercase dark:text-gray-400">
                            Petugas Rumah Sakit
                        </div>
                        <x-signature.ttd-petugas :framed="false" :allowClear="false"
                            :ttd="$newForm['namaPetugas']" :date="$newForm['petugasDate'] ?? ''"
                            :code="$newForm['kodePetugas'] ?? ''" :locked="$isFormLocked"
                            sign="setPetugas" label="" signLabel="TTD sebagai Petugas" />
                    </div>
                </div>
            </section>

        </div>


        {{-- DAFTAR FORM TERSIMPAN --}}
        @if (count($listForm) > 0)
            <div class="mt-6 overflow-x-auto">
                <h3
                    class="text-base font-semibold text-body dark:text-gray-300 pb-2 border-b border-hairline-soft dark:border-gray-800 mb-3">
                    Daftar Form Pernyataan Tersimpan
                </h3>
                <table class="min-w-full text-base border border-hairline rounded-lg dark:border-gray-700">
                    <thead class="bg-surface-soft dark:bg-gray-800">
                        <tr class="text-left text-muted dark:text-gray-300">
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
                                class="border-b border-hairline dark:border-gray-700 hover:bg-surface-soft dark:hover:bg-gray-800">
                                <td class="px-4 py-2">{{ $form['tanggalFormPenjaminan'] ?? '-' }}</td>
                                <td class="px-4 py-2 font-medium text-ink dark:text-gray-200">
                                    {{ $form['pembuatNama'] ?? '-' }}
                                </td>
                                <td class="px-4 py-2 text-muted dark:text-gray-400">
                                    @php
                                        $jenis = collect($jenisPenjaminOptions)->firstWhere(
                                            'id',
                                            $form['jenisPenjamin'] ?? '',
                                        );
                                        $jenisDesc = $jenis ? $jenis['desc'] : $form['jenisPenjamin'] ?? '-';
                                    @endphp
                                    {{ $jenisDesc }}
                                    @if (($form['jenisPenjamin'] ?? '') === 'ASURANSI_LAIN' && !empty($form['asuransiLain']))
                                        <span class="text-sm text-muted">({{ $form['asuransiLain'] }})</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-muted dark:text-gray-400">
                                    {{ $kelasKamarOptions[$form['kelasKamar']]['nama'] ?? ($form['kelasKamar'] ?? '-') }}
                                </td>
                                <td class="px-4 py-2 text-center space-x-2">
                                    <x-secondary-button wire:click="cetak('{{ $form['signaturePembuatDate'] }}')"
                                        wire:loading.attr="disabled" wire:target="cetak" class="text-sm py-1 px-2">
                                        <span wire:loading.remove wire:target="cetak" class="flex items-center gap-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                            </svg>
                                            Cetak
                                        </span>
                                        <span wire:loading wire:target="cetak"
                                            class="flex items-center gap-1"><x-loading /> Mencetak...</span>
                                    </x-secondary-button>
                                    @if (!$isFormLocked)
                                        <x-outline-button type="button"
                                            wire:click.prevent="hapus('{{ $form['signaturePembuatDate'] }}')"
                                            wire:confirm="Yakin hapus form penjaminan ini? Data yang sudah ditandatangani akan dihapus."
                                            wire:loading.attr="disabled"
                                            class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300 !px-2 !py-1"
                                            title="Hapus">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </x-outline-button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
            </div>{{-- /konten flex-1 --}}

            {{-- ══ FOOTER STICKY (anak langsung modal-body → selalu terlihat) ══ --}}
            <div class="sticky bottom-0 z-10 px-6 py-3 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-end gap-3">
                    <x-secondary-button wire:click="cetak('')" wire:loading.attr="disabled" wire:target="cetak" class="gap-2">
                        <span wire:loading.remove wire:target="cetak">Cetak</span>
                        <span wire:loading wire:target="cetak"><x-loading class="w-4 h-4" /> Mencetak...</span>
                    </x-secondary-button>
                    <x-secondary-button type="button" wire:click="closeModal" class="min-w-[110px] justify-center">Tutup</x-secondary-button>
                    @if (!$isFormLocked)
                        <x-primary-button wire:click.prevent="save" wire:loading.attr="disabled" wire:target="save"
                            class="gap-2 min-w-[120px] justify-center">
                            <span wire:loading.remove wire:target="save">Simpan Form Penjaminan</span>
                            <span wire:loading wire:target="save"><x-loading class="w-4 h-4" /> Menyimpan...</span>
                        </x-primary-button>
                    @endif
                </div>
            </div>
    </div>
    </x-modal>

    {{-- Cetak component --}}
    <livewire:pages::components.modul-dokumen.u-g-d.form-penjaminan.cetak-form-penjaminan
        wire:key="cetak-form-penjaminan-{{ $rjNo ?? 'init' }}" />
</div>
