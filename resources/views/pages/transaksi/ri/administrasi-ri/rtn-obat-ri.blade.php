<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;

new class extends Component {
    use EmrRITrait;

    public ?int $riHdrNo = null;
    public array $dataRtnObat = [];

    public function mount(): void
    {
        if ($this->riHdrNo) {
            $this->findData($this->riHdrNo);
        }
    }

    #[On('administrasi-ri.updated')]
    public function refresh(): void
    {
        if ($this->riHdrNo) {
            $this->findData($this->riHdrNo);
        }
    }

    private function findData(int $riHdrNo): void
    {
        $rows = DB::table('rstxn_riobatrtns')
            ->join('immst_products', 'immst_products.product_id', '=', 'rstxn_riobatrtns.product_id')
            ->select(
                DB::raw("to_char(riobat_date, 'dd/mm/yyyy hh24:mi:ss') as riobat_date"),
                'rstxn_riobatrtns.product_id',
                'immst_products.product_name',
                'rstxn_riobatrtns.riobat_qty',
                'rstxn_riobatrtns.riobat_price',
                'rstxn_riobatrtns.riobat_no',
            )
            ->where('rstxn_riobatrtns.rihdr_no', $riHdrNo)
            ->orderByDesc('riobat_date')
            ->get();

        $this->dataRtnObat = $rows->map(fn($r) => (array) $r)->toArray();
    }
};
?>

<div class="space-y-4">
    <div class="overflow-hidden bg-white border border-gray-200 rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Return Obat RI</h3>
            <x-badge variant="gray">{{ count($dataRtnObat) }} item</x-badge>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400 bg-gray-50 dark:bg-gray-800/50">
                    <tr>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3">Produk</th>
                        <th class="px-4 py-3 text-right">Qty</th>
                        <th class="px-4 py-3 text-right">Harga</th>
                        <th class="px-4 py-3 text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($dataRtnObat as $item)
                        <tr wire:key="rtn-obat-ri-{{ $item['riobat_no'] ?? $loop->index }}" class="transition hover:bg-gray-50 dark:hover:bg-gray-800/40">
                            <td class="px-4 py-3 font-mono text-xs text-gray-500 whitespace-nowrap">{{ $item['riobat_date'] ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-800 dark:text-gray-200 whitespace-nowrap">{{ $item['product_name'] ?? $item['product_id'] }}</td>
                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ $item['riobat_qty'] ?? 0 }}</td>
                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 whitespace-nowrap">Rp {{ number_format($item['riobat_price'] ?? 0) }}</td>
                            <td class="px-4 py-3 font-semibold text-right text-red-600 dark:text-red-400 whitespace-nowrap">
                                -Rp {{ number_format(($item['riobat_qty'] ?? 0) * ($item['riobat_price'] ?? 0)) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-sm text-center text-gray-400 dark:text-gray-600">
                                Belum ada return obat
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if (!empty($dataRtnObat))
                    <tfoot class="border-t border-gray-200 bg-gray-50 dark:bg-gray-800/50 dark:border-gray-700">
                        <tr>
                            <td colspan="4" class="px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400">Total Return</td>
                            <td class="px-4 py-3 text-sm font-bold text-right text-red-600 dark:text-red-400">
                                -Rp {{ number_format(collect($dataRtnObat)->sum(fn($i) => ($i['riobat_qty'] ?? 0) * ($i['riobat_price'] ?? 0))) }}
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
