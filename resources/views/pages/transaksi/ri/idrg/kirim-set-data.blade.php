<?php
// resources/views/pages/transaksi/ri/idrg/kirim-set-data.blade.php
// Step 3: Set Data Klaim — form editable tarif_rs + tanggal sebelum POST ke E-Klaim.
// Coder Casemix bisa adjust tarif kalau breakdown auto dari kasir tidak sesuai.

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\iDRG\iDrgTrait;
// Dipanggil statik (VclaimTrait::peserta_nomorkartu) — TIDAK di-use sebagai trait
// untuk hindari collision method dengan iDrgTrait/MasterPasienTrait/EmrRITrait.
use App\Http\Traits\BPJS\VclaimTrait;

new class extends Component {
    use EmrRITrait, MasterPasienTrait, iDrgTrait;

    public ?string $riHdrNo = null;

    // Form state — full claim data payload sesuai Manual hal. 16-21
    public array $claimData = [
        'nomor_sep' => '',
        'nomor_kartu' => '',
        'tgl_masuk' => '',
        'tgl_pulang' => '',
        'cara_masuk' => 'gp',
        'jenis_rawat' => '2',
        // kelas_rawat iDRG = hak kelas peserta BPJS (sep.peserta.hakKelas.kode),
        // fallback kelas kamar terakhir → '3'. Grouping INA-CBG/iDRG ikut hak kelas.
        'kelas_rawat' => '3',
        // hak_kelas BPJS (1/2/3). Sumber sama dengan kelas_rawat.
        'hak_kelas' => '3',
        // Umur saat tgl_masuk (Manual hal. 18). Server tolak "Umur invalid" kalau kosong/0 untuk dewasa.
        'umur_tahun' => '0',
        'umur_hari' => '0',
        'discharge_status' => '1',
        // Bayi baru lahir — berat lahir (gram) untuk grouping neonatal E-Klaim.
        'birth_weight' => '0',
        // Data Klinis — tekanan darah (mmHg).
        'sistole' => '0',
        'diastole' => '0',
        'nomor_kartu_t' => 'kartu_jkn',
        // Mandatory sejak v5.4.11 (Manual hal. 16). Default JKN: payor_id=3, payor_cd=JKN.
        // Adjust kalau RS pakai Payplan ID lain di setup Jaminan E-Klaim.
        'payor_id' => '3',
        'payor_cd' => 'JKN',
        // Kelas tarif INA-CBG (Manual hal. 21). DS = Kelas D Swasta — sesuai RS Imadinah.
        // Tanpa parameter ini server tolak dengan E2014 "Kode tarif invalid".
        'kode_tarif' => 'DS',
        // DPJP utama RI (Manual hal. 17). Diambil dari JSON
        // pengkajianAwalPasienRawatInap.levelingDokter[*] where levelDokter='Utama'.
        // Wajib diisi — kalau kosong, set_claim_data ditolak sebelum kirim ke E-Klaim.
        'nama_dokter' => '',
        // dr_id DPJP terpilih — internal untuk LOV dokter (di-unset dari payload E-Klaim).
        'dpjp_dr_id' => '',
        'tarif_rs' => [
            'prosedur_non_bedah' => '0',
            'prosedur_bedah' => '0',
            'konsultasi' => '0',
            'tenaga_ahli' => '0',
            'keperawatan' => '0',
            'penunjang' => '0',
            'radiologi' => '0',
            'laboratorium' => '0',
            'pelayanan_darah' => '0',
            'rehabilitasi' => '0',
            'kamar' => '0',
            'rawat_intensif' => '0',
            'obat' => '0',
            'obat_kronis' => '0',
            'obat_kemoterapi' => '0',
            'alkes' => '0',
            'bmhp' => '0',
            'sewa_alat' => '0',
        ],
    ];

    // APGAR Score — struktur sesuai Manual E-Klaim 5.10.x (set_claim_data): object `apgar`
    // dengan dua bagian menit_1 & menit_5, tiap bagian 5 elemen (nilai 0/1/2).
    // Manual: apgar selalu disimpan, hanya diperhitungkan saat umur pasien <= 1 hari.
    public array $apgar = [
        'menit_1' => ['appearance' => '0', 'pulse' => '0', 'grimace' => '0', 'activity' => '0', 'respiration' => '0'],
        'menit_5' => ['appearance' => '0', 'pulse' => '0', 'grimace' => '0', 'activity' => '0', 'respiration' => '0'],
    ];

    public ?string $claimDataSavedAt = null;
    public bool $idrgFinal = false;
    public bool $hasClaim = false;

    // SITB (pasien TB) — toggle + validasi No. Registrasi SITB di bawah DPJP.
    // Wajib tervalidasi sebelum Final iDRG (cegah E2066) — guard tetap di kirim-final-idrg.
    public bool $isTb = false;
    public string $nomorRegisterSitb = '';
    public bool $sitbValidated = false;
    public ?string $sitbValidatedAt = null;

    /* ===============================
     | LIFECYCLE
     =============================== */
    public function mount(?string $riHdrNo = null): void
    {
        $this->riHdrNo = $riHdrNo;
        $this->reloadState();
    }

    private function reloadState(): void
    {
        if (empty($this->riHdrNo)) {
            return;
        }
        $data = $this->findDataRI($this->riHdrNo);
        if (empty($data)) {
            return;
        }
        $idrg = $data['idrg'] ?? [];
        $this->idrgFinal = !empty($idrg['idrgFinal']);
        $this->hasClaim = !empty($idrg['nomorSep']);
        $this->claimDataSavedAt = $idrg['claimDataSavedAt'] ?? null;

        // Kalau sudah pernah save, load dari claimData yang tersimpan.
        // Kalau belum, auto-build dari kasir (sekali, bisa override coder via Sync).
        if (!empty($idrg['claimData']) && is_array($idrg['claimData'])) {
            $this->claimData = array_replace_recursive($this->claimData, $idrg['claimData']);
        } else {
            $this->autoBuildFromKasir($data);
        }

        // Guard: claimData legacy / saved sebelum autoBuild bisa punya tgl_masuk/tgl_pulang kosong.
        // Server iDRG tolak dgn error 'tgl_masuk'. Fallback ke entry_date/exit_date dari rstxn_rihdrs.
        if (empty($this->claimData['tgl_masuk']) || empty($this->claimData['tgl_pulang'])) {
            $dates = $this->riClaimDates((int) $this->riHdrNo);
            if (empty($this->claimData['tgl_masuk'])) {
                $this->claimData['tgl_masuk'] = $dates['tglMasuk'];
            }
            if (empty($this->claimData['tgl_pulang'])) {
                $this->claimData['tgl_pulang'] = $dates['tglPulang'];
            }
        }

        // APGAR Score tersimpan terpisah dari claimData (lihat properti $apgar).
        $this->apgar = array_replace_recursive($this->apgar, is_array($idrg['apgar'] ?? null) ? $idrg['apgar'] : []);

        $sitb = $idrg['sitb'] ?? [];
        $this->isTb = !empty($sitb['isTb']);
        $this->nomorRegisterSitb = (string) ($sitb['nomor'] ?? '');
        $this->sitbValidated = !empty($sitb['validated']);
        $this->sitbValidatedAt = $sitb['validatedAt'] ?? null;
    }

    /* ===============================
     | AUTO-BUILD dari Kasir RI (mapping konfirmasi user 2026-04-21)
     =============================== */
    public function syncFromKasir(): void
    {
        if (empty($this->riHdrNo)) {
            return;
        }
        $data = $this->findDataRI($this->riHdrNo);
        if (empty($data)) {
            return;
        }
        $this->autoBuildFromKasir($data);
        $this->dispatch('toast', type: 'success', message: 'Tarif & data klaim di-sync dari kasir RI.');
    }

    /**
     * Cek hak kelas peserta langsung ke BPJS (Peserta/nokartu) lalu set
     * kelas_rawat + hak_kelas. Dipakai saat SEP belum menyimpan klsRawatHak
     * (mis. SEP dibuat manual / kelas masih fallback). Panggilan statik
     * VclaimTrait::peserta_nomorkartu — sama dgn modal SEP RI.
     */
    public function cekHakKelasBpjs(): void
    {
        $kartu = trim((string) ($this->claimData['nomor_kartu'] ?? ''));
        if ($kartu === '') {
            $this->dispatch('toast', type: 'error', message: 'Nomor kartu BPJS kosong — Sync dari Kasir dulu.');
            return;
        }
        try {
            $tgl = !empty($this->claimData['tgl_masuk'])
                ? Carbon::parse($this->claimData['tgl_masuk'])->format('Y-m-d')
                : Carbon::now()->format('Y-m-d');
            $resp = VclaimTrait::peserta_nomorkartu($kartu, $tgl)->getOriginalContent();
            if (($resp['metadata']['code'] ?? 500) == 200) {
                $peserta = $resp['response']['peserta'] ?? [];
                $hak = (string) ($peserta['hakKelas']['kode'] ?? '');
                if ($hak === '') {
                    $this->dispatch('toast', type: 'error', message: 'Hak kelas tidak ada di respons BPJS.');
                    return;
                }
                $this->claimData['hak_kelas'] = $hak;
                $this->claimData['kelas_rawat'] = $hak;
                $this->dispatch('toast', type: 'success', message: 'Hak kelas BPJS: ' . ($peserta['hakKelas']['keterangan'] ?? $hak));
            } else {
                $this->dispatch('toast', type: 'error', message: 'Gagal cek peserta: ' . ($resp['metadata']['message'] ?? '-'));
            }
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Error cek hak kelas: ' . $e->getMessage());
        }
    }

    /**
     * Mapping tarif_rs RI sesuai keputusan user (konfirmasi 2026-04-21):
     *   - room + commonService + perawatan     → kamar
     *   - ok                                   → prosedur_bedah
     *   - jasaMedis                            → prosedur_non_bedah
     *   - konsul + adminAge + adminStatus
     *     + trfUgdRj                           → konsultasi
     *   - jasaDokter                           → tenaga_ahli
     *   - lainLain                             → penunjang
     *   - rad                                  → radiologi
     *   - lab                                  → laboratorium
     *   - obatPinjam + bonResep − rtnObat      → obat
     *
     * jenis_rawat = '1' (inap).
     * kelas_rawat = hak kelas peserta BPJS (klsRawatHak dari SEP RI),
     *   fallback resSep.peserta.hakKelas.kode → class_id kamar terakhir → '3'.
     * discharge_status default '1', user bisa override via dropdown form
     * (saved ke $idrg['dischargeStatus']).
     */
    private function autoBuildFromKasir(array $dataRI): void
    {
        $cost = $this->calculateRICosts((int) $this->riHdrNo);
        $dates = $this->riClaimDates((int) $this->riHdrNo);
        // Kelas kamar aktual (kamar terakhir) — dipakai sebagai fallback saja.
        $kelasKamar = $this->lastKamarClassIdRI((int) $this->riHdrNo) ?: '3';
        // kelas_rawat untuk iDRG = HAK KELAS peserta BPJS (bukan kelas kamar aktual),
        // karena tarif INA-CBG/iDRG di-grouping berdasar hak kelas peserta.
        // Sumber otentik: klsRawatHak yang disimpan saat buat SEP RI
        // (diisi dari cek peserta BPJS). resSep.peserta.hakKelas hanya fallback
        // (respons insert SEP biasanya tak memuatnya). Fallback akhir: kelas kamar → '3'.
        $hakKelas = (string) data_get($dataRI, 'sep.reqSep.request.t_sep.klsRawat.klsRawatHak', '');
        if ($hakKelas === '') {
            $hakKelas = (string) data_get($dataRI, 'sep.resSep.peserta.hakKelas.kode', '');
        }
        $kelas = $hakKelas !== '' ? $hakKelas : $kelasKamar;
        $idrg = $dataRI['idrg'] ?? [];
        $discharge = (string) ($idrg['dischargeStatus'] ?? '1');
        $pasienData = $this->findDataMasterPasien($dataRI['regNo'] ?? '');
        $pasien = $pasienData['pasien'] ?? [];

        $nomorKartu = data_get($pasien, 'identitas.idbpjs') ?: data_get($dataRI, 'sep.resSep.peserta.noKartu') ?: data_get($dataRI, 'sep.reqSep.t_sep.noKartu') ?: '';

        // Fallback: idrg.nomorSep (set saat buat klaim) → idrg.claimNumber → SEP BPJS
        $this->claimData['nomor_sep'] = $idrg['nomorSep'] ?? ($idrg['claimNumber'] ?? data_get($dataRI, 'sep.noSep', ''));
        $this->claimData['nomor_kartu'] = $nomorKartu;
        $this->claimData['tgl_masuk'] = $dates['tglMasuk'];
        $this->claimData['tgl_pulang'] = $dates['tglPulang'];
        $this->claimData['cara_masuk'] = 'gp';
        $this->claimData['jenis_rawat'] = '1';
        $this->claimData['kelas_rawat'] = (string) $kelas;
        $this->claimData['discharge_status'] = $discharge;
        $this->claimData['nomor_kartu_t'] = 'kartu_jkn';
        $this->claimData['payor_id'] = env('IDRG_PAYOR_ID', '3');
        $this->claimData['payor_cd'] = env('IDRG_PAYOR_CD', 'JKN');
        $this->claimData['kode_tarif'] = env('IDRG_KODE_TARIF', 'DS');
        $dpjp = $this->resolveDpjpUtama($dataRI);
        $this->claimData['nama_dokter'] = $dpjp['drName'];
        $this->claimData['dpjp_dr_id'] = $dpjp['drId'];

        // hak_kelas dari SEP peserta.hakKelas.kode (fallback ke kelas kamar / '3')
        $this->claimData['hak_kelas'] = $hakKelas !== '' ? $hakKelas : $kelasKamar;

        // Umur dihitung dari tglLahir pasien vs tgl_masuk (admit date RI).
        $umur = $this->computeUmur($pasien, $this->claimData['tgl_masuk']);
        if ($umur !== null) {
            $this->claimData['umur_tahun'] = (string) $umur['tahun'];
            $this->claimData['umur_hari'] = (string) $umur['hari'];
        }

        $obatTotal = max(0, ($cost['obatPinjam'] ?? 0) + ($cost['bonResep'] ?? 0) - ($cost['rtnObat'] ?? 0));

        $this->claimData['tarif_rs']['prosedur_non_bedah'] = (string) ($cost['jasaMedis'] ?? 0);
        $this->claimData['tarif_rs']['prosedur_bedah'] = (string) ($cost['ok'] ?? 0);
        // konsultasi = visit + konsul + adminAge + adminStatus + trfUgdRj
        // (sebelumnya `visit` ter-skip → total iDRG beda dari total administrasi-ri).
        $this->claimData['tarif_rs']['konsultasi'] = (string) (($cost['visit'] ?? 0) + ($cost['konsul'] ?? 0) + ($cost['adminAge'] ?? 0) + ($cost['adminStatus'] ?? 0) + ($cost['trfUgdRj'] ?? 0));
        $this->claimData['tarif_rs']['tenaga_ahli'] = (string) ($cost['jasaDokter'] ?? 0);
        $this->claimData['tarif_rs']['keperawatan'] = '0';
        $this->claimData['tarif_rs']['penunjang'] = (string) ($cost['lainLain'] ?? 0);
        $this->claimData['tarif_rs']['radiologi'] = (string) ($cost['rad'] ?? 0);
        $this->claimData['tarif_rs']['laboratorium'] = (string) ($cost['lab'] ?? 0);
        $this->claimData['tarif_rs']['pelayanan_darah'] = '0';
        $this->claimData['tarif_rs']['rehabilitasi'] = '0';
        $this->claimData['tarif_rs']['kamar'] = (string) (($cost['room'] ?? 0) + ($cost['commonService'] ?? 0) + ($cost['perawatan'] ?? 0));
        $this->claimData['tarif_rs']['rawat_intensif'] = '0';
        $this->claimData['tarif_rs']['obat'] = (string) $obatTotal;
        $this->claimData['tarif_rs']['obat_kronis'] = '0';
        $this->claimData['tarif_rs']['obat_kemoterapi'] = '0';
        $this->claimData['tarif_rs']['alkes'] = '0';
        $this->claimData['tarif_rs']['bmhp'] = '0';
        $this->claimData['tarif_rs']['sewa_alat'] = '0';
    }

    /**
     * Normalisasi datetime ke "Y-m-d H:i:s" untuk iDRG. Coba parse via Carbon dgn beberapa format.
     * Fallback ke $default kalau parse gagal / input kosong.
     */
    private function normalizeClaimDate(string $val, string $default): string
    {
        $val = trim($val);
        if ($val === '') {
            return $default;
        }
        // Coba parse beberapa format umum (Y-m-d HH:MM:SS, ISO-T, d/m/Y HH:MM:SS, dsb)
        foreach (['Y-m-d H:i:s', 'Y-m-d\TH:i:s', 'Y-m-d H:i', 'd/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y', 'Y-m-d'] as $fmt) {
            try {
                return Carbon::createFromFormat($fmt, $val)->format('Y-m-d H:i:s');
            } catch (\Throwable) {
            }
        }
        // Last resort: Carbon::parse — sukses untuk hampir semua format ISO/long
        try {
            return Carbon::parse($val)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return $default;
        }
    }

    /**
     * Hitung umur dari tglLahir pasien vs tgl_masuk klaim.
     * Carbon 3: pakai abs+int untuk safety sign-flip diff.
     * Return null kalau tglLahir tidak bisa di-parse — caller pilih biarkan default 0/0.
     */
    private function computeUmur(array $pasien, string $tglMasuk): ?array
    {
        $birthStr = trim((string) data_get($pasien, 'tglLahir', ''));
        if ($birthStr === '') {
            return null;
        }
        try {
            $birth = Carbon::createFromFormat('d/m/Y', $birthStr);
        } catch (\Throwable) {
            try {
                $birth = Carbon::parse($birthStr);
            } catch (\Throwable) {
                return null;
            }
        }
        try {
            $masuk = Carbon::parse($tglMasuk);
        } catch (\Throwable) {
            return null;
        }
        $years = (int) abs($birth->diffInYears($masuk));
        $days = (int) abs((clone $birth)->addYears($years)->diffInDays($masuk));
        return ['tahun' => $years, 'hari' => $days];
    }

    /* ===============================
     | DPJP Utama dari JSON
     | Path: pengkajianAwalPasienRawatInap.levelingDokter[*] where levelDokter='Utama'.
     | Pola sama dengan PendapatanRsTrait::extractDpjpUtamaPendapatan().
     | Return drId + drName — drId dipakai LOV dokter, drName untuk payload E-Klaim.
     =============================== */
    private function resolveDpjpUtama(array $dataRI): array
    {
        $kosong = ['drId' => '', 'drName' => ''];
        $list = data_get($dataRI, 'pengkajianAwalPasienRawatInap.levelingDokter', []);
        if (!is_array($list)) {
            return $kosong;
        }
        $drId = '';
        foreach ($list as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            if (strcasecmp((string) ($entry['levelDokter'] ?? ''), 'Utama') === 0) {
                $drId = (string) ($entry['drId'] ?? '');
                if ($drId !== '') {
                    break;
                }
            }
        }
        if ($drId === '') {
            return $kosong;
        }
        return [
            'drId' => $drId,
            'drName' => (string) (DB::table('rsmst_doctors')->where('dr_id', $drId)->value('dr_name') ?? ''),
        ];
    }

    /* ===============================
     | SITB (pasien TB)
     =============================== */

    // Persist toggle "Pasien TB" segera supaya status bertahan saat reload / jadi pengingat validasi.
    public function updatedIsTb($value): void
    {
        if (empty($this->riHdrNo)) {
            return;
        }
        $data = $this->findDataRI($this->riHdrNo);
        $idrg = $data['idrg'] ?? [];
        $sitb = $idrg['sitb'] ?? [];
        $sitb['isTb'] = (bool) $value;
        $idrg['sitb'] = $sitb;
        $this->saveResult($this->riHdrNo, $idrg);
    }

    public function validateSitbAction(): void
    {
        if (empty($this->riHdrNo)) {
            return;
        }
        try {
            $data = $this->findDataRI($this->riHdrNo);
            $idrg = $data['idrg'] ?? [];
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) {
                $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.');
                return;
            }
            $nomor = trim($this->nomorRegisterSitb);
            if ($nomor === '') {
                $this->dispatch('toast', type: 'error', message: 'No. Registrasi SITB wajib diisi.');
                return;
            }

            $res = $this->validateSitb($nomorSep, $nomor)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Validasi SITB'));
                return;
            }

            $idrg['sitb'] = ['isTb' => true, 'nomor' => $nomor, 'validated' => true, 'validatedAt' => now()->toIso8601String()];
            $this->saveResult($this->riHdrNo, $idrg);
            $this->sitbValidated = true;
            $this->sitbValidatedAt = $idrg['sitb']['validatedAt'];
            $this->dispatch('toast', type: 'success', message: 'No. Registrasi SITB tervalidasi.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Validasi SITB gagal: ' . $e->getMessage());
        }
    }

    public function invalidateSitbAction(): void
    {
        if (empty($this->riHdrNo)) {
            return;
        }
        try {
            $data = $this->findDataRI($this->riHdrNo);
            $idrg = $data['idrg'] ?? [];
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) {
                $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.');
                return;
            }

            $res = $this->invalidateSitb($nomorSep)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Batalkan Validasi SITB'));
                return;
            }

            $sitb = $idrg['sitb'] ?? [];
            $sitb['validated'] = false;
            $sitb['validatedAt'] = null;
            $idrg['sitb'] = $sitb;
            $this->saveResult($this->riHdrNo, $idrg);
            $this->sitbValidated = false;
            $this->sitbValidatedAt = null;
            $this->dispatch('toast', type: 'success', message: 'Validasi SITB dibatalkan.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Batalkan validasi SITB gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | LOV DOKTER — override DPJP (hanya nama yang dikirim ke E-Klaim)
     =============================== */
    #[On('lov.selected.dokter-dpjp-idrg-ri')]
    public function onDpjpSelected(?array $payload): void
    {
        if ($this->idrgFinal || empty($payload)) {
            return;
        }
        $this->claimData['dpjp_dr_id'] = (string) ($payload['dr_id'] ?? '');
        $this->claimData['nama_dokter'] = (string) ($payload['dr_name'] ?? '');
    }

    #[On('lov.cleared.dokter-dpjp-idrg-ri')]
    public function onDpjpCleared(): void
    {
        if ($this->idrgFinal) {
            return;
        }
        // Kosongkan dua-duanya — kalau user tidak pilih dokter baru,
        // fallback auto jalan lagi saat Simpan.
        $this->claimData['dpjp_dr_id'] = '';
        $this->claimData['nama_dokter'] = '';
    }

    /* ===============================
     | API ACTION — set_claim_data
     =============================== */

    #[On('idrg-set-data-ri.set')]
    public function set(string $riHdrNo): void
    {
        try {
            $data = $this->findDataRI($riHdrNo);
            if (empty($data)) {
                throw new \RuntimeException('Data RJ tidak ditemukan.');
            }
            $idrg = $data['idrg'] ?? [];
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) {
                $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat (new_claim dulu).');
                return;
            }

            // Sinkron nomor_sep dari state idrg (jaga-jaga form belum di-sync)
            $this->claimData['nomor_sep'] = $nomorSep;

            // DPJP (nama_dokter) mandatory di set_claim_data (Manual hal. 17).
            // Pilihan LOV dokter dihormati — coder boleh override DPJP. Kalau kosong,
            // fallback auto dari JSON pengkajianAwalPasienRawatInap.levelingDokter[*] (levelDokter='Utama').
            $namaDokter = trim((string) ($this->claimData['nama_dokter'] ?? ''));
            if ($namaDokter === '') {
                $dpjp = $this->resolveDpjpUtama($data);
                $namaDokter = trim($dpjp['drName']);
                $this->claimData['dpjp_dr_id'] = $dpjp['drId'];
            }
            if ($namaDokter === '') {
                $this->dispatch('toast', type: 'error', message: 'DPJP Utama kosong di Pengkajian Awal RI (levelingDokter). Set DPJP Utama dulu, atau ketik manual nama dokter di form.');
                return;
            }
            $this->claimData['nama_dokter'] = $namaDokter;

            // Umur (Manual hal. 18): recalc dari tglLahir+tgl_masuk kalau form masih 0/0
            // (legacy claim data sebelum field umur ada, atau autoBuild gagal parse).
            // Form user yang sudah > 0 dihormati.
            $umurTahunForm = (int) ($this->claimData['umur_tahun'] ?? 0);
            $umurHariForm = (int) ($this->claimData['umur_hari'] ?? 0);
            $pasien = null;
            if ($umurTahunForm === 0 && $umurHariForm === 0) {
                $pasien = $this->findDataMasterPasien($data['regNo'] ?? '')['pasien'] ?? [];
                $umur = $this->computeUmur($pasien, (string) $this->claimData['tgl_masuk']);
                if ($umur !== null) {
                    $this->claimData['umur_tahun'] = (string) $umur['tahun'];
                    $this->claimData['umur_hari'] = (string) $umur['hari'];
                    $umurTahunForm = $umur['tahun'];
                    $umurHariForm = $umur['hari'];
                }
            }
            // Block kalau umur tetap 0/0 sementara pasien jelas bukan bayi baru lahir
            // (tglLahir kosong → tidak bisa hitung sama sekali).
            if ($umurTahunForm === 0 && $umurHariForm === 0) {
                $pasien = $pasien ?? ($this->findDataMasterPasien($data['regNo'] ?? '')['pasien'] ?? []);
                $birthStr = trim((string) data_get($pasien, 'tglLahir', ''));
                if ($birthStr === '') {
                    $this->dispatch('toast', type: 'error', message: 'Tgl lahir pasien kosong. Isi tgl lahir di Master Pasien, atau isi manual umur di form.');
                    return;
                }
            }

            // coder_nik mandatory di set_claim_data (Manual 5.10.x hal. 14).
            // Ambil dari emp_id user login (pola sama dengan kirim-final-klaim).
            $coderNik = (string) (auth()->user()->emp_id ?? '');
            if (empty($coderNik)) {
                $this->dispatch('toast', type: 'error', message: 'User aktif tidak punya emp_id. Hubungi admin untuk set Karyawan di profil user.');
                return;
            }
            $this->claimData['coder_nik'] = $coderNik;

            // Normalisasi tgl_masuk / tgl_pulang ke "Y-m-d H:i:s" — anti-bocor format invalid
            // (legacy saved claimData bisa punya format DD/MM/YYYY, ISO-T, dgn ms, dsb).
            // Server iDRG strict: format salah → error "Tanggal masuk (tgl_masuk) invalid" saat grouping.
            $dates = $this->riClaimDates((int) $this->riHdrNo);
            $this->claimData['tgl_masuk'] = $this->normalizeClaimDate($this->claimData['tgl_masuk'] ?? '', $dates['tglMasuk']);
            $this->claimData['tgl_pulang'] = $this->normalizeClaimDate($this->claimData['tgl_pulang'] ?? '', $dates['tglPulang']);

            // Validasi tanggal — riClaimDates sekarang tidak fallback ke now(), jadi kalau kosong
            // berarti benar-benar belum ada datanya. Block dengan pesan jelas supaya user tau
            // harus isi manual atau lengkapi data RI dulu.
            if (empty($this->claimData['tgl_masuk'])) {
                $this->dispatch('toast', type: 'error', message: 'Tanggal Masuk kosong. Cek kolom entry_date di rstxn_rihdrs atau isi manual field "Tgl Masuk" di form Set Data Klaim.');
                return;
            }
            if (empty($this->claimData['tgl_pulang'])) {
                $this->dispatch('toast', type: 'error', message: 'Tanggal Keluar kosong — pasien belum check-out (exit_date kosong di rstxn_rihdrs). Lakukan discharge dulu atau isi manual field "Tgl Pulang" sebelum simpan klaim.');
                return;
            }

            // APGAR Score → object `apgar` (Manual E-Klaim 5.10.x). Nilai di-cast int (0/1/2).
            // birth_weight / sistole / diastole sudah ada di claimData.
            $payload = $this->claimData;
            // dpjp_dr_id internal untuk LOV dokter — bukan parameter E-Klaim.
            unset($payload['dpjp_dr_id']);
            $apgarPayload = [];
            foreach (['menit_1', 'menit_5'] as $row) {
                foreach (['appearance', 'pulse', 'grimace', 'activity', 'respiration'] as $el) {
                    $apgarPayload[$row][$el] = (int) ($this->apgar[$row][$el] ?? 0);
                }
            }
            $payload['apgar'] = $apgarPayload;

            // Kirim ke E-Klaim
            $res = $this->setClaimData($nomorSep, $payload)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $msg = self::describeEklaimError($res['metadata'] ?? [], 'Simpan Data Klaim');
                $rawMsg = (string) ($res['metadata']['message'] ?? '');
                if (preg_match('/\bE200[56]\b/', $rawMsg)) {
                    $msg .= " (NIK yang dikirim: {$coderNik}). Daftarkan NIK ini di Personnel Registration app E-Klaim, atau ubah users.emp_id ke NIK yang sudah terdaftar.";
                }
                $this->dispatch('toast', type: 'error', message: $msg);
                return;
            }

            $idrg['claimData'] = $this->claimData;
            $idrg['apgar'] = $this->apgar;
            $idrg['claimDataSavedAt'] = now()->toIso8601String();
            $this->saveResult($riHdrNo, $idrg);
            $this->dispatch('toast', type: 'success', message: 'Data klaim tersimpan di E-Klaim.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'set_claim_data gagal: ' . $e->getMessage());
        }
    }

    public function setForCurrent(): void
    {
        if (empty($this->riHdrNo)) {
            return;
        }
        $this->set($this->riHdrNo);
    }

    private function saveResult(string $riHdrNo, array $idrg): void
    {
        DB::transaction(function () use ($riHdrNo, $idrg) {
            $this->lockRIRow($riHdrNo);
            $data = $this->findDataRI($riHdrNo);
            $data['idrg'] = $idrg;
            $this->updateJsonRI($riHdrNo, $data);
        });

        $this->dispatch('idrg-section-changed-ri', riHdrNo: (string) $riHdrNo);
    }
};
?>

