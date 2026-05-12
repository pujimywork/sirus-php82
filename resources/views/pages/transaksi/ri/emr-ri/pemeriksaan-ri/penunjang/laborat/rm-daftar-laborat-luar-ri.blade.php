<?php
// resources/views/pages/transaksi/ri/emr-ri/pemeriksaan-ri/penunjang/laborat/rm-daftar-laborat-luar-ri.blade.php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

new class extends Component {
    /*
     | Daftar Lab Luar untuk satu RI — query langsung ke DB
     | (lbtxn_checkupoutdtls JOIN lbtxn_checkuphdrs WHERE ref_no=riHdrNo AND status_rjri='RI').
     | Real-time: respon ke event 'lab-luar-ri.updated'.
     */

    public ?string $riHdrNo = null;

    public function mount(?string $riHdrNo = null): void
    {
        $this->riHdrNo = $riHdrNo;
    }

    #[On('lab-luar-ri.updated')]
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

        return DB::table('lbtxn_checkupoutdtls as o')
            ->join('lbtxn_checkuphdrs as h', 'o.checkup_no', '=', 'h.checkup_no')
            ->leftJoin('rsmst_doctors as d', 'h.dr_id', '=', 'd.dr_id')
            ->select(
                'o.labout_dtl', 'o.checkup_no', 'o.labout_desc', 'o.labout_price',
                'o.labout_result', 'o.pdf_path', 'o.keterangan',
                'h.checkup_date', 'h.checkup_status',
                'd.dr_name',
            )
            ->where('h.ref_no', $this->riHdrNo)
            ->where('h.status_rjri', 'RI')
            ->where('h.checkup_status', '!=', 'F')
            ->orderByDesc('h.checkup_date')
            ->orderByDesc('o.labout_dtl')
            ->get();
    }
};
?>

<div>
    <table class="w-full text-sm text-left text-gray-500 table-auto">
        <thead class="text-xs text-gray-700 uppercase bg-gray-100">
            <tr>
                <th class="px-4 py-3 text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Tgl Order</th>
                <th class="px-4 py-3 text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Pemeriksaan</th>
                <th class="px-4 py-3 text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Dokter Pengirim</th>
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
                        {{ $r->labout_desc }}
                        @if ($r->labout_result)
                            <p class="text-xs italic text-gray-500">Catatan: {{ $r->labout_result }}</p>
                        @endif
                        @if ($r->keterangan)
                            <p class="text-xs italic text-amber-700">Keterangan: {{ $r->keterangan }}</p>
                        @endif
                    </td>
                    <td class="px-2 py-2 text-gray-700 group-hover:bg-gray-50">
                        {{ $r->dr_name ?? '-' }}
                    </td>
                    <td class="px-2 py-2 text-center group-hover:bg-gray-50">
                        @if ($r->checkup_status === 'H')
                            <x-badge variant="success">Selesai</x-badge>
                        @elseif ($r->checkup_status === 'C')
                            <x-badge variant="info">Menunggu Hasil</x-badge>
                        @else
                            <x-badge variant="warning">Terdaftar</x-badge>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-4 py-6 text-sm text-center text-gray-400">
                        Belum ada data laboratorium luar
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
