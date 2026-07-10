<?php

use Livewire\Component;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

new class extends Component {
    use EmrRJTrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $dataDaftarPoliRJ = [];
    public string $activePenilaianTab = 'Nyeri'; // persist sub-tab agar tidak balik ke Nyeri setelah simpan

    // renderVersions
    public array $renderVersions = [];
    protected array $renderAreas = ['modal-penilaian-rj'];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas(['modal-penilaian-rj']);
        $this->formEntryNyeri = $this->defaultFormEntryNyeriState();
        $this->formEntryResikoJatuh = $this->defaultFormEntryResikoJatuhState();
        $this->formEntryResikoBunuhDiri = $this->defaultFormEntryResikoBunuhDiriState();
        $this->formEntryDekubitus = $this->defaultFormEntryDekubitusState();
        $this->formEntryGizi = $this->defaultFormEntryGiziState();
    }

    public function rendering(): void
    {
        $default = $this->getDefaultPenilaian();
        $current = $this->dataDaftarPoliRJ['penilaian'] ?? [];
        $this->dataDaftarPoliRJ['penilaian'] = array_replace_recursive($default, $current);
    }

    /* ===============================
     | OPEN REKAM MEDIS - PENILAIAN
     =============================== */
    #[On('open-rm-penilaian-rj')]
    public function openPenilaian(int $rjNo): void
    {
        if (empty($rjNo)) {
            return;
        }

        $this->rjNo = $rjNo;
        $this->resetForm();
        $this->resetValidation();

        $dataDaftarPoliRJ = $this->findDataRJ($rjNo);

        if (!$dataDaftarPoliRJ) {
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Jalan tidak ditemukan.');
            return;
        }

        $this->dataDaftarPoliRJ = $dataDaftarPoliRJ;

        // Initialize penilaian data jika belum ada
        $this->dataDaftarPoliRJ['penilaian'] ??= $this->getDefaultPenilaian();

        $this->incrementVersion('modal-penilaian-rj');

        if ($this->checkEmrRJStatus($rjNo)) {
            $this->isFormLocked = true;
        }
    }

    /* ===============================
     | DEFAULT PENILAIAN STRUCTURE
     =============================== */
    private function getDefaultPenilaian(): array
    {
        return [
            'nyeri' => [],
            'resikoJatuh' => [],
            'resikoBunuhDiri' => [],
            'dekubitus' => [],
            'gizi' => [],
            'statusPediatrik' => [],
            'diagnosis' => [],
        ];
    }

    /* ===============================
     | SAVE PENILAIAN — internal helper
     | Dipanggil dari semua add/remove assessment.
     | Selalu entry point sendiri (tidak dipanggil dari dalam transaksi lain),
     | sehingga lock + transaction ada di sini.
     =============================== */
    private function savePenilaian(?string $logKeterangan = null): void
    {
        // 1. Read-only guard
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only.');
            return;
        }

        // 2. Guard: properti lokal belum ter-load
        if (empty($this->dataDaftarPoliRJ)) {
            $this->dispatch('toast', type: 'error', message: 'Data kunjungan tidak ditemukan, silakan buka ulang form.');
            return;
        }

        try {
            DB::transaction(function () use ($logKeterangan) {
                // 3. Lock row di DB (SELECT FOR UPDATE) — cegah race condition
                $this->lockRJRow($this->rjNo);

                // 4. Ambil data terkini dari DB (setelah lock)
                $data = $this->findDataRJ($this->rjNo) ?? [];

                // 5. Guard: data DB kosong — jangan overwrite JSON dengan array kosong
                if (empty($data)) {
                    $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan, simpan dibatalkan.');
                    return;
                }

                // 6. Set hanya key 'penilaian' — key lain tidak tersentuh
                $data['penilaian'] = $this->dataDaftarPoliRJ['penilaian'] ?? [];

                // 7. Persist + sync properti lokal
                $this->updateJsonRJ($this->rjNo, $data);
                $this->dataDaftarPoliRJ = $data;

                // 8. Audit log — keterangan dari pemanggil (add/remove tiap assessment)
                if ($logKeterangan !== null) {
                    $this->appendAdminLogRJ((int) $this->rjNo, $logKeterangan, 'MR');
                }
            });

            $this->incrementVersion('modal-penilaian-rj');
            $this->dispatch('refresh-after-rj.saved');
            $this->dispatch('toast', type: 'success', message: 'Penilaian berhasil disimpan.');
        } catch (\RuntimeException $e) {
            // lockRJRow() throws RuntimeException jika row tidak ditemukan
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================================================
     | NYERI
     =============================================================== */

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

    private function defaultFormEntryNyeriState(): array
    {
        return [
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
                'ketIntervensiFarmakologi' => '',
                'ketIntervensiNonFarmakologi' => '',
                'catatanTambahan' => '',
            ],
        ];
    }

    public function setTglPenilaianNyeri(): void
    {
        $this->formEntryNyeri['tglPenilaian'] = Carbon::now()->format('d/m/Y H:i:s');
    }

    public function updateVasNyeriScore(int $score): void
    {
        foreach ($this->formEntryNyeri['nyeri']['nyeriMetode']['dataNyeri'] as &$opt) {
            $opt['active'] = (int) $opt['vas'] === $score;
        }
        $this->formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetodeScore'] = $score;
        $this->formEntryNyeri['nyeri']['nyeriKet'] = $this->getJenisNyeriVas($score);
    }

    private function getJenisNyeriVas(int $score): string
    {
        return match (true) {
            $score === 0 => 'Tidak Nyeri',
            $score <= 3 => 'Nyeri Ringan',
            $score <= 6 => 'Nyeri Sedang',
            default => 'Nyeri Berat',
        };
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
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat menambah penilaian.');
            return;
        }

        $this->formEntryNyeri['petugasPenilai'] = auth()->user()->myuser_name;
        $this->formEntryNyeri['petugasPenilaiCode'] = auth()->user()->myuser_code;

        // Auto-isi tanggal kalau Tidak & tgl kosong (UI tgl hanya tampil saat Ya).
        if (($this->formEntryNyeri['nyeri']['nyeri'] ?? '') !== 'Ya' && empty($this->formEntryNyeri['tglPenilaian'])) {
            $this->setTglPenilaianNyeri();
        }

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
        } catch (ValidationException $e) {
            $this->dispatch('toast', type: 'error', message: collect($e->errors())->flatten()->first() ?? 'Periksa kembali data nyeri yang diisi.');
            return;
        }

        $this->dataDaftarPoliRJ['penilaian']['nyeri'][] = $this->formEntryNyeri;
        $this->savePenilaian('Tambah Penilaian RJ Nyeri — entri ' . ($this->formEntryNyeri['tglPenilaian'] ?? '-'));
        $this->formEntryNyeri = $this->defaultFormEntryNyeriState();
    }

    public function removeAssessmentNyeri(int $index): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat menghapus penilaian.');
            return;
        }

        if (isset($this->dataDaftarPoliRJ['penilaian']['nyeri'][$index])) {
            $tglEntri = $this->dataDaftarPoliRJ['penilaian']['nyeri'][$index]['tglPenilaian'] ?? '-';
            array_splice($this->dataDaftarPoliRJ['penilaian']['nyeri'], $index, 1);
            $this->savePenilaian('Hapus Penilaian RJ Nyeri — entri ' . $tglEntri);
        }
    }

    /* ===============================================================
     | RESIKO JATUH
     =============================================================== */

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

    private function defaultFormEntryResikoJatuhState(): array
    {
        return [
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
    }

    public function setTglPenilaianResikoJatuh(): void
    {
        $this->formEntryResikoJatuh['tglPenilaian'] = Carbon::now()->format('d/m/Y H:i:s');
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
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat menambah penilaian.');
            return;
        }

        $this->formEntryResikoJatuh['petugasPenilai'] = auth()->user()->myuser_name;
        $this->formEntryResikoJatuh['petugasPenilaiCode'] = auth()->user()->myuser_code;

        // Auto-isi tanggal kalau Tidak & tgl kosong (UI tgl hanya tampil saat Ya).
        if (($this->formEntryResikoJatuh['resikoJatuh']['resikoJatuh'] ?? '') !== 'Ya' && empty($this->formEntryResikoJatuh['tglPenilaian'])) {
            $this->setTglPenilaianResikoJatuh();
        }

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
        } catch (ValidationException $e) {
            $this->dispatch('toast', type: 'error', message: collect($e->errors())->flatten()->first() ?? 'Periksa kembali data risiko jatuh yang diisi.');
            return;
        }

        $this->dataDaftarPoliRJ['penilaian']['resikoJatuh'][] = $this->formEntryResikoJatuh;
        $this->savePenilaian('Tambah Penilaian RJ Risiko Jatuh — entri ' . ($this->formEntryResikoJatuh['tglPenilaian'] ?? '-'));
        $this->formEntryResikoJatuh = $this->defaultFormEntryResikoJatuhState();
    }

    public function removeAssessmentResikoJatuh(int $index): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat menghapus penilaian.');
            return;
        }

        if (isset($this->dataDaftarPoliRJ['penilaian']['resikoJatuh'][$index])) {
            $tglEntri = $this->dataDaftarPoliRJ['penilaian']['resikoJatuh'][$index]['tglPenilaian'] ?? '-';
            array_splice($this->dataDaftarPoliRJ['penilaian']['resikoJatuh'], $index, 1);
            $this->savePenilaian('Hapus Penilaian RJ Risiko Jatuh — entri ' . $tglEntri);
        }
    }

    /* ===============================================================
     | RISIKO BUNUH DIRI — C-SSRS (Columbia Suicide Severity Rating Scale)
     =============================================================== */

    public array $formEntryResikoBunuhDiri = [];

    // Pertanyaan A (ide bunuh diri, 1 bulan terakhir) — URUTAN = bobot skor keparahan 1-5.
    public array $ideBunuhDiriPertanyaan = [
        'inginMati' => 'Berharap tidak bangun lagi atau sudah meninggal (wish to be dead)',
        'ideAktifTanpaCara' => 'Berpikir untuk mengakhiri hidup, meskipun tanpa memikirkan caranya',
        'ideAktifDenganCara' => 'Memikirkan cara tertentu untuk bunuh diri, namun tanpa niat melakukannya',
        'ideAktifDenganNiat' => 'Berniat bunuh diri, meskipun belum ada rencana yang jelas',
        'ideAktifNiatRencana' => 'Memiliki rencana yang jelas untuk bunuh diri dan berniat melakukannya',
    ];

    // Pertanyaan B (perilaku bunuh diri — pernah, sepanjang hidup).
    public array $perilakuBunuhDiriPertanyaan = [
        'pernahMencoba' => 'Pernah mencoba bunuh diri',
        'hampirMencoba' => 'Hampir mencoba, dihentikan orang lain',
        'memulaiLaluBerhenti' => 'Memulai tetapi menghentikan diri sendiri',
        'persiapanSerius' => 'Melakukan persiapan serius (mengumpulkan obat, menulis pesan perpisahan)',
    ];

    public array $tindakLanjutBunuhDiriOptions = ['Edukasi & monitoring', 'Safety plan', 'Observasi ketat', 'Rujukan segera / rawat inap'];

    private function defaultFormEntryResikoBunuhDiriState(): array
    {
        return [
            'tglPenilaian' => '',
            'petugasPenilai' => '',
            'petugasPenilaiCode' => '',
            'resikoBunuhDiri' => 'Tidak',
            'ideBunuhDiri' => [
                'inginMati' => 'Tidak',
                'ideAktifTanpaCara' => 'Tidak',
                'ideAktifDenganCara' => 'Tidak',
                'ideAktifDenganNiat' => 'Tidak',
                'ideAktifNiatRencana' => 'Tidak',
            ],
            'perilakuBunuhDiri' => [
                'pernahMencoba' => 'Tidak',
                'hampirMencoba' => 'Tidak',
                'memulaiLaluBerhenti' => 'Tidak',
                'persiapanSerius' => 'Tidak',
                'kapanTerakhir' => '',
            ],
            'skorKeparahan' => 0,
            'kategoriResiko' => 'Tidak Ada',
            'tindakLanjut' => [],
            'catatanKlinis' => '',
        ];
    }

    public function setTglPenilaianResikoBunuhDiri(): void
    {
        $this->formEntryResikoBunuhDiri['tglPenilaian'] = Carbon::now()->format('d/m/Y H:i:s');
    }

    /**
     * Skor keparahan = nomor pertanyaan ide TERTINGGI yang dijawab "Ya" (1-5, 0 = tidak ada).
     * Stratifikasi C-SSRS: Tinggi = skor 5 ATAU riwayat percobaan;
     * Sedang = skor 3-4 atau ada perilaku lain (persiapan dsb.);
     * Rendah = skor 1-2 tanpa perilaku; selain itu "Tidak Ada".
     */
    public function hitungSkorResikoBunuhDiri(): void
    {
        // Gate "Tidak" → tidak ada risiko yang dinilai
        if (($this->formEntryResikoBunuhDiri['resikoBunuhDiri'] ?? 'Tidak') !== 'Ya') {
            $this->formEntryResikoBunuhDiri['skorKeparahan'] = 0;
            $this->formEntryResikoBunuhDiri['kategoriResiko'] = 'Tidak Ada';
            return;
        }

        $ide = $this->formEntryResikoBunuhDiri['ideBunuhDiri'] ?? [];
        $skor = 0;
        $nomor = 0;
        foreach ($this->ideBunuhDiriPertanyaan as $key => $pertanyaan) {
            $nomor++;
            if (($ide[$key] ?? '') === 'Ya') {
                $skor = $nomor;
            }
        }

        $perilaku = $this->formEntryResikoBunuhDiri['perilakuBunuhDiri'] ?? [];
        $pernahMencoba = ($perilaku['pernahMencoba'] ?? '') === 'Ya';
        $adaPerilakuLain = collect(['hampirMencoba', 'memulaiLaluBerhenti', 'persiapanSerius'])
            ->contains(fn($key) => ($perilaku[$key] ?? '') === 'Ya');

        $this->formEntryResikoBunuhDiri['skorKeparahan'] = $skor;
        $this->formEntryResikoBunuhDiri['kategoriResiko'] = match (true) {
            $skor === 5 || $pernahMencoba => 'Tinggi',
            $skor >= 3 || $adaPerilakuLain => 'Sedang',
            $skor >= 1 => 'Rendah',
            default => 'Tidak Ada',
        };
    }

    public function toggleTindakLanjutBunuhDiri(string $opsi): void
    {
        if ($this->isFormLocked) {
            return;
        }

        $terpilih = $this->formEntryResikoBunuhDiri['tindakLanjut'] ?? [];
        if (in_array($opsi, $terpilih, true)) {
            $terpilih = array_values(array_diff($terpilih, [$opsi]));
        } else {
            $terpilih[] = $opsi;
        }
        $this->formEntryResikoBunuhDiri['tindakLanjut'] = $terpilih;
    }

    public function addAssessmentResikoBunuhDiri(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat menambah penilaian.');
            return;
        }

        // Gate "Tidak" → entri bersih; jawaban detail yang sempat terisi tidak ikut tersimpan
        if (($this->formEntryResikoBunuhDiri['resikoBunuhDiri'] ?? 'Tidak') !== 'Ya') {
            $this->formEntryResikoBunuhDiri['ideBunuhDiri'] = ['inginMati' => 'Tidak', 'ideAktifTanpaCara' => 'Tidak', 'ideAktifDenganCara' => 'Tidak', 'ideAktifDenganNiat' => 'Tidak', 'ideAktifNiatRencana' => 'Tidak'];
            $this->formEntryResikoBunuhDiri['perilakuBunuhDiri'] = ['pernahMencoba' => 'Tidak', 'hampirMencoba' => 'Tidak', 'memulaiLaluBerhenti' => 'Tidak', 'persiapanSerius' => 'Tidak', 'kapanTerakhir' => ''];
            $this->formEntryResikoBunuhDiri['tindakLanjut'] = [];
        }

        $this->hitungSkorResikoBunuhDiri();
        $this->formEntryResikoBunuhDiri['petugasPenilai'] = auth()->user()->myuser_name;
        $this->formEntryResikoBunuhDiri['petugasPenilaiCode'] = auth()->user()->myuser_code;

        if (empty($this->formEntryResikoBunuhDiri['tglPenilaian'])) {
            $this->setTglPenilaianResikoBunuhDiri();
        }

        try {
            $this->validate(
                [
                    'formEntryResikoBunuhDiri.resikoBunuhDiri' => 'required|in:Ya,Tidak',
                    'formEntryResikoBunuhDiri.tglPenilaian' => 'required|date_format:d/m/Y H:i:s',
                    'formEntryResikoBunuhDiri.petugasPenilai' => 'required|string|max:100',
                    'formEntryResikoBunuhDiri.ideBunuhDiri.*' => 'required|in:Ya,Tidak',
                    'formEntryResikoBunuhDiri.perilakuBunuhDiri.pernahMencoba' => 'required|in:Ya,Tidak',
                    'formEntryResikoBunuhDiri.perilakuBunuhDiri.hampirMencoba' => 'required|in:Ya,Tidak',
                    'formEntryResikoBunuhDiri.perilakuBunuhDiri.memulaiLaluBerhenti' => 'required|in:Ya,Tidak',
                    'formEntryResikoBunuhDiri.perilakuBunuhDiri.persiapanSerius' => 'required|in:Ya,Tidak',
                    'formEntryResikoBunuhDiri.perilakuBunuhDiri.kapanTerakhir' => 'nullable|string|max:100',
                    'formEntryResikoBunuhDiri.tindakLanjut' => 'array',
                    'formEntryResikoBunuhDiri.catatanKlinis' => 'nullable|string|max:1000',
                ],
                [
                    'formEntryResikoBunuhDiri.tglPenilaian.required' => 'Tanggal penilaian wajib diisi.',
                    'formEntryResikoBunuhDiri.tglPenilaian.date_format' => 'Format tanggal harus dd/mm/yyyy hh:mi:ss.',
                    'formEntryResikoBunuhDiri.ideBunuhDiri.*.in' => 'Jawaban ide bunuh diri hanya boleh "Ya" atau "Tidak".',
                ],
            );
        } catch (ValidationException $e) {
            $this->dispatch('toast', type: 'error', message: collect($e->errors())->flatten()->first() ?? 'Periksa kembali data skrining yang diisi.');
            return;
        }

        $this->dataDaftarPoliRJ['penilaian']['resikoBunuhDiri'][] = $this->formEntryResikoBunuhDiri;
        $this->savePenilaian('Tambah Skrining RJ Risiko Bunuh Diri (C-SSRS) — kategori ' . ($this->formEntryResikoBunuhDiri['kategoriResiko'] ?? '-') . ', entri ' . ($this->formEntryResikoBunuhDiri['tglPenilaian'] ?? '-'));
        $this->formEntryResikoBunuhDiri = $this->defaultFormEntryResikoBunuhDiriState();
    }

    public function removeAssessmentResikoBunuhDiri(int $index): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat menghapus penilaian.');
            return;
        }

        if (isset($this->dataDaftarPoliRJ['penilaian']['resikoBunuhDiri'][$index])) {
            $tglEntri = $this->dataDaftarPoliRJ['penilaian']['resikoBunuhDiri'][$index]['tglPenilaian'] ?? '-';
            array_splice($this->dataDaftarPoliRJ['penilaian']['resikoBunuhDiri'], $index, 1);
            $this->savePenilaian('Hapus Skrining RJ Risiko Bunuh Diri (C-SSRS) — entri ' . $tglEntri);
        }
    }

    /* ===============================================================
     | DEKUBITUS
     =============================================================== */

    public array $formEntryDekubitus = [];

    public array $bradenScaleOptions = [
        'sensoryPerception' => [['score' => 4, 'description' => 'Tidak ada gangguan sensorik'], ['score' => 3, 'description' => 'Gangguan sensorik ringan'], ['score' => 2, 'description' => 'Gangguan sensorik sedang'], ['score' => 1, 'description' => 'Gangguan sensorik berat']],
        'moisture' => [['score' => 4, 'description' => 'Kulit kering'], ['score' => 3, 'description' => 'Kulit lembab'], ['score' => 2, 'description' => 'Kulit basah'], ['score' => 1, 'description' => 'Kulit sangat basah']],
        'activity' => [['score' => 4, 'description' => 'Berjalan secara teratur'], ['score' => 3, 'description' => 'Berjalan dengan bantuan'], ['score' => 2, 'description' => 'Duduk di kursi'], ['score' => 1, 'description' => 'Terbaring di tempat tidur']],
        'mobility' => [['score' => 4, 'description' => 'Mobilitas penuh'], ['score' => 3, 'description' => 'Mobilitas sedikit terbatas'], ['score' => 2, 'description' => 'Mobilitas sangat terbatas'], ['score' => 1, 'description' => 'Tidak bisa bergerak']],
        'nutrition' => [['score' => 4, 'description' => 'Asupan nutrisi baik'], ['score' => 3, 'description' => 'Asupan nutrisi cukup'], ['score' => 2, 'description' => 'Asupan nutrisi kurang'], ['score' => 1, 'description' => 'Asupan nutrisi sangat kurang']],
        'frictionShear' => [['score' => 3, 'description' => 'Tidak ada masalah gesekan'], ['score' => 2, 'description' => 'Potensi masalah gesekan'], ['score' => 1, 'description' => 'Masalah gesekan signifikan']],
    ];

    private function defaultFormEntryDekubitusState(): array
    {
        return [
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
    }

    public function setTglPenilaianDekubitus(): void
    {
        $this->formEntryDekubitus['tglPenilaian'] = Carbon::now()->format('d/m/Y H:i:s');
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
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat menambah penilaian.');
            return;
        }

        $this->formEntryDekubitus['petugasPenilai'] = auth()->user()->myuser_name;
        $this->formEntryDekubitus['petugasPenilaiCode'] = auth()->user()->myuser_code;

        // Auto-isi tanggal kalau Tidak & tgl kosong (UI tgl hanya tampil saat Ya).
        if (($this->formEntryDekubitus['dekubitus']['dekubitus'] ?? '') !== 'Ya' && empty($this->formEntryDekubitus['tglPenilaian'])) {
            $this->setTglPenilaianDekubitus();
        }

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
        } catch (ValidationException $e) {
            $this->dispatch('toast', type: 'error', message: collect($e->errors())->flatten()->first() ?? 'Periksa kembali data yang diisi.');
            return;
        }

        $this->dataDaftarPoliRJ['penilaian']['dekubitus'][] = $this->formEntryDekubitus;
        $this->savePenilaian('Tambah Penilaian RJ Dekubitus — entri ' . ($this->formEntryDekubitus['tglPenilaian'] ?? '-'));
        $this->formEntryDekubitus = $this->defaultFormEntryDekubitusState();
    }

    public function removeAssessmentDekubitus(int $index): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat menghapus penilaian.');
            return;
        }

        if (isset($this->dataDaftarPoliRJ['penilaian']['dekubitus'][$index])) {
            $tglEntri = $this->dataDaftarPoliRJ['penilaian']['dekubitus'][$index]['tglPenilaian'] ?? '-';
            array_splice($this->dataDaftarPoliRJ['penilaian']['dekubitus'], $index, 1);
            $this->savePenilaian('Hapus Penilaian RJ Dekubitus — entri ' . $tglEntri);
        }
    }

    /* ===============================================================
     | GIZI
     =============================================================== */

    public array $formEntryGizi = [];

    public array $skriningGiziAwalOptions = [
        'perubahanBeratBadan' => [['perubahan' => 'Tidak ada perubahan', 'score' => 0], ['perubahan' => 'Turun 5-10%', 'score' => 1], ['perubahan' => 'Turun >10%', 'score' => 2]],
        'asupanMakanan' => [['asupan' => 'Cukup', 'score' => 0], ['asupan' => 'Kurang', 'score' => 1], ['asupan' => 'Sangat kurang', 'score' => 2]],
        'penyakit' => [['penyakit' => 'Tidak ada', 'score' => 0], ['penyakit' => 'Ringan', 'score' => 1], ['penyakit' => 'Berat', 'score' => 2]],
    ];

    private function defaultFormEntryGiziState(): array
    {
        return [
            'tglPenilaian' => '',
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

    public function setTglPenilaianGizi(): void
    {
        $this->formEntryGizi['tglPenilaian'] = Carbon::now()->format('d/m/Y H:i:s');
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

    private function hitungImt(): void
    {
        $bb = (float) ($this->formEntryGizi['gizi']['beratBadan'] ?? 0);
        $tb = (float) ($this->formEntryGizi['gizi']['tinggiBadan'] ?? 0);

        if ($bb > 0 && $tb > 0) {
            $tbM = $tb / 100;
            $this->formEntryGizi['gizi']['imt'] = round($bb / ($tbM * $tbM), 2);
        }
    }

    public function addAssessmentGizi(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat menambah penilaian.');
            return;
        }

        $this->formEntryGizi['petugasPenilai'] = auth()->user()->myuser_name;
        $this->formEntryGizi['petugasPenilaiCode'] = auth()->user()->myuser_code;

        // Auto-isi tanggal kalau kosong (Gizi: tgl selalu wajib, stempel saat Simpan = realtime).
        if (empty($this->formEntryGizi['tglPenilaian'])) {
            $this->setTglPenilaianGizi();
        }

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
        } catch (ValidationException $e) {
            $this->dispatch('toast', type: 'error', message: collect($e->errors())->flatten()->first() ?? 'Periksa kembali data gizi yang diisi.');
            return;
        }

        $this->dataDaftarPoliRJ['penilaian']['gizi'][] = $this->formEntryGizi;
        $this->savePenilaian('Tambah Penilaian RJ Gizi — entri ' . ($this->formEntryGizi['tglPenilaian'] ?? '-'));
        $this->formEntryGizi = $this->defaultFormEntryGiziState();
    }

    public function removeAssessmentGizi(int $index): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat menghapus penilaian.');
            return;
        }

        if (isset($this->dataDaftarPoliRJ['penilaian']['gizi'][$index])) {
            $tglEntri = $this->dataDaftarPoliRJ['penilaian']['gizi'][$index]['tglPenilaian'] ?? '-';
            array_splice($this->dataDaftarPoliRJ['penilaian']['gizi'], $index, 1);
            $this->savePenilaian('Hapus Penilaian RJ Gizi — entri ' . $tglEntri);
        }
    }

    /* ===============================
     | UPDATED HOOK
     =============================== */
    public function updated(string $property): void
    {
        // ===== AUTO IMT GIZI =====
        if (in_array($property, ['formEntryGizi.gizi.beratBadan', 'formEntryGizi.gizi.tinggiBadan'])) {
            $this->hitungImt();
        }

        // ===== AUTO SKRINING GIZI =====
        if (str_starts_with($property, 'formEntryGizi.gizi.skriningGizi')) {
            $this->hitungSkorSkriningGizi();
        }

        // ===== AUTO BRADEN =====
        if (str_starts_with($property, 'formEntryDekubitus.dekubitus.dataBraden')) {
            $this->hitungSkorBraden();
        }

        // ===== AUTO SKOR C-SSRS (RISIKO BUNUH DIRI) =====
        if ($property === 'formEntryResikoBunuhDiri.resikoBunuhDiri' || str_starts_with($property, 'formEntryResikoBunuhDiri.ideBunuhDiri') || str_starts_with($property, 'formEntryResikoBunuhDiri.perilakuBunuhDiri')) {
            $this->hitungSkorResikoBunuhDiri();
        }

        // ===== AUTO MORSE / HUMPTY DUMPTY =====
        if (str_starts_with($property, 'formEntryResikoJatuh.resikoJatuh.resikoJatuhMetode.dataResikoJatuh')) {
            $metode = $this->formEntryResikoJatuh['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetode'] ?? '';
            match ($metode) {
                'Skala Morse' => $this->hitungSkorMorse(),
                'Humpty Dumpty' => $this->hitungSkorHumptyDumpty(),
                default => null,
            };
        }

        // ===== AUTO KET NYERI (NRS / BPS / NIPS) =====
        if ($property === 'formEntryNyeri.nyeri.nyeriMetode.nyeriMetodeScore') {
            $metode = $this->formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetode'] ?? '';
            if (in_array($metode, ['NRS', 'BPS', 'NIPS'])) {
                $score = (int) ($this->formEntryNyeri['nyeri']['nyeriMetode']['nyeriMetodeScore'] ?? 0);
                $this->formEntryNyeri['nyeri']['nyeriKet'] = $this->getJenisNyeriVas($score);
            }
        }

        // ===== RESET DATA SAAT GANTI METODE NYERI =====
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

        // ===== RESET DATA SAAT GANTI METODE RESIKO JATUH =====
        if ($property === 'formEntryResikoJatuh.resikoJatuh.resikoJatuhMetode.resikoJatuhMetode') {
            $this->formEntryResikoJatuh['resikoJatuh']['resikoJatuhMetode']['dataResikoJatuh'] = [];
            $this->formEntryResikoJatuh['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetodeScore'] = 0;
            $this->formEntryResikoJatuh['resikoJatuh']['kategoriResiko'] = '';
        }
    }

    /* ===============================
     | HELPERS
     =============================== */
    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->formEntryNyeri = $this->defaultFormEntryNyeriState();
        $this->formEntryResikoJatuh = $this->defaultFormEntryResikoJatuhState();
        $this->formEntryResikoBunuhDiri = $this->defaultFormEntryResikoBunuhDiriState();
        $this->formEntryDekubitus = $this->defaultFormEntryDekubitusState();
        $this->formEntryGizi = $this->defaultFormEntryGiziState();
    }
};
?>

