<?php
// resources/views/pages/transaksi/ri/idrg/kirim-final-idrg.blade.php
// Step 7: Final iDRG + Edit Ulang iDRG.

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\iDRG\iDrgTrait;

new class extends Component {
    use EmrRITrait, iDrgTrait;

    public ?string $riHdrNo = null;
    public bool $hasClaim = false;
    public bool $hasGroup = false;
    public bool $idrgFinal = false;
    public ?string $idrgFinalAt = null;
    public bool $idrgUngroupable = false;

    public function mount(?string $riHdrNo = null): void
    {
        $this->riHdrNo = $riHdrNo;
        $this->reloadState();
    }

    #[On('idrg-state-updated-ri')]
    public function onStateUpdated(string $riHdrNo): void
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
        $idrg = $data['idrg'] ?? [];
        $this->hasClaim = !empty($idrg['nomorSep']);
        $this->hasGroup = !empty($idrg['idrgGroup']);
        $this->idrgFinal = !empty($idrg['idrgFinal']);
        $this->idrgFinalAt = $idrg['idrgFinalAt'] ?? null;
        $this->idrgUngroupable = !empty($idrg['idrgUngroupable']);
    }

    public function final(): void
    {
        if (empty($this->riHdrNo)) {
            return;
        }
        try {
            $data = $this->findDataRI($this->riHdrNo);
            $idrg = $data['idrg'] ?? [];
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) {
                $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.');
                return;
            }
            if (!empty($idrg['idrgUngroupable'])) {
                $this->dispatch('toast', type: 'error', message: 'Tidak bisa final: grouping iDRG masih ungroupable.');
                return;
            }
            if (empty($idrg['idrgGroup'])) {
                $this->dispatch('toast', type: 'error', message: 'Belum ada grouping iDRG.');
                return;
            }

            $res = $this->finalIdrg($nomorSep)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Final iDRG'));
                return;
            }

            $idrg['idrgFinal'] = true;
            $idrg['idrgFinalAt'] = now()->toIso8601String();
            $this->saveResult($idrg);
            $this->dispatch('toast', type: 'success', message: 'iDRG final.');
            $this->dispatch('idrg-section-changed-ri', riHdrNo: (string) $this->riHdrNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Final iDRG gagal: ' . $e->getMessage());
        }
    }

    public function reedit(): void
    {
        if (empty($this->riHdrNo)) {
            return;
        }
        try {
            $data = $this->findDataRI($this->riHdrNo);
            $idrg = $data['idrg'] ?? [];
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) {
                $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.');
                return;
            }

            $res = $this->reeditIdrg($nomorSep)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Edit Ulang iDRG'));
                return;
            }

            $idrg['idrgFinal'] = false;
            $idrg['idrgFinalAt'] = null;
            $this->saveResult($idrg);
            $this->dispatch('toast', type: 'success', message: 'iDRG dibuka untuk edit ulang.');
            $this->dispatch('idrg-section-changed-ri', riHdrNo: (string) $this->riHdrNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Re-edit iDRG gagal: ' . $e->getMessage());
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
        $this->dispatch('idrg-state-updated-ri', riHdrNo: (string) $this->riHdrNo);
    }
};
?>

<div class="flex items-center justify-between p-4 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700">
    <div class="flex items-center gap-3">
        <div
            class="flex items-center justify-center w-8 h-8 rounded-full {{ $idrgFinal ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500' }}">
            <span class="text-sm font-bold">7</span>
        </div>
        <div>
            <div class="font-semibold text-gray-800 dark:text-gray-100">Final iDRG</div>
            <div class="text-xs text-gray-500 dark:text-gray-400">
                @if ($idrgUngroupable)
                    <span class="text-rose-600 dark:text-rose-400">Ungroupable — tidak bisa final.</span>
                @elseif ($idrgFinal && $idrgFinalAt)
                    Final pada <span class="font-mono">{{ $idrgFinalAt }}</span>
                @else
                    Finalisasi coding iDRG.
                @endif
            </div>
        </div>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        @if ($idrgFinal)
            <button type="button" wire:click="reedit" wire:loading.attr="disabled"
                wire:confirm="Buka kembali iDRG untuk edit ulang?"
                class="px-3 py-1.5 text-xs font-medium text-amber-700 bg-amber-50 rounded-lg hover:bg-amber-100 disabled:opacity-50 dark:bg-amber-900/20 dark:text-amber-400 dark:hover:bg-amber-900/30">
                <span wire:loading.remove wire:target="reedit">↶ Edit Ulang iDRG</span>
                <span wire:loading wire:target="reedit"><x-loading />...</span>
            </button>
        @endif
        <x-primary-button type="button" wire:click="final" wire:loading.attr="disabled"
            :disabled="!$hasClaim || !$hasGroup || $idrgUngroupable || $idrgFinal"
            class="!bg-brand hover:!bg-brand/90 {{ $idrgFinal ? '!bg-emerald-600' : '' }}">
            <span wire:loading.remove wire:target="final">{{ $idrgFinal ? 'Selesai' : 'Final iDRG' }}</span>
            <span wire:loading wire:target="final"><x-loading />...</span>
        </x-primary-button>
    </div>
</div>
