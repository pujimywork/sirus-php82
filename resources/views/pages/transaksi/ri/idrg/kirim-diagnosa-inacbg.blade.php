<?php
// resources/views/pages/transaksi/ri/idrg/kirim-diagnosa-inacbg.blade.php
// Step 9: Coder Editor + Set Diagnosa INACBG.
// Override koder casemix kalau ada kode iDRG yang IM-only tidak berlaku di INACBG.

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\iDRG\iDrgTrait;

new class extends Component {
    use EmrRITrait, iDrgTrait;

    public ?string $riHdrNo = null;

    // State (mirror dari $idrg di JSON DB).
    public array $coderInacbgDiagnosa = [];
    public array $inacbgDiagnosaExpanded = [];
    public ?string $inacbgDiagnosaString = null;
    public bool $inacbgFinal = false;
    public bool $idrgFinal = false;
    public bool $hasClaim = false;

    /* ===============================
     | LIFECYCLE
     =============================== */
    public function mount(?string $riHdrNo = null): void
    {
        $this->riHdrNo = $riHdrNo;
        $this->reloadState();
    }


    private function reloadState(): void
    {
        if (empty($this->riHdrNo)) {
            $this->coderInacbgDiagnosa = [];
            $this->inacbgDiagnosaString = null;
            $this->inacbgFinal = false;
            $this->idrgFinal = false;
            $this->hasClaim = false;
            return;
        }
        $data = $this->findDataRI($this->riHdrNo);
        if (empty($data)) {
            return;
        }
        $idrg = $data['idrg'] ?? [];

        // Auto-sync pertama kali: prefer dari idrg.coderDiagnosa, fallback EMR.
        $sourceCount = count($idrg['coderDiagnosa'] ?? []);
        $emrCount = count($data['diagnosis'] ?? []);
        if (empty($idrg['coderInacbgDiagnosaSyncedAt']) && empty($idrg['coderInacbgDiagnosa']) && ($sourceCount > 0 || $emrCount > 0)) {
            $this->persistAutoSync();
            $data = $this->findDataRI($this->riHdrNo);
            $idrg = $data['idrg'] ?? [];
        }

        $this->coderInacbgDiagnosa = $idrg['coderInacbgDiagnosa'] ?? [];
        $this->inacbgDiagnosaExpanded = $idrg['inacbgDiagnosaExpanded'] ?? [];
        $this->inacbgDiagnosaString = $idrg['inacbgDiagnosaString'] ?? null;
        $this->inacbgFinal = !empty($idrg['inacbgFinal']);
        $this->idrgFinal = !empty($idrg['idrgFinal']);
        $this->hasClaim = !empty($idrg['nomorSep']);
    }

    /**
     * Persist auto-sync: prefer idrg.coderDiagnosa (iDRG editor), fallback EMR diagnosis[].
     * Dipanggil reloadState saat first-open dan syncFromIdrg saat user klik tombol.
     */
    private function persistAutoSync(): void
    {
        DB::transaction(function () {
            $this->lockRIRow($this->riHdrNo);
            $fresh = $this->findDataRI($this->riHdrNo);
            $idrg = $fresh['idrg'] ?? [];
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
                foreach (($fresh['diagnosis'] ?? []) as $d) {
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
            // Pre-fill validcode + accpdx dari master DB (bulk lookup).
            // INACBG tolak kode IM → tandai invalid.
            // Dedup by code — kalau kode diagnosa sama muncul beberapa kali dari iDRG,
            // ambil 1 baris saja. Prioritaskan entry dengan kategori Primary.
            $indexByCode = [];   // map: normalized code → index di $uniqueDiagnosa
            $uniqueDiagnosa = [];
            foreach ($coder as $diagnosa) {
                $normalizedCode = strtoupper(trim((string) ($diagnosa['code'] ?? '')));
                if ($normalizedCode === '') {
                    continue;
                }
                $existingIndex = $indexByCode[$normalizedCode] ?? null;
                if ($existingIndex === null) {
                    $indexByCode[$normalizedCode] = count($uniqueDiagnosa);
                    $uniqueDiagnosa[] = $diagnosa;
                    continue;
                }
                // Sudah ada — upgrade ke Primary kalau entry baru Primary & yang lama belum.
                $newIsPrimary = ($diagnosa['kategori'] ?? '') === 'Primary';
                $oldIsPrimary = ($uniqueDiagnosa[$existingIndex]['kategori'] ?? '') === 'Primary';
                if ($newIsPrimary && !$oldIsPrimary) {
                    $uniqueDiagnosa[$existingIndex] = $diagnosa;
                }
            }
            $coder = $uniqueDiagnosa;

            $codes = array_values(array_unique(array_filter(array_column($coder, 'code'))));
            if (!empty($codes)) {
                $masters = DB::table('rsmst_mstdiags')
                    ->whereIn('icdx', $codes)
                    ->orWhereIn('diag_id', $codes)
                    ->select('diag_id', 'icdx', 'valid_code', 'accpdx', 'im')
                    // duplikat icdx: keyBy keep baris TERAKHIR → sort asc supaya
                    // baris terbaik (valid_code=1, accpdx='Y') yang menang
                    ->orderBy('valid_code')
                    ->orderBy('accpdx')
                    ->get();
                $byIcdx = $masters->keyBy('icdx');
                $byDiagId = $masters->keyBy('diag_id');
                foreach ($coder as $i => $row) {
                    $m = $byIcdx->get($row['code']) ?? $byDiagId->get($row['code']);
                    if (!$m) {
                        continue;
                    }
                    $isImCode = (bool) preg_match('/\(IM\)\s*$/i', $row['desc'] ?? '') || (int) ($m->im ?? 0) === 1;
                    $coder[$i]['validcode'] = $isImCode ? '0' : (string) ((int) ($m->valid_code ?? 0));
                    $coder[$i]['accpdx'] = (string) ($m->accpdx ?? 'N');
                }
            }

            $idrg['coderInacbgDiagnosa'] = $coder;
            $idrg['coderInacbgDiagnosaSyncedAt'] = Carbon::now()->format('Y-m-d H:i:s');
            $fresh['idrg'] = $idrg;
            $this->updateJsonRI($this->riHdrNo, $fresh);
        });
    }

    /* ===============================
     | EDITOR (CODER) — CRUD
     =============================== */

    #[On('lov.selected.riFormDiagnosaInacbgCoder')]
    public function onLov(string $target, array $payload): void
    {
        if (empty($this->riHdrNo)) {
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
     * Tambah diagnosa baru ke coder editor INACBG.
     *
     * Aturan kategori (Primary/Secondary):
     *  - Caller pass $kategori eksplisit → pakai itu.
     *  - Auto: pertama yg boleh primer → Primary, sisanya Secondary.
     *  - Kode dgn accpdx='N' di master diags TIDAK boleh primer → otomatis Secondary.
     */
    public function add(string $code, string $desc, ?string $kategori = null): void
    {
        if (empty($this->riHdrNo) || empty($code)) {
            return;
        }
        // Duplikat icdx di master bisa beda accpdx (baris valid 'Y' vs retired 'N')
        // → boleh primer jika ADA baris master dgn kode ini yang accpdx='Y'.
        $isAllowedAsPrimary = DB::table('rsmst_mstdiags')
            ->where(function ($q) use ($code) {
                $q->where('icdx', $code)->orWhere('diag_id', $code);
            })
            ->where('accpdx', 'Y')
            ->exists();

        $this->mutate(function ($diagList) use ($code, $desc, $kategori, $isAllowedAsPrimary) {
            $hasExistingPrimary = collect($diagList)->contains(fn($diag) => ($diag['kategori'] ?? '') === 'Primary');
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
     * Sort diagnosa list: Primary di paling atas, Secondary di bawah (stable).
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
        if (empty($this->riHdrNo)) {
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
     * Saat promosi ke Primary:
     *  1. Kode harus accpdx='Y' — kalau tidak, tolak dgn toast.
     *  2. Single-Primary invariant: Primary lain di-demote.
     *  3. List disort: Primary di atas.
     */
    public function setKategori(int $index, string $kategori): void
    {
        if (empty($this->riHdrNo)) {
            return;
        }
        $kategori = in_array($kategori, ['Primary', 'Secondary'], true) ? $kategori : 'Secondary';

        if ($kategori === 'Primary') {
            $code = trim((string) ($this->coderInacbgDiagnosa[$index]['code'] ?? ''));
            if ($code !== '') {
                $allowedPrimary = DB::table('rsmst_mstdiags')
                    ->where(function ($q) use ($code) {
                        $q->where('icdx', $code)->orWhere('diag_id', $code);
                    })
                    ->where('accpdx', 'Y')
                    ->exists();
                if (!$allowedPrimary) {
                    $this->dispatch('toast', type: 'error', message: "Kode {$code} tidak boleh sebagai diagnosa primer (accpdx='N').");
                    $current = $this->coderInacbgDiagnosa[$index]['kategori'] ?? 'Secondary';
                    $this->dispatch('reset-select-kategori-inacbg', index: $index, value: $current);
                    return;
                }
            }
        }

        $this->mutate(function ($diagList) use ($index, $kategori) {
            if (!isset($diagList[$index])) {
                return $diagList;
            }
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

    /**
     * Sync dari iDRG coder editor (idrg.coderDiagnosa) sebagai starting point.
     * Coder lalu identify kode IM-only (validcode=0) dan ganti ke kode non-IM.
     * Fallback ke EMR diagnosis[] kalau iDRG coder belum diisi.
     */
    public function syncFromIdrg(): void
    {
        if (empty($this->riHdrNo)) {
            return;
        }
        $this->persistAutoSync();
        $this->reloadState();
        $this->dispatch('toast', type: 'success', message: 'Coder INACBG diagnosa di-sync dari iDRG.');
        $this->dispatch('idrg-section-changed-ri', riHdrNo: (string) $this->riHdrNo);
    }

    private function mutate(callable $fn): void
    {
        DB::transaction(function () use ($fn) {
            $this->lockRIRow($this->riHdrNo);
            $data = $this->findDataRI($this->riHdrNo);
            $coder = $data['idrg']['coderInacbgDiagnosa'] ?? [];
            $coder = $fn($coder);
            $data['idrg']['coderInacbgDiagnosa'] = $coder;
            $this->updateJsonRI($this->riHdrNo, $data);
        });
        $this->reloadState();
        $this->dispatch('idrg-section-changed-ri', riHdrNo: (string) $this->riHdrNo);
    }

    /* ===============================
     | API ACTION — set_diagnosa_inacbg
     =============================== */

    #[On('idrg-diagnosa-inacbg-ri.set')]
    public function set(string $riHdrNo, ?string $diagnosa = null): void
    {
        try {
            $data = $this->findDataRI($riHdrNo);
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

            // Update validcode + simpan info tambahan dari expanded[] response untuk diagnosis
            $expanded = $response['expanded'] ?? $response['data']['expanded'] ?? [];
            if (!empty($idrg['coderInacbgDiagnosa']) && !empty($expanded)) {
                $byCode = collect($expanded)->keyBy('code');
                foreach ($idrg['coderInacbgDiagnosa'] as &$c) {
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
            $idrg['inacbgDiagnosaExpanded'] = $expanded;

            $this->saveResult($riHdrNo, $idrg);
            $this->dispatch('toast', type: 'success', message: "Diagnosa INACBG tersimpan: {$diagnosa}");
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Set diagnosa INACBG gagal: ' . $e->getMessage());
        }
    }

    public function setForCurrent(): void
    {
        if (empty($this->riHdrNo)) {
            return;
        }
        $this->set($this->riHdrNo);
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

    private function saveResult(string $riHdrNo, array $idrg): void
    {
        DB::transaction(function () use ($riHdrNo, $idrg) {
            $this->lockRIRow($riHdrNo);
            $data = $this->findDataRI($riHdrNo);
            $data['idrg'] = $idrg;
            $this->updateJsonRI($riHdrNo, $data);
        });

        $this->dispatch('idrg-section-changed-ri', riHdrNo: (string) $riHdrNo);
    }
};
?>

<div class="p-4 space-y-3 bg-canvas border border-hairline shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700">
    {{-- Header --}}
    <div class="flex items-start justify-between gap-3">
        <div class="flex items-center gap-3">
            <div
                class="flex items-center justify-center w-8 h-8 rounded-full {{ !empty($inacbgDiagnosaString) ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-surface-soft text-muted-soft dark:bg-gray-800 dark:text-gray-500' }}">
                <span class="text-sm font-bold">10</span>
            </div>
            <div>
                <div class="font-semibold text-ink dark:text-gray-100">Set Diagnosa INACBG</div>
                <div class="text-sm text-muted dark:text-gray-400">
                    Override jika ada kode iDRG dengan "IM tidak berlaku" di INACBG.
                </div>
            </div>
        </div>
        <div class="flex flex-wrap items-center justify-end gap-2 shrink-0">
            <button type="button" wire:click="syncFromIdrg" wire:loading.attr="disabled" @disabled($inacbgFinal)
                class="px-3 py-1.5 text-sm font-medium text-body bg-surface-soft rounded-lg hover:bg-gray-200 disabled:opacity-50 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                <span wire:loading.remove wire:target="syncFromIdrg">↻ Sync dari iDRG</span>
                <span wire:loading wire:target="syncFromIdrg"><x-loading />...</span>
            </button>
        </div>
    </div>

    @if (!$inacbgFinal)
        <div wire:key="lov-diagnosa-inacbg-coder-{{ $riHdrNo ?? 'none' }}">
            <livewire:lov.diagnosa.lov-diagnosa label="Cari Diagnosa (untuk INACBG)" target="riFormDiagnosaInacbgCoder"
                wire:key="lov-diagnosa-inacbg-coder-inner-{{ $riHdrNo ?? 'none' }}" />
        </div>
    @endif

    @if (!empty($coderInacbgDiagnosa))
        <div class="overflow-x-auto border border-hairline rounded-lg dark:border-gray-700">
            <table class="w-full text-sm text-left">
                <thead class="text-muted bg-surface-soft dark:bg-gray-800 dark:text-gray-300">
                    <tr>
                        <th class="px-2 py-1.5 font-medium">Kode</th>
                        <th class="px-2 py-1.5 font-medium">Deskripsi</th>
                        <th class="px-2 py-1.5 font-medium">Kategori</th>
                        <th class="px-2 py-1.5 font-medium text-center">Keterangan</th>
                        @if (!$inacbgFinal)
                            <th class="px-2 py-1.5 w-8"></th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline-soft dark:divide-gray-700">
                    @foreach ($coderInacbgDiagnosa as $i => $d)
                        <tr wire:key="coder-inacbg-diag-{{ $i }}-{{ $d['code'] ?? 'x' }}"
                            class="bg-canvas dark:bg-gray-900">
                            <td class="px-2 py-1.5 font-mono font-semibold text-ink dark:text-gray-100">
                                {{ $d['code'] ?? '' }}</td>
                            <td class="px-2 py-1.5 text-body dark:text-gray-300">{{ $d['desc'] ?? '' }}</td>
                            <td class="px-2 py-1.5">
                                @php $curKat = ($d['kategori'] ?? 'Secondary') === 'Primary' ? 'Primary' : 'Secondary'; @endphp
                                <x-select-input x-data
                                    @reset-select-kategori-inacbg.window="if ($event.detail.index === {{ $i }}) $el.value = $event.detail.value"
                                    wire:change="setKategori({{ $i }}, $event.target.value)"
                                    :disabled="$inacbgFinal" class="w-32">
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
                                            ? 'IM tidak berlaku di INACBG. Ganti dengan kode ICD-10 non-IM.'
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
                                        <x-badge variant="danger">{{ $isIm ? 'IM tidak berlaku' : 'Tidak Valid' }}</x-badge>
                                        <span class="text-[10px] text-red-600 dark:text-red-400 leading-tight max-w-[220px]">{{ $reasonFinal }}</span>
                                        {{-- Detail metadata API di-hide — diagnostic info, noise utk user. --}}
                                    </div>
                                @else
                                    <span class="text-muted-soft">-</span>
                                @endif
                            </td>
                            @if (!$inacbgFinal)
                                <td class="px-2 py-1.5">
                                    <x-icon-button color="red" wire:click="remove({{ $i }})"
                                        wire:confirm="Hapus diagnosa {{ $d['code'] ?? '' }} dari coder INACBG?">
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
        <p class="py-2 text-sm text-center text-muted-soft dark:text-gray-500">
            Belum ada diagnosa INACBG. Klik "Sync dari iDRG" atau tambah via LOV.
        </p>
    @endif

    @if (!empty($inacbgDiagnosaString))
        <div class="px-2 py-1.5 text-sm font-mono text-muted bg-surface-soft rounded dark:bg-gray-800 dark:text-gray-400">
            <span class="text-muted">Terkirim:</span> {{ $inacbgDiagnosaString }}
        </div>
    @endif

    {{-- Debug: raw expanded[] dari respons API --}}
    @if (!empty($inacbgDiagnosaExpanded))
        <details class="px-2 py-1 text-sm border border-hairline rounded dark:border-gray-700">
            <summary class="text-muted cursor-pointer hover:text-body dark:hover:text-gray-300">[debug] raw expanded[] response</summary>
            <pre class="p-2 mt-1 overflow-x-auto text-[10px] leading-tight bg-surface-soft rounded dark:bg-gray-900">{{ json_encode($inacbgDiagnosaExpanded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
        </details>
    @endif
</div>
