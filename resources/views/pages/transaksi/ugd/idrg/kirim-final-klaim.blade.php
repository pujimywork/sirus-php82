<?php
// resources/views/pages/transaksi/ugd/idrg/kirim-final-klaim.blade.php
// Step 14: Final Klaim + Edit Ulang Klaim.

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\iDRG\iDrgTrait;

new class extends Component {
    use EmrUGDTrait, iDrgTrait;

    public ?string $rjNo = null;
    public bool $inacbgFinal = false;
    public bool $klaimFinal = false;
    public ?string $klaimFinalAt = null;
    public ?string $coderNik = null;

    public function mount(?string $rjNo = null): void
    {
        $this->rjNo = $rjNo;
        $this->reloadState();
    }

    #[On('idrg-state-updated-ugd')]
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
        $data = $this->findDataUGD($this->rjNo);
        if (empty($data)) {
            return;
        }
        $idrg = $data['idrg'] ?? [];
        $this->inacbgFinal = !empty($idrg['inacbgFinal']);
        $this->klaimFinal = !empty($idrg['klaimFinal']);
        $this->klaimFinalAt = $idrg['klaimFinalAt'] ?? null;
        $this->coderNik = $idrg['coderNik'] ?? null;
    }

    public function final(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        try {
            $data = $this->findDataUGD($this->rjNo);
            $idrg = $data['idrg'] ?? [];
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) {
                $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.');
                return;
            }
            // Kriteria 20
            if (empty($idrg['inacbgFinal'])) {
                $this->dispatch('toast', type: 'error', message: 'INACBG harus final terlebih dahulu.');
                return;
            }

            $coderNik = (string) (auth()->user()->emp_id ?? '');
            if (empty($coderNik)) {
                $this->dispatch('toast', type: 'error', message: 'User aktif tidak punya emp_id. Hubungi admin untuk set Karyawan di profil user.');
                return;
            }

            $res = $this->finalClaim($nomorSep, $coderNik)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Final Klaim'));
                return;
            }

            $idrg['klaimFinal'] = true;
            $idrg['klaimFinalAt'] = now()->toIso8601String();
            $idrg['coderNik'] = $coderNik;
            $this->saveResult($idrg);
            $this->dispatch('toast', type: 'success', message: 'Klaim final.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Final klaim gagal: ' . $e->getMessage());
        }
    }

    public function reedit(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        try {
            $data = $this->findDataUGD($this->rjNo);
            $idrg = $data['idrg'] ?? [];
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) {
                $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.');
                return;
            }

            $res = $this->reeditClaim($nomorSep)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Edit Ulang Klaim'));
                return;
            }

            $idrg['klaimFinal'] = false;
            $idrg['klaimFinalAt'] = null;
            $this->saveResult($idrg);
            $this->dispatch('toast', type: 'success', message: 'Klaim dibuka untuk edit ulang.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Re-edit klaim gagal: ' . $e->getMessage());
        }
    }

    private function saveResult(array $idrg): void
    {
        DB::transaction(function () use ($idrg) {
            $this->lockUGDRow($this->rjNo);
            $data = $this->findDataUGD($this->rjNo);
            $data['idrg'] = $idrg;
            $this->updateJsonUGD($this->rjNo, $data);
        });
        $this->dispatch('idrg-state-updated-ugd', rjNo: (string) $this->rjNo);
    }
};
?>

<div class="flex items-center justify-between p-4 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700">
    <div class="flex items-center gap-3">
        <div
            class="flex items-center justify-center w-8 h-8 rounded-full {{ $klaimFinal ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500' }}">
            <span class="text-sm font-bold">15</span>
        </div>
        <div>
            <div class="font-semibold text-gray-800 dark:text-gray-100">Final Klaim</div>
            <div class="text-xs text-gray-500 dark:text-gray-400">
                @if ($klaimFinal && $klaimFinalAt)
                    Final pada <span class="font-mono">{{ $klaimFinalAt }}</span>
                    @if ($coderNik)
                        — coder: <span class="font-mono">{{ $coderNik }}</span>
                    @endif
                @else
                    claim_final (coder_nik = emp_id user aktif).
                @endif
            </div>
        </div>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        @if ($klaimFinal)
            <button type="button" wire:click="reedit" wire:loading.attr="disabled"
                wire:confirm="Buka kembali finalisasi klaim untuk edit?"
                class="px-3 py-1.5 text-xs font-medium text-amber-700 bg-amber-50 rounded-lg hover:bg-amber-100 disabled:opacity-50 dark:bg-amber-900/20 dark:text-amber-400 dark:hover:bg-amber-900/30">
                <span wire:loading.remove wire:target="reedit">↶ Edit Ulang Klaim</span>
                <span wire:loading wire:target="reedit"><x-loading />...</span>
            </button>
        @endif
        <x-primary-button type="button" wire:click="final" wire:loading.attr="disabled"
            :disabled="!$inacbgFinal || $klaimFinal"
            class="!bg-brand hover:!bg-brand/90 {{ $klaimFinal ? '!bg-emerald-600' : '' }}">
            <span wire:loading.remove wire:target="final">{{ $klaimFinal ? 'Selesai' : 'Final Klaim' }}</span>
            <span wire:loading wire:target="final"><x-loading />...</span>
        </x-primary-button>
    </div>
</div>
