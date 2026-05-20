<?php
// resources/views/pages/transaksi/ri/idrg/kirim-import-inacbg.blade.php
// Step 8: Import koding iDRG → INACBG (sekaligus).

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\iDRG\iDrgTrait;

new class extends Component {
    use EmrRITrait, iDrgTrait;

    public ?string $riHdrNo = null;
    public bool $idrgFinal = false;
    public ?string $inacbgImportedAt = null;

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
        $this->idrgFinal = !empty($idrg['idrgFinal']);
        $this->inacbgImportedAt = $idrg['inacbgImportedAt'] ?? null;
    }

    public function importInacbg(): void
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

            // REPLACE: copy ulang coder diagnosa & prosedur dari iDRG ke INACBG.
            // Reset syncedAt + coder lokal — saat parent re-render & SFC INACBG remount,
            // reloadState akan auto-persist dari idrg.coderDiagnosa / idrg.coderProsedur.
            // (efek sama dengan klik "Sync dari iDRG" di step 9 & 10, tapi otomatis.)
            $idrg['coderInacbgDiagnosa'] = [];
            $idrg['coderInacbgDiagnosaSyncedAt'] = null;
            $idrg['coderInacbgProsedur'] = [];
            $idrg['coderInacbgProsedurSyncedAt'] = null;
            // Hasil set diagnosa/prosedur INACBG sebelumnya juga reset — biar konsisten dengan coder baru.
            $idrg['inacbgDiagnosaString'] = null;
            $idrg['inacbgProsedurString'] = null;

            $this->saveResult($idrg);
            $this->dispatch('toast', type: 'success', message: 'Import iDRG → INACBG selesai. Coder INACBG di-replace ulang dari iDRG.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Import INACBG gagal: ' . $e->getMessage());
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
