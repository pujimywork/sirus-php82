<?php
// resources/views/pages/transaksi/ugd/emr-ugd/penilaian/rm-penilaian-ugd-actions.blade.php

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
    protected array $renderAreas = ['modal-penilaian-ugd'];

    // ── Nyeri ──
    public array $formEntryNyeri = [];
    public array $nyeriMetodeOptions = [['nyeriMetode' => 'NRS'], ['nyeriMetode' => 'BPS'], ['nyeriMetode' => 'NIPS'], ['nyeriMetode' => 'FLACC'], ['nyeriMetode' => 'VAS']];
    public array $vasOptions = [['vas' => '0', 'active' => true], ['vas' => '1', 'active' => false], ['vas' => '2', 'active' => false], ['vas' => '3', 'active' => false], ['vas' => '4', 'active' => false], ['vas' => '5', 'active' => false], ['vas' => '6', 'active' => false], ['vas' => '7', 'active' => false], ['vas' => '8', 'active' => false], ['vas' => '9', 'active' => false], ['vas' => '10', 'active' => false]];
    public array $flaccOptions = [
        'face' => [['score' => 0, 'description' => 'Ekspresi wajah netral atau tersenyum', 'active' => false], ['score' => 1, 'description' => 'Sedikit cemberut, menarik diri', 'active' => false], ['score' => 2, 'description' => 'Meringis, rahang mengatup rapat', 'active' => false]],
        'legs' => [['score' => 0, 'description' => 'Posisi normal atau relaks', 'active' => false], ['score' => 1, 'description' => 'Gelisah, tegang, atau menarik kaki', 'active' => false], ['score' => 2, 'description' => 'Menendang atau kaki ditarik ke arah tubuh', 'active' => false]],
        'activity' => [['score' => 0, 'description' => 'Berbaring tenang, bergerak mudah', 'active' => false], ['score' => 1, 'description' => 'Menggeliat, bergerak bolak-balik, tegang', 'active' => false], ['score' => 2, 'description' => 'Melengkungkan tubuh, kaku, menggeliat hebat', 'active' => false]],
        'cry' => [['score' => 0, 'description' => 'Tidak menangis', 'active' => false], ['score' => 1, 'description' => 'Merintih atau mengerang, sesekali menangis', 'active' => false], ['score' => 2, 'description' => 'Menangis terus-menerus, berteriak', 'active' => false]],
        'consolability' => [['score' => 0, 'description' => 'Tenang, tidak perlu ditenangkan', 'active' => false], ['score' => 1, 'description' => 'Dapat ditenangkan dengan sentuhan', 'active' => false], ['score' => 2, 'description' => 'Sulit ditenangkan, terus menangis', 'active' => false]],
    ];

    // ── Resiko Jatuh ──
    public array $formEntryResikoJatuh = [];
    public array $skalaMorseOptions = [
        'riwayatJatuh' => [['riwayatJatuh' => 'Ya', 'score' => 25], ['riwayatJatuh' => 'Tidak', 'score' => 0]],
        'diagnosisSekunder' => [['diagnosisSekunder' => 'Ya', 'score' => 15], ['diagnosisSekunder' => 'Tidak', 'score' => 0]],
        'alatBantu' => [['alatBantu' => 'Tidak Ada / Bed Rest', 'score' => 0], ['alatBantu' => 'Tongkat / Alat Penopang / Walker', 'score' => 15], ['alatBantu' => 'Furnitur', 'score' => 30]],
        'terapiIV' => [['terapiIV' => 'Ya', 'score' => 20], ['terapiIV' => 'Tidak', 'score' => 0]],
        'gayaBerjalan' => [['gayaBerjalan' => 'Normal / Tirah Baring / Tidak Bergerak', 'score' => 0], ['gayaBerjalan' => 'Lemah', 'score' => 10], ['gayaBerjalan' => 'Terganggu', 'score' => 20]],
        'statusMental' => [['statusMental' => 'Baik', 'score' => 0], ['statusMental' => 'Lupa / Pelupa', 'score' => 15]],
    ];
    public array $humptyDumptyOptions = [
        'umur' => [['umur' => '< 3 tahun', 'score' => 4], ['umur' => '3-7 tahun', 'score' => 3], ['umur' => '7-13 tahun', 'score' => 2], ['umur' => '13-18 tahun', 'score' => 1]],
        'jenisKelamin' => [['jenisKelamin' => 'Laki-laki', 'score' => 2], ['jenisKelamin' => 'Perempuan', 'score' => 1]],
        'diagnosis' => [['diagnosis' => 'Diagnosis neurologis atau perkembangan', 'score' => 4], ['diagnosis' => 'Diagnosis ortopedi', 'score' => 3], ['diagnosis' => 'Diagnosis lainnya', 'score' => 2], ['diagnosis' => 'Tidak ada diagnosis khusus', 'score' => 1]],
        'gangguanKognitif' => [['gangguanKognitif' => 'Gangguan kognitif berat', 'score' => 3], ['gangguanKognitif' => 'Gangguan kognitif sedang', 'score' => 2], ['gangguanKognitif' => 'Gangguan kognitif ringan', 'score' => 1], ['gangguanKognitif' => 'Tidak ada gangguan kognitif', 'score' => 0]],
        'faktorLingkungan' => [['faktorLingkungan' => 'Lingkungan berisiko tinggi', 'score' => 3], ['faktorLingkungan' => 'Lingkungan berisiko sedang', 'score' => 2], ['faktorLingkungan' => 'Lingkungan berisiko rendah', 'score' => 1], ['faktorLingkungan' => 'Lingkungan aman', 'score' => 0]],
        'responObat' => [['responObat' => 'Efek samping obat yang meningkatkan risiko jatuh', 'score' => 3], ['responObat' => 'Efek samping obat ringan', 'score' => 2], ['responObat' => 'Tidak ada efek samping obat', 'score' => 1]],
    ];

    // ── Dekubitus ──
    public array $formEntryDekubitus = [];
    public array $bradenScaleOptions = [
        'sensoryPerception' => [['score' => 4, 'description' => 'Tidak ada gangguan sensorik'], ['score' => 3, 'description' => 'Gangguan sensorik ringan'], ['score' => 2, 'description' => 'Gangguan sensorik sedang'], ['score' => 1, 'description' => 'Gangguan sensorik berat']],
        'moisture' => [['score' => 4, 'description' => 'Kulit kering'], ['score' => 3, 'description' => 'Kulit lembab'], ['score' => 2, 'description' => 'Kulit basah'], ['score' => 1, 'description' => 'Kulit sangat basah']],
        'activity' => [['score' => 4, 'description' => 'Berjalan secara teratur'], ['score' => 3, 'description' => 'Berjalan dengan bantuan'], ['score' => 2, 'description' => 'Duduk di kursi'], ['score' => 1, 'description' => 'Terbaring di tempat tidur']],
        'mobility' => [['score' => 4, 'description' => 'Mobilitas penuh'], ['score' => 3, 'description' => 'Mobilitas sedikit terbatas'], ['score' => 2, 'description' => 'Mobilitas sangat terbatas'], ['score' => 1, 'description' => 'Tidak bisa bergerak']],
        'nutrition' => [['score' => 4, 'description' => 'Asupan nutrisi baik'], ['score' => 3, 'description' => 'Asupan nutrisi cukup'], ['score' => 2, 'description' => 'Asupan nutrisi kurang'], ['score' => 1, 'description' => 'Asupan nutrisi sangat kurang']],
        'frictionShear' => [['score' => 3, 'description' => 'Tidak ada masalah gesekan'], ['score' => 2, 'description' => 'Potensi masalah gesekan'], ['score' => 1, 'description' => 'Masalah gesekan signifikan']],
    ];

    // ── Gizi ──
    public array $formEntryGizi = [];
    public array $skriningGiziAwalOptions = [
        'perubahanBeratBadan' => [['perubahan' => 'Tidak ada perubahan', 'score' => 0], ['perubahan' => 'Turun 5-10%', 'score' => 1], ['perubahan' => 'Turun >10%', 'score' => 2]],
        'asupanMakanan' => [['asupan' => 'Cukup', 'score' => 0], ['asupan' => 'Kurang', 'score' => 1], ['asupan' => 'Sangat kurang', 'score' => 2]],
        'penyakit' => [['penyakit' => 'Tidak ada', 'score' => 0], ['penyakit' => 'Ringan', 'score' => 1], ['penyakit' => 'Berat', 'score' => 2]],
    ];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas(['modal-penilaian-ugd']);
        $this->formEntryNyeri = $this->defaultFormEntryNyeriState();
        $this->formEntryResikoJatuh = $this->defaultFormEntryResikoJatuhState();
        $this->formEntryDekubitus = $this->defaultFormEntryDekubitusState();
        $this->formEntryGizi = $this->defaultFormEntryGiziState();
    }

    public function rendering(): void
    {
        $default = $this->getDefaultPenilaian();
        $current = $this->dataDaftarUGD['penilaian'] ?? [];
        $this->dataDaftarUGD['penilaian'] = array_replace_recursive($default, $current);
    }

    /* ===============================
     | OPEN
     =============================== */
    #[On('open-rm-penilaian-ugd')]
    public function openPenilaian(int $rjNo): void
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
        $this->dataDaftarUGD['penilaian'] ??= $this->getDefaultPenilaian();

        $this->incrementVersion('modal-penilaian-ugd');

        if ($this->checkEmrUGDStatus($rjNo)) {
            $this->isFormLocked = true;
        }
    }

    /* ===============================
     | SAVE PENILAIAN — private helper
     | Dipanggil dari add/remove methods.
     | lockUGDRow() sudah ditangani di sini — caller tidak perlu lock sendiri.
     =============================== */
    private function savePenilaian(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only.');
            return;
        }

        try {
            DB::transaction(function () {
                // 1. Lock row dulu
                $this->lockUGDRow($this->rjNo);

                // 2. Baca data terkini setelah lock
                $data = $this->findDataUGD($this->rjNo) ?? [];

                if (empty($data)) {
                    throw new \RuntimeException('Data UGD tidak ditemukan, simpan dibatalkan.');
                }

                // 3. Patch hanya key penilaian
                $data['penilaian'] = $this->dataDaftarUGD['penilaian'] ?? [];

                $this->updateJsonUGD($this->rjNo, $data);
                $this->dataDaftarUGD = $data;
            });

            // 4. Notify + increment — di luar transaksi
            $this->incrementVersion('modal-penilaian-ugd');
            $this->dispatch('toast', type: 'success', message: 'Penilaian berhasil disimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | NYERI
     =============================== */
    public function setTglPenilaianNyeri(): void
    {
        $this->formEntryNyeri['tglPenilaian'] = Carbon::now()->format('d/m/Y H:i:s');
        $this->incrementVersion('modal-penilaian-ugd');
    }

    public function updateVasNyeriScore(int $score): void
    {
        foreach ($this->formEntryNyeri['nyeri']['nyeriMetode']['dataNyeri'] as &$opt) {
            $opt['active'] = (int) $opt['vas'] === $score;
        }
        $this->formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetodeScore'] = $score;
        $this->formEntryNyeri['nyeri']['nyeriKet'] = $this->getJenisNyeriVas($score);
    }

    public function updateFlaccScore(string $category, int $score): void
    {
        foreach ($this->formEntryNyeri['nyeri']['nyeriMetode']['dataNyeri'][$category] as &$item) {
            $item['active'] = $item['score'] === $score;
        }
        unset($item);

        $total = 0;
        foreach ($this->formEntryNyeri['nyeri']['nyeriMetode']['dataNyeri'] as $options) {
            foreach ($options as $opt) {
                if ($opt['active']) {
                    $total += $opt['score'];
                    break;
                }
            }
        }

        $this->formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetodeScore'] = $total;
        $this->formEntryNyeri['nyeri']['nyeriKet'] = match (true) {
            $total === 0 => 'Santai dan nyaman',
            $total <= 3 => 'Ketidaknyamanan ringan',
            $total <= 6 => 'Nyeri sedang',
            default => 'Nyeri berat',
        };
    }

    public function addAssessmentNyeri(): void
    {
        if ($this->isFormLocked) {
            return;
        }

        $this->formEntryNyeri['petugasPenilai'] = auth()->user()->myuser_name;
        $this->formEntryNyeri['petugasPenilaiCode'] = auth()->user()->myuser_code;

        try {
            $this->validate(
                [
                    'formEntryNyeri.tglPenilaian' => 'required|date_format:d/m/Y H:i:s',
                    'formEntryNyeri.petugasPenilai' => 'required|string|max:100',
                    'formEntryNyeri.petugasPenilaiCode' => 'required|string|max:50',
                    'formEntryNyeri.nyeri.nyeri' => 'required|in:Ya,Tidak',
                    'formEntryNyeri.nyeri.nyeriMetode.nyeriMetode' => 'required_if:formEntryNyeri.nyeri.nyeri,Ya|string|max:50',
                    'formEntryNyeri.nyeri.nyeriMetode.nyeriMetodeScore' => 'required_if:formEntryNyeri.nyeri.nyeri,Ya|numeric|min:0|max:100',
                ],
                [
                    'formEntryNyeri.tglPenilaian.required' => 'Tanggal penilaian wajib diisi.',
                    'formEntryNyeri.tglPenilaian.date_format' => 'Format tanggal harus dd/mm/yyyy hh:mi:ss.',
                    'formEntryNyeri.nyeri.nyeri.required' => 'Status nyeri wajib diisi.',
                    'formEntryNyeri.nyeri.nyeri.in' => 'Status nyeri hanya boleh "Ya" atau "Tidak".',
                    'formEntryNyeri.nyeri.nyeriMetode.nyeriMetode.required_if' => 'Metode nyeri wajib diisi jika ada nyeri.',
                    'formEntryNyeri.nyeri.nyeriMetode.nyeriMetodeScore.required_if' => 'Skor nyeri wajib diisi jika ada nyeri.',
                ],
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->dispatch('toast', type: 'error', message: collect($e->errors())->flatten()->first() ?? 'Periksa kembali data nyeri yang diisi.');
            return;
        }

        $this->dataDaftarUGD['penilaian']['nyeri'][] = $this->formEntryNyeri;
        $this->savePenilaian();
        $this->formEntryNyeri = $this->defaultFormEntryNyeriState();
    }

    public function removeAssessmentNyeri(int $index): void
    {
        if ($this->isFormLocked) {
            return;
        }

        if (isset($this->dataDaftarUGD['penilaian']['nyeri'][$index])) {
            array_splice($this->dataDaftarUGD['penilaian']['nyeri'], $index, 1);
            $this->savePenilaian();
        }
    }

    /* ===============================
     | RESIKO JATUH
     =============================== */
    public function setTglPenilaianResikoJatuh(): void
    {
        $this->formEntryResikoJatuh['tglPenilaian'] = Carbon::now()->format('d/m/Y H:i:s');
        $this->incrementVersion('modal-penilaian-ugd');
    }

    public function hitungSkorMorse(): void
    {
        $selected = $this->formEntryResikoJatuh['resikoJatuh']['resikoJatuhMetode']['dataResikoJatuh'] ?? [];
        $skor = 0;
        foreach ($this->skalaMorseOptions as $key => $options) {
            if (!isset($selected[$key])) {
                continue;
            }
            foreach ($options as $opt) {
                if (($opt[$key] ?? null) === $selected[$key]) {
                    $skor += (int) $opt['score'];
                    break;
                }
            }
        }
        $this->formEntryResikoJatuh['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetodeScore'] = $skor;
        $this->formEntryResikoJatuh['resikoJatuh']['kategoriResiko'] = match (true) {
            $skor >= 45 => 'Tinggi',
            $skor >= 25 => 'Sedang',
            default => 'Rendah',
        };
    }

    public function hitungSkorHumptyDumpty(): void
    {
        $selected = $this->formEntryResikoJatuh['resikoJatuh']['resikoJatuhMetode']['dataResikoJatuh'] ?? [];
        $skor = 0;
        foreach ($this->humptyDumptyOptions as $key => $options) {
            if (!isset($selected[$key])) {
                continue;
            }
            foreach ($options as $opt) {
                if (($opt[$key] ?? null) === $selected[$key]) {
                    $skor += (int) $opt['score'];
                    break;
                }
            }
        }
        $this->formEntryResikoJatuh['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetodeScore'] = $skor;
        $this->formEntryResikoJatuh['resikoJatuh']['kategoriResiko'] = match (true) {
            $skor >= 16 => 'Tinggi',
            $skor >= 12 => 'Sedang',
            default => 'Rendah',
        };
    }

    public function addAssessmentResikoJatuh(): void
    {
        if ($this->isFormLocked) {
            return;
        }

        $this->formEntryResikoJatuh['petugasPenilai'] = auth()->user()->myuser_name;
        $this->formEntryResikoJatuh['petugasPenilaiCode'] = auth()->user()->myuser_code;

        try {
            $this->validate(
                [
                    'formEntryResikoJatuh.tglPenilaian' => 'required|date_format:d/m/Y H:i:s',
                    'formEntryResikoJatuh.petugasPenilai' => 'required|string|max:100',
                    'formEntryResikoJatuh.petugasPenilaiCode' => 'required|string|max:50',
                    'formEntryResikoJatuh.resikoJatuh.resikoJatuh' => 'required|in:Ya,Tidak',
                    'formEntryResikoJatuh.resikoJatuh.resikoJatuhMetode.resikoJatuhMetode' => 'required_if:formEntryResikoJatuh.resikoJatuh.resikoJatuh,Ya|string|max:50',
                    'formEntryResikoJatuh.resikoJatuh.resikoJatuhMetode.resikoJatuhMetodeScore' => 'required_if:formEntryResikoJatuh.resikoJatuh.resikoJatuh,Ya|numeric|min:0',
                    'formEntryResikoJatuh.resikoJatuh.kategoriResiko' => 'nullable|string|max:100',
                    'formEntryResikoJatuh.resikoJatuh.rekomendasi' => 'nullable|string|max:500',
                ],
                [
                    'formEntryResikoJatuh.tglPenilaian.required' => 'Tanggal penilaian wajib diisi.',
                    'formEntryResikoJatuh.tglPenilaian.date_format' => 'Format tanggal harus dd/mm/yyyy hh:mi:ss.',
                    'formEntryResikoJatuh.resikoJatuh.resikoJatuh.required' => 'Status risiko jatuh wajib diisi.',
                    'formEntryResikoJatuh.resikoJatuh.resikoJatuh.in' => 'Risiko jatuh hanya boleh "Ya" atau "Tidak".',
                    'formEntryResikoJatuh.resikoJatuh.resikoJatuhMetode.resikoJatuhMetode.required_if' => 'Metode penilaian wajib diisi jika ada risiko jatuh.',
                    'formEntryResikoJatuh.resikoJatuh.resikoJatuhMetode.resikoJatuhMetodeScore.required_if' => 'Skor wajib diisi jika ada risiko jatuh.',
                ],
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->dispatch('toast', type: 'error', message: collect($e->errors())->flatten()->first() ?? 'Periksa kembali data risiko jatuh yang diisi.');
            return;
        }

        $this->dataDaftarUGD['penilaian']['resikoJatuh'][] = $this->formEntryResikoJatuh;
        $this->savePenilaian();
        $this->formEntryResikoJatuh = $this->defaultFormEntryResikoJatuhState();
    }

    public function removeAssessmentResikoJatuh(int $index): void
    {
        if ($this->isFormLocked) {
            return;
        }

        if (isset($this->dataDaftarUGD['penilaian']['resikoJatuh'][$index])) {
            array_splice($this->dataDaftarUGD['penilaian']['resikoJatuh'], $index, 1);
            $this->savePenilaian();
        }
    }

    /* ===============================
     | DEKUBITUS
     =============================== */
    public function setTglPenilaianDekubitus(): void
    {
        $this->formEntryDekubitus['tglPenilaian'] = Carbon::now()->format('d/m/Y H:i:s');
        $this->incrementVersion('modal-penilaian-ugd');
    }

    public function hitungSkorBraden(): void
    {
        $selected = $this->formEntryDekubitus['dekubitus']['dataBraden'] ?? [];
        $skor = 0;
        foreach ($this->bradenScaleOptions as $key => $options) {
            if (!isset($selected[$key])) {
                continue;
            }
            foreach ($options as $opt) {
                if ((int) $opt['score'] === (int) $selected[$key]) {
                    $skor += (int) $opt['score'];
                    break;
                }
            }
        }
        $this->formEntryDekubitus['dekubitus']['bradenScore'] = $skor;
        $this->formEntryDekubitus['dekubitus']['kategoriResiko'] = match (true) {
            $skor <= 12 => 'Sangat Tinggi',
            $skor <= 14 => 'Tinggi',
            $skor <= 18 => 'Sedang',
            default => 'Rendah',
        };
    }

    public function addAssessmentDekubitus(): void
    {
        if ($this->isFormLocked) {
            return;
        }

        $this->formEntryDekubitus['petugasPenilai'] = auth()->user()->myuser_name;
        $this->formEntryDekubitus['petugasPenilaiCode'] = auth()->user()->myuser_code;

        try {
            $this->validate(
                [
                    'formEntryDekubitus.tglPenilaian' => 'required|date_format:d/m/Y H:i:s',
                    'formEntryDekubitus.petugasPenilai' => 'required|string|max:100',
                    'formEntryDekubitus.petugasPenilaiCode' => 'required|string|max:50',
                    'formEntryDekubitus.dekubitus.dekubitus' => 'required|in:Ya,Tidak',
                ],
                [
                    'formEntryDekubitus.tglPenilaian.required' => 'Tanggal penilaian wajib diisi.',
                    'formEntryDekubitus.tglPenilaian.date_format' => 'Format tanggal harus dd/mm/yyyy hh:mi:ss.',
                    'formEntryDekubitus.dekubitus.dekubitus.required' => 'Status dekubitus wajib diisi.',
                    'formEntryDekubitus.dekubitus.dekubitus.in' => 'Status dekubitus hanya boleh "Ya" atau "Tidak".',
                ],
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->dispatch('toast', type: 'error', message: collect($e->errors())->flatten()->first() ?? 'Periksa kembali data yang diisi.');
            return;
        }

        $this->dataDaftarUGD['penilaian']['dekubitus'][] = $this->formEntryDekubitus;
        $this->savePenilaian();
        $this->formEntryDekubitus = $this->defaultFormEntryDekubitusState();
    }

    public function removeAssessmentDekubitus(int $index): void
    {
        if ($this->isFormLocked) {
            return;
        }

        if (isset($this->dataDaftarUGD['penilaian']['dekubitus'][$index])) {
            array_splice($this->dataDaftarUGD['penilaian']['dekubitus'], $index, 1);
            $this->savePenilaian();
        }
    }

    /* ===============================
     | GIZI
     =============================== */
    public function setTglPenilaianGizi(): void
    {
        $this->formEntryGizi['tglPenilaian'] = Carbon::now()->format('d/m/Y H:i:s');
        $this->incrementVersion('modal-penilaian-ugd');
    }

    public function hitungSkorSkriningGizi(): void
    {
        $selected = $this->formEntryGizi['gizi']['skriningGizi'] ?? [];
        $skor = 0;
        foreach ($this->skriningGiziAwalOptions as $key => $options) {
            if (!isset($selected[$key])) {
                continue;
            }
            foreach ($options as $opt) {
                foreach ($opt as $k => $v) {
                    if ($k !== 'score' && $v === $selected[$key]) {
                        $skor += (int) $opt['score'];
                        break;
                    }
                }
            }
        }
        $this->formEntryGizi['gizi']['skorSkrining'] = $skor;
        $this->formEntryGizi['gizi']['kategoriGizi'] = $skor >= 2 ? 'Berisiko Malnutrisi' : 'Normal';
    }

    public function addAssessmentGizi(): void
    {
        if ($this->isFormLocked) {
            return;
        }

        $this->formEntryGizi['petugasPenilai'] = auth()->user()->myuser_name;
        $this->formEntryGizi['petugasPenilaiCode'] = auth()->user()->myuser_code;

        try {
            $this->validate(
                [
                    'formEntryGizi.tglPenilaian' => 'required|date_format:d/m/Y H:i:s',
                    'formEntryGizi.petugasPenilai' => 'required|string|max:100',
                    'formEntryGizi.petugasPenilaiCode' => 'required|string|max:50',
                    'formEntryGizi.gizi.beratBadan' => 'required|numeric|min:1|max:500',
                    'formEntryGizi.gizi.tinggiBadan' => 'required|numeric|min:1|max:300',
                ],
                [
                    'formEntryGizi.tglPenilaian.required' => 'Tanggal penilaian wajib diisi.',
                    'formEntryGizi.tglPenilaian.date_format' => 'Format tanggal harus dd/mm/yyyy hh:mi:ss.',
                    'formEntryGizi.gizi.beratBadan.required' => 'Berat badan wajib diisi.',
                    'formEntryGizi.gizi.tinggiBadan.required' => 'Tinggi badan wajib diisi.',
                ],
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->dispatch('toast', type: 'error', message: collect($e->errors())->flatten()->first() ?? 'Periksa kembali data gizi yang diisi.');
            return;
        }

        $this->dataDaftarUGD['penilaian']['gizi'][] = $this->formEntryGizi;
        $this->savePenilaian();
        $this->formEntryGizi = $this->defaultFormEntryGiziState();
    }

    public function removeAssessmentGizi(int $index): void
    {
        if ($this->isFormLocked) {
            return;
        }

        if (isset($this->dataDaftarUGD['penilaian']['gizi'][$index])) {
            array_splice($this->dataDaftarUGD['penilaian']['gizi'], $index, 1);
            $this->savePenilaian();
        }
    }

    /* ===============================
     | UPDATED HOOKS
     =============================== */
    public function updated(string $property): void
    {
        if (in_array($property, ['formEntryGizi.gizi.beratBadan', 'formEntryGizi.gizi.tinggiBadan'])) {
            $this->hitungImt();
        }

        if (str_starts_with($property, 'formEntryGizi.gizi.skriningGizi')) {
            $this->hitungSkorSkriningGizi();
        }

        if (str_starts_with($property, 'formEntryDekubitus.dekubitus.dataBraden')) {
            $this->hitungSkorBraden();
        }

        if (str_starts_with($property, 'formEntryResikoJatuh.resikoJatuh.resikoJatuhMetode.dataResikoJatuh')) {
            match ($this->formEntryResikoJatuh['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetode'] ?? '') {
                'Skala Morse' => $this->hitungSkorMorse(),
                'Humpty Dumpty' => $this->hitungSkorHumptyDumpty(),
                default => null,
            };
        }

        if ($property === 'formEntryNyeri.nyeri.nyeriMetode.nyeriMetodeScore') {
            $metode = $this->formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetode'] ?? '';
            if (in_array($metode, ['NRS', 'BPS', 'NIPS'])) {
                $this->formEntryNyeri['nyeri']['nyeriKet'] = $this->getJenisNyeriVas((int) ($this->formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetodeScore'] ?? 0));
            }
        }

        if ($property === 'formEntryNyeri.nyeri.nyeriMetode.nyeriMetode') {
            $value = $this->formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetode'];
            $this->formEntryNyeri['nyeri']['nyeriMetode']['dataNyeri'] = [];
            $this->formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetodeScore'] = 0;
            $this->formEntryNyeri['nyeri']['nyeriKet'] = 'Tidak Nyeri';
            if ($value === 'VAS') {
                $this->formEntryNyeri['nyeri']['nyeriMetode']['dataNyeri'] = $this->vasOptions;
            }
            if ($value === 'FLACC') {
                $this->formEntryNyeri['nyeri']['nyeriMetode']['dataNyeri'] = $this->flaccOptions;
            }
        }

        if ($property === 'formEntryResikoJatuh.resikoJatuh.resikoJatuhMetode.resikoJatuhMetode') {
            $this->formEntryResikoJatuh['resikoJatuh']['resikoJatuhMetode']['dataResikoJatuh'] = [];
            $this->formEntryResikoJatuh['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetodeScore'] = 0;
            $this->formEntryResikoJatuh['resikoJatuh']['kategoriResiko'] = '';
        }
    }

    /* ===============================
     | DEFAULT STRUCTURES
     =============================== */
    private function getDefaultPenilaian(): array
    {
        return [
            'nyeri' => [],
            'resikoJatuh' => [],
            'dekubitus' => [],
            'gizi' => [],
            'statusPediatrik' => [],
            'diagnosis' => [],
        ];
    }

    private function defaultFormEntryNyeriState(): array
    {
        return [
            'tglPenilaian' => Carbon::now()->format('d/m/Y H:i:s'),
            'petugasPenilai' => '',
            'petugasPenilaiCode' => '',
            'nyeri' => [
                'nyeri' => 'Tidak',
                'nyeriMetode' => ['nyeriMetode' => '', 'nyeriMetodeScore' => 0, 'dataNyeri' => []],
                'nyeriKet' => '',
                'pencetus' => '',
                'durasi' => '',
                'lokasi' => '',
                'waktuNyeri' => '',
                'tingkatKesadaran' => '',
                'tingkatAktivitas' => '',
                'ketIntervensiFarmakologi' => '',
                'ketIntervensiNonFarmakologi' => '',
                'catatanTambahan' => '',
            ],
        ];
    }

    private function defaultFormEntryResikoJatuhState(): array
    {
        return [
            'tglPenilaian' => Carbon::now()->format('d/m/Y H:i:s'),
            'petugasPenilai' => '',
            'petugasPenilaiCode' => '',
            'resikoJatuh' => [
                'resikoJatuh' => 'Tidak',
                'resikoJatuhMetode' => ['resikoJatuhMetode' => '', 'resikoJatuhMetodeScore' => 0, 'dataResikoJatuh' => []],
                'kategoriResiko' => '',
                'rekomendasi' => '',
            ],
        ];
    }

    private function defaultFormEntryDekubitusState(): array
    {
        return [
            'tglPenilaian' => Carbon::now()->format('d/m/Y H:i:s'),
            'petugasPenilai' => '',
            'petugasPenilaiCode' => '',
            'dekubitus' => [
                'dekubitus' => 'Tidak',
                'bradenScore' => 0,
                'kategoriResiko' => '',
                'dataBraden' => [],
                'rekomendasi' => '',
            ],
        ];
    }

    private function defaultFormEntryGiziState(): array
    {
        return [
            'tglPenilaian' => Carbon::now()->format('d/m/Y H:i:s'),
            'petugasPenilai' => '',
            'petugasPenilaiCode' => '',
            'gizi' => [
                'skriningGizi' => [],
                'skorSkrining' => 0,
                'kategoriGizi' => '',
                'beratBadan' => '',
                'tinggiBadan' => '',
                'imt' => '',
                'kebutuhanGizi' => '',
                'catatan' => '',
            ],
        ];
    }

    /* ===============================
     | HELPERS
     =============================== */
    private function getJenisNyeriVas(int $score): string
    {
        return match (true) {
            $score === 0 => 'Tidak Nyeri',
            $score <= 3 => 'Nyeri Ringan',
            $score <= 6 => 'Nyeri Sedang',
            default => 'Nyeri Berat',
        };
    }

    private function hitungImt(): void
    {
        $bb = (float) ($this->formEntryGizi['gizi']['beratBadan'] ?? 0);
        $tb = (float) ($this->formEntryGizi['gizi']['tinggiBadan'] ?? 0);

        if ($bb > 0 && $tb > 0) {
            $tbM = $tb / 100;
            $this->formEntryGizi['gizi']['imt'] = round($bb / ($tbM * $tbM), 2);
        }
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->formEntryNyeri = $this->defaultFormEntryNyeriState();
        $this->formEntryResikoJatuh = $this->defaultFormEntryResikoJatuhState();
        $this->formEntryDekubitus = $this->defaultFormEntryDekubitusState();
        $this->formEntryGizi = $this->defaultFormEntryGiziState();
    }
};
?>

<div>
    <div class="flex flex-col w-full" wire:key="{{ $this->renderKey('modal-penilaian-ugd', [$rjNo ?? 'new']) }}">

        @if (isset($dataDaftarUGD['penilaian']))
            <div
                class="w-full p-4 space-y-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                <div x-data="{ activeTab: 'Nyeri' }" class="w-full">

                    {{-- TAB NAVIGATION --}}
                    <div class="w-full px-2 mb-2 border-b border-gray-200 dark:border-gray-700">
                        <ul
                            class="flex flex-wrap w-full -mb-px text-xs font-medium text-center text-gray-500 dark:text-gray-400">
                            @foreach (['Nyeri' => 'Nyeri', 'Risiko Jatuh' => 'Risiko Jatuh', 'Dekubitus' => 'Dekubitus', 'Gizi' => 'Gizi'] as $tab => $label)
                                <li class="mr-2">
                                    <label
                                        class="inline-block px-4 py-2 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-gray-600 hover:border-gray-300"
                                        :class="activeTab === '{{ $tab }}' ? 'text-primary border-primary bg-gray-100' :
                                            ''"
                                        @click="activeTab = '{{ $tab }}'">
                                        {{ $label }}
                                    </label>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    {{-- TAB CONTENTS --}}
                    <div class="w-full p-4">
                        <div class="w-full" x-show.transition.in.opacity.duration.600="activeTab === 'Nyeri'">
                            @include('pages.transaksi.ugd.emr-ugd.penilaian.tabs.nyeri-tab')
                        </div>
                        <div class="w-full" x-show.transition.in.opacity.duration.600="activeTab === 'Risiko Jatuh'">
                            @include('pages.transaksi.ugd.emr-ugd.penilaian.tabs.resiko-jatuh-tab')
                        </div>
                        <div class="w-full" x-show.transition.in.opacity.duration.600="activeTab === 'Dekubitus'">
                            @include('pages.transaksi.ugd.emr-ugd.penilaian.tabs.dekubitus-tab')
                        </div>
                        <div class="w-full" x-show.transition.in.opacity.duration.600="activeTab === 'Gizi'">
                            @include('pages.transaksi.ugd.emr-ugd.penilaian.tabs.gizi-tab')
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="flex items-center justify-center py-12 text-xs text-gray-400">
                Buka kunjungan terlebih dahulu untuk mengisi penilaian.
            </div>
        @endif

    </div>
</div>
