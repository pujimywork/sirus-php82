<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;

new class extends Component {
    use EmrRITrait;

    public ?int $riHdrNo = null;
    public array $dataTrf = [];

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
        $rows = DB::table('rstxn_ritempadmins')
            ->select(
                DB::raw("to_char(tempadm_date, 'dd/mm/yyyy hh24:mi:ss') as tempadm_date"),
                'rj_admin', 'poli_price', 'acte_price', 'actp_price', 'actd_price',
                'obat', 'lab', 'rad', 'other', 'rs_admin',
                'tempadm_no', 'tempadm_flag',
            )
            ->where('rihdr_no', $riHdrNo)
            ->orderByDesc('tempadm_date')
            ->get();

        $this->dataTrf = $rows->map(fn($r) => (array) $r)->toArray();
    }
};
?>

<div class="space-y-4">
    <div class="overflow-hidden bg-white border border-gray-200 rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Transfer dari UGD / Rawat Jalan</h3>
            <x-badge variant="gray">{{ count($dataTrf) }} item</x-badge>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400 bg-gray-50 dark:bg-gray-800/50">
                    <tr>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3">Flag</th>
                        <th class="px-4 py-3 text-right">RJ Admin</th>
                        <th class="px-4 py-3 text-right">Poli</th>
                        <th class="px-4 py-3 text-right">Jasa Kary.</th>
                        <th class="px-4 py-3 text-right">Jasa Medis</th>
                        <th class="px-4 py-3 text-right">Jasa Dokter</th>
                        <th class="px-4 py-3 text-right">Obat</th>
                        <th class="px-4 py-3 text-right">Lab</th>
                        <th class="px-4 py-3 text-right">Rad</th>
                        <th class="px-4 py-3 text-right">Lain</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($dataTrf as $item)
                        <tr wire:key="trf-ugd-rj-ri-{{ $item['tempadm_flag'] ?? 'na' }}-{{ $item['tempadm_date'] ?? $loop->index }}" class="transition hover:bg-gray-50 dark:hover:bg-gray-800/40">
                            <td class="px-4 py-3 font-mono text-xs text-gray-500 whitespace-nowrap">{{ $item['tempadm_date'] ?? '-' }}</td>
                            <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-400">{{ $item['tempadm_flag'] ?? '-' }}</td>
                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 whitespace-nowrap">Rp {{ number_format($item['rj_admin'] ?? 0) }}</td>
                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 whitespace-nowrap">Rp {{ number_format($item['poli_price'] ?? 0) }}</td>
                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 whitespace-nowrap">Rp {{ number_format($item['acte_price'] ?? 0) }}</td>
                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 whitespace-nowrap">Rp {{ number_format($item['actp_price'] ?? 0) }}</td>
                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 whitespace-nowrap">Rp {{ number_format($item['actd_price'] ?? 0) }}</td>
                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 whitespace-nowrap">Rp {{ number_format($item['obat'] ?? 0) }}</td>
                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 whitespace-nowrap">Rp {{ number_format($item['lab'] ?? 0) }}</td>
                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 whitespace-nowrap">Rp {{ number_format($item['rad'] ?? 0) }}</td>
                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 whitespace-nowrap">Rp {{ number_format($item['other'] ?? 0) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="px-4 py-10 text-sm text-center text-gray-400 dark:text-gray-600">
                                Tidak ada transfer dari UGD/RJ
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
