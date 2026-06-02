<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;

new class extends Component {
    use EmrRITrait;

    public ?int $riHdrNo = null;
    public array $dataOk = [];

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
        $rows = DB::table('rstxn_rioks')
            ->select(
                DB::raw("to_char(ok_date, 'dd/mm/yyyy hh24:mi:ss') as ok_date"),
                'ok_desc',
                'ok_price',
                'ok_no',
            )
            ->where('rihdr_no', $riHdrNo)
            ->orderByDesc('rstxn_rioks.ok_date')
            ->get();

        $this->dataOk = $rows->map(fn($r) => (array) $r)->toArray();
    }
};
?>

<div class="space-y-4">
    <div class="overflow-hidden bg-white border border-gray-200 rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Daftar Operasi (OK)</h3>
            <x-badge variant="gray">{{ count($dataOk) }} item</x-badge>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400 bg-gray-50 dark:bg-gray-800/50">
                    <tr>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3">Keterangan</th>
                        <th class="px-4 py-3 text-right">Tarif</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($dataOk as $item)
                        <tr wire:key="ok-ri-{{ $item['ok_no'] ?? $loop->index }}" class="transition hover:bg-gray-50 dark:hover:bg-gray-800/40">
                            <td class="px-4 py-3 font-mono text-xs text-gray-500 whitespace-nowrap">{{ $item['ok_date'] ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-800 dark:text-gray-200">{{ $item['ok_desc'] ?? '-' }}</td>
                            <td class="px-4 py-3 font-semibold text-right text-gray-800 dark:text-gray-200 whitespace-nowrap">
                                Rp {{ number_format($item['ok_price'] ?? 0) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-10 text-sm text-center text-gray-400 dark:text-gray-600">
                                Belum ada data operasi
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if (!empty($dataOk))
                    <tfoot class="border-t border-gray-200 bg-gray-50 dark:bg-gray-800/50 dark:border-gray-700">
                        <tr>
                            <td colspan="2" class="px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400">Total</td>
                            <td class="px-4 py-3 text-sm font-bold text-right text-gray-900 dark:text-white">
                                Rp {{ number_format(collect($dataOk)->sum('ok_price')) }}
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
