<?php
// resources/views/pages/transaksi/ugd/emr-ugd/modul-dokumen/form-penjaminan/rm-form-penjaminan-actions.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Support\KelasKamar;

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

    // Kunci entri yang sedang diedit (signaturePembuatDate = kunci stabil, di-set saat entri pertama dibuat).
    // null = sedang membuat entri baru.
    public ?string $editingKey = null;

    // true = entri terkunci sedang ditampilkan di form dalam mode read-only (lihat saja, tak bisa edit).
    public bool $viewOnly = false;

    public array $jenisPenjaminOptions = [['id' => 'BPJS_KESEHATAN', 'desc' => 'BPJS Kesehatan'], ['id' => 'BPJS_KETENAGAKERJAAN', 'desc' => 'BPJS Ketenagakerjaan'], ['id' => 'ASABRI_TASPEN', 'desc' => 'ASABRI / TASPEN'], ['id' => 'JASA_RAHARJA', 'desc' => 'Jasa Raharja'], ['id' => 'ASURANSI_LAIN', 'desc' => 'Asuransi Lain'], ['id' => 'TANPA_KARTU', 'desc' => 'Tidak memiliki Kartu Penjaminan']];

    // Master kelas kamar — SUMBER TUNGGAL di App\Support\KelasKamar (dipakai LOV, form & cetak).
    // Diisi di mount() untuk kotak fasilitas & label entri; pemilihan via LOV kelas kamar.
    public array $kelasKamarOptions = [];

    public array $hubunganOptions = ['Pasien Sendiri', 'Suami', 'Istri', 'Orang Tua', 'Anak', 'Saudara', 'Lainnya'];
    public array $listForm = [];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas(['modal-form-penjaminan']);
        $this->kelasKamarOptions = KelasKamar::all();
    }

    // Kelas kamar dipilih via LOV → set key ke newForm (payload null saat dikosongkan)
    #[On('lov.selected.kelas-kamar-penjaminan-ugd')]
    public function onKelasKamarSelected(string $target, ?array $payload): void
    {
        $this->newForm['kelasKamar'] = $payload['kelas'] ?? '';
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
        $this->editingKey = null;
        $this->viewOnly = false;
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
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->newForm['tanggalFormPenjaminan'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        $this->incrementVersion('modal-form-penjaminan');
    }

    /* ===============================
     | SET / CLEAR SIGNATURES (gambar, diisi saat draft)
     =============================== */
    public function setSignature(string $dataUrl): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->signature = $dataUrl;
        $this->incrementVersion('modal-form-penjaminan');
    }

    public function clearSignature(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->signature = '';
        $this->incrementVersion('modal-form-penjaminan');
    }

    public function setSignatureSaksi(string $dataUrl): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->signatureSaksi = $dataUrl;
        $this->incrementVersion('modal-form-penjaminan');
    }

    public function clearSignatureSaksi(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->signatureSaksi = '';
        $this->incrementVersion('modal-form-penjaminan');
    }

    /* ===============================
     | HELPER — status & bentuk entri
     =============================== */
    // Entri dianggap FINAL/terkunci bila flag finalized true; entri lama (tanpa flag) yang sudah
    // ada TTD petugas dianggap final (kompatibilitas data lama).
    public function entryIsFinal(array $e): bool
    {
        return array_key_exists('finalized', $e) ? (bool) $e['finalized'] : !empty($e['namaPetugas']);
    }

    // Susun array entri dari state form. $key = signaturePembuatDate (kunci stabil); $finalized = status kunci.
    private function buildEntry(string $key, bool $finalized): array
    {
        $now = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        return array_merge($this->newForm, [
            'signaturePembuat' => $this->signature,
            'signaturePembuatDate' => $key,
            'signatureSaksiKeluarga' => $this->signatureSaksi,
            'signatureSaksiKeluargaDate' => $this->signatureSaksi ? $now : '',
            'finalized' => $finalized,
        ]);
    }

    // Simpan entri (add/update by signaturePembuatDate) dengan status $finalized. Dipakai draft & kunci.
    private function persistEntry(string $key, bool $finalized, string $logVerb): void
    {
        $entry = $this->buildEntry($key, $finalized);

        DB::transaction(function () use ($entry, $key, $logVerb) {
            $this->lockUGDRow($this->rjNo);

            $data = $this->findDataUGD($this->rjNo);
            if (empty($data)) {
                throw new \RuntimeException('Data UGD tidak ditemukan, simpan dibatalkan.');
            }
            if (!isset($data['formPenjaminanOrientasiKamar']) || !is_array($data['formPenjaminanOrientasiKamar'])) {
                $data['formPenjaminanOrientasiKamar'] = [];
            }

            $list = $data['formPenjaminanOrientasiKamar'];
            $idx = collect($list)->search(fn($it) => ($it['signaturePembuatDate'] ?? '') === $key);
            if ($idx === false) {
                $list[] = $entry;
            } else {
                if ($this->entryIsFinal($list[$idx])) {
                    throw new \RuntimeException('Entri sudah terkunci, tidak dapat diubah.');
                }
                $list[$idx] = $entry;
            }
            $data['formPenjaminanOrientasiKamar'] = array_values($list);

            $this->updateJsonUGD($this->rjNo, $data);
            $this->dataDaftarUGD = $data;
            $this->listForm = $data['formPenjaminanOrientasiKamar'];

            $this->appendAdminLogUGD((int) $this->rjNo, $logVerb . ' Form Penjaminan UGD: ' . ($entry['pembuatNama'] ?: '-') . ' (' . $key . ')', 'MR');
        });
    }

    /* ===============================
     | SIMPAN DRAFT (nyicil, tanpa validasi lengkap / tanpa wajib TTD)
     =============================== */
    public function saveDraft(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }
        if (trim($this->newForm['pembuatNama'] ?? '') === '' && trim($this->newForm['jenisPenjamin'] ?? '') === '') {
            $this->dispatch('toast', type: 'error', message: 'Isi minimal Nama Pembuat atau Jenis Penjaminan untuk menyimpan draft.');
            return;
        }

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, false, 'Simpan draft');
            $this->editingKey = $key; // lanjut edit entri yang sama, tidak buat duplikat
            $this->incrementVersion('modal-form-penjaminan');
            $this->dispatch('toast', type: 'success', message: 'Draft Form Penjaminan tersimpan.');
            $this->dispatch('refresh-modul-dokumen-ugd-data', rjNo: $this->rjNo);
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan draft: ' . $e->getMessage());
        }
    }

    /* ===============================
     | TTD PETUGAS = FINALIZE
     | Petugas TTD di akhir → wajib 2 TTD gambar + validasi lengkap + kunci entri.
     =============================== */
    public function setPetugas(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        if (empty($this->signature)) {
            $this->dispatch('toast', type: 'error', message: 'TTD pembuat pernyataan wajib diisi sebelum TTD petugas.');
            return;
        }
        if (empty($this->signatureSaksi)) {
            $this->dispatch('toast', type: 'error', message: 'TTD saksi keluarga wajib diisi sebelum TTD petugas.');
            return;
        }

        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->dispatch('toast', type: 'error', message: 'Lengkapi seluruh kolom wajib sebelum TTD petugas.');
            throw $e;
        }

        // Stempel TTD petugas = user login.
        $this->newForm['namaPetugas'] = auth()->user()->myuser_name ?? '';
        $this->newForm['kodePetugas'] = auth()->user()->myuser_code ?? '';
        $this->newForm['petugasDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, true, 'Kunci (TTD Petugas)');
            $this->resetNewForm();
            $this->signature = '';
            $this->signatureSaksi = '';
            $this->editingKey = null;
            $this->incrementVersion('modal-form-penjaminan');
            $this->dispatch('toast', type: 'success', message: 'Form Penjaminan ditandatangani petugas & terkunci.');
            $this->dispatch('refresh-modul-dokumen-ugd-data', rjNo: $this->rjNo);
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal mengunci: ' . $e->getMessage());
        }
    }

    /* ===============================
     | EDIT / LIHAT / BATAL entri
     =============================== */
    // Muat 1 entri ke form atas (dipakai edit draft & lihat entri terkunci).
    private function hydrateFormFromEntry(array $entry, string $key): void
    {
        $this->newForm = [
            'tanggalFormPenjaminan' => $entry['tanggalFormPenjaminan'] ?? '',
            'pembuatNama' => $entry['pembuatNama'] ?? '',
            'hubunganDenganPasien' => $entry['hubunganDenganPasien'] ?? '',
            'jenisPenjamin' => $entry['jenisPenjamin'] ?? '',
            'asuransiLain' => $entry['asuransiLain'] ?? '',
            'bpjsKlausulDisetujui' => $entry['bpjsKlausulDisetujui'] ?? false,
            'kelasKamar' => $entry['kelasKamar'] ?? '',
            'orientasiKamarDijelaskan' => $entry['orientasiKamarDijelaskan'] ?? false,
            'namaSaksiKeluarga' => $entry['namaSaksiKeluarga'] ?? '',
            'namaPetugas' => $entry['namaPetugas'] ?? '',
            'kodePetugas' => $entry['kodePetugas'] ?? '',
            'petugasDate' => $entry['petugasDate'] ?? '',
        ];
        $this->signature = $entry['signaturePembuat'] ?? '';
        $this->signatureSaksi = $entry['signatureSaksiKeluarga'] ?? '';
        $this->editingKey = $key;
        $this->resetValidation();
        $this->incrementVersion('modal-form-penjaminan');
    }

    public function editEntry(string $key): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        $entry = collect($this->listForm)->firstWhere('signaturePembuatDate', $key);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Entri tidak ditemukan.');
            return;
        }
        if ($this->entryIsFinal($entry)) {
            $this->dispatch('toast', type: 'warning', message: 'Entri sudah terkunci, tidak dapat diedit.');
            return;
        }

        $this->viewOnly = false;
        $this->hydrateFormFromEntry($entry, $key);
        $this->dispatch('toast', type: 'info', message: 'Draft dimuat untuk dilanjutkan.');
    }

    // Lihat entri terkunci: muat ke form atas dalam mode read-only.
    public function viewEntry(string $key): void
    {
        $entry = collect($this->listForm)->firstWhere('signaturePembuatDate', $key);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Entri tidak ditemukan.');
            return;
        }

        $this->viewOnly = true;
        $this->hydrateFormFromEntry($entry, $key);
        $this->dispatch('toast', type: 'info', message: 'Menampilkan entri terkunci (hanya lihat).');
    }

    public function cancelEdit(): void
    {
        $this->resetNewForm();
        $this->signature = '';
        $this->signatureSaksi = '';
        $this->editingKey = null;
        $this->viewOnly = false;
        $this->resetValidation();
        $this->incrementVersion('modal-form-penjaminan');
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
        $this->editingKey = null;
        $this->viewOnly = false;
    }
};
?>

