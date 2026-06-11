<?php
// resources/views/pages/transaksi/ri/idrg/kirim-generate-number.blade.php
// Step 1: Generate Nomor Klaim — opsional (pasien khusus COVID/KIPI/Bayi/Co-Insidense).

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\iDRG\iDrgTrait;

new class extends Component {
    use EmrRITrait, iDrgTrait;

    public ?string $riHdrNo = null;
    public ?string $claimNumber = null;
    public bool $hasClaim = false;
    public bool $hasSepRaw = false;
    public bool $idrgFinal = false;

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
        $this->claimNumber = $idrg['claimNumber'] ?? null;
        $this->hasClaim = !empty($idrg['nomorSep']);
        $this->hasSepRaw = !empty(data_get($data, 'sep.noSep'));
        $this->idrgFinal = !empty($idrg['idrgFinal']);
    }

    public function generate(): void
    {
        if (empty($this->riHdrNo)) {
            return;
        }
        try {
            $res = $this->generateClaimNumber()->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Generate Nomor Klaim'));
                return;
            }
            $claimNumber = $res['response']['claim_number'] ?? null;
            if (empty($claimNumber)) {
                $this->dispatch('toast', type: 'error', message: 'claim_number kosong.');
                return;
            }

            $data = $this->findDataRI($this->riHdrNo);
            $idrg = $data['idrg'] ?? [];
            $idrg['claimNumber'] = $claimNumber;
            $this->saveResult($idrg);
            $this->dispatch('toast', type: 'success', message: "Claim number: {$claimNumber}");
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Generate gagal: ' . $e->getMessage());
        }
    }

    private function saveResult(array $idrg): void
    {
        DB::transaction(function () use ($idrg) {
            $this->lockRIRow($this->riHdrNo);
            $data = $this->findDataRI($this->riHdrNo);
            $data['idrg'] = $idrg;
            $this->updateJsonRI($this->riHdrNo, $data);
        });
        $this->dispatch('idrg-section-changed-ri', riHdrNo: (string) $this->riHdrNo);
    }
};
?>

<div class="flex items-center justify-between p-4 bg-canvas border border-hairline shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700">
    <div class="flex items-center gap-3">
        <div
            class="flex items-center justify-center w-8 h-8 rounded-full {{ !empty($claimNumber) ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-surface-soft text-muted-soft dark:bg-gray-800 dark:text-gray-500' }}">
            <span class="text-sm font-bold">1</span>
        </div>
        <div>
            <div class="font-semibold text-ink dark:text-gray-100">Generate Nomor Klaim</div>
            <div class="text-sm text-muted dark:text-gray-400">
                Opsional (pasien COVID / KIPI / Bayi Baru Lahir / Co-Insidense).
                @if (!empty($claimNumber))
                    <span class="font-mono font-semibold text-success dark:text-success">{{ $claimNumber }}</span>
                @endif
            </div>
        </div>
    </div>
    <x-primary-button type="button" wire:click="generate" wire:loading.attr="disabled"
        :disabled="$hasClaim || $hasSepRaw || $idrgFinal"
        class="!bg-brand hover:!bg-brand/90 min-w-[160px] {{ !empty($claimNumber) ? '!bg-emerald-600' : '' }}">
        <span wire:loading.remove wire:target="generate">{{ !empty($claimNumber) ? 'Selesai' : 'Jalankan' }}</span>
        <span wire:loading wire:target="generate"><x-loading />...</span>
    </x-primary-button>
</div>
