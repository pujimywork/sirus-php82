<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

new class extends Component {
    // Administrasi bersifat READ-ONLY — order, tarif & hapus laboratorium dilakukan
    // di program Penunjang (order lewat EMR, kelola di modul Laborat). Di sini hanya tampil.
    public ?int $rjNo = null;
    public array $rjLab = [];

    public function mount(): void
    {
        if ($this->rjNo) {
            $this->findData($this->rjNo);
        }
    }

    #[On('administrasi-lab-rj.updated')]
    public function onAdministrasiUpdated(): void
    {
        if ($this->rjNo) {
            $this->findData($this->rjNo);
        }
    }

    private function findData(int $rjNo): void
    {
        $this->rjLab = DB::table('rstxn_rjlabs')
            ->select('lab_dtl', 'lab_desc', 'lab_price')
            ->where('rj_no', $rjNo)
            ->orderBy('lab_dtl')
            ->get()
            ->map(
                fn($r) => [
                    'labDtl' => (int) $r->lab_dtl,
                    'labDesc' => $r->lab_desc,
                    'labPrice' => $r->lab_price,
                ],
            )
            ->toArray();
    }
};
?>

<div class="space-y-4">
    {{-- TABEL DATA (read-only) --}}
    <div class="overflow-hidden bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between px-4 py-3 border-b border-hairline dark:border-gray-700">
            <h3 class="text-sm font-semibold text-body dark:text-gray-300">Daftar Laboratorium</h3>
            <x-badge variant="gray">{{ count($rjLab) }} item</x-badge>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead
                    class="text-xs font-semibold text-muted uppercase dark:text-gray-400 bg-surface-soft dark:bg-gray-800/50">
                    <tr>
                        <th class="px-4 py-3">Keterangan</th>
                        <th class="px-4 py-3 text-right">Tarif Laborat</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline-soft dark:divide-gray-800">
                    @forelse ($rjLab as $item)
                        <tr wire:key="lab-row-{{ $item['labDtl'] }}"
                            class="transition hover:bg-surface-soft dark:hover:bg-gray-800/40">
                            <td class="px-4 py-2">
                                <span class="text-ink dark:text-gray-200">{{ $item['labDesc'] }}</span>
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap">
                                <span class="block font-semibold text-right text-ink dark:text-gray-200">
                                    Rp {{ number_format($item['labPrice']) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2"
                                class="px-4 py-10 text-sm text-center text-muted-soft dark:text-gray-600">
                                Belum ada data laboratorium
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                @if (!empty($rjLab))
                    <tfoot class="border-t border-hairline bg-surface-soft dark:bg-gray-800/50 dark:border-gray-700">
                        <tr>
                            <td class="px-4 py-3 text-sm font-semibold text-muted dark:text-gray-400">Total</td>
                            <td class="px-4 py-3 text-sm font-bold text-right text-ink dark:text-white">
                                Rp {{ number_format(collect($rjLab)->sum('labPrice')) }}
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
