<?php
// resources/views/pages/transaksi/rj/idrg/kirim-send-klaim.blade.php
// Step 15: Kirim Klaim ke Data Center (send_claim_individual).

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\iDRG\iDrgTrait;

new class extends Component {
    use EmrRJTrait, iDrgTrait;

    public ?string $rjNo = null;
    public bool $klaimFinal = false;
    public ?string $sentAt = null;
    public array $sendResult = [];

    public function mount(?string $rjNo = null): void
    {
        $this->rjNo = $rjNo;
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
        $idrg = $data['idrg'] ?? [];
        $this->klaimFinal = !empty($idrg['klaimFinal']);
        $this->sentAt = $idrg['sentAt'] ?? null;
        $this->sendResult = $idrg['sendResult'] ?? [];
    }

    public function send(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        try {
            $data = $this->findDataRJ($this->rjNo);
            $idrg = $data['idrg'] ?? [];
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) {
                $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.');
                return;
            }
            // Kriteria 22
            if (empty($idrg['klaimFinal'])) {
                $this->dispatch('toast', type: 'error', message: 'Klaim harus final terlebih dahulu.');
                return;
            }

            $res = $this->sendClaimIndividual($nomorSep)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Kirim Klaim ke Data Center'));
                return;
            }

            $idrg['sendResult'] = $res['response']['data'][0] ?? [];
            $idrg['sentAt'] = now()->toIso8601String();
            $this->saveResult($idrg);
            $this->dispatch('toast', type: 'success', message: 'Klaim terkirim ke data center.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Kirim klaim gagal: ' . $e->getMessage());
        }
    }

    private function saveResult(array $idrg): void
    {
        DB::transaction(function () use ($idrg) {
            $this->lockRJRow($this->rjNo);
            $data = $this->findDataRJ($this->rjNo);
            $data['idrg'] = $idrg;
            $this->updateJsonRJ($this->rjNo, $data);
        });
        $this->dispatch('idrg-section-changed', rjNo: (string) $this->rjNo);
    }
};
?>

<div class="flex items-center justify-between p-4 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700">
    <div class="flex items-center gap-3">
        <div
            class="flex items-center justify-center w-8 h-8 rounded-full {{ !empty($sentAt) ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500' }}">
            <span class="text-sm font-bold">16</span>
        </div>
        <div>
            <div class="font-semibold text-gray-800 dark:text-gray-100">Kirim Klaim ke Data Center</div>
            <div class="text-xs text-gray-500 dark:text-gray-400">
                @if (!empty($sentAt))
                    Terkirim pada <span class="font-mono">{{ $sentAt }}</span>
                @else
                    send_claim_individual.
                @endif
            </div>
        </div>
    </div>
    <x-primary-button type="button" wire:click="send" wire:loading.attr="disabled" :disabled="!$klaimFinal"
        class="!bg-brand hover:!bg-brand/90 min-w-[160px] {{ !empty($sentAt) ? '!bg-emerald-600' : '' }}">
        <span wire:loading.remove wire:target="send">{{ !empty($sentAt) ? 'Kirim Ulang' : 'Kirim' }}</span>
        <span wire:loading wire:target="send"><x-loading />...</span>
    </x-primary-button>
</div>
