<?php
// resources/views/pages/transaksi/ugd/administrasi-ugd/obat-ugd.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;

new class extends Component {
    use EmrUGDTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $rjObat = [];

    /* ═══════════════════════════════════════
     | FIND DATA
    ═══════════════════════════════════════ */
    private function findData(int $rjNo): void
    {
        $rows = DB::table('rstxn_ugdobats')->join('immst_products', 'immst_products.product_id', 'rstxn_ugdobats.product_id')->select('rstxn_ugdobats.rjobat_dtl', 'rstxn_ugdobats.product_id', 'immst_products.product_name', 'rstxn_ugdobats.qty', 'rstxn_ugdobats.price', 'rstxn_ugdobats.rj_carapakai', 'rstxn_ugdobats.rj_kapsul', 'rstxn_ugdobats.rj_takar', 'rstxn_ugdobats.catatan_khusus')->where('rstxn_ugdobats.rj_no', $rjNo)->orderBy('rstxn_ugdobats.rjobat_dtl')->get();

        $this->rjObat = $rows
            ->map(
                fn($r) => [
                    'rjobatDtl' => (int) $r->rjobat_dtl,
                    'productId' => $r->product_id,
                    'productName' => $r->product_name,
                    'qty' => (int) $r->qty,
                    'price' => (int) $r->price,
                    'total' => (int) $r->qty * (int) $r->price,
                    'carapakai' => $r->rj_carapakai,
                    'kapsul' => $r->rj_kapsul,
                    'takar' => $r->rj_takar,
                    'catatan' => $r->catatan_khusus,
                ],
            )
            ->toArray();
    }

    /* ═══════════════════════════════════════
     | REFRESH
    ═══════════════════════════════════════ */
    #[On('administrasi-obat-ugd.updated')]
    public function onAdministrasiUpdated(): void
    {
        if ($this->rjNo) {
            $this->findData($this->rjNo);
        }
    }

    /* ═══════════════════════════════════════
     | REMOVE
    ═══════════════════════════════════════ */
    public function removeObat(int $rjobatDtl): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }

        try {
            DB::transaction(function () use ($rjobatDtl) {
                DB::table('rstxn_ugdobats')->where('rjobat_dtl', $rjobatDtl)->delete();
                $this->rjObat = collect($this->rjObat)->where('rjobatDtl', '!=', $rjobatDtl)->values()->toArray();
            });

            $this->dispatch('administrasi-ugd.updated');
            $this->dispatch('toast', type: 'success', message: 'Obat berhasil dihapus.');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ═══════════════════════════════════════
     | LIFECYCLE
    ═══════════════════════════════════════ */
    public function mount(): void
    {
        if ($this->rjNo) {
            $this->findData($this->rjNo);
            $this->isFormLocked = $this->checkUGDStatus($this->rjNo);
        }
    }
};
?>

<div class="space-y-4">

    {{-- LOCKED BANNER --}}
    @if ($isFormLocked)
        <div
            class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
            Pasien sudah pulang — data obat terkunci, tidak dapat diubah.
        </div>
    @endif

    {{-- INFO --}}
    <div
        class="flex items-center gap-2 px-4 py-2.5 text-sm text-blue-700 bg-blue-50 border border-blue-200 rounded-xl dark:bg-blue-900/20 dark:border-blue-600 dark:text-blue-300">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        Data obat dikelola melalui E-Resep. Tab ini hanya menampilkan rekapitulasi.
    </div>

    {{-- TABEL DATA --}}
    <div class="overflow-hidden bg-white border border-gray-200 rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Daftar Obat</h3>
            <x-badge variant="gray">{{ count($rjObat) }} item</x-badge>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead
                    class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400 bg-gray-50 dark:bg-gray-800/50">
                    <tr>
                        <th class="px-4 py-3">Kode</th>
                        <th class="px-4 py-3">Nama Obat</th>
                        <th class="px-4 py-3 text-center">Qty</th>
                        <th class="px-4 py-3 text-right">Harga Satuan</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        @if (!$isFormLocked)
                            <th class="w-20 px-4 py-3 text-center">Hapus</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($rjObat as $item)
                        <tr wire:key="obat-row-{{ $item['rjobatDtl'] }}"
                            class="transition hover:bg-gray-50 dark:hover:bg-gray-800/40">
                            <td class="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                {{ $item['productId'] }}
                            </td>
                            <td class="px-4 py-3 text-gray-800 dark:text-gray-200">
                                <div>{{ $item['productName'] }}</div>
                                @if ($item['catatan'] && $item['catatan'] !== '-')
                                    <div class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ $item['catatan'] }}
                                    </div>
                                @endif
                            </td>
                            <td
                                class="px-4 py-3 text-center font-semibold text-gray-800 dark:text-gray-200 whitespace-nowrap">
                                {{ $item['qty'] }}
                                @if ($item['takar'])
                                    <span class="text-xs font-normal text-gray-400">{{ $item['takar'] }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                Rp {{ number_format($item['price']) }}
                            </td>
                            <td
                                class="px-4 py-3 font-semibold text-right text-gray-800 dark:text-gray-200 whitespace-nowrap">
                                Rp {{ number_format($item['total']) }}
                            </td>
                            @if (!$isFormLocked)
                                <td class="px-4 py-3 text-center">
                                    <button type="button" wire:click.prevent="removeObat({{ $item['rjobatDtl'] }})"
                                        wire:confirm="Hapus obat ini?" wire:loading.attr="disabled"
                                        wire:target="removeObat({{ $item['rjobatDtl'] }})"
                                        class="inline-flex items-center justify-center w-8 h-8 text-red-500 transition rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 hover:text-red-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isFormLocked ? 5 : 6 }}"
                                class="px-4 py-10 text-sm text-center text-gray-400 dark:text-gray-600">
                                <svg class="w-8 h-8 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                Belum ada data obat
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                @if (!empty($rjObat))
                    <tfoot class="border-t border-gray-200 bg-gray-50 dark:bg-gray-800/50 dark:border-gray-700">
                        <tr>
                            <td colspan="4" class="px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400">
                                Total</td>
                            <td class="px-4 py-3 text-sm font-bold text-right text-gray-900 dark:text-white">
                                Rp {{ number_format(collect($rjObat)->sum('total')) }}
                            </td>
                            @if (!$isFormLocked)
                                <td></td>
                            @endif
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

</div>
