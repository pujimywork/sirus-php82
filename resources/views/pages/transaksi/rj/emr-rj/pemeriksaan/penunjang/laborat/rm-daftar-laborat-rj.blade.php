<?php
// resources/views/pages/transaksi/rj/emr-rj/pemeriksaan/penunjang/laborat/rm-daftar-laborat-rj.blade.php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

new class extends Component {
    /*
     | Daftar Laboratorium (internal) untuk satu RJ — query langsung ke DB
     | (lbtxn_checkupdtls JOIN lbtxn_checkuphdrs WHERE ref_no=rjNo AND status_rjri='RJ').
     | Real-time: respon ke event 'laborat-order-terkirim' (di-dispatch saat order baru).
     */

    public string $rjNo = '';

    public function mount(string $rjNo = ''): void
    {
        $this->rjNo = $rjNo;
    }

    #[On('laborat-order-terkirim')]
    public function refresh(): void
    {
        // Computed property auto re-evaluate; method ini cuma trigger render.
    }

    #[Computed]
    public function rows()
    {
        if (empty($this->rjNo)) {
            return collect();
        }

        $items = DB::table('lbtxn_checkuphdrs as h')
            ->join('lbtxn_checkupdtls as d', 'h.checkup_no', '=', 'd.checkup_no')
            ->join('lbmst_clabitems as c', 'd.clabitem_id', '=', 'c.clabitem_id')
            ->select(
                'h.checkup_no', 'h.checkup_date', 'h.checkup_status',
                'c.clabitem_desc',
            )
            ->where('h.ref_no', $this->rjNo)
            ->where('h.status_rjri', 'RJ')
            ->whereNotNull('d.price')
            ->orderByDesc('h.checkup_date')
            ->orderBy('d.checkup_dtl')
            ->get();

        return $items->groupBy('checkup_no')->map(function ($group) {
            $first = $group->first();
            return (object) [
                'checkup_no' => $first->checkup_no,
                'checkup_date' => $first->checkup_date,
                'checkup_status' => $first->checkup_status,
                'items' => $group->pluck('clabitem_desc')->implode(', '),
            ];
        })->values();
    }
};
?>

<div>
    <table class="w-full text-sm text-left text-gray-500 table-auto">
        <thead class="text-xs text-gray-700 uppercase bg-gray-100">
            <tr>
                <th class="px-4 py-3 text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Tgl Lab</th>
                <th class="px-4 py-3 text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Pemeriksaan Lab</th>
                <th class="w-24 px-4 py-3 text-xs font-medium text-center text-gray-500 uppercase dark:text-gray-400">Status</th>
            </tr>
        </thead>
        <tbody class="bg-white">
            @forelse ($this->rows as $r)
                <tr class="border-b group">
                    <td class="px-2 py-2 text-xs font-mono text-gray-500 group-hover:bg-gray-50 whitespace-nowrap">
                        {{ $r->checkup_date ? \Carbon\Carbon::parse($r->checkup_date)->format('d/m/Y H:i') : '-' }}
                    </td>
                    <td class="px-2 py-2 text-gray-700 group-hover:bg-gray-50">
                        {{ $r->items }}
                    </td>
                    <td class="px-2 py-2 text-center group-hover:bg-gray-50">
                        @if ($r->checkup_status === 'H')
                            <x-badge variant="success">Selesai</x-badge>
                        @elseif ($r->checkup_status === 'C')
                            <x-badge variant="info">Proses</x-badge>
                        @else
                            <x-badge variant="warning">Terdaftar</x-badge>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="px-4 py-6 text-sm text-center text-gray-400">
                        Belum ada data laboratorium
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
