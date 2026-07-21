<?php
// resources/views/pages/transaksi/ri/emr-ri/pengkajian-dokter/rm-pengkajian-dokter-ri-actions.blade.php

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\WithValidationToast\WithValidationToastTrait;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Livewire\Attributes\On;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Support\AlergiSnomed;

new class extends Component {
    use EmrRITrait, EmrUGDTrait, MasterPasienTrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public bool $isFormLocked = false;
    public bool $isReadOnlyByRole = false; // true jika user bukan Dokter/Admin — perawat boleh lihat tapi tidak edit/simpan
    public ?string $riHdrNo = null;
    public array $dataDaftarRi = [];

    // Model disamakan dgn Rekonsiliasi Obat UGD (anamnesa.rekonsiliasiObat):
    // nama/dosis/rute wajib + dua keputusan Dibawa Saat Ranap & Lanjut Saat Pulang.
    public array $rekonsiliasiObat = [
        'namaObat' => '',
        'dosis' => '',
        'rute' => '',
        'dibawaRanap' => 'Tidak',
        'lanjutPulang' => 'Tidak',
    ];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-pengkajian-dokter-ri'];

    public function mount(): void
    {
        $this->registerAreas(['modal-pengkajian-dokter-ri']);
    }

    /**
     * Copy fields dokter dari kunjungan UGD ke Pengkajian Dokter RI ini.
     * Triggered dari rekam-medis-display saat user klik "Copy Asesmen Dokter" di row UGD.
     *
     * Mapping (UGD → RI):
     * - anamnesa.keluhanUtama.keluhanUtama → pengkajianDokter.anamnesa.keluhanUtama
     * - anamnesa.riwayatPenyakitSekarangUmum.riwayatPenyakitSekarangUmum → ...riwayatPenyakit.sekarang
     * - anamnesa.riwayatPenyakitDahulu.riwayatPenyakitDahulu → ...riwayatPenyakit.dahulu
     * - anamnesa.alergi.alergi → ...jenisAlergi
     * - pemeriksaan.fisik → pengkajianDokter.fisik
     *
     * Mode: FILL-ONLY — hanya isi field yang masih KOSONG di RI.
     */
    #[On('request-copy-assessment-from-ugd-dokter')]
    public function copyFromUGDDokter(int $rjNoUGD): void
    {
        if (!$this->riHdrNo) {
            $this->dispatch('toast', type: 'error', message: 'Buka Pengkajian Dokter terlebih dahulu sebelum copy.');
            return;
        }

        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, form terkunci.');
            return;
        }

        $ugd = $this->findDataUGD($rjNoUGD);
        if (empty($ugd)) {
            $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.');
            return;
        }

        // Pastikan target struct sudah init
        $this->dataDaftarRi['pengkajianDokter'] ??= [];
        $this->dataDaftarRi['pengkajianDokter']['anamnesa'] ??= [];
        $this->dataDaftarRi['pengkajianDokter']['anamnesa']['riwayatPenyakit'] ??= ['sekarang' => '', 'dahulu' => '', 'keluarga' => ''];

        $pd = &$this->dataDaftarRi['pengkajianDokter'];

        $copied = 0;
        $skipped = 0;

        $fill = function (string &$target, string $sourceVal) use (&$copied, &$skipped): void {
            $sourceVal = trim($sourceVal);
            if ($sourceVal === '') {
                return;
            }
            if (trim($target) !== '') {
                $skipped++;
                return;
            }
            $target = $sourceVal;
            $copied++;
        };

        // 1) Keluhan Utama
        $pd['anamnesa']['keluhanUtama'] ??= '';
        $fill($pd['anamnesa']['keluhanUtama'], (string) data_get($ugd, 'anamnesa.keluhanUtama.keluhanUtama', ''));

        // 2) Riwayat Penyakit Sekarang
        $pd['anamnesa']['riwayatPenyakit']['sekarang'] ??= '';
        $fill($pd['anamnesa']['riwayatPenyakit']['sekarang'], (string) data_get($ugd, 'anamnesa.riwayatPenyakitSekarangUmum.riwayatPenyakitSekarangUmum', ''));

        // 3) Riwayat Penyakit Dahulu
        $pd['anamnesa']['riwayatPenyakit']['dahulu'] ??= '';
        $fill($pd['anamnesa']['riwayatPenyakit']['dahulu'], (string) data_get($ugd, 'anamnesa.riwayatPenyakitDahulu.riwayatPenyakitDahulu', ''));

        // 4) Jenis Alergi — teks + KODE SNOMED + jawaban Ya/Tidak.
        // Kode wajib ikut: sejak UGD punya default "Tidak ada alergi" (716186003), menyalin
        // teksnya saja membuat kode hilang di RI. Kode hanya ikut kalau teksnya juga ikut
        // (fill-only), supaya kode tak menempel ke alergi lain yang sudah ada di RI.
        $pd['anamnesa']['jenisAlergi'] ??= '';
        $alergiKosong = trim($pd['anamnesa']['jenisAlergi']) === '';
        $fill($pd['anamnesa']['jenisAlergi'], (string) data_get($ugd, 'anamnesa.alergi.alergi', ''));
        if ($alergiKosong && trim((string) $pd['anamnesa']['jenisAlergi']) !== '') {
            $pd['anamnesa']['jenisAlergiSnomedCode'] = (string) data_get($ugd, 'anamnesa.alergi.snomedCode', '');
            $pd['anamnesa']['jenisAlergiSnomedDisplayEn'] = (string) data_get($ugd, 'anamnesa.alergi.snomedDisplayEn', '');
            $pd['anamnesa']['jenisAlergiSnomedDisplayId'] = (string) data_get($ugd, 'anamnesa.alergi.snomedDisplayId', '');
            $pd['anamnesa']['adaAlergi'] = (string) data_get($ugd, 'anamnesa.alergi.adaAlergi', '');
        }

        // 5) Pemeriksaan Fisik
        $pd['fisik'] ??= '';
        $fill($pd['fisik'], (string) data_get($ugd, 'pemeriksaan.fisik', ''));

        // 6) Rekonsiliasi Obat — daftar, jadi bukan fill-only per field: obat yang
        // belum ada DITAMBAHKAN, yang namanya sudah ada di RI dilewati (tak menimpa).
        $pd['anamnesa']['rekonsiliasiObat'] ??= [];
        $obatUgd = collect(data_get($ugd, 'anamnesa.rekonsiliasiObat', []) ?? [])
            ->filter(fn($obat) => filled($obat['namaObat'] ?? null))
            ->map(
                fn($obat) => [
                    'namaObat' => $obat['namaObat'] ?? '',
                    'dosis' => $obat['dosis'] ?? '',
                    'rute' => $obat['rute'] ?? '',
                    'dibawaRanap' => ($obat['dibawaRanap'] ?? 'Tidak') === 'Ya' ? 'Ya' : 'Tidak',
                    'lanjutPulang' => ($obat['lanjutPulang'] ?? 'Tidak') === 'Ya' ? 'Ya' : 'Tidak',
                ],
            )
            ->values()
            ->toArray();

        $sebelum = count($pd['anamnesa']['rekonsiliasiObat']);
        $pd['anamnesa']['rekonsiliasiObat'] = $this->gabungRekonsiliasiObat($pd['anamnesa']['rekonsiliasiObat'], $obatUgd);
        $obatDitambah = count($pd['anamnesa']['rekonsiliasiObat']) - $sebelum;
        $copied += $obatDitambah;
        $skipped += count($obatUgd) - $obatDitambah;

        unset($pd);

        $this->incrementVersion('modal-pengkajian-dokter-ri');

        $msg = "Asesmen Dokter UGD: {$copied} field disalin";
        if ($skipped > 0) {
            $msg .= ", {$skipped} field di-skip (sudah ada nilai)";
        }
        $msg .= '. Klik Simpan untuk persist.';
        $this->dispatch('toast', type: 'success', message: $msg);
    }

    /**
     * Rekonsiliasi obat dari kunjungan UGD yang MENTRANSFER pasien ini ke ranap.
     *
     * Relasi UGD→RI disimpan permanen saat transfer di rstxn_ribiayaselamadugds
     * (rj_no = UGD, ugd_no_rsri = rihdr_no RI), jadi asalnya pasti — tidak menebak
     * lewat reg_no/tanggal. Bentuk entri di UGD & RI sudah identik, jadi disalin
     * apa adanya; record UGD lama yang belum punya dua field keputusan dinormalkan
     * ke 'Tidak' supaya tampilan/cetak RI tidak kosong.
     */
    protected function rekonsiliasiObatDariUgd(string $riHdrNo): array
    {
        $rjNoUgd = DB::table('rstxn_ribiayaselamadugds')->where('ugd_no_rsri', $riHdrNo)->orderByDesc('rj_no')->value('rj_no');

        if (empty($rjNoUgd)) {
            return [];
        }

        $ugd = $this->findDataUGD((int) $rjNoUgd);

        return collect(data_get($ugd, 'anamnesa.rekonsiliasiObat', []) ?? [])
            ->filter(fn($obat) => filled($obat['namaObat'] ?? null))
            ->map(
                fn($obat) => [
                    'namaObat' => $obat['namaObat'] ?? '',
                    'dosis' => $obat['dosis'] ?? '',
                    'rute' => $obat['rute'] ?? '',
                    'dibawaRanap' => ($obat['dibawaRanap'] ?? 'Tidak') === 'Ya' ? 'Ya' : 'Tidak',
                    'lanjutPulang' => ($obat['lanjutPulang'] ?? 'Tidak') === 'Ya' ? 'Ya' : 'Tidak',
                ],
            )
            ->values()
            ->toArray();
    }

    /** Gabung daftar obat UGD ke daftar RI — hanya yang belum ada (dedupe nama obat). */
    protected function gabungRekonsiliasiObat(array $daftarRi, array $daftarUgd): array
    {
        $namaSudahAda = collect($daftarRi)->pluck('namaObat')->map(fn($n) => strtolower(trim((string) $n)))->all();

        foreach ($daftarUgd as $obat) {
            if (in_array(strtolower(trim($obat['namaObat'])), $namaSudahAda, true)) {
                continue;
            }
            $daftarRi[] = $obat;
            $namaSudahAda[] = strtolower(trim($obat['namaObat']));
        }

        return array_values($daftarRi);
    }

    #[On('open-rm-pengkajian-dokter-ri')]
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

        // Dicek SEBELUM struktur default dipasang: kalau key rekonsiliasiObat belum
        // pernah ada, berarti Pengkajian Dokter ini belum pernah disimpan → boleh
        // ditarik dari UGD. Setelah pernah tersimpan, daftar RI jadi milik dokter RI;
        // baris yang sengaja dihapus tidak boleh muncul lagi tiap buka form.
        $belumPernahIsiRekonsiliasi = !array_key_exists('rekonsiliasiObat', (array) data_get($data, 'pengkajianDokter.anamnesa', []));

        $this->dataDaftarRi = $data;
        $this->dataDaftarRi['pengkajianDokter'] ??= [
            'anamnesa' => [
                'keluhanUtama' => '',
                'keluhanUtamaSnomedCode' => '',
                'keluhanUtamaSnomedDisplayEn' => '',
                'keluhanUtamaSnomedDisplayId' => '',
                'keluhanTambahan' => '',
                'riwayatPenyakit' => ['sekarang' => '', 'dahulu' => '', 'keluarga' => ''],
                'adaAlergi' => '', // diturunkan normalisasiRi() dari teks — JANGAN preset 'Tidak'
                'jenisAlergi' => '',
                'jenisAlergiSnomedCode' => '',
                'jenisAlergiSnomedDisplayEn' => '',
                'jenisAlergiSnomedDisplayId' => '',
                'rekonsiliasiObat' => [],
            ],
            'fisik' => '',
            'anatomi' => collect(['kepala', 'mata', 'telinga', 'hidung', 'rambut', 'bibir', 'gigiGeligi', 'lidah', 'langitLangit', 'leher', 'tenggorokan', 'tonsil', 'dada', 'payudara', 'punggung', 'perut', 'genital', 'anus', 'lenganAtas', 'lenganBawah', 'jariTangan', 'kukuTangan', 'persendianTangan', 'tungkaiAtas', 'tungkaiBawah', 'jariKaki', 'kukuKaki', 'persendianKaki', 'faring'])
                ->mapWithKeys(fn($p) => [$p => ['kelainan' => 'Tidak Diperiksa', 'desc' => '']])
                ->toArray(),
            'statusLokalis' => ['deskripsiGambar' => ''],
            'hasilPemeriksaanPenunjang' => ['laboratorium' => '', 'radiologi' => '', 'penunjangLain' => ''],
            'diagnosaAssesment' => ['diagnosaAwal' => ''],
            'rencana' => ['penegakanDiagnosa' => '', 'terapi' => '', 'terapiPulang' => '', 'diet' => '', 'edukasi' => '', 'monitoring' => ''],
            'ringkasanPasienPulang' => ['kondisiPulang' => '', 'instruksiPulang' => '', 'kontrolKe' => ''],
            'tandaTanganDokter' => ['dokterPengkaji' => '', 'dokterPengkajiCode' => '', 'jamDokterPengkaji' => ''],
        ];

        // Prefill rekonsiliasi obat dari kunjungan UGD asal transfer (pola fill-only,
        // sama semangatnya dgn prefill alergi di bawah). Tersimpan permanen ke JSON RI
        // begitu dokter menyimpan form — jadi cetakan RI tetap utuh walau data UGD berubah.
        $this->dataDaftarRi['pengkajianDokter']['anamnesa']['rekonsiliasiObat'] ??= [];
        if ($belumPernahIsiRekonsiliasi && empty($this->dataDaftarRi['pengkajianDokter']['anamnesa']['rekonsiliasiObat'])) {
            $this->dataDaftarRi['pengkajianDokter']['anamnesa']['rekonsiliasiObat'] = $this->rekonsiliasiObatDariUgd($riHdrNo);
        }

        // Prefill alergi dari MASTER PASIEN (pola sama RJ/UGD) — dokter tak perlu isi ulang
        // tiap kunjungan. Fill-only: yang sudah terisi di RI TIDAK ditimpa. Kode SNOMED hanya
        // ikut kalau teksnya juga ikut, supaya kode tak menempel ke alergi lain.
        $pasienData = $this->findDataMasterPasien($this->dataDaftarRi['regNo'] ?? '');
        $pdAnamnesa = &$this->dataDaftarRi['pengkajianDokter']['anamnesa'];
        if (trim((string) ($pdAnamnesa['jenisAlergi'] ?? '')) === '' && !empty($pasienData['pasien']['alergi'])) {
            $pdAnamnesa['jenisAlergi'] = $pasienData['pasien']['alergi'];
            if (!empty($pasienData['pasien']['alergiSnomedCode'])) {
                $pdAnamnesa['jenisAlergiSnomedCode'] = $pasienData['pasien']['alergiSnomedCode'];
                $pdAnamnesa['jenisAlergiSnomedDisplayEn'] = $pasienData['pasien']['alergiSnomedDisplayEn'] ?? '';
                $pdAnamnesa['jenisAlergiSnomedDisplayId'] = $pasienData['pasien']['alergiSnomedDisplayId'] ?? '';
            }
        }
        unset($pdAnamnesa);

        // Seragamkan alergi + turunkan radio "Ada alergi?" (default Tidak -> SNOMED
        // 716186003), pola sama RJ/UGD. RI menyimpannya dgn key BEDA (jenisAlergi*, datar)
        // sehingga dipakai normalisasiRi(); logikanya tetap satu sumber. Record lama tak
        // punya adaAlergi -> diturunkan dari teksnya, jadi tak perlu migrasi data.
        $this->dataDaftarRi['pengkajianDokter']['anamnesa'] = AlergiSnomed::normalisasiRi($this->dataDaftarRi['pengkajianDokter']['anamnesa'] ?? []);

        $this->isFormLocked = $this->checkEmrRIStatus($riHdrNo); // ← trait

        // Lock untuk role bukan Dokter/Admin: Perawat boleh lihat tapi tidak edit/simpan
        $this->isReadOnlyByRole = !auth()
            ->user()
            ->hasAnyRole(['Dokter', 'Admin']);

        $this->incrementVersion('modal-pengkajian-dokter-ri');
    }

    /** Radio "Ada alergi?" diubah -> seragamkan lewat sumber tunggal. */
    public function updatedDataDaftarRiPengkajianDokterAnamnesaAdaAlergi(): void
    {
        $this->dataDaftarRi['pengkajianDokter']['anamnesa'] = AlergiSnomed::normalisasiRi($this->dataDaftarRi['pengkajianDokter']['anamnesa'] ?? []);
    }

    #[On('save-rm-pengkajian-dokter-ri')]
    public function store(?string $logKeterangan = null): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, form terkunci.');
            return;
        }

        // Guard role: Perawat tidak boleh simpan Pengkajian Dokter
        if ($this->isReadOnlyByRole) {
            $this->dispatch('toast', type: 'warning', message: 'Hanya Dokter/Admin yang dapat menyimpan Pengkajian Dokter.');
            return;
        }

        $this->validateWithToast(['dataDaftarRi.pengkajianDokter.anamnesa.keluhanUtama' => 'required|string|max:1000'], ['dataDaftarRi.pengkajianDokter.anamnesa.keluhanUtama.required' => 'Keluhan utama wajib diisi.']);

        try {
            DB::transaction(function () use ($logKeterangan) {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                $isBaru = empty($fresh['pengkajianDokter']);
                $fresh['pengkajianDokter'] = $this->dataDaftarRi['pengkajianDokter'] ?? [];
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;

                // Side effect: sync alergi + kode SNOMED ke master pasien (pola sama RJ/UGD)
                // supaya kunjungan berikutnya — di modul mana pun — sudah terisi.
                $this->syncAlergiKeMasterPasien();

                $this->appendAdminLogRI((int) $this->riHdrNo, $logKeterangan ?? ($isBaru ? 'Buat' : 'Update') . ' Pengkajian Dokter RI', 'MR');
            });
            $this->afterSave('Pengkajian Dokter berhasil disimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /**
     * Sync alergi + kode SNOMED ke master pasien. Kode SELALU ditimpa bersama teksnya
     * (termasuk jadi kosong) supaya kode lama tak menempel pada alergi yang sudah diganti —
     * mis. teks jadi "allopurinol" tapi kode tertinggal 716186003 ("No known allergy"),
     * yang berarti melapor pasien TIDAK alergi padahal alergi.
     */
    private function syncAlergiKeMasterPasien(): void
    {
        $regNo = $this->dataDaftarRi['regNo'] ?? null;
        if (!$regNo) {
            return;
        }

        $an = $this->dataDaftarRi['pengkajianDokter']['anamnesa'] ?? [];
        $alergi = trim((string) ($an['jenisAlergi'] ?? ''));
        if ($alergi === '') {
            return;
        }

        $pasienData = $this->findDataMasterPasien($regNo);
        $pasienData['pasien']['alergi'] = $alergi;
        $pasienData['pasien']['alergiSnomedCode'] = $an['jenisAlergiSnomedCode'] ?? '';
        $pasienData['pasien']['alergiSnomedDisplayEn'] = $an['jenisAlergiSnomedDisplayEn'] ?? '';
        $pasienData['pasien']['alergiSnomedDisplayId'] = $an['jenisAlergiSnomedDisplayId'] ?? '';
        $pasienData['pasien']['regNo'] = $regNo;
        $this->updateJsonMasterPasien($regNo, $pasienData);
    }

    public function setDokterPengkaji(): void
    {
        if (
            !auth()
                ->user()
                ->hasAnyRole(['Dokter', 'Admin'])
        ) {
            $this->dispatch('toast', type: 'error', message: 'Hanya Dokter / Admin yang dapat melakukan TTD.');
            return;
        }
        $this->dataDaftarRi['pengkajianDokter']['tandaTanganDokter']['dokterPengkaji'] = auth()->user()->myuser_name;
        $this->dataDaftarRi['pengkajianDokter']['tandaTanganDokter']['dokterPengkajiCode'] = auth()->user()->myuser_code;
        $this->dataDaftarRi['pengkajianDokter']['tandaTanganDokter']['jamDokterPengkaji'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        $this->store();
    }

    public function addRekonsiliasiObat(): void
    {
        // validate() didahulukan supaya field yang kosong tetap ditandai merah
        // (guard/early-return sebelum validate bikin border error tak muncul).
        $this->validateWithToast(
            [
                'rekonsiliasiObat.namaObat' => ['required', 'string', 'max:200'],
                'rekonsiliasiObat.dosis' => ['required', 'string', 'max:100'],
                'rekonsiliasiObat.rute' => ['required', 'string'],
            ],
            [],
            [
                'rekonsiliasiObat.namaObat' => 'Nama Obat',
                'rekonsiliasiObat.dosis' => 'Dosis',
                'rekonsiliasiObat.rute' => 'Rute',
            ],
        );

        $sudahAda = collect($this->dataDaftarRi['pengkajianDokter']['anamnesa']['rekonsiliasiObat'] ?? [])
            ->where('namaObat', $this->rekonsiliasiObat['namaObat'])
            ->count();

        if ($sudahAda > 0) {
            $this->dispatch('toast', type: 'error', message: 'Obat sudah ada dalam daftar.');
            return;
        }

        $this->dataDaftarRi['pengkajianDokter']['anamnesa']['rekonsiliasiObat'][] = [
            'namaObat' => $this->rekonsiliasiObat['namaObat'],
            'dosis' => $this->rekonsiliasiObat['dosis'],
            'rute' => $this->rekonsiliasiObat['rute'],
            'dibawaRanap' => $this->rekonsiliasiObat['dibawaRanap'] ?? 'Tidak',
            'lanjutPulang' => $this->rekonsiliasiObat['lanjutPulang'] ?? 'Tidak',
        ];

        $namaObat = $this->rekonsiliasiObat['namaObat'];
        $this->reset(['rekonsiliasiObat']);
        $this->store('Tambah riwayat pemakaian obat — ' . $namaObat);
    }

    public function removeRekonsiliasiObat(int $index): void
    {
        $daftarObat = $this->dataDaftarRi['pengkajianDokter']['anamnesa']['rekonsiliasiObat'] ?? [];

        if (!isset($daftarObat[$index])) {
            return;
        }

        $namaObat = $daftarObat[$index]['namaObat'] ?? '-';
        unset($daftarObat[$index]);
        $this->dataDaftarRi['pengkajianDokter']['anamnesa']['rekonsiliasiObat'] = array_values($daftarObat);

        $this->store('Hapus riwayat pemakaian obat — ' . $namaObat);
    }

    private function afterSave(string $msg): void
    {
        $this->incrementVersion('modal-pengkajian-dokter-ri');
        $this->dispatch('refresh-after-ri.saved', tab: 'pengkajian-dokter');
        $this->dispatch('toast', type: 'success', message: $msg);
    }

    /* ── Buka E-Resep dari Pengkajian Dokter ── */
    public function openEresep(): void
    {
        if (empty($this->riHdrNo)) {
            $this->dispatch('toast', type: 'error', message: 'Nomor RI tidak ditemukan.');
            return;
        }
        $this->dispatch('emr-ri.eresep.open', riHdrNo: (int) $this->riHdrNo);
    }

    /* ===============================
     | LOV SNOMED — Keluhan Utama (RI)
     =============================== */
    #[On('lov.selected.keluhanUtamaSnomedRi')]
    public function onKeluhanUtamaSnomedRiSelected(string $target, array $payload): void
    {
        $this->dataDaftarRi['pengkajianDokter']['anamnesa']['keluhanUtamaSnomedCode'] = $payload['snomed_code'] ?? '';
        $this->dataDaftarRi['pengkajianDokter']['anamnesa']['keluhanUtamaSnomedDisplayEn'] = $payload['display_en'] ?? '';
        $this->dataDaftarRi['pengkajianDokter']['anamnesa']['keluhanUtamaSnomedDisplayId'] = $payload['display_id'] ?? '';
    }

    #[On('lov.cleared.keluhanUtamaSnomedRi')]
    public function onKeluhanUtamaSnomedRiCleared(string $target): void
    {
        $this->dataDaftarRi['pengkajianDokter']['anamnesa']['keluhanUtamaSnomedCode'] = '';
        $this->dataDaftarRi['pengkajianDokter']['anamnesa']['keluhanUtamaSnomedDisplayEn'] = '';
        $this->dataDaftarRi['pengkajianDokter']['anamnesa']['keluhanUtamaSnomedDisplayId'] = '';
    }

    /* ===============================
     | LOV SNOMED — Alergi (RI)
     =============================== */
    #[On('lov.selected.alergiSnomedRi')]
    public function onAlergiSnomedRiSelected(string $target, array $payload): void
    {
        $this->dataDaftarRi['pengkajianDokter']['anamnesa']['jenisAlergiSnomedCode'] = $payload['snomed_code'] ?? '';
        $this->dataDaftarRi['pengkajianDokter']['anamnesa']['jenisAlergiSnomedDisplayEn'] = $payload['display_en'] ?? '';
        $this->dataDaftarRi['pengkajianDokter']['anamnesa']['jenisAlergiSnomedDisplayId'] = $payload['display_id'] ?? '';
    }

    #[On('lov.cleared.alergiSnomedRi')]
    public function onAlergiSnomedRiCleared(string $target): void
    {
        $this->dataDaftarRi['pengkajianDokter']['anamnesa']['jenisAlergiSnomedCode'] = '';
        $this->dataDaftarRi['pengkajianDokter']['anamnesa']['jenisAlergiSnomedDisplayEn'] = '';
        $this->dataDaftarRi['pengkajianDokter']['anamnesa']['jenisAlergiSnomedDisplayId'] = '';
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->reset(['rekonsiliasiObat']);
    }

    /* ===============================
     | TERIMA DARI LABORATORIUM DISPLAY → Hasil Pemeriksaan Penunjang Laboratorium
     =============================== */
    #[On('laborat-kirim-penunjang')]
    public function terimaPenunjangLaborat(string $text): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat menyimpan data.');
            return;
        }

        if (empty($this->dataDaftarRi)) {
            $this->dispatch('toast', type: 'error', message: 'Data kunjungan tidak ditemukan, silakan buka ulang form.');
            return;
        }

        try {
            DB::transaction(function () use ($text) {
                $this->lockRIRow($this->riHdrNo);

                $data = $this->findDataRI($this->riHdrNo) ?: [];

                if (empty($data)) {
                    throw new \RuntimeException('Data RI tidak ditemukan.');
                }

                // Append ke pengkajianDokter.hasilPemeriksaanPenunjang.laboratorium
                $existing = $data['pengkajianDokter']['hasilPemeriksaanPenunjang']['laboratorium'] ?? '';

                // Idempotency: skip kalau $text sudah ada di tail (handle double-fire)
                if (str_ends_with(rtrim($existing), trim($text))) {
                    $this->dataDaftarRi = $data;
                    return;
                }

                $data['pengkajianDokter']['hasilPemeriksaanPenunjang']['laboratorium'] = trim(($existing ? $existing . "\n" : '') . $text);

                $this->updateJsonRI($this->riHdrNo, $data);
                $this->dataDaftarRi = $data;

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Terima hasil laboratorium ke Pengkajian Dokter RI', 'MR');
            });

            $this->dispatch('toast', type: 'success', message: 'Data laboratorium berhasil dikirim ke Hasil Pemeriksaan Penunjang.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal mengirim: ' . $e->getMessage());
        }
    }
};
?>

