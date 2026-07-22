<?php
// resources/views/pages/transaksi/ugd/emr-ugd/modul-dokumen/akhir-hayat/rm-akhir-hayat-actions.blade.php
//
// PENGKAJIAN AKHIR HAYAT (UGD) — gabungan formulir KARS (End of Life) + RM.RI.62.
//   dari KARS     : diagnosis & prognosis, penilaian fisik/TTV, simptom berskala,
//                   keadaan emosional, informasi & edukasi, pernyataan persetujuan + 3 TTD.
//   dari RM.RI.62 : kebutuhan spiritual, orang dihubungi, rencana perawatan/home care,
//                   dukungan kunjungan, kondisi keluarga yang ditinggalkan, autopsi/donasi,
//                   intervensi keperawatan & medis (termasuk DNR).
// Pola modul-dokumen UGD multi-entri: draft → TTD → Simpan & Kunci → Lihat/Cetak.

use Livewire\Component;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\WithValidationToast\WithValidationToastTrait;
use App\Support\AkhirHayatClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

new class extends Component {
    use EmrUGDTrait, MasterPasienTrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public ?string $regNo = null;
    public bool $disabled = false;
    public array $dataDaftarUGD = [];

    // TTD gambar dari komponen signature-pad (jangan tulis tag x-... di komentar:
    // Blade tetap mengkompilasinya walau berada di dalam komentar PHP)
    public string $keluargaSignature = '';
    public string $saksiSignature = '';

    public array $form = [];

    public ?string $editingKey = null;   // id entri yang sedang diedit; null = entri baru
    public bool $viewOnly = false;       // entri terkunci ditampilkan read-only

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-akhir-hayat-ugd'];

    /* ===============================
     | OPTION LIST (key => label)
     =============================== */
    public array $skalaSimptom = [
        'tidakAda' => 'Tidak ada',
        'ringan' => 'Ringan',
        'sedang' => 'Sedang',
        'berat' => 'Berat',
    ];

    // Pilihan agama/kepercayaan pasien (untuk kebutuhan spiritual).
    public array $agamaOptions = ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu', 'Kepercayaan Lain'];


    public array $reaksiPasienList = [
        'menyangkalMarah' => 'Menyangkal / marah',
        'sedihMenangis' => 'Sedih / menangis',
        'takutCemas' => 'Takut / cemas',
        'bersalahTakBerdaya' => 'Rasa bersalah / tidak berdaya',
    ];

    public array $masalahPasienList = [
        'anxietas' => 'Cemas / ansietas menjelang kematian',
        'distressSpiritual' => 'Distres spiritual',
    ];

    // Satu daftar untuk reaksi keluarga SAAT INI sekaligus risiko SETELAH ditinggalkan —
    // indikatornya identik di kedua formulir, jadi tak perlu dua checklist kembar.
    public array $kondisiKeluargaList = [
        'marahBersalah' => 'Marah / rasa bersalah',
        'depresiSedih' => 'Depresi / sedih & menangis',
        'letihGangguanTidur' => 'Letih / lelah & gangguan tidur',
        'konsentrasiKomunikasi' => 'Penurunan konsentrasi / komunikasi terganggu',
        'peranKeputusan' => 'Sulit menjalankan peran & terlibat keputusan perawatan',
    ];

    public array $masalahKeluargaList = [
        'kopingTidakEfektif' => 'Koping keluarga tidak efektif',
        'distressSpiritual' => 'Distres spiritual',
        'perubahanProsesKeluarga' => 'Perubahan proses keluarga',
    ];

    public array $dukunganList = [
        'roomingIn' => 'Keluarga boleh menunggu 24 jam',
        'keluargaKunjungLuarJam' => 'Keluarga boleh berkunjung di luar jam besuk',
        'sahabatKunjungLuarJam' => 'Sahabat boleh berkunjung di luar jam besuk',
    ];

    public array $intervensiKeperawatanList = [
        'higienePersonalMata' => 'Kebersihan diri & perawatan mata',
        'posisiReposisi' => 'Posisi tidur nyaman & reposisi tiap 2 jam',
        'suctionSekret' => 'Pengisapan lendir bila menumpuk',
        'nutrisiCairan' => 'Pemenuhan nutrisi & cairan sesuai program',
        'manajemenNyeri' => 'Penanganan nyeri yang memadai',
        'dukunganKeluarga' => 'Pendampingan & empati kepada keluarga berduka',
    ];

    // Tindakan medis — tiap butir keputusan tersendiri, TIDAK digabung
    public array $intervensiMedisList = [
        'rjpo' => 'Resusitasi Jantung Paru Otak (RJPO)',
        'ventilator' => 'Alat bantu napas (ventilator)',
        'feedingTube' => 'Pemberian makan lewat selang',
        'parenteralNutrition' => 'Pemberian nutrisi lewat infus',
        'dialisis' => 'Cuci darah (dialisis)',
        'dnr' => 'Tidak dilakukan resusitasi (DNR)',
    ];

    public array $prognosisOptions = [
        'bonam' => 'Cenderung membaik (dubia ad bonam)',
        'malam' => 'Cenderung memburuk (dubia ad malam)',
        'terminal' => 'Buruk / terminal (malam)',
    ];

    public array $hubunganOptions = [
        'pasien' => 'Pasien Sendiri',
        'suami' => 'Suami',
        'istri' => 'Istri',
        'ayah' => 'Ayah',
        'ibu' => 'Ibu',
        'anak' => 'Anak',
        'saudara' => 'Saudara',
        'wali_hukum' => 'Wali Hukum',
        'lainnya' => 'Lainnya',
    ];

    /* ===============================
     | MOUNT / OPEN / CLOSE
     =============================== */
    public function mount(?int $rjNo = null, bool $disabled = false): void
    {
        $this->rjNo = $rjNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-akhir-hayat-ugd']);

        $this->form = $this->defaultForm();
        $this->prefillHeader();

        if ($this->rjNo) {
            $data = $this->findDataUGD($this->rjNo);
            if ($data) {
                $this->dataDaftarUGD = $data;
                $this->regNo = $data['regNo'] ?? null;
                $this->dataDaftarUGD['pengkajianAkhirHayatUGD'] ??= [];
                $this->form['ttd']['keluargaNama'] = $data['regName'] ?? '';
                $this->isFormLocked = $this->checkEmrUGDStatus($this->rjNo) || $disabled;
            }
        }
    }

    public function openModal(): void
    {
        if (!$this->rjNo || $this->disabled) {
            return;
        }

        $data = $this->findDataUGD($this->rjNo);
        if ($data) {
            $this->dataDaftarUGD = $data;
            $this->regNo = $data['regNo'] ?? $this->regNo;
            $this->dataDaftarUGD['pengkajianAkhirHayatUGD'] ??= [];
            $this->isFormLocked = $this->checkEmrUGDStatus($this->rjNo) || $this->disabled;
        }

        $this->resetFormAkhirHayat();
        $this->prefillDariEmr();

        $this->dispatch('open-modal', name: "rm-akhir-hayat-ugd-{$this->rjNo}");
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: "rm-akhir-hayat-ugd-{$this->rjNo}");
    }

    /* ===============================
     | BENTUK FORM
     =============================== */
    private function defaultForm(): array
    {
        return [
            'tglAsesmen' => '',
            'jenisAsesmen' => '', // diisi otomatis: entri pertama 'awal', berikutnya 'ulang'

            // 1. Kondisi medis & fisik (KARS)
            'medis' => [
                'diagnosaUtama' => '',
                'diagnosaSekunder' => '',
                'riwayat' => '',
                'prognosis' => '',
                'prognosisCatatan' => '',
            ],
            'fisik' => [
                'tb' => '',
                'bb' => '',
                'bmi' => '',
                'sistolik' => '',
                'distolik' => '',
                'nadi' => '',
                'respirasi' => '',
                'suhu' => '',
                'spo2' => '',
            ],

            // 2. Simptom berskala (KARS). Tanda fisik & diagnosis keperawatan sengaja TIDAK di sini:
            // tumpang tindih dengan skala simptom di atas dan sudah dicatat di Asuhan Keperawatan.
            'simptom' => [
                'nyeri' => 'tidakAda',
                'nyeriLokasi' => '',
                'nyeriDeskripsi' => '',
                'sesak' => 'tidakAda',
                'mualMuntah' => 'tidakAda',
                'kelelahan' => 'tidakAda',
                'lainnya' => '',
            ],

            // 3. Psikososial & spiritual
            'psikososial' => [
                'emosional' => '',          // keadaan emosional PASIEN
                'emosionalKeluarga' => '',  // keadaan emosional KELUARGA
                'reaksiPasien' => ['opsi' => [], 'masalah' => []],
                'keluarga' => ['opsi' => [], 'masalah' => []],
                'orangDihubungi' => ['ada' => 'tidak', 'nama' => '', 'hubungan' => '', 'alamat' => '', 'telp' => ''],
            ],
            'spiritual' => [
                'perluPelayanan' => 'tidak',
                'agama' => '',
                'oleh' => '',
                'perluDidoakan' => '',
                'perluBimbingan' => '',
                'perluPendampingan' => '',
            ],

            // 4. Rencana, dukungan, intervensi & edukasi
            'rencana' => [
                'pilihan' => 'rs', // rs | rumah
                'lingkunganSiap' => 'ya',   // default "Ya" saat dirawat di rumah
                'adaPerawat' => 'ya',       // default "Ya" saat dirawat di rumah
                'perawatOleh' => '',
                'perluHomeCare' => 'ya',    // default "Ya" saat dirawat di rumah
            ],
            'dukungan' => ['opsi' => [], 'lainnya' => ''],
            'alternatif' => ['pilihan' => 'tidak', 'donasiOrgan' => '', 'lainnya' => ''],
            'intervensi' => ['keperawatan' => [], 'medis' => [], 'catatan' => ''],
            'edukasi' => ['pendidikanKesehatan' => '', 'rencanaDiRumah' => ''],

            // 5. Pernyataan & TTD
            'ttd' => [
                'keluargaNama' => '',
                'keluargaHubungan' => 'pasien',
                'keluargaTTD' => '',
                'saksiNama' => '',
                'saksiTTD' => '',
                'petugasName' => '',
                'petugasCode' => '',
                'petugasDate' => '',
                'clauseVersion' => AkhirHayatClause::CURRENT,
            ],
        ];
    }

    private function prefillHeader(): void
    {
        if (empty($this->form['tglAsesmen'])) {
            $this->form['tglAsesmen'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        }
    }

    /**
     * Isi awal dari data EMR yang sudah ada — hanya diagnosis, tetap bisa dikoreksi.
     * TTV (TB/BB/tensi/nadi/napas/suhu/SpO₂/IMT) SENGAJA tidak di-prefill: default kosong
     * agar petugas mengisi kondisi terkini pasien terminal, bukan menyalin nilai lama.
     */
    private function prefillDariEmr(): void
    {
        $diagnosa = data_get($this->dataDaftarUGD, 'diagnosisFreeText', '');
        if (filled($diagnosa) && empty($this->form['medis']['diagnosaUtama'])) {
            $this->form['medis']['diagnosaUtama'] = (string) $diagnosa;
        }
    }

    /** BMI dihitung, tidak diketik — kg / m². */
    public function getBmiProperty(): ?string
    {
        $tinggi = (float) str_replace(',', '.', (string) ($this->form['fisik']['tb'] ?? ''));
        $berat = (float) str_replace(',', '.', (string) ($this->form['fisik']['bb'] ?? ''));
        if ($tinggi <= 0 || $berat <= 0) {
            return null;
        }
        return number_format($berat / (($tinggi / 100) ** 2), 1);
    }

    public function setTglAsesmen(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->form['tglAsesmen'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /** Jenis asesmen ditentukan sistem: entri pertama Awal, berikutnya Ulang. */
    private function tentukanJenisAsesmen(string $id): string
    {
        $list = $this->dataDaftarUGD['pengkajianAkhirHayatUGD'] ?? [];
        $existing = collect($list)->firstWhere('id', $id);
        if ($existing) {
            return $existing['form']['jenisAsesmen'] ?? 'awal';
        }
        return count($list) > 0 ? 'ulang' : 'awal';
    }

    /* ===============================
     | TTD
     =============================== */
    public function setKeluargaSignature(string $dataUrl): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->keluargaSignature = $dataUrl;
        $this->incrementVersion('modal-akhir-hayat-ugd');
    }

    public function clearKeluargaSignature(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->keluargaSignature = '';
        $this->form['ttd']['keluargaTTD'] = '';
        $this->incrementVersion('modal-akhir-hayat-ugd');
    }

    public function setSaksiSignature(string $dataUrl): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->saksiSignature = $dataUrl;
        $this->incrementVersion('modal-akhir-hayat-ugd');
    }

    public function clearSaksiSignature(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->saksiSignature = '';
        $this->form['ttd']['saksiTTD'] = '';
        $this->incrementVersion('modal-akhir-hayat-ugd');
    }

    /**
     * TTD PETUGAS = LANGKAH TERAKHIR yang sekaligus MENGUNCI entri
     * (pola Inform Consent: setDokterPenjelas). Urutan bakunya:
     * pasien/keluarga (+saksi) tanda tangan dulu → petugas TTD → entri terkunci.
     */
    public function ttdPetugas(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }

        if (empty($this->form['tglAsesmen'])) {
            $this->form['tglAsesmen'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        }

        // TTD keluarga entri lama tersimpan di form; angkat ke property agar ikut divalidasi
        if (empty($this->keluargaSignature) && filled(data_get($this->form, 'ttd.keluargaTTD'))) {
            $this->keluargaSignature = (string) data_get($this->form, 'ttd.keluargaTTD');
        }

        // Semua kolom wajib (termasuk TTD pasien/keluarga) divalidasi di sini agar field
        // kosong di-highlight MERAH — jangan short-circuit sebelum validate().
        [$rules, $messages, $attributes] = $this->akhirHayatRules();
        $this->validateWithToast($rules, $messages, $attributes);

        // Stempel TTD petugas = user login
        $this->form['ttd']['petugasName'] = auth()->user()->myuser_name ?? '';
        $this->form['ttd']['petugasCode'] = auth()->user()->myuser_code ?? '';
        $this->form['ttd']['petugasDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        $id = $this->editingKey ?: (string) Str::uuid();
        $this->form['jenisAsesmen'] = $this->tentukanJenisAsesmen($id);
        $this->form['ttd']['clauseVersion'] = $this->form['ttd']['clauseVersion'] ?: AkhirHayatClause::CURRENT;

        try {
            $this->persistEntry($id, true, 'Kunci (TTD Petugas)');
            $this->resetFormAkhirHayat();
            $this->dispatch('toast', type: 'success', message: 'Pengkajian akhir hayat ditandatangani petugas dan terkunci.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal mengunci: ' . $e->getMessage());
        }
    }

    /** Toggle keanggotaan array multi-pilih. $fullPath mulai dari property root. */
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

    /** Toggle nilai ya/kosong untuk kebutuhan spiritual (checkbox, bukan radio). */
    public function toggleSpiritual(string $field): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        if (!array_key_exists($field, $this->form['spiritual'] ?? [])) {
            return;
        }
        $this->form['spiritual'][$field] = ($this->form['spiritual'][$field] ?? '') === 'ya' ? '' : 'ya';
    }

    /* ===============================
     | ENTRI (draft / kunci)
     =============================== */
    public function entryIsFinal(array $e): bool
    {
        return array_key_exists('finalized', $e) ? (bool) $e['finalized'] : !empty(data_get($e, 'form.ttd.keluargaTTD'));
    }

    private function buildEntry(string $id, bool $finalized): array
    {
        $form = $this->form;
        if (!empty($this->keluargaSignature)) {
            $form['ttd']['keluargaTTD'] = $this->keluargaSignature;
        }
        if (!empty($this->saksiSignature)) {
            $form['ttd']['saksiTTD'] = $this->saksiSignature;
        }
        $form['fisik']['bmi'] = $this->bmi ?? ''; // snapshot hasil hitung

        $existing = collect($this->dataDaftarUGD['pengkajianAkhirHayatUGD'] ?? [])->firstWhere('id', $id);
        $createdAt = $existing['created_at'] ?? Carbon::now(config('app.timezone'))->format('Y-m-d H:i:s');
        $createdBy = $existing['created_by'] ?? [
            'code' => auth()->user()->myuser_code ?? '',
            'name' => auth()->user()->myuser_name ?? '',
        ];

        return [
            'id' => $id,
            'created_at' => $createdAt,
            'created_by' => $createdBy,
            'form' => $form,
            'finalized' => $finalized,
        ];
    }

    private function persistEntry(string $id, bool $finalized, string $logVerb): void
    {
        $entry = $this->buildEntry($id, $finalized);

        DB::transaction(function () use ($entry, $id, $logVerb) {
            $this->lockUGDRow($this->rjNo);

            $fresh = $this->findDataUGD($this->rjNo) ?? [];
            if (!isset($fresh['pengkajianAkhirHayatUGD']) || !is_array($fresh['pengkajianAkhirHayatUGD'])) {
                $fresh['pengkajianAkhirHayatUGD'] = [];
            }

            $list = $fresh['pengkajianAkhirHayatUGD'];
            $idx = collect($list)->search(fn($it) => ($it['id'] ?? null) === $id);
            if ($idx === false) {
                $list[] = $entry;
            } else {
                if ($this->entryIsFinal($list[$idx])) {
                    throw new \RuntimeException('Entri sudah terkunci, tidak dapat diubah.');
                }
                $list[$idx] = $entry;
            }
            $fresh['pengkajianAkhirHayatUGD'] = array_values($list);

            $this->updateJsonUGD((int) $this->rjNo, $fresh);
            $this->dataDaftarUGD = $fresh;

            $jenis = ($entry['form']['jenisAsesmen'] ?? 'awal') === 'ulang' ? 'Ulang' : 'Awal';
            $this->appendAdminLogUGD(
                (int) $this->rjNo,
                $logVerb . ' Pengkajian Akhir Hayat (Asesmen ' . $jenis . ') — ' . ($entry['form']['tglAsesmen'] ?? '-'),
                'MR',
            );
        });
    }

    /**
     * Aturan validasi sengaja minimal. Asesmen terminal diisi bertahap; mewajibkan
     * banyak field hanya membuat petugas mengetik asal supaya entri bisa dikunci.
     */
    private function akhirHayatRules(): array
    {
        $rules = [
            'form.tglAsesmen' => 'required|date_format:d/m/Y H:i:s',
            'form.ttd.keluargaNama' => 'required|string|max:250',
            'form.ttd.keluargaHubungan' => 'required|string|max:50',
            'form.ttd.saksiNama' => 'required|string|max:250',
            // TTD ikut rules (seragam Inform Consent) → error tampil merah + toast.
            // Nama/waktu petugas TIDAK divalidasi: di-stempel oleh aksi TTD petugas itu sendiri.
            'keluargaSignature' => 'required|string',
            'saksiSignature' => 'required|string',
            // Tanda vital wajib diisi saat kunci — kondisi terkini pasien terminal.
            'form.fisik.tb' => 'required|numeric',
            'form.fisik.bb' => 'required|numeric',
            'form.fisik.sistolik' => 'required|numeric',
            'form.fisik.distolik' => 'required|numeric',
            'form.fisik.nadi' => 'required|numeric',
            'form.fisik.respirasi' => 'required|numeric',
            'form.fisik.suhu' => 'required|numeric',
            'form.fisik.spo2' => 'required|numeric',
            // Keadaan emosional pasien & keluarga + edukasi wajib diisi.
            'form.psikososial.emosional' => 'required|string',
            'form.psikososial.emosionalKeluarga' => 'required|string',
            'form.edukasi.pendidikanKesehatan' => 'required|string',
        ];

        // Donasi organ tanpa menyebut organnya tidak bermakna
        if (($this->form['alternatif']['pilihan'] ?? '') === 'donasi') {
            $rules['form.alternatif.donasiOrgan'] = 'required|string|max:250';
        }

        // Bila ada orang yang ingin dihubungi → nama/hubungan/alamat wajib.
        if (($this->form['psikososial']['orangDihubungi']['ada'] ?? '') === 'ya') {
            $rules['form.psikososial.orangDihubungi.nama'] = 'required|string|max:250';
            $rules['form.psikososial.orangDihubungi.hubungan'] = 'required|string|max:100';
            $rules['form.psikososial.orangDihubungi.alamat'] = 'required|string|max:250';
        }

        $messages = [
            'required' => ':attribute wajib diisi.',
            'form.tglAsesmen.date_format' => 'Tanggal asesmen — format: dd/mm/yyyy hh:mm:ss.',
        ];

        $attributes = [
            'form.tglAsesmen' => 'Tanggal asesmen',
            'form.ttd.keluargaNama' => 'Nama pasien / keluarga penanda tangan',
            'form.ttd.keluargaHubungan' => 'Hubungan dengan pasien',
            'form.ttd.saksiNama' => 'Nama saksi',
            'form.alternatif.donasiOrgan' => 'Organ yang didonasikan',
            'keluargaSignature' => 'Tanda tangan pasien / keluarga',
            'saksiSignature' => 'Tanda tangan saksi',
            'form.fisik.tb' => 'TB (cm)',
            'form.fisik.bb' => 'BB (kg)',
            'form.fisik.sistolik' => 'Sistolik',
            'form.fisik.distolik' => 'Diastolik',
            'form.fisik.nadi' => 'Nadi',
            'form.fisik.respirasi' => 'Napas',
            'form.fisik.suhu' => 'Suhu (°C)',
            'form.fisik.spo2' => 'Saturasi O₂ (%)',
            'form.psikososial.emosional' => 'Keadaan emosional pasien',
            'form.psikososial.emosionalKeluarga' => 'Keadaan emosional keluarga',
            'form.edukasi.pendidikanKesehatan' => 'Pendidikan kesehatan',
            'form.psikososial.orangDihubungi.nama' => 'Nama orang yang dihubungi',
            'form.psikososial.orangDihubungi.hubungan' => 'Hubungan orang yang dihubungi',
            'form.psikososial.orangDihubungi.alamat' => 'Alamat orang yang dihubungi',
        ];

        return [$rules, $messages, $attributes];
    }

    public function saveDraft(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }

        if (empty($this->form['tglAsesmen'])) {
            $this->form['tglAsesmen'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        }

        $id = $this->editingKey ?: (string) Str::uuid();
        $this->form['jenisAsesmen'] = $this->tentukanJenisAsesmen($id);

        try {
            $this->persistEntry($id, false, 'Simpan draft');
            $this->editingKey = $id;
            $this->incrementVersion('modal-akhir-hayat-ugd');
            $this->dispatch('toast', type: 'success', message: 'Draft tersimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan draft: ' . $e->getMessage());
        }
    }

    /* ===============================
     | EDIT / LIHAT / HAPUS
     =============================== */
    private function hydrateFormFromEntry(array $entri): void
    {
        // array_replace_recursive menjaga key nested yang belum ada di record lama
        $this->form = array_replace_recursive($this->defaultForm(), $entri['form'] ?? []);
        $this->keluargaSignature = (string) data_get($entri, 'form.ttd.keluargaTTD', '');
        $this->saksiSignature = (string) data_get($entri, 'form.ttd.saksiTTD', '');
        $this->editingKey = $entri['id'] ?? null;
        $this->resetValidation();
        $this->incrementVersion('modal-akhir-hayat-ugd');
    }

    public function editEntry(string $id): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }
        $entri = collect($this->dataDaftarUGD['pengkajianAkhirHayatUGD'] ?? [])->firstWhere('id', $id);
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
        $entri = collect($this->dataDaftarUGD['pengkajianAkhirHayatUGD'] ?? [])->firstWhere('id', $id);
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
        $this->resetFormAkhirHayat();
    }

    public function removeEntry(string $id): void
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
                $this->lockUGDRow($this->rjNo);

                $fresh = $this->findDataUGD($this->rjNo) ?? [];
                $list = $fresh['pengkajianAkhirHayatUGD'] ?? [];

                $deletedRow = collect($list)->firstWhere('id', $id);
                $newList = array_values(array_filter($list, fn($e) => ($e['id'] ?? null) !== $id));
                if (count($newList) === count($list)) {
                    throw new \RuntimeException('Data tidak ditemukan atau sudah dihapus.');
                }

                $fresh['pengkajianAkhirHayatUGD'] = $newList;
                $this->updateJsonUGD((int) $this->rjNo, $fresh);
                $this->dataDaftarUGD = $fresh;

                $this->appendAdminLogUGD(
                    (int) $this->rjNo,
                    'Hapus Pengkajian Akhir Hayat — ' . ($deletedRow['form']['tglAsesmen'] ?? '-'),
                    'MR',
                );
            });

            if ($this->editingKey === $id) {
                $this->cancelEdit();
            }
            $this->incrementVersion('modal-akhir-hayat-ugd');
            $this->dispatch('toast', type: 'success', message: 'Pengkajian akhir hayat berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | BUKA KUNCI (unlock) — hanya Admin / Manager ke atas.
     | Mencabut kunci + TTD PETUGAS saja; TTD pasien/keluarga & saksi DIPERTAHANKAN,
     | entri kembali jadi draft untuk dikoreksi lalu dikunci ulang oleh petugas.
     =============================== */
    private function bolehBukaKunci(): bool
    {
        return (bool) auth()->user()?->can('dokumen.bukaKunci');
    }

    public function bukaKunci(string $id): void
    {
        if (!$this->bolehBukaKunci()) {
            $this->dispatch('toast', type: 'error', message: 'Hanya Admin / Manager yang dapat membuka kunci.');
            return;
        }
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang — form read-only.');
            return;
        }

        try {
            DB::transaction(function () use ($id) {
                $this->lockUGDRow($this->rjNo);

                $fresh = $this->findDataUGD($this->rjNo) ?: [];
                $list = $fresh['pengkajianAkhirHayatUGD'] ?? [];
                $index = collect($list)->search(fn($it) => ($it['id'] ?? null) === $id);
                if ($index === false) {
                    throw new \RuntimeException('Entri tidak ditemukan.');
                }

                // Cabut kunci + TTD petugas; TTD pasien/keluarga & saksi tetap.
                $list[$index]['finalized'] = false;
                $list[$index]['form']['ttd']['petugasName'] = '';
                $list[$index]['form']['ttd']['petugasCode'] = '';
                $list[$index]['form']['ttd']['petugasDate'] = '';

                $fresh['pengkajianAkhirHayatUGD'] = array_values($list);
                $this->updateJsonUGD((int) $this->rjNo, $fresh);
                $this->dataDaftarUGD = $fresh;

                $this->appendAdminLogUGD(
                    (int) $this->rjNo,
                    'Buka kunci Pengkajian Akhir Hayat — entri ' . ($list[$index]['form']['tglAsesmen'] ?? $id)
                        . ' (oleh ' . (auth()->user()->myuser_name ?? auth()->user()->name ?? '-') . ')',
                    'MR',
                );
            });

            $this->incrementVersion('modal-akhir-hayat-ugd');
            $this->dispatch('toast', type: 'success', message: 'Kunci dibuka — entri kembali draft & TTD petugas dicabut. Silakan koreksi lalu kunci ulang.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal membuka kunci: ' . $e->getMessage());
        }
    }

    /* ===============================
     | CETAK
     =============================== */
    public function cetak(string $id)
    {
        $entry = collect($this->dataDaftarUGD['pengkajianAkhirHayatUGD'] ?? [])->firstWhere('id', $id);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Data pengkajian tidak ditemukan.');
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

            $ttdPetugasPath = null;
            $petugasCode = data_get($entry, 'form.ttd.petugasCode') ?: data_get($entry, 'created_by.code');
            if ($petugasCode) {
                $ttdPath = DB::table('users')->where('myuser_code', $petugasCode)->value('myuser_ttd_image');
                if (!empty($ttdPath) && file_exists(public_path('storage/' . $ttdPath))) {
                    $ttdPetugasPath = public_path('storage/' . $ttdPath);
                }
            }

            $data = array_merge($pasien, [
                'dataRi' => $this->dataDaftarUGD,
                'entry' => $entry,
                'identitasRs' => $identitasRs,
                'ttdPetugasPath' => $ttdPetugasPath,
                'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
                'clause' => AkhirHayatClause::get(data_get($entry, 'form.ttd.clauseVersion')),
                'opsiLabel' => [
                    'skala' => $this->skalaSimptom,
                    'reaksiPasien' => $this->reaksiPasienList,
                    'masalahPasien' => $this->masalahPasienList,
                    'kondisiKeluarga' => $this->kondisiKeluargaList,
                    'masalahKeluarga' => $this->masalahKeluargaList,
                    'dukungan' => $this->dukunganList,
                    'intervensiKeperawatan' => $this->intervensiKeperawatanList,
                    'intervensiMedis' => $this->intervensiMedisList,
                    'prognosis' => $this->prognosisOptions,
                    'hubungan' => $this->hubunganOptions,
                ],
            ]);

            set_time_limit(300);

            $pdf = Pdf::loadView('pages.components.modul-dokumen.u-g-d.akhir-hayat.cetak-akhir-hayat-print', ['data' => $data])->setPaper('A4');

            $this->dispatch('toast', type: 'success', message: 'Berhasil mencetak Pengkajian Akhir Hayat.');
            return response()->streamDownload(fn() => print $pdf->output(), 'akhir-hayat-ugd-' . ($pasien['regNo'] ?? $this->rjNo) . '.pdf');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cetak: ' . $e->getMessage());
        }
    }

    public function resetFormAkhirHayat(): void
    {
        $this->form = $this->defaultForm();
        $this->form['ttd']['keluargaNama'] = $this->dataDaftarUGD['regName'] ?? '';
        $this->prefillHeader();
        $this->keluargaSignature = '';
        $this->saksiSignature = '';
        $this->editingKey = null;
        $this->viewOnly = false;
        $this->resetValidation();
        $this->incrementVersion('modal-akhir-hayat-ugd');
    }
};
?>

