<?php
// resources/views/pages/transaksi/ri/idrg/kirim-group-idrg-2.blade.php
// Step 7: Grouping iDRG Stage 2 — pilih topup_codes dari topup_options (Manual 5.10.x hal. 30-31).
// Tampil hanya kalau stage 1 punya topup_options.

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\iDRG\iDrgTrait;

new class extends Component {
    use EmrRITrait, iDrgTrait;

    public ?string $riHdrNo = null;
    public bool $idrgFinal = false;
    public array $stage1 = [];
    public array $stage2 = [];
    public array $topupOptions = [];
    public array $selectedTopup = [];

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
        $this->stage1 = $idrg['idrgGroup'] ?? [];
        $this->stage2 = $idrg['idrgStage2'] ?? [];
        $this->topupOptions = $this->stage1['topup_options'] ?? [];
        $saved = $idrg['idrgTopupCodesInput'] ?? '';
        $this->selectedTopup = !empty($saved) ? explode('#', $saved) : [];
    }

    public function toggleTopup(string $code): void
    {
        if (in_array($code, $this->selectedTopup, true)) {
            $this->selectedTopup = array_values(array_filter($this->selectedTopup, fn($c) => $c !== $code));
        } else {
            $this->selectedTopup[] = $code;
        }
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

            $topupCodes = implode('#', $this->selectedTopup);
            $res = $this->grouperIdrgStage2($nomorSep, $topupCodes)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Grouping iDRG Stage 2'));
                return;
            }

            $idrg['idrgStage2'] = $res['response_idrg'] ?? ($res['response'] ?? []);
            $idrg['idrgTopupCodesInput'] = $topupCodes;
            $idrg['idrgFinal'] = false;
            $this->saveResult($idrg);
            $this->dispatch('toast', type: 'success', message: 'iDRG stage 2 selesai.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Grouping iDRG stage 2 gagal: ' . $e->getMessage());
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
                class="flex items-center justify-center w-8 h-8 rounded-full {{ !empty($stage2) ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500' }}">
                <span class="text-sm font-bold">7</span>
            </div>
            <div>
                <div class="font-semibold text-gray-800 dark:text-gray-100">Grouping iDRG Stage 2 — Topup</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    Hanya jika stage 1 menampilkan topup_options (prosthesis, implant, dll).
                </div>
            </div>
        </div>
        <x-primary-button type="button" wire:click="group" wire:loading.attr="disabled"
            :disabled="$idrgFinal || empty($stage1)"
            class="!bg-brand hover:!bg-brand/90 {{ !empty($stage2) ? '!bg-emerald-600' : '' }}">
            <span wire:loading.remove wire:target="group">{{ !empty($stage2) ? 'Group Ulang' : 'Jalankan' }}</span>
            <span wire:loading wire:target="group"><x-loading />...</span>
        </x-primary-button>
    </div>

    @if (!empty($topupOptions))
        <fieldset class="p-3 border border-gray-200 rounded-lg dark:border-gray-700" @disabled($idrgFinal)>
            <legend class="px-2 text-xs font-semibold tracking-wide text-gray-600 uppercase dark:text-gray-400">
                Pilih Topup (multi-select)
            </legend>
            <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                @foreach ($topupOptions as $opt)
                    @php
                        $code = $opt['code'] ?? '';
                        $checked = in_array($code, $selectedTopup, true);
                    @endphp
                    <label
                        class="flex items-start gap-2 p-2 text-xs rounded cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800 {{ $checked ? 'bg-emerald-50 dark:bg-emerald-900/20' : '' }}">
                        <input type="checkbox" value="{{ $code }}"
                            wire:click="toggleTopup('{{ $code }}')" @checked($checked)
                            @disabled($idrgFinal)
                            class="mt-0.5 rounded border-gray-300 text-brand focus:ring-brand dark:bg-gray-800 dark:border-gray-700">
                        <div>
                            <div class="font-mono font-semibold text-gray-800 dark:text-gray-100">{{ $code }}</div>
                            <div class="text-gray-600 dark:text-gray-400">{{ $opt['description'] ?? '-' }}</div>
                            @if (!empty($opt['type']))
                                <div class="text-gray-400">Type: {{ $opt['type'] }}</div>
                            @endif
                            @if (!empty($opt['cost_weight']))
                                <div class="font-mono text-gray-400">CW: {{ $opt['cost_weight'] }}</div>
                            @endif
                        </div>
                    </label>
                @endforeach
            </div>
        </fieldset>
    @elseif (!empty($stage1))
        <p class="px-2 py-2 text-xs text-center text-gray-400 dark:text-gray-500">
            Tidak ada topup_options dari stage 1 — boleh skip stage 2, langsung Final iDRG.
        </p>
    @endif

    @if (!empty($stage2))
        <div class="px-3 py-2 text-xs rounded-lg bg-gray-50 dark:bg-gray-800">
            <div class="grid grid-cols-2 gap-3 md:grid-cols-3">
                <div>
                    <div class="text-gray-500">DRG (Stage 2)</div>
                    <div class="font-mono font-semibold text-gray-800 dark:text-gray-100">
                        {{ $stage2['drg_code'] ?? '-' }}
                    </div>
                </div>
                <div class="md:col-span-2">
                    <div class="text-gray-500">Deskripsi</div>
                    <div class="text-gray-700 dark:text-gray-300">{{ $stage2['drg_description'] ?? '-' }}</div>
                </div>
                <div>
                    <div class="text-gray-500">Total Cost Weight</div>
                    <div class="font-mono font-semibold text-gray-800 dark:text-gray-100">
                        {{ $stage2['total_cost_weight'] ?? ($stage2['cost_weight'] ?? '-') }}
                    </div>
                </div>
                @if (!empty($stage2['topup']))
                    <div class="md:col-span-3">
                        <div class="text-gray-500">Topup dipakai</div>
                        <ul class="text-gray-700 dark:text-gray-300">
                            @foreach ($stage2['topup'] as $tp)
                                <li class="font-mono">
                                    {{ $tp['code'] ?? '-' }} — {{ $tp['description'] ?? '' }}
                                    @if (!empty($tp['cost_weight']))
                                        (CW {{ $tp['cost_weight'] }})
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