<div>
    <div class="flex flex-col w-full" wire:key="{{ $this->renderKey('modal-penilaian-rj', [$rjNo ?? 'new']) }}">

        @if (isset($dataDaftarPoliRJ['penilaian']))
            <div
                class="w-full p-4 space-y-6 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                <div id="PenilaianRawatJalan" x-data="{ activeTab: $wire.entangle('activePenilaianTab') }" class="w-full">

                    {{-- TAB NAVIGATION --}}
                    <x-scrollable-tabs class="w-full px-2 mb-2 border-b border-hairline dark:border-gray-700">
                        <div class="flex flex-nowrap w-full gap-2 -mb-px">
                            @foreach (['Nyeri' => 'Nyeri', 'Risiko Jatuh' => 'Risiko Jatuh', 'Risiko Bunuh Diri' => 'Risiko Bunuh Diri', 'Dekubitus' => 'Dekubitus', 'Gizi' => 'Gizi'] as $tab => $label)
                                <x-tab variant="underline" active-expr="activeTab === '{{ $tab }}'"
                                    x-on:click="activeTab = '{{ $tab }}'">
                                    {{ $label }}
                                </x-tab>
                            @endforeach
                        </div>
                    </x-scrollable-tabs>

                    {{-- TAB CONTENTS --}}
                    <div class="w-full p-4">

                        <div class="w-full" x-show.transition.in.opacity.duration.600="activeTab === 'Nyeri'">
                            @include('pages.transaksi.rj.emr-rj.penilaian.tabs.nyeri-tab')
                        </div>

                        <div class="w-full" x-show.transition.in.opacity.duration.600="activeTab === 'Risiko Jatuh'">
                            @include('pages.transaksi.rj.emr-rj.penilaian.tabs.resiko-jatuh-tab')
                        </div>

                        <div class="w-full" x-show.transition.in.opacity.duration.600="activeTab === 'Risiko Bunuh Diri'">
                            @include('pages.transaksi.rj.emr-rj.penilaian.tabs.risiko-bunuh-diri-tab')
                        </div>

                        <div class="w-full" x-show.transition.in.opacity.duration.600="activeTab === 'Dekubitus'">
                            @include('pages.transaksi.rj.emr-rj.penilaian.tabs.dekubitus-tab')
                        </div>

                        <div class="w-full" x-show.transition.in.opacity.duration.600="activeTab === 'Gizi'">
                            @include('pages.transaksi.rj.emr-rj.penilaian.tabs.gizi-tab')
                        </div>

                    </div>
                </div>
            </div>
        @else
            <div class="flex items-center justify-center py-12 text-sm text-muted-soft">
                Buka kunjungan terlebih dahulu untuk mengisi penilaian.
            </div>
        @endif

    </div>
</div>
