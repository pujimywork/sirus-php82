<?php
// resources/views/pages/transaksi/ugd/idrg/kirim-prosedur-inacbg.blade.php
// Step 10: Coder Editor + Set Prosedur INACBG.
// Override koder casemix kalau ada kode iDRG yang IM-only tidak berlaku di INACBG.

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\iDRG\iDrgTrait;

new class extends Component {
    use EmrUGDTrait, iDrgTrait;

    public ?string $rjNo = null;

    // State (mirror dari $idrg di JSON DB).
    public array $coderInacbgProsedur = [];
    public ?string $inacbgProsedurString = null;
    public bool $inacbgFinal = false;
    public bool $idrgFinal = false;
    public bool $hasClaim = false;

    /* ===============================
     | LIFECYCLE
     =============================== */
    public function mount(?string $rjNo = null): void
    {
        $this->rjNo = $rjNo;
        $this->reloadState();
    }


    private function reloadState(): void
    {
        if (empty($this->rjNo)) {
            $this->coderInacbgProsedur = [];
            $this->inacbgProsedurString = null;
            $this->inacbgFinal = false;
            $this->idrgFinal = false;
            $this->hasClaim = false;
            return;
        }
        $data = $this->findDataUGD($this->rjNo);
        if (empty($data)) {
            return;
        }
        $idrg = $data['idrg'] ?? [];

        // Auto-sync pertama kali: prefer dari idrg.coderProsedur, fallback EMR.
        $sourceCount = count($idrg['coderProsedur'] ?? []);
        $emrCount = count($data['procedure'] ?? []);
        if (empty($idrg['coderInacbgProsedurSyncedAt']) && empty($idrg['coderInacbgProsedur']) && ($sourceCount > 0 || $emrCount > 0)) {
            $this->persistAutoSync();
            $data = $this->findDataUGD($this->rjNo);
            $idrg = $data['idrg'] ?? [];
        }

        $this->coderInacbgProsedur = $idrg['coderInacbgProsedur'] ?? [];
        $this->inacbgProsedurString = $idrg['inacbgProsedurString'] ?? null;
        $this->inacbgFinal = !empty($idrg['inacbgFinal']);
        $this->idrgFinal = !empty($idrg['idrgFinal']);
        $this->hasClaim = !empty($idrg['nomorSep']);
    }

    /**
     * Persist auto-sync: prefer idrg.coderProsedur (iDRG editor), fallback EMR procedure[].
     */
    private function persistAutoSync(): void
    {
        DB::transaction(function () {
            $this->lockUGDRow($this->rjNo);
            $fresh = $this->findDataUGD($this->rjNo);
            $idrg = $fresh['idrg'] ?? [];
            $sourceFromIdrg = $idrg['coderProsedur'] ?? [];
            $coder = [];
            if (!empty($sourceFromIdrg)) {
                foreach ($sourceFromIdrg as $p) {
                    $code = trim((string) ($p['code'] ?? ''));
                    if ($code === '') {
                        continue;
                    }
                    $coder[] = [
                        'code' => $code,
                        'desc' => (string) ($p['desc'] ?? ''),
                        'multiplicity' => max(1, (int) ($p['multiplicity'] ?? 1)),
                        'settingGroup' => max(1, (int) ($p['settingGroup'] ?? 1)),
                        'validcode' => null,
                    ];
                }
            } else {
                foreach (($fresh['procedure'] ?? []) as $p) {
                    $code = trim((string) ($p['procedureId'] ?? ''));
                    if ($code === '') {
                        continue;
                    }
                    $coder[] = [
                        'code' => $code,
                        'desc' => (string) ($p['procedureDesc'] ?? ''),
                        'multiplicity' => max(1, (int) ($p['multiplicity'] ?? 1)),
                        'settingGroup' => max(1, (int) ($p['settingGroup'] ?? 1)),
                        'validcode' => null,
                    ];
                }
            }
            $idrg['coderInacbgProsedur'] = $coder;
            $idrg['coderInacbgProsedurSyncedAt'] = Carbon::now()->format('Y-m-d H:i:s');
            $fresh['idrg'] = $idrg;
            $this->updateJsonUGD($this->rjNo, $fresh);
        });
    }

    /* ===============================
     | EDITOR (CODER) — CRUD
     =============================== */

    #[On('lov.selected.ugdFormProsedurInacbgCoder')]
    public function onLov(string $target, array $payload): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $code = trim((string) ($payload['icd9'] ?? $payload['proc_id'] ?? $payload['icdx'] ?? ''));
        $desc = (string) ($payload['proc_desc'] ?? $payload['description'] ?? $payload['icd9_desc'] ?? '');
        if ($code === '') {
            $this->dispatch('toast', type: 'error', message: 'Kode prosedur tidak valid.');
            return;
        }
        $this->add($code, $desc);
    }

    public function add(string $code, string $desc, int $multiplicity = 1, int $settingGroup = 1): void
    {
        if (empty($this->rjNo) || empty($code)) {
            return;
        }
        $multiplicity = max(1, $multiplicity);
        $settingGroup = max(1, $settingGroup);
        $this->mutate(function ($coder) use ($code, $desc, $multiplicity, $settingGroup) {
            $coder[] = [
                'code' => $code,
                'desc' => $desc,
                'multiplicity' => $multiplicity,
                'settingGroup' => $settingGroup,
                'validcode' => null,
            ];
            return $coder;
        });
    }

    public function remove(int $index): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $this->mutate(function ($coder) use ($index) {
            if (isset($coder[$index])) {
                unset($coder[$index]);
            }
            return array_values($coder);
        });
    }

    public function setMultiplicity(int $index, int $value): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $value = max(1, $value);
        $this->mutate(function ($coder) use ($index, $value) {
            if (isset($coder[$index])) {
                $coder[$index]['multiplicity'] = $value;
            }
            return $coder;
        });
    }

    public function setSettingGroup(int $index, int $value): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $value = max(1, $value);
        $this->mutate(function ($coder) use ($index, $value) {
            if (isset($coder[$index])) {
                $coder[$index]['settingGroup'] = $value;
            }
            return $coder;
        });
    }

    /**
     * Sync dari iDRG coder editor (idrg.coderProsedur) sebagai starting point.
     * Fallback ke EMR procedure[] kalau iDRG coder belum diisi.
     */
    public function syncFromIdrg(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $this->persistAutoSync();
        $this->reloadState();
        $this->dispatch('toast', type: 'success', message: 'Coder INACBG prosedur di-sync dari iDRG.');
        $this->dispatch('idrg-section-changed-ugd', rjNo: (string) $this->rjNo);
    }

    private function mutate(callable $fn): void
    {
        DB::transaction(function () use ($fn) {
            $this->lockUGDRow($this->rjNo);
            $data = $this->findDataUGD($this->rjNo);
            $coder = $data['idrg']['coderInacbgProsedur'] ?? [];
            $coder = $fn($coder);
            $data['idrg']['coderInacbgProsedur'] = $coder;
            $this->updateJsonUGD($this->rjNo, $data);
        });
        $this->reloadState();
        $this->dispatch('idrg-section-changed-ugd', rjNo: (string) $this->rjNo);
    }

    /* ===============================
     | API ACTION — set_prosedur_inacbg
     =============================== */

    #[On('idrg-prosedur-inacbg-ugd.set')]
    public function set(string $rjNo, ?string $procedure = null): void
    {
        try {
            $data = $this->findDataUGD($rjNo);
            if (empty($data)) {
                throw new \RuntimeException('Data RJ tidak ditemukan.');
            }
            $idrg = $data['idrg'] ?? [];
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) {
                $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.');
                return;
            }

            if ($procedure === null || $procedure === '') {
                $coder = $idrg['coderInacbgProsedur'] ?? [];
                $procedure = !empty($coder) ? $this->buildString($coder) : '#';
            }

            $res = $this->setProsedurInacbg($nomorSep, $procedure)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Set Prosedur INACBG'));
                return;
            }

            $response = $res['response'] ?? [];
            $idrg['inacbgProsedur'] = $response;
            $idrg['inacbgProsedurString'] = $procedure;

            // Update validcode
            $expanded = $response['expanded'] ?? $response['data']['expanded'] ?? [];
            if (!empty($idrg['coderInacbgProsedur']) && !empty($expanded)) {
                $byCode = collect($expanded)->keyBy('code');
                foreach ($idrg['coderInacbgProsedur'] as &$c) {
                    $code = $c['code'] ?? '';
                    if (isset($byCode[$code])) {
                        $c['validcode'] = (string) ($byCode[$code]['validcode'] ?? '');
                    }
                }
                unset($c);
            }

            $this->saveResult($rjNo, $idrg);
            $this->dispatch('toast', type: 'success', message: "Prosedur INACBG tersimpan: {$procedure}");
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Set prosedur INACBG gagal: ' . $e->getMessage());
        }
    }

    public function setForCurrent(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $this->set($this->rjNo);
    }

    private function buildString(array $coder): string
    {
        if (empty($coder)) {
            return '';
        }
        $groups = [];
        foreach ($coder as $c) {
            $code = trim((string) ($c['code'] ?? ''));
            if ($code === '') {
                continue;
            }
            $mult = max(1, (int) ($c['multiplicity'] ?? 1));
            $groupKey = max(1, (int) ($c['settingGroup'] ?? 1));
            $token = $mult > 1 ? "{$code}+{$mult}" : $code;
            $groups[$groupKey][] = $token;
        }
        ksort($groups);
        $parts = [];
        foreach ($groups as $tokens) {
            $parts[] = implode('#', $tokens);
        }
        return implode('#', $parts);
    }

    private function saveResult(string $rjNo, array $idrg): void
    {
        DB::transaction(function () use ($rjNo, $idrg) {
            $this->lockUGDRow($rjNo);
            $data = $this->findDataUGD($rjNo);
            $data['idrg'] = $idrg;
            $this->updateJsonUGD($rjNo, $data);
        });

        $this->dispatch('idrg-section-changed-ugd', rjNo: (string) $rjNo);
    }
};
?>

