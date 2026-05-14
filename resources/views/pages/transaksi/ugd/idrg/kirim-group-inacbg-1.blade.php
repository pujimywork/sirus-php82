<?php
// resources/views/pages/transaksi/ugd/idrg/kirim-group-inacbg-1.blade.php
// Step 11: Grouping INACBG Stage 1.

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\iDRG\iDrgTrait;

new class extends Component {
    use EmrUGDTrait, iDrgTrait;

    public ?string $rjNo = null;
    public bool $idrgFinal = false;
    public bool $inacbgFinal = false;
    public bool $inacbgUngroupable = false;
    public array $inacbgStage1 = [];

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
        $this->inacbgFinal = !empty($idrg['inacbgFinal']);
        $this->inacbgUngroupable = !empty($idrg['inacbgUngroupable']);
        $this->inacbgStage1 = $idrg['inacbgStage1'] ?? [];
    }

    public function group(): void
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

            $res = $this->grouperInacbgStage1($nomorSep)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Grouping INACBG Stage 1'));
                return;
            }

            $payload = $res['response'] ?? [];
            $cbgCode = (string) ($payload['response_inacbg']['cbg']['code'] ?? '');
            $isUngroupable = str_starts_with($cbgCode, 'X');

            $idrg['inacbgStage1'] = $payload;
            $idrg['inacbgUngroupable'] = $isUngroupable;
            $idrg['inacbgFinal'] = false;
            $this->saveResult($idrg);

            if ($isUngroupable) {
                $reason = self::describeUngroupable($payload);
                $this->dispatch('toast', type: 'warning', message: "INACBG tidak bisa dikelompokkan (kode diawali \"X\": {$cbgCode}). {$reason} Tombol Final INACBG nonaktif.");
            } else {
                $this->dispatch('toast', type: 'success', message: "INACBG stage 1: {$cbgCode}");
            }
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Grouping INACBG stage 1 gagal: ' . $e->getMessage());
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

<div class="p-4 space-y-3 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700">
    <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div
                class="flex items-center justify-center w-8 h-8 rounded-full {{ !empty($inacbgStage1) ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500' }}">
                <span class="text-sm font-bold">12</span>
            </div>
            <div>
                <div class="font-semibold text-gray-800 dark:text-gray-100">Grouping INACBG Stage 1</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Hasil kode CBG dasar.</div>
            </div>
        </div>
        <x-primary-button type="button" wire:click="group" wire:loading.attr="disabled"
            :disabled="!$idrgFinal || $inacbgFinal"
            class="!bg-brand hover:!bg-brand/90 {{ !empty($inacbgStage1) ? '!bg-emerald-600' : '' }}">
            <span wire:loading.remove wire:target="group">{{ !empty($inacbgStage1) ? 'Group Ulang' : 'Jalankan' }}</span>
            <span wire:loading wire:target="group"><x-loading />...</span>
        </x-primary-button>
    </div>

    @if (!empty($inacbgStage1))
        @php
            $cbg = $inacbgStage1['response_inacbg']['cbg'] ?? [];
            $tarif = $inacbgStage1['response_inacbg']['tariff'] ?? [];
        @endphp
        <div class="px-3 py-2 text-xs rounded-lg bg-gray-50 dark:bg-gray-800">
            <div class="grid grid-cols-2 gap-3 md:grid-cols-3">
                <div>
                    <div class="text-gray-500">CBG Code</div>
                    <div class="font-mono font-semibold text-gray-800 dark:text-gray-100">{{ $cbg['code'] ?? '-' }}</div>
                </div>
                <div class="md:col-span-2">
                    <div class="text-gray-500">Deskripsi</div>
                    <div class="text-gray-700 dark:text-gray-300">{{ $cbg['description'] ?? '-' }}</div>
                </div>
                <div>
                    <div class="text-gray-500">Tarif Total</div>
                    <div class="font-mono font-semibold text-gray-800 dark:text-gray-100">
                        Rp {{ number_format((int) ($tarif['total'] ?? 0), 0, ',', '.') }}
                    </div>
                </div>
                <div>
                    <div class="text-gray-500">Status</div>
                    @if ($inacbgUngroupable)
                        <x-badge variant="danger">Ungroupable (X)</x-badge>
                    @else
                        <x-badge variant="success">Groupable</x-badge>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
