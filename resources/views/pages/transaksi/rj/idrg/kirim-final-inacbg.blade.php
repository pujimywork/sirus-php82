<?php
// resources/views/pages/transaksi/rj/idrg/kirim-final-inacbg.blade.php
// Step 13: Final INACBG + Edit Ulang INACBG.

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\iDRG\iDrgTrait;

new class extends Component {
    use EmrRJTrait, iDrgTrait;

    public ?string $rjNo = null;
    public bool $idrgFinal = false;
    public bool $inacbgFinal = false;
    public ?string $inacbgFinalAt = null;
    public bool $inacbgUngroupable = false;
    public bool $hasStage1 = false;
    public bool $klaimFinal = false;

    public function mount(?string $rjNo = null): void
    {
        $this->rjNo = $rjNo;
        $this->reloadState();
    }

    #[On('idrg-state-updated')]
    public function onStateUpdated(string $rjNo): void
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
        $idrg = $data['idrg'] ?? [];
        $this->idrgFinal = !empty($idrg['idrgFinal']);
        $this->inacbgFinal = !empty($idrg['inacbgFinal']);
        $this->inacbgFinalAt = $idrg['inacbgFinalAt'] ?? null;
        $this->inacbgUngroupable = !empty($idrg['inacbgUngroupable']);
        $this->hasStage1 = !empty($idrg['inacbgStage1']);
        $this->klaimFinal = !empty($idrg['klaimFinal']);
    }

    public function final(): void
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
            if (!empty($idrg['inacbgUngroupable'])) {
                $this->dispatch('toast', type: 'error', message: 'Tidak bisa final: INACBG masih ungroupable.');
                return;
            }

            $res = $this->finalInacbg($nomorSep)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Final INACBG'));
                return;
            }

            $idrg['inacbgFinal'] = true;
            $idrg['inacbgFinalAt'] = now()->toIso8601String();
            $this->saveResult($idrg);
            $this->dispatch('toast', type: 'success', message: 'INACBG final.');
            $this->dispatch('idrg-section-changed', rjNo: (string) $this->rjNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Final INACBG gagal: ' . $e->getMessage());
        }
    }

    public function reedit(): void
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

            $res = $this->reeditInacbg($nomorSep)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Edit Ulang INACBG'));
                return;
            }

            $idrg['inacbgFinal'] = false;
            $idrg['inacbgFinalAt'] = null;
            $this->saveResult($idrg);
            $this->dispatch('toast', type: 'success', message: 'INACBG dibuka untuk edit ulang.');
            $this->dispatch('idrg-section-changed', rjNo: (string) $this->rjNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Re-edit INACBG gagal: ' . $e->getMessage());
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
        $this->dispatch('idrg-state-updated', rjNo: (string) $this->rjNo);
    }
};
?>

<div class="flex items-center justify-between p-4 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700">
    <div class="flex items-center gap-3">
        <div
            class="flex items-center justify-center w-8 h-8 rounded-full {{ $inacbgFinal ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500' }}">
            <span class="text-sm font-bold">13</span>
        </div>
        <div>
            <div class="font-semibold text-gray-800 dark:text-gray-100">Final INACBG</div>
            <div class="text-xs text-gray-500 dark:text-gray-400">
                @if ($inacbgUngroupable)
                    <span class="text-rose-600 dark:text-rose-400">Ungroupable — tidak bisa final.</span>
                @elseif ($inacbgFinal && $inacbgFinalAt)
                    Final pada <span class="font-mono">{{ $inacbgFinalAt }}</span>
                @else
                    Finalisasi coding INACBG.
                @endif
            </div>
        </div>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        @if ($inacbgFinal && !$klaimFinal)
            <button type="button" wire:click="reedit" wire:loading.attr="disabled"
                wire:confirm="Buka kembali INACBG untuk edit ulang?"
                class="px-3 py-1.5 text-xs font-medium text-amber-700 bg-amber-50 rounded-lg hover:bg-amber-100 disabled:opacity-50 dark:bg-amber-900/20 dark:text-amber-400 dark:hover:bg-amber-900/30">
                <span wire:loading.remove wire:target="reedit">↶ Edit Ulang INACBG</span>
                <span wire:loading wire:target="reedit"><x-loading />...</span>
            </button>
        @endif
        <x-primary-button type="button" wire:click="final" wire:loading.attr="disabled"
            :disabled="!$idrgFinal || $inacbgUngroupable || $inacbgFinal || !$hasStage1"
            class="!bg-brand hover:!bg-brand/90 {{ $inacbgFinal ? '!bg-emerald-600' : '' }}">
            <span wire:loading.remove wire:target="final">{{ $inacbgFinal ? 'Selesai' : 'Final INACBG' }}</span>
            <span wire:loading wire:target="final"><x-loading />...</span>
        </x-primary-button>
    </div>
</div>
