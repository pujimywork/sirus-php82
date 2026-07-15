<?php
// resources/views/pages/transaksi/ri/satu-sehat/kirim-chief-complaint.blade.php
// Kirim Keluhan Utama (Condition / problem-list-item, SNOMED CT) — RI.
// Sumber: pengkajianDokter.anamnesa.{keluhanUtama, keluhanUtamaSnomedCode, ...DisplayEn/Id}.

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\SATUSEHAT\ConditionTrait;

new class extends Component {
    use EmrRITrait, ConditionTrait;

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
        $this->count = !empty($ss['chiefComplaintId']) ? 1 : 0;
    }

    public function kirimForCurrent(): void
    {
        if (empty($this->riHdrNo)) {
            return;
        }
        $this->kirim($this->riHdrNo);
        $this->reloadState();
    }

    #[On('ss-chief-complaint-ri.kirim')]
    public function kirim(string $riHdrNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRI = $this->findDataRI($riHdrNo);
            if (empty($dataRI)) { $this->dispatch('toast', type: 'error', message: 'Data Rawat Inap tidak ditemukan.'); return; }

            $ss = $dataRI['satusehat'] ?? [];
            if (empty($ss['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            if (!empty($ss['chiefComplaintId'])) { $this->dispatch('toast', type: 'info', message: 'Keluhan utama sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataRI['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $an = $dataRI['pengkajianDokter']['anamnesa'] ?? [];
            $keluhanText = trim((string) ($an['keluhanUtama'] ?? ''));
            $snomedCode  = trim((string) ($an['keluhanUtamaSnomedCode'] ?? ''));

            if ($keluhanText === '') { $this->dispatch('toast', type: 'error', message: 'Keluhan utama belum diisi di Pengkajian Dokter.'); return; }
            if ($snomedCode === '') { $this->dispatch('toast', type: 'error', message: 'Kode SNOMED Keluhan Utama belum diisi di Pengkajian Dokter (wajib utk Satu Sehat).'); return; }

            $recordedDate = $this->parseDate($dataRI['entryDate'] ?? '');

            $res = $this->createChiefComplaint([
                'patientId'      => $patientId,
                'encounterId'    => $ss['encounterId'],
                'snomed_code'    => $snomedCode,
                'snomed_display' => $an['keluhanUtamaSnomedDisplayEn'] ?? '',
                'complaint_text' => $an['keluhanUtamaSnomedDisplayId'] ?? $keluhanText,
                'recordedDate'   => $recordedDate->toIso8601String(),
            ]);

            if (empty($res['id'])) { $this->dispatch('toast', type: 'error', message: 'Keluhan utama gagal: respons tanpa id.'); return; }

            $ss['chiefComplaintId'] = $res['id'];
            $this->saveResult($riHdrNo, $ss);
            $this->dispatch('toast', type: 'success', message: 'Keluhan utama berhasil dikirim.');
            $this->dispatch('ri-satu-sehat.refresh', riHdrNo: $riHdrNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Keluhan utama gagal: ' . $e->getMessage());
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
            <div class="font-semibold text-ink dark:text-gray-100">Chief Complaint</div>
            <div class="text-xs text-muted dark:text-gray-400">Keluhan utama pasien (SNOMED CT).</div>
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
