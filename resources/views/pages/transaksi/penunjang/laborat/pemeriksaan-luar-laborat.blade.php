<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use Illuminate\Support\Facades\DB;

new class extends Component {

    public string $checkupNo = '';
    #[Reactive]
    public string $labStatus = 'P';
    public array $outDtlRows = [];
    public array $formOutDtl = [
        'laboutDesc' => '',
        'laboutResult' => '',
        'laboutNormal' => '',
    ];

    /* =======================
     | Mount
     * ======================= */
    public function mount(): void
    {
        if ($this->checkupNo) {
            $this->loadOutDtlRows();
        }
    }

    /* =======================
     | Refresh from parent
     * ======================= */
    #[On('pemeriksaan-luar.refresh')]
    public function onRefresh(string $checkupNo = ''): void
    {
        if ($checkupNo) {
            $this->checkupNo = $checkupNo;
        }
        $this->loadOutDtlRows();
    }

    /* =======================
     | LOAD OUT DTL ROWS
     * ======================= */
    private function loadOutDtlRows(): void
    {
        $rows = DB::table('lbtxn_checkupoutdtls')
            ->select('labout_dtl', 'labout_desc', 'labout_result', 'labout_normal')
            ->where('checkup_no', $this->checkupNo)
            ->orderBy('labout_dtl', 'asc')
            ->get();

        $this->outDtlRows = $rows->map(fn($r) => (array) $r)->toArray();
    }

    /* =======================
     | ADD OUT DTL
     * ======================= */
    public function addOutDtl(): void
    {
        if ($this->labStatus !== 'P') {
            $this->dispatch('toast', type: 'warning', message: $this->labStatus === 'C' ? 'Sedang proses input hasil, tidak bisa menambah item.' : 'Hasil sudah disimpan.');
            return;
        }

        // Saat P hanya insert item (desc), hasil diisi nanti saat C

        $this->validate([
            'formOutDtl.laboutDesc' => 'required|string|max:1000',
        ], [
            'formOutDtl.laboutDesc.required' => 'Deskripsi pemeriksaan harus diisi.',
        ]);

        try {
            $dtlNo = DB::scalar('SELECT NVL(TO_NUMBER(MAX(labout_dtl)) + 1, 1) FROM lbtxn_checkupoutdtls');

            DB::table('lbtxn_checkupoutdtls')->insert([
                'checkup_no' => $this->checkupNo,
                'labout_dtl' => $dtlNo,
                'labout_desc' => $this->formOutDtl['laboutDesc'],
                'labout_result' => $this->formOutDtl['laboutResult'] ?: null,
                'labout_normal' => $this->formOutDtl['laboutNormal'] ?: null,
            ]);

            $this->resetOutDtlForm();
            $this->loadOutDtlRows();
            $this->dispatch('lab-tab.updated');
            $this->dispatch('toast', type: 'success', message: 'Item pemeriksaan luar berhasil ditambahkan.');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menambah: ' . $e->getMessage());
        }
    }

    /* =======================
     | UPDATE OUT DTL FIELD
     * ======================= */
    public function updateOutDtlResult(int $laboutDtl, string $field, string $value): void
    {
        // P = hanya desc, C = hanya hasil & normal, H = terkunci
        if ($this->labStatus === 'H') {
            $this->dispatch('toast', type: 'warning', message: 'Hasil LAB sudah disimpan, tidak bisa diubah.');
            return;
        }

        $allowed = $this->labStatus === 'P'
            ? ['labout_desc']
            : ['labout_result', 'labout_normal'];
        if (!in_array($field, $allowed)) {
            return;
        }

        DB::table('lbtxn_checkupoutdtls')
            ->where('checkup_no', $this->checkupNo)
            ->where('labout_dtl', $laboutDtl)
            ->update([$field => $value]);

        $this->loadOutDtlRows();
        $this->dispatch('lab-tab.updated');
    }

    /* =======================
     | DELETE OUT DTL
     * ======================= */
    public function deleteOutDtlRow(int $laboutDtl): void
    {
        if ($this->labStatus !== 'P') {
            $this->dispatch('toast', type: 'warning', message: 'Tidak bisa menghapus, pemeriksaan sudah diproses.');
            return;
        }

        DB::table('lbtxn_checkupoutdtls')
            ->where('checkup_no', $this->checkupNo)
            ->where('labout_dtl', $laboutDtl)
            ->delete();

        $this->loadOutDtlRows();
        $this->dispatch('lab-tab.updated');
        $this->dispatch('toast', type: 'success', message: 'Item berhasil dihapus.');
    }

    /* =======================
     | RESET FORM
     * ======================= */
    private function resetOutDtlForm(): void
    {
        $this->formOutDtl = [
            'laboutDesc' => '',
            'laboutResult' => '',
            'laboutNormal' => '',
        ];
        $this->resetValidation();
    }
};
?>

