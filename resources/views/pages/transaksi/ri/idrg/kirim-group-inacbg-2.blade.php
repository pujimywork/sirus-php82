<?php
// resources/views/pages/transaksi/ri/idrg/kirim-group-inacbg-2.blade.php
// Step 12: Grouping INACBG Stage 2 — pilih special_cmg dari special_cmg_option (Manual hal. 34-36).
// Tampil hanya kalau stage 1 punya special_cmg_option.

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\iDRG\iDrgTrait;

new class extends Component {
    use EmrRITrait, iDrgTrait;

    public ?string $riHdrNo = null;
    public bool $idrgFinal = false;
    public bool $inacbgFinal = false;
    public array $stage1 = [];
    public array $stage2 = [];
    public array $specialCmgOptions = [];
    // selectedCmg sekarang assoc: [type_slug => code]. Mirror e-klaim — satu pilihan per kategori
    // (Special Procedure / Prosthesis / Investigation / Drug).
    public array $selectedCmg = [];

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
        $this->inacbgFinal = !empty($idrg['inacbgFinal']);
        $this->stage1 = $idrg['inacbgStage1'] ?? [];
        $this->stage2 = $idrg['inacbgStage2'] ?? [];
        $this->specialCmgOptions = data_get($this->stage1, 'special_cmg_option') ?? data_get($this->stage1, 'response_inacbg.special_cmg_option') ?? [];

        // Restore selectedCmg dari string code yang tersimpan — lookup type via specialCmgOptions
        $saved = $idrg['inacbgSpecialCmgInput'] ?? '';
        $savedCodes = !empty($saved) ? array_filter(explode('#', $saved)) : [];
        $this->selectedCmg = [];
        foreach ($savedCodes as $code) {
            foreach ($this->specialCmgOptions as $opt) {
                if (($opt['code'] ?? '') === $code) {
                    $slug = self::slugType((string) ($opt['type'] ?? 'default'));
                    $this->selectedCmg[$slug] = $code;
                    break;
                }
            }
        }
    }

    /** Slug "Special Procedure" → "special_procedure" supaya valid sebagai wire:model path key. */
    public static function slugType(string $type): string
    {
        $s = strtolower(trim($type));
        $s = preg_replace('/[^a-z0-9]+/', '_', $s);
        return trim($s, '_') ?: 'default';
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

            // Kumpulkan code dari selectedCmg, skip yang kosong (= "None" dipilih).
            $codes = array_values(array_filter($this->selectedCmg, fn($c) => !empty($c)));
            $specialCmg = implode('#', $codes);
            $res = $this->grouperInacbgStage2($nomorSep, $specialCmg)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Grouping INACBG Stage 2'));
                return;
            }

            $idrg['inacbgStage2'] = $res['response'] ?? [];
            $idrg['inacbgSpecialCmgInput'] = $specialCmg;
            $this->saveResult($idrg);
            $this->dispatch('toast', type: 'success', message: 'INACBG stage 2 selesai.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Grouping INACBG stage 2 gagal: ' . $e->getMessage());
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

