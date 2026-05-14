<?php
// resources/views/pages/transaksi/rj/idrg/kirim-group-idrg.blade.php
// Step 6: Grouping iDRG Stage 1 (grouper, grouper=idrg, stage=1).
// Manual 5.10.x: response stage 1 bisa berisi topup_options — kalau ada,
// coder harus jalankan Stage 2 dulu sebelum Final iDRG.

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\iDRG\iDrgTrait;

new class extends Component {
    use EmrRJTrait, iDrgTrait;

    public ?string $rjNo = null;
    public bool $hasClaim = false;
    public bool $idrgFinal = false;
    public bool $idrgUngroupable = false;
    public array $idrgGroup = [];

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
        $this->hasClaim = !empty($idrg['nomorSep']);
        $this->idrgFinal = !empty($idrg['idrgFinal']);
        $this->idrgUngroupable = !empty($idrg['idrgUngroupable']);
        $this->idrgGroup = $idrg['idrgGroup'] ?? [];
    }

    public function group(): void
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

            $res = $this->grouperIdrgStage1($nomorSep)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Grouping iDRG'));
                return;
            }

            $groupResult = $res['response'] ?? [];
            // Kriteria 7-8: mdc 36 = ungroupable
            $mdc = $groupResult['mdc_number'] ?? '';
            $isUngroupable = ((string) $mdc === '36');

            $idrg['idrgGroup'] = $groupResult;
            $idrg['idrgUngroupable'] = $isUngroupable;
            $idrg['idrgFinal'] = false;
            // Reset stage 2 setiap kali stage 1 dijalankan ulang
            $idrg['idrgStage2'] = [];
            $idrg['idrgTopupCodesInput'] = '';
            $this->saveResult($idrg);

            if ($isUngroupable) {
                $this->dispatch('toast', type: 'warning', message: self::describeUngroupable($groupResult) . ' Tombol Final iDRG tidak aktif.');
            } else {
                $this->dispatch('toast', type: 'success', message: 'Grouping iDRG: ' . ($groupResult['drg_code'] ?? '-'));
            }
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Grouping iDRG gagal: ' . $e->getMessage());
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

<div class="p-4 space-y-3 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700">
    <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div
                class="flex items-center justify-center w-8 h-8 rounded-full {{ !empty($idrgGroup) ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500' }}">
                <span class="text-sm font-bold">6</span>
            </div>
            <div>
                <div class="font-semibold text-gray-800 dark:text-gray-100">Grouping iDRG Stage 1</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Hasil DRG dasar (kalau ada topup_options, lanjut Stage 2).</div>
            </div>
        </div>
        <x-primary-button type="button" wire:click="group" wire:loading.attr="disabled"
            :disabled="!$hasClaim || $idrgFinal"
            class="!bg-brand hover:!bg-brand/90 {{ !empty($idrgGroup) ? '!bg-emerald-600' : '' }}">
            <span wire:loading.remove wire:target="group">{{ !empty($idrgGroup) ? 'Group Ulang' : 'Jalankan' }}</span>
            <span wire:loading wire:target="group"><x-loading />...</span>
        </x-primary-button>
    </div>

    @if (!empty($idrgGroup))
        <div class="px-3 py-2 text-xs rounded-lg bg-gray-50 dark:bg-gray-800">
            <div class="grid grid-cols-2 gap-3 md:grid-cols-3">
                <div>
                    <div class="text-gray-500">DRG Code</div>
                    <div class="font-mono font-semibold text-gray-800 dark:text-gray-100">
                        {{ $idrgGroup['drg_code'] ?? '-' }}
                    </div>
                </div>
                <div class="md:col-span-2">
                    <div class="text-gray-500">Deskripsi</div>
                    <div class="text-gray-700 dark:text-gray-300">{{ $idrgGroup['drg_description'] ?? '-' }}</div>
                </div>
                <div>
                    <div class="text-gray-500">MDC</div>
                    <div class="text-gray-700 dark:text-gray-300">
                        {{ $idrgGroup['mdc_number'] ?? '-' }} — {{ $idrgGroup['mdc_description'] ?? '-' }}
                    </div>
                </div>
                <div>
                    <div class="text-gray-500">Total Cost Weight</div>
                    <div class="font-mono font-semibold text-gray-800 dark:text-gray-100">
                        {{ $idrgGroup['total_cost_weight'] ?? ($idrgGroup['cost_weight'] ?? '-') }}
                    </div>
                </div>
                <div>
                    <div class="text-gray-500">Status</div>
                    @if ($idrgUngroupable)
                        <x-badge variant="danger">Ungroupable</x-badge>
                    @else
                        <x-badge variant="success">{{ $idrgGroup['status_cd'] ?? 'normal' }}</x-badge>
                    @endif
                </div>
                @if (!empty($idrgGroup['topup_options']))
                    <div class="md:col-span-3">
                        <div class="text-gray-500">Topup Options</div>
                        <x-badge variant="warning">Perlu Stage 2 ({{ count($idrgGroup['topup_options']) }} opsi)</x-badge>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
