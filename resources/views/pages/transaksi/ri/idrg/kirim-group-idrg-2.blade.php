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
    // selectedTopup sekarang assoc: [type_slug => code]. Satu pilihan per kategori (mirror e-klaim).
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

        // Restore selectedTopup dari string code yang tersimpan — lookup type via topupOptions
        $saved = $idrg['idrgTopupCodesInput'] ?? '';
        $savedCodes = !empty($saved) ? array_filter(explode('#', $saved)) : [];
        $this->selectedTopup = [];
        foreach ($savedCodes as $code) {
            foreach ($this->topupOptions as $opt) {
                if (($opt['code'] ?? '') === $code) {
                    $slug = self::slugType((string) ($opt['type'] ?? 'default'));
                    $this->selectedTopup[$slug] = $code;
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

            // Kumpulkan code dari selectedTopup, skip yang kosong (= "None" dipilih).
            $codes = array_values(array_filter($this->selectedTopup, fn($c) => !empty($c)));
            $topupCodes = implode('#', $codes);
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

<div class="p-4 space-y-3 bg-canvas border border-hairline shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700">
    <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div
                class="flex items-center justify-center w-8 h-8 rounded-full {{ !empty($stage2) ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-surface-soft text-muted-soft dark:bg-gray-800 dark:text-gray-500' }}">
                <span class="text-sm font-bold">7</span>
            </div>
            <div>
                <div class="font-semibold text-ink dark:text-gray-100">Grouping iDRG Stage 2 — Topup</div>
                <div class="text-sm text-muted dark:text-gray-400">
                    Hanya jika stage 1 menampilkan topup_options (prosthesis, implant, dll).
                </div>
            </div>
        </div>
        <x-primary-button type="button" wire:click="group" wire:loading.attr="disabled"
            :disabled="$idrgFinal || empty($stage1)"
            class="!bg-brand hover:!bg-brand/90 min-w-[220px] {{ !empty($stage2) ? '!bg-emerald-600' : '' }}">
            <span wire:loading.remove wire:target="group">{{ !empty($stage2) ? 'Grouping Ulang iDRG Stage 2' : 'Grouping iDRG Stage 2' }}</span>
            <span wire:loading wire:target="group"><x-loading />...</span>
        </x-primary-button>
    </div>

    @if (!empty($topupOptions))
        @php
            // Group topup_options by `type` field — render satu dropdown per kategori (e-klaim style).
            // Kalau API tidak kasih type, semua masuk grup 'default' → satu dropdown gabungan.
            $byType = [];
            foreach ($topupOptions as $opt) {
                $typeLabel = (string) ($opt['type'] ?? 'Topup');
                $slug = $this::slugType($typeLabel);
                $byType[$slug] ??= ['label' => $typeLabel, 'options' => []];
                $byType[$slug]['options'][] = $opt;
            }
        @endphp
        <fieldset class="p-3 border border-hairline rounded-lg dark:border-gray-700" @disabled($idrgFinal)>
            <legend class="px-2 text-sm font-semibold tracking-wide text-muted uppercase dark:text-gray-400">
                Pilih Topup (single-select per kategori)
            </legend>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                @foreach ($byType as $slug => $group)
                    <div>
                        <x-input-label :value="$group['label']" class="text-sm" />
                        <x-select-input wire:model="selectedTopup.{{ $slug }}" class="w-full mt-1 text-sm"
                            :disabled="$idrgFinal">
                            <option value="">— None —</option>
                            @foreach ($group['options'] as $opt)
                                @php
                                    $code = $opt['code'] ?? '';
                                    $desc = $opt['description'] ?? '-';
                                    $cw = $opt['cost_weight'] ?? null;
                                @endphp
                                <option value="{{ $code }}">
                                    {{ $code }} — {{ $desc }}{{ $cw ? ' (CW ' . $cw . ')' : '' }}
                                </option>
                            @endforeach
                        </x-select-input>
                    </div>
                @endforeach
            </div>
        </fieldset>
    @elseif (!empty($stage1))
        <p class="px-2 py-2 text-sm text-center text-muted-soft dark:text-gray-500">
            Tidak ada topup_options dari stage 1 — boleh skip stage 2, langsung Final iDRG.
        </p>
    @endif

    @if (!empty($stage2))
        <div class="px-3 py-2 text-sm rounded-lg bg-surface-soft dark:bg-gray-800">
            <div class="grid grid-cols-2 gap-3 md:grid-cols-3">
                <div>
                    <div class="text-muted">DRG (Stage 2)</div>
                    <div class="font-mono font-semibold text-ink dark:text-gray-100">
                        {{ $stage2['drg_code'] ?? '-' }}
                    </div>
                </div>
                <div class="md:col-span-2">
                    <div class="text-muted">Deskripsi</div>
                    <div class="text-body dark:text-gray-300">{{ $stage2['drg_description'] ?? '-' }}</div>
                </div>
                <div>
                    <div class="text-muted">Total Cost Weight</div>
                    <div class="font-mono font-semibold text-ink dark:text-gray-100">
                        {{ $stage2['total_cost_weight'] ?? ($stage2['cost_weight'] ?? '-') }}
                    </div>
                </div>
                @if (!empty($stage2['topup']))
                    <div class="md:col-span-3">
                        <div class="text-muted">Topup dipakai</div>
                        <ul class="text-body dark:text-gray-300">
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
