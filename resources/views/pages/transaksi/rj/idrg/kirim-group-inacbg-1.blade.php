<?php
// resources/views/pages/transaksi/rj/idrg/kirim-group-inacbg-1.blade.php
// Step 11: Grouping INACBG Stage 1.

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
        $data = $this->findDataRJ($this->rjNo);
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
            $data = $this->findDataRJ($this->rjNo);
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
            // Toleran 2 bentuk respons E-Klaim: flat `cbg.code` (baru) atau
            // wrapper `response_inacbg.cbg.code` (versi lama).
            $cbgCode = (string) (data_get($payload, 'cbg.code') ?? data_get($payload, 'response_inacbg.cbg.code') ?? '');
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
            $this->lockRJRow($this->rjNo);
            $data = $this->findDataRJ($this->rjNo);
            $data['idrg'] = $idrg;
            $this->updateJsonRJ($this->rjNo, $data);
        });
        $this->dispatch('idrg-section-changed', rjNo: (string) $this->rjNo);
    }
};
?>

<div class="p-4 space-y-3 bg-canvas border border-hairline shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700">
    <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div
                class="flex items-center justify-center w-8 h-8 rounded-full {{ !empty($inacbgStage1) ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-surface-soft text-muted-soft dark:bg-gray-800 dark:text-gray-500' }}">
                <span class="text-sm font-bold">12</span>
            </div>
            <div>
                <div class="font-semibold text-ink dark:text-gray-100">Grouping INACBG Stage 1</div>
                <div class="text-sm text-muted dark:text-gray-400">Hasil kode CBG dasar.</div>
            </div>
        </div>
        <x-primary-button type="button" wire:click="group" wire:loading.attr="disabled"
            :disabled="!$idrgFinal || $inacbgFinal"
            class="!bg-brand hover:!bg-brand/90 min-w-[240px] {{ !empty($inacbgStage1) ? '!bg-emerald-600' : '' }}">
            <span wire:loading.remove wire:target="group">{{ !empty($inacbgStage1) ? 'Grouping Ulang INACBG Stage 1' : 'Grouping INACBG Stage 1' }}</span>
            <span wire:loading wire:target="group"><x-loading />...</span>
        </x-primary-button>
    </div>

    @if (!empty($inacbgStage1))
        @php
            // Toleran wrapper response_inacbg + flat. Field bisa di root atau di wrapper.
            $resolve = fn($key) => data_get($inacbgStage1, $key) ?? data_get($inacbgStage1, "response_inacbg.{$key}");
            $cbg = $resolve('cbg') ?? [];
            $cbgCode = (string) ($cbg['code'] ?? '-');
            $cbgDesc = (string) ($cbg['description'] ?? '-');
            $costWeight = (string) ($cbg['cost_weight'] ?? '-');
            // Tarif Total — toleran (tariff number/array/total_tariff). Fallback compute base_rate × cost_weight.
            $tariffRaw = $resolve('tariff');
            $tariffTotal = is_numeric($tariffRaw)
                ? (int) $tariffRaw
                : (int) (data_get($tariffRaw, 'total') ?? $resolve('total_tariff') ?? data_get($inacbgStage1, 'tariff.total') ?? 0);
            $baseRate = (int) ($resolve('base_rate') ?? $resolve('nbr') ?? 0);
            if ($tariffTotal === 0 && is_numeric($costWeight) && $baseRate > 0) {
                $tariffTotal = (int) round($baseRate * (float) $costWeight);
            }
            $statusCd = $resolve('status_cd') ?? 'normal';
            $isFinal = $inacbgFinal || ($statusCd === 'final');
            $hasBintang = !$isFinal;
        @endphp
        <div class="overflow-hidden text-sm border border-emerald-200 rounded-lg dark:border-emerald-800">
            <div class="px-3 py-2 font-semibold text-center bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400">
                Hasil Grouping INACBG{{ $inacbgFinal ? ' — Final' : '' }}
            </div>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-hairline-soft dark:divide-gray-800">
                    <tr>
                        <td class="w-32 px-3 py-1.5 text-right text-muted align-top">CBG</td>
                        <td class="px-3 py-1.5 text-body dark:text-gray-300">{{ $cbgDesc }}</td>
                        <td class="px-3 py-1.5 font-mono font-semibold text-ink dark:text-gray-100 whitespace-nowrap">{{ $cbgCode }}</td>
                        <td class="px-3 py-1.5 text-right text-muted whitespace-nowrap">CW: <span class="font-mono font-semibold text-ink dark:text-gray-100">{{ $hasBintang ? '**' : '' }} {{ $costWeight }}</span></td>
                    </tr>
                    @if ($baseRate > 0)
                        <tr>
                            <td class="px-3 py-1.5 text-right text-muted">Base Rate</td>
                            <td class="px-3 py-1.5 font-mono text-body dark:text-gray-300" colspan="3">
                                {{ $hasBintang ? '**' : '' }} Rp {{ number_format($baseRate, 0, ',', '.') }}
                            </td>
                        </tr>
                    @endif
                    <tr class="bg-emerald-50/50 dark:bg-emerald-900/10">
                        <td class="px-3 py-2 font-semibold text-right text-muted">Tarif Total</td>
                        <td class="px-3 py-2 text-right font-mono font-bold text-ink dark:text-white text-sm" colspan="3">
                            {{ $hasBintang ? '**' : '' }} Rp {{ number_format($tariffTotal, 0, ',', '.') }}
                        </td>
                    </tr>
                    <tr>
                        <td class="px-3 py-1.5 text-right text-muted">Status</td>
                        <td class="px-3 py-1.5" colspan="3">
                            @if ($inacbgUngroupable)
                                <x-badge variant="danger">Ungroupable (X)</x-badge>
                            @else
                                <x-badge variant="success">{{ $statusCd }}</x-badge>
                            @endif
                        </td>
                    </tr>
                </tbody>
            </table>
            @if ($hasBintang)
                <div class="px-3 py-2 text-sm italic border-t border-amber-200 text-amber-700 bg-amber-50 dark:text-amber-400 dark:bg-amber-900/20 dark:border-amber-800">
                    ** ) Catatan: Nilai belum final, sewaktu-waktu bisa berubah.
                </div>
            @endif
            <details class="px-3 py-1 text-sm border-t border-hairline dark:border-gray-700">
                <summary class="text-muted cursor-pointer hover:text-body dark:hover:text-gray-300">[debug] raw response</summary>
                <pre class="p-2 mt-1 overflow-x-auto text-[10px] leading-tight bg-surface-soft rounded dark:bg-gray-900">{{ json_encode($inacbgStage1, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
            </details>
        </div>
    @endif
</div>
