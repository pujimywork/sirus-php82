<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/edukasi-terintegrasi-ri/rm-edukasi-terintegrasi-ri-actions.blade.php

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
    public ?string $regNo = null;
    public bool $disabled = false;
    public array $dataDaftarRi = [];

    // Signature dari <x-signature.signature-pad /> (TTD gambar pasien/keluarga)
    public string $sasaranEdukasiSignature = '';

    public array $form = [];

    // Kunci entri yang sedang diedit (= id entri). null = membuat entri baru.
    public ?string $editingKey = null;

    // true = entri terkunci ditampilkan di form dalam mode read-only.
    public bool $viewOnly = false;

    // Static option lists (key => label) untuk render checkbox / radio
    public array $tujuanList = [
        'penyakit'        => 'Pemahaman penyakit/diagnosis',
        'obat'            => 'Penggunaan obat yang aman',
        'nutrisi'         => 'Nutrisi & diet',
        'aktivitas'       => 'Aktivitas & latihan',
        'perawatanRumah'  => 'Perawatan di rumah',
        'pencegahan'      => 'Pencegahan komplikasi',
        'lainnya'         => 'Lainnya',
    ];

    public array $kebutuhanList = [
        'penyakitHasil' => 'Penjelasan penyakit & hasil pemeriksaan',
        'prosedur'      => 'Prosedur / tindakan medis',
        'rencanaAsuhan' => 'Rencana asuhan & tindak lanjut',
        'obatEfek'      => 'Penggunaan obat & efek samping',
        'cuciTangan'    => 'Cuci tangan & pencegahan infeksi',
        'alatRumah'     => 'Penggunaan alat medis di rumah',
        'warningSign'   => 'Tanda bahaya yang perlu diwaspadai',
        'lainnya'       => 'Lainnya',
    ];

    public array $metodeList = [
        'lisan'        => 'Penjelasan lisan',
        'demonstrasi'  => 'Demonstrasi / praktik langsung',
        'leaflet'      => 'Leaflet / brosur',
        'video'        => 'Video edukasi',
        'poster'       => 'Poster / peraga',
        'lainnya'      => 'Lainnya',
    ];

    public array $prefList = [
        'lisan'        => 'Lisan',
        'tulisan'      => 'Tulisan',
        'demonstrasi'  => 'Demonstrasi',
        'video'        => 'Video',
        'poster'       => 'Poster',
        'lainnya'      => 'Lainnya',
    ];

    public array $hasilList = [
        'paham'             => 'Pasien/keluarga memahami informasi',
        'mampuMengulang'    => 'Dapat mengulang kembali informasi',
        'tunjukkanSkill'    => 'Menunjukkan keterampilan yang diajarkan',
        'sesuaiNilai'       => 'Edukasi sesuai nilai & keyakinan pasien',
        'perluEdukasiUlang' => 'Diperlukan edukasi ulang',
    ];

    public array $rujukList = [
        'dietisien'    => 'Dietisien',
        'farmasi'      => 'Farmasi',
        'rehabilitasi' => 'Rehabilitasi',
        'psikologi'    => 'Psikologi',
        'lainnya'      => 'Lainnya',
    ];

    public array $hubunganOptions = [
        'pasien'     => 'Pasien Sendiri',
        'suami'      => 'Suami',
        'istri'      => 'Istri',
        'ayah'       => 'Ayah',
        'ibu'        => 'Ibu',
        'anak'       => 'Anak',
        'saudara'    => 'Saudara',
        'wali_hukum' => 'Wali Hukum',
        'lainnya'    => 'Lainnya',
    ];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-edukasi-terintegrasi-ri'];

    public function mount(?string $riHdrNo = null, bool $disabled = false): void
    {
        $this->riHdrNo  = $riHdrNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-edukasi-terintegrasi-ri']);

        $this->form = $this->defaultForm();
        $this->prefillHeader();

        if ($this->riHdrNo) {
            $data = $this->findDataRI($this->riHdrNo);
            if ($data) {
                $this->dataDaftarRi = $data;
                $this->regNo = $data['regNo'] ?? null;
                $this->dataDaftarRi['edukasiPasienTerintegrasi'] ??= [];
                $this->form['ttd']['pasienKeluargaNama'] = $data['regName'] ?? '';
                $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $disabled;
            }
        }
    }

    public function openModal(): void
    {
        if (!$this->riHdrNo || $this->disabled) {
            return;
        }

        $data = $this->findDataRI($this->riHdrNo);
        if ($data) {
            $this->dataDaftarRi = $data;
            $this->regNo = $data['regNo'] ?? $this->regNo;
            $this->dataDaftarRi['edukasiPasienTerintegrasi'] ??= [];
            $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $this->disabled;
        }

        $this->editingKey = null;
        $this->viewOnly = false;
        $this->resetFormEdukasi();

        $this->dispatch('open-modal', name: "rm-edukasi-terintegrasi-ri-{$this->riHdrNo}");
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: "rm-edukasi-terintegrasi-ri-{$this->riHdrNo}");
    }

    private function defaultForm(): array
    {
        return [
            'tglEdukasi'       => '',
            'pemberiInformasi' => ['petugasCode' => '', 'petugasName' => ''],

            'tujuan'      => ['opsi' => [], 'lainnya' => ''],

            'evaluasiAwal' => [
                'literasi'              => null,
                'bahasaAtauPendidikan'  => '',
                'hambatanEmosional'         => ['ada' => null, 'keterangan' => ''],
                'keterbatasanFisikKognitif' => ['ada' => null, 'keterangan' => ''],
                'nilaiKeyakinanBudaya'      => ['ada' => null, 'deskripsi' => ''],
                'preferensiInformasi'       => ['opsi' => [], 'lainnya' => ''],
            ],

            'kebutuhan'   => ['opsi' => [], 'lainnya' => ''],
            'metodeMedia' => ['opsi' => [], 'lainnya' => ''],

            'hasil' => [
                'paham'             => ['ya' => null, 'keterangan' => ''],
                'mampuMengulang'    => ['ya' => null, 'keterangan' => ''],
                'tunjukkanSkill'    => ['ya' => null, 'keterangan' => ''],
                'sesuaiNilai'       => ['ya' => null, 'keterangan' => ''],
                'perluEdukasiUlang' => ['ya' => null, 'keterangan' => ''],
            ],

            'tindakLanjut' => [
                'edukasiLanjutanTanggal' => '',
                'dirujukKe'              => [],
                'tidakPerluTL'           => false,
            ],

            'ttd' => [
                'pasienKeluargaNama' => '',
                'pasienKeluargaHubungan' => 'pasien',
                'pasienKeluargaTTD'  => '',
            ],
        ];
    }

    private function prefillHeader(): void
    {
        $this->form['pemberiInformasi']['petugasName'] = auth()->user()->myuser_name ?? '';
        $this->form['pemberiInformasi']['petugasCode'] = auth()->user()->myuser_code ?? '';
        if (empty($this->form['tglEdukasi'])) {
            $this->form['tglEdukasi'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        }
    }

    public function setTglEdukasi(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->form['tglEdukasi'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    public function setEdukasiLanjutanToday(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->form['tindakLanjut']['edukasiLanjutanTanggal']
            = Carbon::now(config('app.timezone'))->format('d/m/Y');
    }

    public function setSasaranSignature(string $dataUrl): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->sasaranEdukasiSignature = $dataUrl;
        $this->incrementVersion('modal-edukasi-terintegrasi-ri');
    }

    public function clearSasaranSignature(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->sasaranEdukasiSignature = '';
        $this->form['ttd']['pasienKeluargaTTD'] = '';
        $this->incrementVersion('modal-edukasi-terintegrasi-ri');
    }

    /* ===============================
     | HELPER — status & bentuk entri
     =============================== */
    // Entri dianggap FINAL/terkunci bila flag finalized true; entri lama (tanpa flag)
    // yang sudah ada TTD gambar pasien dianggap final (kompatibilitas data lama).
    public function entryIsFinal(array $e): bool
    {
        return array_key_exists('finalized', $e)
            ? (bool) $e['finalized']
            : !empty(data_get($e, 'form.ttd.pasienKeluargaTTD'));
    }

    // Susun array entri dari state form. Pertahankan created_at/created_by saat edit.
    private function buildEntry(string $id, bool $finalized): array
    {
        $this->normalizeBooleansOnForm();

        $form = $this->form;
        if (!empty($this->sasaranEdukasiSignature)) {
            $form['ttd']['pasienKeluargaTTD'] = $this->sasaranEdukasiSignature;
        }

        $existing = collect($this->dataDaftarRi['edukasiPasienTerintegrasi'] ?? [])->firstWhere('id', $id);
        $createdAt = $existing['created_at'] ?? Carbon::now(config('app.timezone'))->format('Y-m-d H:i:s');
        $createdBy = $existing['created_by'] ?? [
            'code' => auth()->user()->myuser_code ?? '',
            'name' => auth()->user()->myuser_name ?? '',
        ];

        return [
            'id'         => $id,
            'created_at' => $createdAt,
            'created_by' => $createdBy,
            'form'       => $form,
            'finalized'  => $finalized,
        ];
    }

    // Simpan entri (add/update by $id) dengan status $finalized. Dipakai draft & kunci.
    private function persistEntry(string $id, bool $finalized, string $logVerb): void
    {
        $entry = $this->buildEntry($id, $finalized);

        DB::transaction(function () use ($entry, $id, $logVerb) {
            $this->lockRIRow($this->riHdrNo);

            $fresh = $this->findDataRI($this->riHdrNo) ?? [];
            if (!isset($fresh['edukasiPasienTerintegrasi']) || !is_array($fresh['edukasiPasienTerintegrasi'])) {
                $fresh['edukasiPasienTerintegrasi'] = [];
            }

            $list = $fresh['edukasiPasienTerintegrasi'];
            $idx = collect($list)->search(fn($it) => ($it['id'] ?? null) === $id);
            if ($idx === false) {
                $list[] = $entry;
            } else {
                if ($this->entryIsFinal($list[$idx])) {
                    throw new \RuntimeException('Entri sudah terkunci, tidak dapat diubah.');
                }
                $list[$idx] = $entry;
            }
            $fresh['edukasiPasienTerintegrasi'] = array_values($list);

            $this->updateJsonRI((int) $this->riHdrNo, $fresh);
            $this->dataDaftarRi = $fresh;

            $this->appendAdminLogRI((int) $this->riHdrNo, $logVerb . ' Edukasi Terintegrasi — entri ' . ($entry['form']['tglEdukasi'] ?? '-'), 'MR');
        });
    }

    /* ===============================
     | VALIDATION RULES (dipakai finalize)
     =============================== */
    private function edukasiRules(): array
    {
        $rules = [
            // HEADER
            'form.tglEdukasi'                   => 'required|date_format:d/m/Y H:i:s',
            'form.pemberiInformasi.petugasCode' => 'required|string|max:50',
            'form.pemberiInformasi.petugasName' => 'required|string|max:250',

            // 1) Tujuan
            'form.tujuan.opsi'    => 'nullable|array',
            'form.tujuan.opsi.*'  => 'in:penyakit,obat,nutrisi,aktivitas,perawatanRumah,pencegahan,lainnya',
            'form.tujuan.lainnya' => 'nullable|string|max:200',

            // 2) Evaluasi Awal
            'form.evaluasiAwal.literasi'                              => 'nullable|in:Baik,Cukup,Kurang',
            'form.evaluasiAwal.bahasaAtauPendidikan'                  => 'nullable|string|max:200',
            'form.evaluasiAwal.hambatanEmosional.ada'                 => 'nullable|boolean',
            'form.evaluasiAwal.hambatanEmosional.keterangan'          => 'nullable|string|max:300',
            'form.evaluasiAwal.keterbatasanFisikKognitif.ada'         => 'nullable|boolean',
            'form.evaluasiAwal.keterbatasanFisikKognitif.keterangan'  => 'nullable|string|max:300',
            'form.evaluasiAwal.nilaiKeyakinanBudaya.ada'              => 'nullable|boolean',
            'form.evaluasiAwal.nilaiKeyakinanBudaya.deskripsi'        => 'nullable|string|max:500',
            'form.evaluasiAwal.preferensiInformasi.opsi'              => 'nullable|array',
            'form.evaluasiAwal.preferensiInformasi.opsi.*'            => 'in:lisan,tulisan,demonstrasi,video,poster,lainnya',
            'form.evaluasiAwal.preferensiInformasi.lainnya'           => 'nullable|string|max:200',

            // 3) Kebutuhan
            'form.kebutuhan.opsi'    => 'nullable|array',
            'form.kebutuhan.opsi.*'  => 'in:penyakitHasil,prosedur,rencanaAsuhan,obatEfek,cuciTangan,alatRumah,warningSign,lainnya',
            'form.kebutuhan.lainnya' => 'nullable|string|max:200',

            // 4) Metode & Media
            'form.metodeMedia.opsi'    => 'nullable|array',
            'form.metodeMedia.opsi.*'  => 'in:lisan,demonstrasi,leaflet,video,poster,lainnya',
            'form.metodeMedia.lainnya' => 'nullable|string|max:200',

            // 5) Hasil
            'form.hasil.*.ya'         => 'nullable|boolean',
            'form.hasil.*.keterangan' => 'nullable|string|max:300',

            // 6) Tindak Lanjut
            'form.tindakLanjut.edukasiLanjutanTanggal' => 'nullable|date_format:d/m/Y',
            'form.tindakLanjut.dirujukKe'              => 'nullable|array',
            'form.tindakLanjut.dirujukKe.*'            => 'string|max:50',
            'form.tindakLanjut.tidakPerluTL'           => 'boolean',

            // 7) TTD
            'form.ttd.pasienKeluargaNama'     => 'required|string|max:150',
            'form.ttd.pasienKeluargaHubungan' => 'required|string|max:50',
        ];

        // Conditional: "lainnya" wajib diisi kalau di-check
        if (in_array('lainnya', $this->form['tujuan']['opsi'] ?? [], true)) {
            $rules['form.tujuan.lainnya'] = 'required|string|max:200';
        }
        if (in_array('lainnya', $this->form['kebutuhan']['opsi'] ?? [], true)) {
            $rules['form.kebutuhan.lainnya'] = 'required|string|max:200';
        }
        if (in_array('lainnya', $this->form['metodeMedia']['opsi'] ?? [], true)) {
            $rules['form.metodeMedia.lainnya'] = 'required|string|max:200';
        }
        if (in_array('lainnya', $this->form['evaluasiAwal']['preferensiInformasi']['opsi'] ?? [], true)) {
            $rules['form.evaluasiAwal.preferensiInformasi.lainnya'] = 'required|string|max:200';
        }

        $attributes = [
            'form.tglEdukasi'                   => 'Tanggal edukasi',
            'form.pemberiInformasi.petugasCode' => 'Kode petugas',
            'form.pemberiInformasi.petugasName' => 'Nama petugas',
            'form.tujuan.lainnya'               => 'Tujuan (lainnya)',
            'form.kebutuhan.lainnya'            => 'Kebutuhan (lainnya)',
            'form.metodeMedia.lainnya'          => 'Metode/media (lainnya)',
            'form.evaluasiAwal.preferensiInformasi.lainnya' => 'Preferensi (lainnya)',
            'form.ttd.pasienKeluargaNama'       => 'Nama pasien/keluarga',
            'form.ttd.pasienKeluargaHubungan'   => 'Hubungan dengan pasien',
        ];

        $messages = [
            'required'    => ':attribute wajib diisi.',
            'string'      => ':attribute harus berupa teks.',
            'array'       => ':attribute harus berupa daftar.',
            'boolean'     => ':attribute harus bernilai ya/tidak.',
            'in'          => ':attribute berisi nilai yang tidak valid.',
            'max.string'  => ':attribute maksimal :max karakter.',
            'date_format' => 'Format :attribute tidak sesuai (harus :format).',
        ];

        return [$rules, $messages, $attributes];
    }

    /* ===============================
     | SIMPAN DRAFT (nyicil, tanpa validasi lengkap)
     =============================== */
    public function saveDraft(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }

        if (empty($this->form['tglEdukasi'])) {
            $this->form['tglEdukasi'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        }
        $this->form['pemberiInformasi']['petugasName'] = $this->form['pemberiInformasi']['petugasName'] ?: (auth()->user()->myuser_name ?? '');
        $this->form['pemberiInformasi']['petugasCode'] = $this->form['pemberiInformasi']['petugasCode'] ?: (auth()->user()->myuser_code ?? '');

        $id = $this->editingKey ?: (string) Str::uuid();

        try {
            $this->persistEntry($id, false, 'Simpan draft');
            $this->editingKey = $id; // lanjut edit entri yang sama, tidak buat duplikat
            $this->incrementVersion('modal-edukasi-terintegrasi-ri');
            $this->dispatch('toast', type: 'success', message: 'Draft tersimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan draft: ' . $e->getMessage());
        }
    }

    /* ===============================
     | FINALIZE (Simpan & Kunci) — validasi lengkap + TTD wajib
     =============================== */
    public function finalize(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }

        if (empty($this->form['tglEdukasi'])) {
            $this->form['tglEdukasi'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        }
        $this->form['pemberiInformasi']['petugasName'] = $this->form['pemberiInformasi']['petugasName'] ?: (auth()->user()->myuser_name ?? '');
        $this->form['pemberiInformasi']['petugasCode'] = $this->form['pemberiInformasi']['petugasCode'] ?: (auth()->user()->myuser_code ?? '');

        // TTD gambar pasien/keluarga wajib sebelum mengunci
        if (empty($this->sasaranEdukasiSignature) && empty(data_get($this->form, 'ttd.pasienKeluargaTTD'))) {
            $this->dispatch('toast', type: 'error', message: 'TTD pasien/keluarga wajib sebelum mengunci.');
            return;
        }
        if (!empty($this->sasaranEdukasiSignature)) {
            $this->form['ttd']['pasienKeluargaTTD'] = $this->sasaranEdukasiSignature;
        }

        $this->normalizeBooleansOnForm();

        [$rules, $messages, $attributes] = $this->edukasiRules();
        $this->validateWithToast($rules, $messages, $attributes);

        $id = $this->editingKey ?: (string) Str::uuid();

        try {
            $this->persistEntry($id, true, 'Kunci');
            $this->resetFormEdukasi();
            $this->editingKey = null;
            $this->viewOnly = false;
            $this->dispatch('toast', type: 'success', message: 'Edukasi tervalidasi & terkunci.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal mengunci: ' . $e->getMessage());
        }
    }

    /* ===============================
     | EDIT / LIHAT / BATAL
     =============================== */
    private function hydrateFormFromEntry(array $entri): void
    {
        // array_replace_recursive menjaga agar key nested yang hilang di data lama tetap ada
        $this->form = array_replace_recursive($this->defaultForm(), $entri['form'] ?? []);
        $this->sasaranEdukasiSignature = (string) data_get($entri, 'form.ttd.pasienKeluargaTTD', '');
        $this->editingKey = $entri['id'] ?? null;
        $this->resetValidation();
        $this->incrementVersion('modal-edukasi-terintegrasi-ri');
    }

    public function editEntry(string $id): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }
        $entri = collect($this->dataDaftarRi['edukasiPasienTerintegrasi'] ?? [])->firstWhere('id', $id);
        if (!$entri) {
            $this->dispatch('toast', type: 'error', message: 'Entri tidak ditemukan.');
            return;
        }
        if ($this->entryIsFinal($entri)) {
            $this->dispatch('toast', type: 'warning', message: 'Entri sudah terkunci, tidak dapat diedit.');
            return;
        }

        $this->viewOnly = false;
        $this->hydrateFormFromEntry($entri);
        $this->dispatch('toast', type: 'info', message: 'Draft dimuat untuk dilanjutkan.');
    }

    public function viewEntry(string $id): void
    {
        $entri = collect($this->dataDaftarRi['edukasiPasienTerintegrasi'] ?? [])->firstWhere('id', $id);
        if (!$entri) {
            $this->dispatch('toast', type: 'error', message: 'Entri tidak ditemukan.');
            return;
        }

        $this->viewOnly = true;
        $this->hydrateFormFromEntry($entri);
        $this->dispatch('toast', type: 'info', message: 'Menampilkan entri terkunci (hanya lihat).');
    }

    public function cancelEdit(): void
    {
        $this->resetFormEdukasi();
        $this->editingKey = null;
        $this->viewOnly = false;
    }

    public function removeEdukasiTerintegrasiById(string $id): void
    {
        if (!auth()->user()?->can('dokumen.hapus')) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berwenang menghapus entri.');
            return;
        }
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }

        try {
            DB::transaction(function () use ($id) {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                $list  = $fresh['edukasiPasienTerintegrasi'] ?? [];

                $deletedRow = collect($list)->firstWhere('id', $id);
                $newList = array_values(array_filter($list, fn($e) => ($e['id'] ?? null) !== $id));
                if (count($newList) === count($list)) {
                    throw new \RuntimeException('Data tidak ditemukan atau sudah dihapus.');
                }

                $fresh['edukasiPasienTerintegrasi'] = $newList;
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Edukasi Terintegrasi — entri ' . ($deletedRow['form']['tglEdukasi'] ?? '-'), 'MR');
            });

            // bila entri yang dihapus sedang dibuka di form, kosongkan form
            if ($this->editingKey === $id) {
                $this->cancelEdit();
            }
            $this->afterSave('Data edukasi berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    public function cetak(string $id)
    {
        $list = $this->dataDaftarRi['edukasiPasienTerintegrasi'] ?? [];
        $entry = collect($list)->firstWhere('id', $id);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Data edukasi tidak ditemukan.');
            return;
        }

        try {
            $identitasRs = DB::table('rsmst_identitases')->select('int_name', 'int_phone1', 'int_phone2', 'int_fax', 'int_address', 'int_city')->first();
            $pasienData = $this->findDataMasterPasien($this->regNo ?? '');
            $pasien = $pasienData['pasien'] ?? [];

            if (!empty($pasien['tglLahir'])) {
                try {
                    $pasien['thn'] = Carbon::createFromFormat('d/m/Y', $pasien['tglLahir'])->diff(Carbon::now(config('app.timezone')))->format('%y Thn, %m Bln %d Hr');
                } catch (\Throwable) {
                    $pasien['thn'] = '-';
                }
            }

            // TTD petugas pemberi edukasi (dari created_by.code -> users.myuser_ttd_image)
            $ttdPetugasPath = null;
            $petugasCode = $entry['form']['pemberiInformasi']['petugasCode'] ?? ($entry['created_by']['code'] ?? null);
            if ($petugasCode) {
                $ttdPath = DB::table('users')->where('myuser_code', $petugasCode)->value('myuser_ttd_image');
                if (!empty($ttdPath) && file_exists(public_path('storage/' . $ttdPath))) {
                    $ttdPetugasPath = public_path('storage/' . $ttdPath);
                }
            }

            $data = array_merge($pasien, [
                'dataRi' => $this->dataDaftarRi,
                'entry' => $entry,
                'identitasRs' => $identitasRs,
                'ttdPetugasPath' => $ttdPetugasPath,
                'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
            ]);

            set_time_limit(300);

            $pdf = Pdf::loadView('pages.components.modul-dokumen.r-i.edukasi-terintegrasi.cetak-edukasi-terintegrasi-ri-print', ['data' => $data])->setPaper('A4');

            $this->dispatch('toast', type: 'success', message: 'Berhasil mencetak Edukasi Terintegrasi.');
            return response()->streamDownload(fn() => print $pdf->output(), 'edukasi-terintegrasi-ri-' . ($pasien['regNo'] ?? $this->riHdrNo) . '.pdf');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cetak: ' . $e->getMessage());
        }
    }

    /**
     * Toggle membership di array multi-pilih (untuk x-toggle group).
     * $fullPath: path lengkap mulai dari property root, mis. "form.tujuan.opsi".
     */
    public function toggleArrayOpt(string $fullPath, string $opt): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $current = (array) data_get($this, $fullPath, []);
        if (in_array($opt, $current, true)) {
            $current = array_values(array_filter($current, fn($nilai) => $nilai !== $opt));
        } else {
            $current[] = $opt;
        }
        data_set($this, $fullPath, $current);
    }

    public function resetFormEdukasi(): void
    {
        $this->form = $this->defaultForm();
        $this->form['ttd']['pasienKeluargaNama'] = $this->dataDaftarRi['regName'] ?? '';
        $this->prefillHeader();
        $this->sasaranEdukasiSignature = '';
        $this->editingKey = null;
        $this->viewOnly = false;
        $this->resetValidation();
        $this->incrementVersion('modal-edukasi-terintegrasi-ri');
    }

    private function afterSave(string $msg): void
    {
        $this->incrementVersion('modal-edukasi-terintegrasi-ri');
        $this->dispatch('toast', type: 'success', message: $msg);
    }

    private function normalizeBooleansOnForm(): void
    {
        $formData = &$this->form;

        foreach (['hambatanEmosional', 'keterbatasanFisikKognitif', 'nilaiKeyakinanBudaya'] as $key) {
            if (array_key_exists('ada', $formData['evaluasiAwal'][$key] ?? [])) {
                $formData['evaluasiAwal'][$key]['ada'] = filter_var(
                    $formData['evaluasiAwal'][$key]['ada'],
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE
                );
            }
        }

        if (isset($formData['hasil'])) {
            foreach ($formData['hasil'] as &$hasilItem) {
                if (array_key_exists('ya', $hasilItem)) {
                    $hasilItem['ya'] = filter_var($hasilItem['ya'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                }
            }
            unset($hasilItem);
        }

        if (isset($formData['tindakLanjut']['tidakPerluTL'])) {
            $formData['tindakLanjut']['tidakPerluTL'] = (bool) $formData['tindakLanjut']['tidakPerluTL'];
        }
    }
};
?>

<div>
    {{-- RINGKASAN + TOMBOL (pola General Consent) --}}
    @php $eduTerCount = count($dataDaftarRi['edukasiPasienTerintegrasi'] ?? []); @endphp
    <div class="p-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex-1 space-y-2">
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">Edukasi Terintegrasi</h3>
                    @if ($eduTerCount > 0)
                        <x-badge variant="success">{{ $eduTerCount }} entri</x-badge>
                    @else
                        <x-badge variant="warning">Belum ada</x-badge>
                    @endif
                </div>
                <p class="text-base text-muted dark:text-gray-400">
                    Catatan edukasi terintegrasi antar-PPA (dokter, perawat, gizi, farmasi, dll.).
                </p>
            </div>
            <div class="flex shrink-0">
                <x-primary-button type="button" wire:click="openModal" wire:loading.attr="disabled"
                    wire:target="openModal" :disabled="!$riHdrNo" class="gap-2">
                    <span wire:loading.remove wire:target="openModal" class="flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                        Buka Edukasi Terintegrasi
                    </span>
                    <span wire:loading wire:target="openModal" class="flex items-center gap-1.5">
                        <x-loading class="w-4 h-4" /> Memuat...
                    </span>
                </x-primary-button>
            </div>
        </div>
    </div>

    {{-- MODAL FORM --}}
    <x-modal name="rm-edukasi-terintegrasi-ri-{{ $riHdrNo ?? 'init' }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]">
            {{-- HEADER MODAL --}}
            <div class="flex items-center justify-between gap-4 px-6 py-4 border-b border-hairline bg-surface-soft dark:border-gray-700">
                <div class="flex items-center gap-3">
                    <h2 class="text-xl font-semibold text-ink dark:text-gray-100">Edukasi Terintegrasi</h2>
                    @if ($eduTerCount > 0)
                        <x-badge variant="info">{{ $eduTerCount }} tersimpan</x-badge>
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

            {{-- Display Pasien (selaras General Consent) --}}
            <div class="px-4 pt-4">
                <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                    wire:key="edu-ter-ri-display-pasien-{{ $riHdrNo ?? 'init' }}" />
            </div>
            <div class="flex-1 p-4 sm:p-6 space-y-4"
                wire:key="{{ $this->renderKey('modal-edukasi-terintegrasi-ri', [$riHdrNo ?? 'new']) }}">

    @php $formRO = $isFormLocked || $viewOnly; @endphp

    @if ($isFormLocked)
        <div class="flex items-center gap-2 px-4 py-2.5 mb-2 rounded-lg
                    bg-amber-50 border border-amber-200 text-amber-800
                    dark:bg-amber-900/20 dark:border-amber-700 dark:text-amber-300 text-sm">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
            </svg>
            Pasien sudah pulang — form dalam mode <strong>read-only</strong>.
        </div>
    @endif

    @if ($viewOnly)
        <div class="flex items-center gap-2 px-4 py-2.5 mb-2 text-sm font-medium text-sky-700 bg-sky-50 border border-sky-200 rounded-lg dark:bg-sky-900/20 dark:border-sky-600 dark:text-sky-300">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
            Menampilkan entri terkunci (hanya lihat) — klik <strong>Selesai Melihat</strong> untuk kembali ke form entri baru.
        </div>
    @elseif ($editingKey && !$isFormLocked)
        <div class="flex items-center gap-2 px-4 py-2.5 mb-2 text-sm font-medium text-brand-green bg-brand-lime/10 border border-brand-lime/40 rounded-lg dark:text-brand-lime dark:bg-brand-lime/5">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            Sedang melanjutkan entri draft — <strong>Simpan Draft</strong> menyimpan ke entri ini; klik <strong>Entri Baru</strong> untuk menambah edukasi lain.
        </div>
    @endif

    {{-- ═══════════════ FORM ENTRY ═══════════════ --}}
    @if (!$isFormLocked)
        <x-border-form title="Formulir Edukasi Terintegrasi Pasien & Keluarga" align="start" bgcolor="bg-surface-soft">
            <fieldset @disabled($formRO)>
            <div class="mt-3 space-y-5">

                {{-- ─── HEADER: Waktu & Petugas ─── --}}
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div>
                        <x-input-label value="Tanggal Edukasi *" />
                        <div class="flex items-end gap-2 mt-1">
                            <x-text-input wire:model="form.tglEdukasi" class="flex-1 font-mono"
                                placeholder="dd/mm/yyyy hh:ii:ss" readonly
                                :error="$errors->has('form.tglEdukasi')" />
                            <x-now-button wire:click="setTglEdukasi" :disabled="$formRO" />
                        </div>
                        <x-input-error :messages="$errors->get('form.tglEdukasi')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label value="Nama Petugas (Pemberi Informasi) *" />
                        <x-text-input wire:model="form.pemberiInformasi.petugasName" class="w-full mt-1"
                            :error="$errors->has('form.pemberiInformasi.petugasName')" :disabled="$formRO" />
                        <x-input-error :messages="$errors->get('form.pemberiInformasi.petugasName')" class="mt-1" />
                    </div>
                </div>

                <hr class="border-hairline dark:border-gray-700">

                {{-- ─── 1) TUJUAN EDUKASI ─── --}}
                <div>
                    <h4 class="text-sm font-semibold text-ink dark:text-gray-100 mb-2">
                        1) Tujuan Edukasi <span class="text-xs font-normal text-muted">(boleh lebih dari satu)</span>
                    </h4>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 md:grid-cols-4">
                        @foreach ($tujuanList as $key => $label)
                            <div wire:key="tujuan-{{ $key }}">
                                <x-toggle
                                    :current="in_array($key, $form['tujuan']['opsi'] ?? []) ? '1' : '0'"
                                    trueValue="1" falseValue="0"
                                    wireClick="toggleArrayOpt('form.tujuan.opsi', '{{ $key }}')"
                                    :label="$label" :disabled="$formRO" />
                            </div>
                        @endforeach
                    </div>
                    @if (in_array('lainnya', $form['tujuan']['opsi'] ?? []))
                        <x-text-input wire:model.blur="form.tujuan.lainnya" class="w-full mt-2"
                            placeholder="Sebutkan tujuan lainnya" :disabled="$formRO"
                            :error="$errors->has('form.tujuan.lainnya')" />
                        <x-input-error :messages="$errors->get('form.tujuan.lainnya')" class="mt-1" />
                    @endif
                </div>

                <hr class="border-hairline dark:border-gray-700">

                {{-- ─── 2) EVALUASI AWAL & NILAI ─── --}}
                <div class="space-y-3">
                    <h4 class="text-sm font-semibold text-ink dark:text-gray-100">
                        2) Evaluasi Awal Kemampuan & Nilai
                    </h4>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="p-3 border border-hairline rounded-lg bg-canvas dark:bg-gray-800 dark:border-gray-700">
                            <x-input-label value="Kemampuan membaca / menulis" />
                            <div class="flex gap-2 mt-1">
                                @foreach (['Baik', 'Cukup', 'Kurang'] as $opt)
                                    <x-radio-button :label="$opt" :value="$opt" name="literasi"
                                        wire:model.live="form.evaluasiAwal.literasi" :disabled="$formRO" />
                                @endforeach
                            </div>
                        </div>
                        <div class="p-3 border border-hairline rounded-lg bg-canvas dark:bg-gray-800 dark:border-gray-700">
                            <x-input-label value="Bahasa yang digunakan / tingkat pendidikan" />
                            <x-text-input wire:model.blur="form.evaluasiAwal.bahasaAtauPendidikan" :error="$errors->has('form.evaluasiAwal.bahasaAtauPendidikan')"
                                class="w-full mt-1" placeholder="Contoh: Indonesia / SMA"
                                :disabled="$formRO" />
                        </div>
                        <div class="p-3 border border-hairline rounded-lg bg-canvas dark:bg-gray-800 dark:border-gray-700">
                            <x-input-label value="Hambatan emosional / motivasi" />
                            <div class="flex gap-2 mt-1">
                                <x-radio-button label="Ada" value="1" name="hambatanEmo"
                                    wire:model.live="form.evaluasiAwal.hambatanEmosional.ada"
                                    :disabled="$formRO" />
                                <x-radio-button label="Tidak ada" value="0" name="hambatanEmo"
                                    wire:model.live="form.evaluasiAwal.hambatanEmosional.ada"
                                    :disabled="$formRO" />
                            </div>
                            <x-text-input wire:model.blur="form.evaluasiAwal.hambatanEmosional.keterangan" :error="$errors->has('form.evaluasiAwal.hambatanEmosional.keterangan')"
                                class="w-full mt-2" placeholder="Keterangan jika ada hambatan"
                                :disabled="$formRO" />
                        </div>
                        <div class="p-3 border border-hairline rounded-lg bg-canvas dark:bg-gray-800 dark:border-gray-700">
                            <x-input-label value="Keterbatasan fisik / kognitif" />
                            <div class="flex gap-2 mt-1">
                                <x-radio-button label="Ada" value="1" name="keterbatasanFk"
                                    wire:model.live="form.evaluasiAwal.keterbatasanFisikKognitif.ada"
                                    :disabled="$formRO" />
                                <x-radio-button label="Tidak ada" value="0" name="keterbatasanFk"
                                    wire:model.live="form.evaluasiAwal.keterbatasanFisikKognitif.ada"
                                    :disabled="$formRO" />
                            </div>
                            <x-text-input wire:model.blur="form.evaluasiAwal.keterbatasanFisikKognitif.keterangan" :error="$errors->has('form.evaluasiAwal.keterbatasanFisikKognitif.keterangan')"
                                class="w-full mt-2" placeholder="Keterangan jika ada keterbatasan"
                                :disabled="$formRO" />
                        </div>
                        <div class="p-3 border border-hairline rounded-lg bg-canvas dark:bg-gray-800 dark:border-gray-700">
                            <x-input-label value="Nilai, keyakinan, dan budaya yang dianut" />
                            <div class="flex gap-2 mt-1">
                                <x-radio-button label="Ada" value="1" name="nilaiBudaya"
                                    wire:model.live="form.evaluasiAwal.nilaiKeyakinanBudaya.ada"
                                    :disabled="$formRO" />
                                <x-radio-button label="Tidak ada" value="0" name="nilaiBudaya"
                                    wire:model.live="form.evaluasiAwal.nilaiKeyakinanBudaya.ada"
                                    :disabled="$formRO" />
                            </div>
                            <x-textarea wire:model.blur="form.evaluasiAwal.nilaiKeyakinanBudaya.deskripsi" :error="$errors->has('form.evaluasiAwal.nilaiKeyakinanBudaya.deskripsi')"
                                class="w-full mt-2" rows="2"
                                placeholder="Jelaskan nilai/kepercayaan/budaya yang relevan"
                                :disabled="$formRO" />
                        </div>
                        <div class="p-3 border border-hairline rounded-lg bg-canvas dark:bg-gray-800 dark:border-gray-700">
                            <x-input-label value="Preferensi menerima informasi" />
                            <div class="flex flex-wrap gap-3 mt-1">
                                @foreach ($prefList as $key => $label)
                                    <div wire:key="pref-{{ $key }}">
                                        <x-toggle
                                            :current="in_array($key, $form['evaluasiAwal']['preferensiInformasi']['opsi'] ?? []) ? '1' : '0'"
                                            trueValue="1" falseValue="0"
                                            wireClick="toggleArrayOpt('form.evaluasiAwal.preferensiInformasi.opsi', '{{ $key }}')"
                                            :label="$label" :disabled="$formRO" />
                                    </div>
                                @endforeach
                            </div>
                            @if (in_array('lainnya', $form['evaluasiAwal']['preferensiInformasi']['opsi'] ?? []))
                                <x-text-input wire:model.blur="form.evaluasiAwal.preferensiInformasi.lainnya"
                                    class="w-full mt-2" placeholder="Sebutkan preferensi lainnya"
                                    :error="$errors->has('form.evaluasiAwal.preferensiInformasi.lainnya')"
                                    :disabled="$formRO" />
                                <x-input-error :messages="$errors->get('form.evaluasiAwal.preferensiInformasi.lainnya')" class="mt-1" />
                            @endif
                        </div>
                    </div>
                </div>

                <hr class="border-hairline dark:border-gray-700">

                {{-- ─── 3) KEBUTUHAN EDUKASI ─── --}}
                <div>
                    <h4 class="text-sm font-semibold text-ink dark:text-gray-100 mb-2">
                        3) Kebutuhan Edukasi <span class="text-xs font-normal text-muted">(boleh lebih dari satu)</span>
                    </h4>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 md:grid-cols-4">
                        @foreach ($kebutuhanList as $key => $label)
                            <div wire:key="need-{{ $key }}">
                                <x-toggle
                                    :current="in_array($key, $form['kebutuhan']['opsi'] ?? []) ? '1' : '0'"
                                    trueValue="1" falseValue="0"
                                    wireClick="toggleArrayOpt('form.kebutuhan.opsi', '{{ $key }}')"
                                    :label="$label" :disabled="$formRO" />
                            </div>
                        @endforeach
                    </div>
                    @if (in_array('lainnya', $form['kebutuhan']['opsi'] ?? []))
                        <x-text-input wire:model.blur="form.kebutuhan.lainnya" class="w-full mt-2"
                            placeholder="Sebutkan kebutuhan lainnya" :disabled="$formRO"
                            :error="$errors->has('form.kebutuhan.lainnya')" />
                        <x-input-error :messages="$errors->get('form.kebutuhan.lainnya')" class="mt-1" />
                    @endif
                </div>

                <hr class="border-hairline dark:border-gray-700">

                {{-- ─── 4) METODE & MEDIA ─── --}}
                <div>
                    <h4 class="text-sm font-semibold text-ink dark:text-gray-100 mb-2">
                        4) Metode & Media Edukasi
                    </h4>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 md:grid-cols-4">
                        @foreach ($metodeList as $key => $label)
                            <div wire:key="metode-{{ $key }}">
                                <x-toggle
                                    :current="in_array($key, $form['metodeMedia']['opsi'] ?? []) ? '1' : '0'"
                                    trueValue="1" falseValue="0"
                                    wireClick="toggleArrayOpt('form.metodeMedia.opsi', '{{ $key }}')"
                                    :label="$label" :disabled="$formRO" />
                            </div>
                        @endforeach
                    </div>
                    @if (in_array('lainnya', $form['metodeMedia']['opsi'] ?? []))
                        <x-text-input wire:model.blur="form.metodeMedia.lainnya" class="w-full mt-2"
                            placeholder="Sebutkan metode/media lainnya" :disabled="$formRO"
                            :error="$errors->has('form.metodeMedia.lainnya')" />
                        <x-input-error :messages="$errors->get('form.metodeMedia.lainnya')" class="mt-1" />
                    @endif
                </div>

                <hr class="border-hairline dark:border-gray-700">

                {{-- ─── 5) HASIL EDUKASI ─── --}}
                <div class="space-y-2">
                    <h4 class="text-sm font-semibold text-ink dark:text-gray-100">5) Hasil Edukasi</h4>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($hasilList as $key => $label)
                            <div class="p-3 border border-hairline rounded-lg bg-canvas dark:bg-gray-800 dark:border-gray-700"
                                wire:key="hasil-{{ $key }}">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-sm">{{ $label }}</span>
                                    <div class="flex gap-2">
                                        <x-radio-button label="Ya" value="1" name="hasil-{{ $key }}"
                                            wire:model.live="form.hasil.{{ $key }}.ya"
                                            :disabled="$formRO" />
                                        <x-radio-button label="Tidak" value="0" name="hasil-{{ $key }}"
                                            wire:model.live="form.hasil.{{ $key }}.ya"
                                            :disabled="$formRO" />
                                    </div>
                                </div>
                                @if (in_array(data_get($form, "hasil.$key.ya"), ['1', 1, true], true))
                                    <x-text-input wire:model.blur="form.hasil.{{ $key }}.keterangan"
                                        class="w-full mt-2" placeholder="Keterangan"
                                        :disabled="$formRO" />
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <hr class="border-hairline dark:border-gray-700">

                {{-- ─── 6) TINDAK LANJUT ─── --}}
                <div class="space-y-2">
                    <h4 class="text-sm font-semibold text-ink dark:text-gray-100">6) Tindak Lanjut</h4>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                        <div>
                            <x-input-label value="Edukasi lanjutan (dd/mm/yyyy)" />
                            <div class="flex items-end gap-2 mt-1">
                                <x-text-input wire:model="form.tindakLanjut.edukasiLanjutanTanggal"
                                    class="flex-1 font-mono" placeholder="dd/mm/yyyy"
                                    :error="$errors->has('form.tindakLanjut.edukasiLanjutanTanggal')"
                                    :disabled="$formRO" />
                                <x-secondary-button wire:click="setEdukasiLanjutanToday" type="button"
                                    :disabled="$formRO">Hari Ini</x-secondary-button>
                            </div>
                        </div>
                        <div class="md:col-span-2">
                            <x-input-label value="Rujuk ke (boleh lebih dari satu)" />
                            <div class="flex flex-wrap gap-3 mt-1">
                                @foreach ($rujukList as $key => $label)
                                    <div wire:key="rujuk-{{ $key }}">
                                        <x-toggle
                                            :current="in_array($key, $form['tindakLanjut']['dirujukKe'] ?? []) ? '1' : '0'"
                                            trueValue="1" falseValue="0"
                                            wireClick="toggleArrayOpt('form.tindakLanjut.dirujukKe', '{{ $key }}')"
                                            :label="$label" :disabled="$formRO" />
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="md:col-span-3">
                            <x-toggle wire:model.live="form.tindakLanjut.tidakPerluTL"
                                :trueValue="true" :falseValue="false"
                                label="Tidak diperlukan tindak lanjut"
                                :disabled="$formRO" />
                        </div>
                    </div>
                </div>

                <hr class="border-hairline dark:border-gray-700">

                {{-- ─── 7) TANDA TANGAN ─── --}}
                <div class="space-y-3">
                    <h4 class="text-sm font-semibold text-ink dark:text-gray-100">7) Tanda Tangan Pasien / Keluarga *</h4>

                    @if (!empty($sasaranEdukasiSignature))
                        <div class="flex items-center gap-3">
                            <img src="{{ $sasaranEdukasiSignature }}" alt="TTD"
                                class="object-contain w-32 h-16 bg-canvas border border-gray-300 rounded" />
                            @if (!$formRO)
                                <x-secondary-button wire:click="clearSasaranSignature" type="button"
                                    class="text-xs">Hapus TTD</x-secondary-button>
                            @endif
                        </div>
                    @elseif (!$formRO)
                        <div>
                            <x-input-label value="Tanda Tangan" />
                            <div class="mt-1">
                                <x-signature.signature-pad wireMethod="setSasaranSignature" />
                            </div>
                        </div>
                    @else
                        <p class="py-6 text-base italic text-center text-muted-soft">Belum ditandatangani.</p>
                    @endif

                    <div>
                        <x-input-label value="Nama Pasien / Keluarga *" />
                        <x-text-input wire:model.blur="form.ttd.pasienKeluargaNama" class="w-full mt-1"
                            placeholder="Nama yang menandatangani"
                            :error="$errors->has('form.ttd.pasienKeluargaNama')"
                            :disabled="$formRO" />
                        <x-input-error :messages="$errors->get('form.ttd.pasienKeluargaNama')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label value="Hubungan dengan Pasien *" />
                        <x-select-input wire:model.blur="form.ttd.pasienKeluargaHubungan"
                            :error="$errors->has('form.ttd.pasienKeluargaHubungan')" :disabled="$formRO"
                            class="w-full mt-1">
                            <option value="">— Pilih hubungan —</option>
                            @foreach ($hubunganOptions as $val => $label)
                                <option value="{{ $val }}">{{ $label }}</option>
                            @endforeach
                        </x-select-input>
                        <x-input-error :messages="$errors->get('form.ttd.pasienKeluargaHubungan')" class="mt-1" />
                    </div>
                </div>

            </div>
            </fieldset>
        </x-border-form>
    @endif

    {{-- ═══════════════ LIST RIWAYAT (expandable) ═══════════════ --}}
    <x-border-form title="Riwayat Edukasi Terintegrasi" align="start" bgcolor="bg-surface-soft">
        @php $list = $dataDaftarRi['edukasiPasienTerintegrasi'] ?? []; @endphp
        <div class="mt-3 overflow-x-auto bg-canvas border border-hairline rounded-xl dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-center justify-between gap-2 px-4 pt-3">
                <span class="text-sm font-semibold text-body dark:text-gray-300">Daftar Edukasi Tersimpan</span>
                <span class="text-xs italic text-muted-soft">Klik baris untuk lihat detail lengkap</span>
            </div>
            <table class="min-w-full mt-2 text-sm">
                <thead class="bg-surface-soft dark:bg-gray-800">
                    <tr class="text-left">
                        <th class="w-8 px-2 py-3 border-b border-hairline dark:border-gray-700"></th>
                        <th class="px-4 py-3 text-sm font-medium text-muted dark:text-gray-400 border-b border-hairline dark:border-gray-700">Tanggal</th>
                        <th class="px-4 py-3 text-sm font-medium text-muted dark:text-gray-400 border-b border-hairline dark:border-gray-700">Pasien / Keluarga</th>
                        <th class="px-4 py-3 text-sm font-medium text-muted dark:text-gray-400 border-b border-hairline dark:border-gray-700">Petugas</th>
                        <th class="px-4 py-3 text-sm font-medium text-center text-muted dark:text-gray-400 border-b border-hairline dark:border-gray-700">Status</th>
                        <th class="px-4 py-3 text-sm font-medium text-center text-muted dark:text-gray-400 border-b border-hairline dark:border-gray-700 w-56">Aksi</th>
                    </tr>
                </thead>
                @forelse (array_reverse($list) as $entri)
                    @php
                        $form  = $entri['form'] ?? [];
                        $id    = $entri['id'] ?? null;
                        $tgl   = $form['tglEdukasi'] ?? '-';
                        $petugasName = data_get($form, 'pemberiInformasi.petugasName', '-') ?: '-';
                        $pasienNama  = data_get($form, 'ttd.pasienKeluargaNama', '-') ?: '-';
                        $isFinal     = $this->entryIsFinal($entri);
                        $hasTtd      = !empty(data_get($form, 'ttd.pasienKeluargaTTD'));

                        $hambatanEmo = data_get($form, 'evaluasiAwal.hambatanEmosional.ada');
                        $hambatanFk  = data_get($form, 'evaluasiAwal.keterbatasanFisikKognitif.ada');
                        $isEmo       = in_array($hambatanEmo, [true, 1, '1'], true);
                        $isFk        = in_array($hambatanFk, [true, 1, '1'], true);
                        $isPahamTidak = in_array(data_get($form, 'hasil.paham.ya'), [false, 0, '0'], true);
                        $alertRow    = $isPahamTidak || $isEmo || $isFk;

                        // ringkasan join label
                        $tujuanTxt = collect($form['tujuan']['opsi'] ?? [])->map(fn($k) => $tujuanList[$k] ?? $k)->implode(', ');
                        if (!empty($form['tujuan']['lainnya'])) {
                            $tujuanTxt = trim($tujuanTxt . ($tujuanTxt ? ', ' : '') . $form['tujuan']['lainnya']);
                        }
                        $kebutuhanTxt = collect($form['kebutuhan']['opsi'] ?? [])->map(fn($k) => $kebutuhanList[$k] ?? $k)->implode(', ');
                        if (!empty($form['kebutuhan']['lainnya'])) {
                            $kebutuhanTxt = trim($kebutuhanTxt . ($kebutuhanTxt ? ', ' : '') . $form['kebutuhan']['lainnya']);
                        }
                        $metodeTxt = collect($form['metodeMedia']['opsi'] ?? [])->map(fn($k) => $metodeList[$k] ?? $k)->implode(', ');
                        if (!empty($form['metodeMedia']['lainnya'])) {
                            $metodeTxt = trim($metodeTxt . ($metodeTxt ? ', ' : '') . $form['metodeMedia']['lainnya']);
                        }
                        $rujukTxt = collect($form['tindakLanjut']['dirujukKe'] ?? [])->map(fn($k) => $rujukList[$k] ?? $k)->implode(', ');
                        $literasi = data_get($form, 'evaluasiAwal.literasi') ?: '-';
                        $hubLabel = $hubunganOptions[data_get($form, 'ttd.pasienKeluargaHubungan')] ?? data_get($form, 'ttd.pasienKeluargaHubungan', '');
                        $tlTgl    = data_get($form, 'tindakLanjut.edukasiLanjutanTanggal') ?: '-';
                        $tidakPerluTL = (bool) data_get($form, 'tindakLanjut.tidakPerluTL');
                    @endphp

                    <tbody wire:key="edu-terint-{{ $id ?: $loop->index }}"
                        x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }"
                        class="border-b border-hairline dark:border-gray-700">
                        <tr @click="open = !open"
                            class="cursor-pointer align-top hover:bg-surface-soft dark:hover:bg-gray-800/60 {{ $editingKey && $editingKey === $id ? 'bg-brand-lime/10 dark:bg-brand-lime/5' : ($alertRow ? 'bg-red-50/50 dark:bg-red-900/10' : '') }}">
                            <td class="px-2 py-3 text-center align-middle">
                                <svg class="w-4 h-4 mx-auto text-muted transition-transform" :class="{ 'rotate-90': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </td>
                            <td class="px-4 py-3 font-mono text-muted whitespace-nowrap align-middle dark:text-gray-300">{{ $tgl }}</td>
                            <td class="px-4 py-3 font-medium text-ink align-middle dark:text-white">{{ $pasienNama }}</td>
                            <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">{{ $petugasName }}</td>
                            <td class="px-4 py-3 text-center align-middle">
                                <div class="flex flex-col items-center gap-1">
                                    @if ($isFinal)
                                        <x-badge variant="info">Terkunci</x-badge>
                                    @else
                                        <x-badge variant="warning">Draft</x-badge>
                                    @endif
                                    @if ($alertRow)
                                        <x-badge variant="danger">⚠ Risiko</x-badge>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center align-middle" @click.stop>
                                <div class="flex flex-col items-center gap-2">
                                    {{-- Baris atas: aksi non-destruktif (Lanjut/Lihat/Cetak) --}}
                                    <div class="flex items-center justify-center gap-2">
                                    @if (!$isFinal && !$isFormLocked && $id)
                                        <x-primary-button type="button" wire:click="editEntry('{{ $id }}')"
                                            wire:loading.attr="disabled" wire:target="editEntry('{{ $id }}')"
                                            class="gap-1.5" title="Lanjutkan mengisi entri ini">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                            Lanjut Isi
                                        </x-primary-button>
                                    @endif
                                    @if ($isFinal && $id)
                                        <x-secondary-button type="button" wire:click="viewEntry('{{ $id }}')"
                                            wire:loading.attr="disabled" wire:target="viewEntry('{{ $id }}')"
                                            class="gap-1.5" title="Lihat detail (read-only) di form atas">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            Lihat
                                        </x-secondary-button>
                                    @endif
                                    @if ($id)
                                        <x-secondary-button wire:click="cetak('{{ $id }}')"
                                            wire:loading.attr="disabled" wire:target="cetak('{{ $id }}')"
                                            class="gap-1.5">
                                            <span wire:loading.remove wire:target="cetak('{{ $id }}')" class="flex items-center gap-1.5">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                </svg>
                                                Cetak
                                            </span>
                                            <span wire:loading wire:target="cetak('{{ $id }}')" class="flex items-center gap-1.5"><x-loading class="w-5 h-5" /> Mencetak...</span>
                                        </x-secondary-button>
                                    @endif
                                    </div>

                                    {{-- Baris bawah: aksi destruktif (Hapus) --}}
                                    @if (!$isFormLocked && $id)
                                        <div class="flex items-center justify-center gap-2">
                                        @can('dokumen.hapus')
                                        <x-outline-button type="button"
                                            wire:click.prevent="removeEdukasiTerintegrasiById('{{ $id }}')"
                                            wire:confirm="Hapus data edukasi terintegrasi ini?"
                                            wire:loading.attr="disabled"
                                            class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300 !px-2 !py-1"
                                            title="Hapus">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </x-outline-button>
                                        @endcan
                                        </div>
                                    @endif
                                </div>
                            </td>
                        </tr>

                        {{-- DETAIL (expand) --}}
                        <tr x-show="open" x-cloak>
                            <td colspan="6" class="px-4 py-4 bg-surface-soft/60 dark:bg-gray-950/30">
                                <dl class="grid grid-cols-1 gap-x-8 gap-y-3 md:grid-cols-2">
                                    <div class="md:col-span-2">
                                        <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Tujuan Edukasi</dt>
                                        <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $tujuanTxt ?: '-' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Evaluasi Awal</dt>
                                        <dd class="mt-0.5 text-ink dark:text-gray-200">
                                            Literasi: {{ $literasi }};
                                            Hambatan emosional: {{ $isEmo ? 'Ada' : 'Tidak' }};
                                            Keterbatasan fisik/kognitif: {{ $isFk ? 'Ada' : 'Tidak' }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Kebutuhan Edukasi</dt>
                                        <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $kebutuhanTxt ?: '-' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Metode & Media</dt>
                                        <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $metodeTxt ?: '-' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Hasil Edukasi</dt>
                                        <dd class="mt-0.5 text-ink dark:text-gray-200">
                                            @foreach ($hasilList as $hk => $hlabel)
                                                @php $hv = data_get($form, "hasil.$hk.ya"); @endphp
                                                @if (!is_null($hv) && $hv !== '')
                                                    <div>{{ $hlabel }}: <strong>{{ in_array($hv, [true, 1, '1'], true) ? 'Ya' : 'Tidak' }}</strong></div>
                                                @endif
                                            @endforeach
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Tindak Lanjut</dt>
                                        <dd class="mt-0.5 text-ink dark:text-gray-200">
                                            @if ($tidakPerluTL)
                                                Tidak diperlukan tindak lanjut
                                            @else
                                                Edukasi lanjutan: {{ $tlTgl }}@if ($rujukTxt); Rujuk ke: {{ $rujukTxt }}@endif
                                            @endif
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Penanda Tangan</dt>
                                        <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $pasienNama }}@if ($hubLabel) <span class="text-muted">({{ $hubLabel }})</span>@endif</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">TTD Pasien / Keluarga</dt>
                                        <dd class="mt-0.5">
                                            @if ($hasTtd)
                                                <span class="text-success-deep dark:text-green-300">Sudah TTD</span>
                                            @else
                                                <x-badge variant="danger">Belum TTD</x-badge>
                                            @endif
                                        </dd>
                                    </div>
                                </dl>
                            </td>
                        </tr>
                    </tbody>
                @empty
                    <tbody>
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-muted-soft">Belum ada data edukasi terintegrasi.</td>
                        </tr>
                    </tbody>
                @endforelse
            </table>
        </div>
    </x-border-form>

            </div>{{-- /konten flex-1 --}}

            {{-- ══ FOOTER STICKY (anak langsung modal-body → selalu terlihat) ══ --}}
            <div class="sticky bottom-0 z-10 px-6 py-3 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    @if ($viewOnly)
                        <p class="flex items-center gap-1.5 text-sm text-sky-600 dark:text-sky-400">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <span>Mode lihat — entri terkunci, tidak dapat diubah.</span>
                        </p>
                    @elseif (!$isFormLocked)
                        <p class="flex items-center gap-1.5 text-sm text-muted dark:text-gray-400">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Simpan draft dulu, lalu <strong>Simpan &amp; Kunci</strong> setelah TTD pasien/keluarga.</span>
                        </p>
                    @else
                        <span></span>
                    @endif

                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <x-secondary-button type="button" wire:click="closeModal">Tutup</x-secondary-button>

                        @if ($viewOnly)
                            <x-primary-button wire:click.prevent="cancelEdit" wire:target="cancelEdit"
                                wire:loading.attr="disabled" class="gap-1.5 min-w-[160px] justify-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                Selesai Melihat
                            </x-primary-button>
                        @elseif (!$isFormLocked)
                            @if ($editingKey)
                                <x-outline-button wire:click.prevent="cancelEdit" wire:target="cancelEdit"
                                    wire:loading.attr="disabled" class="gap-1.5"
                                    title="Kosongkan form untuk menambah edukasi lain — entri yang sudah tersimpan tidak berubah">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Entri Baru
                                </x-outline-button>
                            @endif
                            <x-secondary-button wire:click.prevent="saveDraft" wire:loading.attr="disabled"
                                wire:target="saveDraft" class="gap-2 min-w-[150px] justify-center">
                                <span wire:loading.remove wire:target="saveDraft" class="flex items-center gap-1.5">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 21v-8H7v8M7 3v5h8M5 3h11l4 4v12a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z" />
                                    </svg>
                                    Simpan Draft
                                </span>
                                <span wire:loading wire:target="saveDraft"><x-loading class="w-4 h-4" /> Menyimpan...</span>
                            </x-secondary-button>
                            <x-primary-button wire:click.prevent="finalize" wire:loading.attr="disabled"
                                wire:target="finalize" class="gap-2 min-w-[160px] justify-center">
                                <span wire:loading.remove wire:target="finalize" class="flex items-center gap-1.5">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                    Simpan &amp; Kunci
                                </span>
                                <span wire:loading wire:target="finalize"><x-loading class="w-4 h-4" /> Mengunci...</span>
                            </x-primary-button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </x-modal>
</div>