<div class="space-y-4" wire:key="{{ $this->renderKey('modal-pengkajian-dokter-ri', [$riHdrNo ?? 'new']) }}"
    x-data="{
        sectionDirty: false,
        openedAt: 0,
        tab: 'pengkajian-dokter',
        markDirty() {
            if (!this.sectionDirty && Date.now() - this.openedAt > 300) {
                this.sectionDirty = true;
                this.$dispatch('section-dirty', { tab: this.tab });
            }
        },
    }" x-init="openedAt = Date.now();
    window.addEventListener('refresh-after-ri.saved', (e) => {
        // hanya bereaksi pada save tab sendiri — kalau tidak, save tab lain ikut
        // menghapus penanda dirty tab ini padahal isinya belum tersimpan
        const savedTab = e.detail?.tab;
        if (savedTab && savedTab !== 'pengkajian-dokter') return;
        sectionDirty = false;
        openedAt = Date.now();
        $dispatch('section-clean', { tab: tab });
    });" x-on:input="markDirty()" x-on:change="markDirty()">

    @if ($isFormLocked)
        <div
            class="flex items-center gap-2 px-4 py-2.5 rounded-lg
                    bg-amber-50 border border-amber-200 text-amber-800
                    dark:bg-amber-900/20 dark:border-amber-700 dark:text-amber-300 text-sm">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
            </svg>
            Pasien sudah pulang — form dalam mode <strong>read-only</strong>.
        </div>
    @elseif ($isReadOnlyByRole)
        <div
            class="flex items-center gap-2 px-4 py-2.5 rounded-lg
                    bg-blue-50 border border-blue-200 text-blue-800
                    dark:bg-blue-900/20 dark:border-blue-700 dark:text-blue-300 text-sm">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Mode lihat (view-only) — hanya <strong>Dokter / Admin</strong> yang dapat mengedit & menyimpan Pengkajian
            Dokter.
        </div>
    @endif

    {{-- ══════════════════════════════════════
    | BAGIAN 1 — ANAMNESA
    ══════════════════════════════════════ --}}
    <x-border-form title="Bagian 1 — Anamnesa" align="start" bgcolor="bg-surface-soft" :collapsible="true"
        :open="true">
        <div class="mt-3 space-y-3">

            <div>
                <x-input-label value="Keluhan Utama *" />
                <x-textarea wire:model.live="dataDaftarRi.pengkajianDokter.anamnesa.keluhanUtama" class="w-full mt-1"
                    rows="3" :disabled="$isFormLocked || $isReadOnlyByRole" placeholder="Keluhan utama pasien..." />
                <x-input-error :messages="$errors->get('dataDaftarRi.pengkajianDokter.anamnesa.keluhanUtama')" class="mt-1" />
            </div>

            {{-- SNOMED CT — Keluhan Utama (untuk Satu Sehat) --}}
            {{-- DINONAKTIFKAN SEMENTARA: aktifkan RI SNOMED dengan mengubah `false` → `true`.
                 Sinkron dgn sender di satu-sehat-ri-actions (kirim-chief-complaint/allergy). --}}
            @if (false)
                <div>
                    <livewire:lov.snomed.lov-snomed target="keluhanUtamaSnomedRi"
                        label="Kode SNOMED Keluhan Utama (Satu Sehat)"
                        placeholder="Ketik keluhan dalam Bahasa Indonesia / Inggris..." valueSet="condition-code"
                        :initialSnomedCode="$dataDaftarRi['pengkajianDokter']['anamnesa']['keluhanUtamaSnomedCode'] ?? null" :disabled="$isFormLocked || $isReadOnlyByRole"
                        wire:key="lov-snomed-keluhan-ri-{{ $riHdrNo ?? 'new' }}-{{ $renderVersions['modal-pengkajian-dokter-ri'] ?? 0 }}" />
                </div>
            @endif

            <div>
                <x-input-label value="Keluhan Tambahan" />
                <x-textarea wire:model.live="dataDaftarRi.pengkajianDokter.anamnesa.keluhanTambahan" class="w-full mt-1"
                    rows="2" :disabled="$isFormLocked || $isReadOnlyByRole" placeholder="Keluhan tambahan..." />
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <x-input-label value="Riwayat Penyakit Sekarang" />
                    <x-textarea wire:model.live="dataDaftarRi.pengkajianDokter.anamnesa.riwayatPenyakit.sekarang"
                        class="w-full mt-1" rows="4" :disabled="$isFormLocked || $isReadOnlyByRole" />
                </div>
                <div>
                    <x-input-label value="Riwayat Penyakit Dahulu" />
                    <x-textarea wire:model.live="dataDaftarRi.pengkajianDokter.anamnesa.riwayatPenyakit.dahulu"
                        class="w-full mt-1" rows="4" :disabled="$isFormLocked || $isReadOnlyByRole" />
                </div>
                <div>
                    <x-input-label value="Riwayat Penyakit Keluarga" />
                    <x-textarea wire:model.live="dataDaftarRi.pengkajianDokter.anamnesa.riwayatPenyakit.keluarga"
                        class="w-full mt-1" rows="4" :disabled="$isFormLocked || $isReadOnlyByRole" />
                </div>
            </div>

            {{-- Ada alergi? — pola sama RJ/UGD. Default "Tidak" -> SNOMED 716186003 diisi di
                 server (bukan lewat LOV zat: 716186003 konsep *situation*, bukan zat, jadi
                 ditolak substance-code). Lihat App\Support\AlergiSnomed. --}}
            <div>
                <x-input-label value="Ada Alergi?" />
                <div class="flex gap-4 mt-2">
                    @foreach (['Ya', 'Tidak'] as $opt)
                        <x-radio-button :label="$opt" :value="$opt" name="adaAlergiRi"
                            wire:model.live="dataDaftarRi.pengkajianDokter.anamnesa.adaAlergi" :disabled="$isFormLocked || $isReadOnlyByRole" />
                    @endforeach
                </div>
            </div>

            @php $adaAlergiRi = ($dataDaftarRi['pengkajianDokter']['anamnesa']['adaAlergi'] ?? 'Tidak') === 'Ya'; @endphp

            @if ($adaAlergiRi)
                <div>
                    {{-- Label/komponen/placeholder DISAMAKAN dgn RJ/UGD (dulu: label "Jenis
                         Alergi", x-text-input 1 baris, placeholder beda). Key JSON tetap
                         `jenisAlergi` — itu data, mengubahnya merusak record lama. --}}
                    <x-input-label value="Alergi" :required="false" />
                    <x-textarea wire:model.live="dataDaftarRi.pengkajianDokter.anamnesa.jenisAlergi"
                        placeholder="Jenis Alergi — Makanan / Obat / Udara" :disabled="$isFormLocked || $isReadOnlyByRole" :rows="3"
                        class="w-full mt-1" />

                    {{-- SNOMED CT — ZAT penyebab alergi (Satu Sehat). DINONAKTIFKAN SEMENTARA
                         (false → true utk aktifkan, bareng LOV keluhan utama di atas). --}}
                    @if (false)
                        <div class="mt-3">
                            <livewire:lov.snomed.lov-snomed target="alergiSnomedRi"
                                label="Kode SNOMED Zat Penyebab Alergi (Satu Sehat)"
                                placeholder="Ketik nama zat / obat penyebab..." valueSet="substance-code"
                                :initialSnomedCode="$dataDaftarRi['pengkajianDokter']['anamnesa']['jenisAlergiSnomedCode'] ??
                                    null" :disabled="$isFormLocked || $isReadOnlyByRole"
                                wire:key="lov-snomed-alergi-ri-{{ $riHdrNo ?? 'new' }}-{{ $renderVersions['modal-pengkajian-dokter-ri'] ?? 0 }}" />
                        </div>
                    @endif
                </div>
            @else
                <div class="text-xs text-muted dark:text-gray-400">
                    Terekam sebagai <span class="font-semibold text-ink dark:text-gray-100">Tidak ada alergi</span>
                    <span class="font-mono text-[10px] text-muted-soft">716186003</span> untuk Satu Sehat.
                </div>
            @endif

        </div>
    </x-border-form>

    {{-- ══════════════════════════════════════
    | BAGIAN 1B — REKONSILIASI OBAT (key JSON: rekonsiliasiObat)
    ══════════════════════════════════════ --}}
    <x-border-form title="Rekonsiliasi Obat" align="start" bgcolor="bg-surface-soft" :collapsible="true"
        :open="false">
        <div class="mt-3 space-y-3">

            <div x-data="{ open: false }"
                class="overflow-hidden border rounded-2xl bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-700">
                <button type="button" @click="open = !open"
                    class="flex items-center justify-between w-full px-4 py-3 text-base font-semibold text-blue-900 transition-colors hover:bg-blue-100 dark:text-blue-200 dark:hover:bg-blue-900/30">
                    <span class="flex items-center gap-2">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Cara Pengisian Rekonsiliasi Obat
                    </span>
                    <svg class="w-4 h-4 transition-transform" :class="open ? 'rotate-180' : ''" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div x-show="open" x-collapse class="px-4 pb-4 space-y-3 text-base text-blue-900 dark:text-blue-200">
                    <p>
                        Daftar obat yang sedang / terakhir dipakai pasien <strong>sebelum masuk rawat inap</strong>
                        (rekonsiliasi obat).
                    </p>

                    <ol class="space-y-2 ml-6 list-decimal">
                        <li>
                            Isi <strong>Nama Obat</strong>, <strong>Dosis</strong>, dan <strong>Rute</strong>
                            (ketiganya wajib).
                        </li>
                        <li>
                            Tentukan <strong>Dibawa Saat Ranap</strong> (obat ikut dibawa ke ruangan) dan
                            <strong>Lanjut Saat Pulang</strong> (obat diteruskan di rumah).
                        </li>
                        <li>
                            Klik <strong>Tambah</strong> &mdash; data langsung tersimpan dan muncul di tabel.
                        </li>
                    </ol>

                    <p>
                        Isian di tabel bersifat <strong>data saja</strong>. Bila ada yang keliru, hapus
                        barisnya lalu tambahkan ulang dengan keterangan yang benar.
                    </p>
                </div>
            </div>

            @if (!$isFormLocked && !$isReadOnlyByRole)
                {{-- Nama Obat · Dosis · Rute sebaris — pola sama dgn Rekonsiliasi Obat UGD --}}
                <div class="grid grid-cols-12 gap-2">
                    <div class="col-span-5">
                        <x-input-label value="Nama Obat" :required="true" class="truncate whitespace-nowrap" />
                        <x-text-input wire:model="rekonsiliasiObat.namaObat"
                            wire:keydown.enter.prevent="addRekonsiliasiObat" placeholder="Amlodipin 10 mg"
                            :error="$errors->has('rekonsiliasiObat.namaObat')" class="w-full px-2 mt-1" />
                        <x-input-error :messages="$errors->get('rekonsiliasiObat.namaObat')" class="mt-1" />
                    </div>

                    <div class="col-span-3">
                        <x-input-label value="Dosis" :required="true" class="truncate whitespace-nowrap" />
                        <x-text-input wire:model="rekonsiliasiObat.dosis"
                            wire:keydown.enter.prevent="addRekonsiliasiObat" placeholder="1x1 tab" :error="$errors->has('rekonsiliasiObat.dosis')"
                            class="w-full px-2 mt-1" />
                        <x-input-error :messages="$errors->get('rekonsiliasiObat.dosis')" class="mt-1" />
                    </div>

                    <div class="col-span-4">
                        <x-input-label value="Rute" :required="true" class="truncate whitespace-nowrap" />
                        <x-select-input wire:model="rekonsiliasiObat.rute" :error="$errors->has('rekonsiliasiObat.rute')"
                            class="w-full px-2 mt-1">
                            <option value="">—</option>
                            @foreach (['Oral', 'Sublingual', 'IV', 'IM', 'SC', 'Inhalasi', 'Topikal', 'Rektal', 'Tetes Mata', 'Tetes Telinga', 'Lainnya'] as $rute)
                                <option value="{{ $rute }}">{{ $rute }}</option>
                            @endforeach
                        </x-select-input>
                        <x-input-error :messages="$errors->get('rekonsiliasiObat.rute')" class="mt-1" />
                    </div>
                </div>

                {{-- Keputusan rekonsiliasi: label kiri, toggle kanan --}}
                <div class="pt-1 space-y-2 border-t border-hairline dark:border-gray-700">
                    <div class="flex items-center justify-between gap-3">
                        <x-input-label value="Dibawa Saat Ranap" :required="false" />
                        <x-toggle wire:model.live="rekonsiliasiObat.dibawaRanap" trueValue="Ya" falseValue="Tidak"
                            :label="($rekonsiliasiObat['dibawaRanap'] ?? 'Tidak') === 'Ya' ? 'Ya' : 'Tidak'" />
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <x-input-label value="Lanjut Saat Pulang" :required="false" />
                        <x-toggle wire:model.live="rekonsiliasiObat.lanjutPulang" trueValue="Ya" falseValue="Tidak"
                            :label="($rekonsiliasiObat['lanjutPulang'] ?? 'Tidak') === 'Ya' ? 'Ya' : 'Tidak'" />
                    </div>
                </div>

                <x-primary-button type="button" wire:click="addRekonsiliasiObat" wire:loading.attr="disabled"
                    wire:target="addRekonsiliasiObat" class="justify-center gap-1.5 w-full">
                    <span wire:loading.remove wire:target="addRekonsiliasiObat" class="flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        Tambah
                    </span>
                    <span wire:loading wire:target="addRekonsiliasiObat" class="flex items-center gap-1.5">
                        <x-loading class="w-4 h-4" /> Menyimpan...
                    </span>
                </x-primary-button>
            @endif

            {{-- Daftar obat — susunan kolom sama dgn Rekonsiliasi Obat UGD --}}
            <div class="overflow-x-auto bg-canvas border rounded-2xl border-hairline dark:border-gray-700">
                <table class="ds-table">
                    <thead>
                        <tr>
                            <th class="ds-c w-10">No</th>
                            <th>Obat (Dosis &middot; Rute)</th>
                            <th>Keterangan</th>
                            <th class="ds-c w-14">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($dataDaftarRi['pengkajianDokter']['anamnesa']['rekonsiliasiObat'] ?? [] as $index => $obat)
                            <tr wire:key="rekon-obat-ri-{{ $riHdrNo ?? 'new' }}-{{ $index }}">
                                @php
                                    $dosisRute = collect([$obat['dosis'] ?? null, $obat['rute'] ?? null])
                                        ->filter(fn($isi) => filled($isi))
                                        ->implode(' · ');
                                @endphp
                                <td class="ds-c ds-td-meta">{{ $index + 1 }}</td>
                                <td>
                                    <div class="ds-td-strong">{{ $obat['namaObat'] ?? '-' }}</div>
                                    @if ($dosisRute)
                                        <div class="text-muted dark:text-gray-400">{{ $dosisRute }}</div>
                                    @endif
                                </td>

                                {{-- Keputusan rekonsiliasi — tampil sebagai data saja (diisi lewat form Tambah). --}}
                                <td>
                                    <div class="space-y-1.5">
                                        @foreach ([['dibawaRanap', 'Dibawa saat ranap'], ['lanjutPulang', 'Lanjut saat pulang']] as [$kolom, $judul])
                                            @php $nilai = ($obat[$kolom] ?? 'Tidak') === 'Ya' ? 'Ya' : 'Tidak'; @endphp
                                            <div class="flex items-center justify-between gap-2">
                                                <span
                                                    class="text-muted dark:text-gray-400">{{ $judul }}</span>
                                                <span
                                                    class="font-medium {{ $nilai === 'Ya' ? 'text-success-deep dark:text-success' : 'text-muted-soft' }}">
                                                    {{ $nilai }}
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                </td>

                                <td class="ds-c">
                                    @if (!$isFormLocked && !$isReadOnlyByRole)
                                        <x-confirm-button variant="danger-soft" :action="'removeRekonsiliasiObat(' . $index . ')'" title="Hapus Obat"
                                            :message="'Yakin hapus ' .
                                                ($obat['namaObat'] ?? 'obat ini') .
                                                ' dari daftar?'" confirmText="Ya, hapus" cancelText="Batal"
                                            class="px-2 py-1">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </x-confirm-button>
                                    @else
                                        <span class="text-muted-soft">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="ds-c italic text-muted-soft">
                                    Belum ada riwayat pemakaian obat.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>
    </x-border-form>

    {{-- ══════════════════════════════════════
    | BAGIAN 2.1 — PEMERIKSAAN FISIK
    ══════════════════════════════════════ --}}
    <x-border-form title="Bagian 2.1 — Pemeriksaan Fisik" align="start" bgcolor="bg-surface-soft" :collapsible="true"
        :open="false">
        <div class="mt-3">
            <x-textarea wire:model.live="dataDaftarRi.pengkajianDokter.fisik" class="w-full" rows="5"
                :disabled="$isFormLocked || $isReadOnlyByRole" placeholder="Deskripsi pemeriksaan fisik status generalis..." />
        </div>
    </x-border-form>

    {{-- ══════════════════════════════════════
    | BAGIAN 2.2 — PEMERIKSAAN ANATOMI
    ══════════════════════════════════════ --}}
    <x-border-form title="Bagian 2.2 — Pemeriksaan Anatomi" align="start" bgcolor="bg-surface-soft"
        :collapsible="true" :open="false">
        @php
            $anatomiList = [
                'kepala' => 'Kepala',
                'mata' => 'Mata',
                'telinga' => 'Telinga',
                'hidung' => 'Hidung',
                'rambut' => 'Rambut',
                'bibir' => 'Bibir',
                'gigiGeligi' => 'Gigi Geligi',
                'lidah' => 'Lidah',
                'langitLangit' => 'Langit-Langit',
                'leher' => 'Leher',
                'tenggorokan' => 'Tenggorokan',
                'tonsil' => 'Tonsil',
                'dada' => 'Dada',
                'payudara' => 'Payudara',
                'punggung' => 'Punggung',
                'perut' => 'Perut',
                'genital' => 'Genital',
                'anus' => 'Anus',
                'lenganAtas' => 'Lengan Atas',
                'lenganBawah' => 'Lengan Bawah',
                'jariTangan' => 'Jari Tangan',
                'kukuTangan' => 'Kuku Tangan',
                'persendianTangan' => 'Persendian Tangan',
                'tungkaiAtas' => 'Tungkai Atas',
                'tungkaiBawah' => 'Tungkai Bawah',
                'jariKaki' => 'Jari Kaki',
                'kukuKaki' => 'Kuku Kaki',
                'persendianKaki' => 'Persendian Kaki',
                'faring' => 'Faring',
            ];
        @endphp

        <div class="mt-4" x-data="{ activeTabAnatomi: '{{ array_key_first($anatomiList) }}' }">
            <div class="flex gap-4">

                {{-- SIDEBAR TABS --}}
                <div
                    class="w-44 shrink-0 overflow-y-auto max-h-80 rounded-lg border border-hairline dark:border-gray-700 bg-canvas dark:bg-gray-900">
                    @foreach ($anatomiList as $key => $label)
                        <button type="button" @click="activeTabAnatomi = '{{ $key }}'"
                            class="w-full text-left px-3 py-2.5 text-sm font-medium border-b border-hairline-soft dark:border-gray-700 transition-colors last:border-0"
                            :class="activeTabAnatomi === '{{ $key }}'
                                ?
                                'bg-brand text-white' :
                                'text-muted hover:bg-surface-soft hover:text-ink dark:text-gray-400 dark:hover:bg-gray-800'">
                            {{ strtoupper($label) }}
                        </button>
                    @endforeach
                </div>

                {{-- PANEL KONTEN --}}
                <div class="flex-1 min-w-0">
                    @foreach ($anatomiList as $key => $label)
                        @php
                            $kelainan =
                                $dataDaftarRi['pengkajianDokter']['anatomi'][$key]['kelainan'] ?? 'Tidak Diperiksa';
                        @endphp

                        {{-- ✅ x-data per panel agar kelainan Alpine reactive --}}
                        <div x-show="activeTabAnatomi === '{{ $key }}'" x-data="{ kelainan: '{{ $kelainan }}' }"
                            x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
                            x-transition:enter-end="opacity-100" class="space-y-3">

                            {{-- Kelainan --}}
                            <div>
                                <x-input-label :value="__(strtoupper($label) . ' — Kelainan')" />
                                <x-select-input x-on:change="kelainan = $event.target.value"
                                    wire:model.live="dataDaftarRi.pengkajianDokter.anatomi.{{ $key }}.kelainan"
                                    :disabled="$isFormLocked || $isReadOnlyByRole" class="w-full mt-1">
                                    <option value="Tidak Diperiksa">Tidak Diperiksa</option>
                                    <option value="Tidak Ada Kelainan">Tidak Ada Kelainan</option>
                                    <option value="Ada">Ada Kelainan</option>
                                </x-select-input>
                                <x-input-error :messages="$errors->get('dataDaftarRi.pengkajianDokter.anatomi.' . $key . '.kelainan')" class="mt-1" />
                            </div>

                            {{-- Deskripsi — ✅ reactive via Alpine state --}}
                            <div x-show="kelainan === 'Ada'" x-cloak>
                                <x-input-label value="Deskripsi Kelainan" />
                                <x-textarea
                                    wire:model.live="dataDaftarRi.pengkajianDokter.anatomi.{{ $key }}.desc"
                                    placeholder="Deskripsi kelainan {{ $label }}..." :disabled="$isFormLocked || $isReadOnlyByRole"
                                    rows="4" class="w-full mt-1" />
                                <x-input-error :messages="$errors->get('dataDaftarRi.pengkajianDokter.anatomi.' . $key . '.desc')" class="mt-1" />
                            </div>

                        </div>
                    @endforeach
                </div>

            </div>
        </div>
    </x-border-form>

    {{-- ══════════════════════════════════════
    | BAGIAN 3 — STATUS LOKALIS
    ══════════════════════════════════════ --}}
    <x-border-form title="Bagian 3 — Status Lokalis" align="start" bgcolor="bg-surface-soft" :collapsible="true"
        :open="false">
        <div class="mt-3">
            <x-textarea wire:model.live="dataDaftarRi.pengkajianDokter.statusLokalis.deskripsiGambar" class="w-full"
                rows="4" :disabled="$isFormLocked || $isReadOnlyByRole" placeholder="Deskripsi status lokalis..." />
        </div>
    </x-border-form>

    {{-- ══════════════════════════════════════
    | BAGIAN 4 — HASIL PEMERIKSAAN PENUNJANG
    ══════════════════════════════════════ --}}
    <x-border-form title="Bagian 4 — Hasil Pemeriksaan Penunjang" align="start" bgcolor="bg-surface-soft"
        :collapsible="true" :open="false">
        <div class="mt-3 grid grid-cols-3 gap-3">
            <div>
                <x-input-label value="Laboratorium" />
                <x-textarea wire:model.live="dataDaftarRi.pengkajianDokter.hasilPemeriksaanPenunjang.laboratorium"
                    class="w-full mt-1" rows="5" :disabled="$isFormLocked || $isReadOnlyByRole" />
            </div>
            <div>
                <x-input-label value="Radiologi" />
                <x-textarea wire:model.live="dataDaftarRi.pengkajianDokter.hasilPemeriksaanPenunjang.radiologi"
                    class="w-full mt-1" rows="5" :disabled="$isFormLocked || $isReadOnlyByRole" />
            </div>
            <div>
                <x-input-label value="Penunjang Lain" />
                <x-textarea wire:model.live="dataDaftarRi.pengkajianDokter.hasilPemeriksaanPenunjang.penunjangLain"
                    class="w-full mt-1" rows="5" :disabled="$isFormLocked || $isReadOnlyByRole" />
            </div>
        </div>
    </x-border-form>

    {{-- ══════════════════════════════════════
    | BAGIAN 5 — DIAGNOSA & RENCANA
    ══════════════════════════════════════ --}}
    <x-border-form title="Bagian 5 — Diagnosis & Rencana Terapi" align="start" bgcolor="bg-surface-soft"
        :collapsible="true" :open="false">
        <div class="mt-3 space-y-3">
            <div>
                <x-input-label value="Diagnosis Awal / Assessment" />
                <x-textarea wire:model.live="dataDaftarRi.pengkajianDokter.diagnosaAssesment.diagnosaAwal"
                    class="w-full mt-1" rows="2" :disabled="$isFormLocked || $isReadOnlyByRole" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                @foreach ([['key' => 'penegakanDiagnosa', 'label' => 'Penegakan Diagnosis'], ['key' => 'terapi', 'label' => 'Terapi'], ['key' => 'terapiPulang', 'label' => 'Terapi Pulang'], ['key' => 'diet', 'label' => 'Diet'], ['key' => 'edukasi', 'label' => 'Edukasi'], ['key' => 'monitoring', 'label' => 'Monitoring']] as $field)
                    <div>
                        <x-input-label value="{{ $field['label'] }}" />
                        <x-textarea wire:model.live="dataDaftarRi.pengkajianDokter.rencana.{{ $field['key'] }}"
                            class="w-full mt-1" rows="2" :disabled="$isFormLocked || $isReadOnlyByRole" />
                    </div>
                @endforeach
            </div>
        </div>
    </x-border-form>

    {{-- ══════════════════════════════════════
    | BAGIAN 7 — RINGKASAN PASIEN PULANG
    ══════════════════════════════════════ --}}
    <x-border-form title="Bagian 7 — Ringkasan Pasien Pulang" align="start" bgcolor="bg-surface-soft"
        :collapsible="true" :open="false">
        {{-- Kondisi Pulang + Instruksi Pulang + Kontrol Ke — satu baris --}}
        <div class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-3 items-start">
            <div>
                <x-input-label value="Kondisi Saat Pulang" />
                <x-textarea wire:model.live="dataDaftarRi.pengkajianDokter.ringkasanPasienPulang.kondisiPulang"
                    class="w-full mt-1" rows="3" :disabled="$isFormLocked || $isReadOnlyByRole"
                    placeholder="Deskripsi kondisi pasien saat pulang..." />
            </div>
            <div>
                <x-input-label value="Instruksi / Saran Pulang" />
                <x-textarea wire:model.live="dataDaftarRi.pengkajianDokter.ringkasanPasienPulang.instruksiPulang"
                    class="w-full mt-1" rows="3" :disabled="$isFormLocked || $isReadOnlyByRole"
                    placeholder="Instruksi diet, aktivitas, obat pulang..." />
            </div>
            <div>
                <x-input-label value="Kontrol Ke" />
                <x-text-input wire:model.live="dataDaftarRi.pengkajianDokter.ringkasanPasienPulang.kontrolKe"
                    class="w-full mt-1" :disabled="$isFormLocked || $isReadOnlyByRole" placeholder="Poli / dokter tujuan kontrol..." />
            </div>
        </div>
    </x-border-form>

    {{-- ══════════════════════════════════════
    | BAGIAN 6 — TANDA TANGAN DOKTER
    ══════════════════════════════════════ --}}
    <x-border-form title="Bagian 6 — Tanda Tangan Dokter Pengkaji" align="start" bgcolor="bg-surface-soft"
        :collapsible="true" :open="false">
        <div class="mt-3">
            <x-signature.ttd-petugas :framed="false" :allowClear="false" :ttd="$dataDaftarRi['pengkajianDokter']['tandaTanganDokter']['dokterPengkaji'] ?? ''" :date="$dataDaftarRi['pengkajianDokter']['tandaTanganDokter']['jamDokterPengkaji'] ?? ''"
                :code="$dataDaftarRi['pengkajianDokter']['tandaTanganDokter']['dokterPengkajiCode'] ?? ''" :locked="$isFormLocked || $isReadOnlyByRole" :canSign="auth()
                    ->user()
                    ?->hasAnyRole(['Dokter', 'Admin'])" sign="setDokterPengkaji"
                nameLabel="Dokter Pengkaji" dateLabel="Jam TTD" signLabel="TTD Saya" />
        </div>
    </x-border-form>


</div>
