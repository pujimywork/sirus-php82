<?php
// resources/views/pages/transaksi/rj/idrg/kirim-diagnosa-inacbg.blade.php
// Step 9: Coder Editor + Set Diagnosa INACBG.
// Override koder casemix kalau ada kode iDRG yang IM-only tidak berlaku di INACBG.

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\iDRG\iDrgTrait;

new class extends Component {
    use EmrRJTrait, iDrgTrait;

    public ?string $rjNo = null;

    // State (mirror dari $idrg di JSON DB).
    public array $coderInacbgDiagnosa = [];
    public ?string $inacbgDiagnosaString = null;
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
            $this->coderInacbgDiagnosa = [];
            $this->inacbgDiagnosaString = null;
            $this->inacbgFinal = false;
            $this->idrgFinal = false;
            $this->hasClaim = false;
            return;
        }
        $data = $this->findDataRJ($this->rjNo);
        if (empty($data)) {
            return;
        }
        $idrg = $data['idrg'] ?? [];
        $this->coderInacbgDiagnosa = $idrg['coderInacbgDiagnosa'] ?? [];
        $this->inacbgDiagnosaString = $idrg['inacbgDiagnosaString'] ?? null;
        $this->inacbgFinal = !empty($idrg['inacbgFinal']);
        $this->idrgFinal = !empty($idrg['idrgFinal']);
        $this->hasClaim = !empty($idrg['nomorSep']);
    }

    /* ===============================
     | EDITOR (CODER) — CRUD
     =============================== */

    #[On('lov.selected.rjFormDiagnosaInacbgCoder')]
    public function onLov(string $target, array $payload): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $code = trim((string) ($payload['icdx'] ?? $payload['diag_id'] ?? ''));
        $desc = (string) ($payload['diag_desc'] ?? $payload['description'] ?? '');
        if ($code === '') {
            $this->dispatch('toast', type: 'error', message: 'Kode diagnosa tidak valid.');
            return;
        }
        $this->add($code, $desc, 'Secondary');
    }

    public function add(string $code, string $desc, string $kategori = 'Secondary'): void
    {
        if (empty($this->rjNo) || empty($code)) {
            return;
        }
        $this->mutate(function ($coder) use ($code, $desc, $kategori) {
            $coder[] = [
                'code' => $code,
                'desc' => $desc,
                'kategori' => $kategori,
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

    public function setKategori(int $index, string $kategori): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $kategori = in_array($kategori, ['Primary', 'Secondary'], true) ? $kategori : 'Secondary';
        $this->mutate(function ($coder) use ($index, $kategori) {
            if (isset($coder[$index])) {
                $coder[$index]['kategori'] = $kategori;
            }
            return $coder;
        });
    }

    /**
     * Sync dari iDRG coder editor (idrg.coderDiagnosa) sebagai starting point.
     * Coder lalu identify kode IM-only (validcode=0) dan ganti ke kode non-IM.
     * Fallback ke EMR diagnosis[] kalau iDRG coder belum diisi.
     */
    public function syncFromIdrg(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        DB::transaction(function () {
            $this->lockRJRow($this->rjNo);
            $data = $this->findDataRJ($this->rjNo);
            $idrg = $data['idrg'] ?? [];
            $sourceFromIdrg = $idrg['coderDiagnosa'] ?? [];
            $coder = [];
            if (!empty($sourceFromIdrg)) {
                foreach ($sourceFromIdrg as $d) {
                    $code = trim((string) ($d['code'] ?? ''));
                    if ($code === '') {
                        continue;
                    }
                    $coder[] = [
                        'code' => $code,
                        'desc' => (string) ($d['desc'] ?? ''),
                        'kategori' => (string) ($d['kategori'] ?? 'Secondary'),
                        'validcode' => null,
                    ];
                }
            } else {
                // Fallback EMR
                foreach (($data['diagnosis'] ?? []) as $d) {
                    $code = trim((string) ($d['icdX'] ?? $d['diagId'] ?? ''));
                    if ($code === '') {
                        continue;
                    }
                    $coder[] = [
                        'code' => $code,
                        'desc' => (string) ($d['diagDesc'] ?? ''),
                        'kategori' => (string) ($d['kategoriDiagnosa'] ?? 'Secondary'),
                        'validcode' => null,
                    ];
                }
            }
            $idrg['coderInacbgDiagnosa'] = $coder;
            $idrg['coderInacbgDiagnosaSyncedAt'] = Carbon::now()->format('Y-m-d H:i:s');
            $data['idrg'] = $idrg;
            $this->updateJsonRJ($this->rjNo, $data);
        });
        $this->reloadState();
        $this->dispatch('toast', type: 'success', message: 'Coder INACBG diagnosa di-sync dari iDRG.');
        $this->dispatch('idrg-state-updated', rjNo: (string) $this->rjNo);
    }

    private function mutate(callable $fn): void
    {
        DB::transaction(function () use ($fn) {
            $this->lockRJRow($this->rjNo);
            $data = $this->findDataRJ($this->rjNo);
            $coder = $data['idrg']['coderInacbgDiagnosa'] ?? [];
            $coder = $fn($coder);
            $data['idrg']['coderInacbgDiagnosa'] = $coder;
            $this->updateJsonRJ($this->rjNo, $data);
        });
        $this->reloadState();
        $this->dispatch('idrg-state-updated', rjNo: (string) $this->rjNo);
    }

    /* ===============================
     | API ACTION — set_diagnosa_inacbg
     =============================== */

    #[On('idrg-diagnosa-inacbg-rj.set')]
    public function set(string $rjNo, ?string $diagnosa = null): void
    {
        try {
            $data = $this->findDataRJ($rjNo);
            if (empty($data)) {
                throw new \RuntimeException('Data RJ tidak ditemukan.');
            }
            $idrg = $data['idrg'] ?? [];
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) {
                $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.');
                return;
            }

            if ($diagnosa === null || $diagnosa === '') {
                $coder = $idrg['coderInacbgDiagnosa'] ?? [];
                if (!empty($coder)) {
                    $diagnosa = $this->buildString($coder);
                } else {
                    $this->dispatch('toast', type: 'error', message: 'Belum ada coder diagnosa INACBG. Klik "Sync dari iDRG" atau tambah via LOV.');
                    return;
                }
            }

            $res = $this->setDiagnosaInacbg($nomorSep, $diagnosa)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Set Diagnosa INACBG'));
                return;
            }

            $response = $res['response'] ?? [];
            $idrg['inacbgDiagnosa'] = $response;
            $idrg['inacbgDiagnosaString'] = $diagnosa;

            // Update validcode di coderInacbgDiagnosa
            $expanded = $response['expanded'] ?? $response['data']['expanded'] ?? [];
            if (!empty($idrg['coderInacbgDiagnosa']) && !empty($expanded)) {
                $byCode = collect($expanded)->keyBy('code');
                foreach ($idrg['coderInacbgDiagnosa'] as &$c) {
                    $code = $c['code'] ?? '';
                    if (isset($byCode[$code])) {
                        $c['validcode'] = (string) ($byCode[$code]['validcode'] ?? '');
                    }
                }
                unset($c);
            }

            $this->saveResult($rjNo, $idrg);
            $this->dispatch('toast', type: 'success', message: "Diagnosa INACBG tersimpan: {$diagnosa}");
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Set diagnosa INACBG gagal: ' . $e->getMessage());
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
        $primary = [];
        $secondary = [];
        foreach ($coder as $c) {
            $code = trim((string) ($c['code'] ?? ''));
            if ($code === '') {
                continue;
            }
            if (($c['kategori'] ?? '') === 'Primary') {
                $primary[] = $code;
            } else {
                $secondary[] = $code;
            }
        }
        return implode('#', [...$primary, ...$secondary]);
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
                class="flex items-center justify-center w-8 h-8 rounded-full {{ !empty($inacbgDiagnosaString) ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500' }}">
                <span class="text-sm font-bold">9</span>
            </div>
            <div>
                <div class="font-semibold text-gray-800 dark:text-gray-100">Set Diagnosa INACBG</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    Override jika ada kode iDRG dengan "IM tidak berlaku" di INACBG.
                </div>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" wire:click="syncFromIdrg" wire:loading.attr="disabled" @disabled($inacbgFinal)
                class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 disabled:opacity-50 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                <span wire:loading.remove wire:target="syncFromIdrg">↻ Sync dari iDRG</span>
                <span wire:loading wire:target="syncFromIdrg"><x-loading />...</span>
            </button>
            <x-primary-button type="button" wire:click="setForCurrent" wire:loading.attr="disabled"
                :disabled="$inacbgFinal || !$hasClaim || empty($coderInacbgDiagnosa)"
                class="!bg-brand hover:!bg-brand/90 {{ !empty($inacbgDiagnosaString) ? '!bg-emerald-600' : '' }}">
                <span wire:loading.remove wire:target="setForCurrent">
                    {{ !empty($inacbgDiagnosaString) ? 'Set Ulang' : 'Set Diagnosa INACBG' }}
                </span>
                <span wire:loading wire:target="setForCurrent"><x-loading />...</span>
            </x-primary-button>
        </div>
    </div>

    @if (!$inacbgFinal)
        <div wire:key="lov-diagnosa-inacbg-coder-{{ $rjNo ?? 'none' }}">
            <livewire:lov.diagnosa.lov-diagnosa label="Cari Diagnosa (untuk INACBG)" target="rjFormDiagnosaInacbgCoder"
                wire:key="lov-diagnosa-inacbg-coder-inner-{{ $rjNo ?? 'none' }}" />
        </div>
    @endif

    @if (!empty($coderInacbgDiagnosa))
        <div class="overflow-x-auto border border-gray-200 rounded-lg dark:border-gray-700">
            <table class="w-full text-xs text-left">
                <thead class="text-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-300">
                    <tr>
                        <th class="px-2 py-1.5 font-medium">Kode</th>
                        <th class="px-2 py-1.5 font-medium">Deskripsi</th>
                        <th class="px-2 py-1.5 font-medium">Kategori</th>
                        <th class="px-2 py-1.5 font-medium text-center">Valid IM</th>
                        @if (!$inacbgFinal)
                            <th class="px-2 py-1.5 w-8"></th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($coderInacbgDiagnosa as $i => $d)
                        <tr wire:key="coder-inacbg-diag-{{ $i }}-{{ $d['code'] ?? 'x' }}"
                            class="bg-white dark:bg-gray-900">
                            <td class="px-2 py-1.5 font-mono font-semibold text-gray-800 dark:text-gray-100">
                                {{ $d['code'] ?? '' }}</td>
                            <td class="px-2 py-1.5 text-gray-700 dark:text-gray-300">{{ $d['desc'] ?? '' }}</td>
                            <td class="px-2 py-1.5">
                                <select wire:change="setKategori({{ $i }}, $event.target.value)"
                                    @disabled($inacbgFinal)
                                    class="text-xs px-2 py-0.5 border border-gray-200 rounded dark:bg-gray-800 dark:border-gray-700">
                                    <option value="Primary" @selected(($d['kategori'] ?? 'Secondary') === 'Primary')>
                                        Primary</option>
                                    <option value="Secondary" @selected(($d['kategori'] ?? 'Secondary') === 'Secondary')>
                                        Secondary</option>
                                </select>
                            </td>
                            <td class="px-2 py-1.5 text-center">
                                @php $vc = $d['validcode'] ?? null; @endphp
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
                            @if (!$inacbgFinal)
                                <td class="px-2 py-1.5">
                                    <button type="button" wire:click="remove({{ $i }})"
                                        wire:confirm="Hapus diagnosa {{ $d['code'] ?? '' }} dari coder INACBG?"
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
            Belum ada diagnosa INACBG. Klik "Sync dari iDRG" atau tambah via LOV.
        </p>
    @endif

    @if (!empty($inacbgDiagnosaString))
        <div class="px-2 py-1.5 text-xs font-mono text-gray-600 bg-gray-50 rounded dark:bg-gray-800 dark:text-gray-400">
            <span class="text-gray-500">Terkirim:</span> {{ $inacbgDiagnosaString }}
        </div>
    @endif
</div>
