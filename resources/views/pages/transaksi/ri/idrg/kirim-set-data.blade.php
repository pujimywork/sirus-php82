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
        'kelas_rawat' => '3',
        // hak_kelas BPJS (1/2/3). Diambil dari SEP peserta.hakKelas.kode.
        // Berbeda dengan kelas_rawat (yang aktual ditempati pasien).
        'hak_kelas' => '3',
        // Umur saat tgl_masuk (Manual hal. 18). Server tolak "Umur invalid" kalau kosong/0 untuk dewasa.
        'umur_tahun' => '0',
        'umur_hari' => '0',
        'discharge_status' => '1',
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

    public ?string $claimDataSavedAt = null;
    public bool $idrgFinal = false;
    public bool $hasClaim = false;

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
        //cek ini
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
     * kelas_rawat = class_id kamar terakhir pasien (fallback '3').
     * discharge_status default '1', user bisa override via dropdown form
     * (saved ke $idrg['dischargeStatus']).
     */
    private function autoBuildFromKasir(array $dataRI): void
    {
        $cost = $this->calculateRICosts((int) $this->riHdrNo);
        $dates = $this->riClaimDates((int) $this->riHdrNo);
        $kelas = $this->lastKamarClassIdRI((int) $this->riHdrNo) ?: '3';
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
        $this->claimData['nama_dokter'] = $this->resolveDpjpUtamaName($dataRI);

        // hak_kelas dari SEP peserta.hakKelas.kode (fallback ke kelas_rawat)
        $hakKelas = (string) data_get($dataRI, 'sep.resSep.peserta.hakKelas.kode', '');
        $this->claimData['hak_kelas'] = $hakKelas !== '' ? $hakKelas : ((string) $kelas ?: '3');

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
     =============================== */
    private function resolveDpjpUtamaName(array $dataRI): string
    {
        $list = data_get($dataRI, 'pengkajianAwalPasienRawatInap.levelingDokter', []);
        if (!is_array($list)) {
            return '';
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
            return '';
        }
        return (string) (DB::table('rsmst_doctors')->where('dr_id', $drId)->value('dr_name') ?? '');
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
            // RI: ambil dari JSON pengkajianAwalPasienRawatInap.levelingDokter[*] (levelDokter='Utama').
            // Re-sync setiap kali set — jaga-jaga DPJP Utama berganti di Pengkajian Awal RI.
            $namaDokter = trim($this->resolveDpjpUtamaName($data));
            if ($namaDokter === '') {
                $this->dispatch('toast', type: 'error', message: 'DPJP Utama kosong di Pengkajian Awal RI (levelingDokter). Set DPJP Utama dulu sebelum simpan data klaim.');
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

            // Kirim ke E-Klaim
            $res = $this->setClaimData($nomorSep, $this->claimData)->getOriginalContent();
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
                <x-input-label value="Hak Kelas BPJS" class="text-sm" />
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
                    <option value="2">2 — Pulang Paksa (APS)</option>
                    <option value="3">3 — Meninggal</option>
                    <option value="4">4 — Lainnya</option>
                    <option value="5">5 — Dirujuk</option>
                </x-select-input>
            </div>
            <div class="md:col-span-3 lg:col-span-5">
                <x-input-label value="DPJP Utama (Nama Dokter)" class="text-sm" />
                <x-text-input wire:model="claimData.nama_dokter" readonly
                    placeholder="Ambil dari DPJP Utama di Pengkajian Awal RI — isi dulu kalau kosong"
                    class="text-sm {{ empty($claimData['nama_dokter']) ? 'bg-rose-50 dark:bg-rose-900/20' : 'bg-gray-50 dark:bg-gray-800' }}" />
            </div>
        </div>
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
