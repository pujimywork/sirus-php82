<?php
// resources/views/pages/transaksi/ri/emr-ri/penilaian/rm-penilaian-ri-actions.blade.php

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Carbon\Carbon;
use Livewire\Attributes\On;

new class extends Component {
    use EmrRITrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?string $riHdrNo = null;
    public array $dataDaftarRi = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-penilaian-ri'];

    /* ═══════════ FORM ENTRY NYERI ═══════════ */
    public array $formEntryNyeri = [
        'tglPenilaian' => '',
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
            'sistolik' => '',
            'distolik' => '',
            'frekuensiNafas' => '',
            'frekuensiNadi' => '',
            'suhu' => '',
            'ketIntervensiFarmakologi' => '',
            'ketIntervensiNonFarmakologi' => '',
            'catatanTambahan' => '',
        ],
    ];

    public array $nyeriMetodeOptions = [['nyeriMetode' => 'NRS'], ['nyeriMetode' => 'BPS'], ['nyeriMetode' => 'NIPS'], ['nyeriMetode' => 'FLACC'], ['nyeriMetode' => 'VAS']];

    public array $vasOptions = [['vas' => '0', 'active' => true], ['vas' => '1', 'active' => false], ['vas' => '2', 'active' => false], ['vas' => '3', 'active' => false], ['vas' => '4', 'active' => false], ['vas' => '5', 'active' => false], ['vas' => '6', 'active' => false], ['vas' => '7', 'active' => false], ['vas' => '8', 'active' => false], ['vas' => '9', 'active' => false], ['vas' => '10', 'active' => false]];

    public array $flaccOptions = [
        'face' => [['score' => 0, 'description' => 'Ekspresi wajah netral atau tersenyum', 'active' => false], ['score' => 1, 'description' => 'Ekspresi wajah sedikit cemberut, menarik diri', 'active' => false], ['score' => 2, 'description' => 'Ekspresi wajah meringis, rahang mengatup rapat', 'active' => false]],
        'legs' => [['score' => 0, 'description' => 'Posisi normal atau relaks', 'active' => false], ['score' => 1, 'description' => 'Gelisah, tegang, atau menarik kaki', 'active' => false], ['score' => 2, 'description' => 'Menendang, atau kaki ditarik ke arah tubuh', 'active' => false]],
        'activity' => [['score' => 0, 'description' => 'Berbaring tenang, posisi normal, bergerak dengan mudah', 'active' => false], ['score' => 1, 'description' => 'Menggeliat, bergerak bolak-balik, tegang', 'active' => false], ['score' => 2, 'description' => 'Melengkungkan tubuh, kaku, atau menggeliat hebat', 'active' => false]],
        'cry' => [['score' => 0, 'description' => 'Tidak menangis (tertidur atau terjaga)', 'active' => false], ['score' => 1, 'description' => 'Merintih atau mengerang, sesekali menangis', 'active' => false], ['score' => 2, 'description' => 'Menangis terus-menerus, berteriak, atau merintih', 'active' => false]],
        'consolability' => [['score' => 0, 'description' => 'Tenang, tidak perlu ditenangkan', 'active' => false], ['score' => 1, 'description' => 'Dapat ditenangkan dengan sentuhan atau pelukan', 'active' => false], ['score' => 2, 'description' => 'Sulit ditenangkan, terus menangis atau merintih', 'active' => false]],
    ];

    /* ═══════════ FORM ENTRY RISIKO JATUH ═══════════ */
    public array $formEntryResikoJatuh = [
        'tglPenilaian' => '',
        'petugasPenilai' => '',
        'petugasPenilaiCode' => '',
        'resikoJatuh' => [
            'resikoJatuh' => 'Tidak',
            'resikoJatuhMetode' => ['resikoJatuhMetode' => '', 'resikoJatuhMetodeScore' => 0, 'dataResikoJatuh' => []],
            'kategoriResiko' => '',
            'rekomendasi' => '',
        ],
    ];

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

    /* ═══════════ FORM ENTRY DEKUBITUS ═══════════ */
    public array $formEntryDekubitus = [
        'tglPenilaian' => '',
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

    public array $bradenScaleOptions = [
        'sensoryPerception' => [['score' => 4, 'description' => 'Tidak ada gangguan sensorik'], ['score' => 3, 'description' => 'Gangguan sensorik ringan'], ['score' => 2, 'description' => 'Gangguan sensorik sedang'], ['score' => 1, 'description' => 'Gangguan sensorik berat']],
        'moisture' => [['score' => 4, 'description' => 'Kulit kering'], ['score' => 3, 'description' => 'Kulit lembab'], ['score' => 2, 'description' => 'Kulit basah'], ['score' => 1, 'description' => 'Kulit sangat basah']],
        'activity' => [['score' => 4, 'description' => 'Berjalan secara teratur'], ['score' => 3, 'description' => 'Berjalan dengan bantuan'], ['score' => 2, 'description' => 'Duduk di kursi'], ['score' => 1, 'description' => 'Terbaring di tempat tidur']],
        'mobility' => [['score' => 4, 'description' => 'Mobilitas penuh'], ['score' => 3, 'description' => 'Mobilitas sedikit terbatas'], ['score' => 2, 'description' => 'Mobilitas sangat terbatas'], ['score' => 1, 'description' => 'Tidak bisa bergerak']],
        'nutrition' => [['score' => 4, 'description' => 'Asupan nutrisi baik'], ['score' => 3, 'description' => 'Asupan nutrisi cukup'], ['score' => 2, 'description' => 'Asupan nutrisi kurang'], ['score' => 1, 'description' => 'Asupan nutrisi sangat kurang']],
        'frictionShear' => [['score' => 3, 'description' => 'Tidak ada masalah gesekan atau geseran'], ['score' => 2, 'description' => 'Potensi masalah gesekan atau geseran'], ['score' => 1, 'description' => 'Masalah gesekan atau geseran yang signifikan']],
    ];

    /* ═══════════ FORM ENTRY GIZI ═══════════ */
    public array $formEntryGizi = [
        'tglPenilaian' => '',
        'petugasPenilai' => '',
        'petugasPenilaiCode' => '',
        'gizi' => [
            'beratBadan' => '',
            'tinggiBadan' => '',
            'imt' => '',
            'kebutuhanGizi' => '',
            'skorSkrining' => 0,
            'kategoriGizi' => '',
            'skriningGizi' => [],
            'catatan' => '',
        ],
    ];

    public array $skriningGiziAwalOptions = [
        'perubahanBeratBadan' => [['perubahan' => 'Tidak ada perubahan', 'score' => 0], ['perubahan' => 'Turun 5-10%', 'score' => 1], ['perubahan' => 'Turun >10%', 'score' => 2]],
        'asupanMakanan' => [['asupan' => 'Cukup', 'score' => 0], ['asupan' => 'Kurang', 'score' => 1], ['asupan' => 'Sangat kurang', 'score' => 2]],
        'penyakit' => [['penyakit' => 'Tidak ada', 'score' => 0], ['penyakit' => 'Ringan', 'score' => 1], ['penyakit' => 'Berat', 'score' => 2]],
    ];

    /* ═══════════ MOUNT ═══════════ */
    public function mount(): void
    {
        $this->registerAreas(['modal-penilaian-ri']);
    }

    #[On('open-rm-penilaian-ri')]
    public function open(string $riHdrNo): void
    {
        if (empty($riHdrNo)) {
            return;
        }
        $this->riHdrNo = $riHdrNo;
        $this->resetForm();
        $this->resetValidation();

        $data = $this->findDataRI($riHdrNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }

        $this->dataDaftarRi = $data;
        $this->dataDaftarRi['penilaian'] ??= ['nyeri' => [], 'resikoJatuh' => [], 'dekubitus' => [], 'gizi' => []];
        $this->incrementVersion('modal-penilaian-ri');

        $riStatus = DB::scalar('select ri_status from rstxn_rihdrs where rihdr_no=:r', ['r' => $riHdrNo]);
        $this->isFormLocked = $riStatus !== 'I';
    }

    /* ═══════════ HELPERS TANGGAL ═══════════ */
    public function setTglPenilaianNyeri(): void
    {
        $this->formEntryNyeri['tglPenilaian'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }
    public function setTglPenilaianResikoJatuh(): void
    {
        $this->formEntryResikoJatuh['tglPenilaian'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }
    public function setTglPenilaianDekubitus(): void
    {
        $this->formEntryDekubitus['tglPenilaian'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }
    public function setTglPenilaianGizi(): void
    {
        $this->formEntryGizi['tglPenilaian'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /* ═══════════ NYERI LOGIC ═══════════ */
    public function updatedFormEntryNyeriNyeriNyeriMetodeNyeriMetode(string $value): void
    {
        $this->formEntryNyeri['nyeri']['nyeriMetode']['dataNyeri'] = match ($value) {
            'VAS' => $this->vasOptions,
            'FLACC' => $this->flaccOptions,
            default => [],
        };
        $this->formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetodeScore'] = 0;
        $this->formEntryNyeri['nyeri']['nyeriKet'] = 'Tidak Nyeri';
    }

    public function updateVasNyeriScore(int $score): void
    {
        foreach ($this->formEntryNyeri['nyeri']['nyeriMetode']['dataNyeri'] as &$opt) {
            $opt['active'] = $opt['vas'] == $score;
        }
        $this->formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetodeScore'] = $score;
        $this->formEntryNyeri['nyeri']['nyeriKet'] = $score === 0 ? 'Tidak Nyeri' : ($score <= 3 ? 'Nyeri Ringan' : ($score <= 6 ? 'Nyeri Sedang' : 'Nyeri Berat'));
    }

    public function updateFlaccScore(string $category, int $score): void
    {
        foreach ($this->formEntryNyeri['nyeri']['nyeriMetode']['dataNyeri'][$category] as &$item) {
            $item['active'] = $item['score'] === $score;
        }
        $total = 0;
        foreach ($this->formEntryNyeri['nyeri']['nyeriMetode']['dataNyeri'] as $items) {
            foreach ($items as $item) {
                if ($item['active']) {
                    $total += $item['score'];
                    break;
                }
            }
        }
        $this->formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetodeScore'] = $total;
        $this->formEntryNyeri['nyeri']['nyeriKet'] = $total === 0 ? 'Santai dan nyaman' : ($total <= 3 ? 'Ketidaknyamanan ringan' : ($total <= 6 ? 'Nyeri sedang' : 'Nyeri berat'));
    }

    public function addAssessmentNyeri(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }
        $this->formEntryNyeri['petugasPenilai'] = auth()->user()->myuser_name;
        $this->formEntryNyeri['petugasPenilaiCode'] = auth()->user()->myuser_code;
        $this->validate([
            'formEntryNyeri.tglPenilaian' => 'required|date_format:d/m/Y H:i:s',
            'formEntryNyeri.nyeri.nyeri' => 'required|in:Ya,Tidak',
            'formEntryNyeri.nyeri.sistolik' => 'required|numeric|min:0|max:300',
            'formEntryNyeri.nyeri.distolik' => 'required|numeric|min:0|max:200',
            'formEntryNyeri.nyeri.frekuensiNafas' => 'required|numeric|min:0|max:100',
            'formEntryNyeri.nyeri.frekuensiNadi' => 'required|numeric|min:0|max:200',
            'formEntryNyeri.nyeri.suhu' => 'required|numeric|min:30|max:45',
        ]);
        try {
            $this->withRiLock(function () {
                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                $fresh['penilaian']['nyeri'][] = $this->formEntryNyeri;
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
            });
            $this->reset(['formEntryNyeri']);
            $this->afterSave('Penilaian Nyeri berhasil disimpan.');
        } catch (LockTimeoutException) {
            $this->dispatch('toast', type: 'error', message: 'Sistem sibuk.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    public function removeAssessmentNyeri(int $index): void
    {
        if ($this->isFormLocked) {
            return;
        }
        try {
            $this->withRiLock(function () use ($index) {
                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                array_splice($fresh['penilaian']['nyeri'], $index, 1);
                $fresh['penilaian']['nyeri'] = array_values($fresh['penilaian']['nyeri']);
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
            });
            $this->afterSave('Penilaian Nyeri dihapus.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ═══════════ RISIKO JATUH LOGIC ═══════════ */
    public function updatedFormEntryResikoJatuhResikoJatuhResikoJatuhMetodeResikoJatuhMetode(): void
    {
        $this->formEntryResikoJatuh['resikoJatuh']['resikoJatuhMetode']['dataResikoJatuh'] = [];
        $this->formEntryResikoJatuh['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetodeScore'] = 0;
        $this->formEntryResikoJatuh['resikoJatuh']['kategoriResiko'] = '';
    }

    public function updatedFormEntryResikoJatuhResikoJatuhResikoJatuhMetodeDataResikoJatuh(): void
    {
        $metode = $this->formEntryResikoJatuh['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetode'] ?? '';
        $selected = $this->formEntryResikoJatuh['resikoJatuh']['resikoJatuhMetode']['dataResikoJatuh'] ?? [];
        $options = $metode === 'Skala Morse' ? $this->skalaMorseOptions : $this->humptyDumptyOptions;
        $skor = 0;
        foreach ($options as $key => $opts) {
            if (!isset($selected[$key])) {
                continue;
            }
            foreach ($opts as $opt) {
                if (($opt[$key] ?? null) === $selected[$key]) {
                    $skor += (int) $opt['score'];
                    break;
                }
            }
        }
        $this->formEntryResikoJatuh['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetodeScore'] = $skor;
        $this->formEntryResikoJatuh['resikoJatuh']['kategoriResiko'] = $metode === 'Skala Morse' ? ($skor >= 45 ? 'Tinggi' : ($skor >= 25 ? 'Sedang' : 'Rendah')) : ($skor >= 16 ? 'Tinggi' : ($skor >= 12 ? 'Sedang' : 'Rendah'));
    }

    public function addAssessmentResikoJatuh(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }
        $this->formEntryResikoJatuh['petugasPenilai'] = auth()->user()->myuser_name;
        $this->formEntryResikoJatuh['petugasPenilaiCode'] = auth()->user()->myuser_code;
        $this->validate([
            'formEntryResikoJatuh.tglPenilaian' => 'required|date_format:d/m/Y H:i:s',
            'formEntryResikoJatuh.resikoJatuh.resikoJatuh' => 'required|in:Ya,Tidak',
            'formEntryResikoJatuh.resikoJatuh.resikoJatuhMetode.resikoJatuhMetode' => 'required_if:formEntryResikoJatuh.resikoJatuh.resikoJatuh,Ya|string',
        ]);
        try {
            $this->withRiLock(function () {
                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                $fresh['penilaian']['resikoJatuh'][] = $this->formEntryResikoJatuh;
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
            });
            $this->reset(['formEntryResikoJatuh']);
            $this->afterSave('Penilaian Risiko Jatuh berhasil disimpan.');
        } catch (LockTimeoutException) {
            $this->dispatch('toast', type: 'error', message: 'Sistem sibuk.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    public function removeAssessmentResikoJatuh(int $index): void
    {
        if ($this->isFormLocked) {
            return;
        }
        try {
            $this->withRiLock(function () use ($index) {
                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                array_splice($fresh['penilaian']['resikoJatuh'], $index, 1);
                $fresh['penilaian']['resikoJatuh'] = array_values($fresh['penilaian']['resikoJatuh']);
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
            });
            $this->afterSave('Risiko Jatuh dihapus.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ═══════════ DEKUBITUS LOGIC ═══════════ */
    public function updatedFormEntryDekubitusDekubitusDatBraden(): void
    {
        $this->hitungSkorBraden();
    }

    public function hitungSkorBraden(): void
    {
        $data = $this->formEntryDekubitus['dekubitus']['dataBraden'] ?? [];
        $skor = 0;
        foreach ($this->bradenScaleOptions as $key => $opts) {
            if (!isset($data[$key])) {
                continue;
            }
            foreach ($opts as $opt) {
                if ((string) $opt['score'] === (string) $data[$key]) {
                    $skor += (int) $opt['score'];
                    break;
                }
            }
        }
        $this->formEntryDekubitus['dekubitus']['bradenScore'] = $skor;
        $this->formEntryDekubitus['dekubitus']['kategoriResiko'] = $skor <= 12 ? 'Sangat Tinggi' : ($skor <= 14 ? 'Tinggi' : ($skor <= 18 ? 'Sedang' : 'Rendah'));
    }

    public function updatedFormEntryDekubitusDekubitusDataBraden(): void
    {
        $this->hitungSkorBraden();
    }

    public function addAssessmentDekubitus(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }
        $this->formEntryDekubitus['petugasPenilai'] = auth()->user()->myuser_name;
        $this->formEntryDekubitus['petugasPenilaiCode'] = auth()->user()->myuser_code;
        $this->validate([
            'formEntryDekubitus.tglPenilaian' => 'required|date_format:d/m/Y H:i:s',
            'formEntryDekubitus.dekubitus.dekubitus' => 'required|in:Ya,Tidak',
        ]);
        try {
            $this->withRiLock(function () {
                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                $fresh['penilaian']['dekubitus'][] = $this->formEntryDekubitus;
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
            });
            $this->reset(['formEntryDekubitus']);
            $this->afterSave('Penilaian Dekubitus berhasil disimpan.');
        } catch (LockTimeoutException) {
            $this->dispatch('toast', type: 'error', message: 'Sistem sibuk.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    public function removeAssessmentDekubitus(int $index): void
    {
        if ($this->isFormLocked) {
            return;
        }
        try {
            $this->withRiLock(function () use ($index) {
                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                array_splice($fresh['penilaian']['dekubitus'], $index, 1);
                $fresh['penilaian']['dekubitus'] = array_values($fresh['penilaian']['dekubitus']);
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
            });
            $this->afterSave('Dekubitus dihapus.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ═══════════ GIZI LOGIC ═══════════ */
    public function updatedFormEntryGiziGiziBeratBadan(): void
    {
        $this->hitungImt();
    }
    public function updatedFormEntryGiziGiziTinggiBadan(): void
    {
        $this->hitungImt();
    }

    private function hitungImt(): void
    {
        $bb = (float) ($this->formEntryGizi['gizi']['beratBadan'] ?? 0);
        $tb = (float) ($this->formEntryGizi['gizi']['tinggiBadan'] ?? 0);
        if ($bb > 0 && $tb > 0) {
            $this->formEntryGizi['gizi']['imt'] = round($bb / pow($tb / 100, 2), 2);
        }
    }

    public function updatedFormEntryGiziGiziSkriningGizi(): void
    {
        $this->hitungSkorGizi();
    }

    private function hitungSkorGizi(): void
    {
        $selected = $this->formEntryGizi['gizi']['skriningGizi'] ?? [];
        $fieldKeys = ['perubahanBeratBadan' => 'perubahan', 'asupanMakanan' => 'asupan', 'penyakit' => 'penyakit'];
        $skor = 0;
        foreach ($this->skriningGiziAwalOptions as $key => $opts) {
            if (!isset($selected[$key])) {
                continue;
            }
            $fk = $fieldKeys[$key] ?? $key;
            foreach ($opts as $opt) {
                if (($opt[$fk] ?? null) === $selected[$key]) {
                    $skor += (int) $opt['score'];
                    break;
                }
            }
        }
        $this->formEntryGizi['gizi']['skorSkrining'] = $skor;
        $this->formEntryGizi['gizi']['kategoriGizi'] = $skor >= 2 ? 'Berisiko Malnutrisi' : 'Normal';
    }

    public function addAssessmentGizi(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }
        $this->formEntryGizi['petugasPenilai'] = auth()->user()->myuser_name;
        $this->formEntryGizi['petugasPenilaiCode'] = auth()->user()->myuser_code;
        $this->validate([
            'formEntryGizi.tglPenilaian' => 'required|date_format:d/m/Y H:i:s',
            'formEntryGizi.gizi.beratBadan' => 'required|numeric|min:1',
            'formEntryGizi.gizi.tinggiBadan' => 'required|numeric|min:1',
        ]);
        try {
            $this->withRiLock(function () {
                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                $fresh['penilaian']['gizi'][] = $this->formEntryGizi;
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
            });
            $this->reset(['formEntryGizi']);
            $this->afterSave('Penilaian Gizi berhasil disimpan.');
        } catch (LockTimeoutException) {
            $this->dispatch('toast', type: 'error', message: 'Sistem sibuk.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    public function removeAssessmentGizi(int $index): void
    {
        if ($this->isFormLocked) {
            return;
        }
        try {
            $this->withRiLock(function () use ($index) {
                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                array_splice($fresh['penilaian']['gizi'], $index, 1);
                $fresh['penilaian']['gizi'] = array_values($fresh['penilaian']['gizi']);
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
            });
            $this->afterSave('Gizi dihapus.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ── helpers ── */
    private function afterSave(string $msg): void
    {
        $this->incrementVersion('modal-penilaian-ri');
        $this->dispatch('toast', type: 'success', message: $msg);
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->reset(['formEntryNyeri', 'formEntryResikoJatuh', 'formEntryDekubitus', 'formEntryGizi']);
    }

    private function withRiLock(callable $fn): void
    {
        Cache::lock("ri:{$this->riHdrNo}", 10)->block(5, function () use ($fn) {
            DB::transaction(function () use ($fn) {
                $this->lockRIRow($this->riHdrNo);
                $fn();
            }, 5);
        });
    }
};
?>

<div class="space-y-0" wire:key="{{ $this->renderKey('modal-penilaian-ri', [$riHdrNo ?? 'new']) }}"
    x-data="{ activeTab: 'nyeri' }">

    @if ($isFormLocked)
        <div
            class="flex items-center gap-2 px-4 py-2.5 mb-3 rounded-lg
                    bg-amber-50 border border-amber-200 text-amber-800
                    dark:bg-amber-900/20 dark:border-amber-700 dark:text-amber-300 text-sm">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
            </svg>
            Pasien sudah pulang — form dalam mode <strong>read-only</strong>.
        </div>
    @endif

    {{-- ── TAB NAV ── --}}
    <div class="border-b border-gray-200 dark:border-gray-700 mb-4">
        <ul class="flex flex-wrap -mb-px text-xs font-medium text-gray-500 dark:text-gray-400">
            @foreach ([['key' => 'nyeri', 'label' => 'Penilaian Nyeri'], ['key' => 'resikoJatuh', 'label' => 'Risiko Jatuh'], ['key' => 'dekubitus', 'label' => 'Dekubitus'], ['key' => 'gizi', 'label' => 'Gizi']] as $tab)
                <li class="mr-2">
                    <button type="button" @click="activeTab = '{{ $tab['key'] }}'"
                        :class="activeTab === '{{ $tab['key'] }}'
                            ?
                            'text-brand border-brand bg-brand/5 font-semibold' :
                            'border-transparent hover:text-gray-600 hover:border-gray-300'"
                        class="inline-flex items-center px-4 py-2.5 border-b-2 rounded-t-lg transition-colors">
                        {{ $tab['label'] }}
                    </button>
                </li>
            @endforeach
        </ul>
    </div>

    {{-- ══════════════════════════════════════
    | TAB NYERI
    ══════════════════════════════════════ --}}
    <div x-show="activeTab === 'nyeri'" x-transition.opacity.duration.200ms class="space-y-4">

        @if (!$isFormLocked)
            <x-border-form title="Tambah Penilaian Nyeri" align="start" bgcolor="bg-gray-50">
                <div class="mt-4 space-y-4">

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label value="Tanggal Penilaian *" />
                            <div class="flex gap-2 mt-1">
                                <x-text-input wire:model="formEntryNyeri.tglPenilaian" placeholder="dd/mm/yyyy hh:ii:ss"
                                    :error="$errors->has('formEntryNyeri.tglPenilaian')" class="w-full" />
                                <x-secondary-button wire:click="setTglPenilaianNyeri" type="button"
                                    class="whitespace-nowrap text-xs">
                                    Sekarang
                                </x-secondary-button>
                            </div>
                            <x-input-error :messages="$errors->get('formEntryNyeri.tglPenilaian')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Status Nyeri *" />
                            <x-select-input wire:model.live="formEntryNyeri.nyeri.nyeri" class="w-full mt-1">
                                <option value="Tidak">Tidak</option>
                                <option value="Ya">Ya</option>
                            </x-select-input>
                        </div>
                    </div>

                    @if ($formEntryNyeri['nyeri']['nyeri'] === 'Ya')
                        <div>
                            <x-input-label value="Metode Penilaian *" />
                            <x-select-input wire:model.live="formEntryNyeri.nyeri.nyeriMetode.nyeriMetode"
                                class="w-full mt-1">
                                <option value="">-- Pilih Metode --</option>
                                @foreach ($nyeriMetodeOptions as $opt)
                                    <option value="{{ $opt['nyeriMetode'] }}">{{ $opt['nyeriMetode'] }}</option>
                                @endforeach
                            </x-select-input>
                        </div>

                        @if ($formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetode'])
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="px-3 py-1 text-xs font-bold text-white rounded-lg bg-brand">
                                    Skor: {{ $formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetodeScore'] }}
                                </span>
                                @if ($formEntryNyeri['nyeri']['nyeriKet'])
                                    @php $ket = $formEntryNyeri['nyeri']['nyeriKet']; @endphp
                                    <span
                                        class="px-2 py-0.5 text-xs font-bold rounded-full
                                        {{ str_contains(strtolower($ket), 'berat')
                                            ? 'bg-red-100 text-red-700'
                                            : (str_contains(strtolower($ket), 'sedang')
                                                ? 'bg-yellow-100 text-yellow-700'
                                                : (str_contains(strtolower($ket), 'ringan')
                                                    ? 'bg-orange-100 text-orange-700'
                                                    : 'bg-green-100 text-green-700')) }}">
                                        {{ $ket }}
                                    </span>
                                @endif
                            </div>
                        @endif

                        {{-- NRS --}}
                        @if ($formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetode'] === 'NRS')
                            <x-border-form title="Numeric Rating Scale (NRS)" align="start" bgcolor="bg-white">
                                <div class="mt-3">
                                    <p class="text-xs text-gray-400 mb-2">Interpretasi: 0 Tidak Nyeri | 1–3 Ringan | 4–6
                                        Sedang | 7–10 Berat</p>
                                    <x-input-label value="Skor NRS (0–10) *" />
                                    <x-text-input type="number" min="0" max="10"
                                        wire:model.live="formEntryNyeri.nyeri.nyeriMetode.nyeriMetodeScore"
                                        class="w-32 mt-1" />
                                </div>
                            </x-border-form>
                        @endif

                        {{-- VAS --}}
                        @if ($formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetode'] === 'VAS')
                            <x-border-form title="Visual Analog Scale (VAS)" align="start" bgcolor="bg-white">
                                <div class="mt-3">
                                    <p class="text-xs text-gray-400 mb-2">Interpretasi: 0 Tidak Nyeri | 1–3 Ringan | 4–6
                                        Sedang | 7–10 Berat</p>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($formEntryNyeri['nyeri']['nyeriMetode']['dataNyeri'] as $opt)
                                            <button type="button" wire:click="updateVasNyeriScore({{ $opt['vas'] }})"
                                                class="w-10 h-10 text-xs font-bold rounded-lg border-2 transition
                                                    {{ $opt['active'] ? 'border-brand bg-brand text-white' : 'border-gray-300 bg-white text-gray-600 hover:border-brand hover:text-brand' }}">
                                                {{ $opt['vas'] }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            </x-border-form>
                        @endif

                        {{-- FLACC --}}
                        @if ($formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetode'] === 'FLACC')
                            <x-border-form title="FLACC Scale" align="start" bgcolor="bg-white">
                                <div class="mt-3 space-y-3">
                                    <p class="text-xs text-gray-400">Interpretasi: 0 Santai | 1–3 Ringan | 4–6 Sedang |
                                        7–10 Berat</p>
                                    @foreach ($formEntryNyeri['nyeri']['nyeriMetode']['dataNyeri'] as $category => $items)
                                        <div>
                                            <x-input-label :value="ucwords($category)" />
                                            <div class="flex flex-wrap gap-2 mt-1">
                                                @foreach ($items as $item)
                                                    <button type="button"
                                                        wire:click="updateFlaccScore('{{ $category }}', {{ $item['score'] }})"
                                                        class="px-3 py-1.5 text-xs rounded-lg border-2 transition
                                                            {{ $item['active'] ? 'border-brand bg-brand text-white' : 'border-gray-300 bg-white text-gray-600 hover:border-brand hover:text-brand' }}">
                                                        <span class="font-bold">{{ $item['score'] }}</span> —
                                                        {{ $item['description'] }}
                                                    </button>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </x-border-form>
                        @endif

                        {{-- BPS / NIPS --}}
                        @if (in_array($formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetode'], ['BPS', 'NIPS']))
                            <x-border-form :title="$formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetode']" align="start" bgcolor="bg-white">
                                <div class="mt-3">
                                    <x-input-label value="Skor *" />
                                    <x-text-input type="number" min="0"
                                        wire:model.live="formEntryNyeri.nyeri.nyeriMetode.nyeriMetodeScore"
                                        class="w-32 mt-1" />
                                </div>
                            </x-border-form>
                        @endif

                        {{-- Detail Nyeri --}}
                        <x-border-form title="Detail Nyeri" align="start" bgcolor="bg-white">
                            <div class="mt-3 grid grid-cols-2 gap-3">
                                <div>
                                    <x-input-label value="Pencetus" />
                                    <x-text-input wire:model="formEntryNyeri.nyeri.pencetus" class="w-full mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Durasi" />
                                    <x-text-input wire:model="formEntryNyeri.nyeri.durasi" placeholder="30 menit"
                                        class="w-full mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Lokasi" />
                                    <x-text-input wire:model="formEntryNyeri.nyeri.lokasi" class="w-full mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Waktu Nyeri" />
                                    <x-text-input wire:model="formEntryNyeri.nyeri.waktuNyeri"
                                        placeholder="Malam hari" class="w-full mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Tingkat Kesadaran" />
                                    <x-select-input wire:model="formEntryNyeri.nyeri.tingkatKesadaran"
                                        class="w-full mt-1">
                                        <option value="">-- Pilih --</option>
                                        @foreach (['Composmentis', 'Apatis', 'Somnolen', 'Stupor', 'Koma'] as $opt)
                                            <option value="{{ $opt }}">{{ $opt }}</option>
                                        @endforeach
                                    </x-select-input>
                                </div>
                                <div>
                                    <x-input-label value="Tingkat Aktivitas" />
                                    <x-select-input wire:model="formEntryNyeri.nyeri.tingkatAktivitas"
                                        class="w-full mt-1">
                                        <option value="">-- Pilih --</option>
                                        @foreach (['Mandiri', 'Dibantu Sebagian', 'Dibantu Penuh'] as $opt)
                                            <option value="{{ $opt }}">{{ $opt }}</option>
                                        @endforeach
                                    </x-select-input>
                                </div>
                            </div>
                        </x-border-form>

                        {{-- TTV --}}
                        <x-border-form title="Tanda-Tanda Vital" align="start" bgcolor="bg-white">
                            <div class="mt-3 grid grid-cols-3 gap-3">
                                @foreach ([['key' => 'sistolik', 'label' => 'Sistolik (mmHg)'], ['key' => 'distolik', 'label' => 'Diastolik (mmHg)'], ['key' => 'frekuensiNafas', 'label' => 'Frek. Nafas (x/mnt)'], ['key' => 'frekuensiNadi', 'label' => 'Frek. Nadi (x/mnt)'], ['key' => 'suhu', 'label' => 'Suhu (°C)']] as $f)
                                    <div>
                                        <x-input-label value="{{ $f['label'] }} *" />
                                        <x-text-input type="number" step="any"
                                            wire:model="formEntryNyeri.nyeri.{{ $f['key'] }}" :error="$errors->has('formEntryNyeri.nyeri.' . $f['key'])"
                                            class="w-full mt-1" />
                                        <x-input-error :messages="$errors->get('formEntryNyeri.nyeri.' . $f['key'])" class="mt-1" />
                                    </div>
                                @endforeach
                            </div>
                        </x-border-form>

                        {{-- Intervensi --}}
                        <x-border-form title="Intervensi & Catatan" align="start" bgcolor="bg-white">
                            <div class="mt-3 space-y-3">
                                <div>
                                    <x-input-label value="Intervensi Farmakologi" />
                                    <x-textarea wire:model="formEntryNyeri.nyeri.ketIntervensiFarmakologi"
                                        class="w-full mt-1" rows="2" placeholder="Nama obat, dosis, rute..." />
                                </div>
                                <div>
                                    <x-input-label value="Intervensi Non-Farmakologi" />
                                    <x-textarea wire:model="formEntryNyeri.nyeri.ketIntervensiNonFarmakologi"
                                        class="w-full mt-1" rows="2"
                                        placeholder="Kompres, relaksasi, distraksi..." />
                                </div>
                                <div>
                                    <x-input-label value="Catatan Tambahan" />
                                    <x-textarea wire:model="formEntryNyeri.nyeri.catatanTambahan" class="w-full mt-1"
                                        rows="2" />
                                </div>
                            </div>
                        </x-border-form>
                    @endif

                    <div class="flex justify-end">
                        <x-primary-button wire:click="addAssessmentNyeri" wire:loading.attr="disabled"
                            wire:target="addAssessmentNyeri">
                            <span wire:loading.remove wire:target="addAssessmentNyeri">Simpan Penilaian Nyeri</span>
                            <span wire:loading wire:target="addAssessmentNyeri"
                                class="flex items-center gap-1"><x-loading /> Menyimpan...</span>
                        </x-primary-button>
                    </div>
                </div>
            </x-border-form>
        @endif

        {{-- Riwayat Nyeri --}}
        @if (!empty($dataDaftarRi['penilaian']['nyeri']))
            <x-border-form title="Riwayat Penilaian Nyeri" align="start" bgcolor="bg-white">
                <div class="mt-3 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="w-full text-xs text-left text-gray-600 dark:text-gray-300">
                        <thead class="bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="px-3 py-2">Tgl Penilaian</th>
                                <th class="px-3 py-2">Petugas</th>
                                <th class="px-3 py-2">Nyeri</th>
                                <th class="px-3 py-2">Metode</th>
                                <th class="px-3 py-2">Skor</th>
                                <th class="px-3 py-2">Keterangan</th>
                                <th class="px-3 py-2">TD</th>
                                <th class="px-3 py-2">Nadi</th>
                                <th class="px-3 py-2">Nafas</th>
                                <th class="px-3 py-2">Suhu</th>
                                @if (!$isFormLocked)
                                    <th class="px-3 py-2"></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach (array_reverse($dataDaftarRi['penilaian']['nyeri'] ?? [], true) as $i => $row)
                                @php
                                    $ket = $row['nyeri']['nyeriKet'] ?? '-';
                                    $rowBg = str_contains(strtolower($ket), 'berat')
                                        ? 'bg-red-50 hover:bg-red-100'
                                        : (str_contains(strtolower($ket), 'sedang')
                                            ? 'bg-yellow-50 hover:bg-yellow-100'
                                            : (str_contains(strtolower($ket), 'ringan')
                                                ? 'bg-orange-50 hover:bg-orange-100'
                                                : 'bg-green-50 hover:bg-green-100'));
                                @endphp
                                <tr class="{{ $rowBg }}">
                                    <td class="px-3 py-2 whitespace-nowrap">{{ $row['tglPenilaian'] ?? '-' }}</td>
                                    <td class="px-3 py-2">{{ $row['petugasPenilai'] ?? '-' }}</td>
                                    <td class="px-3 py-2">
                                        <span
                                            class="px-2 py-0.5 rounded-full text-xs font-medium {{ ($row['nyeri']['nyeri'] ?? '') == 'Ya' ? 'bg-orange-100 text-orange-700' : 'bg-green-100 text-green-700' }}">
                                            {{ $row['nyeri']['nyeri'] ?? '-' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2">{{ $row['nyeri']['nyeriMetode']['nyeriMetode'] ?? '-' }}</td>
                                    <td class="px-3 py-2 font-bold">
                                        {{ $row['nyeri']['nyeriMetode']['nyeriMetodeScore'] ?? '-' }}</td>
                                    <td class="px-3 py-2">
                                        @if ($ket !== '-')
                                            <span
                                                class="px-2 py-0.5 rounded-full text-xs font-medium
                                                {{ str_contains(strtolower($ket), 'berat')
                                                    ? 'bg-red-100 text-red-700'
                                                    : (str_contains(strtolower($ket), 'sedang')
                                                        ? 'bg-yellow-100 text-yellow-700'
                                                        : (str_contains(strtolower($ket), 'ringan')
                                                            ? 'bg-orange-100 text-orange-700'
                                                            : 'bg-green-100 text-green-700')) }}">
                                                {{ $ket }}
                                            </span>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        {{ ($row['nyeri']['sistolik'] ?? '-') . '/' . ($row['nyeri']['distolik'] ?? '-') }}
                                    </td>
                                    <td class="px-3 py-2">{{ $row['nyeri']['frekuensiNadi'] ?? '-' }}</td>
                                    <td class="px-3 py-2">{{ $row['nyeri']['frekuensiNafas'] ?? '-' }}</td>
                                    <td class="px-3 py-2">{{ $row['nyeri']['suhu'] ?? '-' }}</td>
                                    @if (!$isFormLocked)
                                        <td class="px-3 py-2">
                                            <x-icon-button variant="danger"
                                                wire:click="removeAssessmentNyeri({{ $i }})"
                                                wire:confirm="Hapus data nyeri ini?">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </x-icon-button>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-border-form>
        @else
            <p class="text-xs text-center text-gray-400 py-6">Belum ada data penilaian nyeri.</p>
        @endif
    </div>

    {{-- ══════════════════════════════════════
    | TAB RISIKO JATUH
    ══════════════════════════════════════ --}}
    <div x-show="activeTab === 'resikoJatuh'" x-transition.opacity.duration.200ms class="space-y-4">

        @if (!$isFormLocked)
            <x-border-form title="Tambah Penilaian Risiko Jatuh" align="start" bgcolor="bg-gray-50">
                <div class="mt-4 space-y-4">

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label value="Tanggal Penilaian *" />
                            <div class="flex gap-2 mt-1">
                                <x-text-input wire:model="formEntryResikoJatuh.tglPenilaian"
                                    placeholder="dd/mm/yyyy hh:ii:ss" :error="$errors->has('formEntryResikoJatuh.tglPenilaian')" class="w-full" />
                                <x-secondary-button wire:click="setTglPenilaianResikoJatuh" type="button"
                                    class="whitespace-nowrap text-xs">
                                    Sekarang
                                </x-secondary-button>
                            </div>
                            <x-input-error :messages="$errors->get('formEntryResikoJatuh.tglPenilaian')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Risiko Jatuh *" />
                            <x-select-input wire:model.live="formEntryResikoJatuh.resikoJatuh.resikoJatuh"
                                class="w-full mt-1">
                                <option value="Tidak">Tidak</option>
                                <option value="Ya">Ya</option>
                            </x-select-input>
                        </div>
                    </div>

                    @if ($formEntryResikoJatuh['resikoJatuh']['resikoJatuh'] === 'Ya')
                        <div>
                            <x-input-label value="Metode *" />
                            <x-select-input
                                wire:model.live="formEntryResikoJatuh.resikoJatuh.resikoJatuhMetode.resikoJatuhMetode"
                                class="w-full mt-1">
                                <option value="">-- Pilih Metode --</option>
                                <option value="Skala Morse">Skala Morse (Dewasa)</option>
                                <option value="Humpty Dumpty">Humpty Dumpty (Pediatrik)</option>
                            </x-select-input>
                        </div>

                        @php $metodeRJ = $formEntryResikoJatuh['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetode'] ?? ''; @endphp

                        @if (in_array($metodeRJ, ['Skala Morse', 'Humpty Dumpty']))
                            @php
                                $skorRJ =
                                    $formEntryResikoJatuh['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetodeScore'];
                                $katRJ = $formEntryResikoJatuh['resikoJatuh']['kategoriResiko'];
                                $optionsRJ = $metodeRJ === 'Skala Morse' ? $skalaMorseOptions : $humptyDumptyOptions;
                                $interpretasiRJ =
                                    $metodeRJ === 'Skala Morse'
                                        ? '<25 Rendah | 25–44 Sedang | ≥45 Tinggi'
                                        : '<12 Rendah | 12–15 Sedang | ≥16 Tinggi';
                            @endphp
                            <x-border-form :title="$metodeRJ" align="start" bgcolor="bg-white">
                                <div class="mt-3 space-y-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span
                                            class="px-2 py-0.5 text-xs font-bold text-white rounded-full bg-brand">Skor:
                                            {{ $skorRJ }}</span>
                                        @if ($katRJ)
                                            <span
                                                class="px-2 py-0.5 text-xs font-bold rounded-full
                                                {{ $katRJ === 'Tinggi' ? 'bg-red-100 text-red-700' : ($katRJ === 'Sedang' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700') }}">
                                                {{ $katRJ }}
                                            </span>
                                        @endif
                                        <span class="text-xs text-gray-400">Interpretasi: {{ $interpretasiRJ }}</span>
                                    </div>
                                    @foreach ($optionsRJ as $key => $opts)
                                        <div>
                                            <x-input-label :value="ucwords(preg_replace('/(?<!^)[A-Z]/', ' $0', $key))" />
                                            <x-select-input
                                                wire:model.live="formEntryResikoJatuh.resikoJatuh.resikoJatuhMetode.dataResikoJatuh.{{ $key }}"
                                                class="w-full mt-1">
                                                <option value="">-- Pilih --</option>
                                                @foreach ($opts as $opt)
                                                    <option value="{{ $opt[$key] }}">{{ $opt[$key] }} (Skor:
                                                        {{ $opt['score'] }})</option>
                                                @endforeach
                                            </x-select-input>
                                        </div>
                                    @endforeach
                                </div>
                            </x-border-form>
                        @endif

                        <div>
                            <x-input-label value="Rekomendasi" />
                            <x-textarea wire:model="formEntryResikoJatuh.resikoJatuh.rekomendasi" class="w-full mt-1"
                                rows="2" />
                        </div>
                    @endif

                    <div class="flex justify-end">
                        <x-primary-button wire:click="addAssessmentResikoJatuh" wire:loading.attr="disabled"
                            wire:target="addAssessmentResikoJatuh">
                            <span wire:loading.remove wire:target="addAssessmentResikoJatuh">Simpan Penilaian Risiko
                                Jatuh</span>
                            <span wire:loading wire:target="addAssessmentResikoJatuh"
                                class="flex items-center gap-1"><x-loading /> Menyimpan...</span>
                        </x-primary-button>
                    </div>
                </div>
            </x-border-form>
        @endif

        @if (!empty($dataDaftarRi['penilaian']['resikoJatuh']))
            <x-border-form title="Riwayat Penilaian Risiko Jatuh" align="start" bgcolor="bg-white">
                <div class="mt-3 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="w-full text-xs text-left text-gray-600 dark:text-gray-300">
                        <thead class="bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="px-3 py-2">Tgl Penilaian</th>
                                <th class="px-3 py-2">Petugas</th>
                                <th class="px-3 py-2">Risiko</th>
                                <th class="px-3 py-2">Metode</th>
                                <th class="px-3 py-2">Skor</th>
                                <th class="px-3 py-2">Kategori</th>
                                <th class="px-3 py-2">Rekomendasi</th>
                                @if (!$isFormLocked)
                                    <th class="px-3 py-2"></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach (array_reverse($dataDaftarRi['penilaian']['resikoJatuh'] ?? [], true) as $i => $row)
                                @php
                                    $kat = $row['resikoJatuh']['kategoriResiko'] ?? '-';
                                    $rowBg =
                                        $kat === 'Tinggi'
                                            ? 'bg-red-50 hover:bg-red-100'
                                            : ($kat === 'Sedang'
                                                ? 'bg-yellow-50 hover:bg-yellow-100'
                                                : ($kat === 'Rendah'
                                                    ? 'bg-green-50 hover:bg-green-100'
                                                    : 'hover:bg-gray-50'));
                                @endphp
                                <tr class="{{ $rowBg }}">
                                    <td class="px-3 py-2 whitespace-nowrap">{{ $row['tglPenilaian'] ?? '-' }}</td>
                                    <td class="px-3 py-2">{{ $row['petugasPenilai'] ?? '-' }}</td>
                                    <td class="px-3 py-2">
                                        <span
                                            class="px-2 py-0.5 rounded-full text-xs font-medium {{ ($row['resikoJatuh']['resikoJatuh'] ?? '') === 'Ya' ? 'bg-orange-100 text-orange-700' : 'bg-green-100 text-green-700' }}">
                                            {{ $row['resikoJatuh']['resikoJatuh'] ?? '-' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2">
                                        {{ $row['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetode'] ?? '-' }}</td>
                                    <td class="px-3 py-2 font-bold">
                                        {{ $row['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetodeScore'] ?? '-' }}
                                    </td>
                                    <td class="px-3 py-2">
                                        <span
                                            class="px-2 py-0.5 rounded-full text-xs font-medium
                                            {{ $kat === 'Tinggi' ? 'bg-red-100 text-red-700' : ($kat === 'Sedang' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700') }}">
                                            {{ $kat }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-gray-500">{{ $row['resikoJatuh']['rekomendasi'] ?? '-' }}
                                    </td>
                                    @if (!$isFormLocked)
                                        <td class="px-3 py-2">
                                            <x-icon-button variant="danger"
                                                wire:click="removeAssessmentResikoJatuh({{ $i }})"
                                                wire:confirm="Hapus data risiko jatuh ini?">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </x-icon-button>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-border-form>
        @else
            <p class="text-xs text-center text-gray-400 py-6">Belum ada data penilaian risiko jatuh.</p>
        @endif
    </div>

    {{-- ══════════════════════════════════════
    | TAB DEKUBITUS
    ══════════════════════════════════════ --}}
    <div x-show="activeTab === 'dekubitus'" x-transition.opacity.duration.200ms class="space-y-4">

        @if (!$isFormLocked)
            <x-border-form title="Tambah Penilaian Dekubitus (Skala Braden)" align="start" bgcolor="bg-gray-50">
                <div class="mt-4 space-y-4">

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label value="Tanggal Penilaian *" />
                            <div class="flex gap-2 mt-1">
                                <x-text-input wire:model="formEntryDekubitus.tglPenilaian"
                                    placeholder="dd/mm/yyyy hh:ii:ss" :error="$errors->has('formEntryDekubitus.tglPenilaian')" class="w-full" />
                                <x-secondary-button wire:click="setTglPenilaianDekubitus" type="button"
                                    class="whitespace-nowrap text-xs">
                                    Sekarang
                                </x-secondary-button>
                            </div>
                            <x-input-error :messages="$errors->get('formEntryDekubitus.tglPenilaian')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Status Dekubitus *" />
                            <x-select-input wire:model.live="formEntryDekubitus.dekubitus.dekubitus"
                                class="w-full mt-1">
                                <option value="Tidak">Tidak</option>
                                <option value="Ya">Ya</option>
                            </x-select-input>
                        </div>
                    </div>

                    @if (($formEntryDekubitus['dekubitus']['dekubitus'] ?? '') === 'Ya')
                        <x-border-form title="Penilaian Skala Braden" align="start" bgcolor="bg-white">
                            <div class="mt-3 space-y-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="px-2 py-0.5 text-xs font-bold text-white rounded-full bg-brand">
                                        Skor: {{ $formEntryDekubitus['dekubitus']['bradenScore'] ?? 0 }}
                                    </span>
                                    @if ($formEntryDekubitus['dekubitus']['kategoriResiko'] ?? '')
                                        @php $katForm = $formEntryDekubitus['dekubitus']['kategoriResiko']; @endphp
                                        <span
                                            class="px-2 py-0.5 text-xs font-bold rounded-full
                                            {{ in_array($katForm, ['Sangat Tinggi', 'Tinggi']) ? 'bg-red-100 text-red-700' : ($katForm === 'Sedang' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700') }}">
                                            {{ $katForm }}
                                        </span>
                                    @endif
                                    <span class="text-xs text-gray-400">≤12 Sangat Tinggi | 13–14 Tinggi | 15–18 Sedang
                                        | ≥19 Rendah</span>
                                </div>
                                @foreach ($bradenScaleOptions as $key => $options)
                                    <div>
                                        <x-input-label :value="ucwords(preg_replace('/(?<!^)[A-Z]/', ' $0', $key))" />
                                        <x-select-input
                                            wire:model.live="formEntryDekubitus.dekubitus.dataBraden.{{ $key }}"
                                            class="w-full mt-1">
                                            <option value="">-- Pilih --</option>
                                            @foreach ($options as $opt)
                                                <option value="{{ $opt['score'] }}">{{ $opt['description'] }} (Skor:
                                                    {{ $opt['score'] }})</option>
                                            @endforeach
                                        </x-select-input>
                                    </div>
                                @endforeach
                            </div>
                        </x-border-form>

                        <div>
                            <x-input-label value="Rekomendasi" />
                            <x-textarea wire:model="formEntryDekubitus.dekubitus.rekomendasi" class="w-full mt-1"
                                rows="2" />
                        </div>
                    @endif

                    <div class="flex justify-end">
                        <x-primary-button wire:click="addAssessmentDekubitus" wire:loading.attr="disabled"
                            wire:target="addAssessmentDekubitus">
                            <span wire:loading.remove wire:target="addAssessmentDekubitus">Simpan Penilaian
                                Dekubitus</span>
                            <span wire:loading wire:target="addAssessmentDekubitus"
                                class="flex items-center gap-1"><x-loading /> Menyimpan...</span>
                        </x-primary-button>
                    </div>
                </div>
            </x-border-form>
        @endif

        @if (!empty($dataDaftarRi['penilaian']['dekubitus']))
            <x-border-form title="Riwayat Penilaian Dekubitus" align="start" bgcolor="bg-white">
                <div class="mt-3 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="w-full text-xs text-left text-gray-600 dark:text-gray-300">
                        <thead class="bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="px-3 py-2">Tgl Penilaian</th>
                                <th class="px-3 py-2">Petugas</th>
                                <th class="px-3 py-2">Dekubitus</th>
                                <th class="px-3 py-2">Skor Braden</th>
                                <th class="px-3 py-2">Kategori</th>
                                <th class="px-3 py-2">Rekomendasi</th>
                                @if (!$isFormLocked)
                                    <th class="px-3 py-2"></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach (array_reverse($dataDaftarRi['penilaian']['dekubitus'] ?? [], true) as $i => $row)
                                @php
                                    $kat = $row['dekubitus']['kategoriResiko'] ?? '-';
                                    $rowBg = in_array($kat, ['Sangat Tinggi', 'Tinggi'])
                                        ? 'bg-red-50 hover:bg-red-100'
                                        : ($kat === 'Sedang'
                                            ? 'bg-yellow-50 hover:bg-yellow-100'
                                            : ($kat === 'Rendah'
                                                ? 'bg-green-50 hover:bg-green-100'
                                                : 'hover:bg-gray-50'));
                                @endphp
                                <tr class="{{ $rowBg }}">
                                    <td class="px-3 py-2 whitespace-nowrap">{{ $row['tglPenilaian'] ?? '-' }}</td>
                                    <td class="px-3 py-2">{{ $row['petugasPenilai'] ?? '-' }}</td>
                                    <td class="px-3 py-2">
                                        <span
                                            class="px-2 py-0.5 rounded-full text-xs font-medium {{ ($row['dekubitus']['dekubitus'] ?? '') === 'Ya' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' }}">
                                            {{ $row['dekubitus']['dekubitus'] ?? '-' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 font-bold">{{ $row['dekubitus']['bradenScore'] ?? '-' }}</td>
                                    <td class="px-3 py-2">
                                        <span
                                            class="px-2 py-0.5 rounded-full text-xs font-medium
                                            {{ in_array($kat, ['Sangat Tinggi', 'Tinggi']) ? 'bg-red-100 text-red-700' : ($kat === 'Sedang' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700') }}">
                                            {{ $kat }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-gray-500">{{ $row['dekubitus']['rekomendasi'] ?? '-' }}
                                    </td>
                                    @if (!$isFormLocked)
                                        <td class="px-3 py-2">
                                            <x-icon-button variant="danger"
                                                wire:click="removeAssessmentDekubitus({{ $i }})"
                                                wire:confirm="Hapus data dekubitus ini?">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </x-icon-button>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-border-form>
        @else
            <p class="text-xs text-center text-gray-400 py-6">Belum ada data penilaian dekubitus.</p>
        @endif
    </div>

    {{-- ══════════════════════════════════════
    | TAB GIZI
    ══════════════════════════════════════ --}}
    <div x-show="activeTab === 'gizi'" x-transition.opacity.duration.200ms class="space-y-4">

        @if (!$isFormLocked)
            <x-border-form title="Tambah Penilaian Gizi" align="start" bgcolor="bg-gray-50">
                <div class="mt-4 space-y-4">

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label value="Tanggal Penilaian *" />
                            <div class="flex gap-2 mt-1">
                                <x-text-input wire:model="formEntryGizi.tglPenilaian"
                                    placeholder="dd/mm/yyyy hh:ii:ss" :error="$errors->has('formEntryGizi.tglPenilaian')" class="w-full" />
                                <x-secondary-button wire:click="setTglPenilaianGizi" type="button"
                                    class="whitespace-nowrap text-xs">
                                    Sekarang
                                </x-secondary-button>
                            </div>
                        </div>
                        <div>
                            <x-input-label value="Kebutuhan Gizi" />
                            <x-text-input wire:model="formEntryGizi.gizi.kebutuhanGizi" placeholder="1800 kkal/hari"
                                class="w-full mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Berat Badan (kg) *" />
                            <x-text-input type="number" step="0.1"
                                wire:model.live="formEntryGizi.gizi.beratBadan" :error="$errors->has('formEntryGizi.gizi.beratBadan')"
                                class="w-full mt-1" />
                            <x-input-error :messages="$errors->get('formEntryGizi.gizi.beratBadan')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Tinggi Badan (cm) *" />
                            <x-text-input type="number" step="0.1"
                                wire:model.live="formEntryGizi.gizi.tinggiBadan" :error="$errors->has('formEntryGizi.gizi.tinggiBadan')"
                                class="w-full mt-1" />
                            <x-input-error :messages="$errors->get('formEntryGizi.gizi.tinggiBadan')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="IMT (auto)" />
                            <x-text-input wire:model="formEntryGizi.gizi.imt" readonly
                                class="w-full mt-1 bg-gray-100 cursor-not-allowed" />
                        </div>
                    </div>

                    <x-border-form title="Skrining Gizi Awal" align="start" bgcolor="bg-white">
                        <div class="mt-3 space-y-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="px-2 py-0.5 text-xs font-bold text-white rounded-full bg-brand">
                                    Skor: {{ $formEntryGizi['gizi']['skorSkrining'] ?? 0 }}
                                </span>
                                @if ($formEntryGizi['gizi']['kategoriGizi'] ?? '')
                                    <span
                                        class="px-2 py-0.5 text-xs font-bold rounded-full
                                        {{ ($formEntryGizi['gizi']['kategoriGizi'] ?? '') == 'Berisiko Malnutrisi' ? 'bg-orange-100 text-orange-700' : 'bg-green-100 text-green-700' }}">
                                        {{ $formEntryGizi['gizi']['kategoriGizi'] }}
                                    </span>
                                @endif
                                <span class="text-xs text-gray-400">Skor ≥2 = Berisiko Malnutrisi</span>
                            </div>
                            @php $fieldKeys = ['perubahanBeratBadan'=>'perubahan','asupanMakanan'=>'asupan','penyakit'=>'penyakit']; @endphp
                            @foreach ($skriningGiziAwalOptions as $key => $options)
                                @php
                                    $fk = $fieldKeys[$key] ?? $key;
                                    $lb = match ($key) {
                                        'perubahanBeratBadan' => 'Perubahan Berat Badan',
                                        'asupanMakanan' => 'Asupan Makanan',
                                        'penyakit' => 'Kondisi Penyakit',
                                        default => ucwords($key),
                                    };
                                @endphp
                                <div>
                                    <x-input-label :value="$lb" />
                                    <x-select-input
                                        wire:model.live="formEntryGizi.gizi.skriningGizi.{{ $key }}"
                                        class="w-full mt-1">
                                        <option value="">-- Pilih --</option>
                                        @foreach ($options as $opt)
                                            <option value="{{ $opt[$fk] }}">{{ $opt[$fk] }} (Skor:
                                                {{ $opt['score'] }})</option>
                                        @endforeach
                                    </x-select-input>
                                </div>
                            @endforeach
                        </div>
                    </x-border-form>

                    <div>
                        <x-input-label value="Catatan" />
                        <x-textarea wire:model="formEntryGizi.gizi.catatan" class="w-full mt-1" rows="2" />
                    </div>

                    <div class="flex justify-end">
                        <x-primary-button wire:click="addAssessmentGizi" wire:loading.attr="disabled"
                            wire:target="addAssessmentGizi">
                            <span wire:loading.remove wire:target="addAssessmentGizi">Simpan Penilaian Gizi</span>
                            <span wire:loading wire:target="addAssessmentGizi"
                                class="flex items-center gap-1"><x-loading /> Menyimpan...</span>
                        </x-primary-button>
                    </div>
                </div>
            </x-border-form>
        @endif

        @if (!empty($dataDaftarRi['penilaian']['gizi']))
            <x-border-form title="Riwayat Penilaian Gizi" align="start" bgcolor="bg-white">
                <div class="mt-3 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="w-full text-xs text-left text-gray-600 dark:text-gray-300">
                        <thead class="bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="px-3 py-2">Tgl Penilaian</th>
                                <th class="px-3 py-2">Petugas</th>
                                <th class="px-3 py-2">BB (kg)</th>
                                <th class="px-3 py-2">TB (cm)</th>
                                <th class="px-3 py-2">IMT</th>
                                <th class="px-3 py-2">Skor</th>
                                <th class="px-3 py-2">Kategori</th>
                                <th class="px-3 py-2">Catatan</th>
                                @if (!$isFormLocked)
                                    <th class="px-3 py-2"></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach (array_reverse($dataDaftarRi['penilaian']['gizi'] ?? [], true) as $i => $row)
                                @php
                                    $kat = $row['gizi']['kategoriGizi'] ?? '-';
                                    $rowBg =
                                        $kat === 'Berisiko Malnutrisi'
                                            ? 'bg-orange-50 hover:bg-orange-100'
                                            : ($kat === 'Normal'
                                                ? 'bg-green-50 hover:bg-green-100'
                                                : 'hover:bg-gray-50');
                                @endphp
                                <tr class="{{ $rowBg }}">
                                    <td class="px-3 py-2 whitespace-nowrap">{{ $row['tglPenilaian'] ?? '-' }}</td>
                                    <td class="px-3 py-2">{{ $row['petugasPenilai'] ?? '-' }}</td>
                                    <td class="px-3 py-2">{{ $row['gizi']['beratBadan'] ?? '-' }}</td>
                                    <td class="px-3 py-2">{{ $row['gizi']['tinggiBadan'] ?? '-' }}</td>
                                    <td class="px-3 py-2 font-bold">{{ $row['gizi']['imt'] ?? '-' }}</td>
                                    <td class="px-3 py-2 font-bold">{{ $row['gizi']['skorSkrining'] ?? '-' }}</td>
                                    <td class="px-3 py-2">
                                        <span
                                            class="px-2 py-0.5 rounded-full text-xs font-medium
                                            {{ $kat === 'Berisiko Malnutrisi' ? 'bg-orange-100 text-orange-700' : 'bg-green-100 text-green-700' }}">
                                            {{ $kat }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-gray-500 max-w-xs truncate">
                                        {{ $row['gizi']['catatan'] ?? '-' }}</td>
                                    @if (!$isFormLocked)
                                        <td class="px-3 py-2">
                                            <x-icon-button variant="danger"
                                                wire:click="removeAssessmentGizi({{ $i }})"
                                                wire:confirm="Hapus data gizi ini?">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </x-icon-button>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-border-form>
        @else
            <p class="text-xs text-center text-gray-400 py-6">Belum ada data penilaian gizi.</p>
        @endif
    </div>

</div>