<div class="p-4 space-y-3 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700">
    {{-- Header --}}
    <div class="flex items-start justify-between gap-3">
        <div class="flex items-center gap-3">
            <div
                class="flex items-center justify-center w-8 h-8 rounded-full {{ !empty($inacbgProsedurString) ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500' }}">
                <span class="text-sm font-bold">11</span>
            </div>
            <div>
                <div class="font-semibold text-gray-800 dark:text-gray-100">Set Prosedur INACBG</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    Override jika ada kode iDRG dengan "IM tidak berlaku" di INACBG.
                </div>
            </div>
        </div>
        <div class="flex flex-wrap items-center justify-end gap-2 shrink-0">
            <button type="button" wire:click="syncFromIdrg" wire:loading.attr="disabled" @disabled($inacbgFinal)
                class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 disabled:opacity-50 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                <span wire:loading.remove wire:target="syncFromIdrg">↻ Sync dari iDRG</span>
                <span wire:loading wire:target="syncFromIdrg"><x-loading />...</span>
            </button>
            <x-primary-button type="button" wire:click="setForCurrent" wire:loading.attr="disabled"
                :disabled="$inacbgFinal || !$hasClaim"
                class="!bg-brand hover:!bg-brand/90 min-w-[160px] {{ !empty($inacbgProsedurString) ? '!bg-emerald-600' : '' }}">
                <span wire:loading.remove wire:target="setForCurrent">
                    {{ !empty($inacbgProsedurString) ? 'Set Ulang' : 'Set Prosedur INACBG' }}
                </span>
                <span wire:loading wire:target="setForCurrent"><x-loading />...</span>
            </x-primary-button>
        </div>
    </div>

    @if (!$inacbgFinal)
        <div wire:key="lov-procedure-inacbg-coder-{{ $rjNo ?? 'none' }}">
            <livewire:lov.procedure.lov-procedure label="Cari Prosedur (untuk INACBG)"
                target="ugdFormProsedurInacbgCoder"
                wire:key="lov-procedure-inacbg-coder-inner-{{ $rjNo ?? 'none' }}" />
        </div>
    @endif

    @if (!empty($coderInacbgProsedur))
        <div class="overflow-x-auto border border-gray-200 rounded-lg dark:border-gray-700">
            <table class="w-full text-xs text-left">
                <thead class="text-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-300">
                    <tr>
                        <th class="px-2 py-1.5 font-medium">Kode</th>
                        <th class="px-2 py-1.5 font-medium">Deskripsi</th>
                        <th class="px-2 py-1.5 font-medium text-center" title="Multiplicity: berapa kali dalam 1 operasi (+N)">Mult.</th>
                        <th class="px-2 py-1.5 font-medium text-center" title="Setting: operasi ke-berapa (1, 2, 3...)">Setting</th>
                        <th class="px-2 py-1.5 font-medium text-center">Valid IM</th>
                        @if (!$inacbgFinal)
                            <th class="px-2 py-1.5 w-8"></th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($coderInacbgProsedur as $i => $p)
                        <tr wire:key="coder-inacbg-proc-{{ $i }}-{{ $p['code'] ?? 'x' }}"
                            class="bg-white dark:bg-gray-900">
                            <td class="px-2 py-1.5 font-mono font-semibold text-gray-800 dark:text-gray-100">
                                {{ $p['code'] ?? '' }}</td>
                            <td class="px-2 py-1.5 text-gray-700 dark:text-gray-300">{{ $p['desc'] ?? '' }}</td>
                            <td class="px-2 py-1.5 text-center">
                                <x-text-input type="number" min="1" value="{{ $p['multiplicity'] ?? 1 }}"
                                    wire:change="setMultiplicity({{ $i }}, $event.target.value)"
                                    :disabled="$inacbgFinal" class="w-16 text-xs text-center" />
                            </td>
                            <td class="px-2 py-1.5 text-center">
                                <x-text-input type="number" min="1" value="{{ $p['settingGroup'] ?? 1 }}"
                                    wire:change="setSettingGroup({{ $i }}, $event.target.value)"
                                    :disabled="$inacbgFinal" class="w-16 text-xs text-center" />
                            </td>
                            <td class="px-2 py-1.5 text-center">
                                @php $vc = $p['validcode'] ?? null; @endphp
                                @if ($vc === '1')
                                    <x-badge variant="success">Valid</x-badge>
                                @elseif ($vc === '0')
                                    <x-badge variant="danger">Tidak Valid</x-badge>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            @if (!$inacbgFinal)
                                <td class="px-2 py-1.5">
                                    <x-icon-button color="red" wire:click="remove({{ $i }})"
                                        wire:confirm="Hapus prosedur {{ $p['code'] ?? '' }} dari coder INACBG?">
                                        <span class="text-base font-bold leading-none">×</span>
                                    </x-icon-button>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="py-2 text-xs text-center text-gray-400 dark:text-gray-500">
            Belum ada prosedur INACBG. Klik "Sync dari iDRG" atau tambah via LOV (boleh kosong jika tidak ada tindakan).
        </p>
    @endif

    @if (!empty($inacbgProsedurString))
        <div class="px-2 py-1.5 text-xs font-mono text-gray-600 bg-gray-50 rounded dark:bg-gray-800 dark:text-gray-400">
            <span class="text-gray-500">Terkirim:</span> {{ $inacbgProsedurString }}
        </div>
    @endif
</div>
