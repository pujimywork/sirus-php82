<?php
// resources/views/pages/transaksi/rj/satu-sehat/kirim-allergy.blade.php
// Step 7: Kirim Alergi (AllergyIntolerance, SNOMED CT)
// Sumber: anamnesa.alergi.{alergi, snomedCode, snomedDisplayEn, snomedDisplayId}.
// createAllergyIntolerance WAJIB: patientId, encounterId, code(SNOMED), recorderId(Practitioner IHS).

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\SATUSEHAT\AllergyIntoleranceTrait;
use App\Support\AlergiSnomed;

new class extends Component {
    use EmrRJTrait, AllergyIntoleranceTrait;

    public ?string $rjNo = null;
    public bool $hasEncounter = false;
    public int $count = 0;

    public function mount(?string $rjNo = null): void
    {
        $this->rjNo = $rjNo;
        $this->reloadState();
    }

    #[On('rj-satu-sehat.refresh')]
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
        $data = $this->findDataRJ($this->rjNo);
        if (empty($data)) {
            return;
        }
        $ss = $data['satusehat'] ?? [];
        $this->hasEncounter = !empty($ss['encounterId']);
        $this->count = !empty($ss['allergyId']) ? 1 : 0;
    }

    public function kirimForCurrent(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $this->kirim($this->rjNo);
        $this->reloadState();
    }

    #[On('ss-allergy-rj.kirim')]
    public function kirim(string $rjNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRJ = $this->findDataRJ($rjNo);
            if (empty($dataRJ)) { $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.'); return; }

            $ss = $dataRJ['satusehat'] ?? [];
            if (empty($ss['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            if (!empty($ss['allergyId'])) { $this->dispatch('toast', type: 'info', message: 'Alergi sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataRJ['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $recorderId = $this->getDoctorIHS($dataRJ['drId'] ?? '');
            if (empty($recorderId)) { $this->dispatch('toast', type: 'error', message: 'IHS dokter (dr_uuid) kosong — lengkapi di master dokter.'); return; }

            $al = $dataRJ['anamnesa']['alergi'] ?? [];
            $alergiText = trim((string) ($al['alergi'] ?? ''));
            $snomedCode = trim((string) ($al['snomedCode'] ?? ''));

            $tidakAdaAlergi = AlergiSnomed::adalahTidakAdaAlergi($snomedCode);
            // Teks boleh kosong SELAMA kodenya pernyataan "tidak ada alergi" — di situ
            // kode-nya yang bermakna, bukan teksnya.
            if ($alergiText === '' && !$tidakAdaAlergi) { $this->dispatch('toast', type: 'error', message: 'Data alergi belum diisi di anamnesa.'); return; }
            if ($snomedCode === '') { $this->dispatch('toast', type: 'error', message: 'Kode SNOMED Alergi belum diisi di anamnesa (wajib utk Satu Sehat).'); return; }

            $res = $this->createAllergyIntolerance([
                'patientId'   => $patientId,
                'encounterId' => $ss['encounterId'],
                'recorderId'  => $recorderId,
                'code'        => $snomedCode,
                'display'     => $al['snomedDisplayEn'] ?? ($al['snomedDisplayId'] ?? $alergiText),
                // "Tidak ada alergi" (mis. 716186003) = pernyataan TIADA alergi -> type/category/
                // criticality DIHILANGKAN (null). Mengirim category='medication' bersamanya
                // kontradiktif: "tidak ada alergi obat" punya kode sendiri (409137002).
                'type'        => $tidakAdaAlergi ? null : 'allergy',
                'category'    => $tidakAdaAlergi ? null : 'medication',
                'criticality' => $tidakAdaAlergi ? null : 'low',
                'note'        => $alergiText,
                'onset'       => $this->parseDate($dataRJ['rjDate'] ?? '')->toIso8601String(),
            ]);

            if (empty($res['id'])) { $this->dispatch('toast', type: 'error', message: 'Alergi gagal: respons tanpa id.'); return; }

            $ss['allergyId'] = $res['id'];
            $this->saveResult($rjNo, $ss);
            $this->dispatch('toast', type: 'success', message: 'Alergi berhasil dikirim.');
            $this->dispatch('rj-satu-sehat.refresh', rjNo: $rjNo);
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

    private function saveResult(string $rjNo, array $ss): void
    {
        DB::transaction(function () use ($rjNo, $ss) {
            $this->lockRJRow($rjNo);
            $data = $this->findDataRJ($rjNo);
            $data['satusehat'] = $ss;
            $this->updateJsonRJ($rjNo, $data);
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
            <span class="text-sm font-bold">7</span>
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
