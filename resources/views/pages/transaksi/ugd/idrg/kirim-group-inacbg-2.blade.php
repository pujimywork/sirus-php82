<?php
// resources/views/pages/transaksi/ugd/idrg/kirim-group-inacbg-2.blade.php
// Step 12: Grouping INACBG Stage 2 — pilih special_cmg dari special_cmg_option (Manual hal. 34-36).
// Tampil hanya kalau stage 1 punya special_cmg_option.

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
    public array $stage1 = [];
    public array $stage2 = [];
    public array $specialCmgOptions = [];
    public array $selectedCmg = []; // array of code strings yang dipilih coder

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
        $this->stage1 = $idrg['inacbgStage1'] ?? [];
        $this->stage2 = $idrg['inacbgStage2'] ?? [];
        $this->specialCmgOptions = data_get($this->stage1, 'special_cmg_option') ?? data_get($this->stage1, 'response_inacbg.special_cmg_option') ?? [];
        $saved = $idrg['inacbgSpecialCmgInput'] ?? '';
        $this->selectedCmg = !empty($saved) ? explode('#', $saved) : [];
    }

    public function toggleCmg(string $code): void
    {
        if (in_array($code, $this->selectedCmg, true)) {
            $this->selectedCmg = array_values(array_filter($this->selectedCmg, fn($c) => $c !== $code));
        } else {
            $this->selectedCmg[] = $code;
        }
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

            $specialCmg = implode('#', $this->selectedCmg);
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
                class="flex items-center justify-center w-8 h-8 rounded-full {{ !empty($stage2) ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500' }}">
                <span class="text-sm font-bold">13</span>
            </div>
            <div>
                <div class="font-semibold text-gray-800 dark:text-gray-100">Grouping INACBG Stage 2 — Special CMG</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    Hanya jika stage 1 menampilkan special_cmg_option (implant, prosthesis, dll).
                </div>
            </div>
        </div>
        <x-primary-button type="button" wire:click="group" wire:loading.attr="disabled"
            :disabled="!$idrgFinal || $inacbgFinal || empty($stage1)"
            class="!bg-brand hover:!bg-brand/90 {{ !empty($stage2) ? '!bg-emerald-600' : '' }}">
            <span wire:loading.remove wire:target="group">{{ !empty($stage2) ? 'Group Ulang' : 'Jalankan' }}</span>
            <span wire:loading wire:target="group"><x-loading />...</span>
        </x-primary-button>
    </div>

    @if (!empty($specialCmgOptions))
        <fieldset class="p-3 border border-gray-200 rounded-lg dark:border-gray-700" @disabled($inacbgFinal)>
            <legend class="px-2 text-xs font-semibold tracking-wide text-gray-600 uppercase dark:text-gray-400">
                Pilih Special CMG (multi-select)
            </legend>
            <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                @foreach ($specialCmgOptions as $opt)
                    @php
                        $code = $opt['code'] ?? '';
                        $checked = in_array($code, $selectedCmg, true);
                    @endphp
                    <label
                        class="flex items-start gap-2 p-2 text-xs rounded cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800 {{ $checked ? 'bg-emerald-50 dark:bg-emerald-900/20' : '' }}">
                        <input type="checkbox" value="{{ $code }}"
                            wire:click="toggleCmg('{{ $code }}')" @checked($checked)
                            @disabled($inacbgFinal)
                            class="mt-0.5 rounded border-gray-300 text-brand focus:ring-brand dark:bg-gray-800 dark:border-gray-700">
                        <div>
                            <div class="font-mono font-semibold text-gray-800 dark:text-gray-100">{{ $code }}</div>
                            <div class="text-gray-600 dark:text-gray-400">{{ $opt['description'] ?? '-' }}</div>
                            @if (!empty($opt['type']))
                                <div class="text-gray-400">Type: {{ $opt['type'] }}</div>
                            @endif
                        </div>
                    </label>
                @endforeach
            </div>
        </fieldset>
    @elseif (!empty($stage1))
        <p class="px-2 py-2 text-xs text-center text-gray-400 dark:text-gray-500">
            Tidak ada special_cmg_option dari stage 1 — boleh skip stage 2 atau tetap jalankan dengan input kosong.
        </p>
    @endif

    @if (!empty($stage2))
        @php
            $cbg2 = data_get($stage2, 'cbg') ?? data_get($stage2, 'response_inacbg.cbg') ?? [];
            $tarif2 = data_get($stage2, 'tariff') ?? data_get($stage2, 'response_inacbg.tariff') ?? [];
        @endphp
        <div class="px-3 py-2 text-xs rounded-lg bg-gray-50 dark:bg-gray-800">
            <div class="grid grid-cols-2 gap-3 md:grid-cols-3">
                <div>
                    <div class="text-gray-500">CBG (Stage 2)</div>
                    <div class="font-mono font-semibold text-gray-800 dark:text-gray-100">{{ $cbg2['code'] ?? '-' }}</div>
                </div>
                <div class="md:col-span-2">
                    <div class="text-gray-500">Deskripsi</div>
                    <div class="text-gray-700 dark:text-gray-300">{{ $cbg2['description'] ?? '-' }}</div>
                </div>
                <div>
                    <div class="text-gray-500">Tarif Total</div>
                    <div class="font-mono font-semibold text-gray-800 dark:text-gray-100">
                        Rp {{ number_format((int) ($tarif2['total'] ?? 0), 0, ',', '.') }}
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
