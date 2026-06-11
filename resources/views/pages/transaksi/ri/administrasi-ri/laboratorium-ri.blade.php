<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;

new class extends Component {
    use EmrRITrait;

    public ?int $riHdrNo = null;
    public array $dataLab = [];

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
        $rows = DB::table('rstxn_rilabs')
            ->select(
                DB::raw("to_char(lab_date, 'dd/mm/yyyy hh24:mi:ss') as lab_date"),
                'lab_desc',
                'lab_price',
                'lab_dtl',
                'checkup_no',
            )
            ->where('rihdr_no', $riHdrNo)
            ->orderByDesc('rstxn_rilabs.lab_date')
            ->get();

        $this->dataLab = $rows->map(fn($r) => (array) $r)->toArray();
    }
};
?>

<div class="space-y-4">
    <div class="overflow-hidden bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between px-4 py-3 border-b border-hairline dark:border-gray-700">
            <h3 class="text-sm font-semibold text-body dark:text-gray-300">Daftar Laboratorium</h3>
            <x-badge variant="gray">{{ count($dataLab) }} item</x-badge>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs font-semibold text-muted uppercase dark:text-gray-400 bg-surface-soft dark:bg-gray-800/50">
                    <tr>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3">Keterangan</th>
                        <th class="px-4 py-3 text-right">Tarif</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline-soft dark:divide-gray-800">
                    @forelse ($dataLab as $item)
                        <tr wire:key="laboratorium-ri-{{ $item['checkup_no'] ?? '' }}-{{ $item['lab_dtl'] ?? $loop->index }}" class="transition hover:bg-surface-soft dark:hover:bg-gray-800/40">
                            <td class="px-4 py-3 font-mono text-xs text-muted whitespace-nowrap">{{ $item['lab_date'] ?? '-' }}</td>
                            <td class="px-4 py-3 text-ink dark:text-gray-200">{{ $item['lab_desc'] ?? '-' }}</td>
                            <td class="px-4 py-3 font-semibold text-right text-ink dark:text-gray-200 whitespace-nowrap">
                                Rp {{ number_format($item['lab_price'] ?? 0) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-10 text-sm text-center text-muted-soft dark:text-gray-600">
                                Belum ada data laboratorium
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if (!empty($dataLab))
                    <tfoot class="border-t border-hairline bg-surface-soft dark:bg-gray-800/50 dark:border-gray-700">
                        <tr>
                            <td colspan="2" class="px-4 py-3 text-sm font-semibold text-muted dark:text-gray-400">Total</td>
                            <td class="px-4 py-3 text-sm font-bold text-right text-ink dark:text-white">
                                Rp {{ number_format(collect($dataLab)->sum('lab_price')) }}
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
