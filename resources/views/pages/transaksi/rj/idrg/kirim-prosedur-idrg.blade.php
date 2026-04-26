<?php
// resources/views/pages/transaksi/rj/idrg/kirim-prosedur-idrg.blade.php
// Coder Editor + Set/Get Prosedur iDRG (ICD-9-CM 2010 IM, support multiplicity & setting).
// Manual hal. 26-27: format "86.22+3#86.22+2" = debridement 3x di operasi 1, 2x di operasi 2.

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\iDRG\iDrgTrait;

new class extends Component {
    use EmrRJTrait, iDrgTrait;

    public ?string $rjNo = null;

    // State (mirror dari $idrg di JSON DB) — supaya UI Livewire reactive.
    public array $coderProsedur = [];
    public ?string $idrgProsedurString = null;
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

    #[On('idrg-state-updated')]
    public function onStateUpdated(string $rjNo): void
    {
        if ((string) $this->rjNo !== $rjNo) {
            return;
        }
        $this->reloadState();
    }

    private function reloadState(): void
    {
        if (empty($this->rjNo)) {
            $this->coderProsedur = [];
            $this->idrgProsedurString = null;
            $this->idrgFinal = false;
            $this->hasClaim = false;
            return;
        }
        $data = $this->findDataRJ($this->rjNo);
        if (empty($data)) {
            return;
        }
        $idrg = $data['idrg'] ?? [];
        $this->coderProsedur = $idrg['coderProsedur'] ?? [];
        $this->idrgProsedurString = $idrg['idrgProsedurString'] ?? null;
        $this->idrgFinal = !empty($idrg['idrgFinal']);
        $this->hasClaim = !empty($idrg['nomorSep']);
    }

    /* ===============================
     | EDITOR (CODER) — CRUD
     =============================== */

    #[On('lov.selected.rjFormProsedurIdrgCoder')]
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

    public function syncFromEmr(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        DB::transaction(function () {
            $this->lockRJRow($this->rjNo);
            $data = $this->findDataRJ($this->rjNo);
            $emrProc = $data['procedure'] ?? [];
            $coder = [];
            foreach ($emrProc as $p) {
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
            $data['idrg']['coderProsedur'] = $coder;
            $data['idrg']['coderProsedurSyncedAt'] = Carbon::now()->format('Y-m-d H:i:s');
            $this->updateJsonRJ($this->rjNo, $data);
        });
        $this->reloadState();
        $this->dispatch('toast', type: 'success', message: 'Coder prosedur di-sync dari EMR.');
        $this->dispatch('idrg-state-updated', rjNo: (string) $this->rjNo);
    }

    private function mutate(callable $fn): void
    {
        DB::transaction(function () use ($fn) {
            $this->lockRJRow($this->rjNo);
            $data = $this->findDataRJ($this->rjNo);
            $coder = $data['idrg']['coderProsedur'] ?? [];
            $coder = $fn($coder);
            $data['idrg']['coderProsedur'] = $coder;
            $this->updateJsonRJ($this->rjNo, $data);
        });
        $this->reloadState();
        $this->dispatch('idrg-state-updated', rjNo: (string) $this->rjNo);
    }

    /* ===============================
     | API ACTIONS — Set / Get / Search
     =============================== */

    #[On('idrg-prosedur-rj.set')]
    public function set(string $rjNo, ?string $procedure = null): void
    {
        try {
            [$dataRJ, $idrg] = $this->loadData($rjNo);
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) {
                $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.');
                return;
            }

            // Prioritas: coderProsedur (editor coder) → fallback EMR procedure[]
            if ($procedure === null || $procedure === '') {
                $coder = $idrg['coderProsedur'] ?? [];
                $procedure = !empty($coder)
                    ? $this->buildString($coder, 'code')
                    : $this->buildString($dataRJ['procedure'] ?? [], 'procedureId');
                if (empty($procedure)) {
                    // Manual hal. 26: kirim "#" untuk hapus semua, "" malah berarti no-change.
                    $procedure = '#';
                }
            }

            $res = $this->setProsedurIdrg($nomorSep, $procedure)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Set Prosedur iDRG'));
                return;
            }

            $response = $res['response'] ?? [];
            $idrg['idrgProsedur'] = $response;
            $idrg['idrgProsedurString'] = $procedure;

            // Update validcode di coderProsedur berdasarkan expanded[] response
            // (validcode adalah property kode, bukan setting — same code = same validcode di setting manapun)
            $expanded = $response['expanded'] ?? $response['data']['expanded'] ?? [];
            if (!empty($idrg['coderProsedur']) && !empty($expanded)) {
                $byCode = collect($expanded)->keyBy('code');
                foreach ($idrg['coderProsedur'] as &$c) {
                    $code = $c['code'] ?? '';
                    if (isset($byCode[$code])) {
                        $c['validcode'] = (string) ($byCode[$code]['validcode'] ?? '');
                    }
                }
                unset($c);
            }

            $this->saveResult($rjNo, $idrg);
            $this->dispatch('toast', type: 'success', message: "Prosedur iDRG tersimpan: {$procedure}");
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Set prosedur iDRG gagal: ' . $e->getMessage());
        }
    }

    public function setForCurrent(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $this->set($this->rjNo);
    }

    /**
     * Build "code+mult#code+mult#code...". Group by settingGroup ascending, lalu join semua dengan '#'.
     * Manual hal. 26: setting beda = kode muncul lagi di string, multiplicity = "+N" di belakang kode.
     * $codeKey: 'code' utk coder array, 'procedureId' utk EMR procedure[] array.
     */
    private function buildString(array $items, string $codeKey): string
    {
        if (empty($items)) {
            return '';
        }
        $groups = [];
        foreach ($items as $i) {
            $code = trim((string) ($i[$codeKey] ?? ''));
            if ($code === '') {
                continue;
            }
            $mult = max(1, (int) ($i['multiplicity'] ?? 1));
            $groupKey = max(1, (int) ($i['settingGroup'] ?? 1));
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

    #[On('idrg-prosedur-rj.get')]
    public function get(string $rjNo): void
    {
        try {
            [, $idrg] = $this->loadData($rjNo);
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) {
                $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.');
                return;
            }

            $res = $this->getProsedurIdrg($nomorSep)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Ambil Prosedur iDRG'));
                return;
            }

            $idrg['idrgProsedur'] = $res['response'] ?? [];
            $this->saveResult($rjNo, $idrg);
            $this->dispatch('idrg-prosedur-rj.loaded', $idrg['idrgProsedur']);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Get prosedur iDRG gagal: ' . $e->getMessage());
        }
    }

    #[On('idrg-prosedur-rj.search')]
    public function search(string $keyword): void
    {
        try {
            $res = $this->searchProsedurIdrg($keyword)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Cari Prosedur di iDRG'));
                return;
            }
            $this->dispatch('idrg-prosedur-rj.search-result', $res['response']['data'] ?? []);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Search prosedur iDRG gagal: ' . $e->getMessage());
        }
    }

    private function loadData(string $rjNo): array
    {
        $dataRJ = $this->findDataRJ($rjNo);
        if (empty($dataRJ)) {
            throw new \RuntimeException('Data RJ tidak ditemukan.');
        }
        return [$dataRJ, $dataRJ['idrg'] ?? []];
    }

    private function saveResult(string $rjNo, array $idrg): void
    {
        DB::transaction(function () use ($rjNo, $idrg) {
            $this->lockRJRow($rjNo);
            $data = $this->findDataRJ($rjNo);
            $data['idrg'] = $idrg;
            $this->updateJsonRJ($rjNo, $data);
        });

        $this->dispatch('idrg-state-updated', rjNo: (string) $rjNo);
    }
};
?>

