<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Reactive;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    public string $checkupNo = '';
    public string $sex = '';
    #[Reactive]
    public string $labStatus = 'P'; // P=administrasi, C=input hasil, H=selesai
    public array $dtlRows = [];

    // Item picker (grid card style seperti RJ)
    public string $searchLabItem = '';
    public array $selectedItems = []; // [ clabitem_id => [...item] ]

    /* =======================
     | Mount
     * ======================= */
    public function mount(): void
    {
        if ($this->checkupNo) {
            $this->loadDtlRows();
        }
    }

    /* =======================
     | Status berubah → reload rows (query filter beda per status)
     * ======================= */
    public function updatedLabStatus(): void
    {
        $this->loadDtlRows();
    }

    /* =======================
     | Refresh from parent
     * ======================= */
    #[On('pemeriksaan-lab.refresh')]
    public function onRefresh(string $checkupNo = '', string $sex = ''): void
    {
        if ($checkupNo) {
            $this->checkupNo = $checkupNo;
        }
        if ($sex) {
            $this->sex = $sex;
        }
        $this->loadDtlRows();
    }

    /* =======================
     | LOAD DTL ROWS
     * ======================= */
    private function loadDtlRows(): void
    {
        $query = DB::table('lbtxn_checkupdtls as a')
            ->join('lbmst_clabitems as b', 'a.clabitem_id', '=', 'b.clabitem_id')
            ->select(
                'a.checkup_dtl',
                'a.clabitem_id',
                'b.clabitem_desc',
                'a.lab_result',
                'a.lab_result_status',
                'a.lab_item_code',
                'a.price',
                'b.normal_m',
                'b.normal_f',
                'b.unit_desc',
                'b.unit_convert',
                'b.lowhigh_status',
                'b.low_limit_m',
                'b.high_limit_m',
                'b.low_limit_f',
                'b.high_limit_f',
                'b.is_group',
                'b.clabitem_group',
            )
            ->where('a.checkup_no', $this->checkupNo);

        // Status P (administrasi): hanya tampilkan item yg punya price (biaya)
        // Status C/H: tampilkan semua item (untuk input/lihat hasil)
        if ($this->labStatus === 'P') {
            $query->whereNotNull('a.price');
        }

        $rows = $query->orderBy('a.checkup_dtl', 'asc')->get();

        $isProses = $this->labStatus === 'C';

        $this->dtlRows = $rows->map(function ($r) use ($isProses) {
            $row = (array) $r;
            // Status C: nilai normal mentah (tanpa konversi, tanpa satuan) supaya sesuai input
            // Status H/P: nilai normal dikonversi + satuan
            $row['nilai_normal_display'] = $isProses
                ? $this->formatNilaiNormalRaw($row)
                : $this->formatNilaiNormal($row);
            $row['hasil_display'] = $this->formatHasilDisplay($row);
            return $row;
        })->toArray();
    }

    /* =======================
     | FORMAT NILAI NORMAL (port dari Oracle normal_M / normal_F)
     | Logika: lowhigh_status=Y → hitung dari limit * unit_convert
     |         lowhigh_status!=Y → tampilkan normal_M/F apa adanya
     * ======================= */
    private function formatNilaiNormal(array $item): string
    {
        $lowhighStatus = $item['lowhigh_status'] ?? 'N';
        $unitConvert = floatval($item['unit_convert'] ?? 1) ?: 1;
        $unitDesc = $item['unit_desc'] ?? '';

        // Pilih limit berdasarkan jenis kelamin
        $lowLimit = $this->sex === 'P' ? ($item['low_limit_f'] ?? null) : ($item['low_limit_m'] ?? null);
        $highLimit = $this->sex === 'P' ? ($item['high_limit_f'] ?? null) : ($item['high_limit_m'] ?? null);
        $normalText = $this->sex === 'P' ? ($item['normal_f'] ?? '') : ($item['normal_m'] ?? '');

        if ($lowhighStatus === 'Y') {
            // Format number berdasarkan panjang unit_convert
            $formatFn = function ($val) use ($unitConvert) {
                $result = floatval($val) * $unitConvert;
                // Kalau unit_convert > 1, format dengan ribuan
                if ($unitConvert >= 1000) {
                    return number_format($result, 0, ',', ',');
                }
                // Bulatkan sesuai presisi
                $rounded = round($result, 2);
                return rtrim(rtrim(number_format($rounded, 2, '.', ''), '0'), '.');
            };

            if ($lowLimit === null && $highLimit !== null) {
                $x = '> ' . $formatFn($highLimit);
            } elseif ($lowLimit !== null && $highLimit === null) {
                $x = '< ' . $formatFn($lowLimit);
            } elseif ($lowLimit !== null && $highLimit !== null) {
                $x = $formatFn($lowLimit) . ' - ' . $formatFn($highLimit);
            } else {
                return '-';
            }

            return trim($x . '  ' . $unitDesc);
        }

        // Non-numeric: tampilkan normal text + unit
        if (!empty($normalText)) {
            return trim($normalText . '  ' . $unitDesc);
        }

        return '-';
    }

    /* =======================
     | FORMAT NILAI NORMAL RAW (tanpa unit_convert, tanpa satuan)
     | Dipakai saat status C supaya sesuai angka yang diinput user
     * ======================= */
    private function formatNilaiNormalRaw(array $item): string
    {
        $lowhighStatus = $item['lowhigh_status'] ?? 'N';
        $lowLimit = $this->sex === 'P' ? ($item['low_limit_f'] ?? null) : ($item['low_limit_m'] ?? null);
        $highLimit = $this->sex === 'P' ? ($item['high_limit_f'] ?? null) : ($item['high_limit_m'] ?? null);
        $normalText = $this->sex === 'P' ? ($item['normal_f'] ?? '') : ($item['normal_m'] ?? '');

        if ($lowhighStatus === 'Y') {
            if ($lowLimit === null && $highLimit !== null) {
                return '> ' . $highLimit;
            } elseif ($lowLimit !== null && $highLimit === null) {
                return '< ' . $lowLimit;
            } elseif ($lowLimit !== null && $highLimit !== null) {
                return $lowLimit . ' - ' . $highLimit;
            }
            return '-';
        }

        return !empty($normalText) ? $normalText : '-';
    }

    /* =======================
     | FORMAT HASIL DISPLAY (lab_result * unit_convert + satuan)
     | Di DB: lab_result disimpan setelah dibagi unit_convert
     | Di tampilan: perlu dikalikan balik agar sesuai nilai normal
     * ======================= */
    private function formatHasilDisplay(array $item): string
    {
        $labResult = $item['lab_result'] ?? '';
        if ($labResult === '' || $labResult === null) {
            return '-';
        }

        $lowhighStatus = $item['lowhigh_status'] ?? 'N';
        $unitConvert = floatval($item['unit_convert'] ?? 1) ?: 1;
        $unitDesc = $item['unit_desc'] ?? '';

        if ($lowhighStatus === 'Y' && is_numeric($labResult)) {
            $displayValue = floatval($labResult) * $unitConvert;

            // Format: kalau besar pakai ribuan, kalau kecil tampilkan desimal
            if ($unitConvert >= 1000) {
                $formatted = number_format($displayValue, 0, ',', ',');
            } else {
                $rounded = round($displayValue, 2);
                $formatted = rtrim(rtrim(number_format($rounded, 2, '.', ''), '0'), '.');
            }

            return trim($formatted . '  ' . $unitDesc);
        }

        // Non-numeric: tampilkan apa adanya + satuan
        return trim($labResult . ($unitDesc ? '  ' . $unitDesc : ''));
    }

    /* =======================
     | COMPUTED: ITEM GRID (paginated)
     * ======================= */
    #[Computed]
    public function items()
    {
        $search = trim($this->searchLabItem);

        return DB::table('lbmst_clabitems')
            ->select('clabitem_id', 'clabitem_desc', 'price', 'item_code')
            ->whereNull('clabitem_group')
            ->whereNotNull('clabitem_desc')
            ->when($search, fn($q) => $q->whereRaw('UPPER(clabitem_desc) LIKE ?', ['%' . mb_strtoupper($search) . '%']))
            ->orderBy('clabitem_desc', 'asc')
            ->paginate(15);
    }

    public function updatedSearchLabItem(): void
    {
        $this->resetPage();
    }

    /* =======================
     | TOGGLE / REMOVE SELECTED ITEM
     * ======================= */
    public function toggleItem(string $id, string $desc, $price, ?string $itemCode): void
    {
        if (isset($this->selectedItems[$id])) {
            unset($this->selectedItems[$id]);
        } else {
            $this->selectedItems[$id] = [
                'clabitem_id' => $id,
                'clabitem_desc' => $desc,
                'price' => $price,
                'item_code' => $itemCode,
            ];
        }
    }

    public function isSelected(string $id): bool
    {
        return isset($this->selectedItems[$id]);
    }

    public function removeSelected(string $id): void
    {
        unset($this->selectedItems[$id]);
    }

    /* =======================
     | TAMBAH ITEM TERPILIH (batch insert)
     * ======================= */
    public function tambahItemTerpilih(): void
    {
        if ($this->labStatus !== 'P') {
            $this->dispatch('toast', type: 'warning', message: 'Tidak bisa menambah item, pemeriksaan sudah diproses.');
            return;
        }

        if (empty($this->selectedItems)) {
            $this->dispatch('toast', type: 'warning', message: 'Pilih minimal satu item pemeriksaan.');
            return;
        }

        try {
            DB::transaction(function () {
                foreach ($this->selectedItems as $item) {
                    $this->insertItemAndChildren($item);
                }
            });

            $count = count($this->selectedItems);
            $this->selectedItems = [];
            $this->searchLabItem = '';
            $this->resetPage();
            $this->loadDtlRows();
            $this->dispatch('lab-tab.updated');
            $this->dispatch('toast', type: 'success', message: "{$count} item pemeriksaan berhasil ditambahkan.");
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menambah item: ' . $e->getMessage());
        }
    }

    /**
     * Insert satu item + child items (group) ke lbtxn_checkupdtls.
     */
    private function insertItemAndChildren(array $item): void
    {
        $clabitemId = $item['clabitem_id'];

        // Insert parent item dulu (yang punya price)
        $dtlNo = DB::scalar('SELECT NVL(TO_NUMBER(MAX(checkup_dtl)) + 1, 1) FROM lbtxn_checkupdtls');

        DB::table('lbtxn_checkupdtls')->insert([
            'clabitem_id' => $clabitemId,
            'checkup_no' => $this->checkupNo,
            'checkup_dtl' => $dtlNo,
            'lab_item_code' => $item['item_code'] ?? null,
            'price' => $item['price'] ?? null,
        ]);

        // Insert child items (yang clabitem_group = parent, tanpa price)
        $children = DB::table('lbmst_clabitems')
            ->where('clabitem_group', $clabitemId)
            ->orderBy('item_seq')
            ->orderBy('clabitem_desc')
            ->get();

        foreach ($children as $child) {
            $childDtlNo = DB::scalar('SELECT NVL(TO_NUMBER(MAX(checkup_dtl)) + 1, 1) FROM lbtxn_checkupdtls');

            DB::table('lbtxn_checkupdtls')->insert([
                'clabitem_id' => $child->clabitem_id,
                'checkup_no' => $this->checkupNo,
                'checkup_dtl' => $childDtlNo,
                'lab_item_code' => $child->item_code,
            ]);
        }
    }

    /* =======================
     | UPDATE LAB RESULT
     * ======================= */
    public function updateLabResult(int $checkupDtl, ?string $value): void
    {
        // Hanya bisa input hasil saat status C (proses)
        if ($this->labStatus !== 'C') {
            $this->dispatch('toast', type: 'warning', message: $this->labStatus === 'P' ? 'Proses administrasi dulu sebelum input hasil.' : 'Hasil LAB sudah disimpan, tidak bisa diubah.');
            return;
        }

        $value = trim($value ?? '');

        // If empty, clear result and status
        if ($value === '') {
            DB::table('lbtxn_checkupdtls')
                ->where('checkup_no', $this->checkupNo)
                ->where('checkup_dtl', $checkupDtl)
                ->update([
                    'lab_result' => null,
                    'lab_result_status' => null,
                ]);

            $this->loadDtlRows();
            $this->dispatch('lab-tab.updated');
            return;
        }

        // Get item info
        $item = collect($this->dtlRows)->firstWhere('checkup_dtl', $checkupDtl);
        if (!$item) {
            return;
        }

        $resultStatus = null;

        if ($item['lowhigh_status'] === 'Y' && is_numeric($value)) {
            // Numeric comparison
            $unitConvert = floatval($item['unit_convert'] ?? 1) ?: 1;
            $numValue = floatval($value) / $unitConvert;

            $lowLimit = $this->sex === 'P'
                ? ($item['low_limit_f'] ?? null)
                : ($item['low_limit_m'] ?? null);
            $highLimit = $this->sex === 'P'
                ? ($item['high_limit_f'] ?? null)
                : ($item['high_limit_m'] ?? null);

            if ($lowLimit === null && $highLimit !== null) {
                // Only high_limit exists
                $resultStatus = $numValue <= floatval($highLimit) ? 'L' : null;
            } elseif ($lowLimit !== null && $highLimit === null) {
                // Only low_limit exists
                $resultStatus = $numValue >= floatval($lowLimit) ? 'H' : null;
            } elseif ($lowLimit !== null && $highLimit !== null) {
                // Both exist
                if ($numValue < floatval($lowLimit)) {
                    $resultStatus = 'L';
                } elseif ($numValue > floatval($highLimit)) {
                    $resultStatus = 'H';
                }
                // between low and high → null (normal)
            }
        } elseif ($item['lowhigh_status'] !== 'Y') {
            // Text comparison
            $normalValue = $this->sex === 'P'
                ? ($item['normal_f'] ?? '')
                : ($item['normal_m'] ?? '');

            if ($normalValue !== '' && $value !== $normalValue) {
                $resultStatus = 'R';
            }
        }

        DB::table('lbtxn_checkupdtls')
            ->where('checkup_no', $this->checkupNo)
            ->where('checkup_dtl', $checkupDtl)
            ->update([
                'lab_result' => $value,
                'lab_result_status' => $resultStatus,
            ]);

        $this->loadDtlRows();
        $this->dispatch('lab-tab.updated');
    }

    /* =======================
     | IMPORT HASIL MINDRAY
     * ======================= */
    public function importMindray(): void
    {
        if (empty($this->checkupNo)) {
            return;
        }

        if ($this->labStatus !== 'C') {
            $this->dispatch('toast', type: 'warning', message: $this->labStatus === 'P' ? 'Proses administrasi dulu sebelum import hasil.' : 'Hasil LAB sudah disimpan.');
            return;
        }

        try {
            // 1. Query hasil dari DB Mindray (Oracle terpisah)
            $mindrayResults = DB::connection('oracle_mindray')->select("
                SELECT a.PatientName, b.ItemCode, b.Value, b.Low, b.High
                FROM tblSpecimenInfo a
                JOIN tblTestResult b ON a.SpecimenID = b.SpecimenID
                WHERE a.SpecimenID = :cno
            ", ['cno' => $this->checkupNo]);

            if (empty($mindrayResults)) {
                $this->dispatch('toast', type: 'warning', message: 'Data Mindray tidak ditemukan untuk checkup ini.');
                return;
            }

            DB::transaction(function () use ($mindrayResults) {
                // 2. Update patient_name di header
                $patientName = $mindrayResults[0]->patientname ?? null;
                if ($patientName) {
                    DB::table('lbtxn_checkuphdrs')
                        ->where('checkup_no', $this->checkupNo)
                        ->update(['patient_name' => $patientName]);
                }

                // 3. Update lab_result per item berdasarkan item_code
                foreach ($mindrayResults as $mr) {
                    $itemCode = strtoupper(trim($mr->itemcode ?? ''));
                    $value = $mr->value ?? null;

                    if (empty($itemCode) || $value === null) {
                        continue;
                    }

                    $labResult = round((float) $value, 1);

                    // Konversi khusus alat baru (low & high null = alat baru)
                    if ($mr->low === null && $mr->high === null) {
                        $labResult = match ($itemCode) {
                            'HGB'    => round((float) $value / 10, 1),
                            'MCHC'   => round((float) $value / 10, 1),
                            'RDW-CV' => round((float) $value / 100, 1),
                            'PCT'    => round((float) $value * 10, 1),
                            default  => $labResult,
                        };
                    }

                    DB::table('lbtxn_checkupdtls')
                        ->where('checkup_no', $this->checkupNo)
                        ->where('lab_item_code', $itemCode)
                        ->update(['lab_result' => (string) $labResult]);
                }

                // 4. Zero out item tertentu
                $zeroItems = ['EO00006', 'BA00007', 'BA00008'];
                DB::table('lbtxn_checkupdtls')
                    ->where('checkup_no', $this->checkupNo)
                    ->whereIn('clabitem_id', $zeroItems)
                    ->update(['lab_result' => '0']);

                // 5. Hitung lab_result_status untuk semua item
                $this->recalculateAllResultStatus();
            });

            $this->loadDtlRows();
            $this->dispatch('lab-tab.updated');
            $this->dispatch('toast', type: 'success', message: count($mindrayResults) . ' hasil Mindray berhasil diimpor.');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal import Mindray: ' . $e->getMessage());
        }
    }

    /* =======================
     | RECALCULATE ALL LAB RESULT STATUS
     * ======================= */
    private function recalculateAllResultStatus(): void
    {
        $items = DB::table('lbtxn_checkupdtls as a')
            ->join('lbmst_clabitems as b', 'a.clabitem_id', '=', 'b.clabitem_id')
            ->select(
                'a.checkup_dtl',
                'a.lab_result',
                'b.lowhigh_status',
                'b.unit_convert',
                'b.low_limit_m', 'b.high_limit_m',
                'b.low_limit_f', 'b.high_limit_f',
                'b.normal_m', 'b.normal_f',
            )
            ->where('a.checkup_no', $this->checkupNo)
            ->get();

        foreach ($items as $item) {
            $value = trim($item->lab_result ?? '');

            if ($value === '') {
                DB::table('lbtxn_checkupdtls')
                    ->where('checkup_no', $this->checkupNo)
                    ->where('checkup_dtl', $item->checkup_dtl)
                    ->update(['lab_result_status' => null]);
                continue;
            }

            $resultStatus = null;

            if (($item->lowhigh_status ?? 'N') === 'Y' && is_numeric($value)) {
                $numValue = (float) $value;
                $lowLimit = $this->sex === 'P' ? $item->low_limit_f : $item->low_limit_m;
                $highLimit = $this->sex === 'P' ? $item->high_limit_f : $item->high_limit_m;

                if ($lowLimit === null && $highLimit !== null) {
                    $resultStatus = $numValue <= (float) $highLimit ? 'L' : null;
                } elseif ($lowLimit !== null && $highLimit === null) {
                    $resultStatus = $numValue >= (float) $lowLimit ? 'H' : null;
                } elseif ($lowLimit !== null && $highLimit !== null) {
                    if ($numValue < (float) $lowLimit) {
                        $resultStatus = 'L';
                    } elseif ($numValue > (float) $highLimit) {
                        $resultStatus = 'H';
                    }
                }
            } elseif (($item->lowhigh_status ?? 'N') !== 'Y') {
                $normalValue = $this->sex === 'P' ? ($item->normal_f ?? '') : ($item->normal_m ?? '');
                if ($normalValue !== '' && $value !== $normalValue) {
                    $resultStatus = 'R';
                }
            }

            DB::table('lbtxn_checkupdtls')
                ->where('checkup_no', $this->checkupNo)
                ->where('checkup_dtl', $item->checkup_dtl)
                ->update(['lab_result_status' => $resultStatus]);
        }
    }

    /* =======================
     | DELETE DTL ITEM
     * ======================= */
    public function deleteDtlRow(int $checkupDtl): void
    {
        if ($this->labStatus !== 'P') {
            $this->dispatch('toast', type: 'warning', message: 'Tidak bisa menghapus item, pemeriksaan sudah diproses.');
            return;
        }

        // Ambil clabitem_id dari row yang akan dihapus
        $row = DB::table('lbtxn_checkupdtls')
            ->where('checkup_no', $this->checkupNo)
            ->where('checkup_dtl', $checkupDtl)
            ->first();

        if (!$row) {
            return;
        }

        DB::transaction(function () use ($row, $checkupDtl) {
            // 1. Hapus children (item yang clabitem_group = clabitem_id ini)
            DB::table('lbtxn_checkupdtls')
                ->where('checkup_no', $this->checkupNo)
                ->whereIn('clabitem_id', function ($q) use ($row) {
                    $q->select('clabitem_id')
                      ->from('lbmst_clabitems')
                      ->where('clabitem_group', $row->clabitem_id);
                })
                ->delete();

            // 2. Hapus item itu sendiri
            DB::table('lbtxn_checkupdtls')
                ->where('checkup_no', $this->checkupNo)
                ->where('checkup_dtl', $checkupDtl)
                ->delete();
        });

        $this->loadDtlRows();
        $this->dispatch('lab-tab.updated');
        $this->dispatch('toast', type: 'success', message: 'Item berhasil dihapus.');
    }
};
?>

