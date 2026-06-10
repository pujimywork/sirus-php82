<?php
// resources/views/pages/transaksi/rj/emr-rj/pemeriksaan/penunjang/radiologi/rm-daftar-radiologi-rj.blade.php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new class extends Component {
    /*
     | Daftar Radiologi untuk satu RJ — query langsung ke DB
     | (rstxn_rjrads JOIN rsmst_radiologis WHERE rj_no=rjNo).
     | Group per waktu_entry (1 kiriman = banyak item dengan timestamp identik).
     | Real-time: respon ke event 'radiologi-order-terkirim'.
     */

    public string $rjNo = '';

    public function mount(string $rjNo = ''): void
    {
        $this->rjNo = $rjNo;
    }

    #[On('radiologi-order-terkirim')]
    public function refresh(): void
    {
        // Trigger render via computed property re-evaluation.
    }

    #[Computed]
    public function rows()
    {
        if (empty($this->rjNo)) {
            return collect();
        }

        $items = DB::table('rstxn_rjrads as r')
            ->join('rsmst_radiologis as m', 'r.rad_id', '=', 'm.rad_id')
            ->select('r.rad_dtl', 'r.rj_no', 'r.waktu_entry', 'm.rad_desc', 'r.dr_pengirim', 'r.keterangan')
            ->where('r.rj_no', $this->rjNo)
            ->orderByDesc('r.waktu_entry')
            ->orderBy('r.rad_dtl')
            ->get();

        return $items
            ->groupBy(fn($r) => $r->waktu_entry ? Carbon::parse($r->waktu_entry)->format('YmdHis') : 'null')
            ->map(function ($group) {
                $first = $group->first();
                return (object) [
                    'rj_no' => $first->rj_no,
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
    <table class="w-full text-base text-left text-muted table-auto">
        <thead class="text-sm text-body uppercase bg-surface-soft">
            <tr>
                <th class="px-4 py-3 text-sm font-medium text-muted uppercase dark:text-gray-400">Tgl Rad</th>
                <th class="px-4 py-3 text-sm font-medium text-muted uppercase dark:text-gray-400">Pemeriksaan Rad</th>
                <th class="px-4 py-3 text-sm font-medium text-muted uppercase dark:text-gray-400">Dokter Pengirim</th>
                <th class="w-24 px-4 py-3 text-sm font-medium text-center text-muted uppercase dark:text-gray-400">Status</th>
            </tr>
        </thead>
        <tbody class="bg-canvas">
            @forelse ($this->rows as $r)
                <tr class="border-b group">
                    <td class="px-2 py-2 text-sm font-mono text-muted group-hover:bg-surface-soft whitespace-nowrap">
                        {{ $r->waktu_entry ? \Carbon\Carbon::parse($r->waktu_entry)->format('d/m/Y H:i') : '-' }}
                    </td>
                    <td class="px-2 py-2 text-body group-hover:bg-surface-soft">
                        {{ $r->items }}
                    </td>
                    <td class="px-2 py-2 text-body group-hover:bg-surface-soft">
                        {{ $r->dr_pengirim ?? '-' }}
                    </td>
                    <td class="px-2 py-2 text-center text-muted-soft group-hover:bg-surface-soft">
                        -
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-4 py-6 text-base text-center text-muted-soft">
                        Belum ada data radiologi
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
