<?php
// resources/views/pages/transaksi/ri/satu-sehat/kirim-cppt.blade.php
// Kirim CPPT (SOAP) → ClinicalImpression (asesmen "A") — RI.
//
// Sumber = datadaftarri_json → cppt[] (multi-entri, tiap entri punya cpptId UUID stabil).
// 1 entri CPPT = 1 ClinicalImpression. summary = ringkasan SOAP; effective = tglCPPT.
//
// assessorId (Practitioner IHS) WAJIB untuk ClinicalImpression. CPPT ditulis berbagai PPA
// (dokter/perawat/gizi/farmasi) yang sebagian belum punya IHS → MVP pakai DPJP (dr_uuid)
// sebagai assessor untuk semua entri.
//
// Guard incremental: ss['cpptClinicalImpressionIds'] = { cpptId: clinicalImpressionId }.
// Entri yang sudah terkirim dilewati, jadi CPPT baru bisa dikirim menyusul.

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\SATUSEHAT\ClinicalImpressionTrait;

new class extends Component {
    use EmrRITrait, ClinicalImpressionTrait;

    public ?string $riHdrNo = null;
    public bool $hasEncounter = false;
    public int $count = 0;       // jumlah CPPT sudah terkirim (ClinicalImpression)
    public int $entryCount = 0;  // jumlah entri CPPT tersedia

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
        $this->count = count($ss['cpptClinicalImpressionIds'] ?? []);
        $this->entryCount = count($data['cppt'] ?? []);
    }

    /** Ringkas SOAP jadi satu teks summary. */
    private function soapSummary(array $soap): string
    {
        $parts = [];
        foreach (['subjective' => 'S', 'objective' => 'O', 'assessment' => 'A', 'plan' => 'P'] as $key => $label) {
            $val = trim((string) ($soap[$key] ?? ''));
            if ($val !== '') {
                $parts[] = "{$label}: {$val}";
            }
        }
        return implode("\n", $parts);
    }

    public function kirimForCurrent(): void
    {
        if (empty($this->riHdrNo)) {
            return;
        }
        $this->kirim($this->riHdrNo);
        $this->reloadState();
    }

    #[On('ss-cppt-ri.kirim')]
    public function kirim(string $riHdrNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRI = $this->findDataRI($riHdrNo);
            if (empty($dataRI)) { $this->dispatch('toast', type: 'error', message: 'Data Rawat Inap tidak ditemukan.'); return; }

            $ss = $dataRI['satusehat'] ?? [];
            if (empty($ss['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }

            $patientId = $this->getPatientIHS($dataRI['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            // Assessor = DPJP (fallback MVP untuk semua entri CPPT).
            $assessorId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataRI['drId'] ?? '')->value('dr_uuid') ?? '');
            if (empty($assessorId)) { $this->dispatch('toast', type: 'error', message: 'IHS DPJP (dr_uuid) kosong — lengkapi di master dokter.'); return; }

            $entries = $dataRI['cppt'] ?? [];
            if (empty($entries)) { $this->dispatch('toast', type: 'error', message: 'Belum ada CPPT untuk dikirim.'); return; }

            $sentMap = $ss['cpptClinicalImpressionIds'] ?? [];
            $newSent = 0;

            foreach ($entries as $entry) {
                $cpptId = (string) ($entry['cpptId'] ?? '');
                if ($cpptId === '' || isset($sentMap[$cpptId])) {
                    continue; // tanpa id stabil / sudah terkirim → skip
                }

                $summary = $this->soapSummary($entry['soap'] ?? []);
                if ($summary === '') {
                    continue; // tak ada isi SOAP
                }

                $effective = $this->parseDate((string) ($entry['tglCPPT'] ?? ($dataRI['entryDate'] ?? '')));
                $profesi = trim((string) ($entry['profession'] ?? ''));

                $res = $this->createClinicalImpression([
                    'patientId'   => $patientId,
                    'encounterId' => $ss['encounterId'],
                    'assessorId'  => $assessorId,
                    'summary'     => $summary,
                    'description' => trim('CPPT ' . $profesi . ' — ' . (string) ($entry['tglCPPT'] ?? '')),
                    'effective'   => $effective->toIso8601String(),
                ]);

                if (!empty($res['id'])) {
                    $sentMap[$cpptId] = $res['id'];
                    $newSent++;
                }
            }

            if ($newSent === 0) {
                $this->dispatch('toast', type: 'info', message: 'Tidak ada CPPT baru untuk dikirim (semua sudah terkirim).');
                return;
            }

            $ss['cpptClinicalImpressionIds'] = $sentMap;
            $this->saveResult($riHdrNo, $ss);
            $this->dispatch('toast', type: 'success', message: "CPPT berhasil dikirim ({$newSent} entri baru).");
            $this->dispatch('ri-satu-sehat.refresh', riHdrNo: $riHdrNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'CPPT gagal: ' . $e->getMessage());
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
            <span class="text-sm font-bold">8</span>
        </div>
        <div>
            <div class="font-semibold text-ink dark:text-gray-100">ClinicalImpression <span class="text-xs font-normal text-muted">(CPPT)</span></div>
            <div class="text-xs text-muted dark:text-gray-400">
                Catatan perkembangan SOAP. Assessor = DPJP.
                @if ($entryCount > 0)
                    <span class="text-muted-soft">{{ $count }}/{{ $entryCount }} entri terkirim.</span>
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
        class="!bg-teal-600 hover:!bg-teal-700 {{ $count > 0 && $count >= $entryCount ? '!bg-emerald-600' : '' }}">
        <span wire:loading.remove wire:target="kirimForCurrent">{{ $count > 0 && $count >= $entryCount ? 'Terkirim' : 'Kirim' }}</span>
        <span wire:loading wire:target="kirimForCurrent"><x-loading />...</span>
    </x-primary-button>
</div>