<div class="p-4 space-y-3 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700">
    {{-- Header --}}
    <div class="flex items-start justify-between gap-3">
        <div class="flex items-center gap-3">
            <div
                class="flex items-center justify-center w-8 h-8 rounded-full {{ !empty($idrgProsedurString) ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500' }}">
                <span class="text-sm font-bold">5</span>
            </div>
            <div>
                <div class="font-semibold text-gray-800 dark:text-gray-100">Set Prosedur iDRG</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    Mult = jumlah dalam 1 operasi (notasi "+N"). Setting = operasi berbeda (kode muncul lagi di string).
                </div>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" wire:click="syncFromEmr" wire:loading.attr="disabled" @disabled($idrgFinal)
                class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 disabled:opacity-50 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                <span wire:loading.remove wire:target="syncFromEmr">↻ Sync dari EMR</span>
                <span wire:loading wire:target="syncFromEmr"><x-loading />...</span>
            </button>
            <x-primary-button type="button" wire:click="setForCurrent" wire:loading.attr="disabled"
                :disabled="$idrgFinal || !$hasClaim"
                class="!bg-brand hover:!bg-brand/90 {{ !empty($idrgProsedurString) ? '!bg-emerald-600' : '' }}">
                <span wire:loading.remove wire:target="setForCurrent">
                    {{ !empty($idrgProsedurString) ? 'Set Ulang' : 'Set Prosedur iDRG' }}
                </span>
                <span wire:loading wire:target="setForCurrent"><x-loading />...</span>
            </x-primary-button>
        </div>
    </div>

    {{-- LOV tambah --}}
    @if (!$idrgFinal)
        <div wire:key="lov-procedure-idrg-coder-{{ $rjNo ?? 'none' }}">
            <livewire:lov.procedure.lov-procedure label="Cari Prosedur (untuk klaim iDRG)"
                target="rjFormProsedurIdrgCoder"
                wire:key="lov-procedure-idrg-coder-inner-{{ $rjNo ?? 'none' }}" />
        </div>
    @endif

    {{-- Tabel coder --}}
    @if (!empty($coderProsedur))
        <div class="overflow-x-auto border border-gray-200 rounded-lg dark:border-gray-700">
            <table class="w-full text-xs text-left">
                <thead class="text-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-300">
                    <tr>
                        <th class="px-2 py-1.5 font-medium">Kode</th>
                        <th class="px-2 py-1.5 font-medium">Deskripsi</th>
                        <th class="px-2 py-1.5 font-medium text-center" title="Multiplicity: berapa kali dalam 1 operasi (+N)">Mult.</th>
                        <th class="px-2 py-1.5 font-medium text-center" title="Setting: operasi ke-berapa (1, 2, 3...)">Setting</th>
                        <th class="px-2 py-1.5 font-medium text-center">Valid IM</th>
                        @if (!$idrgFinal)
                            <th class="px-2 py-1.5 w-8"></th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($coderProsedur as $i => $p)
                        <tr wire:key="coder-proc-{{ $i }}-{{ $p['code'] ?? 'x' }}" class="bg-white dark:bg-gray-900">
                            <td class="px-2 py-1.5 font-mono font-semibold text-gray-800 dark:text-gray-100">
                                {{ $p['code'] ?? '' }}</td>
                            <td class="px-2 py-1.5 text-gray-700 dark:text-gray-300">{{ $p['desc'] ?? '' }}</td>
                            <td class="px-2 py-1.5 text-center">
                                <input type="number" min="1" value="{{ $p['multiplicity'] ?? 1 }}"
                                    wire:change="setMultiplicity({{ $i }}, $event.target.value)"
                                    @disabled($idrgFinal)
                                    class="w-16 px-2 py-0.5 text-xs text-center border border-gray-200 rounded dark:bg-gray-800 dark:border-gray-700">
                            </td>
                            <td class="px-2 py-1.5 text-center">
                                <input type="number" min="1" value="{{ $p['settingGroup'] ?? 1 }}"
                                    wire:change="setSettingGroup({{ $i }}, $event.target.value)"
                                    @disabled($idrgFinal)
                                    class="w-16 px-2 py-0.5 text-xs text-center border border-gray-200 rounded dark:bg-gray-800 dark:border-gray-700">
                            </td>
                            <td class="px-2 py-1.5 text-center">
                                @php $vc = $p['validcode'] ?? null; @endphp
                                @if ($vc === '1')
                                    <span
                                        class="px-2 py-0.5 text-xs font-medium rounded bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">Valid</span>
                                @elseif ($vc === '0')
                                    <span
                                        class="px-2 py-0.5 text-xs font-medium rounded bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400">Tidak
                                        Valid</span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            @if (!$idrgFinal)
                                <td class="px-2 py-1.5">
                                    <button type="button" wire:click="remove({{ $i }})"
                                        wire:confirm="Hapus prosedur {{ $p['code'] ?? '' }} dari coder?"
                                        class="text-base font-bold leading-none text-rose-600 hover:text-rose-800 dark:text-rose-400">×</button>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="py-2 text-xs text-center text-gray-400 dark:text-gray-500">
            Belum ada prosedur coder. Tambah via LOV atau klik "Sync dari EMR" (boleh kosong jika tidak ada tindakan).
        </p>
    @endif

    {{-- Response string yang terkirim --}}
    @if (!empty($idrgProsedurString))
        <div class="px-2 py-1.5 text-xs font-mono text-gray-600 bg-gray-50 rounded dark:bg-gray-800 dark:text-gray-400">
            <span class="text-gray-500">Terkirim:</span> {{ $idrgProsedurString }}
        </div>
    @endif
</div>