<div class="p-4 space-y-3 bg-canvas border border-hairline shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700">
    <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div
                class="flex items-center justify-center w-8 h-8 rounded-full {{ !empty($stage2) ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-surface-soft text-muted-soft dark:bg-gray-800 dark:text-gray-500' }}">
                <span class="text-sm font-bold">13</span>
            </div>
            <div>
                <div class="font-semibold text-ink dark:text-gray-100">Grouping INACBG Stage 2 — Special CMG</div>
                <div class="text-sm text-muted dark:text-gray-400">
                    Hanya jika stage 1 menampilkan special_cmg_option (implant, prosthesis, dll).
                </div>
            </div>
        </div>
        <x-primary-button type="button" wire:click="group" wire:loading.attr="disabled"
            :disabled="!$idrgFinal || $inacbgFinal || empty($stage1)"
            class="!bg-brand hover:!bg-brand/90 min-w-[240px] {{ !empty($stage2) ? '!bg-emerald-600' : '' }}">
            <span wire:loading.remove wire:target="group">{{ !empty($stage2) ? 'Grouping Ulang INACBG Stage 2' : 'Grouping INACBG Stage 2' }}</span>
            <span wire:loading wire:target="group"><x-loading />...</span>
        </x-primary-button>
    </div>

    @if (!empty($specialCmgOptions))
        @php
            // Group special_cmg_options by `type` (Special Procedure / Prosthesis / Investigation / Drug).
            $byType = [];
            foreach ($specialCmgOptions as $opt) {
                $typeLabel = (string) ($opt['type'] ?? 'Special CMG');
                $slug = $this::slugType($typeLabel);
                $byType[$slug] ??= ['label' => $typeLabel, 'options' => []];
                $byType[$slug]['options'][] = $opt;
            }
        @endphp
        <fieldset class="p-3 border border-hairline rounded-lg dark:border-gray-700" @disabled($inacbgFinal)>
            <legend class="px-2 text-sm font-semibold tracking-wide text-muted uppercase dark:text-gray-400">
                Special CMG (single-select per kategori)
            </legend>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                @foreach ($byType as $slug => $group)
                    <div>
                        <x-input-label :value="$group['label']" class="text-sm" />
                        <x-select-input wire:model="selectedCmg.{{ $slug }}" class="w-full mt-1 text-sm"
                            :disabled="$inacbgFinal">
                            <option value="">— None —</option>
                            @foreach ($group['options'] as $opt)
                                @php
                                    $code = $opt['code'] ?? '';
                                    $desc = $opt['description'] ?? '-';
                                @endphp
                                <option value="{{ $code }}">{{ $code }} — {{ $desc }}</option>
                            @endforeach
                        </x-select-input>
                    </div>
                @endforeach
            </div>
        </fieldset>
    @elseif (!empty($stage1))
        <p class="px-2 py-2 text-sm text-center text-muted-soft dark:text-gray-500">
            Tidak ada special_cmg_option dari stage 1 — boleh skip stage 2 atau tetap jalankan dengan input kosong.
        </p>
    @endif

    @if (!empty($stage2))
        @php
            $resolve2 = fn($key) => data_get($stage2, $key) ?? data_get($stage2, "response_inacbg.{$key}");
            $cbg2 = $resolve2('cbg') ?? [];
            $cbg2Code = (string) ($cbg2['code'] ?? '-');
            $cbg2Desc = (string) ($cbg2['description'] ?? '-');
            $costWeight2 = (string) ($cbg2['cost_weight'] ?? '-');
            $tariff2Raw = $resolve2('tariff');
            $tariffTotal2 = is_numeric($tariff2Raw)
                ? (int) $tariff2Raw
                : (int) (data_get($tariff2Raw, 'total') ?? $resolve2('total_tariff') ?? 0);
            $baseRate2 = (int) ($resolve2('base_rate') ?? $resolve2('nbr') ?? 0);
            if ($tariffTotal2 === 0 && is_numeric($costWeight2) && $baseRate2 > 0) {
                $tariffTotal2 = (int) round($baseRate2 * (float) $costWeight2);
            }
            $hasBintang2 = !$inacbgFinal;
        @endphp
        <div class="overflow-hidden text-sm border border-emerald-200 rounded-lg dark:border-emerald-800">
            <div class="px-3 py-2 font-semibold text-center bg-emerald-50 dark:bg-emerald-900/20 text-success dark:text-success">
                Hasil Grouping INACBG Stage 2{{ $inacbgFinal ? ' — Final' : '' }}
            </div>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-hairline-soft dark:divide-gray-800">
                    <tr>
                        <td class="w-32 px-3 py-1.5 text-right text-muted align-top">CBG (Stage 2)</td>
                        <td class="px-3 py-1.5 text-body dark:text-gray-300">{{ $cbg2Desc }}</td>
                        <td class="px-3 py-1.5 font-mono font-semibold text-ink dark:text-gray-100 whitespace-nowrap">{{ $cbg2Code }}</td>
                        <td class="px-3 py-1.5 text-right text-muted whitespace-nowrap">CW: <span class="font-mono font-semibold text-ink dark:text-gray-100">{{ $hasBintang2 ? '**' : '' }} {{ $costWeight2 }}</span></td>
                    </tr>
                    @if ($baseRate2 > 0)
                        <tr>
                            <td class="px-3 py-1.5 text-right text-muted">Base Rate</td>
                            <td class="px-3 py-1.5 font-mono text-body dark:text-gray-300" colspan="3">
                                {{ $hasBintang2 ? '**' : '' }} Rp {{ number_format($baseRate2, 0, ',', '.') }}
                            </td>
                        </tr>
                    @endif
                    <tr class="bg-emerald-50/50 dark:bg-emerald-900/10">
                        <td class="px-3 py-2 font-semibold text-right text-muted">Tarif Total</td>
                        <td class="px-3 py-2 text-right font-mono font-bold text-ink dark:text-white text-sm" colspan="3">
                            {{ $hasBintang2 ? '**' : '' }} Rp {{ number_format($tariffTotal2, 0, ',', '.') }}
                        </td>
                    </tr>
                </tbody>
            </table>
            @if ($hasBintang2)
                <div class="px-3 py-2 text-sm italic border-t border-amber-200 text-amber-700 bg-amber-50 dark:text-amber-400 dark:bg-amber-900/20 dark:border-amber-800">
                    ** ) Catatan: Nilai belum final, sewaktu-waktu bisa berubah.
                </div>
            @endif
            <details class="px-3 py-1 text-sm border-t border-hairline dark:border-gray-700">
                <summary class="text-muted cursor-pointer hover:text-body dark:hover:text-gray-300">[debug] raw response</summary>
                <pre class="p-2 mt-1 overflow-x-auto text-[10px] leading-tight bg-surface-soft rounded dark:bg-gray-900">{{ json_encode($stage2, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
            </details>
        </div>
    @endif
</div>