<div>
    {{-- RINGKASAN + TOMBOL (pola General Consent) --}}
    @php $penjaminanCount = count($listForm ?? []); @endphp
    <div class="p-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div class="flex-1 space-y-2">
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">Form Penjaminan &amp; Orientasi Kamar</h3>
                    @if ($penjaminanCount > 0)
                        <x-badge variant="success">{{ $penjaminanCount }} tersimpan</x-badge>
                    @else
                        <x-badge variant="warning">Belum ada</x-badge>
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

        @if ($penjaminanCount > 0)
            <div class="mt-4 overflow-x-auto">
                <h4 class="mb-2 text-sm font-semibold text-body dark:text-gray-300">Daftar Form Pernyataan Tersimpan</h4>
                <table class="min-w-full text-sm border border-hairline rounded-lg dark:border-gray-700">
                    <thead class="bg-surface-soft dark:bg-gray-800">
                        <tr class="text-left text-muted dark:text-gray-300">
                            <th class="px-3 py-2 border-b">Tanggal</th>
                            <th class="px-3 py-2 border-b">Pembuat</th>
                            <th class="px-3 py-2 border-b">Jenis Penjamin</th>
                            <th class="px-3 py-2 border-b">Petugas</th>
                            <th class="px-3 py-2 border-b text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (array_reverse($listForm) as $ic)
                            @php
                                $jenisRow = collect($jenisPenjaminOptions)->firstWhere('id', $ic['jenisPenjamin'] ?? '');
                                $jenisRowDesc = $jenisRow ? $jenisRow['desc'] : ($ic['jenisPenjamin'] ?? '-');
                            @endphp
                            <tr class="border-b border-hairline dark:border-gray-700">
                                <td class="px-3 py-2 text-muted dark:text-gray-400 tabular-nums">{{ $ic['signaturePembuatDate'] ?? '-' }}</td>
                                <td class="px-3 py-2 font-medium text-ink dark:text-gray-200">{{ $ic['pembuatNama'] ?? '-' }}</td>
                                <td class="px-3 py-2 text-muted dark:text-gray-400">{{ $jenisRowDesc }}</td>
                                <td class="px-3 py-2 text-muted dark:text-gray-400">
                                    @if (!empty($ic['namaPetugas'])){{ $ic['namaPetugas'] }}@else<x-badge variant="danger">Belum TTD</x-badge>@endif
                                </td>
                                <td class="px-3 py-2 text-center">
                                    @if ($this->entryIsFinal($ic))
                                        <x-badge variant="info">Terkunci</x-badge>
                                    @else
                                        <x-badge variant="warning">Draft</x-badge>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- MODAL FORM --}}
    <x-modal name="rm-form-penjaminan-{{ $rjNo ?? 'init' }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]" wire:key="{{ $this->renderKey('modal-form-penjaminan', [$rjNo ?? 'new']) }}">
            {{-- HEADER MODAL --}}
            <div class="flex items-center justify-between gap-4 px-6 py-4 border-b border-hairline bg-surface-soft dark:border-gray-700">
                <div class="flex items-center gap-3">
                    <h2 class="text-xl font-semibold text-ink dark:text-gray-100">Form Penjaminan &amp; Orientasi Kamar</h2>
                    <x-badge variant="danger">UGD</x-badge>
                    @if ($penjaminanCount > 0)
                        <x-badge variant="info">{{ $penjaminanCount }} tersimpan</x-badge>
                    @endif
                    @if ($isFormLocked)
                        <x-badge variant="danger">Read Only</x-badge>
                    @endif
                </div>
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

            @php $formRO = $isFormLocked || $viewOnly; @endphp

        @if ($isFormLocked)
            <div
                class="flex items-center gap-2 px-4 py-2.5 mt-4 text-base font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300 mx-4">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                EMR terkunci — data tidak dapat diubah.
            </div>
        @endif

        @if ($viewOnly)
            <div
                class="flex items-center gap-2 px-4 py-2.5 mt-4 text-base font-medium text-sky-700 bg-sky-50 border border-sky-200 rounded-xl dark:bg-sky-900/20 dark:border-sky-600 dark:text-sky-300 mx-4">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                Menampilkan entri terkunci <strong>{{ $editingKey }}</strong> (hanya lihat) — klik <strong>Selesai Melihat</strong> untuk kembali ke form entri baru.
            </div>
        @elseif ($editingKey && !$isFormLocked)
            <div
                class="flex items-center gap-2 px-4 py-2.5 mt-4 text-base font-medium text-brand-green bg-brand-lime/10 border border-brand-lime/40 rounded-xl dark:text-brand-lime dark:bg-brand-lime/5 mx-4">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Sedang melanjutkan entri <strong>{{ $editingKey }}</strong> — <strong>Simpan Perubahan</strong> menyimpan ke entri ini; klik <strong>Entri Baru</strong> untuk menambah pernyataan lain.
            </div>
        @endif

        <div
            class="p-6 mt-4 space-y-6 bg-canvas border border-hairline shadow-sm sm:p-8 rounded-2xl dark:bg-gray-900 dark:border-gray-700 mx-4">

            {{-- ══ DATA PERNYATAAN & PENJAMINAN ══ --}}
            <section class="space-y-4">
                <h3 class="text-base font-semibold text-ink dark:text-gray-200">
                    Data Pernyataan &amp; Penjaminan
                </h3>

                <div>
                    <x-input-label value="Tanggal Form Pernyataan *" class="mb-1" />
                    <div class="flex gap-2">
                        <x-text-input wire:model.live="newForm.tanggalFormPenjaminan" :error="$errors->has('newForm.tanggalFormPenjaminan')" placeholder="dd/mm/yyyy hh:ii:ss"
                            :disabled="$formRO" class="flex-1" />
                        <x-now-button wire:click="setTanggalForm" wire:loading.attr="disabled" :disabled="$formRO" />
                    </div>
                    <x-input-error :messages="$errors->get('newForm.tanggalFormPenjaminan')" class="mt-1" />
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <x-input-label value="Nama Pembuat Pernyataan *" class="mb-1" />
                        <x-text-input wire:model.live="newForm.pembuatNama" :error="$errors->has('newForm.pembuatNama')" placeholder="Nama lengkap..."
                            :disabled="$formRO" class="w-full" />
                        <x-input-error :messages="$errors->get('newForm.pembuatNama')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label value="Hubungan dengan Pasien *" class="mb-1" />
                        <x-select-input wire:model.live="newForm.hubunganDenganPasien" :error="$errors->has('newForm.hubunganDenganPasien')" :disabled="$formRO">
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
                        <x-select-input wire:model.live="newForm.jenisPenjamin" :error="$errors->has('newForm.jenisPenjamin')" :disabled="$formRO">
                            <option value="">Pilih</option>
                            @foreach ($jenisPenjaminOptions as $opt)
                                <option value="{{ $opt['id'] }}">{{ $opt['desc'] }}</option>
                            @endforeach
                        </x-select-input>
                        <x-input-error :messages="$errors->get('newForm.jenisPenjamin')" class="mt-1" />
                    </div>

                    <div>
                        <livewire:lov.kelas-kamar.lov-kelas-kamar target="kelas-kamar-penjaminan-ugd"
                            label="Pilih Kelas Kamar *" placeholder="Ketik / pilih kelas kamar..."
                            :initialKelas="$newForm['kelasKamar'] ?? null" :disabled="$formRO"
                            wire:key="lov-kelas-kamar-{{ $editingKey ?? 'new' }}-{{ $renderVersions['modal-form-penjaminan'] ?? 0 }}" />
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
                            placeholder="Contoh: Allianz, Prudential, dll" :disabled="$formRO" class="w-full" />
                        <x-input-error :messages="$errors->get('newForm.asuransiLain')" class="mt-1" />
                    </div>
                @endif

                @if (($newForm['jenisPenjamin'] ?? '') === 'BPJS_KESEHATAN')
                    <div>
                        <x-toggle wire:model.live="newForm.bpjsKlausulDisetujui" trueValue="1" falseValue="0"
                            label="Saya menyetujui ketentuan penjaminan BPJS Kesehatan sesuai dengan peraturan yang berlaku."
                            :disabled="$formRO" />
                        <x-input-error :messages="$errors->get('newForm.bpjsKlausulDisetujui')" class="mt-1" />
                    </div>
                @endif

                <div>
                    <x-toggle wire:model.live="newForm.orientasiKamarDijelaskan" trueValue="1" falseValue="0"
                        label="Saya telah mendapatkan penjelasan mengenai fasilitas kamar yang dipilih beserta tarifnya."
                        :disabled="$formRO" />
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
                            <x-signature.signature-result :signature="$signature" :date="''" :disabled="$formRO"
                                wireMethod="clearSignature" />
                        @elseif (!$formRO)
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
                            <x-signature.signature-result :signature="$signatureSaksi" :date="''" :disabled="$formRO"
                                wireMethod="clearSignatureSaksi" />
                        @elseif (!$formRO)
                            <x-signature.signature-pad wireMethod="setSignatureSaksi" />
                        @else
                            <p class="py-8 text-base italic text-center text-muted-soft">Belum ditandatangani.</p>
                        @endif

                        <div class="mt-3">
                            <x-input-label value="Nama Saksi Keluarga *" class="mb-1" />
                            <x-text-input wire:model.live="newForm.namaSaksiKeluarga" :error="$errors->has('newForm.namaSaksiKeluarga')"
                                placeholder="Nama lengkap saksi..." :disabled="$formRO" class="w-full" />
                            <x-input-error :messages="$errors->get('newForm.namaSaksiKeluarga')" class="mt-1" />
                        </div>
                    </div>

                    {{-- Petugas Rumah Sakit = TTD Petugas & Kunci (finalize) --}}
                    <div class="flex flex-col">
                        <div
                            class="mb-2 text-sm font-semibold tracking-wide text-center text-muted uppercase dark:text-gray-400">
                            Petugas Rumah Sakit
                        </div>
                        <x-signature.ttd-petugas :framed="false" :allowClear="false"
                            :ttd="$newForm['namaPetugas']" :date="$newForm['petugasDate'] ?? ''"
                            :code="$newForm['kodePetugas'] ?? ''" :locked="$formRO"
                            sign="setPetugas" label="" signLabel="TTD Petugas &amp; Kunci" />
                        @if (!$formRO && empty($newForm['namaPetugas']))
                            <p class="mt-2 text-xs text-center text-muted">Menandatangani = validasi lengkap &amp; mengunci form ini.</p>
                        @endif
                    </div>
                </div>
            </section>

        </div>


        {{-- DAFTAR FORM TERSIMPAN (tabel expandable) --}}
        @if (count($listForm) > 0)
            <div class="mt-6 overflow-x-auto px-4 pb-4">
                <div class="flex items-center justify-between gap-2 pb-2 border-b border-hairline-soft dark:border-gray-800 mb-3">
                    <h3 class="text-base font-semibold text-body dark:text-gray-300">
                        Daftar Form Pernyataan Tersimpan
                    </h3>
                    <span class="text-xs italic text-muted-soft">Klik baris untuk lihat detail lengkap</span>
                </div>
                <table class="min-w-full text-base border border-hairline rounded-lg dark:border-gray-700">
                    <thead class="bg-surface-soft dark:bg-gray-800">
                        <tr class="text-left text-sm font-semibold tracking-wide text-muted uppercase dark:text-gray-300">
                            <th class="w-8 px-2 py-3 border-b"></th>
                            <th class="px-4 py-3 border-b">Tanggal</th>
                            <th class="px-4 py-3 border-b">Nama Pembuat</th>
                            <th class="px-4 py-3 border-b">Jenis Penjamin</th>
                            <th class="px-4 py-3 border-b">Petugas</th>
                            <th class="px-4 py-3 border-b text-center">Status</th>
                            <th class="px-4 py-3 border-b text-center">Aksi</th>
                        </tr>
                    </thead>
                    @foreach (array_reverse($listForm) as $entry)
                        @php
                            $isFinal = $this->entryIsFinal($entry);
                            $rowKey = $entry['signaturePembuatDate'] ?? '';
                            $jenisEntry = collect($jenisPenjaminOptions)->firstWhere('id', $entry['jenisPenjamin'] ?? '');
                            $jenisEntryDesc = $jenisEntry ? $jenisEntry['desc'] : ($entry['jenisPenjamin'] ?? '-');
                        @endphp
                        <tbody x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }" class="border-b border-hairline dark:border-gray-700">
                            <tr @click="open = !open"
                                class="cursor-pointer hover:bg-surface-soft dark:hover:bg-gray-800 {{ $editingKey && $editingKey === $rowKey ? 'bg-brand-lime/10 dark:bg-brand-lime/5' : '' }}">
                                <td class="px-2 py-3 text-center align-middle">
                                    <svg class="w-4 h-4 mx-auto text-muted transition-transform" :class="{ 'rotate-90': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </td>
                                <td class="px-4 py-3 align-middle text-sm tabular-nums text-muted dark:text-gray-400">
                                    {{ $entry['tanggalFormPenjaminan'] ?: ($rowKey ?: '-') }}
                                </td>
                                <td class="px-4 py-3 align-middle font-semibold text-ink dark:text-gray-100">
                                    {{ $entry['pembuatNama'] ?: '(tanpa nama)' }}
                                </td>
                                <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">
                                    {{ $jenisEntryDesc }}
                                    @if (($entry['jenisPenjamin'] ?? '') === 'ASURANSI_LAIN' && !empty($entry['asuransiLain']))
                                        <span class="text-sm text-muted">({{ $entry['asuransiLain'] }})</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">
                                    @if (!empty($entry['namaPetugas']))
                                        <span class="font-medium text-ink dark:text-gray-200">{{ $entry['namaPetugas'] }}</span>
                                    @else
                                        <x-badge variant="danger">Belum TTD</x-badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3 align-middle text-center">
                                    @if ($isFinal)
                                        <x-badge variant="info">Terkunci</x-badge>
                                    @else
                                        <x-badge variant="warning">Draft</x-badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3 align-middle text-center" @click.stop>
                                    <div class="flex items-center justify-center gap-2">
                                        @if (!$isFinal && !$isFormLocked)
                                            <x-primary-button type="button" wire:click="editEntry('{{ $rowKey }}')" wire:loading.attr="disabled" wire:target="editEntry('{{ $rowKey }}')" class="gap-1.5" title="Lanjutkan mengisi entri ini">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                                Lanjut Isi
                                            </x-primary-button>
                                        @endif
                                        @if ($isFinal)
                                            <x-secondary-button type="button" wire:click="viewEntry('{{ $rowKey }}')" wire:loading.attr="disabled" wire:target="viewEntry('{{ $rowKey }}')" class="gap-1.5" title="Lihat detail (read-only) di form atas">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                                Lihat
                                            </x-secondary-button>
                                            <x-secondary-button wire:click="cetak('{{ $rowKey }}')" wire:loading.attr="disabled" wire:target="cetak('{{ $rowKey }}')" class="gap-1.5">
                                                <span wire:loading.remove wire:target="cetak('{{ $rowKey }}')" class="flex items-center gap-1.5">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                    </svg>
                                                    Cetak
                                                </span>
                                                <span wire:loading wire:target="cetak('{{ $rowKey }}')" class="flex items-center gap-1.5"><x-loading class="w-5 h-5" /> Mencetak...</span>
                                            </x-secondary-button>
                                        @endif
                                        @if (!$isFormLocked)
                                            <x-outline-button type="button" wire:click.prevent="hapus('{{ $rowKey }}')" wire:confirm="Yakin hapus form penjaminan ini? Data yang sudah ditandatangani akan dihapus."
                                                wire:loading.attr="disabled"
                                                class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300"
                                                title="Hapus">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </x-outline-button>
                                        @endif
                                    </div>
                                </td>
                            </tr>

                            {{-- DETAIL (expand) --}}
                            <tr x-show="open" x-cloak>
                                <td colspan="7" class="px-4 py-4 bg-surface-soft/60 dark:bg-gray-950/30">
                                    <dl class="grid grid-cols-1 gap-x-8 gap-y-3 md:grid-cols-2">
                                        <div>
                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Tanggal Form Pernyataan</dt>
                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['tanggalFormPenjaminan'] ?: '-' }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Nama Pembuat Pernyataan</dt>
                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['pembuatNama'] ?: '-' }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Hubungan dengan Pasien</dt>
                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['hubunganDenganPasien'] ?: '-' }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Jenis Kartu Penjaminan</dt>
                                            <dd class="mt-0.5 text-ink dark:text-gray-200">
                                                {{ $jenisEntryDesc }}
                                                @if (($entry['jenisPenjamin'] ?? '') === 'ASURANSI_LAIN' && !empty($entry['asuransiLain']))
                                                    <span class="text-muted">({{ $entry['asuransiLain'] }})</span>
                                                @endif
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Kelas Kamar</dt>
                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $kelasKamarOptions[$entry['kelasKamar']]['nama'] ?? ($entry['kelasKamar'] ?? '-') }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Orientasi Kamar Dijelaskan</dt>
                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ !empty($entry['orientasiKamarDijelaskan']) && $entry['orientasiKamarDijelaskan'] != '0' ? 'Ya' : 'Belum' }}</dd>
                                        </div>
                                        @if (($entry['jenisPenjamin'] ?? '') === 'BPJS_KESEHATAN')
                                            <div>
                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Persetujuan Ketentuan BPJS</dt>
                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ !empty($entry['bpjsKlausulDisetujui']) && $entry['bpjsKlausulDisetujui'] != '0' ? 'Disetujui' : 'Belum' }}</dd>
                                            </div>
                                        @endif
                                        <div>
                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Nama Saksi Keluarga</dt>
                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['namaSaksiKeluarga'] ?: '-' }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">TTD Pembuat Pernyataan</dt>
                                            <dd class="mt-0.5">
                                                @if (!empty($entry['signaturePembuat']))
                                                    <span class="text-success-deep dark:text-green-300">Sudah TTD</span>
                                                    <span class="text-sm text-muted-soft">— {{ $entry['signaturePembuatDate'] ?? '-' }}</span>
                                                @else
                                                    <x-badge variant="danger">Belum TTD</x-badge>
                                                @endif
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">TTD Saksi Keluarga</dt>
                                            <dd class="mt-0.5">
                                                @if (!empty($entry['signatureSaksiKeluarga']))
                                                    <span class="text-success-deep dark:text-green-300">Sudah TTD</span>
                                                    <span class="text-sm text-muted-soft">— {{ $entry['signatureSaksiKeluargaDate'] ?? '-' }}</span>
                                                @else
                                                    <x-badge variant="danger">Belum TTD</x-badge>
                                                @endif
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Petugas Rumah Sakit</dt>
                                            <dd class="mt-0.5">
                                                @if (!empty($entry['namaPetugas']))
                                                    <span class="text-ink dark:text-gray-200">{{ $entry['namaPetugas'] }}</span>
                                                    @if (!empty($entry['kodePetugas']))<span class="text-sm text-muted-soft"> ({{ $entry['kodePetugas'] }})</span>@endif
                                                    <span class="text-sm text-muted-soft">— {{ $entry['petugasDate'] ?? '-' }}</span>
                                                @else
                                                    <x-badge variant="danger">Belum TTD</x-badge>
                                                @endif
                                            </dd>
                                        </div>
                                    </dl>
                                </td>
                            </tr>
                        </tbody>
                    @endforeach
                </table>
            </div>
        @endif
            </div>{{-- /konten flex-1 --}}

            {{-- ══ FOOTER STICKY (anak langsung modal-body → selalu terlihat) ══ --}}
            <div class="sticky bottom-0 z-10 px-6 py-4 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    @if ($viewOnly)
                        <p class="flex items-center gap-1.5 text-sm text-sky-600 dark:text-sky-400">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <span>Mode lihat — entri terkunci, tidak dapat diubah.</span>
                        </p>
                    @elseif ($rjNo && !$isFormLocked)
                        <p class="flex items-center gap-1.5 text-sm text-muted dark:text-gray-400">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Simpan draft dulu, lalu <strong>kunci</strong> lewat tombol <strong>TTD Petugas &amp; Kunci</strong> di kolom Petugas Rumah Sakit.</span>
                        </p>
                    @else
                        <span></span>
                    @endif

                    <div class="flex flex-wrap items-center justify-end gap-3">
                        <x-secondary-button type="button" wire:click="closeModal" class="min-w-[110px] justify-center">Tutup</x-secondary-button>

                        @if ($viewOnly)
                            <x-primary-button wire:click.prevent="cancelEdit" wire:target="cancelEdit"
                                wire:loading.attr="disabled" class="gap-1.5 min-w-[160px] justify-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                Selesai Melihat
                            </x-primary-button>
                        @elseif ($rjNo && !$isFormLocked)
                            @if ($editingKey)
                                <x-outline-button wire:click.prevent="cancelEdit" wire:target="cancelEdit"
                                    wire:loading.attr="disabled" class="gap-1.5"
                                    title="Kosongkan form untuk menambah pernyataan lain — entri yang sudah tersimpan tidak berubah">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 4v16m8-8H4" />
                                    </svg>
                                    Entri Baru
                                </x-outline-button>
                            @endif
                            <x-primary-button wire:click.prevent="saveDraft" wire:loading.attr="disabled"
                                wire:target="saveDraft" class="gap-2 min-w-[160px] justify-center">
                                <span wire:loading.remove wire:target="saveDraft" class="flex items-center gap-1.5">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17 21v-8H7v8M7 3v5h8M5 3h11l4 4v12a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z" />
                                    </svg>
                                    {{ $editingKey ? 'Simpan Perubahan' : 'Simpan Draft' }}
                                </span>
                                <span wire:loading wire:target="saveDraft"><x-loading class="w-4 h-4" /> Menyimpan...</span>
                            </x-primary-button>
                        @endif
                    </div>
                </div>
            </div>
    </div>
    </x-modal>

    {{-- Cetak component --}}
    <livewire:pages::components.modul-dokumen.u-g-d.form-penjaminan.cetak-form-penjaminan
        wire:key="cetak-form-penjaminan-{{ $rjNo ?? 'init' }}" />
</div>