<div class="p-4 space-y-3 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700">
    {{-- Header --}}
    <div class="flex items-start justify-between gap-3">
        <div class="flex items-center gap-3">
            <div
                class="flex items-center justify-center w-8 h-8 rounded-full {{ !empty($claimDataSavedAt) ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500' }}">
                <span class="text-sm font-bold">3</span>
            </div>
            <div>
                <div class="font-semibold text-gray-800 dark:text-gray-100">Simpan Data Klaim</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    Tarif & tanggal auto dari kasir RI. Coder boleh adjust sebelum kirim.
                </div>
            </div>
        </div>
        <div class="flex flex-wrap items-center justify-end gap-2 shrink-0">
            <button type="button" wire:click="syncFromKasir" wire:loading.attr="disabled" @disabled($idrgFinal)
                class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 disabled:opacity-50 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                <span wire:loading.remove wire:target="syncFromKasir">↻ Sync dari Kasir</span>
                <span wire:loading wire:target="syncFromKasir"><x-loading />...</span>
            </button>
        </div>
    </div>

    {{-- Neonatus (usia 0–28 hari): field Berat Lahir + APGAR hanya relevan di rentang ini.
         umur_tahun/umur_hari auto-computed dari tglLahir vs tgl_masuk (autoBuildFromKasir). --}}
    @php
        $isNeonatus = (int) ($claimData['umur_tahun'] ?? 0) === 0 && (int) ($claimData['umur_hari'] ?? 0) <= 28;
    @endphp

    {{-- Identitas + Klasifikasi --}}
    <fieldset class="p-3 border border-gray-200 rounded-lg dark:border-gray-700" @disabled($idrgFinal)>
        <legend class="px-2 text-sm font-semibold tracking-wide text-gray-600 uppercase dark:text-gray-400">
            Identitas & Klasifikasi
        </legend>
        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-5">
            <div>
                <x-input-label value="Nomor SEP" class="text-sm" />
                <x-text-input wire:model="claimData.nomor_sep" readonly
                    class="font-mono text-sm bg-gray-50 dark:bg-gray-800" />
            </div>
            <div>
                <x-input-label value="Nomor Kartu BPJS" class="text-sm" />
                <x-text-input wire:model="claimData.nomor_kartu" readonly
                    class="font-mono text-sm bg-gray-50 dark:bg-gray-800" />
            </div>
            <div>
                <x-input-label value="Jenis Kartu" class="text-sm" />
                <x-select-input wire:model="claimData.nomor_kartu_t" :disabled="$idrgFinal" class="text-sm">
                    <option value="kartu_jkn">JKN (BPJS)</option>
                    <option value="nik">NIK</option>
                    <option value="kitas">KITAS</option>
                    <option value="kitap">KITAP</option>
                    <option value="paspor">Paspor</option>
                    <option value="kk">Kartu Keluarga</option>
                    <option value="sjp">SJP</option>
                    <option value="klaim_ibu">Klaim Ibu (Bayi Baru Lahir)</option>
                    <option value="lainnya">Lainnya</option>
                </x-select-input>
            </div>
            <div>
                <x-input-label value="Tgl Masuk" class="text-sm" />
                <x-text-input wire:model="claimData.tgl_masuk" placeholder="yyyy-mm-dd HH:MM:SS" :disabled="$idrgFinal"
                    class="font-mono text-sm" />
            </div>
            <div>
                <x-input-label value="Tgl Pulang" class="text-sm" />
                <x-text-input wire:model="claimData.tgl_pulang" placeholder="yyyy-mm-dd HH:MM:SS" :disabled="$idrgFinal"
                    class="font-mono text-sm" />
            </div>
            <div>
                <x-input-label value="Cara Masuk" class="text-sm" />
                <x-select-input wire:model="claimData.cara_masuk" :disabled="$idrgFinal" class="text-sm">
                    <option value="gp">GP (referral umum)</option>
                    <option value="sp">Spesialis</option>
                    <option value="fl">Datang Sendiri</option>
                </x-select-input>
            </div>
            <div>
                <x-input-label value="Jenis Rawat" class="text-sm" />
                <x-select-input wire:model="claimData.jenis_rawat" :disabled="$idrgFinal" class="text-sm">
                    <option value="1">1 — Rawat Inap</option>
                    <option value="2">2 — Rawat Jalan</option>
                    <option value="3">3 — IGD</option>
                </x-select-input>
            </div>
            <div>
                <x-input-label value="Kelas Rawat" class="text-sm" />
                <x-select-input wire:model="claimData.kelas_rawat" :disabled="$idrgFinal" class="text-sm">
                    <option value="1">Kelas 1</option>
                    <option value="2">Kelas 2</option>
                    <option value="3">Kelas 3</option>
                </x-select-input>
            </div>
            <div>
                <div class="flex items-center justify-between">
                    <x-input-label value="Hak Kelas BPJS" class="text-sm" />
                    <button type="button" wire:click="cekHakKelasBpjs" wire:loading.attr="disabled"
                        wire:target="cekHakKelasBpjs" @disabled($idrgFinal)
                        class="text-xs font-medium text-blue-600 hover:underline disabled:opacity-50 dark:text-blue-400">
                        <span wire:loading.remove wire:target="cekHakKelasBpjs">↻ Cek BPJS</span>
                        <span wire:loading wire:target="cekHakKelasBpjs">Cek…</span>
                    </button>
                </div>
                <x-select-input wire:model="claimData.hak_kelas" :disabled="$idrgFinal" class="text-sm">
                    <option value="1">Kelas 1</option>
                    <option value="2">Kelas 2</option>
                    <option value="3">Kelas 3</option>
                </x-select-input>
            </div>
            <div>
                <x-input-label value="Umur (Tahun)" class="text-sm" />
                <x-text-input wire:model="claimData.umur_tahun" :disabled="$idrgFinal" inputmode="numeric"
                    class="font-mono text-sm" />
            </div>
            <div>
                <x-input-label value="Umur (Hari sisa)" class="text-sm" />
                <x-text-input wire:model="claimData.umur_hari" :disabled="$idrgFinal" inputmode="numeric"
                    class="font-mono text-sm" />
            </div>
            <div>
                <x-input-label value="Discharge Status" class="text-sm" />
                <x-select-input wire:model="claimData.discharge_status" :disabled="$idrgFinal" class="text-sm">
                    <option value="1">1 — Atas Persetujuan Dokter</option>
                    <option value="2">2 — Dirujuk</option>
                    <option value="3">3 — Atas Permintaan Sendiri (APS)</option>
                    <option value="4">4 — Meninggal</option>
                    <option value="5">5 — Lain-lain</option>
                </x-select-input>
            </div>
            @if ($isNeonatus)
                <div>
                    <x-input-label value="Berat Lahir (gram)" class="text-sm" />
                    <x-text-input wire:model="claimData.birth_weight" :disabled="$idrgFinal" inputmode="numeric"
                        placeholder="0" class="font-mono text-sm" />
                    <p class="mt-1 text-xs text-gray-400">Khusus bayi baru lahir (neonatal).</p>
                </div>
            @endif
            <div class="md:col-span-3 lg:col-span-5">
                <livewire:lov.dokter.lov-dokter target="dokter-dpjp-idrg-ri" label="DPJP Utama (Nama Dokter)"
                    placeholder="Cari nama/kode dokter untuk override DPJP..." :initial-dr-id="$claimData['dpjp_dr_id'] ?: null"
                    :disabled="$idrgFinal"
                    wire:key="lov-dpjp-idrg-ri-{{ $riHdrNo }}-{{ $claimData['dpjp_dr_id'] ?: 'auto' }}-{{ $idrgFinal ? 1 : 0 }}" />
                <p class="mt-1 text-xs text-gray-400">
                    Hanya nama dokter yang dikirim ke E-Klaim — kosongkan (Ubah) untuk ambil ulang otomatis
                    dari DPJP Utama Pengkajian Awal RI saat Simpan.
                    @if (empty($claimData['dpjp_dr_id']) && !empty($claimData['nama_dokter']))
                        Nilai tersimpan: <span class="font-medium">{{ $claimData['nama_dokter'] }}</span>
                    @endif
                </p>
            </div>

            {{-- SITB (pasien TB) — toggle reveal No. Registrasi SITB, wajib validasi sebelum Final iDRG (cegah E2066) --}}
            <div class="md:col-span-3 lg:col-span-5 pt-2 border-t border-gray-100 dark:border-gray-700">
                <x-toggle wire:model.live="isTb" :trueValue="true" :falseValue="false"
                    label="Pasien TB (perlu validasi No. Registrasi SITB)" :disabled="$idrgFinal" />
                @if ($isTb)
                    <div class="flex flex-wrap items-end gap-2 mt-2">
                        <div class="flex-1 min-w-[220px]">
                            <x-input-label value="No. Registrasi SITB" class="text-sm" />
                            <x-text-input wire:model="nomorRegisterSitb" :disabled="$sitbValidated || $idrgFinal"
                                placeholder="Nomor register SITB pasien TB"
                                class="font-mono text-sm {{ $sitbValidated ? 'bg-emerald-50 dark:bg-emerald-900/20' : '' }}" />
                        </div>
                        @if (!$sitbValidated)
                            <x-primary-button type="button" wire:click="validateSitbAction" wire:loading.attr="disabled"
                                :disabled="$idrgFinal" class="!bg-brand hover:!bg-brand/90">
                                <span wire:loading.remove wire:target="validateSitbAction">Validasi SITB</span>
                                <span wire:loading wire:target="validateSitbAction"><x-loading />...</span>
                            </x-primary-button>
                        @else
                            <button type="button" wire:click="invalidateSitbAction" wire:loading.attr="disabled" @disabled($idrgFinal)
                                class="px-3 py-1.5 text-sm font-medium text-amber-700 bg-amber-50 rounded-lg hover:bg-amber-100 disabled:opacity-50 dark:bg-amber-900/20 dark:text-amber-400 dark:hover:bg-amber-900/30">
                                <span wire:loading.remove wire:target="invalidateSitbAction">↶ Batalkan Validasi</span>
                                <span wire:loading wire:target="invalidateSitbAction"><x-loading />...</span>
                            </button>
                        @endif
                    </div>
                    @if ($sitbValidated)
                        <div class="mt-1 text-sm text-emerald-600 dark:text-emerald-400">
                            ✓ SITB tervalidasi
                            @if ($sitbValidatedAt)— <span class="font-mono">{{ $sitbValidatedAt }}</span>@endif
                        </div>
                    @else
                        <div class="mt-1 text-sm text-amber-600 dark:text-amber-400">
                            Pasien TB wajib validasi SITB sebelum Final iDRG (cegah E2066).
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </fieldset>

    {{-- Data Klinis (mirror E-Klaim INA-CBG: Tekanan Darah + APGAR Score) --}}
    <fieldset class="p-3 border border-gray-200 rounded-lg dark:border-gray-700" @disabled($idrgFinal)>
        <legend class="px-2 text-sm font-semibold tracking-wide text-gray-600 uppercase dark:text-gray-400">
            Data Klinis
        </legend>

        {{-- Tekanan Darah --}}
        <div class="mb-4">
            <x-input-label value="Tekanan Darah (mmHg)" class="text-sm" />
            <div class="flex items-end gap-2 mt-1">
                <div class="w-24">
                    <x-text-input wire:model="claimData.sistole" :disabled="$idrgFinal" inputmode="numeric"
                        placeholder="0" class="font-mono text-sm text-center" />
                    <p class="mt-1 text-xs text-center text-gray-400">Sistole</p>
                </div>
                <span class="pb-5 text-gray-400">/</span>
                <div class="w-24">
                    <x-text-input wire:model="claimData.diastole" :disabled="$idrgFinal" inputmode="numeric"
                        placeholder="0" class="font-mono text-sm text-center" />
                    <p class="mt-1 text-xs text-center text-gray-400">Diastole</p>
                </div>
            </div>
        </div>

        {{-- APGAR Score — hanya tampil untuk neonatus (usia 0–28 hari) --}}
        @if ($isNeonatus)
        @php
            $apgarCols = [
                'appearance' => 'Appearance',
                'pulse' => 'Pulse',
                'grimace' => 'Grimace',
                'activity' => 'Activity',
                'respiration' => 'Respiration',
            ];
        @endphp
        <div class="overflow-x-auto">
            <x-input-label value="APGAR Score" class="text-sm" />
            <table class="mt-1 text-sm border-collapse">
                <thead>
                    <tr class="text-xs text-gray-500 dark:text-gray-400">
                        <th class="px-2 py-1"></th>
                        @foreach ($apgarCols as $label)
                            <th class="px-2 py-1 font-medium text-center">{{ $label }}</th>
                        @endforeach
                        <th class="px-2 py-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach (['menit_1' => '1 menit', 'menit_5' => '5 menit'] as $row => $rowLabel)
                        <tr>
                            <td class="px-2 py-1 font-medium text-gray-600 whitespace-nowrap dark:text-gray-300">
                                {{ $rowLabel }}</td>
                            @foreach ($apgarCols as $key => $label)
                                <td class="px-1 py-1">
                                    <x-text-input wire:model="apgar.{{ $row }}.{{ $key }}" :disabled="$idrgFinal"
                                        inputmode="numeric" placeholder="0" class="font-mono text-sm text-center w-14" />
                                </td>
                            @endforeach
                            <td class="px-2 py-1 text-xs text-gray-500 whitespace-nowrap dark:text-gray-400">
                                Total: <span
                                    class="font-mono font-semibold text-gray-700 dark:text-gray-200">{{ array_sum(array_map('intval', $apgar[$row] ?? [])) }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="mt-1 text-xs text-gray-400">Khusus bayi baru lahir. Tiap komponen 0–2 (total per baris 0–10).</p>
        </div>
        @endif
    </fieldset>

    {{-- Tarif RS --}}
    @php
        $tarifFields = [
            'prosedur_non_bedah' => 'Prosedur Non-Bedah',
            'prosedur_bedah' => 'Prosedur Bedah',
            'konsultasi' => 'Konsultasi',
            'tenaga_ahli' => 'Tenaga Ahli',
            'keperawatan' => 'Keperawatan',
            'penunjang' => 'Penunjang',
            'radiologi' => 'Radiologi',
            'laboratorium' => 'Laboratorium',
            'pelayanan_darah' => 'Pelayanan Darah',
            'rehabilitasi' => 'Rehabilitasi',
            'kamar' => 'Kamar',
            'rawat_intensif' => 'Rawat Intensif',
            'obat' => 'Obat',
            'obat_kronis' => 'Obat Kronis',
            'obat_kemoterapi' => 'Obat Kemoterapi',
            'alkes' => 'Alkes',
            'bmhp' => 'BMHP',
            'sewa_alat' => 'Sewa Alat',
        ];
        $totalTarif = 0;
        foreach (array_keys($tarifFields) as $k) {
            $totalTarif += (int) ($claimData['tarif_rs'][$k] ?? 0);
        }
    @endphp
    <fieldset class="p-3 border border-gray-200 rounded-lg dark:border-gray-700" @disabled($idrgFinal)>
        <legend class="px-2 text-sm font-semibold tracking-wide text-gray-600 uppercase dark:text-gray-400">
            Tarif RS (Rp)
        </legend>
        <div class="grid grid-cols-2 gap-2 md:grid-cols-3 lg:grid-cols-6">
            @foreach ($tarifFields as $key => $label)
                <div>
                    <x-input-label :value="$label" class="text-sm" />
                    <x-text-input-number wire:model="claimData.tarif_rs.{{ $key }}" :disabled="$idrgFinal" />
                </div>
            @endforeach
        </div>
        <div class="flex flex-col items-end gap-2 pt-2 mt-2 border-t border-gray-100 dark:border-gray-700">
            <div class="text-sm">
                <span class="text-gray-500">Total Tarif: </span>
                <span class="font-mono font-semibold text-gray-800 dark:text-gray-100">
                    Rp {{ number_format($totalTarif, 0, ',', '.') }}
                </span>
            </div>
            <x-primary-button type="button" wire:click="setForCurrent" wire:loading.attr="disabled"
                :disabled="$idrgFinal || !$hasClaim"
                class="!bg-brand hover:!bg-brand/90 min-w-[220px] {{ !empty($claimDataSavedAt) ? '!bg-emerald-600' : '' }}">
                <span wire:loading.remove wire:target="setForCurrent">
                    {{ !empty($claimDataSavedAt) ? 'Simpan Ulang Data Klaim' : 'Simpan Data Klaim' }}
                </span>
                <span wire:loading wire:target="setForCurrent"><x-loading />...</span>
            </x-primary-button>
        </div>
    </fieldset>

    @if (!empty($claimDataSavedAt))
        <div
            class="px-2 py-1.5 text-sm text-gray-600 bg-emerald-50 rounded dark:bg-emerald-900/20 dark:text-emerald-300">
            ✓ Tersimpan di E-Klaim — {{ $claimDataSavedAt }}
        </div>
    @endif
</div>
