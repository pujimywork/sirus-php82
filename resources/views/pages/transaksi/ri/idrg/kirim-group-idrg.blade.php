<?php
// resources/views/pages/transaksi/ri/idrg/kirim-group-idrg.blade.php
// Step 6: Grouping iDRG Stage 1 (grouper, grouper=idrg, stage=1).
// Manual 5.10.x: response stage 1 bisa berisi topup_options — kalau ada,
// coder harus jalankan Stage 2 dulu sebelum Final iDRG.

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\iDRG\iDrgTrait;

new class extends Component {
    use EmrRITrait, iDrgTrait;

    public ?string $riHdrNo = null;
    public bool $hasClaim = false;
    public bool $idrgFinal = false;
    public bool $idrgUngroupable = false;
    public array $idrgGroup = [];
    public string $coderNik = '';
    public string $idrgFinalAt = '';
    public string $claimDataSavedAt = '';
    public string $jenisRawat = '';

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
        $this->hasClaim = !empty($idrg['nomorSep']);
        $this->idrgFinal = !empty($idrg['idrgFinal']);
        $this->idrgUngroupable = !empty($idrg['idrgUngroupable']);
        $this->idrgGroup = $idrg['idrgGroup'] ?? [];
        // Info coder/date dari state (saveResult tidak simpan di idrgGroup, tapi di idrg root)
        $this->coderNik = (string) ($idrg['coderNik'] ?? '');
        $this->idrgFinalAt = (string) ($idrg['idrgFinalAt'] ?? '');
        $this->claimDataSavedAt = (string) ($idrg['claimDataSavedAt'] ?? '');
        // Jenis rawat dari claimData (set_claim_data form)
        $this->jenisRawat = (string) data_get($idrg, 'claimData.jenis_rawat', '');
    }

    public function group(): void
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
            $this->lockRIRow($this->riHdrNo);
            $data = $this->findDataRI($this->riHdrNo);
            $data['idrg'] = $idrg;
            $this->updateJsonRI($this->riHdrNo, $data);
        });
        $this->dispatch('idrg-section-changed-ri', riHdrNo: (string) $this->riHdrNo);
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
            class="!bg-brand hover:!bg-brand/90 min-w-[160px] {{ !empty($idrgGroup) ? '!bg-emerald-600' : '' }}">
            <span wire:loading.remove wire:target="group">{{ !empty($idrgGroup) ? 'Group Ulang' : 'Jalankan' }}</span>
            <span wire:loading wire:target="group"><x-loading />...</span>
        </x-primary-button>
    </div>

    @if (!empty($idrgGroup))
        @php
            // Field-field iDRG response — toleran nama key alternatif untuk versi API beda.
            $drgCode = data_get($idrgGroup, 'drg_code') ?? '-';
            $drgDesc = data_get($idrgGroup, 'drg_description') ?? '-';
            $mdcNo = data_get($idrgGroup, 'mdc_number') ?? '-';
            $mdcDesc = data_get($idrgGroup, 'mdc_description') ?? '-';
            $jenisRawatMap = ['1' => 'Rawat Inap', '2' => 'Rawat Jalan', '3' => 'IGD'];
            $jenisRawatDesc = data_get($idrgGroup, 'jenis_rawat_desc') ?? ($jenisRawatMap[$jenisRawat] ?? ($jenisRawat ?: '-'));
            $drgCw = data_get($idrgGroup, 'drg_cost_weight') ?? data_get($idrgGroup, 'cost_weight') ?? '-';
            $totalCw = data_get($idrgGroup, 'total_cost_weight') ?? '-';
            $nbr = (int) (data_get($idrgGroup, 'nbr') ?? data_get($idrgGroup, 'base_rate') ?? 0);
            // Total Klaim — coba beberapa key; kalau kosong, hitung nbr × total_cw.
            $totalTariff = (int) (
                data_get($idrgGroup, 'total_tariff')
                ?? data_get($idrgGroup, 'tariff')
                ?? data_get($idrgGroup, 'tariff.total')
                ?? data_get($idrgGroup, 'total_klaim')
                ?? data_get($idrgGroup, 'klaim')
                ?? 0
            );
            if ($totalTariff === 0 && is_numeric($totalCw) && $nbr > 0) {
                $totalTariff = (int) round($nbr * (float) $totalCw);
            }
            $statusCd = data_get($idrgGroup, 'status_cd') ?? 'normal';
            $coderDate = $idrgFinalAt ?: $claimDataSavedAt;
            $isFinal = $idrgFinal || ($statusCd === 'final');
            $hasBintang = !$isFinal; // ** = belum final, masih bisa berubah
        @endphp
        <div class="overflow-hidden text-xs border border-emerald-200 rounded-lg dark:border-emerald-800">
            <div class="px-3 py-2 text-center font-semibold bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400">
                Hasil Grouping iDRG{{ $idrgFinal ? ' — Final' : '' }}
            </div>
            <table class="w-full text-xs">
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    <tr>
                        <td class="w-32 px-3 py-1.5 text-right text-gray-500 align-top">Info</td>
                        <td class="px-3 py-1.5 text-gray-700 dark:text-gray-300" colspan="3">
                            @if ($coderNik)
                                <span class="font-mono">{{ $coderNik }}</span>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                            @if ($coderDate)
                                <span class="mx-1 text-gray-400">@</span>
                                <span class="font-mono">{{ $coderDate }}</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="w-32 px-3 py-1.5 text-right text-gray-500">Jenis Rawat</td>
                        <td class="px-3 py-1.5 text-gray-700 dark:text-gray-300" colspan="3">{{ $jenisRawatDesc }}</td>
                    </tr>
                    <tr>
                        <td class="px-3 py-1.5 text-right text-gray-500 align-top">MDC</td>
                        <td class="px-3 py-1.5 text-gray-700 dark:text-gray-300" colspan="3">
                            {{ $mdcDesc }} <span class="ml-1 font-mono text-gray-400">({{ $mdcNo }})</span>
                        </td>
                    </tr>
                    <tr>
                        <td class="px-3 py-1.5 text-right text-gray-500 align-top">DRG</td>
                        <td class="px-3 py-1.5 text-gray-700 dark:text-gray-300">
                            {{ $drgDesc }}
                        </td>
                        <td class="px-3 py-1.5 font-mono font-semibold text-gray-800 dark:text-gray-100 whitespace-nowrap">{{ $drgCode }}</td>
                        <td class="px-3 py-1.5 text-right text-gray-500 whitespace-nowrap">DRG CW: <span class="font-mono font-semibold text-gray-800 dark:text-gray-100">{{ $hasBintang ? '**' : '' }} {{ $drgCw }}</span></td>
                    </tr>
                    <tr>
                        <td class="px-3 py-1.5 text-right text-gray-500">NBR</td>
                        <td class="px-3 py-1.5 font-mono text-gray-700 dark:text-gray-300" colspan="2">
                            {{ $hasBintang ? '**' : '' }} Rp {{ number_format($nbr, 0, ',', '.') }}
                        </td>
                        <td class="px-3 py-1.5 text-right text-gray-500 whitespace-nowrap">Total CW: <span class="font-mono font-semibold text-gray-800 dark:text-gray-100">{{ $hasBintang ? '**' : '' }} {{ $totalCw }}</span></td>
                    </tr>
                    <tr class="bg-emerald-50/50 dark:bg-emerald-900/10">
                        <td class="px-3 py-2 text-right text-gray-500 font-semibold">Total Klaim</td>
                        <td class="px-3 py-2 text-right font-mono font-bold text-gray-900 dark:text-white text-sm" colspan="3">
                            {{ $hasBintang ? '**' : '' }} Rp {{ number_format($totalTariff, 0, ',', '.') }}
                        </td>
                    </tr>
                    <tr>
                        <td class="px-3 py-1.5 text-right text-gray-500">Status</td>
                        <td class="px-3 py-1.5" colspan="3">
                            @if ($idrgUngroupable)
                                <x-badge variant="danger">Ungroupable (MDC 36)</x-badge>
                            @else
                                <x-badge variant="success">{{ $statusCd }}</x-badge>
                            @endif
                        </td>
                    </tr>
                    @if (!empty($idrgGroup['topup_options']))
                        <tr>
                            <td class="px-3 py-1.5 text-right text-gray-500">Topup</td>
                            <td class="px-3 py-1.5" colspan="3">
                                <x-badge variant="warning">Perlu Stage 2 ({{ count($idrgGroup['topup_options']) }} opsi)</x-badge>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
            @if ($hasBintang)
                <div class="px-3 py-2 text-xs italic text-amber-700 bg-amber-50 dark:text-amber-400 dark:bg-amber-900/20 border-t border-amber-200 dark:border-amber-800">
                    ** ) Catatan: Nilai belum final, sewaktu-waktu bisa berubah.
                </div>
            @endif
            <details class="px-3 py-1 text-xs border-t border-gray-200 dark:border-gray-700">
                <summary class="text-gray-500 cursor-pointer hover:text-gray-700 dark:hover:text-gray-300">[debug] raw response</summary>
                <pre class="p-2 mt-1 overflow-x-auto text-[10px] leading-tight bg-gray-100 rounded dark:bg-gray-900">{{ json_encode($idrgGroup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
            </details>
        </div>
    @endif
</div>