<div>
    {{-- ══ RINGKASAN + TOMBOL ══ --}}
    @php $akhirHayatCount = count($dataDaftarUGD['pengkajianAkhirHayatUGD'] ?? []); @endphp
    <div class="p-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex-1 space-y-2">
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">Pengkajian Akhir Hayat</h3>
                    @if ($akhirHayatCount > 0)
                        <x-badge variant="success">{{ $akhirHayatCount }} entri</x-badge>
                    @else
                        <x-badge variant="warning">Belum ada</x-badge>
                    @endif
                </div>
                <p class="text-base text-muted dark:text-gray-400">
                    Asesmen pasien terminal &amp; keluarganya: kondisi medis, simptom, psikososial-spiritual,
                    rencana &amp; intervensi (termasuk DNR), lalu ditandatangani pasien/keluarga, saksi, dan petugas.
                </p>
            </div>
            <div class="flex shrink-0">
                <x-primary-button type="button" wire:click="openModal" wire:loading.attr="disabled"
                    wire:target="openModal" :disabled="!$rjNo" class="gap-2">
                    <span wire:loading.remove wire:target="openModal" class="flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                        Buka Pengkajian Akhir Hayat
                    </span>
                    <span wire:loading wire:target="openModal" class="flex items-center gap-1.5">
                        <x-loading class="w-4 h-4" /> Memuat...
                    </span>
                </x-primary-button>
            </div>
        </div>
    </div>

    {{-- ══ MODAL ══ --}}
    <x-modal name="rm-akhir-hayat-ugd-{{ $rjNo ?? 'init' }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]">

            <div class="flex items-center justify-between gap-4 px-6 py-4 border-b border-hairline bg-surface-soft dark:border-gray-700">
                <div class="flex items-center gap-3">
                    <h2 class="text-xl font-semibold text-ink dark:text-gray-100">
                        Pengkajian Akhir Hayat
                        <span class="block text-sm font-normal text-muted dark:text-gray-400">
                            Asesmen pasien menjelang akhir hayat &amp; keluarganya
                        </span>
                    </h2>
                    @if ($akhirHayatCount > 0)
                        <x-badge variant="info">{{ $akhirHayatCount }} tersimpan</x-badge>
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

            <div class="px-4 pt-4">
                <livewire:pages::transaksi.ugd.display-pasien-ugd.display-pasien-ugd :rjNo="$rjNo"
                    wire:key="akhir-hayat-ugd-display-pasien-{{ $rjNo ?? 'init' }}" />
            </div>

            <div class="flex-1 p-4 sm:p-6 space-y-4"
                wire:key="{{ $this->renderKey('modal-akhir-hayat-ugd', [$rjNo ?? 'new']) }}">

                @php $formRO = $isFormLocked || $viewOnly; @endphp

                @if ($isFormLocked)
                    <div class="flex items-center gap-2 px-4 py-2.5 text-sm rounded-lg bg-amber-50 border border-amber-200 text-amber-800 dark:bg-amber-900/20 dark:border-amber-700 dark:text-amber-300">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                        </svg>
                        Pasien sudah pulang — form dalam mode <strong>read-only</strong>.
                    </div>
                @endif

                @if ($viewOnly)
                    <div class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-sky-700 bg-sky-50 border border-sky-200 rounded-lg dark:bg-sky-900/20 dark:border-sky-600 dark:text-sky-300">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        Menampilkan entri terkunci (hanya lihat) — klik <strong>Selesai Melihat</strong> untuk kembali ke entri baru.
                    </div>
                @elseif ($editingKey && !$isFormLocked)
                    <div class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-brand-green bg-brand-lime/10 border border-brand-lime/40 rounded-lg dark:text-brand-lime dark:bg-brand-lime/5">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        Melanjutkan entri draft — klik <strong>Entri Baru</strong> untuk asesmen ulang berikutnya.
                    </div>
                @endif

                @if (!$isFormLocked)
                    {{-- Waktu asesmen — Awal/Ulang ditentukan sistem --}}
                    <div class="flex flex-wrap items-end gap-3 p-4 bg-canvas border border-hairline rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                        <div class="flex-1 min-w-[240px]">
                            <x-input-label value="Tanggal & Jam Asesmen *" />
                            <div class="flex items-end gap-2 mt-1">
                                <x-text-input wire:model="form.tglAsesmen" class="flex-1 font-mono"
                                    placeholder="dd/mm/yyyy hh:ii:ss" readonly :error="$errors->has('form.tglAsesmen')" />
                                <x-now-button wire:click="setTglAsesmen" :disabled="$formRO" />
                            </div>
                            <x-input-error :messages="$errors->get('form.tglAsesmen')" class="mt-1" />
                        </div>
                        <div class="pb-1">
                            @php
                                $jenisTersimpan = $editingKey ? $form['jenisAsesmen'] ?? '' : '';
                                $jenisAktif = $jenisTersimpan ?: (count($dataDaftarUGD['pengkajianAkhirHayatUGD'] ?? []) > 0 ? 'ulang' : 'awal');
                            @endphp
                            <x-badge variant="info">{{ $jenisAktif === 'ulang' ? 'Asesmen Ulang' : 'Asesmen Awal' }}</x-badge>
                        </div>
                        <div class="pb-1 text-xs text-muted dark:text-gray-400">
                            Wajib: tanggal &amp; TTD pasien/keluarga. Petugas TTD paling akhir → entri terkunci.
                        </div>
                    </div>

                    {{-- ── 1. KONDISI MEDIS & FISIK ── --}}
                    <x-border-form title="1. Kondisi Medis & Penilaian Fisik" align="start"
                        bgcolor="bg-surface-soft" :collapsible="true" :open="true">
                        <fieldset @disabled($formRO)>
                            <div class="mt-3 space-y-3">
                                {{-- ── Grup: Diagnosis & Prognosis ── --}}
                                <div class="p-3 border rounded-xl border-hairline bg-canvas dark:bg-gray-800 dark:border-gray-700">
                                    <p class="mb-2 text-sm font-semibold tracking-wide uppercase text-ink dark:text-white">Diagnosis &amp; Prognosis</p>
                                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4 items-start">
                                        <div>
                                            <x-input-label value="Diagnosis Utama" />
                                            <x-textarea wire:model.blur="form.medis.diagnosaUtama" class="w-full mt-1" rows="2"
                                                placeholder="Diagnosis utama" :disabled="$formRO" />
                                        </div>
                                        <div>
                                            <x-input-label value="Diagnosis Sekunder" />
                                            <x-textarea wire:model.blur="form.medis.diagnosaSekunder" class="w-full mt-1" rows="2"
                                                placeholder="Diagnosis penyerta" :disabled="$formRO" />
                                        </div>
                                        <div>
                                            <x-input-label value="Riwayat Penyakit & Perawatan Sebelumnya" />
                                            <x-textarea wire:model.blur="form.medis.riwayat" class="w-full mt-1" rows="2"
                                                placeholder="Ringkas saja" :disabled="$formRO" />
                                        </div>
                                        <div>
                                            <x-input-label value="Prognosis" />
                                            <x-select-input wire:model.blur="form.medis.prognosis" class="w-full mt-1" :disabled="$formRO">
                                                <option value="">— Pilih —</option>
                                                @foreach ($prognosisOptions as $val => $label)
                                                    <option value="{{ $val }}">{{ $label }}</option>
                                                @endforeach
                                            </x-select-input>
                                            <x-text-input wire:model.blur="form.medis.prognosisCatatan" class="w-full mt-2"
                                                placeholder="Catatan prognosis (opsional)" :disabled="$formRO" />
                                        </div>
                                    </div>
                                </div>

                                {{-- ── Grup: Tanda Vital ── --}}
                                <div class="p-3 border rounded-xl border-hairline bg-canvas dark:bg-gray-800 dark:border-gray-700">
                                    <p class="mb-2 text-sm font-semibold tracking-wide uppercase text-ink dark:text-white">Tanda Vital</p>
                                    <div class="grid grid-cols-3 gap-2 sm:grid-cols-5 lg:grid-cols-9 items-start">
                                        @foreach ([['tb', 'TB (cm)'], ['bb', 'BB (kg)'], ['sistolik', 'Sistolik'], ['distolik', 'Diastolik'], ['nadi', 'Nadi'], ['respirasi', 'Napas'], ['suhu', 'Suhu (°C)'], ['spo2', 'Saturasi O₂ (%)']] as [$fieldFisik, $labelFisik])
                                            <div wire:key="fisik-{{ $fieldFisik }}">
                                                <x-input-label :value="$labelFisik . ' *'" />
                                                <x-text-input wire:model.blur="form.fisik.{{ $fieldFisik }}" type="number" step="any"
                                                    class="w-full mt-1" :error="$errors->has('form.fisik.' . $fieldFisik)"
                                                    :disabled="$formRO" />
                                                <x-input-error :messages="$errors->get('form.fisik.' . $fieldFisik)" class="mt-1" />
                                            </div>
                                        @endforeach
                                        <div>
                                            <x-input-label value="IMT" />
                                            <div class="px-2 py-2 mt-1 text-sm font-semibold text-center border rounded-lg border-hairline bg-surface-soft/60 text-ink dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-100">
                                                {{ $this->bmi ?? '—' }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <p class="text-xs text-muted dark:text-gray-400">
                                    Diagnosis terisi otomatis dari Pengkajian Awal bila sudah ada — koreksi bila berubah.
                                    Tanda vital diisi sesuai kondisi terkini; IMT dihitung otomatis dari TB &amp; BB.
                                </p>
                            </div>
                        </fieldset>
                    </x-border-form>

                    {{-- ── 2. SIMPTOM & MASALAH KEPERAWATAN ── --}}
                    <x-border-form title="2. Gejala & Masalah Keperawatan" align="start"
                        bgcolor="bg-surface-soft" :collapsible="true" :open="true">
                        <fieldset @disabled($formRO)>
                            <div class="mt-3 space-y-4">
                                <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4 items-start">
                                    @foreach ([['nyeri', 'Nyeri'], ['sesak', 'Sesak napas'], ['mualMuntah', 'Mual / muntah'], ['kelelahan', 'Kelelahan']] as [$fieldSimptom, $labelSimptom])
                                        <div class="p-3 border rounded-lg bg-canvas border-hairline dark:bg-gray-800 dark:border-gray-700"
                                            wire:key="simptom-{{ $fieldSimptom }}">
                                            <x-input-label :value="$labelSimptom" />
                                            <x-select-input wire:model.live="form.simptom.{{ $fieldSimptom }}"
                                                :disabled="$formRO" class="w-full mt-1.5 text-sm">
                                                <option value="">— pilih tingkat —</option>
                                                @foreach ($skalaSimptom as $val => $labelSkala)
                                                    <option value="{{ $val }}">{{ $labelSkala }}</option>
                                                @endforeach
                                            </x-select-input>

                                            {{-- Detail nyeri menempel LANGSUNG di bawah kartu Nyeri (bukan di bawah
                                                 seluruh grid) agar jelas milik Nyeri, bukan gejala lain. --}}
                                            @if ($fieldSimptom === 'nyeri'
                                                && ($form['simptom']['nyeri'] ?? '') !== ''
                                                && ($form['simptom']['nyeri'] ?? '') !== 'tidakAda')
                                                <div class="mt-3 space-y-2">
                                                    <div>
                                                        <x-input-label value="Lokasi nyeri" />
                                                        <x-text-input wire:model.blur="form.simptom.nyeriLokasi"
                                                            class="w-full mt-1 text-sm" :disabled="$formRO" />
                                                    </div>
                                                    <div>
                                                        <x-input-label value="Deskripsi nyeri" />
                                                        <x-text-input wire:model.blur="form.simptom.nyeriDeskripsi"
                                                            class="w-full mt-1 text-sm"
                                                            placeholder="Karakter / durasi / pencetus" :disabled="$formRO" />
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>

                                <div>
                                    <x-input-label value="Gejala lainnya" />
                                    <x-text-input wire:model.blur="form.simptom.lainnya" class="w-full mt-1"
                                        placeholder="Gejala lain yang menonjol" :disabled="$formRO" />
                                </div>

                            </div>
                        </fieldset>
                    </x-border-form>

                    {{-- ── 3. PSIKOSOSIAL & SPIRITUAL ── --}}
                    <x-border-form title="3. Psikososial & Spiritual" align="start"
                        bgcolor="bg-surface-soft" :collapsible="true" :open="true">
                        <fieldset @disabled($formRO)>
                            <div class="mt-3 space-y-4">
                                {{-- Dua kolom sejajar: kiri = kondisi PASIEN, kanan = kondisi KELUARGA.
                                     Di dalam tiap kolom, sub-blok berbingkai dipasang kanan-kiri (xl) agar
                                     pengelompokan "reaksi/kondisi" vs "masalah keperawatan" langsung terlihat. --}}
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 items-start">

                                    {{-- ── PASIEN ── --}}
                                    <div class="p-3 border rounded-xl border-hairline bg-canvas dark:bg-gray-800 dark:border-gray-700">
                                        <p class="mb-2 text-sm font-semibold tracking-wide uppercase text-ink dark:text-white">Pasien</p>

                                        <x-input-label value="Keadaan emosional *" />
                                        <x-textarea wire:model.blur="form.psikososial.emosional" class="w-full mt-1" rows="2"
                                            placeholder="Uraian singkat kondisi emosional pasien"
                                            :error="$errors->has('form.psikososial.emosional')" :disabled="$formRO" />
                                        <x-input-error :messages="$errors->get('form.psikososial.emosional')" class="mt-1" />

                                        <div class="grid grid-cols-1 gap-3 mt-3 xl:grid-cols-2 items-start">
                                            <div class="p-3 border rounded-lg border-hairline bg-surface-soft/60 dark:bg-gray-900/40 dark:border-gray-700">
                                                <p class="mb-2 text-xs font-semibold tracking-wide uppercase text-muted dark:text-gray-400">Reaksi atas penyakitnya</p>
                                                <div class="grid grid-cols-1 gap-2">
                                                    @foreach ($reaksiPasienList as $key => $label)
                                                        <div wire:key="reaksi-pasien-{{ $key }}">
                                                            <x-toggle :current="in_array($key, $form['psikososial']['reaksiPasien']['opsi'] ?? []) ? '1' : '0'"
                                                                trueValue="1" falseValue="0"
                                                                wireClick="toggleArrayOpt('form.psikososial.reaksiPasien.opsi', '{{ $key }}')"
                                                                :label="$label" :disabled="$formRO" />
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>

                                            <div class="p-3 border rounded-lg border-hairline bg-surface-soft/60 dark:bg-gray-900/40 dark:border-gray-700">
                                                <p class="mb-2 text-xs font-semibold tracking-wide uppercase text-muted dark:text-gray-400">Masalah keperawatan</p>
                                                <div class="grid grid-cols-1 gap-2">
                                                    @foreach ($masalahPasienList as $key => $label)
                                                        <div wire:key="masalah-pasien-{{ $key }}">
                                                            <x-toggle :current="in_array($key, $form['psikososial']['reaksiPasien']['masalah'] ?? []) ? '1' : '0'"
                                                                trueValue="1" falseValue="0"
                                                                wireClick="toggleArrayOpt('form.psikososial.reaksiPasien.masalah', '{{ $key }}')"
                                                                :label="$label" :disabled="$formRO" />
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- ── KELUARGA ── --}}
                                    <div class="p-3 border rounded-xl border-hairline bg-canvas dark:bg-gray-800 dark:border-gray-700">
                                        <p class="mb-2 text-sm font-semibold tracking-wide uppercase text-ink dark:text-white">Keluarga</p>

                                        <x-input-label value="Keadaan emosional *" />
                                        <x-textarea wire:model.blur="form.psikososial.emosionalKeluarga" class="w-full mt-1" rows="2"
                                            placeholder="Uraian singkat kondisi emosional keluarga"
                                            :error="$errors->has('form.psikososial.emosionalKeluarga')" :disabled="$formRO" />
                                        <x-input-error :messages="$errors->get('form.psikososial.emosionalKeluarga')" class="mt-1" />

                                        <div class="grid grid-cols-1 gap-3 mt-3 xl:grid-cols-2 items-start">
                                            <div class="p-3 border rounded-lg border-hairline bg-surface-soft/60 dark:bg-gray-900/40 dark:border-gray-700">
                                                <p class="mb-2 text-xs font-semibold tracking-wide uppercase text-muted dark:text-gray-400">Kondisi saat ini &amp; setelah ditinggalkan</p>
                                                <div class="grid grid-cols-1 gap-2">
                                                    @foreach ($kondisiKeluargaList as $key => $label)
                                                        <div wire:key="keluarga-{{ $key }}">
                                                            <x-toggle :current="in_array($key, $form['psikososial']['keluarga']['opsi'] ?? []) ? '1' : '0'"
                                                                trueValue="1" falseValue="0"
                                                                wireClick="toggleArrayOpt('form.psikososial.keluarga.opsi', '{{ $key }}')"
                                                                :label="$label" :disabled="$formRO" />
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>

                                            <div class="p-3 border rounded-lg border-hairline bg-surface-soft/60 dark:bg-gray-900/40 dark:border-gray-700">
                                                <p class="mb-2 text-xs font-semibold tracking-wide uppercase text-muted dark:text-gray-400">Masalah keperawatan</p>
                                                <div class="grid grid-cols-1 gap-2">
                                                    @foreach ($masalahKeluargaList as $key => $label)
                                                        <div wire:key="masalah-keluarga-{{ $key }}">
                                                            <x-toggle :current="in_array($key, $form['psikososial']['keluarga']['masalah'] ?? []) ? '1' : '0'"
                                                                trueValue="1" falseValue="0"
                                                                wireClick="toggleArrayOpt('form.psikososial.keluarga.masalah', '{{ $key }}')"
                                                                :label="$label" :disabled="$formRO" />
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="p-3 border rounded-lg border-hairline bg-canvas dark:bg-gray-800 dark:border-gray-700">
                                <p class="mb-2 text-xs font-semibold tracking-wide uppercase text-muted dark:text-gray-400">Kebutuhan spiritual</p>
                                {{-- Satu baris: perlu? · oleh siapa · bentuk kebutuhan (muncul bila "Ya") --}}
                                <div class="grid grid-cols-1 gap-3 lg:grid-cols-12 items-start">
                                    <div class="lg:col-span-2">
                                        <x-input-label value="Perlu pelayanan spiritual?" />
                                        <div class="flex gap-2 mt-1">
                                            @foreach (['tidak' => 'Tidak', 'ya' => 'Ya'] as $val => $label)
                                                <x-radio-button :label="$label" :value="$val" name="spiritualPerlu"
                                                    wire:model.live="form.spiritual.perluPelayanan" :disabled="$formRO" />
                                            @endforeach
                                        </div>
                                    </div>

                                    @if (($form['spiritual']['perluPelayanan'] ?? '') === 'ya')
                                        <div class="lg:col-span-3">
                                            <x-input-label value="Agama / Kepercayaan" />
                                            <x-select-input wire:model.blur="form.spiritual.agama" class="w-full mt-1" :disabled="$formRO">
                                                <option value="">— Pilih Agama / Kepercayaan —</option>
                                                @foreach ($agamaOptions as $opt)
                                                    <option value="{{ $opt }}">{{ $opt }}</option>
                                                @endforeach
                                            </x-select-input>
                                        </div>

                                        <div class="lg:col-span-3">
                                            <x-input-label value="Oleh (rohaniawan / pendamping)" />
                                            <x-text-input wire:model.blur="form.spiritual.oleh" class="w-full mt-1"
                                                placeholder="Nama / unit pelayanan" :disabled="$formRO" />
                                        </div>

                                        <div class="lg:col-span-4">
                                            <x-input-label value="Bentuk kebutuhan" />
                                            <div class="flex flex-wrap gap-x-4 gap-y-2 mt-2">
                                                @foreach ([['perluDidoakan', 'Didoakan'], ['perluBimbingan', 'Bimbingan rohani'], ['perluPendampingan', 'Pendampingan rohani']] as [$fieldSpiritual, $labelSpiritual])
                                                    <div wire:key="spiritual-{{ $fieldSpiritual }}">
                                                        <x-toggle :current="($form['spiritual'][$fieldSpiritual] ?? '') === 'ya' ? '1' : '0'"
                                                            trueValue="1" falseValue="0"
                                                            wireClick="toggleSpiritual('{{ $fieldSpiritual }}')"
                                                            :label="$labelSpiritual" :disabled="$formRO" />
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                </div>{{-- /bingkai spiritual --}}

                                <div class="p-3 border rounded-lg border-hairline bg-surface-soft/60 dark:bg-gray-900/40 dark:border-gray-700">
                                    <p class="mb-2 text-xs font-semibold tracking-wide uppercase text-muted dark:text-gray-400">Orang yang ingin dihubungi saat ini</p>
                                    <div class="flex gap-2 mt-1">
                                        @foreach (['tidak' => 'Tidak ada', 'ya' => 'Ada'] as $val => $label)
                                            <x-radio-button :label="$label" :value="$val" name="orangDihubungi"
                                                wire:model.live="form.psikososial.orangDihubungi.ada" :disabled="$formRO" />
                                        @endforeach
                                    </div>
                                    @if (($form['psikososial']['orangDihubungi']['ada'] ?? '') === 'ya')
                                        <div class="grid grid-cols-1 gap-3 mt-2 md:grid-cols-4 items-start">
                                            <div>
                                                <x-input-label value="Nama *" />
                                                <x-text-input wire:model.blur="form.psikososial.orangDihubungi.nama" class="w-full mt-1"
                                                    :error="$errors->has('form.psikososial.orangDihubungi.nama')" :disabled="$formRO" />
                                                <x-input-error :messages="$errors->get('form.psikososial.orangDihubungi.nama')" class="mt-1" />
                                            </div>
                                            <div>
                                                <x-input-label value="Hubungan *" />
                                                <x-text-input wire:model.blur="form.psikososial.orangDihubungi.hubungan" class="w-full mt-1"
                                                    :error="$errors->has('form.psikososial.orangDihubungi.hubungan')" :disabled="$formRO" />
                                                <x-input-error :messages="$errors->get('form.psikososial.orangDihubungi.hubungan')" class="mt-1" />
                                            </div>
                                            <div>
                                                <x-input-label value="Alamat *" />
                                                <x-text-input wire:model.blur="form.psikososial.orangDihubungi.alamat" class="w-full mt-1"
                                                    :error="$errors->has('form.psikososial.orangDihubungi.alamat')" :disabled="$formRO" />
                                                <x-input-error :messages="$errors->get('form.psikososial.orangDihubungi.alamat')" class="mt-1" />
                                            </div>
                                            <div>
                                                <x-input-label value="Telepon / HP" />
                                                <x-text-input wire:model.blur="form.psikososial.orangDihubungi.telp" class="w-full mt-1"
                                                    placeholder="08xxxxxxxxxx" :disabled="$formRO" />
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </fieldset>
                    </x-border-form>

                    {{-- ── 4. RENCANA, INTERVENSI & EDUKASI ── --}}
                    <x-border-form title="4. Rencana Perawatan, Intervensi & Edukasi" align="start"
                        bgcolor="bg-surface-soft" :collapsible="true" :open="true">
                        <fieldset @disabled($formRO)>
                            <div class="mt-3 space-y-4">
                                <div>
                                    <x-input-label value="Rencana perawatan selanjutnya" />
                                    <div class="flex flex-wrap gap-2 mt-1">
                                        @foreach (['rs' => 'Tetap dirawat di RS', 'rumah' => 'Dirawat di rumah'] as $val => $label)
                                            <x-radio-button :label="$label" :value="$val" name="rencanaPerawatan"
                                                wire:model.live="form.rencana.pilihan" :disabled="$formRO" />
                                        @endforeach
                                    </div>
                                    @if (($form['rencana']['pilihan'] ?? '') === 'rumah')
                                        <div class="grid grid-cols-1 gap-3 mt-3 md:grid-cols-3 items-start">
                                            <div class="p-3 border border-hairline rounded-lg bg-canvas dark:bg-gray-800 dark:border-gray-700">
                                                <x-input-label value="Lingkungan rumah siap?" />
                                                <div class="flex gap-2 mt-1">
                                                    @foreach (['ya' => 'Ya', 'tidak' => 'Tidak'] as $val => $label)
                                                        <x-radio-button :label="$label" :value="$val" name="lingkunganSiap"
                                                            wire:model.live="form.rencana.lingkunganSiap" :disabled="$formRO" />
                                                    @endforeach
                                                </div>
                                            </div>
                                            <div class="p-3 border border-hairline rounded-lg bg-canvas dark:bg-gray-800 dark:border-gray-700">
                                                <x-input-label value="Ada yang merawat di rumah?" />
                                                <div class="flex gap-2 mt-1">
                                                    @foreach (['ya' => 'Ya', 'tidak' => 'Tidak'] as $val => $label)
                                                        <x-radio-button :label="$label" :value="$val" name="adaPerawat"
                                                            wire:model.live="form.rencana.adaPerawat" :disabled="$formRO" />
                                                    @endforeach
                                                </div>
                                                @if (($form['rencana']['adaPerawat'] ?? '') === 'ya')
                                                    <x-text-input wire:model.blur="form.rencana.perawatOleh" class="w-full mt-2"
                                                        placeholder="Oleh siapa" :disabled="$formRO" />
                                                @endif
                                            </div>
                                            <div class="p-3 border border-hairline rounded-lg bg-canvas dark:bg-gray-800 dark:border-gray-700">
                                                <x-input-label value="Perlu perawatan rumah oleh RS?" />
                                                <div class="flex gap-2 mt-1">
                                                    @foreach (['ya' => 'Ya', 'tidak' => 'Tidak'] as $val => $label)
                                                        <x-radio-button :label="$label" :value="$val" name="perluHomeCare"
                                                            wire:model.live="form.rencana.perluHomeCare" :disabled="$formRO" />
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Instruksi perawatan di rumah menempel di sini (bukan di blok Edukasi)
                                             supaya tidak ada dua tempat membahas rencana rawat di rumah. --}}
                                        <div class="mt-3">
                                            <x-input-label value="Instruksi perawatan di rumah untuk keluarga" />
                                            <x-textarea wire:model.blur="form.edukasi.rencanaDiRumah" class="w-full mt-1" rows="2"
                                                placeholder="Obat, nutrisi, perawatan luka, tanda bahaya, kapan harus kembali ke RS"
                                                :disabled="$formRO" />
                                        </div>
                                    @endif
                                </div>

                                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 items-start">
                                    <div class="p-3 border rounded-lg border-hairline bg-surface-soft/60 dark:bg-gray-900/40 dark:border-gray-700">
                                        <p class="mb-2 text-xs font-semibold tracking-wide uppercase text-muted dark:text-gray-400">Dukungan / kelonggaran pelayanan</p>
                                        <div class="grid grid-cols-1 gap-2 mt-1">
                                            @foreach ($dukunganList as $key => $label)
                                                <div wire:key="dukungan-{{ $key }}">
                                                    <x-toggle :current="in_array($key, $form['dukungan']['opsi'] ?? []) ? '1' : '0'"
                                                        trueValue="1" falseValue="0"
                                                        wireClick="toggleArrayOpt('form.dukungan.opsi', '{{ $key }}')"
                                                        :label="$label" :disabled="$formRO" />
                                                </div>
                                            @endforeach
                                        </div>
                                        <x-text-input wire:model.blur="form.dukungan.lainnya" class="w-full mt-2"
                                            placeholder="Dukungan lain (opsional)" :disabled="$formRO" />
                                    </div>

                                    <div class="p-3 border rounded-lg border-hairline bg-surface-soft/60 dark:bg-gray-900/40 dark:border-gray-700">
                                        <p class="mb-2 text-xs font-semibold tracking-wide uppercase text-muted dark:text-gray-400">Kebutuhan pelayanan lain</p>
                                        <x-select-input wire:model.live="form.alternatif.pilihan" class="w-full max-w-xs" :disabled="$formRO">
                                            @foreach (['tidak' => 'Tidak ada', 'autopsi' => 'Autopsi', 'donasi' => 'Donasi organ', 'lainnya' => 'Lainnya'] as $val => $label)
                                                <option value="{{ $val }}">{{ $label }}</option>
                                            @endforeach
                                        </x-select-input>
                                        @if (($form['alternatif']['pilihan'] ?? '') === 'donasi')
                                            <x-text-input wire:model.blur="form.alternatif.donasiOrgan" class="w-full mt-2"
                                                placeholder="Sebutkan organ yang didonasikan"
                                                :error="$errors->has('form.alternatif.donasiOrgan')" :disabled="$formRO" />
                                            <x-input-error :messages="$errors->get('form.alternatif.donasiOrgan')" class="mt-1" />
                                        @endif
                                        @if (($form['alternatif']['pilihan'] ?? '') === 'lainnya')
                                            <x-text-input wire:model.blur="form.alternatif.lainnya" class="w-full mt-2"
                                                placeholder="Sebutkan kebutuhan pelayanan lain" :disabled="$formRO" />
                                        @endif
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 items-start">
                                    <div class="p-3 border rounded-lg border-hairline bg-surface-soft/60 dark:bg-gray-900/40 dark:border-gray-700">
                                        <p class="mb-2 text-xs font-semibold tracking-wide uppercase text-muted dark:text-gray-400">Intervensi keperawatan</p>
                                        <div class="grid grid-cols-1 gap-2 mt-1">
                                            @foreach ($intervensiKeperawatanList as $key => $label)
                                                <div wire:key="int-kep-{{ $key }}">
                                                    <x-toggle :current="in_array($key, $form['intervensi']['keperawatan'] ?? []) ? '1' : '0'"
                                                        trueValue="1" falseValue="0"
                                                        wireClick="toggleArrayOpt('form.intervensi.keperawatan', '{{ $key }}')"
                                                        :label="$label" :disabled="$formRO" />
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    <div class="p-3 border rounded-lg border-hairline bg-surface-soft/60 dark:bg-gray-900/40 dark:border-gray-700">
                                        <p class="mb-2 text-xs font-semibold tracking-wide uppercase text-muted dark:text-gray-400">Tindakan medis (termasuk keputusan DNR)</p>
                                        <div class="grid grid-cols-1 gap-2 mt-1">
                                            @foreach ($intervensiMedisList as $key => $label)
                                                <div wire:key="int-medis-{{ $key }}">
                                                    <x-toggle :current="in_array($key, $form['intervensi']['medis'] ?? []) ? '1' : '0'"
                                                        trueValue="1" falseValue="0"
                                                        wireClick="toggleArrayOpt('form.intervensi.medis', '{{ $key }}')"
                                                        :label="$label" :disabled="$formRO" />
                                                </div>
                                            @endforeach
                                        </div>
                                        <x-text-input wire:model.blur="form.intervensi.catatan" class="w-full mt-2"
                                            placeholder="Catatan intervensi (opsional)" :disabled="$formRO" />
                                    </div>
                                </div>

                                <div class="p-3 border rounded-lg border-hairline bg-surface-soft/60 dark:bg-gray-900/40 dark:border-gray-700">
                                    <p class="mb-2 text-xs font-semibold tracking-wide uppercase text-muted dark:text-gray-400">Pendidikan kesehatan (pasien &amp; keluarga) *</p>
                                    <x-textarea wire:model.blur="form.edukasi.pendidikanKesehatan" class="w-full" rows="2"
                                        placeholder="Materi edukasi yang diberikan — mis. perjalanan penyakit, manajemen nyeri, tanda menjelang akhir hayat"
                                        :error="$errors->has('form.edukasi.pendidikanKesehatan')" :disabled="$formRO" />
                                    <x-input-error :messages="$errors->get('form.edukasi.pendidikanKesehatan')" class="mt-1" />
                                </div>
                            </div>
                        </fieldset>
                    </x-border-form>

                    {{-- ── 5. PERNYATAAN & TANDA TANGAN ── --}}
                    <x-border-form title="5. Pernyataan Persetujuan & Tanda Tangan" align="start"
                        bgcolor="bg-surface-soft" :collapsible="true" :open="true">
                        <fieldset @disabled($formRO)>
                            @php $clause = \App\Support\AkhirHayatClause::get($form['ttd']['clauseVersion'] ?? null); @endphp
                            <p class="p-3 mt-3 text-sm border rounded-lg text-body bg-canvas border-hairline dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300">
                                {{ $clause['persetujuan'] }}
                            </p>

                            {{-- Tiga kolom TTD berbingkai seragam & sama tinggi (items-stretch + h-full),
                                 supaya kotak tanda tangan sejajar dan tidak terlihat berantakan. --}}
                            <div class="grid grid-cols-1 gap-4 mt-4 sm:grid-cols-2 lg:grid-cols-3 items-stretch">

                                {{-- Pasien / Keluarga --}}
                                <div class="flex flex-col h-full p-3 border rounded-lg border-hairline bg-surface-soft/60 dark:bg-gray-900/40 dark:border-gray-700">
                                    <p class="mb-2 text-xs font-semibold tracking-wide text-center uppercase text-muted dark:text-gray-400">
                                        Pasien / Keluarga
                                    </p>
                                    <div class="flex-1">
                                        @if (!empty($keluargaSignature))
                                            <x-signature.signature-result :signature="$keluargaSignature" :date="''"
                                                :disabled="$formRO" wireMethod="clearKeluargaSignature" />
                                        @elseif (!$formRO)
                                            <x-signature.signature-pad wireMethod="setKeluargaSignature" />
                                        @else
                                            <p class="py-8 text-sm italic text-center text-muted-soft">Belum ditandatangani.</p>
                                        @endif
                                        <x-input-error :messages="$errors->get('keluargaSignature')" class="mt-1" />
                                    </div>

                                    <div class="mt-3 space-y-2">
                                        <div>
                                            <x-input-label value="Nama Penanda Tangan *" />
                                            <x-text-input wire:model.blur="form.ttd.keluargaNama" class="w-full mt-1"
                                                :error="$errors->has('form.ttd.keluargaNama')" :disabled="$formRO" />
                                            <x-input-error :messages="$errors->get('form.ttd.keluargaNama')" class="mt-1" />
                                        </div>
                                        <div>
                                            <x-input-label value="Hubungan dengan Pasien *" />
                                            <x-select-input wire:model.blur="form.ttd.keluargaHubungan" class="w-full mt-1"
                                                :error="$errors->has('form.ttd.keluargaHubungan')" :disabled="$formRO">
                                                <option value="">— Pilih hubungan —</option>
                                                @foreach ($hubunganOptions as $val => $label)
                                                    <option value="{{ $val }}">{{ $label }}</option>
                                                @endforeach
                                            </x-select-input>
                                            <x-input-error :messages="$errors->get('form.ttd.keluargaHubungan')" class="mt-1" />
                                        </div>
                                    </div>
                                </div>

                                {{-- Saksi (wajib) --}}
                                <div class="flex flex-col h-full p-3 border rounded-lg border-hairline bg-surface-soft/60 dark:bg-gray-900/40 dark:border-gray-700">
                                    <p class="mb-2 text-xs font-semibold tracking-wide text-center uppercase text-muted dark:text-gray-400">
                                        Saksi *
                                    </p>
                                    <div class="flex-1">
                                        @if (!empty($saksiSignature))
                                            <x-signature.signature-result :signature="$saksiSignature" :date="''"
                                                :disabled="$formRO" wireMethod="clearSaksiSignature" />
                                        @elseif (!$formRO)
                                            <x-signature.signature-pad wireMethod="setSaksiSignature" />
                                        @else
                                            <p class="py-8 text-sm italic text-center text-muted-soft">Belum ditandatangani.</p>
                                        @endif
                                        <x-input-error :messages="$errors->get('saksiSignature')" class="mt-1" />
                                    </div>

                                    <div class="mt-3">
                                        <x-input-label value="Nama Saksi *" />
                                        <x-text-input wire:model.blur="form.ttd.saksiNama" class="w-full mt-1"
                                            placeholder="Nama saksi yang menyaksikan"
                                            :error="$errors->has('form.ttd.saksiNama')" :disabled="$formRO" />
                                        <x-input-error :messages="$errors->get('form.ttd.saksiNama')" class="mt-1" />
                                    </div>
                                </div>

                                {{-- Petugas — judul kolom sudah ada di atas, jadi komponen cukup pakai label "Nama" --}}
                                <div class="flex flex-col h-full p-3 border rounded-lg border-hairline bg-surface-soft/60 dark:bg-gray-900/40 dark:border-gray-700">
                                    <p class="mb-2 text-xs font-semibold tracking-wide text-center uppercase text-muted dark:text-gray-400">
                                        Petugas (Dokter / Perawat)
                                    </p>
                                    <div class="flex-1 flex flex-col justify-center">
                                        <x-signature.ttd-petugas :framed="false"
                                            :ttd="$form['ttd']['petugasName'] ?? ''"
                                            :date="$form['ttd']['petugasDate'] ?? ''"
                                            :code="$form['ttd']['petugasCode'] ?? ''"
                                            :locked="$formRO" :allowClear="false" sign="ttdPetugas"
                                            nameLabel="Nama" dateLabel="Jam TTD" signLabel="TTD Petugas & Kunci"
                                            emptyText="Menunggu TTD petugas." />
                                    </div>
                                    <p class="mt-3 text-xs text-muted dark:text-gray-400">
                                        Petugas menandatangani <strong>paling akhir</strong> — setelah pasien/keluarga
                                        (dan saksi) TTD. Menandatangani = memvalidasi &amp; <strong>mengunci</strong> entri ini.
                                    </p>
                                </div>
                            </div>
                        </fieldset>
                    </x-border-form>
                @endif

                {{-- ═══════════ RIWAYAT ═══════════ --}}
                <x-border-form title="Riwayat Pengkajian Akhir Hayat" align="start" bgcolor="bg-surface-soft">
                    @php $list = $dataDaftarUGD['pengkajianAkhirHayatUGD'] ?? []; @endphp
                    <div class="mt-3 overflow-x-auto bg-canvas border border-hairline rounded-xl dark:border-gray-700 dark:bg-gray-900">
                        <table class="min-w-full text-sm">
                            <thead class="bg-surface-soft dark:bg-gray-800">
                                <tr class="text-left">
                                    <th class="w-8 px-2 py-3 border-b border-hairline dark:border-gray-700"></th>
                                    <th class="px-4 py-3 text-sm font-medium text-muted dark:text-gray-400 border-b border-hairline dark:border-gray-700">Tanggal</th>
                                    <th class="px-4 py-3 text-sm font-medium text-muted dark:text-gray-400 border-b border-hairline dark:border-gray-700">Jenis</th>
                                    <th class="px-4 py-3 text-sm font-medium text-muted dark:text-gray-400 border-b border-hairline dark:border-gray-700">Penanda Tangan</th>
                                    <th class="px-4 py-3 text-sm font-medium text-muted dark:text-gray-400 border-b border-hairline dark:border-gray-700">Petugas</th>
                                    <th class="px-4 py-3 text-sm font-medium text-center text-muted dark:text-gray-400 border-b border-hairline dark:border-gray-700">Status</th>
                                    <th class="px-4 py-3 text-sm font-medium text-center text-muted dark:text-gray-400 border-b border-hairline dark:border-gray-700 w-56">Aksi</th>
                                </tr>
                            </thead>
                            @forelse (array_reverse($list) as $entri)
                                @php
                                    $formEntri = $entri['form'] ?? [];
                                    $id = $entri['id'] ?? null;
                                    $tgl = $formEntri['tglAsesmen'] ?? '-';
                                    $jenis = ($formEntri['jenisAsesmen'] ?? 'awal') === 'ulang' ? 'Ulang' : 'Awal';
                                    $keluargaNama = data_get($formEntri, 'ttd.keluargaNama', '-') ?: '-';
                                    $petugasName = data_get($formEntri, 'ttd.petugasName', '-') ?: '-';
                                    $isFinal = $this->entryIsFinal($entri);
                                    $adaDnr = in_array('dnr', data_get($formEntri, 'intervensi.medis', []) ?? [], true);
                                    $diagnosaUtama = data_get($formEntri, 'medis.diagnosaUtama') ?: '-';
                                    $nyeriLabel = $skalaSimptom[data_get($formEntri, 'simptom.nyeri')] ?? '-';
                                    $sesakLabel = $skalaSimptom[data_get($formEntri, 'simptom.sesak')] ?? '-';
                                    $saksiNama = data_get($formEntri, 'ttd.saksiNama') ?: '-';
                                    $intervensiMedisTxt = collect(data_get($formEntri, 'intervensi.medis', []) ?? [])
                                        ->map(fn($k) => $intervensiMedisList[$k] ?? $k)->implode(', ');
                                @endphp

                                <tbody wire:key="akhir-hayat-{{ $id ?: $loop->index }}"
                                    x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }"
                                    class="border-b border-hairline dark:border-gray-700">
                                    <tr @click="open = !open"
                                        class="cursor-pointer align-top hover:bg-surface-soft dark:hover:bg-gray-800/60 {{ $editingKey && $editingKey === $id ? 'bg-brand-lime/10 dark:bg-brand-lime/5' : '' }}">
                                        <td class="px-2 py-3 text-center align-middle">
                                            <svg class="w-4 h-4 mx-auto text-muted transition-transform" :class="{ 'rotate-90': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                            </svg>
                                        </td>
                                        <td class="px-4 py-3 font-mono text-muted whitespace-nowrap align-middle dark:text-gray-300">{{ $tgl }}</td>
                                        <td class="px-4 py-3 align-middle text-body dark:text-gray-300">{{ $jenis }}</td>
                                        <td class="px-4 py-3 font-medium text-ink align-middle dark:text-white">{{ $keluargaNama }}</td>
                                        <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">{{ $petugasName }}</td>
                                        <td class="px-4 py-3 text-center align-middle">
                                            <div class="flex flex-col items-center gap-1">
                                                @if ($isFinal)
                                                    <x-badge variant="info">Terkunci</x-badge>
                                                @else
                                                    <x-badge variant="warning">Draft</x-badge>
                                                @endif
                                                @if ($adaDnr)
                                                    <x-badge variant="danger">DNR</x-badge>
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
                                                        class="gap-1.5" title="Lihat entri terkunci">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                        </svg>
                                                        Lihat
                                                    </x-secondary-button>
                                                @endif
                                                @if ($id)
                                                    <x-info-button type="button" wire:click="cetak('{{ $id }}')"
                                                        wire:loading.attr="disabled" wire:target="cetak('{{ $id }}')"
                                                        class="gap-1.5" title="Cetak pengkajian">
                                                        <span wire:loading.remove wire:target="cetak('{{ $id }}')" class="flex items-center gap-1.5">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                            </svg>
                                                            Cetak
                                                        </span>
                                                        <span wire:loading wire:target="cetak('{{ $id }}')" class="flex items-center gap-1.5"><x-loading class="w-5 h-5" /> Mencetak...</span>
                                                    </x-info-button>
                                                @endif
                                                </div>

                                                {{-- Baris bawah: Buka Kunci + Hapus --}}
                                                @if (!$isFormLocked)
                                                    <div class="flex items-center justify-center gap-2">
                                                @if ($isFinal && $id && !$isFormLocked)
                                                    @can('dokumen.bukaKunci')
                                                        <x-confirm-button action="bukaKunci('{{ $id }}')"
                                                            title="Buka Kunci Pengkajian Akhir Hayat"
                                                            message="TTD petugas akan dicabut & entri kembali menjadi draft untuk dikoreksi. TTD pasien/keluarga & saksi tetap. Lanjutkan?"
                                                            confirmText="Ya, Buka Kunci" class="gap-1.5">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                    d="M8 11V7a4 4 0 118 0m-8 4h10a2 2 0 012 2v5a2 2 0 01-2 2H8a2 2 0 01-2-2v-5a2 2 0 012-2z" />
                                                            </svg>
                                                            Buka Kunci
                                                        </x-confirm-button>
                                                    @endcan
                                                @endif
                                                @if (!$isFormLocked && $id)
                                                    @can('dokumen.hapus')
                                                    <x-outline-button type="button" wire:click.prevent="removeEntry('{{ $id }}')"
                                                        wire:confirm="Hapus pengkajian ini?" wire:loading.attr="disabled"
                                                        class="!px-2 !py-1 !text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300"
                                                        title="Hapus pengkajian">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </x-outline-button>
                                                    @endcan
                                                @endif
                                                    </div>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                    <tr x-show="open" x-cloak class="bg-surface-soft/60 dark:bg-gray-800/40">
                                        <td colspan="7" class="px-6 py-4">
                                            <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                                                <div>
                                                    <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Diagnosis Utama</dt>
                                                    <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $diagnosaUtama }}</dd>
                                                </div>
                                                <div>
                                                    <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Nyeri / Sesak</dt>
                                                    <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $nyeriLabel }} / {{ $sesakLabel }}</dd>
                                                </div>
                                                <div>
                                                    <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Saksi</dt>
                                                    <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $saksiNama }}</dd>
                                                </div>
                                                <div>
                                                    <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Intervensi Medis</dt>
                                                    <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $intervensiMedisTxt ?: '-' }}</dd>
                                                </div>
                                            </dl>
                                        </td>
                                    </tr>
                                </tbody>
                            @empty
                                <tbody>
                                    <tr>
                                        <td colspan="7" class="px-4 py-8 text-center text-muted-soft">Belum ada pengkajian akhir hayat.</td>
                                    </tr>
                                </tbody>
                            @endforelse
                        </table>
                    </div>
                </x-border-form>

            </div>{{-- /konten --}}

            {{-- ══ FOOTER ══ --}}
            <div class="sticky bottom-0 z-10 px-6 py-3 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    @if ($viewOnly)
                        <p class="text-sm text-sky-600 dark:text-sky-400">Mode lihat — entri terkunci, tidak dapat diubah.</p>
                    @elseif (!$isFormLocked)
                        <p class="text-sm text-muted dark:text-gray-400">
                            Simpan draft kapan saja. Entri terkunci saat <strong>petugas menandatangani</strong> di bagian 5.
                        </p>
                    @else
                        <span></span>
                    @endif

                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <x-secondary-button type="button" wire:click="closeModal">Tutup</x-secondary-button>

                        @if ($viewOnly)
                            <x-primary-button wire:click.prevent="cancelEdit" wire:target="cancelEdit"
                                wire:loading.attr="disabled" class="gap-1.5 min-w-[160px] justify-center">
                                Selesai Melihat
                            </x-primary-button>
                        @elseif (!$isFormLocked)
                            @if ($editingKey)
                                <x-outline-button wire:click.prevent="cancelEdit" wire:target="cancelEdit"
                                    wire:loading.attr="disabled" class="gap-1.5" title="Kosongkan form untuk asesmen ulang berikutnya">
                                    Entri Baru
                                </x-outline-button>
                            @endif
                            <x-primary-button wire:click.prevent="saveDraft" wire:loading.attr="disabled"
                                wire:target="saveDraft" class="gap-2 min-w-[170px] justify-center">
                                <span wire:loading.remove wire:target="saveDraft">{{ $editingKey ? 'Simpan Perubahan' : 'Simpan Draft' }}</span>
                                <span wire:loading wire:target="saveDraft"><x-loading class="w-4 h-4" /> Menyimpan...</span>
                            </x-primary-button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </x-modal>
</div>
