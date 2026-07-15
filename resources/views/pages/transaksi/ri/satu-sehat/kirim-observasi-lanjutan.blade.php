<?php
// resources/views/pages/transaksi/ri/satu-sehat/kirim-observasi-lanjutan.blade.php
// Step 13 (RI): Kirim Observasi Lanjutan — pemberian obat/cairan, oksigen, pengeluaran cairan.
//
// Sumber = datadaftarri_json → observasi.{obatDanCairan, pemakaianOksigen, pengeluaranCairan}
//   obatDanCairan.pemberianObatDanCairan[] → MedicationAdministration (KFA via productId)
//   pemakaianOksigen.pemakaianOksigenData[] → Observation (alat + laju aliran)
//   pengeluaranCairan.pengeluaranCairan[]   → Observation (volume urine)
//
// Tanda vital (observasi.observasiLanjutan.tandaVital[]) TIDAK di sini — sudah dikirim kartu 5.
// Pemetaan kode ada di App\Support\ObservasiLanjutanMap.
//
// CATATAN DATA: hanya ~31% baris pemberian obat punya productId (cairan sering diketik
// bebas tanpa pilih master obat) → baris tanpa productId/KFA DILEWATI tapi DIHITUNG &
// dilaporkan ke user, jangan hilang diam-diam.

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\SATUSEHAT\ObservationTrait;
use App\Http\Traits\SATUSEHAT\MedicationAdministrationTrait;
use App\Support\ObservasiLanjutanMap;

