<?php
// resources/views/pages/transaksi/ri/satu-sehat/kirim-allergy.blade.php
// Kirim Alergi (AllergyIntolerance, SNOMED CT) — RI.
// Sumber: pengkajianDokter.anamnesa.{jenisAlergi, jenisAlergiSnomedCode, ...DisplayEn/Id}.

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\SATUSEHAT\AllergyIntoleranceTrait;

new class extends Component {
    use EmrRITrait, AllergyIntoleranceTrait;

    public ?string $riHdrNo = null;
    public bool $hasEncounter = false;
    public int $count = 0;

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
        $this->count = !empty($ss['allergyId']) ? 1 : 0;
    }

    public function kirimForCurrent(): void
    {
        if (empty($this->riHdrNo)) {
            return;
        }
        $this->kirim($this->riHdrNo);
        $this->reloadState();
    }

    #[On('ss-allergy-ri.kirim')]
    public function kirim(string $riHdrNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRI = $this->findDataRI($riHdrNo);
            if (empty($dataRI)) { $this->dispatch('toast', type: 'error', message: 'Data Rawat Inap tidak ditemukan.'); return; }

            $ss = $dataRI['satusehat'] ?? [];
            if (empty($ss['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            if (!empty($ss['allergyId'])) { $this->dispatch('toast', type: 'info', message: 'Alergi sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataRI['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $recorderId = $this->getDoctorIHS($dataRI['drId'] ?? '');
            if (empty($recorderId)) { $this->dispatch('toast', type: 'error', message: 'IHS dokter (dr_uuid) kosong — lengkapi di master dokter.'); return; }

            $an = $dataRI['pengkajianDokter']['anamnesa'] ?? [];
            $alergiText = trim((string) ($an['jenisAlergi'] ?? ''));
            $snomedCode = trim((string) ($an['jenisAlergiSnomedCode'] ?? ''));

            if ($alergiText === '') { $this->dispatch('toast', type: 'error', message: 'Data alergi belum diisi di Pengkajian Dokter.'); return; }
            if ($snomedCode === '') { $this->dispatch('toast', type: 'error', message: 'Kode SNOMED Alergi belum diisi di Pengkajian Dokter (wajib utk Satu Sehat).'); return; }

            $res = $this->createAllergyIntolerance([
                'patientId'   => $patientId,
                'encounterId' => $ss['encounterId'],
                'recorderId'  => $recorderId,
                'code'        => $snomedCode,
                'display'     => $an['jenisAlergiSnomedDisplayEn'] ?? ($an['jenisAlergiSnomedDisplayId'] ?? $alergiText),
                'category'    => 'medication',
                'criticality' => 'low',
                'note'        => $alergiText,
                'onset'       => $this->parseDate($dataRI['entryDate'] ?? '')->toIso8601String(),
            ]);

            if (empty($res['id'])) { $this->dispatch('toast', type: 'error', message: 'Alergi gagal: respons tanpa id.'); return; }

            $ss['allergyId'] = $res['id'];
            $this->saveResult($riHdrNo, $ss);
            $this->dispatch('toast', type: 'success', message: 'Alergi berhasil dikirim.');
            $this->dispatch('ri-satu-sehat.refresh', riHdrNo: $riHdrNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Alergi gagal: ' . $e->getMessage());
        }
    }

    private function getPatientIHS(string $regNo): string
    {
        if (empty($regNo)) return '';
        return (string) (DB::table('rsmst_pasiens')->where('reg_no', $regNo)->value('patient_uuid') ?? '');
    }

    private function getDoctorIHS(string $drId): string
    {
        if (empty($drId)) return '';
        return (string) (DB::table('rsmst_doctors')->where('dr_id', $drId)->value('dr_uuid') ?? '');
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
            <span class="text-sm font-bold">14</span>
        </div>
        <div>
            <div class="font-semibold text-ink dark:text-gray-100">Allergy Intolerance</div>
            <div class="text-xs text-muted dark:text-gray-400">Riwayat alergi pasien (SNOMED CT).</div>
            @if ($count > 0)
                <div class="mt-1 font-mono text-xs text-success dark:text-success">
                    terkirim
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
