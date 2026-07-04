<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

new class extends Component {
    // Administrasi bersifat READ-ONLY — order, tarif & hapus radiologi dilakukan
    // di program Penunjang (Upload Hasil / order lewat EMR). Di sini hanya tampil.
    public ?int $rjNo = null;
    public array $rjRad = [];

    public function mount(): void
    {
        if ($this->rjNo) {
            $this->findData($this->rjNo);
        }
    }

    #[On('administrasi-rad-rj.updated')]
    public function onAdministrasiUpdated(): void
    {
        if ($this->rjNo) {
            $this->findData($this->rjNo);
        }
    }

    private function findData(int $rjNo): void
    {
        $this->rjRad = DB::table('rstxn_rjrads')
            ->join('rsmst_radiologis', 'rsmst_radiologis.rad_id', 'rstxn_rjrads.rad_id')
            ->select('rstxn_rjrads.rad_dtl', 'rstxn_rjrads.rad_id', 'rsmst_radiologis.rad_desc', 'rstxn_rjrads.rad_price')
            ->where('rj_no', $rjNo)
            ->orderBy('rstxn_rjrads.rad_dtl')
            ->get()
            ->map(
                fn($r) => [
                    'radDtl' => (int) $r->rad_dtl,
                    'radId' => $r->rad_id,
                    'radDesc' => $r->rad_desc,
                    'radPrice' => $r->rad_price,
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
            <h3 class="text-sm font-semibold text-body dark:text-gray-300">Daftar Radiologi</h3>
            <x-badge variant="gray">{{ count($rjRad) }} item</x-badge>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead
                    class="text-xs font-semibold text-muted uppercase dark:text-gray-400 bg-surface-soft dark:bg-gray-800/50">
                    <tr>
                        <th class="px-4 py-3">Kode</th>
                        <th class="px-4 py-3">Keterangan</th>
                        <th class="px-4 py-3 text-right">Tarif Radiologi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline-soft dark:divide-gray-800">
                    @forelse ($rjRad as $item)
                        <tr wire:key="rad-row-{{ $item['radDtl'] }}"
                            class="transition hover:bg-surface-soft dark:hover:bg-gray-800/40">
                            <td class="px-4 py-2 font-mono text-xs text-muted dark:text-gray-400 whitespace-nowrap">
                                {{ $item['radId'] }}
                            </td>
                            <td class="px-4 py-2 text-ink dark:text-gray-200 whitespace-nowrap">
                                {{ $item['radDesc'] }}
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap">
                                <span class="block font-semibold text-right text-ink dark:text-gray-200">
                                    Rp {{ number_format($item['radPrice']) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3"
                                class="px-4 py-10 text-sm text-center text-muted-soft dark:text-gray-600">
                                Belum ada data radiologi
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                @if (!empty($rjRad))
                    <tfoot class="border-t border-hairline bg-surface-soft dark:bg-gray-800/50 dark:border-gray-700">
                        <tr>
                            <td colspan="2" class="px-4 py-3 text-sm font-semibold text-muted dark:text-gray-400">Total
                            </td>
                            <td class="px-4 py-3 text-sm font-bold text-right text-ink dark:text-white">
                                Rp {{ number_format(collect($rjRad)->sum('radPrice')) }}
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
