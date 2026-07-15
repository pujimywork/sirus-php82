<?php
// resources/views/pages/transaksi/ugd/satu-sehat/kirim-penilaian.blade.php
// Step 12 (UGD): Kirim Penilaian (Observation) — Risiko Jatuh + Gizi.
//
// Sumber = datadaftarugd_json → penilaian.resikoJatuh[] & penilaian.gizi[] (MULTI-ENTRI).
// Node penilaian di UGD IDENTIK dengan RI (jarang: modul lain biasanya beda struktur),
// jadi pemetaan LOINC dipakai bareng lewat App\Support\PenilaianObservationMap.
// Beda dari RI: trait EmrUGDTrait, PK rj_no, event ss-penilaian-ugd.kirim,
// dan fallback waktu = rjDate (RI: entryDate).

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\SATUSEHAT\ObservationTrait;
use App\Support\PenilaianObservationMap;

new class extends Component {
    use EmrUGDTrait, ObservationTrait;

    public ?string $rjNo = null;
    public bool $hasEncounter = false;
    public int $count = 0;       // jumlah Observation terkirim
    public int $jatuhCount = 0;  // entri risiko jatuh tersedia
    public int $giziCount = 0;   // entri gizi tersedia

    public function mount(?string $rjNo = null): void
    {
        $this->rjNo = $rjNo;
        $this->reloadState();
    }

    #[On('ugd-satu-sehat.refresh')]
    public function onRefresh(string $rjNo): void
    {
        if ((string) $this->rjNo !== $rjNo) {
            return;
        }
        $this->reloadState();
    }

    private function reloadState(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $data = $this->findDataUGD($this->rjNo);
        if (empty($data)) {
            return;
        }
        $ss = $data['satusehat'] ?? [];
        $this->hasEncounter = !empty($ss['encounterId']);
        $this->count = count($ss['penilaianObservationIds'] ?? []);
        $this->jatuhCount = count($this->resikoJatuhEntries($data));
        $this->giziCount = count($this->giziEntries($data));
    }

    /** @return array<int, array> */
    private function resikoJatuhEntries(array $data): array
    {
        return $data['penilaian']['resikoJatuh'] ?? [];
    }

    /** @return array<int, array> */
    private function giziEntries(array $data): array
    {
        return $data['penilaian']['gizi'] ?? [];
    }

    public function kirimForCurrent(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $this->kirim($this->rjNo);
        $this->reloadState();
    }

    #[On('ss-penilaian-ugd.kirim')]
    public function kirim(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataUGD = $this->findDataUGD($rjNo);
            if (empty($dataUGD)) { $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.'); return; }

            $ss = $dataUGD['satusehat'] ?? [];
            if (empty($ss['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            if (!empty($ss['penilaianObservationIds'])) { $this->dispatch('toast', type: 'info', message: 'Penilaian sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataUGD['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $practitionerId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataUGD['drId'] ?? '')->value('dr_uuid') ?? '');

            $jatuhEntries = $this->resikoJatuhEntries($dataUGD);
            $giziEntries = $this->giziEntries($dataUGD);
            if (empty($jatuhEntries) && empty($giziEntries)) {
                $this->dispatch('toast', type: 'error', message: 'Tidak ada data Penilaian (risiko jatuh / gizi).');
                return;
            }

            $ids = [];

            foreach ($jatuhEntries as $e) {
                $base = $this->baseFor($e, $dataUGD, $patientId, $ss['encounterId'], $practitionerId);
                foreach (PenilaianObservationMap::resikoJatuh($e) as $obs) {
                    $res = $this->createObservation(array_merge($base, $obs));
                    if (!empty($res['id'])) $ids[] = $res['id'];
                }
            }

            foreach ($giziEntries as $e) {
                $base = $this->baseFor($e, $dataUGD, $patientId, $ss['encounterId'], $practitionerId);
                foreach (PenilaianObservationMap::gizi($e) as $obs) {
                    $res = $this->createObservation(array_merge($base, $obs));
                    if (!empty($res['id'])) $ids[] = $res['id'];
                }
            }

            if (empty($ids)) { $this->dispatch('toast', type: 'error', message: 'Tidak ada nilai penilaian valid untuk dikirim.'); return; }

            $ss['penilaianObservationIds'] = $ids;
            $this->saveResult($rjNo, $ss);
            $this->dispatch('toast', type: 'success', message: 'Penilaian berhasil dikirim (' . count($ids) . ' observation).');
            $this->dispatch('ugd-satu-sehat.refresh', rjNo: $rjNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Penilaian gagal: ' . $e->getMessage());
        }
    }

    /** Payload dasar (subject/encounter/performer/waktu) untuk satu entri penilaian. */
    private function baseFor(array $entry, array $dataUGD, string $patientId, string $encounterId, string $practitionerId): array
    {
        $when = trim((string) ($entry['tglPenilaian'] ?? ''));
        $isoDate = ($when !== '' ? $this->parseDate($when) : $this->parseDate($dataUGD['rjDate'] ?? ''))->toIso8601String();

        return [
            'patientId'     => $patientId,
            'encounterId'   => $encounterId,
            'performerId'   => $practitionerId,
            'effectiveDate' => $isoDate,
        ];
    }

    private function getPatientIHS(string $regNo): string
    {
        if (empty($regNo)) return '';
        return (string) (DB::table('rsmst_pasiens')->where('reg_no', $regNo)->value('patient_uuid') ?? '');
    }

    private function saveResult(string $rjNo, array $ss): void
    {
        DB::transaction(function () use ($rjNo, $ss) {
            $this->lockUGDRow($rjNo);
            $data = $this->findDataUGD($rjNo);
            $data['satusehat'] = $ss;
            $this->updateJsonUGD((int) $rjNo, $data);
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
            <span class="text-sm font-bold">12</span>
        </div>
        <div>
            <div class="font-semibold text-ink dark:text-gray-100">Penilaian</div>
            <div class="text-xs text-muted dark:text-gray-400">
                Risiko jatuh (skor &amp; kategori) dan gizi (BB, TB, IMT).
                @if ($jatuhCount > 0 || $giziCount > 0)
                    <span class="text-muted-soft">{{ $jatuhCount }} risiko jatuh, {{ $giziCount }} gizi.</span>
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
