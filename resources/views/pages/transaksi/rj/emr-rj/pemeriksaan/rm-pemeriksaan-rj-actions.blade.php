<?php

use Livewire\Component;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;

new class extends Component {
    use EmrRJTrait, WithRenderVersioningTrait, WithFileUploads;

    // ── Upload Penunjang ──────────────────────────────────────────
    public $filePDF = null;
    public string $descPDF = '';
    public string $viewFilePDF = '';

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $dataDaftarPoliRJ = [];

    //radio
    public $suspekAkibatKerja;

    // renderVersions
    public array $renderVersions = [];
    protected array $renderAreas = ['modal-pemeriksaan-rj'];

    /* ===============================
     | OPEN REKAM MEDIS PERAWAT - PEMERIKSAAN
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

        // Initialize pemeriksaan data if not exists
        if (!isset($this->dataDaftarPoliRJ['pemeriksaan'])) {
            $this->dataDaftarPoliRJ['pemeriksaan'] = $this->getDefaultPemeriksaan();
        }

        if (isset($this->dataDaftarPoliRJ['pemeriksaan']['suspekAkibatKerja']['suspekAkibatKerja'])) {
            $this->suspekAkibatKerja = $this->dataDaftarPoliRJ['pemeriksaan']['suspekAkibatKerja']['suspekAkibatKerja'];
        }

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

            'anatomi' => [
                'kepala' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'mata' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'telinga' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'hidung' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'rambut' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'bibir' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'gigiGeligi' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'lidah' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'langitLangit' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'leher' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'tenggorokan' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'tonsil' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'dada' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'payudarah' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'punggung' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'perut' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'genital' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'anus' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'lenganAtas' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'lenganBawah' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'jariTangan' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'kukuTangan' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'persendianTangan' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'tungkaiAtas' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'tungkaiBawah' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'jariKaki' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'kukuKaki' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'persendianKaki' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
                'faring' => [
                    'kelainan' => 'Tidak Diperiksa',
                    'kelainanOptions' => [['kelainan' => 'Tidak Diperiksa'], ['kelainan' => 'Tidak Ada Kelainan'], ['kelainan' => 'Ada']],
                    'desc' => '',
                ],
            ],

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
     | CLOSE MODAL
     =============================== */
    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'rm-pemeriksaan-actions');
    }

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
            // TANDA VITAL
            'dataDaftarPoliRJ.pemeriksaan.tandaVital.waktuPemeriksaan' => 'Waktu Pemeriksaan',
            'dataDaftarPoliRJ.pemeriksaan.tandaVital.sistolik' => 'Sistolik',
            'dataDaftarPoliRJ.pemeriksaan.tandaVital.distolik' => 'Distolik',
            'dataDaftarPoliRJ.pemeriksaan.tandaVital.frekuensiNadi' => 'Frekuensi Nadi',
            'dataDaftarPoliRJ.pemeriksaan.tandaVital.frekuensiNafas' => 'Frekuensi Nafas',
            'dataDaftarPoliRJ.pemeriksaan.tandaVital.suhu' => 'Suhu',
            'dataDaftarPoliRJ.pemeriksaan.tandaVital.spo2' => 'SpO2',
            'dataDaftarPoliRJ.pemeriksaan.tandaVital.gda' => 'GDA',

            // NUTRISI
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

                // ✅ Set hanya key 'pemeriksaan', key lain tidak tersentuh
                $data['pemeriksaan'] = $this->dataDaftarPoliRJ['pemeriksaan'] ?? [];

                $this->updateJsonRJ($this->rjNo, $data);
            });

            $this->afterSave('Pemeriksaan berhasil disimpan.');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
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
            // 🔥 INCREMENT: Refresh untuk menampilkan perawat yang sudah di-set
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
        if (!$this->isFormLocked) {
            $this->dataDaftarPoliRJ['pemeriksaan']['tandaVital']['waktuPemeriksaan'] = $time;

            // 🔥 INCREMENT: Refresh untuk menampilkan waktu yang sudah di-set
            $this->incrementVersion('modal-pemeriksaan-rj');
        }
    }

    /* ===============================
     | HITUNG IMT (Indeks Massa Tubuh)
     =============================== */
    private function hitungIMT(): void
    {
        $bb = $this->dataDaftarPoliRJ['pemeriksaan']['nutrisi']['bb'] ?? 0;
        $tb = $this->dataDaftarPoliRJ['pemeriksaan']['nutrisi']['tb'] ?? 0;

        if ($bb > 0 && $tb > 0) {
            $tbInMeter = $tb / 100;
            $imt = $bb / ($tbInMeter * $tbInMeter);
            $this->dataDaftarPoliRJ['pemeriksaan']['nutrisi']['imt'] = round($imt, 2);
        }
    }

    public function updated($propertyName, $value)
    {
        // Cek apakah property yang di-update adalah BB atau TB
        if (str_contains($propertyName, 'pemeriksaan.nutrisi.bb') || str_contains($propertyName, 'pemeriksaan.nutrisi.tb')) {
            $this->hitungIMT();
        }

        // Cek apakah property yang di-update adalah suspekAkibatKerja Radio button
        if ($propertyName === 'suspekAkibatKerja') {
            $this->suspekAkibatKerja = $value;
            $this->dataDaftarPoliRJ['pemeriksaan']['suspekAkibatKerja']['suspekAkibatKerja'] = $value;
        }
    }

    private function afterSave(string $message): void
    {
        // 🔥 INCREMENT: Refresh seluruh modal pemeriksaan
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

    /* ===============================================================
 | UPLOAD HASIL PENUNJANG
 =============================================================== */
    public function uploadHasilPenunjang(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form terkunci, tidak dapat mengupload file.');
            return;
        }

        $this->validate(
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

                $this->dataDaftarPoliRJ['pemeriksaan']['uploadHasilPenunjang'] = $data['pemeriksaan']['uploadHasilPenunjang'];
            });

            $this->reset(['filePDF', 'descPDF']);
            $this->resetValidation(['filePDF', 'descPDF']);
            $this->incrementVersion('modal-pemeriksaan-rj');
            $this->dispatch('toast', type: 'success', message: 'File penunjang berhasil diupload.');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal upload: ' . $e->getMessage());
        }
    }

    /* ===============================================================
 | DELETE HASIL PENUNJANG
 =============================================================== */
    public function deleteHasilPenunjang(string $file): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form terkunci, tidak dapat menghapus file.');
            return;
        }

        try {
            DB::transaction(function () use ($file) {
                $data = $this->findDataRJ($this->rjNo) ?? [];

                if (empty($data)) {
                    $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.');
                    return;
                }

                if (Storage::disk('local')->exists($file)) {
                    Storage::disk('local')->delete($file);
                }

                $data['pemeriksaan']['uploadHasilPenunjang'] = collect($data['pemeriksaan']['uploadHasilPenunjang'] ?? [])
                    ->filter(fn($item) => ($item['file'] ?? '') !== $file)
                    ->values()
                    ->toArray();

                $this->updateJsonRJ($this->rjNo, $data);

                $this->dataDaftarPoliRJ['pemeriksaan']['uploadHasilPenunjang'] = $data['pemeriksaan']['uploadHasilPenunjang'];
            });

            $this->incrementVersion('modal-pemeriksaan-rj');
            $this->dispatch('toast', type: 'success', message: 'File berhasil dihapus.');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus file: ' . $e->getMessage());
        }
    }

    /* ===============================================================
 | OPEN MODAL LIHAT PDF
 =============================================================== */
    public function openModalViewPenunjang(string $file): void
    {
        if (!Storage::disk('local')->exists($file)) {
            $this->dispatch('toast', type: 'error', message: 'File tidak ditemukan di server.');
            return;
        }

        // Konversi file ke data URI — tidak perlu route
        $content = Storage::disk('local')->get($file);
        $this->viewFilePDF = 'data:application/pdf;base64,' . base64_encode($content);

        $this->dispatch('open-modal', name: 'view-penunjang-pdf');
    }

    /* ===============================================================
 | CLOSE MODAL LIHAT PDF
 =============================================================== */
    public function closeModalViewPenunjang(): void
    {
        $this->viewFilePDF = '';
        $this->dispatch('close-modal', name: 'view-penunjang-pdf');
    }

    public function mount()
    {
        $this->registerAreas(['modal-pemeriksaan-rj']);
    }

    #[On('laborat-kirim-penunjang')]
    public function terimaPenunjangLaborat(string $text): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat menyimpan data.');
            return;
        }

        try {
            DB::transaction(function () use ($text) {
                // ✅ Ambil existing data dari DB
                $data = $this->findDataRJ($this->rjNo) ?? [];

                // ✅ Guard: jika data kosong, batalkan
                if (empty($data)) {
                    $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan, simpan dibatalkan.');
                    return;
                }

                // ✅ Append ke penunjang yang sudah ada, tidak overwrite key lain
                $existing = $data['pemeriksaan']['penunjang'] ?? '';
                $data['pemeriksaan']['penunjang'] = trim(($existing ? $existing . "\n" : '') . $text);
                $this->updateJsonRJ($this->rjNo, $data);

                // ✅ Sync ke property lokal agar UI ikut update
                $this->dataDaftarPoliRJ['pemeriksaan']['penunjang'] = $data['pemeriksaan']['penunjang'];
                // 🔥 INCREMENT: Refresh seluruh modal pemeriksaan
                $this->incrementVersion('modal-pemeriksaan-rj');
            });

            $this->dispatch('toast', type: 'success', message: 'Data laboratorium berhasil dikirim ke Penunjang.');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal mengirim ke Penunjang: ' . $e->getMessage());
        }
    }

    #[On('laborat-order-terkirim')]
    public function terimaLaboratOrder(): void
    {
        // Refresh data lokal dari DB agar tab Penunjang ikut update
        $data = $this->findDataRJ($this->rjNo);
        if ($data) {
            $this->dataDaftarPoliRJ['pemeriksaan']['pemeriksaanPenunjang'] = $data['pemeriksaan']['pemeriksaanPenunjang'] ?? [];
        }

        $this->incrementVersion('modal-pemeriksaan-rj');
    }

    #[On('radiologi-order-terkirim')]
    public function terimaRadiologiOrder(): void
    {
        // Refresh data lokal dari DB agar tab Penunjang ikut update
        $data = $this->findDataRJ($this->rjNo);
        if ($data) {
            $this->dataDaftarPoliRJ['pemeriksaan']['pemeriksaanPenunjang'] = $data['pemeriksaan']['pemeriksaanPenunjang'] ?? [];
        }
        $this->incrementVersion('modal-pemeriksaan-rj');
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

                {{-- jika pemeriksaan ada --}}
                @if (isset($dataDaftarPoliRJ['pemeriksaan']))
                    <div class="w-full mb-1">
                        <div class="grid grid-cols-1">
                            <div id="TransaksiRawatJalan" class="px-2">
                                <div id="TransaksiRawatJalan" x-data="{ activeTab: 'Umum' }">

                                    {{-- TAB NAVIGATION --}}
                                    <div class="px-2 border-b border-gray-200 dark:border-gray-700">
                                        <ul
                                            class="flex flex-wrap -mb-px text-xs font-medium text-center text-gray-500 dark:text-gray-400">

                                            {{-- UMUM TAB --}}
                                            <li class="mr-2">
                                                <label
                                                    class="inline-block p-4 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                                    :class="activeTab === '{{ $dataDaftarPoliRJ['pemeriksaan']['umumTab'] ?? 'Umum' }}'
                                                        ?
                                                        'text-primary border-primary bg-gray-100' : ''"
                                                    @click="activeTab ='{{ $dataDaftarPoliRJ['pemeriksaan']['umumTab'] ?? 'Umum' }}'">
                                                    {{ $dataDaftarPoliRJ['pemeriksaan']['umumTab'] ?? 'Umum' }}
                                                </label>
                                            </li>

                                            {{-- FISIK TAB --}}
                                            <li class="mr-2">
                                                <label
                                                    class="inline-block p-4 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                                    :class="activeTab === 'Fisik' ?
                                                        'text-primary border-primary bg-gray-100' : ''"
                                                    @click="activeTab ='Fisik'">
                                                    Fisik
                                                </label>
                                            </li>

                                            {{-- ANATOMI TAB --}}
                                            <li class="mr-2">
                                                <label
                                                    class="inline-block p-4 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                                    :class="activeTab === 'Anatomi' ?
                                                        'text-primary border-primary bg-gray-100' : ''"
                                                    @click="activeTab ='Anatomi'">
                                                    Anatomi
                                                </label>
                                            </li>

                                            {{-- UJI FUNGSI TAB --}}
                                            <li class="mr-2">
                                                <label
                                                    class="inline-block p-4 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                                    :class="activeTab === 'UjiFungsi' ?
                                                        'text-primary border-primary bg-gray-100' : ''"
                                                    @click="activeTab ='UjiFungsi'">
                                                    Uji Fungsi
                                                </label>
                                            </li>

                                            {{-- PENUNJANG TAB --}}
                                            <li class="mr-2">
                                                <label
                                                    class="inline-block p-4 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                                    :class="activeTab === 'Penunjang' ?
                                                        'text-primary border-primary bg-gray-100' : ''"
                                                    @click="activeTab ='Penunjang'">
                                                    Penunjang
                                                </label>
                                            </li>

                                            {{-- PELAYANAN PENUNJANG TAB --}}
                                            <li class="mr-2">
                                                <label
                                                    class="inline-block p-4 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                                    :class="activeTab === 'PenunjangHasil' ?
                                                        'text-primary border-primary bg-gray-100' : ''"
                                                    @click="activeTab ='PenunjangHasil'">
                                                    Pelayanan Penunjang
                                                </label>
                                            </li>

                                            {{-- UPLOAD PENUNJANG TAB --}}
                                            <li class="mr-2">
                                                <label
                                                    class="inline-block p-4 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                                    :class="activeTab === 'UploadPenunjangHasil' ?
                                                        'text-primary border-primary bg-gray-100' : ''"
                                                    @click="activeTab ='UploadPenunjangHasil'">
                                                    Upload Penunjang
                                                </label>
                                            </li>
                                        </ul>
                                    </div>

                                    {{-- UMUM TAB CONTENT --}}
                                    {{-- UMUM TAB CONTENT --}}
                                    <div class="p-2 rounded-lg bg-gray-50 dark:bg-gray-800"
                                        :class="{
                                            'active': activeTab === '{{ $dataDaftarPoliRJ['pemeriksaan']['umumTab'] ?? 'Umum' }}'
                                        }"
                                        x-show.transition.in.opacity.duration.600="activeTab === '{{ $dataDaftarPoliRJ['pemeriksaan']['umumTab'] ?? 'Umum' }}'">
                                        @include('pages.transaksi.rj.emr-rj.pemeriksaan.tabs.umum-tab')
                                    </div>

                                    {{-- FISIK TAB CONTENT --}}
                                    <div class="p-2 rounded-lg bg-gray-50 dark:bg-gray-800"
                                        :class="{
                                            'active': activeTab === 'Fisik'
                                        }"
                                        x-show.transition.in.opacity.duration.600="activeTab === 'Fisik'">
                                        @include('pages.transaksi.rj.emr-rj.pemeriksaan.tabs.fisik-tab')
                                    </div>

                                    {{-- ANATOMI TAB CONTENT --}}
                                    <div class="p-2 rounded-lg bg-gray-50 dark:bg-gray-800"
                                        :class="{
                                            'active': activeTab === 'Anatomi'
                                        }"
                                        x-show.transition.in.opacity.duration.600="activeTab === 'Anatomi'">
                                        @include('pages.transaksi.rj.emr-rj.pemeriksaan.tabs.anatomi-tab')
                                    </div>

                                    {{-- UJI FUNGSI TAB CONTENT --}}
                                    <div class="p-2 rounded-lg bg-gray-50 dark:bg-gray-800"
                                        :class="{
                                            'active': activeTab === 'UjiFungsi'
                                        }"
                                        x-show.transition.in.opacity.duration.600="activeTab === 'UjiFungsi'">
                                        @include('pages.transaksi.rj.emr-rj.pemeriksaan.tabs.uji-fungsi-tab')
                                    </div>

                                    {{-- PENUNJANG TAB CONTENT --}}
                                    <div class="p-2 rounded-lg bg-gray-50 dark:bg-gray-800"
                                        :class="{
                                            'active': activeTab === 'Penunjang'
                                        }"
                                        x-show.transition.in.opacity.duration.600="activeTab === 'Penunjang'">
                                        @include('pages.transaksi.rj.emr-rj.pemeriksaan.tabs.penunjang-tab')
                                    </div>

                                    {{-- PELAYANAN PENUNJANG TAB CONTENT --}}
                                    <div class="p-2 rounded-lg bg-gray-50 dark:bg-gray-800"
                                        :class="{
                                            'active': activeTab === 'PenunjangHasil'
                                        }"
                                        x-show.transition.in.opacity.duration.600="activeTab === 'PenunjangHasil'">
                                        @include('pages.transaksi.rj.emr-rj.pemeriksaan.tabs.pelayanan-penunjang-tab')
                                    </div>

                                    {{-- PELAYANAN PENUNJANG TAB CONTENT --}}
                                    <div class="p-2 rounded-lg bg-gray-50 dark:bg-gray-800"
                                        :class="{
                                            'active': activeTab === 'UploadPenunjangHasil'
                                        }"
                                        x-show.transition.in.opacity.duration.600="activeTab === 'UploadPenunjangHasil'">
                                        @include('pages.transaksi.rj.emr-rj.pemeriksaan.tabs.upload-pelayanan-penunjang-tab')
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
