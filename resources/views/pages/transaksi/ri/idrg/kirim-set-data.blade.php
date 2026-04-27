<?php
// resources/views/pages/transaksi/ri/idrg/kirim-set-data.blade.php
// Step 3: Set Data Klaim — form editable tarif_rs + tanggal sebelum POST ke E-Klaim.
// Coder Casemix bisa adjust tarif kalau breakdown auto dari kasir tidak sesuai.

use Livewire\Component;
use Livewire\Attributes\On;
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
        'discharge_status' => '1',
        'nomor_kartu_t' => 'kartu_jkn',
        // Mandatory sejak v5.4.11 (Manual hal. 16). Default JKN: payor_id=3, payor_cd=JKN.
        // Adjust kalau RS pakai Payplan ID lain di setup Jaminan E-Klaim.
        'payor_id' => '3',
        'payor_cd' => 'JKN',
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

    #[On('idrg-state-updated-ri')]
    public function onStateUpdated(string $riHdrNo): void
    {
        if ((string) $this->riHdrNo !== $riHdrNo) {
            return;
        }
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

        $nomorKartu = data_get($pasien, 'identitas.idbpjs')
            ?: data_get($dataRI, 'sep.resSep.peserta.noKartu')
            ?: data_get($dataRI, 'sep.reqSep.t_sep.noKartu')
            ?: '';

        // Fallback: idrg.nomorSep (set saat buat klaim) → idrg.claimNumber → SEP BPJS
        $this->claimData['nomor_sep'] = $idrg['nomorSep']
            ?? $idrg['claimNumber']
            ?? data_get($dataRI, 'sep.noSep', '');
        $this->claimData['nomor_kartu'] = $nomorKartu;
        $this->claimData['tgl_masuk'] = $dates['tglMasuk'];
        $this->claimData['tgl_pulang'] = $dates['tglPulang'];
        $this->claimData['cara_masuk'] = 'gp';
        $this->claimData['jenis_rawat'] = '1';
        $this->claimData['kelas_rawat'] = (string) $kelas;
        $this->claimData['discharge_status'] = $discharge;
        $this->claimData['nomor_kartu_t'] = 'kartu_jkn';
        $this->claimData['payor_id'] = '3';
        $this->claimData['payor_cd'] = 'JKN';

        $obatTotal = max(0, ($cost['obatPinjam'] ?? 0) + ($cost['bonResep'] ?? 0) - ($cost['rtnObat'] ?? 0));

        $this->claimData['tarif_rs']['prosedur_non_bedah'] = (string) ($cost['jasaMedis'] ?? 0);
        $this->claimData['tarif_rs']['prosedur_bedah']     = (string) ($cost['ok'] ?? 0);
        $this->claimData['tarif_rs']['konsultasi']         = (string) (($cost['konsul'] ?? 0) + ($cost['adminAge'] ?? 0) + ($cost['adminStatus'] ?? 0) + ($cost['trfUgdRj'] ?? 0));
        $this->claimData['tarif_rs']['tenaga_ahli']        = (string) ($cost['jasaDokter'] ?? 0);
        $this->claimData['tarif_rs']['keperawatan']        = '0';
        $this->claimData['tarif_rs']['penunjang']          = (string) ($cost['lainLain'] ?? 0);
        $this->claimData['tarif_rs']['radiologi']          = (string) ($cost['rad'] ?? 0);
        $this->claimData['tarif_rs']['laboratorium']       = (string) ($cost['lab'] ?? 0);
        $this->claimData['tarif_rs']['pelayanan_darah']    = '0';
        $this->claimData['tarif_rs']['rehabilitasi']       = '0';
        $this->claimData['tarif_rs']['kamar']              = (string) (($cost['room'] ?? 0) + ($cost['commonService'] ?? 0) + ($cost['perawatan'] ?? 0));
        $this->claimData['tarif_rs']['rawat_intensif']     = '0';
        $this->claimData['tarif_rs']['obat']               = (string) $obatTotal;
        $this->claimData['tarif_rs']['obat_kronis']        = '0';
        $this->claimData['tarif_rs']['obat_kemoterapi']    = '0';
        $this->claimData['tarif_rs']['alkes']              = '0';
        $this->claimData['tarif_rs']['bmhp']               = '0';
        $this->claimData['tarif_rs']['sewa_alat']          = '0';
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

            // coder_nik mandatory di set_claim_data (Manual 5.10.x hal. 14).
            // Ambil dari emp_id user login (pola sama dengan kirim-final-klaim).
            $coderNik = (string) (auth()->user()->emp_id ?? '');
            if (empty($coderNik)) {
                $this->dispatch('toast', type: 'error', message: 'User aktif tidak punya emp_id. Hubungi admin untuk set Karyawan di profil user.');
                return;
            }
            $this->claimData['coder_nik'] = $coderNik;

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

        $this->dispatch('idrg-state-updated-ri', riHdrNo: (string) $riHdrNo);
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
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    Tarif & tanggal auto dari kasir RJ. Coder boleh adjust sebelum kirim.
                </div>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" wire:click="syncFromKasir" wire:loading.attr="disabled" @disabled($idrgFinal)
                class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 disabled:opacity-50 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                <span wire:loading.remove wire:target="syncFromKasir">↻ Sync dari Kasir</span>
                <span wire:loading wire:target="syncFromKasir"><x-loading />...</span>
            </button>
            <x-primary-button type="button" wire:click="setForCurrent" wire:loading.attr="disabled"
                :disabled="$idrgFinal || !$hasClaim"
                class="!bg-brand hover:!bg-brand/90 {{ !empty($claimDataSavedAt) ? '!bg-emerald-600' : '' }}">
                <span wire:loading.remove wire:target="setForCurrent">
                    {{ !empty($claimDataSavedAt) ? 'Simpan Ulang' : 'Simpan Data Klaim' }}
                </span>
                <span wire:loading wire:target="setForCurrent"><x-loading />...</span>
            </x-primary-button>
        </div>
    </div>

    {{-- Identitas + Klasifikasi --}}
    <fieldset class="p-3 border border-gray-200 rounded-lg dark:border-gray-700" @disabled($idrgFinal)>
        <legend class="px-2 text-xs font-semibold tracking-wide text-gray-600 uppercase dark:text-gray-400">
            Identitas & Klasifikasi
        </legend>
        <div class="grid grid-cols-2 gap-3 md:grid-cols-3">
            <div>
                <x-input-label value="Nomor SEP" class="text-xs" />
                <x-text-input wire:model="claimData.nomor_sep" readonly class="font-mono text-xs bg-gray-50 dark:bg-gray-800" />
            </div>
            <div>
                <x-input-label value="Nomor Kartu BPJS" class="text-xs" />
                <x-text-input wire:model="claimData.nomor_kartu" readonly class="font-mono text-xs bg-gray-50 dark:bg-gray-800" />
            </div>
            <div>
                <x-input-label value="Jenis Kartu" class="text-xs" />
                <x-select-input wire:model="claimData.nomor_kartu_t" :disabled="$idrgFinal" class="text-xs">
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
                <x-input-label value="Tgl Masuk" class="text-xs" />
                <x-text-input wire:model="claimData.tgl_masuk" placeholder="yyyy-mm-dd HH:MM:SS"
                    :disabled="$idrgFinal" class="font-mono text-xs" />
            </div>
            <div>
                <x-input-label value="Tgl Pulang" class="text-xs" />
                <x-text-input wire:model="claimData.tgl_pulang" placeholder="yyyy-mm-dd HH:MM:SS"
                    :disabled="$idrgFinal" class="font-mono text-xs" />
            </div>
            <div>
                <x-input-label value="Cara Masuk" class="text-xs" />
                <x-select-input wire:model="claimData.cara_masuk" :disabled="$idrgFinal" class="text-xs">
                    <option value="gp">GP (referral umum)</option>
                    <option value="sp">Spesialis</option>
                    <option value="fl">Datang Sendiri</option>
                </x-select-input>
            </div>
            <div>
                <x-input-label value="Jenis Rawat" class="text-xs" />
                <x-select-input wire:model="claimData.jenis_rawat" :disabled="$idrgFinal" class="text-xs">
                    <option value="1">1 — Rawat Inap</option>
                    <option value="2">2 — Rawat Jalan</option>
                    <option value="3">3 — IGD</option>
                </x-select-input>
            </div>
            <div>
                <x-input-label value="Kelas Rawat" class="text-xs" />
                <x-select-input wire:model="claimData.kelas_rawat" :disabled="$idrgFinal" class="text-xs">
                    <option value="1">Kelas 1</option>
                    <option value="2">Kelas 2</option>
                    <option value="3">Kelas 3</option>
                </x-select-input>
            </div>
            <div>
                <x-input-label value="Discharge Status" class="text-xs" />
                <x-select-input wire:model="claimData.discharge_status" :disabled="$idrgFinal" class="text-xs">
                    <option value="1">1 — Atas Persetujuan Dokter</option>
                    <option value="2">2 — Pulang Paksa (APS)</option>
                    <option value="3">3 — Meninggal</option>
                    <option value="4">4 — Lainnya</option>
                    <option value="5">5 — Dirujuk</option>
                </x-select-input>
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
        <legend class="px-2 text-xs font-semibold tracking-wide text-gray-600 uppercase dark:text-gray-400">
            Tarif RS (Rp)
        </legend>
        <div class="grid grid-cols-2 gap-2 md:grid-cols-3">
            @foreach ($tarifFields as $key => $label)
                <div>
                    <x-input-label :value="$label" class="text-xs" />
                    <x-text-input-number wire:model="claimData.tarif_rs.{{ $key }}"
                        :disabled="$idrgFinal" />
                </div>
            @endforeach
        </div>
        <div class="flex justify-end pt-2 mt-2 border-t border-gray-100 dark:border-gray-700">
            <div class="text-xs">
                <span class="text-gray-500">Total Tarif: </span>
                <span class="font-mono font-semibold text-gray-800 dark:text-gray-100">
                    Rp {{ number_format($totalTarif, 0, ',', '.') }}
                </span>
            </div>
        </div>
    </fieldset>

    @if (!empty($claimDataSavedAt))
        <div class="px-2 py-1.5 text-xs text-gray-600 bg-emerald-50 rounded dark:bg-emerald-900/20 dark:text-emerald-300">
            ✓ Tersimpan di E-Klaim — {{ $claimDataSavedAt }}
        </div>
    @endif
</div>