<div>
    <div class="space-y-4">

        {{-- STATUS INFO --}}
        @if ($labStatus === 'P')
            <div class="p-3 text-sm border rounded-lg bg-blue-50 border-blue-200 text-blue-700">
                Status: <strong>Administrasi</strong> — Tambah/hapus item pemeriksaan. Klik "Proses Administrasi" untuk lanjut entry hasil.
            </div>
        @elseif ($labStatus === 'H')
            <div class="p-3 text-sm border rounded-lg bg-green-50 border-green-200 text-green-700">
                Status: <strong>Selesai</strong> — Hasil laboratorium sudah tersimpan dan terkunci.
            </div>
        @endif

        {{-- ITEM PICKER GRID (hanya tampil saat P) --}}
        @if ($labStatus === 'P')

            {{-- Selected Items Chips --}}
            @if (!empty($selectedItems))
                <div class="px-4 py-3 border rounded-lg border-brand-green/20 bg-brand-green/5">
                    <p class="mb-2 text-xs font-semibold text-brand-green">
                        {{ count($selectedItems) }} item dipilih:
                    </p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($selectedItems as $id => $sel)
                            <span
                                class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium border rounded-full bg-brand-green/10 text-brand-green border-brand-green/20">
                                {{ $sel['clabitem_desc'] }}
                                @if ($sel['price'])
                                    <span class="text-brand-green/60">· {{ number_format($sel['price']) }}</span>
                                @endif
                                <button type="button" wire:click="removeSelected('{{ $id }}')"
                                    class="ml-0.5 hover:text-red-500 transition-colors">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </span>
                        @endforeach
                    </div>

                    {{-- Tombol Tambah --}}
                    <div class="flex items-center justify-between mt-3">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 text-sm font-medium text-brand-green bg-brand-green/10 border border-brand-green/30 rounded-full">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            {{ count($selectedItems) }} item dipilih
                        </span>
                        <x-primary-button type="button" wire:click="tambahItemTerpilih"
                            wire:loading.attr="disabled" wire:target="tambahItemTerpilih">
                            <span wire:loading.remove wire:target="tambahItemTerpilih" class="flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Tambah Pemeriksaan Terpilih
                            </span>
                            <span wire:loading wire:target="tambahItemTerpilih" class="flex items-center gap-1.5">
                                <x-loading /> Menyimpan...
                            </span>
                        </x-primary-button>
                    </div>
                </div>
            @endif

            {{-- Search --}}
            <div class="relative">
                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <x-text-input type="text" wire:model.live.debounce.300ms="searchLabItem"
                    placeholder="Cari item pemeriksaan..."
                    class="!w-full pl-10" />
            </div>

            {{-- Item Grid --}}
            <div class="p-3 overflow-y-auto border rounded-lg max-h-72 bg-gray-50/70 dark:bg-gray-950/20 border-gray-200 dark:border-gray-700">
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                    @forelse ($this->items as $item)
                        @php $selected = $this->isSelected($item->clabitem_id); @endphp
                        <button type="button"
                            wire:click="toggleItem('{{ $item->clabitem_id }}', '{{ addslashes($item->clabitem_desc) }}', {{ $item->price ?? 'null' }}, '{{ $item->item_code }}')"
                            class="relative flex flex-col items-center justify-center p-3 rounded-xl border-2 text-center transition-all
                                {{ $selected
                                    ? 'border-brand-green bg-brand-green/10 text-brand-green shadow-sm'
                                    : 'border-gray-200 bg-white hover:border-brand-green/40 hover:bg-brand-green/5 text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300' }}">

                            @if ($selected)
                                <span class="absolute top-1.5 right-1.5 flex items-center justify-center w-4 h-4 bg-brand-green rounded-full">
                                    <svg class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                    </svg>
                                </span>
                            @endif

                            <p class="text-xs font-medium leading-tight">{{ $item->clabitem_desc }}</p>

                            @if ($item->price)
                                <p class="mt-1 text-[10px] {{ $selected ? 'text-brand-green/70' : 'text-gray-400' }}">
                                    {{ number_format($item->price) }}
                                </p>
                            @endif
                        </button>
                    @empty
                        <div class="py-8 text-center text-gray-400 col-span-full">
                            <p class="text-sm">Tidak ada item ditemukan</p>
                        </div>
                    @endforelse
                </div>

                @if ($this->items->hasPages())
                    <div class="mt-3">
                        {{ $this->items->links() }}
                    </div>
                @endif
            </div>

        @endif

        {{-- TOMBOL MINDRAY (hanya saat status C) --}}
        @if ($labStatus === 'C')
        <div class="flex items-center gap-2">
            <x-secondary-button type="button" wire:click="importMindray" wire:loading.attr="disabled"
                wire:target="importMindray"
                class="!bg-blue-50 !text-blue-700 !border-blue-300 hover:!bg-blue-100">
                <span wire:loading.remove wire:target="importMindray" class="flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Import Hasil Mindray
                </span>
                <span wire:loading wire:target="importMindray" class="flex items-center gap-1.5">
                    <x-loading /> Mengambil data...
                </span>
            </x-secondary-button>
            <span class="text-xs text-gray-400">Ambil hasil otomatis dari alat Mindray</span>
        </div>
        @endif

        {{-- DTL TABLE --}}
        <div x-data class="overflow-x-auto border rounded-lg border-gray-200 dark:border-gray-700">
            <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    @if ($labStatus === 'P')
                        {{-- TABEL ADMINISTRASI: No, Item, Harga, Aksi --}}
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">No</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Item Pemeriksaan</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500">Harga</th>
                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500">Aksi</th>
                        </tr>
                    @else
                        {{-- TABEL HASIL: No, Item, Hasil, Satuan, Normal, Status [+Harga saat H] --}}
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">No</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Item Pemeriksaan</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Hasil</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Nilai Normal</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Status</th>
                            @if ($labStatus === 'H')
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500">Harga</th>
                            @endif
                        </tr>
                    @endif
                </thead>
                <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                    @if ($labStatus === 'P')
                        {{-- ===== BARIS ADMINISTRASI ===== --}}
                        @php $totalPrice = 0; @endphp
                        @forelse ($dtlRows as $idx => $dtl)
                            @php $totalPrice += (int) ($dtl['price'] ?? 0); @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                <td class="px-3 py-2 text-gray-500">{{ $idx + 1 }}</td>
                                <td class="px-3 py-2">
                                    <div class="font-medium text-gray-900 dark:text-gray-100">
                                        {{ $dtl['clabitem_desc'] ?? '-' }}
                                    </div>
                                    <div class="text-xs text-gray-400">{{ $dtl['clabitem_id'] }}</div>
                                </td>
                                <td class="px-3 py-2 text-right font-medium tabular-nums">
                                    Rp {{ number_format($dtl['price'] ?? 0) }}
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <button type="button"
                                        wire:click="deleteDtlRow({{ $dtl['checkup_dtl'] }})"
                                        wire:confirm="Yakin hapus item ini?"
                                        class="text-red-500 hover:text-red-700">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-3 py-8 text-center text-gray-400">
                                    Belum ada item pemeriksaan
                                </td>
                            </tr>
                        @endforelse
                </tbody>
                @if (count($dtlRows))
                    <tfoot class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <td colspan="2" class="px-3 py-2 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">
                                Total Pemeriksaan:</td>
                            <td class="px-3 py-2 text-right text-sm font-bold text-brand tabular-nums">
                                Rp {{ number_format($totalPrice) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif

                    @else
                        {{-- ===== BARIS HASIL LAB ===== --}}
                        @php $totalPriceHasil = 0; @endphp
                        @forelse ($dtlRows as $idx => $dtl)
                            @php $totalPriceHasil += (int) ($dtl['price'] ?? 0); @endphp
                            @php
                                $normal = $dtl['nilai_normal_display'] ?? '-';
                                $resultStatus = $dtl['lab_result_status'] ?? '';
                                $statusColor = match($resultStatus) {
                                    'H' => 'text-red-600 font-bold',
                                    'L' => 'text-blue-600 font-bold',
                                    'N' => 'text-green-600',
                                    'R' => 'text-orange-600 font-bold',
                                    default => 'text-gray-400',
                                };
                                $statusLabel = match($resultStatus) {
                                    'H' => 'Tinggi',
                                    'L' => 'Rendah',
                                    'N' => 'Normal',
                                    'R' => 'Abnormal',
                                    default => '-',
                                };
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                <td class="px-3 py-2 text-gray-500">{{ $idx + 1 }}</td>
                                <td class="px-3 py-2">
                                    <div class="font-medium text-gray-900 dark:text-gray-100">
                                        {{ $dtl['clabitem_desc'] ?? '-' }}
                                    </div>
                                    <div class="text-xs text-gray-400">{{ $dtl['clabitem_id'] }}</div>
                                </td>
                                <td class="px-3 py-2">
                                    @if ($labStatus === 'C')
                                        <x-text-input type="text"
                                            value="{{ $dtl['lab_result'] ?? '' }}"
                                            wire:change="updateLabResult({{ $dtl['checkup_dtl'] }}, $event.target.value)"
                                            x-ref="hasil{{ $idx }}"
                                            x-on:keydown.enter.prevent="$event.target.blur(); $nextTick(() => $refs.hasil{{ $idx + 1 }}?.focus())"
                                            class="!w-28 text-sm"
                                            placeholder="Hasil..." />
                                    @else
                                        <span class="text-gray-700 dark:text-gray-300">{{ $dtl['hasil_display'] ?? '-' }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-gray-500">{{ $normal }}</td>
                                <td class="px-3 py-2 {{ $statusColor }}">{{ $statusLabel }}</td>
                                @if ($labStatus === 'H')
                                    <td class="px-3 py-2 text-right font-medium tabular-nums">
                                        @if ($dtl['price'])
                                            Rp {{ number_format($dtl['price']) }}
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $labStatus === 'H' ? 6 : 5 }}" class="px-3 py-8 text-center text-gray-400">
                                    Belum ada item pemeriksaan
                                </td>
                            </tr>
                        @endforelse
                </tbody>
                @if ($labStatus === 'H' && count($dtlRows) && $totalPriceHasil > 0)
                    <tfoot class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <td colspan="5" class="px-3 py-2 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">
                                Total Pemeriksaan:</td>
                            <td class="px-3 py-2 text-right text-sm font-bold text-brand tabular-nums">
                                Rp {{ number_format($totalPriceHasil) }}</td>
                        </tr>
                    </tfoot>
                @endif
                    @endif
            </table>
        </div>
    </div>
</div>