new class extends Component {
    use EmrRITrait, ObservationTrait, MedicationAdministrationTrait;

    public ?string $riHdrNo = null;
    public bool $hasEncounter = false;
    public int $count = 0;        // total resource terkirim
    public int $obatCount = 0;    // baris pemberian obat siap kirim (ber-KFA)
    public int $obatSkipped = 0;  // baris dilewati (tanpa productId / tanpa KFA)
    public int $oksigenCount = 0;
    public int $keluarCount = 0;

    public function mount(?string $riHdrNo = null): void
    {
        $this->riHdrNo = $riHdrNo;
        $this->reloadState();
    }

    #[On('ri-satu-sehat.refresh')]
    public function onRefresh(string $riHdrNo): void
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
        $ss = $data['satusehat'] ?? [];
        $this->hasEncounter = !empty($ss['encounterId']);
        $this->count = count($ss['observasiLanjutanIds'] ?? []);

        $obat = $this->obatEntries($data);
        $siap = $this->enrichKfa($obat);
        $this->obatCount = count($siap);
        $this->obatSkipped = count($obat) - count($siap);
        $this->oksigenCount = count($this->oksigenEntries($data));
        $this->keluarCount = count($this->keluarEntries($data));
    }

    /** @return array<int, array> */
    private function obatEntries(array $data): array
    {
        return $data['observasi']['obatDanCairan']['pemberianObatDanCairan'] ?? [];
    }

    /** @return array<int, array> */
    private function oksigenEntries(array $data): array
    {
        return $data['observasi']['pemakaianOksigen']['pemakaianOksigenData'] ?? [];
    }

    /** @return array<int, array> */
    private function keluarEntries(array $data): array
    {
        return $data['observasi']['pengeluaranCairan']['pengeluaranCairan'] ?? [];
    }

    /**
     * Sisakan hanya baris yang punya productId DAN produknya ber-KFA, lalu lampirkan kode KFA.
     *
     * @return array<int, array>
     */
    private function enrichKfa(array $rows): array
    {
        $pids = [];
        foreach ($rows as $r) {
            $p = trim((string) ($r['productId'] ?? ''));
            if ($p !== '') {
                $pids[$p] = true;
            }
        }
        if ($pids === []) {
            return [];
        }

        $master = DB::table('immst_products')
            ->whereIn('product_id', array_keys($pids))
            ->whereRaw('product_id_satusehat IS NOT NULL AND LENGTH(TRIM(product_id_satusehat)) > 0')
            ->get(['product_id', 'product_id_satusehat', 'product_name_satusehat'])
            ->keyBy('product_id');

        $out = [];
        foreach ($rows as $r) {
            $p = trim((string) ($r['productId'] ?? ''));
            if ($p === '' || !$master->has($p)) {
                continue;
            }
            $m = $master->get($p);
            $r['_kfaCode'] = (string) $m->product_id_satusehat;
            $r['_kfaName'] = (string) ($m->product_name_satusehat ?: ($r['namaObatAtauJenisCairan'] ?? ''));
            $out[] = $r;
        }

        return $out;
    }

    public function kirimForCurrent(): void
    {
        if (empty($this->riHdrNo)) {
            return;
        }
        $this->kirim($this->riHdrNo);
        $this->reloadState();
    }

    #[On('ss-observasi-lanjutan-ri.kirim')]
    public function kirim(string $riHdrNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRI = $this->findDataRI($riHdrNo);
            if (empty($dataRI)) { $this->dispatch('toast', type: 'error', message: 'Data Rawat Inap tidak ditemukan.'); return; }

            $ss = $dataRI['satusehat'] ?? [];
            if (empty($ss['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            if (!empty($ss['observasiLanjutanIds'])) { $this->dispatch('toast', type: 'info', message: 'Observasi lanjutan sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataRI['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $orgId = (string) env('SATUSEHAT_ORGANIZATION_ID', '');
            $practitionerId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataRI['drId'] ?? '')->value('dr_uuid') ?? '');
            $patientName = (string) ($dataRI['regName'] ?? '');

            $obat = $this->enrichKfa($this->obatEntries($dataRI));
            $oksigen = $this->oksigenEntries($dataRI);
            $keluar = $this->keluarEntries($dataRI);

            if (empty($obat) && empty($oksigen) && empty($keluar)) {
                $this->dispatch('toast', type: 'error', message: 'Tidak ada data observasi lanjutan ber-KFA untuk dikirim.');
                return;
            }

            $ids = [];

            // 1) Pemberian obat & cairan → MedicationAdministration
            foreach ($obat as $i => $o) {
                $when = $this->parseDate((string) ($o['waktuPemberian'] ?? ''))->toIso8601String();
                $dose = ObservasiLanjutanMap::dosis((string) ($o['dosis'] ?? ''));
                $rute = ObservasiLanjutanMap::rute((string) ($o['rute'] ?? ''));

                $payload = [
                    'medContainedId'    => 'medadm-' . ($o['id'] ?? $i),
                    'orgId'             => $orgId,
                    'medicationCode'    => $o['_kfaCode'],
                    'medicationDisplay' => $o['_kfaName'],
                    'patientId'         => $patientId,
                    'patientName'       => $patientName,
                    'encounterId'       => $ss['encounterId'],
                    'effectiveDate'     => $when,
                    'performerId'       => $practitionerId,
                ];
                // mad-1: route hanya ikut bila dose ada (dosage wajib punya dose/rate).
                if ($dose !== null) {
                    $payload['dose'] = $dose;
                    $payload['dosageText'] = trim((string) ($o['dosis'] ?? '')) ?: null;
                    if ($rute !== null) {
                        $payload['routeCode'] = $rute['code'];
                        $payload['routeDisplay'] = $rute['display'];
                    }
                }
                $res = $this->createMedicationAdministration($payload);
                if (!empty($res['id'])) $ids[] = $res['id'];
            }

            // 2) Oksigen & 3) pengeluaran cairan → Observation
            foreach ([[$oksigen, 'oksigen', 'tanggalWaktuMulai'], [$keluar, 'pengeluaran', 'waktuPengeluaran']] as [$rows, $jenis, $waktuKey]) {
                foreach ($rows as $e) {
                    $when = $this->parseDate((string) ($e[$waktuKey] ?? ''))->toIso8601String();
                    $base = [
                        'patientId'     => $patientId,
                        'encounterId'   => $ss['encounterId'],
                        'performerId'   => $practitionerId,
                        'effectiveDate' => $when,
                    ];
                    $obsList = $jenis === 'oksigen'
                        ? ObservasiLanjutanMap::oksigen($e)
                        : ObservasiLanjutanMap::pengeluaran($e);
                    foreach ($obsList as $obs) {
                        $res = $this->createObservation(array_merge($base, $obs));
                        if (!empty($res['id'])) $ids[] = $res['id'];
                    }
                }
            }

            if (empty($ids)) { $this->dispatch('toast', type: 'error', message: 'Tidak ada nilai observasi lanjutan valid untuk dikirim.'); return; }

            $ss['observasiLanjutanIds'] = $ids;
            $this->saveResult($riHdrNo, $ss);

            $msg = 'Observasi lanjutan berhasil dikirim (' . count($ids) . ' resource).';
            if ($this->obatSkipped > 0) {
                $msg .= ' ' . $this->obatSkipped . ' baris obat dilewati (tanpa productId/KFA).';
            }
            $this->dispatch('toast', type: 'success', message: $msg);
            $this->dispatch('ri-satu-sehat.refresh', riHdrNo: $riHdrNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Observasi lanjutan gagal: ' . $e->getMessage());
        }
    }

    private function getPatientIHS(string $regNo): string
    {
        if (empty($regNo)) return '';
        return (string) (DB::table('rsmst_pasiens')->where('reg_no', $regNo)->value('patient_uuid') ?? '');
    }

    private function saveResult(string $riHdrNo, array $ss): void
    {
        DB::transaction(function () use ($riHdrNo, $ss) {
            $this->lockRIRow($riHdrNo);
            $data = $this->findDataRI($riHdrNo);
            $data['satusehat'] = $ss;
            $this->updateJsonRI((int) $riHdrNo, $data);
        });
    }

    private function parseDate(string $str): Carbon
    {
        if (empty($str)) return Carbon::now();
        try { return Carbon::createFromFormat('d/m/Y H:i:s', $str); } catch (\Throwable) {
            try { return Carbon::parse($str); } catch (\Throwable) { return Carbon::now(); }
        }
    }
};
?>

<div class="flex items-center justify-between p-4 bg-canvas border border-hairline shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700">
    <div class="flex items-center gap-3">
        <div
            class="flex items-center justify-center w-8 h-8 rounded-full {{ $count > 0 ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-surface-soft text-muted-soft dark:bg-gray-800 dark:text-gray-500' }}">
            <span class="text-sm font-bold">13</span>
        </div>
        <div>
            <div class="font-semibold text-ink dark:text-gray-100">Observasi Lanjutan</div>
            <div class="text-xs text-muted dark:text-gray-400">
                Pemberian obat &amp; cairan, oksigen, pengeluaran cairan.
                @if ($obatCount > 0 || $oksigenCount > 0 || $keluarCount > 0)
                    <span class="text-muted-soft">{{ $obatCount }} obat, {{ $oksigenCount }} oksigen, {{ $keluarCount }} pengeluaran.</span>
                @endif
                @if ($obatSkipped > 0)
                    <span class="text-amber-600 dark:text-amber-400">{{ $obatSkipped }} baris obat tanpa KFA dilewati.</span>
                @endif
            </div>
            @if ($count > 0)
                <div class="mt-1 font-mono text-xs text-success dark:text-success">
                    {{ $count }} terkirim
                </div>
            @endif
        </div>
    </div>
    <x-primary-button type="button" wire:click="kirimForCurrent" wire:loading.attr="disabled" :disabled="!$hasEncounter"
        class="!bg-teal-600 hover:!bg-teal-700 {{ $count > 0 ? '!bg-emerald-600' : '' }}">
        <span wire:loading.remove wire:target="kirimForCurrent">{{ $count > 0 ? 'Terkirim' : 'Kirim' }}</span>
        <span wire:loading wire:target="kirimForCurrent"><x-loading />...</span>
    </x-primary-button>
</div>
