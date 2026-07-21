<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/case-manager-ri/rm-case-manager-ri-actions.blade.php
// Case Manager (MPP) — DUA list independen: Form A (Skrining Awal) & Form B (Pelaksanaan/Monitoring).
// Pola multi-entri: Draft (nyicil) + Lanjut Isi + TTD-Kunci (finalize) + Lihat (read-only) + tabel expandable.
// Disimpan ke datadaftarri_json → formMPP.formA[] / formMPP.formB[]. Kunci entri stabil = formA_id / formB_id (uuid).
// FINAL = petugas sudah TTD (tandaTanganPetugas.petugasName terisi). Stempel NAMA saja (tanpa gambar), jabatan MPP.
// Model diterapkan ke KEDUA list secara independen (editingKeyA/B, viewOnlyA/B).

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\WithValidationToast\WithValidationToastTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

new class extends Component {
    use EmrRITrait, MasterPasienTrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public bool $isFormLocked = false;
    public ?string $riHdrNo = null;
    public bool $disabled = false;
    public array $dataDaftarRi = [];

    public array $formA = [
        'formA_id' => '',
        'tipeForm' => 'FormA',
        'tanggal' => '',
        'indentifikasiKasus' => '',
        'assessment' => '',
        'perencanaan' => '',
        'tandaTanganPetugas' => ['petugasCode' => '', 'petugasName' => '', 'jabatan' => 'MPP'],
    ];

    public array $formB = [
        'formB_id' => '',
        'tipeForm' => 'FormB',
        'formA_id' => '',
        'tanggal' => '',
        'pelaksanaanMonitoring' => '',
        'advokasiKolaborasi' => '',
        'terminasi' => '',
        'tandaTanganPetugas' => ['petugasCode' => '', 'petugasName' => '', 'jabatan' => 'MPP'],
    ];

    // Kunci entri yang sedang diedit per-list (formA_id / formB_id). null = sedang membuat entri baru.
    public ?string $editingKeyA = null;
    public ?string $editingKeyB = null;

    // true = entri terkunci sedang ditampilkan (read-only) di form section tsb.
    public bool $viewOnlyA = false;
    public bool $viewOnlyB = false;

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-case-manager-ri'];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(?string $riHdrNo = null, bool $disabled = false): void
    {
        $this->riHdrNo = $riHdrNo ?: $this->riHdrNo;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-case-manager-ri']);

        if ($this->riHdrNo) {
            $this->loadData();
        }
    }

    private function loadData(): void
    {
        $data = $this->findDataRI($this->riHdrNo);
        if (!$data) {
            return;
        }
        $this->dataDaftarRi = $data;
        $this->dataDaftarRi['formMPP'] ??= ['formA' => [], 'formB' => []];
        $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $this->disabled;
    }

    /* ===============================
     | OPEN / CLOSE MODAL — pola standar modul-dokumen (kartu + tombol Buka → x-modal)
     =============================== */
    public function openModal(): void
    {
        if (!$this->riHdrNo || $this->disabled) {
            return;
        }

        $this->resetForm();

        $data = $this->findDataRI($this->riHdrNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }

        $this->dataDaftarRi = $data;
        $this->dataDaftarRi['formMPP'] ??= ['formA' => [], 'formB' => []];
        $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $this->disabled;

        $this->incrementVersion('modal-case-manager-ri');
        $this->dispatch('open-modal', name: "rm-case-manager-ri-{$this->riHdrNo}");
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: "rm-case-manager-ri-{$this->riHdrNo}");
    }

    /* ===============================
     | SET TANGGAL SEKARANG
     =============================== */
    public function setTanggalFormA(): void
    {
        if ($this->isFormLocked || $this->viewOnlyA) {
            return;
        }
        $this->formA['tanggal'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    public function setTanggalFormB(): void
    {
        if ($this->isFormLocked || $this->viewOnlyB) {
            return;
        }
        $this->formB['tanggal'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /* ===============================
     | HELPER — status entri (FINAL = petugas sudah TTD)
     =============================== */
    public function entryIsFinal(array $e): bool
    {
        return array_key_exists('finalized', $e)
            ? (bool) $e['finalized']
            : !empty(data_get($e, 'tandaTanganPetugas.petugasName'));
    }

    /* ===============================
     | BUILD ENTRY (dari state form) — id = key, + flag finalized
     =============================== */
    private function buildEntryA(string $key, bool $finalized): array
    {
        return [
            'formA_id' => $key,
            'tipeForm' => 'FormA',
            'tanggal' => $this->formA['tanggal'] ?? '',
            'indentifikasiKasus' => $this->formA['indentifikasiKasus'] ?? '',
            'assessment' => $this->formA['assessment'] ?? '',
            'perencanaan' => $this->formA['perencanaan'] ?? '',
            'tandaTanganPetugas' => [
                'petugasCode' => $this->formA['tandaTanganPetugas']['petugasCode'] ?? '',
                'petugasName' => $this->formA['tandaTanganPetugas']['petugasName'] ?? '',
                'jabatan' => $this->formA['tandaTanganPetugas']['jabatan'] ?? 'MPP',
            ],
            'finalized' => $finalized,
        ];
    }

    private function buildEntryB(string $key, bool $finalized): array
    {
        return [
            'formB_id' => $key,
            'tipeForm' => 'FormB',
            'formA_id' => $this->formB['formA_id'] ?? '',
            'tanggal' => $this->formB['tanggal'] ?? '',
            'pelaksanaanMonitoring' => $this->formB['pelaksanaanMonitoring'] ?? '',
            'advokasiKolaborasi' => $this->formB['advokasiKolaborasi'] ?? '',
            'terminasi' => $this->formB['terminasi'] ?? '',
            'tandaTanganPetugas' => [
                'petugasCode' => $this->formB['tandaTanganPetugas']['petugasCode'] ?? '',
                'petugasName' => $this->formB['tandaTanganPetugas']['petugasName'] ?? '',
                'jabatan' => $this->formB['tandaTanganPetugas']['jabatan'] ?? 'MPP',
            ],
            'finalized' => $finalized,
        ];
    }

    /* ===============================
     | PERSIST (add / update by formA_id | formB_id) — tolak update entri final
     =============================== */
    private function persistEntryA(string $key, bool $finalized, string $logVerb): void
    {
        $entry = $this->buildEntryA($key, $finalized);

        DB::transaction(function () use ($entry, $key, $logVerb) {
            $this->lockRIRow($this->riHdrNo);

            $fresh = $this->findDataRI($this->riHdrNo) ?: [];
            if (empty($fresh)) {
                throw new \RuntimeException('Data RI tidak ditemukan, simpan dibatalkan.');
            }
            $fresh['formMPP'] ??= ['formA' => [], 'formB' => []];
            $fresh['formMPP']['formA'] ??= [];

            $list = $fresh['formMPP']['formA'];
            $idx = collect($list)->search(fn($it) => ($it['formA_id'] ?? '') === $key);
            if ($idx === false) {
                $entry['created_at'] = now()->format('Y-m-d H:i:s');
                $list[] = $entry;
            } else {
                if ($this->entryIsFinal($list[$idx])) {
                    throw new \RuntimeException('Entri sudah terkunci, tidak dapat diubah.');
                }
                $entry['created_at'] = $list[$idx]['created_at'] ?? now()->format('Y-m-d H:i:s');
                $entry['updated_at'] = now()->format('Y-m-d H:i:s');
                $list[$idx] = $entry;
            }
            $fresh['formMPP']['formA'] = array_values($list);

            $this->updateJsonRI((int) $this->riHdrNo, $fresh);
            $this->dataDaftarRi = $fresh;

            $this->appendAdminLogRI((int) $this->riHdrNo, $logVerb . ' Form A (Skrining MPP) — ' . ($entry['tanggal'] ?: '-') . ' (' . $key . ')', 'MR');
        });
    }

    private function persistEntryB(string $key, bool $finalized, string $logVerb): void
    {
        $entry = $this->buildEntryB($key, $finalized);

        DB::transaction(function () use ($entry, $key, $logVerb) {
            $this->lockRIRow($this->riHdrNo);

            $fresh = $this->findDataRI($this->riHdrNo) ?: [];
            if (empty($fresh)) {
                throw new \RuntimeException('Data RI tidak ditemukan, simpan dibatalkan.');
            }
            $fresh['formMPP'] ??= ['formA' => [], 'formB' => []];
            $fresh['formMPP']['formB'] ??= [];

            $list = $fresh['formMPP']['formB'];
            $idx = collect($list)->search(fn($it) => ($it['formB_id'] ?? '') === $key);
            if ($idx === false) {
                $entry['created_at'] = now()->format('Y-m-d H:i:s');
                $list[] = $entry;
            } else {
                if ($this->entryIsFinal($list[$idx])) {
                    throw new \RuntimeException('Entri sudah terkunci, tidak dapat diubah.');
                }
                $entry['created_at'] = $list[$idx]['created_at'] ?? now()->format('Y-m-d H:i:s');
                $entry['updated_at'] = now()->format('Y-m-d H:i:s');
                $list[$idx] = $entry;
            }
            $fresh['formMPP']['formB'] = array_values($list);

            $this->updateJsonRI((int) $this->riHdrNo, $fresh);
            $this->dataDaftarRi = $fresh;

            $this->appendAdminLogRI((int) $this->riHdrNo, $logVerb . ' Form B (Pelaksanaan MPP) — ' . ($entry['tanggal'] ?: '-') . ' (' . $key . ')', 'MR');
        });
    }

    /* ===============================
     | SIMPAN DRAFT (nyicil, tanpa TTD & tanpa validasi penuh)
     =============================== */
    public function saveDraftA(): void
    {
        if ($this->isFormLocked || $this->viewOnlyA) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }
        if (blank($this->formA['indentifikasiKasus'] ?? null)) {
            $this->dispatch('toast', type: 'error', message: 'Isi minimal Identifikasi Kasus untuk menyimpan draft Form A.');
            return;
        }

        $key = $this->editingKeyA ?: (string) Str::uuid();

        try {
            $this->persistEntryA($key, false, 'Simpan draft');
            $this->editingKeyA = $key;
            $this->incrementVersion('modal-case-manager-ri');
            $this->dispatch('toast', type: 'success', message: 'Draft Form A tersimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan draft: ' . $e->getMessage());
        }
    }

    public function saveDraftB(): void
    {
        if ($this->isFormLocked || $this->viewOnlyB) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }
        if (blank($this->formB['formA_id'] ?? null)) {
            $this->dispatch('toast', type: 'error', message: 'Pilih referensi Form A terlebih dahulu.');
            return;
        }
        if (blank($this->formB['pelaksanaanMonitoring'] ?? null)) {
            $this->dispatch('toast', type: 'error', message: 'Isi minimal Pelaksanaan & Monitoring untuk menyimpan draft Form B.');
            return;
        }

        $key = $this->editingKeyB ?: (string) Str::uuid();

        try {
            $this->persistEntryB($key, false, 'Simpan draft');
            $this->editingKeyB = $key;
            $this->incrementVersion('modal-case-manager-ri');
            $this->dispatch('toast', type: 'success', message: 'Draft Form B tersimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan draft: ' . $e->getMessage());
        }
    }

    /* ===============================
     | FINALIZE (TTD Petugas & Kunci)
     =============================== */
    public function kunciFormA(): void
    {
        if ($this->isFormLocked || $this->viewOnlyA) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }

        // Stempel TTD petugas = user login (nama, tanpa gambar), jabatan MPP.
        $this->formA['tandaTanganPetugas'] = [
            'petugasCode' => auth()->user()->myuser_code ?? '',
            'petugasName' => auth()->user()->myuser_name ?? '',
            'jabatan' => 'MPP',
        ];

        $this->validateWithToast(
            [
                'formA.tanggal' => 'required|date_format:d/m/Y H:i:s',
                'formA.indentifikasiKasus' => 'required|string',
                'formA.assessment' => 'required|string',
                'formA.perencanaan' => 'required|string',
                'formA.tandaTanganPetugas.petugasCode' => 'required|string|max:50',
                'formA.tandaTanganPetugas.petugasName' => 'required|string|max:150',
            ],
            [
                'formA.tanggal.required' => 'Tanggal wajib diisi.',
                'formA.indentifikasiKasus.required' => 'Identifikasi Kasus wajib diisi.',
                'formA.assessment.required' => 'Assessment wajib diisi.',
                'formA.perencanaan.required' => 'Perencanaan wajib diisi.',
                'formA.tandaTanganPetugas.petugasCode.required' => 'Kode petugas wajib diisi.',
                'formA.tandaTanganPetugas.petugasName.required' => 'Nama petugas wajib diisi.',
            ],
        );

        $key = $this->editingKeyA ?: (string) Str::uuid();

        try {
            $this->persistEntryA($key, true, 'Kunci (TTD)');
            $this->resetFormA();
            $this->editingKeyA = null;
            $this->viewOnlyA = false;
            $this->incrementVersion('modal-case-manager-ri');
            $this->dispatch('toast', type: 'success', message: 'Form A ditandatangani & terkunci.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal mengunci: ' . $e->getMessage());
        }
    }

    public function kunciFormB(): void
    {
        if ($this->isFormLocked || $this->viewOnlyB) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }

        $this->formB['tandaTanganPetugas'] = [
            'petugasCode' => auth()->user()->myuser_code ?? '',
            'petugasName' => auth()->user()->myuser_name ?? '',
            'jabatan' => 'MPP',
        ];

        $this->validateWithToast(
            [
                'formB.formA_id' => 'required|string',
                'formB.tanggal' => 'required|date_format:d/m/Y H:i:s',
                'formB.pelaksanaanMonitoring' => 'required|string',
                'formB.advokasiKolaborasi' => 'required|string',
                'formB.terminasi' => 'required|string',
                'formB.tandaTanganPetugas.petugasCode' => 'required|string|max:50',
                'formB.tandaTanganPetugas.petugasName' => 'required|string|max:150',
            ],
            [
                'formB.formA_id.required' => 'Referensi Form A wajib dipilih.',
                'formB.tanggal.required' => 'Tanggal wajib diisi.',
                'formB.pelaksanaanMonitoring.required' => 'Pelaksanaan & Monitoring wajib diisi.',
                'formB.advokasiKolaborasi.required' => 'Advokasi / Kolaborasi wajib diisi.',
                'formB.terminasi.required' => 'Terminasi wajib diisi.',
                'formB.tandaTanganPetugas.petugasCode.required' => 'Kode petugas wajib diisi.',
                'formB.tandaTanganPetugas.petugasName.required' => 'Nama petugas wajib diisi.',
            ],
        );

        $key = $this->editingKeyB ?: (string) Str::uuid();

        try {
            $this->persistEntryB($key, true, 'Kunci (TTD)');
            $formA_id = $this->formB['formA_id'];
            $this->resetFormB();
            $this->formB['formA_id'] = $formA_id; // pertahankan referensi untuk entri berikutnya
            $this->editingKeyB = null;
            $this->viewOnlyB = false;
            $this->incrementVersion('modal-case-manager-ri');
            $this->dispatch('toast', type: 'success', message: 'Form B ditandatangani & terkunci.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal mengunci: ' . $e->getMessage());
        }
    }

    /** Batalkan TTD pada form (state form saja, sebelum finalize tersimpan). */
    public function hapusTtdA(): void
    {
        if ($this->isFormLocked || $this->viewOnlyA) {
            return;
        }
        $this->formA['tandaTanganPetugas'] = ['petugasCode' => '', 'petugasName' => '', 'jabatan' => 'MPP'];
    }

    public function hapusTtdB(): void
    {
        if ($this->isFormLocked || $this->viewOnlyB) {
            return;
        }
        $this->formB['tandaTanganPetugas'] = ['petugasCode' => '', 'petugasName' => '', 'jabatan' => 'MPP'];
    }

    /* ===============================
     | EDIT / LIHAT entri
     =============================== */
    private function hydrateFormAFromEntry(array $entry, string $key): void
    {
        $this->formA = [
            'formA_id' => $key,
            'tipeForm' => 'FormA',
            'tanggal' => $entry['tanggal'] ?? '',
            'indentifikasiKasus' => $entry['indentifikasiKasus'] ?? '',
            'assessment' => $entry['assessment'] ?? '',
            'perencanaan' => $entry['perencanaan'] ?? '',
            'tandaTanganPetugas' => [
                'petugasCode' => data_get($entry, 'tandaTanganPetugas.petugasCode', ''),
                'petugasName' => data_get($entry, 'tandaTanganPetugas.petugasName', ''),
                'jabatan' => data_get($entry, 'tandaTanganPetugas.jabatan', 'MPP'),
            ],
        ];
        $this->editingKeyA = $key;
        $this->resetValidation();
        $this->incrementVersion('modal-case-manager-ri');
    }

    private function hydrateFormBFromEntry(array $entry, string $key): void
    {
        $this->formB = [
            'formB_id' => $key,
            'tipeForm' => 'FormB',
            'formA_id' => $entry['formA_id'] ?? '',
            'tanggal' => $entry['tanggal'] ?? '',
            'pelaksanaanMonitoring' => $entry['pelaksanaanMonitoring'] ?? '',
            'advokasiKolaborasi' => $entry['advokasiKolaborasi'] ?? '',
            'terminasi' => $entry['terminasi'] ?? '',
            'tandaTanganPetugas' => [
                'petugasCode' => data_get($entry, 'tandaTanganPetugas.petugasCode', ''),
                'petugasName' => data_get($entry, 'tandaTanganPetugas.petugasName', ''),
                'jabatan' => data_get($entry, 'tandaTanganPetugas.jabatan', 'MPP'),
            ],
        ];
        $this->editingKeyB = $key;
        $this->resetValidation();
        $this->incrementVersion('modal-case-manager-ri');
    }

    public function editEntryA(string $id): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        $entry = collect($this->dataDaftarRi['formMPP']['formA'] ?? [])->firstWhere('formA_id', $id);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Entri Form A tidak ditemukan.');
            return;
        }
        if ($this->entryIsFinal($entry)) {
            $this->dispatch('toast', type: 'warning', message: 'Entri sudah terkunci, tidak dapat diedit.');
            return;
        }
        $this->viewOnlyA = false;
        $this->hydrateFormAFromEntry($entry, $id);
        $this->dispatch('toast', type: 'info', message: 'Draft Form A dimuat untuk dilanjutkan.');
    }

    public function viewEntryA(string $id): void
    {
        $entry = collect($this->dataDaftarRi['formMPP']['formA'] ?? [])->firstWhere('formA_id', $id);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Entri Form A tidak ditemukan.');
            return;
        }
        $this->viewOnlyA = true;
        $this->hydrateFormAFromEntry($entry, $id);
        $this->dispatch('toast', type: 'info', message: 'Menampilkan entri Form A terkunci (hanya lihat).');
    }

    public function editEntryB(string $id): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        $entry = collect($this->dataDaftarRi['formMPP']['formB'] ?? [])->firstWhere('formB_id', $id);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Entri Form B tidak ditemukan.');
            return;
        }
        if ($this->entryIsFinal($entry)) {
            $this->dispatch('toast', type: 'warning', message: 'Entri sudah terkunci, tidak dapat diedit.');
            return;
        }
        $this->viewOnlyB = false;
        $this->hydrateFormBFromEntry($entry, $id);
        $this->dispatch('toast', type: 'info', message: 'Draft Form B dimuat untuk dilanjutkan.');
    }

    public function viewEntryB(string $id): void
    {
        $entry = collect($this->dataDaftarRi['formMPP']['formB'] ?? [])->firstWhere('formB_id', $id);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Entri Form B tidak ditemukan.');
            return;
        }
        $this->viewOnlyB = true;
        $this->hydrateFormBFromEntry($entry, $id);
        $this->dispatch('toast', type: 'info', message: 'Menampilkan entri Form B terkunci (hanya lihat).');
    }

    /* ===============================
     | ENTRI BARU / BATAL EDIT
     =============================== */
    public function tambahFormA(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }
        $this->cancelEditA();
        $this->formA['tanggal'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    public function tambahFormB(string $formA_id = ''): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }
        $this->cancelEditB();
        if ($formA_id !== '') {
            $this->formB['formA_id'] = $formA_id;
        }
        $this->formB['tanggal'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    public function cancelEditA(): void
    {
        $this->resetFormA();
        $this->editingKeyA = null;
        $this->viewOnlyA = false;
        $this->resetValidation();
        $this->incrementVersion('modal-case-manager-ri');
    }

    public function cancelEditB(): void
    {
        $formA_id = $this->formB['formA_id'] ?? ''; // pertahankan referensi Form A
        $this->resetFormB();
        $this->formB['formA_id'] = $formA_id;
        $this->editingKeyB = null;
        $this->viewOnlyB = false;
        $this->resetValidation();
        $this->incrementVersion('modal-case-manager-ri');
    }

    // Tutup editor Form B sepenuhnya (buang referensi Form A juga) → editor tersembunyi lagi.
    // Form B kini "anak" Form A: editor hanya muncul saat + Form B / Lanjut Isi / Lihat.
    public function tutupFormB(): void
    {
        $this->resetFormB();
        $this->editingKeyB = null;
        $this->viewOnlyB = false;
        $this->resetValidation();
        $this->incrementVersion('modal-case-manager-ri');
    }

    /* ===============================
     | HAPUS entri (draft atau final)
     =============================== */
    public function hapusForm(string $tipe, string $id): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }

        try {
            DB::transaction(function () use ($tipe, $id) {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?: [];
                $list = $fresh['formMPP'][$tipe] ?? [];
                $deletedRow = collect($list)->firstWhere($tipe . '_id', $id);
                $fresh['formMPP'][$tipe] = array_values(array_filter($list, fn($e) => ($e[$tipe . '_id'] ?? null) !== $id));
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;

                $formLabel = $tipe === 'formA' ? 'Form A (Skrining MPP)' : 'Form B (Pelaksanaan MPP)';
                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus ' . $formLabel . ' — entri ' . ($deletedRow['tanggal'] ?? '-'), 'MR');
            });

            if ($tipe === 'formA' && $this->editingKeyA === $id) {
                $this->cancelEditA();
            }
            if ($tipe === 'formB' && $this->editingKeyB === $id) {
                $this->cancelEditB();
            }

            $this->incrementVersion('modal-case-manager-ri');
            $this->dispatch('toast', type: 'success', message: 'Entri dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | CETAK per-entri
     =============================== */
    public function cetakFormA(string $id)
    {
        $formA = collect($this->dataDaftarRi['formMPP']['formA'] ?? [])->firstWhere('formA_id', $id);
        if (!$formA) {
            $this->dispatch('toast', type: 'error', message: 'Data Form A tidak ditemukan.');
            return;
        }

        try {
            $identitasRs = DB::table('rsmst_identitases')->select('int_name', 'int_phone1', 'int_phone2', 'int_fax', 'int_address', 'int_city')->first();
            $dataPasien = $this->findDataMasterPasien($this->dataDaftarRi['regNo'] ?? '');
            $pdf = Pdf::loadView('livewire.cetak.cetak-form-a-print', [
                'identitasRs' => $identitasRs,
                'dataPasien' => $dataPasien,
                'dataDaftarRi' => $this->dataDaftarRi,
                'dataFormA' => $formA,
            ])->output();

            $this->dispatch('toast', type: 'success', message: 'Berhasil mencetak Form A.');
            return response()->streamDownload(fn() => print $pdf, 'form-a-' . $id . '.pdf');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cetak: ' . $e->getMessage());
        }
    }

    public function cetakFormB(string $id)
    {
        $formB = collect($this->dataDaftarRi['formMPP']['formB'] ?? [])->firstWhere('formB_id', $id);
        if (!$formB) {
            $this->dispatch('toast', type: 'error', message: 'Data Form B tidak ditemukan.');
            return;
        }

        try {
            $identitasRs = DB::table('rsmst_identitases')->select('int_name', 'int_phone1', 'int_phone2', 'int_fax', 'int_address', 'int_city')->first();
            $dataPasien = $this->findDataMasterPasien($this->dataDaftarRi['regNo'] ?? '');
            $pdf = Pdf::loadView('livewire.cetak.cetak-form-b-print', [
                'identitasRs' => $identitasRs,
                'dataPasien' => $dataPasien,
                'dataDaftarRi' => $this->dataDaftarRi,
                'dataFormB' => $formB,
            ])->output();

            $this->dispatch('toast', type: 'success', message: 'Berhasil mencetak Form B.');
            return response()->streamDownload(fn() => print $pdf, 'form-b-' . $id . '.pdf');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cetak: ' . $e->getMessage());
        }
    }

    /* ===============================
     | RESET
     =============================== */
    private function resetFormA(): void
    {
        $this->formA = [
            'formA_id' => '',
            'tipeForm' => 'FormA',
            'tanggal' => '',
            'indentifikasiKasus' => '',
            'assessment' => '',
            'perencanaan' => '',
            'tandaTanganPetugas' => ['petugasCode' => '', 'petugasName' => '', 'jabatan' => 'MPP'],
        ];
    }

    private function resetFormB(): void
    {
        $this->formB = [
            'formB_id' => '',
            'tipeForm' => 'FormB',
            'formA_id' => '',
            'tanggal' => '',
            'pelaksanaanMonitoring' => '',
            'advokasiKolaborasi' => '',
            'terminasi' => '',
            'tandaTanganPetugas' => ['petugasCode' => '', 'petugasName' => '', 'jabatan' => 'MPP'],
        ];
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->dataDaftarRi = [];
        $this->resetFormA();
        $this->resetFormB();
        $this->editingKeyA = null;
        $this->editingKeyB = null;
        $this->viewOnlyA = false;
        $this->viewOnlyB = false;
    }
};
?>

<div>
    {{-- ══ SUMMARY CARD (inline di tab) ══ --}}
    @php
        $mppCountA = count($dataDaftarRi['formMPP']['formA'] ?? []);
        $mppCountB = count($dataDaftarRi['formMPP']['formB'] ?? []);
    @endphp
    <div class="p-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex-1 space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">Case Manager (MPP)</h3>
                    <x-badge variant="{{ $mppCountA > 0 ? 'success' : 'warning' }}">Form A: {{ $mppCountA }}</x-badge>
                    <x-badge variant="{{ $mppCountB > 0 ? 'success' : 'warning' }}">Form B: {{ $mppCountB }}</x-badge>
                </div>
                <p class="text-base text-muted dark:text-gray-400">
                    Skrining awal &amp; pelaksanaan/monitoring oleh Manajer Pelayanan Pasien selama perawatan.
                </p>
            </div>
            <div class="flex shrink-0">
                <x-primary-button type="button" wire:click="openModal" wire:loading.attr="disabled"
                    wire:target="openModal" :disabled="$disabled || !$riHdrNo" class="gap-2">
                    <span wire:loading.remove wire:target="openModal" class="flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                        Buka Case Manager (MPP)
                    </span>
                    <span wire:loading wire:target="openModal" class="flex items-center gap-1.5">
                        <x-loading class="w-4 h-4" /> Memuat...
                    </span>
                </x-primary-button>
            </div>
        </div>
    </div>

    {{-- ══ MODAL FORM ══ --}}
    <x-modal name="rm-case-manager-ri-{{ $riHdrNo ?? 'init' }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal-case-manager-ri', [$riHdrNo ?? 'new']) }}">

            {{-- HEADER MODAL --}}
            <div class="flex items-center justify-between gap-4 px-6 py-4 border-b border-hairline bg-surface-soft dark:border-gray-700 shrink-0">
                <h2 class="text-xl font-semibold text-ink dark:text-gray-100">Case Manager (MPP)</h2>
                <div class="flex items-center gap-2">
                    @if ($mppCountA + $mppCountB > 0)
                        <x-badge variant="info">{{ $mppCountA + $mppCountB }} tersimpan</x-badge>
                    @endif
                    @if ($isFormLocked)
                        <x-badge variant="danger">Read Only</x-badge>
                    @endif
                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <span class="sr-only">Close</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 overflow-y-auto bg-surface-soft dark:bg-gray-950/20">
                <div class="max-w-full mx-auto space-y-4">

    @php
        $formRO_A = $isFormLocked || $viewOnlyA;
        $formRO_B = $isFormLocked || $viewOnlyB;
        $listFormA = $dataDaftarRi['formMPP']['formA'] ?? [];
        $listFormB = $dataDaftarRi['formMPP']['formB'] ?? [];
        // Peta label Form A (untuk referensi read-only di Form B)
        $formALabels = [];
        foreach ($listFormA as $fa) {
            $formALabels[$fa['formA_id'] ?? ''] = ($fa['tanggal'] ?? '-') . ' — ' . (data_get($fa, 'tandaTanganPetugas.petugasName') ?: 'Draft');
        }
        // Editor Form B hanya tampil saat sedang menambah/melanjutkan/melihat satu Form B
        // (dipicu tombol + Form B / Lanjut Isi / Lihat pada entri Form A) — Form B = anak Form A.
        $formBActive = $viewOnlyB || filled($editingKeyB) || filled($formB['formA_id'] ?? null);
    @endphp

    @if ($isFormLocked)
        <div class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium border rounded-lg text-amber-800 bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:border-amber-700 dark:text-amber-300">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
            </svg>
            Pasien sudah pulang — form dalam mode <strong>read-only</strong>.
        </div>
    @endif

    {{-- ══════════════════════════ SECTION A — SKRINING AWAL MPP ══════════════════════════ --}}
    <x-border-form title="Form A — Skrining Awal MPP" align="start" bgcolor="bg-surface-soft">

        {{-- Banner status per-section A --}}
        @if ($viewOnlyA)
            <div class="flex items-center gap-2 px-4 py-2.5 mb-3 text-sm font-medium text-sky-700 bg-sky-50 border border-sky-200 rounded-lg dark:bg-sky-900/20 dark:border-sky-600 dark:text-sky-300">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                Menampilkan entri Form A terkunci (hanya lihat) — klik <strong>Selesai Melihat</strong> untuk kembali ke entri baru.
            </div>
        @elseif ($editingKeyA && !$isFormLocked)
            <div class="flex items-center gap-2 px-4 py-2.5 mb-3 text-sm font-medium text-brand-green bg-brand-lime/10 border border-brand-lime/40 rounded-lg dark:text-brand-lime dark:bg-brand-lime/5">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Sedang melanjutkan draft Form A — <strong>Simpan Perubahan</strong> menyimpan ke entri ini; klik <strong>Entri Baru</strong> untuk menambah catatan lain.
            </div>
        @endif

        <fieldset @disabled($formRO_A) class="space-y-4">
            {{-- Display Pasien --}}
            <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                wire:key="cm-fa-display-pasien-{{ $riHdrNo ?? 'new' }}" />

            <div class="flex items-end gap-3">
                <div class="flex-1">
                    <x-input-label value="Tanggal *" />
                    <x-text-input wire:model="formA.tanggal" class="w-full mt-1 font-mono" readonly
                        :error="$errors->has('formA.tanggal')" placeholder="dd/mm/yyyy HH:mm:ss" />
                    <x-input-error :messages="$errors->get('formA.tanggal')" class="mt-1" />
                </div>
                @if (!$formRO_A)
                    <x-now-button wire:click="setTanggalFormA" />
                @endif
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                @foreach ([['key' => 'indentifikasiKasus', 'label' => 'Identifikasi Kasus'], ['key' => 'assessment', 'label' => 'Assessment'], ['key' => 'perencanaan', 'label' => 'Perencanaan']] as $field)
                    <div>
                        <x-input-label value="{{ $field['label'] }} *" />
                        <x-textarea wire:model="formA.{{ $field['key'] }}" :error="$errors->has('formA.' . $field['key'])" class="w-full mt-1" rows="4"
                            placeholder="{{ $field['label'] }}..." />
                        <x-input-error :messages="$errors->get('formA.' . $field['key'])" class="mt-1" />
                    </div>
                @endforeach
            </div>

            {{-- ══ TTD PETUGAS & KUNCI (Form A) ══ --}}
            <x-signature.ttd-petugas :ttd="$formA['tandaTanganPetugas']['petugasName'] ?? ''"
                :code="$formA['tandaTanganPetugas']['petugasCode'] ?? ''" :date="$formA['tanggal'] ?? ''"
                :locked="$formRO_A" :allowClear="false" sign="kunciFormA" clear="hapusTtdA"
                title="Tanda Tangan Petugas (MPP)" nameLabel="Petugas (MPP)" dateLabel="Waktu / Tanggal"
                signLabel="TTD Petugas &amp; Kunci" />
        </fieldset>

        @if (!$formRO_A)
            <p class="mt-2 mb-3 text-xs text-center text-muted">Menandatangani = mengunci entri Form A ini (tidak bisa diubah lagi).</p>
        @endif

        {{-- Footer aksi Section A --}}
        <div class="flex flex-wrap items-center justify-end gap-2 mt-3">
            @if ($viewOnlyA)
                <x-primary-button wire:click.prevent="cancelEditA" wire:target="cancelEditA" wire:loading.attr="disabled"
                    class="gap-1.5">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Selesai Melihat
                </x-primary-button>
            @elseif (!$isFormLocked)
                @if ($editingKeyA)
                    <x-outline-button wire:click.prevent="tambahFormA" wire:target="tambahFormA" wire:loading.attr="disabled"
                        class="gap-1.5" title="Kosongkan form untuk menambah catatan lain — entri tersimpan tidak berubah">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Entri Baru
                    </x-outline-button>
                @endif
                <x-primary-button wire:click.prevent="saveDraftA" wire:loading.attr="disabled" wire:target="saveDraftA"
                    class="gap-2 min-w-[160px] justify-center">
                    <span wire:loading.remove wire:target="saveDraftA" class="flex items-center gap-1.5">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 21v-8H7v8M7 3v5h8M5 3h11l4 4v12a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z" />
                        </svg>
                        {{ $editingKeyA ? 'Simpan Perubahan' : 'Simpan Draft' }}
                    </span>
                    <span wire:loading wire:target="saveDraftA"><x-loading class="w-4 h-4" /> Menyimpan...</span>
                </x-primary-button>
            @endif
        </div>

        {{-- ── TABEL EXPANDABLE Form A ── --}}
        <div class="mt-4">
            @if (count($listFormA))
                <span class="block mb-2 text-xs italic text-muted-soft">Klik baris untuk lihat detail lengkap</span>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm border border-hairline rounded-lg dark:border-gray-700">
                        <thead class="bg-surface-soft dark:bg-gray-800">
                            <tr class="text-left text-sm font-semibold tracking-wide uppercase text-muted dark:text-gray-300">
                                <th class="w-8 px-2 py-3 border-b"></th>
                                <th class="px-4 py-3 border-b">Tanggal</th>
                                <th class="px-4 py-3 border-b">Identifikasi Kasus</th>
                                <th class="px-4 py-3 border-b">Petugas (TTD)</th>
                                <th class="px-4 py-3 text-center border-b">Status</th>
                                <th class="px-4 py-3 text-center border-b">Aksi</th>
                            </tr>
                        </thead>
                        @foreach (array_reverse($listFormA) as $entry)
                            @php
                                $isFinal = $this->entryIsFinal($entry);
                                $rowKey = $entry['formA_id'] ?? '';
                                $petugas = data_get($entry, 'tandaTanganPetugas.petugasName');
                                // Form B milik Form A ini (tampil nested di detail)
                                $relatedFormB = collect($listFormB)->where('formA_id', $rowKey)->values();
                            @endphp
                            <tbody x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }" class="border-b border-hairline dark:border-gray-700">
                                <tr @click="open = !open"
                                    class="cursor-pointer hover:bg-surface-soft dark:hover:bg-gray-800 {{ $editingKeyA && $editingKeyA === $rowKey ? 'bg-brand-lime/10 dark:bg-brand-lime/5' : '' }}">
                                    <td class="px-2 py-3 text-center align-middle">
                                        <svg class="w-4 h-4 mx-auto transition-transform text-muted" :class="{ 'rotate-90': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </td>
                                    <td class="px-4 py-3 font-semibold align-middle text-ink dark:text-gray-100 font-mono">{{ $entry['tanggal'] ?: '-' }}</td>
                                    <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">{{ \Illuminate\Support\Str::limit($entry['indentifikasiKasus'] ?? '', 48) ?: '-' }}</td>
                                    <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">
                                        @if (!empty($petugas))
                                            <span class="font-medium text-ink dark:text-gray-200">{{ $petugas }}</span>
                                        @else
                                            <x-badge variant="danger">Belum TTD</x-badge>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center align-middle">
                                        @if ($isFinal)
                                            <x-badge variant="info">Terkunci</x-badge>
                                        @else
                                            <x-badge variant="warning">Draft</x-badge>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center align-middle" @click.stop>
                                        <div class="flex flex-wrap items-center justify-center gap-2">
                                            @if (!$isFinal && !$isFormLocked)
                                                <x-primary-button type="button" wire:click="editEntryA('{{ $rowKey }}')" wire:loading.attr="disabled" wire:target="editEntryA('{{ $rowKey }}')" class="gap-1.5" title="Lanjutkan mengisi draft ini">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                    Lanjut Isi
                                                </x-primary-button>
                                            @endif
                                            @if ($isFinal)
                                                <x-secondary-button type="button" wire:click="viewEntryA('{{ $rowKey }}')" wire:loading.attr="disabled" wire:target="viewEntryA('{{ $rowKey }}')" class="gap-1.5" title="Lihat detail (read-only)">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                    </svg>
                                                    Lihat
                                                </x-secondary-button>
                                            @endif
                                            <x-primary-button wire:click="cetakFormA('{{ $rowKey }}')" type="button" wire:loading.attr="disabled" wire:target="cetakFormA('{{ $rowKey }}')" title="Cetak">
                                                <span wire:loading.remove wire:target="cetakFormA('{{ $rowKey }}')" class="flex items-center gap-1">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                    </svg>
                                                    Cetak
                                                </span>
                                                <span wire:loading wire:target="cetakFormA('{{ $rowKey }}')" class="flex items-center gap-1"><x-loading /> ...</span>
                                            </x-primary-button>
                                            @if (!$isFormLocked)
                                                <x-outline-button type="button" wire:click.prevent="hapusForm('formA','{{ $rowKey }}')" wire:confirm="Hapus Form A ini?" wire:loading.attr="disabled"
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
                                    <td colspan="6" class="px-4 py-4 bg-surface-soft/60 dark:bg-gray-950/30">
                                        <dl class="grid grid-cols-1 gap-x-8 gap-y-3 md:grid-cols-2">
                                            <div>
                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Tanggal</dt>
                                                <dd class="mt-0.5 font-mono text-ink dark:text-gray-200">{{ $entry['tanggal'] ?: '-' }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Petugas (TTD)</dt>
                                                <dd class="mt-0.5">
                                                    @if (!empty($petugas))
                                                        <span class="text-ink dark:text-gray-200">{{ $petugas }}</span>
                                                        <span class="text-sm text-muted-soft">— {{ data_get($entry, 'tandaTanganPetugas.jabatan', 'MPP') }}</span>
                                                    @else
                                                        <x-badge variant="danger">Belum TTD</x-badge>
                                                    @endif
                                                </dd>
                                            </div>
                                            <div class="md:col-span-2">
                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Identifikasi Kasus</dt>
                                                <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['indentifikasiKasus'] ?: '-' }}</dd>
                                            </div>
                                            <div class="md:col-span-2">
                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Assessment</dt>
                                                <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['assessment'] ?: '-' }}</dd>
                                            </div>
                                            <div class="md:col-span-2">
                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Perencanaan</dt>
                                                <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['perencanaan'] ?: '-' }}</dd>
                                            </div>
                                        </dl>

                                        {{-- ── FORM B (Tindak Lanjut) — nested di bawah Form A induk ── --}}
                                        <div class="pt-4 mt-4 border-t border-hairline dark:border-gray-700">
                                            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                                                <span class="text-xs font-semibold tracking-wide uppercase text-muted-soft">
                                                    Form B — Pelaksanaan / Monitoring ({{ $relatedFormB->count() }})
                                                </span>
                                                @if (!$isFormLocked)
                                                    <x-info-button type="button" wire:click="tambahFormB('{{ $rowKey }}')"
                                                        title="Tambah Form B untuk Form A ini">+ Form B</x-info-button>
                                                @endif
                                            </div>

                                            @if ($relatedFormB->count())
                                                <div class="space-y-2">
                                                    @foreach ($relatedFormB as $fb)
                                                        @php
                                                            $fbKey = $fb['formB_id'] ?? '';
                                                            $fbFinal = $this->entryIsFinal($fb);
                                                            $fbPetugas = data_get($fb, 'tandaTanganPetugas.petugasName');
                                                        @endphp
                                                        <div class="p-3 border rounded-lg bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700 {{ $editingKeyB && $editingKeyB === $fbKey ? 'ring-1 ring-brand-lime/40' : '' }}">
                                                            <div class="flex flex-wrap items-start justify-between gap-2">
                                                                <div class="min-w-0">
                                                                    <div class="flex items-center gap-2">
                                                                        <span class="font-mono text-sm font-semibold text-ink dark:text-gray-100">{{ $fb['tanggal'] ?: '-' }}</span>
                                                                        @if ($fbFinal)
                                                                            <x-badge variant="info">Terkunci</x-badge>
                                                                        @else
                                                                            <x-badge variant="warning">Draft</x-badge>
                                                                        @endif
                                                                    </div>
                                                                    <p class="mt-1 text-sm text-muted dark:text-gray-400">{{ \Illuminate\Support\Str::limit($fb['pelaksanaanMonitoring'] ?? '', 90) ?: '-' }}</p>
                                                                    <p class="mt-0.5 text-xs text-muted-soft">
                                                                        @if (!empty($fbPetugas)) TTD: {{ $fbPetugas }} @else <span class="text-red-600 dark:text-red-400">Belum TTD</span> @endif
                                                                    </p>
                                                                </div>
                                                                <div class="flex flex-wrap items-center justify-end gap-1.5">
                                                                    @if (!$fbFinal && !$isFormLocked)
                                                                        <x-primary-button type="button" wire:click="editEntryB('{{ $fbKey }}')" class="!px-2.5 !py-1 gap-1" title="Lanjutkan mengisi draft ini">
                                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                                                            Lanjut Isi
                                                                        </x-primary-button>
                                                                    @endif
                                                                    @if ($fbFinal)
                                                                        <x-secondary-button type="button" wire:click="viewEntryB('{{ $fbKey }}')" class="!px-2.5 !py-1 gap-1" title="Lihat detail (read-only)">
                                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                                                            Lihat
                                                                        </x-secondary-button>
                                                                    @endif
                                                                    <x-primary-button type="button" wire:click="cetakFormB('{{ $fbKey }}')" wire:loading.attr="disabled" wire:target="cetakFormB('{{ $fbKey }}')" class="!px-2.5 !py-1 gap-1" title="Cetak Form B">
                                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                                                                    </x-primary-button>
                                                                    @if (!$isFormLocked)
                                                                        <x-outline-button type="button" wire:click.prevent="hapusForm('formB','{{ $fbKey }}')" wire:confirm="Hapus Form B ini?"
                                                                            class="!px-2.5 !py-1 !text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30" title="Hapus Form B">
                                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                                        </x-outline-button>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @else
                                                <p class="text-sm text-muted-soft">Belum ada Form B untuk entri ini.</p>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        @endforeach
                    </table>
                </div>
            @else
                <p class="text-sm text-muted dark:text-gray-400">Belum ada entri Form A tersimpan.</p>
            @endif
        </div>
    </x-border-form>

    {{-- ══════ EDITOR FORM B — muncul saat + Form B / Lanjut Isi / Lihat pada entri Form A (Form B = anak Form A) ══════ --}}
    @if ($formBActive)
    <x-border-form title="Form B — Pelaksanaan, Monitoring, Advokasi, Terminasi" align="start" bgcolor="bg-surface-soft">

        {{-- Banner status per-section B --}}
        @if ($viewOnlyB)
            <div class="flex items-center gap-2 px-4 py-2.5 mb-3 text-sm font-medium text-sky-700 bg-sky-50 border border-sky-200 rounded-lg dark:bg-sky-900/20 dark:border-sky-600 dark:text-sky-300">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                Menampilkan entri Form B terkunci (hanya lihat) — klik <strong>Selesai Melihat</strong> untuk kembali ke entri baru.
            </div>
        @elseif ($editingKeyB && !$isFormLocked)
            <div class="flex items-center gap-2 px-4 py-2.5 mb-3 text-sm font-medium text-brand-green bg-brand-lime/10 border border-brand-lime/40 rounded-lg dark:text-brand-lime dark:bg-brand-lime/5">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Sedang melanjutkan draft Form B — <strong>Simpan Perubahan</strong> menyimpan ke entri ini; klik <strong>Entri Baru</strong> untuk menambah catatan lain.
            </div>
        @endif

        <fieldset @disabled($formRO_B) class="space-y-4">
            <div class="flex items-end gap-3">
                <div class="flex-1">
                    <x-input-label value="Tanggal *" />
                    <x-text-input wire:model="formB.tanggal" class="w-full mt-1 font-mono" readonly
                        :error="$errors->has('formB.tanggal')" placeholder="dd/mm/yyyy HH:mm:ss" />
                    <x-input-error :messages="$errors->get('formB.tanggal')" class="mt-1" />
                </div>
                @if (!$formRO_B)
                    <x-now-button wire:click="setTanggalFormB" />
                @endif
            </div>

            {{-- Referensi Form A (read-only) — otomatis dari entri Form A saat klik "+ Form B" --}}
            <div>
                <x-input-label value="Referensi Form A" />
                <div class="w-full px-3 py-2 mt-1 text-sm border rounded-lg bg-surface-soft border-hairline text-ink dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200">
                    {{ $formALabels[$formB['formA_id'] ?? ''] ?? '(Form A tidak ditemukan)' }}
                </div>
                <x-input-error :messages="$errors->get('formB.formA_id')" class="mt-1" />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                @foreach ([['key' => 'pelaksanaanMonitoring', 'label' => 'Pelaksanaan & Monitoring'], ['key' => 'advokasiKolaborasi', 'label' => 'Advokasi / Kolaborasi'], ['key' => 'terminasi', 'label' => 'Terminasi']] as $field)
                    <div>
                        {{-- :value (bound) — label "Pelaksanaan & Monitoring" ber-&, kalau lewat value="{{ }}"
                             akan ter-escape dua kali & tampil "&amp;" di layar --}}
                        <x-input-label :value="$field['label'] . ' *'" />
                        <x-textarea wire:model="formB.{{ $field['key'] }}" :error="$errors->has('formB.' . $field['key'])" class="w-full mt-1" rows="4"
                            placeholder="{{ $field['label'] }}..." />
                        <x-input-error :messages="$errors->get('formB.' . $field['key'])" class="mt-1" />
                    </div>
                @endforeach
            </div>

            {{-- ══ TTD PETUGAS & KUNCI (Form B) ══ --}}
            <x-signature.ttd-petugas :ttd="$formB['tandaTanganPetugas']['petugasName'] ?? ''"
                :code="$formB['tandaTanganPetugas']['petugasCode'] ?? ''" :date="$formB['tanggal'] ?? ''"
                :locked="$formRO_B" :allowClear="false" sign="kunciFormB" clear="hapusTtdB"
                title="Tanda Tangan Petugas (MPP)" nameLabel="Petugas (MPP)" dateLabel="Waktu / Tanggal"
                signLabel="TTD Petugas &amp; Kunci" />
        </fieldset>

        @if (!$formRO_B)
            <p class="mt-2 mb-3 text-xs text-center text-muted">Menandatangani = mengunci entri Form B ini (tidak bisa diubah lagi).</p>
        @endif

        {{-- Footer aksi Editor Form B --}}
        <div class="flex flex-wrap items-center justify-end gap-2 mt-3">
            @if ($viewOnlyB)
                <x-primary-button wire:click.prevent="tutupFormB" wire:target="tutupFormB" wire:loading.attr="disabled"
                    class="gap-1.5">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Selesai Melihat
                </x-primary-button>
            @elseif (!$isFormLocked)
                <x-outline-button wire:click.prevent="tutupFormB" wire:target="tutupFormB" wire:loading.attr="disabled"
                    class="gap-1.5" title="Tutup editor Form B tanpa menyimpan">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    Tutup
                </x-outline-button>
                @if ($editingKeyB)
                    <x-outline-button wire:click.prevent="tambahFormB" wire:target="tambahFormB" wire:loading.attr="disabled"
                        class="gap-1.5" title="Kosongkan form untuk menambah catatan lain — entri tersimpan tidak berubah">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Entri Baru
                    </x-outline-button>
                @endif
                <x-primary-button wire:click.prevent="saveDraftB" wire:loading.attr="disabled" wire:target="saveDraftB"
                    class="gap-2 min-w-[160px] justify-center">
                    <span wire:loading.remove wire:target="saveDraftB" class="flex items-center gap-1.5">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 21v-8H7v8M7 3v5h8M5 3h11l4 4v12a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z" />
                        </svg>
                        {{ $editingKeyB ? 'Simpan Perubahan' : 'Simpan Draft' }}
                    </span>
                    <span wire:loading wire:target="saveDraftB"><x-loading class="w-4 h-4" /> Menyimpan...</span>
                </x-primary-button>
            @endif
        </div>

    </x-border-form>
    @endif

                </div>
            </div>
        </div>
    </x-modal>
</div>
