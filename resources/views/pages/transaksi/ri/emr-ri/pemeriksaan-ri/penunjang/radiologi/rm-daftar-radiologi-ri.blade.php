<?php
// resources/views/pages/transaksi/ri/emr-ri/pemeriksaan-ri/penunjang/radiologi/rm-daftar-radiologi-ri.blade.php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new class extends Component {
    /*
     | Daftar Radiologi untuk satu RI — query langsung ke DB
     | (rstxn_riradiologs JOIN rsmst_radiologis WHERE rihdr_no=riHdrNo).
     | Group per waktu_entry (1 kiriman = banyak item dengan timestamp identik).
     | Real-time: respon ke event 'radiologi-order-terkirim'.
     */

    public ?string $riHdrNo = null;

    public function mount(?string $riHdrNo = null): void
    {
        $this->riHdrNo = $riHdrNo;
    }

    #[On('radiologi-order-terkirim')]
    public function refresh(): void
    {
        // Computed property auto re-evaluate.
    }

    #[Computed]
    public function rows()
    {
        if (empty($this->riHdrNo)) {
            return collect();
        }

        $items = DB::table('rstxn_riradiologs as r')
            ->join('rsmst_radiologis as m', 'r.rad_id', '=', 'm.rad_id')
            ->select('r.rirad_no', 'r.rihdr_no', 'r.waktu_entry', 'm.rad_desc', 'r.dr_pengirim', 'r.keterangan')
            ->where('r.rihdr_no', $this->riHdrNo)
            ->orderByDesc('r.waktu_entry')
            ->orderBy('r.rirad_no')
            ->get();

        return $items
            ->groupBy(fn($r) => $r->waktu_entry ? Carbon::parse($r->waktu_entry)->format('YmdHis') : 'null')
            ->map(function ($group) {
                $first = $group->first();
                return (object) [
                    'rirad_no' => $first->rirad_no,
                    'rihdr_no' => $first->rihdr_no,
                    'waktu_entry' => $first->waktu_entry,
                    'dr_pengirim' => $first->dr_pengirim,
                    'items' => $group->map(fn($r) => $r->rad_desc . ($r->keterangan ? ' (' . $r->keterangan . ')' : ''))->implode(', '),
                ];
            })
            ->values();
    }
};
?>

<div>
    <table class="w-full text-sm text-left text-gray-500 table-auto">
        <thead class="text-xs text-gray-700 uppercase bg-gray-100">
            <tr>
                <th class="px-4 py-3 text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Tgl Rad</th>
                <th class="px-4 py-3 text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Pemeriksaan Rad</th>
                <th class="px-4 py-3 text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Dokter Pengirim</th>
                <th class="w-24 px-4 py-3 text-xs font-medium text-center text-gray-500 uppercase dark:text-gray-400">Status</th>
            </tr>
        </thead>
        <tbody class="bg-white">
            @forelse ($this->rows as $r)
                <tr class="border-b group">
                    <td class="px-2 py-2 text-xs font-mono text-gray-500 group-hover:bg-gray-50 whitespace-nowrap">
                        {{ $r->waktu_entry ? \Carbon\Carbon::parse($r->waktu_entry)->format('d/m/Y H:i') : '-' }}
                    </td>
                    <td class="px-2 py-2 text-gray-700 group-hover:bg-gray-50">
                        {{ $r->items }}
                    </td>
                    <td class="px-2 py-2 text-gray-700 group-hover:bg-gray-50">
                        {{ $r->dr_pengirim ?? '-' }}
                    </td>
                    <td class="px-2 py-2 text-center text-gray-400 group-hover:bg-gray-50">
                        -
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-4 py-6 text-sm text-center text-gray-400">
                        Belum ada data radiologi
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
