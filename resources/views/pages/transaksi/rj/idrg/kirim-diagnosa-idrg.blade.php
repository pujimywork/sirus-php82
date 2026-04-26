<?php
// resources/views/pages/transaksi/rj/idrg/kirim-diagnosa-idrg.blade.php
// Coder Editor + Set/Get Diagnosa iDRG (ICD-10 2010 IM).
// Coder Casemix bisa edit array $idrg['coderDiagnosa'] tanpa mengubah EMR (hak RM).

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
    public array $coderDiagnosa = [];
    public ?string $idrgDiagnosaString = null;
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
            $this->coderDiagnosa = [];
            $this->idrgDiagnosaString = null;
            $this->idrgFinal = false;
            $this->hasClaim = false;
            return;
        }
        $data = $this->findDataRJ($this->rjNo);
        if (empty($data)) {
            return;
        }
        $idrg = $data['idrg'] ?? [];
        $this->coderDiagnosa = $idrg['coderDiagnosa'] ?? [];
        $this->idrgDiagnosaString = $idrg['idrgDiagnosaString'] ?? null;
        $this->idrgFinal = !empty($idrg['idrgFinal']);
        $this->hasClaim = !empty($idrg['nomorSep']);
    }

    /* ===============================
     | EDITOR (CODER) — CRUD
     =============================== */

    #[On('lov.selected.rjFormDiagnosaIdrgCoder')]
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

    public function syncFromEmr(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        DB::transaction(function () {
            $this->lockRJRow($this->rjNo);
            $data = $this->findDataRJ($this->rjNo);
            $emrDiag = $data['diagnosis'] ?? [];
            $coder = [];
            foreach ($emrDiag as $d) {
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
            $data['idrg']['coderDiagnosa'] = $coder;
            $data['idrg']['coderDiagnosaSyncedAt'] = Carbon::now()->format('Y-m-d H:i:s');
            $this->updateJsonRJ($this->rjNo, $data);
        });
        $this->reloadState();
        $this->dispatch('toast', type: 'success', message: 'Coder diagnosa di-sync dari EMR.');
        $this->dispatch('idrg-state-updated', rjNo: (string) $this->rjNo);
    }

    private function mutate(callable $fn): void
    {
        DB::transaction(function () use ($fn) {
            $this->lockRJRow($this->rjNo);
            $data = $this->findDataRJ($this->rjNo);
            $coder = $data['idrg']['coderDiagnosa'] ?? [];
            $coder = $fn($coder);
            $data['idrg']['coderDiagnosa'] = $coder;
            $this->updateJsonRJ($this->rjNo, $data);
        });
        $this->reloadState();
        $this->dispatch('idrg-state-updated', rjNo: (string) $this->rjNo);
    }

    /* ===============================
     | API ACTIONS — Set / Get / Search
     =============================== */

    #[On('idrg-diagnosa-rj.set')]
    public function set(string $rjNo, ?string $diagnosa = null): void
    {
        try {
            [$dataRJ, $idrg] = $this->loadData($rjNo);
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) {
                $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.');
                return;
            }

            // Prioritas: coderDiagnosa (editor coder) → fallback EMR diagnosis[]
            if ($diagnosa === null || $diagnosa === '') {
                $coder = $idrg['coderDiagnosa'] ?? [];
                $diagnosa = !empty($coder)
                    ? $this->buildString($coder, 'kategori', 'code')
                    : $this->buildString($dataRJ['diagnosis'] ?? [], 'kategoriDiagnosa', null);
                if (empty($diagnosa)) {
                    $this->dispatch('toast', type: 'error', message: 'Tidak ada diagnosa untuk dikirim. Tambah via LOV atau Sync dari EMR.');
                    return;
                }
            }

            $res = $this->setDiagnosaIdrg($nomorSep, $diagnosa)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Set Diagnosa iDRG'));
                return;
            }

            $response = $res['response'] ?? [];
            $idrg['idrgDiagnosa'] = $response;
            $idrg['idrgDiagnosaString'] = $diagnosa;

            // Update validcode di coderDiagnosa berdasarkan expanded[] response
            $expanded = $response['expanded'] ?? $response['data']['expanded'] ?? [];
            if (!empty($idrg['coderDiagnosa']) && !empty($expanded)) {
                $byCode = collect($expanded)->keyBy('code');
                foreach ($idrg['coderDiagnosa'] as &$c) {
                    $code = $c['code'] ?? '';
                    if (isset($byCode[$code])) {
                        $c['validcode'] = (string) ($byCode[$code]['validcode'] ?? '');
                    }
                }
                unset($c);
            }

            $this->saveResult($rjNo, $idrg);
            $this->dispatch('toast', type: 'success', message: "Diagnosa iDRG tersimpan: {$diagnosa}");
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Set diagnosa iDRG gagal: ' . $e->getMessage());
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
     * Build string "PRIMARY#PRIMARY#SECONDARY#SECONDARY".
     * $kategoriKey: nama key kategori di item ('kategori' utk coder, 'kategoriDiagnosa' utk EMR).
     * $codeKey: nama key code; null = pakai fallback icdX/diagId (struktur EMR).
     */
    private function buildString(array $items, string $kategoriKey, ?string $codeKey): string
    {
        if (empty($items)) {
            return '';
        }
        $primary = [];
        $secondary = [];
        foreach ($items as $i) {
            if ($codeKey !== null) {
                $code = trim((string) ($i[$codeKey] ?? ''));
            } else {
                $code = trim((string) ($i['icdX'] ?? $i['diagId'] ?? ''));
            }
            if ($code === '') {
                continue;
            }
            if (($i[$kategoriKey] ?? '') === 'Primary') {
                $primary[] = $code;
            } else {
                $secondary[] = $code;
            }
        }
        return implode('#', [...$primary, ...$secondary]);
    }

    #[On('idrg-diagnosa-rj.get')]
    public function get(string $rjNo): void
    {
        try {
            [, $idrg] = $this->loadData($rjNo);
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) {
                $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.');
                return;
            }

            $res = $this->getDiagnosaIdrg($nomorSep)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Ambil Diagnosa iDRG'));
                return;
            }

            $idrg['idrgDiagnosa'] = $res['response'] ?? [];
            $this->saveResult($rjNo, $idrg);
            $this->dispatch('idrg-diagnosa-rj.loaded', $idrg['idrgDiagnosa']);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Get diagnosa iDRG gagal: ' . $e->getMessage());
        }
    }

    #[On('idrg-diagnosa-rj.search')]
    public function search(string $keyword): void
    {
        try {
            $res = $this->searchDiagnosaIdrg($keyword)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Cari Diagnosa di iDRG'));
                return;
            }
            $this->dispatch('idrg-diagnosa-rj.search-result', $res['response']['data'] ?? []);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Search diagnosa iDRG gagal: ' . $e->getMessage());
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
                class="flex items-center justify-center w-8 h-8 rounded-full {{ !empty($idrgDiagnosaString) ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500' }}">
                <span class="text-sm font-bold">4</span>
            </div>
            <div>
                <div class="font-semibold text-gray-800 dark:text-gray-100">Set Diagnosa iDRG</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Coder edit di sini, EMR tidak terganggu.</div>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" wire:click="syncFromEmr" wire:loading.attr="disabled" @disabled($idrgFinal)
                class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 disabled:opacity-50 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                <span wire:loading.remove wire:target="syncFromEmr">↻ Sync dari EMR</span>
                <span wire:loading wire:target="syncFromEmr"><x-loading />...</span>
            </button>
            <x-primary-button type="button" wire:click="setForCurrent" wire:loading.attr="disabled"
                :disabled="$idrgFinal || !$hasClaim || empty($coderDiagnosa)"
                class="!bg-brand hover:!bg-brand/90 {{ !empty($idrgDiagnosaString) ? '!bg-emerald-600' : '' }}">
                <span wire:loading.remove wire:target="setForCurrent">
                    {{ !empty($idrgDiagnosaString) ? 'Set Ulang' : 'Set Diagnosa iDRG' }}
                </span>
                <span wire:loading wire:target="setForCurrent"><x-loading />...</span>
            </x-primary-button>
        </div>
    </div>

    {{-- LOV tambah --}}
    @if (!$idrgFinal)
        <div wire:key="lov-diagnosa-idrg-coder-{{ $rjNo ?? 'none' }}">
            <livewire:lov.diagnosa.lov-diagnosa label="Cari Diagnosa (untuk klaim iDRG)" target="rjFormDiagnosaIdrgCoder"
                wire:key="lov-diagnosa-idrg-coder-inner-{{ $rjNo ?? 'none' }}" />
        </div>
    @endif

    {{-- Tabel coder --}}
    @if (!empty($coderDiagnosa))
        <div class="overflow-x-auto border border-gray-200 rounded-lg dark:border-gray-700">
            <table class="w-full text-xs text-left">
                <thead class="text-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-gray-300">
                    <tr>
                        <th class="px-2 py-1.5 font-medium">Kode</th>
                        <th class="px-2 py-1.5 font-medium">Deskripsi</th>
                        <th class="px-2 py-1.5 font-medium">Kategori</th>
                        <th class="px-2 py-1.5 font-medium text-center">Valid IM</th>
                        @if (!$idrgFinal)
                            <th class="px-2 py-1.5 w-8"></th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($coderDiagnosa as $i => $d)
                        <tr wire:key="coder-diag-{{ $i }}-{{ $d['code'] ?? 'x' }}" class="bg-white dark:bg-gray-900">
                            <td class="px-2 py-1.5 font-mono font-semibold text-gray-800 dark:text-gray-100">
                                {{ $d['code'] ?? '' }}</td>
                            <td class="px-2 py-1.5 text-gray-700 dark:text-gray-300">{{ $d['desc'] ?? '' }}</td>
                            <td class="px-2 py-1.5">
                                <select wire:change="setKategori({{ $i }}, $event.target.value)" @disabled($idrgFinal)
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
                            @if (!$idrgFinal)
                                <td class="px-2 py-1.5">
                                    <button type="button" wire:click="remove({{ $i }})"
                                        wire:confirm="Hapus diagnosa {{ $d['code'] ?? '' }} dari coder?"
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
            Belum ada diagnosa coder. Tambah via LOV atau klik "Sync dari EMR".
        </p>
    @endif

    {{-- Response string yang terkirim --}}
    @if (!empty($idrgDiagnosaString))
        <div class="px-2 py-1.5 text-xs font-mono text-gray-600 bg-gray-50 rounded dark:bg-gray-800 dark:text-gray-400">
            <span class="text-gray-500">Terkirim:</span> {{ $idrgDiagnosaString }}
        </div>
    @endif
</div>
