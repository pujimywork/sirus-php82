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
    public array $idrgDiagnosaExpanded = [];
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

        // Auto-sync dari EMR pertama kali modal dibuka (idempotent, dipandu syncedAt flag).
        // Dengan ini coder tidak perlu klik "Sync dari EMR" manual setiap pasien baru.
        if (empty($idrg['coderDiagnosaSyncedAt']) && empty($idrg['coderDiagnosa']) && !empty($data['diagnosis'])) {
            $this->persistEmrSync();
            $data = $this->findDataRJ($this->rjNo);
            $idrg = $data['idrg'] ?? [];
        }

        $this->coderDiagnosa = $idrg['coderDiagnosa'] ?? [];
        $this->idrgDiagnosaExpanded = $idrg['idrgDiagnosaExpanded'] ?? [];
        $this->idrgDiagnosaString = $idrg['idrgDiagnosaString'] ?? null;
        $this->idrgFinal = !empty($idrg['idrgFinal']);
        $this->hasClaim = !empty($idrg['nomorSep']);
    }

    private function persistEmrSync(): void
    {
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
        $this->add($code, $desc);
    }

    /**
     * Tambah diagnosa baru ke coder editor.
     *
     * Aturan kategori (Primary/Secondary):
     *  - Kalau caller pass $kategori eksplisit → pakai itu (no check)
     *  - Kalau auto: pertama yg boleh primer → Primary, sisanya Secondary
     *  - Kode dgn accpdx='N' di master diags TIDAK boleh primer → otomatis Secondary
     *
     * Setelah insert, list disort: Primary di atas, Secondary di bawah.
     */
    public function add(string $code, string $desc, ?string $kategori = null): void
    {
        if (empty($this->rjNo) || empty($code)) {
            return;
        }
        // Lookup accpdx ('Y' = boleh sebagai Primary Diagnosis, 'N' = hanya Secondary)
        $masterAccpdx = DB::table('rsmst_mstdiags')
            ->where('icdx', $code)
            ->orWhere('diag_id', $code)
            ->value('accpdx');
        $isAllowedAsPrimary = ($masterAccpdx === 'Y');

        $this->mutate(function ($diagList) use ($code, $desc, $kategori, $isAllowedAsPrimary) {
            $hasExistingPrimary = collect($diagList)->contains(fn($diag) => ($diag['kategori'] ?? '') === 'Primary');
            // Auto-Primary kalau: belum ada Primary lain AND kode boleh sebagai PDX
            $kategoriDefault = (!$hasExistingPrimary && $isAllowedAsPrimary) ? 'Primary' : 'Secondary';

            $diagList[] = [
                'code' => $code,
                'desc' => $desc,
                'kategori' => $kategori ?? $kategoriDefault,
                'validcode' => null,
            ];

            return $this->sortPrimaryFirst($diagList);
        });
    }

    /**
     * Sort diagnosa list: Primary di paling atas, Secondary di bawah (stable order).
     */
    private function sortPrimaryFirst(array $diagList): array
    {
        $diagList = array_values($diagList);
        usort($diagList, fn($a, $b) =>
            (($a['kategori'] ?? '') === 'Primary' ? 0 : 1) -
            (($b['kategori'] ?? '') === 'Primary' ? 0 : 1)
        );
        return $diagList;
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

    /**
     * Ubah kategori (Primary/Secondary) baris diagnosa di index tertentu.
     *
     * Aturan saat promosi ke Primary:
     *  1. Kode harus accpdx='Y' di master diags — kalau tidak, tolak dengan toast.
     *  2. Single-Primary invariant: Primary lain (kalau ada) otomatis di-demote ke Secondary.
     *  3. List disort ulang: Primary di atas.
     */
    public function setKategori(int $index, string $kategori): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $kategori = in_array($kategori, ['Primary', 'Secondary'], true) ? $kategori : 'Secondary';

        // Guard accpdx kalau promosi ke Primary
        if ($kategori === 'Primary') {
            $code = trim((string) ($this->coderDiagnosa[$index]['code'] ?? ''));
            if ($code !== '') {
                $masterAccpdx = DB::table('rsmst_mstdiags')
                    ->where('icdx', $code)
                    ->orWhere('diag_id', $code)
                    ->value('accpdx');
                if ($masterAccpdx !== 'Y') {
                    $this->dispatch('toast', type: 'error', message: "Kode {$code} tidak boleh sebagai diagnosa primer (accpdx='N').");
                    // Reset DOM select element kembali ke value lama (browser keep user's selection)
                    $current = $this->coderDiagnosa[$index]['kategori'] ?? 'Secondary';
                    $this->dispatch('reset-select-kategori-idrg', index: $index, value: $current);
                    return;
                }
            }
        }

        $this->mutate(function ($diagList) use ($index, $kategori) {
            if (!isset($diagList[$index])) {
                return $diagList;
            }
            // Single-Primary invariant: demote Primary lain ke Secondary
            if ($kategori === 'Primary') {
                foreach ($diagList as $i => $diag) {
                    if ($i !== $index && ($diag['kategori'] ?? '') === 'Primary') {
                        $diagList[$i]['kategori'] = 'Secondary';
                    }
                }
            }
            $diagList[$index]['kategori'] = $kategori;
            return $this->sortPrimaryFirst($diagList);
        });
    }

    public function syncFromEmr(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $this->persistEmrSync();
        $this->reloadState();
        $this->dispatch('toast', type: 'success', message: 'Coder diagnosa di-sync dari EMR.');
        $this->dispatch('idrg-section-changed', rjNo: (string) $this->rjNo);
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
        $this->dispatch('idrg-section-changed', rjNo: (string) $this->rjNo);
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

            // Update validcode + simpan info tambahan dari expanded[] response untuk diagnosis
            $expanded = $response['expanded'] ?? $response['data']['expanded'] ?? [];
            if (!empty($idrg['coderDiagnosa']) && !empty($expanded)) {
                $byCode = collect($expanded)->keyBy('code');
                foreach ($idrg['coderDiagnosa'] as &$c) {
                    $code = $c['code'] ?? '';
                    if (isset($byCode[$code])) {
                        $item = $byCode[$code];
                        $c['validcode'] = (string) ($item['validcode'] ?? '');
                        $extra = $item;
                        unset($extra['code'], $extra['validcode']);
                        $c['validInfo'] = $extra;
                    }
                }
                unset($c);
            }
            $idrg['idrgDiagnosaExpanded'] = $expanded;

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

        $this->dispatch('idrg-section-changed', rjNo: (string) $rjNo);
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
        <div class="flex flex-wrap items-center justify-end gap-2 shrink-0">
            <button type="button" wire:click="syncFromEmr" wire:loading.attr="disabled" @disabled($idrgFinal)
                class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 disabled:opacity-50 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                <span wire:loading.remove wire:target="syncFromEmr">↻ Sync dari EMR</span>
                <span wire:loading wire:target="syncFromEmr"><x-loading />...</span>
            </button>
            <x-primary-button type="button" wire:click="setForCurrent" wire:loading.attr="disabled"
                :disabled="$idrgFinal || !$hasClaim || empty($coderDiagnosa)"
                class="!bg-brand hover:!bg-brand/90 min-w-[160px] {{ !empty($idrgDiagnosaString) ? '!bg-emerald-600' : '' }}">
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
                                @php $curKat = ($d['kategori'] ?? 'Secondary') === 'Primary' ? 'Primary' : 'Secondary'; @endphp
                                <x-select-input x-data
                                    @reset-select-kategori-idrg.window="if ($event.detail.index === {{ $i }}) $el.value = $event.detail.value"
                                    wire:change="setKategori({{ $i }}, $event.target.value)"
                                    :disabled="$idrgFinal" class="w-32">
                                    <option value="Primary" @selected($curKat === 'Primary')>Primary</option>
                                    <option value="Secondary" @selected($curKat === 'Secondary')>Secondary</option>
                                </x-select-input>
                            </td>
                            <td class="px-2 py-1.5 text-center align-top">
                                @php
                                    $vc = $d['validcode'] ?? null;
                                    $info = $d['validInfo'] ?? [];
                                    $desc = (string) ($d['desc'] ?? '');

                                    $hasImSuffix = (bool) preg_match('/\(IM\)\s*$/i', $desc);
                                    $apiImFlag = false;
                                    foreach (['im_only', 'imOnly', 'im', 'is_im', 'imonly'] as $k) {
                                        if (!empty($info[$k]) && (string) $info[$k] !== '0') {
                                            $apiImFlag = true;
                                            break;
                                        }
                                    }
                                    $isIm = $hasImSuffix || $apiImFlag;

                                    $reasonApi = $info['description'] ?? $info['message'] ?? $info['validcode_message'] ?? $info['reason'] ?? '';
                                    $reasonFinal = $reasonApi !== ''
                                        ? $reasonApi
                                        : ($isIm
                                            ? 'Kode IM tidak dikenali e-klaim. Coba kode ICD-10 standar tanpa suffix IM.'
                                            : 'Kode tidak dikenali e-klaim (cek typo / kode retired).');

                                    $extraPairs = [];
                                    foreach ($info as $k => $v) {
                                        if (in_array($k, ['description', 'message', 'validcode_message', 'reason'], true)) {
                                            continue;
                                        }
                                        if ($v === null || $v === '' || $v === [] || $v === '0' || $v === 0) {
                                            continue;
                                        }
                                        $extraPairs[] = "{$k}: " . (is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE));
                                    }
                                    $fullJson = !empty($info) ? json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
                                @endphp
                                @if ($vc === '1')
                                    <x-badge variant="success">Valid</x-badge>
                                @elseif ($vc === '0')
                                    <div class="inline-flex flex-col items-start gap-0.5 text-left"
                                        @if ($fullJson) title="{{ $fullJson }}" @endif>
                                        <x-badge variant="danger">{{ $isIm ? 'Kode IM tidak diakui' : 'Tidak Valid' }}</x-badge>
                                        <span class="text-[10px] text-red-600 dark:text-red-400 leading-tight max-w-[220px]">{{ $reasonFinal }}</span>
                                        {{-- Detail metadata API (display/no/metadata) di-hide — info diagnostic, noise utk user. --}}
                                    </div>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            @if (!$idrgFinal)
                                <td class="px-2 py-1.5">
                                    <x-icon-button color="red" wire:click="remove({{ $i }})"
                                        wire:confirm="Hapus diagnosa {{ $d['code'] ?? '' }} dari coder?">
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
            Belum ada diagnosa coder. Tambah via LOV atau klik "Sync dari EMR".
        </p>
    @endif

    {{-- Response string yang terkirim --}}
    @if (!empty($idrgDiagnosaString))
        <div class="px-2 py-1.5 text-xs font-mono text-gray-600 bg-gray-50 rounded dark:bg-gray-800 dark:text-gray-400">
            <span class="text-gray-500">Terkirim:</span> {{ $idrgDiagnosaString }}
        </div>
    @endif

    {{-- Debug: raw expanded[] dari respons API --}}
    @if (!empty($idrgDiagnosaExpanded))
        <details class="px-2 py-1 text-xs border border-gray-200 rounded dark:border-gray-700">
            <summary class="text-gray-500 cursor-pointer hover:text-gray-700 dark:hover:text-gray-300">[debug] raw expanded[] response</summary>
            <pre class="p-2 mt-1 overflow-x-auto text-[10px] leading-tight bg-gray-100 rounded dark:bg-gray-900">{{ json_encode($idrgDiagnosaExpanded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
        </details>
    @endif
</div>
