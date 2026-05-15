<?php
// resources/views/pages/transaksi/ugd/idrg/kirim-import-inacbg.blade.php
// Step 8: Import koding iDRG → INACBG (sekaligus).

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\iDRG\iDrgTrait;

new class extends Component {
    use EmrUGDTrait, iDrgTrait;

    public ?string $rjNo = null;
    public bool $idrgFinal = false;
    public ?string $inacbgImportedAt = null;

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
        $data = $this->findDataUGD($this->rjNo);
        if (empty($data)) {
            return;
        }
        $idrg = $data['idrg'] ?? [];
        $this->idrgFinal = !empty($idrg['idrgFinal']);
        $this->inacbgImportedAt = $idrg['inacbgImportedAt'] ?? null;
    }

    public function importInacbg(): void
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
            // Kriteria 12
            if (empty($idrg['idrgFinal'])) {
                $this->dispatch('toast', type: 'error', message: 'iDRG harus final terlebih dahulu.');
                return;
            }

            $res = $this->importIdrgToInacbg($nomorSep)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Import iDRG ke INACBG'));
                return;
            }

            $idrg['inacbgImport'] = $res['response'] ?? [];
            $idrg['inacbgImportedAt'] = now()->toIso8601String();
            $this->saveResult($idrg);
            $this->dispatch('toast', type: 'success', message: 'Import iDRG → INACBG selesai. Cek kode "IM tidak berlaku" di step 9-10 jika ada.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Import INACBG gagal: ' . $e->getMessage());
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
        $this->dispatch('idrg-section-changed-ugd', rjNo: (string) $this->rjNo);
    }
};
?>

<div class="flex items-center justify-between p-4 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700">
    <div class="flex items-center gap-3">
        <div
            class="flex items-center justify-center w-8 h-8 rounded-full {{ !empty($inacbgImportedAt) ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500' }}">
            <span class="text-sm font-bold">9</span>
        </div>
        <div>
            <div class="font-semibold text-gray-800 dark:text-gray-100">Import Coding iDRG → INACBG</div>
            <div class="text-xs text-gray-500 dark:text-gray-400">
                @if (!empty($inacbgImportedAt))
                    Terimport pada <span class="font-mono">{{ $inacbgImportedAt }}</span>
                @else
                    Import keseluruhan kode sekaligus.
                @endif
            </div>
        </div>
    </div>
    <x-primary-button type="button" wire:click="importInacbg" wire:loading.attr="disabled"
        :disabled="!$idrgFinal"
        class="!bg-brand hover:!bg-brand/90 min-w-[160px] {{ !empty($inacbgImportedAt) ? '!bg-emerald-600' : '' }}">
        <span wire:loading.remove wire:target="importInacbg">{{ !empty($inacbgImportedAt) ? 'Import Ulang' : 'Import' }}</span>
        <span wire:loading wire:target="importInacbg"><x-loading />...</span>
    </x-primary-button>
</div>
