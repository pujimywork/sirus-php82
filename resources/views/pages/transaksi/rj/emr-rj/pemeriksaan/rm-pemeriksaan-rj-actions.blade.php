<?php

use Livewire\Component;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\WithValidationToast\WithValidationToastTrait;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;

new class extends Component {
    use EmrRJTrait, WithRenderVersioningTrait, WithValidationToastTrait, WithFileUploads;

    // ── Upload Penunjang ──────────────────────────────────────────
    public $filePDF = null;
    public string $descPDF = '';
    public string $viewFilePDF = '';

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $dataDaftarPoliRJ = [];

    // radio
    public $suspekAkibatKerja;

    // renderVersions
    public array $renderVersions = [];
    protected array $renderAreas = ['modal-pemeriksaan-rj'];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas(['modal-pemeriksaan-rj']);
    }

    /* ===============================
     | RENDERING — pastikan pemeriksaan selalu lengkap sebelum render
     | Merge default ke data existing agar key seperti anatomi, tandaVital
     | dll selalu ada meskipun JSON hanya punya pemeriksaanPenunjang dari kirim lab
     =============================== */
    public function rendering(): void
    {
        $default = $this->getDefaultPemeriksaan();
        $current = $this->dataDaftarPoliRJ['pemeriksaan'] ?? [];
        $this->dataDaftarPoliRJ['pemeriksaan'] = array_replace_recursive($default, $current);
    }

    /* ===============================
     | OPEN REKAM MEDIS - PEMERIKSAAN
     =============================== */
    #[On('open-rm-pemeriksaan-rj')]
    public function openPemeriksaan($rjNo): void
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

        // Initialize pemeriksaan data jika belum ada
        $this->dataDaftarPoliRJ['pemeriksaan'] ??= $this->getDefaultPemeriksaan();

        // Sync radio button suspekAkibatKerja ke property terpisah
        $this->suspekAkibatKerja = $this->dataDaftarPoliRJ['pemeriksaan']['suspekAkibatKerja']['suspekAkibatKerja'] ?? null;

        // 🔥 INCREMENT: Refresh seluruh modal pemeriksaan
        $this->incrementVersion('modal-pemeriksaan-rj');

        // Cek status lock
        if ($this->checkEmrRJStatus($rjNo)) {
            $this->isFormLocked = true;
        }
    }

    /* ===============================
     | GET DEFAULT PEMERIKSAAN STRUCTURE
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

            'anatomi' => collect(['kepala', 'mata', 'telinga', 'hidung', 'rambut', 'bibir', 'gigiGeligi', 'lidah', 'langitLangit', 'leher', 'tenggorokan', 'tonsil', 'dada', 'payudarah', 'punggung', 'perut', 'genital', 'anus', 'lenganAtas', 'lenganBawah', 'jariTangan', 'kukuTangan', 'persendianTangan', 'tungkaiAtas', 'tungkaiBawah', 'jariKaki', 'kukuKaki', 'persendianKaki', 'faring'])
                ->mapWithKeys(
                    fn($part) => [
                        $part => [
                            'kelainan' => 'Tidak Diperiksa',
                            'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                            'desc' => '',
                        ],
                    ],
                )
                ->toArray(),

            'suspekAkibatKerja' => [
                'suspekAkibatKerja' => '',
                'keteranganSuspekAkibatKerja' => '',
                'suspekAkibatKerjaOptions' => [['suspekAkibatKerja' => 'Ya'], ['suspekAkibatKerja' => 'Tidak']],
            ],

            'FisikujiFungsi' => [
                'FisikujiFungsi' => '',
            ],

            'eeg' => [
                'hasilPemeriksaan' => '',
                'hasilPemeriksaanSebelumnya' => '',
                'mriKepala' => '',
                'hasilPerekaman' => '',
                'kesimpulan' => '',
                'saran' => '',
            ],

            'emg' => [
                'keluhanPasien' => '',
                'pengobatan' => '',
                'td' => '',
                'rr' => '',
                'hr' => '',
                's' => '',
                'gcs' => '',
                'fkl' => '',
                'nprs' => '',
                'rclRctl' => '',
                'nnCr' => '',
                'nnCrLain' => '',
                'motorik' => '',
                'pergerakan' => '',
                'kekuatan' => '',
                'extremitasSuperior' => '',
                'extremitasInferior' => '',
                'tonus' => '',
                'refleksFisiologi' => '',
                'refleksPatologis' => '',
                'sensorik' => '',
                'otonom' => '',
                'emcEmgFindings' => '',
                'impresion' => '',
            ],

            'ravenTest' => [
                'skoring' => '',
                'presentil' => '',
                'interpretasi' => '',
                'anjuran' => '',
            ],

            'penunjang' => '',
            'uploadHasilPenunjang' => [],
        ];
    }

    /* ===============================
     | VALIDATION RULES
     =============================== */
    protected function rules(): array
    {
        return [
            // TANDA VITAL
            'dataDaftarPoliRJ.pemeriksaan.tandaVital.waktuPemeriksaan' => 'date_format:d/m/Y H:i:s',
            'dataDaftarPoliRJ.pemeriksaan.tandaVital.sistolik' => 'nullable|numeric',
            'dataDaftarPoliRJ.pemeriksaan.tandaVital.distolik' => 'nullable|numeric',
            'dataDaftarPoliRJ.pemeriksaan.tandaVital.frekuensiNadi' => 'required|numeric',
            'dataDaftarPoliRJ.pemeriksaan.tandaVital.frekuensiNafas' => 'required|numeric',
            'dataDaftarPoliRJ.pemeriksaan.tandaVital.suhu' => 'required|numeric',
            'dataDaftarPoliRJ.pemeriksaan.tandaVital.spo2' => 'nullable|numeric|min:0|max:100',
            'dataDaftarPoliRJ.pemeriksaan.tandaVital.gda' => 'nullable|numeric|min:0',

            // NUTRISI
            'dataDaftarPoliRJ.pemeriksaan.nutrisi.bb' => 'required|numeric|min:0|max:300',
            'dataDaftarPoliRJ.pemeriksaan.nutrisi.tb' => 'required|numeric|min:0|max:300',
            'dataDaftarPoliRJ.pemeriksaan.nutrisi.imt' => 'required|numeric|min:0',
            'dataDaftarPoliRJ.pemeriksaan.nutrisi.lk' => 'nullable|numeric|min:0|max:100',
            'dataDaftarPoliRJ.pemeriksaan.nutrisi.lila' => 'nullable|numeric|min:0|max:100',
        ];
    }

    protected function messages(): array
    {
        return [
            // TANDA VITAL
            'dataDaftarPoliRJ.pemeriksaan.tandaVital.waktuPemeriksaan.date_format' => ':attribute harus dalam format dd/mm/yyyy hh:mi:ss',
            'dataDaftarPoliRJ.pemeriksaan.tandaVital.frekuensiNadi.required' => ':attribute wajib diisi',
            'dataDaftarPoliRJ.pemeriksaan.tandaVital.frekuensiNadi.numeric' => ':attribute harus berupa angka',
            'dataDaftarPoliRJ.pemeriksaan.tandaVital.frekuensiNafas.required' => ':attribute wajib diisi',
            'dataDaftarPoliRJ.pemeriksaan.tandaVital.frekuensiNafas.numeric' => ':attribute harus berupa angka',
            'dataDaftarPoliRJ.pemeriksaan.tandaVital.suhu.required' => ':attribute wajib diisi',
            'dataDaftarPoliRJ.pemeriksaan.tandaVital.suhu.numeric' => ':attribute harus berupa angka',
            'dataDaftarPoliRJ.pemeriksaan.tandaVital.sistolik.numeric' => ':attribute harus berupa angka',
            'dataDaftarPoliRJ.pemeriksaan.tandaVital.distolik.numeric' => ':attribute harus berupa angka',
            'dataDaftarPoliRJ.pemeriksaan.tandaVital.spo2.numeric' => ':attribute harus berupa angka',
            'dataDaftarPoliRJ.pemeriksaan.tandaVital.spo2.min' => ':attribute tidak boleh kurang dari 0',
            'dataDaftarPoliRJ.pemeriksaan.tandaVital.spo2.max' => ':attribute tidak boleh lebih dari 100',
            'dataDaftarPoliRJ.pemeriksaan.tandaVital.gda.numeric' => ':attribute harus berupa angka',
            'dataDaftarPoliRJ.pemeriksaan.tandaVital.gda.min' => ':attribute tidak boleh kurang dari 0',

            // NUTRISI
            'dataDaftarPoliRJ.pemeriksaan.nutrisi.bb.required' => ':attribute wajib diisi',
            'dataDaftarPoliRJ.pemeriksaan.nutrisi.bb.numeric' => ':attribute harus berupa angka',
            'dataDaftarPoliRJ.pemeriksaan.nutrisi.bb.min' => ':attribute tidak boleh kurang dari 0 kg',
            'dataDaftarPoliRJ.pemeriksaan.nutrisi.bb.max' => ':attribute tidak boleh lebih dari 300 kg',
            'dataDaftarPoliRJ.pemeriksaan.nutrisi.tb.required' => ':attribute wajib diisi',
            'dataDaftarPoliRJ.pemeriksaan.nutrisi.tb.numeric' => ':attribute harus berupa angka',
            'dataDaftarPoliRJ.pemeriksaan.nutrisi.tb.min' => ':attribute tidak boleh kurang dari 0 cm',
            'dataDaftarPoliRJ.pemeriksaan.nutrisi.tb.max' => ':attribute tidak boleh lebih dari 300 cm',
            'dataDaftarPoliRJ.pemeriksaan.nutrisi.imt.required' => ':attribute wajib diisi',
            'dataDaftarPoliRJ.pemeriksaan.nutrisi.imt.numeric' => ':attribute harus berupa angka',
            'dataDaftarPoliRJ.pemeriksaan.nutrisi.imt.min' => ':attribute tidak boleh kurang dari 0',
            'dataDaftarPoliRJ.pemeriksaan.nutrisi.lk.numeric' => ':attribute harus berupa angka',
            'dataDaftarPoliRJ.pemeriksaan.nutrisi.lk.min' => ':attribute tidak boleh kurang dari 0 cm',
            'dataDaftarPoliRJ.pemeriksaan.nutrisi.lk.max' => ':attribute tidak boleh lebih dari 100 cm',
            'dataDaftarPoliRJ.pemeriksaan.nutrisi.lila.numeric' => ':attribute harus berupa angka',
            'dataDaftarPoliRJ.pemeriksaan.nutrisi.lila.min' => ':attribute tidak boleh kurang dari 0 cm',
            'dataDaftarPoliRJ.pemeriksaan.nutrisi.lila.max' => ':attribute tidak boleh lebih dari 100 cm',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'dataDaftarPoliRJ.pemeriksaan.tandaVital.waktuPemeriksaan' => 'Waktu Pemeriksaan',
            'dataDaftarPoliRJ.pemeriksaan.tandaVital.sistolik' => 'Sistolik',
            'dataDaftarPoliRJ.pemeriksaan.tandaVital.distolik' => 'Distolik',
            'dataDaftarPoliRJ.pemeriksaan.tandaVital.frekuensiNadi' => 'Frekuensi Nadi',
            'dataDaftarPoliRJ.pemeriksaan.tandaVital.frekuensiNafas' => 'Frekuensi Nafas',
            'dataDaftarPoliRJ.pemeriksaan.tandaVital.suhu' => 'Suhu',
            'dataDaftarPoliRJ.pemeriksaan.tandaVital.spo2' => 'SpO2',
            'dataDaftarPoliRJ.pemeriksaan.tandaVital.gda' => 'GDA',
            'dataDaftarPoliRJ.pemeriksaan.nutrisi.bb' => 'Berat Badan',
            'dataDaftarPoliRJ.pemeriksaan.nutrisi.tb' => 'Tinggi Badan',
            'dataDaftarPoliRJ.pemeriksaan.nutrisi.imt' => 'Indeks Massa Tubuh',
            'dataDaftarPoliRJ.pemeriksaan.nutrisi.lk' => 'Lingkar Kepala',
            'dataDaftarPoliRJ.pemeriksaan.nutrisi.lila' => 'Lingkar Lengan Atas',
        ];
    }

    /* ===============================
     | SAVE PEMERIKSAAN
     =============================== */
    #[On('save-rm-pemeriksaan-rj')]
    public function save(): void
    {
        // 1. Read-only guard — selalu dengan toast
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat menyimpan data.');
            return;
        }

        // 2. Guard: properti lokal belum ter-load
        if (empty($this->dataDaftarPoliRJ)) {
            $this->dispatch('toast', type: 'error', message: 'Data kunjungan tidak ditemukan, silakan buka ulang form.');
            return;
        }

        // 3. Validasi Livewire rules
        $this->validateWithToast();

        try {
            DB::transaction(function () {
                // 4. Lock row di DB (SELECT FOR UPDATE) — cegah race condition
                $this->lockRJRow($this->rjNo);

                // 5. Ambil data terkini dari DB (setelah lock)
                $data = $this->findDataRJ($this->rjNo) ?? [];

                // 6. Guard: data DB kosong — jangan overwrite JSON dengan array kosong
                if (empty($data)) {
                    $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan, simpan dibatalkan.');
                    return;
                }

                // 7. Set hanya key 'pemeriksaan' — key lain tidak tersentuh
                $data['pemeriksaan'] = $this->dataDaftarPoliRJ['pemeriksaan'] ?? [];

                // 8. Persist + sync properti lokal
                $this->updateJsonRJ($this->rjNo, $data);
                $this->dataDaftarPoliRJ = $data;
            });

            $this->afterSave('Pemeriksaan berhasil disimpan.');
        } catch (\RuntimeException $e) {
            // lockRJRow() throws RuntimeException jika row tidak ditemukan
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | TERIMA DATA LABORAT DARI MODUL LAIN
     =============================== */
    #[On('laborat-kirim-penunjang')]
    public function terimaPenunjangLaborat(string $text): void
    {
        // 1. Read-only guard — selalu dengan toast
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat menyimpan data.');
            return;
        }

        // 2. Guard: properti lokal belum ter-load
        if (empty($this->dataDaftarPoliRJ)) {
            $this->dispatch('toast', type: 'error', message: 'Data kunjungan tidak ditemukan, silakan buka ulang form.');
            return;
        }

        try {
            DB::transaction(function () use ($text) {
                // 3. Lock row dulu
                $this->lockRJRow($this->rjNo);

                // 4. Ambil data terkini dari DB (setelah lock)
                $data = $this->findDataRJ($this->rjNo) ?? [];

                if (empty($data)) {
                    $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan, simpan dibatalkan.');
                    return;
                }

                // 5. Append ke penunjang yang sudah ada — tidak overwrite key lain
                $existing = $data['pemeriksaan']['penunjang'] ?? '';
                $data['pemeriksaan']['penunjang'] = trim(($existing ? $existing . "\n" : '') . $text);

                // 6. Persist + sync
                $this->updateJsonRJ($this->rjNo, $data);
                $this->dataDaftarPoliRJ = $data;
            });

            $this->afterSave('Data laboratorium berhasil dikirim ke Penunjang.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal mengirim ke Penunjang: ' . $e->getMessage());
        }
    }

    /* ===============================
     | UPLOAD HASIL PENUNJANG
     =============================== */
    public function uploadHasilPenunjang(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form terkunci, tidak dapat mengupload file.');
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
                // Lock row dulu
                $this->lockRJRow($this->rjNo);

                $data = $this->findDataRJ($this->rjNo) ?? [];

                if (empty($data)) {
                    $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.');
                    return;
                }

                $path = $this->filePDF->store('uploadHasilPenunjang', 'local');

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

                $this->updateJsonRJ($this->rjNo, $data);
                $this->dataDaftarPoliRJ = $data;
            });

            $this->reset(['filePDF', 'descPDF']);
            $this->resetValidation(['filePDF', 'descPDF']);
            $this->incrementVersion('modal-pemeriksaan-rj');
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
            $this->dispatch('toast', type: 'error', message: 'Form terkunci, tidak dapat menghapus file.');
            return;
        }

        try {
            DB::transaction(function () use ($file) {
                // Lock row dulu
                $this->lockRJRow($this->rjNo);

                $data = $this->findDataRJ($this->rjNo) ?? [];

                if (empty($data)) {
                    $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.');
                    return;
                }

                // Hapus file fisik jika ada
                if (Storage::disk('local')->exists($file)) {
                    Storage::disk('local')->delete($file);
                }

                // Hapus dari array
                $data['pemeriksaan']['uploadHasilPenunjang'] = collect($data['pemeriksaan']['uploadHasilPenunjang'] ?? [])
                    ->filter(fn($item) => ($item['file'] ?? '') !== $file)
                    ->values()
                    ->toArray();

                $this->updateJsonRJ($this->rjNo, $data);
                $this->dataDaftarPoliRJ = $data;
            });

            $this->incrementVersion('modal-pemeriksaan-rj');
            $this->dispatch('toast', type: 'success', message: 'File berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus file: ' . $e->getMessage());
        }
    }

    /* ===============================
     | OPEN / CLOSE MODAL LIHAT PDF
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
     | SET PERAWAT PEMERIKSA
     =============================== */
    public function setPerawatPemeriksa(): void
    {
        if ($this->isFormLocked) {
            return;
        }

        if (auth()->user()->hasRole('Perawat')) {
            $this->dataDaftarPoliRJ['pemeriksaan']['tandaVital']['perawatPemeriksa'] = auth()->user()->myuser_name;
            $this->dataDaftarPoliRJ['pemeriksaan']['tandaVital']['perawatPemeriksaCode'] = auth()->user()->myuser_code;
            $this->incrementVersion('modal-pemeriksaan-rj');
        } else {
            $this->dispatch('toast', type: 'error', message: 'Hanya user dengan role Perawat yang dapat melakukan TTD-E.');
        }
    }

    /* ===============================
     | SET WAKTU PEMERIKSAAN
     =============================== */
    public function setWaktuPemeriksaan($time): void
    {
        if ($this->isFormLocked) {
            return;
        }

        $this->dataDaftarPoliRJ['pemeriksaan']['tandaVital']['waktuPemeriksaan'] = $time;
        $this->incrementVersion('modal-pemeriksaan-rj');
    }

    /* ===============================
     | REFRESH DARI EVENT MODUL LAIN
     | Tidak perlu lock — hanya baca + sync lokal
     =============================== */
    #[On('laborat-order-terkirim')]
    public function terimaLaboratOrder(): void
    {
        $data = $this->findDataRJ($this->rjNo);
        if ($data) {
            $this->dataDaftarPoliRJ['pemeriksaan']['pemeriksaanPenunjang'] = $data['pemeriksaan']['pemeriksaanPenunjang'] ?? [];
        }
        $this->incrementVersion('modal-pemeriksaan-rj');
    }

    #[On('radiologi-order-terkirim')]
    public function terimaRadiologiOrder(): void
    {
        $data = $this->findDataRJ($this->rjNo);
        if ($data) {
            $this->dataDaftarPoliRJ['pemeriksaan']['pemeriksaanPenunjang'] = $data['pemeriksaan']['pemeriksaanPenunjang'] ?? [];
        }
        $this->incrementVersion('modal-pemeriksaan-rj');
    }

    /* ===============================
     | UPDATED HOOK
     =============================== */
    public function updated($propertyName, $value): void
    {
        // Auto-hitung IMT saat BB atau TB berubah
        if (str_contains($propertyName, 'pemeriksaan.nutrisi.bb') || str_contains($propertyName, 'pemeriksaan.nutrisi.tb')) {
            $this->hitungIMT();
        }

        // Sync radio button suspekAkibatKerja ke dataDaftarPoliRJ
        if ($propertyName === 'suspekAkibatKerja') {
            $this->suspekAkibatKerja = $value;
            $this->dataDaftarPoliRJ['pemeriksaan']['suspekAkibatKerja']['suspekAkibatKerja'] = $value;
        }
    }

    /* ===============================
     | CLOSE MODAL
     =============================== */
    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'rm-pemeriksaan-actions');
    }

    /* ===============================
     | HELPERS
     =============================== */
    private function hitungIMT(): void
    {
        $bb = (float) ($this->dataDaftarPoliRJ['pemeriksaan']['nutrisi']['bb'] ?? 0);
        $tb = (float) ($this->dataDaftarPoliRJ['pemeriksaan']['nutrisi']['tb'] ?? 0);

        if ($bb > 0 && $tb > 0) {
            $tbM = $tb / 100;
            $this->dataDaftarPoliRJ['pemeriksaan']['nutrisi']['imt'] = round($bb / ($tbM * $tbM), 2);
        }
    }

    private function afterSave(string $message): void
    {
        $this->incrementVersion('modal-pemeriksaan-rj');
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
    {{-- CONTAINER UTAMA --}}
    <div class="flex flex-col w-full" wire:key="{{ $this->renderKey('modal-pemeriksaan-rj', [$rjNo ?? 'new']) }}">

        {{-- BODY --}}
        <div class="w-full mx-auto">
            <div
                class="w-full p-4 space-y-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                @if (isset($dataDaftarPoliRJ['pemeriksaan']))
                    <div class="w-full mb-1">
                        <div class="grid grid-cols-1">
                            <div id="TransaksiRawatJalan" class="px-2">
                                <div x-data="{ activeTab: 'Umum' }">

                                    {{-- TAB NAVIGATION --}}
                                    <div class="px-2 border-b border-gray-200 dark:border-gray-700">
                                        <ul
                                            class="flex flex-wrap -mb-px text-xs font-medium text-center text-gray-500 dark:text-gray-400">

                                            {{-- UMUM --}}
                                            <li class="mr-2">
                                                <label
                                                    class="inline-block p-4 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                                    :class="activeTab === '{{ $dataDaftarPoliRJ['pemeriksaan']['umumTab'] ?? 'Umum' }}'
                                                        ?
                                                        'text-primary border-primary bg-gray-100' : ''"
                                                    @click="activeTab = '{{ $dataDaftarPoliRJ['pemeriksaan']['umumTab'] ?? 'Umum' }}'">
                                                    {{ $dataDaftarPoliRJ['pemeriksaan']['umumTab'] ?? 'Umum' }}
                                                </label>
                                            </li>

                                            {{-- ANATOMI --}}
                                            <li class="mr-2">
                                                <label
                                                    class="inline-block p-4 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                                    :class="activeTab === 'Anatomi' ?
                                                        'text-primary border-primary bg-gray-100' : ''"
                                                    @click="activeTab = 'Anatomi'">
                                                    Anatomi
                                                </label>
                                            </li>

                                            {{-- PELAYANAN PENUNJANG --}}
                                            <li class="mr-2">
                                                <label
                                                    class="inline-block p-4 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                                    :class="activeTab === 'PenunjangHasil' ?
                                                        'text-primary border-primary bg-gray-100' : ''"
                                                    @click="activeTab = 'PenunjangHasil'">
                                                    Pelayanan Penunjang
                                                </label>
                                            </li>

                                            {{-- UPLOAD PENUNJANG --}}
                                            <li class="mr-2">
                                                <label
                                                    class="inline-block p-4 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                                    :class="activeTab === 'UploadPenunjangHasil' ?
                                                        'text-primary border-primary bg-gray-100' : ''"
                                                    @click="activeTab = 'UploadPenunjangHasil'">
                                                    Upload Penunjang
                                                </label>
                                            </li>

                                            {{-- HASIL PENUNJANG (semua kunjungan) --}}
                                            <li class="mr-2">
                                                <label
                                                    class="inline-flex items-center gap-2 p-4 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300 dark:hover:text-gray-300"
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

                                    {{-- TAB CONTENTS --}}
                                    <div class="p-2 rounded-lg bg-gray-50 dark:bg-gray-800"
                                        x-show.transition.in.opacity.duration.600="activeTab === '{{ $dataDaftarPoliRJ['pemeriksaan']['umumTab'] ?? 'Umum' }}'">
                                        @include('pages.transaksi.rj.emr-rj.pemeriksaan.tabs.umum-tab')
                                    </div>

                                    <div class="p-2 rounded-lg bg-gray-50 dark:bg-gray-800"
                                        x-show.transition.in.opacity.duration.600="activeTab === 'Fisik'">
                                        @include('pages.transaksi.rj.emr-rj.pemeriksaan.tabs.fisik-tab')
                                    </div>

                                    <div class="p-2 rounded-lg bg-gray-50 dark:bg-gray-800"
                                        x-show.transition.in.opacity.duration.600="activeTab === 'Anatomi'">
                                        @include('pages.transaksi.rj.emr-rj.pemeriksaan.tabs.anatomi-tab')
                                    </div>

                                    <div class="p-2 rounded-lg bg-gray-50 dark:bg-gray-800"
                                        x-show.transition.in.opacity.duration.600="activeTab === 'UjiFungsi'">
                                        @include('pages.transaksi.rj.emr-rj.pemeriksaan.tabs.uji-fungsi-tab')
                                    </div>

                                    <div class="p-2 rounded-lg bg-gray-50 dark:bg-gray-800"
                                        x-show.transition.in.opacity.duration.600="activeTab === 'Penunjang'">
                                        @include('pages.transaksi.rj.emr-rj.pemeriksaan.tabs.penunjang-tab')
                                    </div>

                                    <div class="p-2 rounded-lg bg-gray-50 dark:bg-gray-800"
                                        x-show.transition.in.opacity.duration.600="activeTab === 'PenunjangHasil'">
                                        @include('pages.transaksi.rj.emr-rj.pemeriksaan.tabs.pelayanan-penunjang-tab')
                                    </div>

                                    <div class="p-2 rounded-lg bg-gray-50 dark:bg-gray-800"
                                        x-show.transition.in.opacity.duration.600="activeTab === 'UploadPenunjangHasil'">
                                        @include('pages.transaksi.rj.emr-rj.pemeriksaan.tabs.upload-pelayanan-penunjang-tab')
                                    </div>

                                    <div class="p-2 rounded-lg bg-gray-50 dark:bg-gray-800"
                                        x-show.transition.in.opacity.duration.600="activeTab === 'HasilPenunjang'">
                                        @include('pages.transaksi.rj.emr-rj.pemeriksaan.tabs.hasil-penunjang-tab')
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
