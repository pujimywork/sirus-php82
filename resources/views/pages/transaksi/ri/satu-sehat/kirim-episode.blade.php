<?php
// resources/views/pages/transaksi/ri/satu-sehat/kirim-episode.blade.php
// Kirim EpisodeOfCare — RI (1 rawat inap = 1 episode).
//
// episodeNo = rihdr_no, careManager = DPJP, start = tgl masuk, end = tgl pulang.
// Setelah dibuat, Encounter RI di-link ke episode (Encounter.episodeOfCare[]).
// Finish: saat pasien pulang → PUT status 'finished' + period.end = exitDate.

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\SATUSEHAT\EpisodeOfCareTrait;
use App\Http\Traits\SATUSEHAT\EncounterTrait;

new class extends Component {
    use EmrRITrait, EpisodeOfCareTrait, EncounterTrait;

    public ?string $riHdrNo = null;
    public bool $hasEncounter = false;
    public ?string $episodeId = null;
    public bool $episodeFinished = false;

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
        $this->episodeId = $ss['episodeOfCareId'] ?? null;
        $this->episodeFinished = !empty($ss['episodeOfCareFinished']);
    }

    public function kirimForCurrent(): void
    {
        if (empty($this->riHdrNo)) {
            return;
        }
        $this->kirim($this->riHdrNo);
        $this->reloadState();
    }

    public function finishForCurrent(): void
    {
        if (empty($this->riHdrNo)) {
            return;
        }
        $this->finish($this->riHdrNo);
        $this->reloadState();
    }

    #[On('ss-episode-ri.kirim')]
    public function kirim(string $riHdrNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRI = $this->findDataRI($riHdrNo);
            if (empty($dataRI)) { $this->dispatch('toast', type: 'error', message: 'Data Rawat Inap tidak ditemukan.'); return; }

            $ss = $dataRI['satusehat'] ?? [];
            if (empty($ss['encounterId'])) { $this->dispatch('toast', type: 'error', message: 'Kirim Encounter terlebih dahulu.'); return; }
            if (!empty($ss['episodeOfCareId'])) { $this->dispatch('toast', type: 'info', message: 'EpisodeOfCare sudah pernah dikirim.'); return; }

            $patientId = $this->getPatientIHS($dataRI['regNo'] ?? '');
            if (empty($patientId)) { $this->dispatch('toast', type: 'error', message: 'Patient IHS Number kosong.'); return; }

            $careManagerId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataRI['drId'] ?? '')->value('dr_uuid') ?? '');
            if (empty($careManagerId)) { $this->dispatch('toast', type: 'error', message: 'IHS DPJP (dr_uuid) kosong — lengkapi di master dokter.'); return; }

            $start = $this->parseDate($dataRI['entryDate'] ?? '')->toIso8601String();

            $res = $this->createEpisodeOfCare([
                'episodeNo'     => (string) $riHdrNo,
                'patientId'     => $patientId,
                'careManagerId' => $careManagerId,
                'start'         => $start,
                'status'        => 'active',
            ]);
            $episodeId = $res['id'] ?? null;
            if (empty($episodeId)) { $this->dispatch('toast', type: 'error', message: 'EpisodeOfCare gagal: respons tanpa id.'); return; }

            $ss['episodeOfCareId'] = $episodeId;

            // Link Encounter → EpisodeOfCare (Encounter.episodeOfCare[]).
            try {
                $enc = $this->getEncounter($ss['encounterId']);
                $ref = 'EpisodeOfCare/' . $episodeId;
                $existingRefs = collect($enc['episodeOfCare'] ?? [])->pluck('reference')->all();
                if (!in_array($ref, $existingRefs, true)) {
                    $enc['episodeOfCare'][] = ['reference' => $ref];
                    $this->makeRequest('put', "Encounter/{$ss['encounterId']}", $enc);
                }
            } catch (\Throwable $e) {
                // link opsional — episode tetap tersimpan meski link gagal
            }

            $this->saveResult($riHdrNo, $ss);
            $this->dispatch('toast', type: 'success', message: 'EpisodeOfCare berhasil dikirim: ' . $episodeId);
            $this->dispatch('ri-satu-sehat.refresh', riHdrNo: $riHdrNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'EpisodeOfCare gagal: ' . $e->getMessage());
        }
    }

    #[On('ss-episode-ri.finish')]
    public function finish(string $riHdrNo): void
    {
        try {
            $this->initializeSatuSehat();
            $dataRI = $this->findDataRI($riHdrNo);
            if (empty($dataRI)) { $this->dispatch('toast', type: 'error', message: 'Data Rawat Inap tidak ditemukan.'); return; }

            $ss = $dataRI['satusehat'] ?? [];
            if (empty($ss['episodeOfCareId'])) { $this->dispatch('toast', type: 'error', message: 'EpisodeOfCare belum dibuat.'); return; }
            if (!empty($ss['episodeOfCareFinished'])) { $this->dispatch('toast', type: 'info', message: 'EpisodeOfCare sudah finished.'); return; }

            $patientId = $this->getPatientIHS($dataRI['regNo'] ?? '');
            $careManagerId = (string) (DB::table('rsmst_doctors')->where('dr_id', $dataRI['drId'] ?? '')->value('dr_uuid') ?? '');

            $exitStr = trim((string) ($dataRI['exitDate'] ?? ''));
            $end = ($exitStr !== '' ? $this->parseDate($exitStr) : Carbon::now())->toIso8601String();
            $start = $this->parseDate($dataRI['entryDate'] ?? '')->toIso8601String();

            $this->updateEpisodeOfCare($ss['episodeOfCareId'], [
                'episodeNo'     => (string) $riHdrNo,
                'patientId'     => $patientId,
                'careManagerId' => $careManagerId,
                'start'         => $start,
                'end'           => $end,
                'status'        => 'finished',
            ]);
            $ss['episodeOfCareFinished'] = true;

            $this->saveResult($riHdrNo, $ss);
            $this->dispatch('toast', type: 'success', message: 'EpisodeOfCare finished.');
            $this->dispatch('ri-satu-sehat.refresh', riHdrNo: $riHdrNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Finish EpisodeOfCare gagal: ' . $e->getMessage());
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

<div class="space-y-3">
    <div class="flex items-center justify-between p-4 bg-canvas border border-hairline shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex items-center gap-3">
            <div
                class="flex items-center justify-center w-8 h-8 rounded-full {{ !empty($episodeId) ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-surface-soft text-muted-soft dark:bg-gray-800 dark:text-gray-500' }}">
                <span class="text-[10px] font-bold">Ep</span>
            </div>
            <div>
                <div class="font-semibold text-ink dark:text-gray-100">EpisodeOfCare</div>
                <div class="text-xs text-muted dark:text-gray-400">Episode rawat inap (1 RI = 1 episode). Link ke Encounter.</div>
                @if (!empty($episodeId))
                    <div class="mt-1 font-mono text-xs text-success dark:text-success">ID: {{ $episodeId }}</div>
                @endif
            </div>
        </div>
        <x-primary-button type="button" wire:click="kirimForCurrent" wire:loading.attr="disabled" :disabled="!$hasEncounter"
            class="!bg-teal-600 hover:!bg-teal-700 {{ !empty($episodeId) ? '!bg-emerald-600' : '' }}">
            <span wire:loading.remove wire:target="kirimForCurrent">{{ !empty($episodeId) ? 'Terkirim' : 'Kirim' }}</span>
            <span wire:loading wire:target="kirimForCurrent"><x-loading />...</span>
        </x-primary-button>
    </div>

    @if (!empty($episodeId))
        <div class="flex items-center justify-between p-4 bg-canvas border-2 border-teal-300 shadow-sm rounded-xl dark:bg-gray-900 dark:border-teal-700">
            <div class="flex items-center gap-3">
                <div
                    class="flex items-center justify-center w-8 h-8 rounded-full {{ $episodeFinished ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-teal-100 text-teal-600 dark:bg-teal-900/30 dark:text-teal-400' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <div>
                    <div class="font-semibold text-ink dark:text-gray-100">Selesaikan Episode</div>
                    <div class="text-xs text-muted dark:text-gray-400">Status finished + period.end saat pasien pulang.</div>
                </div>
            </div>
            <x-primary-button type="button" wire:click="finishForCurrent" wire:loading.attr="disabled"
                class="{{ $episodeFinished ? '!bg-emerald-600' : '!bg-teal-600 hover:!bg-teal-700' }}">
                <span wire:loading.remove wire:target="finishForCurrent">{{ $episodeFinished ? 'Selesai' : 'Finish' }}</span>
                <span wire:loading wire:target="finishForCurrent"><x-loading />...</span>
            </x-primary-button>
        </div>
    @endif
</div>