<div>
    <div class="space-y-4">

        @if ($labStatus === 'P')
            <div class="p-3 text-sm border rounded-lg bg-blue-50 border-blue-200 text-blue-700">
                Status: <strong>Administrasi</strong> — Tambah/hapus item pemeriksaan luar. Klik "Proses Administrasi" untuk lanjut entry hasil.
            </div>
        @elseif ($labStatus === 'H')
            <div class="p-3 text-sm border rounded-lg bg-green-50 border-green-200 text-green-700">
                Status: <strong>Selesai</strong> — Hasil pemeriksaan luar sudah tersimpan dan terkunci.
            </div>
        @endif

        {{-- FORM ADD (hanya saat P, hanya deskripsi) --}}
        @if ($labStatus === 'P')
        <div class="p-4 border rounded-lg bg-gray-50 dark:bg-gray-800 dark:border-gray-700">
            <h4 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-200">Tambah Pemeriksaan Luar</h4>
            <div class="flex items-end gap-3">
                <div class="flex-1">
                    <x-input-label value="Deskripsi Pemeriksaan" />
                    <x-text-input wire:model="formOutDtl.laboutDesc" class="w-full mt-1"
                        placeholder="Nama pemeriksaan..." />
                    @error('formOutDtl.laboutDesc')
                        <span class="text-xs text-red-500">{{ $message }}</span>
                    @enderror
                </div>
                <x-primary-button type="button" wire:click="addOutDtl" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="addOutDtl"
                        class="flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 4v16m8-8H4" />
                        </svg>
                        Tambah
                    </span>
                    <span wire:loading wire:target="addOutDtl" class="flex items-center gap-1.5">
                        <x-loading /> Menyimpan...
                    </span>
                </x-primary-button>
            </div>
        </div>
        @endif

        {{-- OUT DTL TABLE --}}
        <div class="overflow-x-auto border rounded-lg border-gray-200 dark:border-gray-700">
            <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">No</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Deskripsi</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Hasil</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Nilai Normal</th>
                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                    @forelse ($outDtlRows as $idx => $out)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="px-3 py-2 text-gray-500">{{ $idx + 1 }}</td>
                            {{-- Deskripsi: editable saat P, read-only saat C/H --}}
                            <td class="px-3 py-2">
                                @if ($labStatus === 'P')
                                    <x-text-input type="text" value="{{ $out['labout_desc'] ?? '' }}"
                                        wire:change="updateOutDtlResult({{ $out['labout_dtl'] }}, 'labout_desc', $event.target.value)"
                                        class="text-sm" />
                                @else
                                    <div class="font-medium text-gray-900 dark:text-gray-100">{{ $out['labout_desc'] ?? '-' }}</div>
                                @endif
                            </td>
                            {{-- Hasil: editable saat C, read-only saat P/H --}}
                            <td class="px-3 py-2">
                                @if ($labStatus === 'C')
                                    <x-text-input type="text" value="{{ $out['labout_result'] ?? '' }}"
                                        wire:change="updateOutDtlResult({{ $out['labout_dtl'] }}, 'labout_result', $event.target.value)"
                                        class="!w-40 text-sm"
                                        placeholder="Hasil..." />
                                @else
                                    <span class="text-gray-700 dark:text-gray-300">{{ $out['labout_result'] ?? '-' }}</span>
                                @endif
                            </td>
                            {{-- Normal: editable saat C, read-only saat P/H --}}
                            <td class="px-3 py-2">
                                @if ($labStatus === 'C')
                                    <x-text-input type="text" value="{{ $out['labout_normal'] ?? '' }}"
                                        wire:change="updateOutDtlResult({{ $out['labout_dtl'] }}, 'labout_normal', $event.target.value)"
                                        class="!w-40 text-sm"
                                        placeholder="Normal..." />
                                @else
                                    <span class="text-gray-700 dark:text-gray-300">{{ $out['labout_normal'] ?? '-' }}</span>
                                @endif
                            </td>
                            {{-- Aksi: hapus hanya saat P --}}
                            <td class="px-3 py-2 text-center">
                                @if ($labStatus === 'P')
                                    <button type="button"
                                        wire:click="deleteOutDtlRow({{ $out['labout_dtl'] }})"
                                        wire:confirm="Yakin hapus item ini?"
                                        class="text-red-500 hover:text-red-700">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-8 text-center text-gray-400">
                                Belum ada pemeriksaan luar
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
