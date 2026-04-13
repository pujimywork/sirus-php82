<?php
// resources/views/pages/transaksi/ugd/administrasi-ugd/transfer-ugd.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;

new class extends Component {
    use EmrUGDTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $rjTransfer = [];
    public int $sumTransfer = 0;

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        if ($this->rjNo) {
            $this->loadData($this->rjNo);
            $this->isFormLocked = $this->checkUGDStatus($this->rjNo);
        }
    }

    /* ===============================
     | LOAD DATA — read-only, tidak perlu lock
     =============================== */
    private function loadData(int $rjNo): void
    {
        $rows = DB::table('rstxn_ugdtempadmins')->select('rj_no', 'tempadm_flag', 'tempadm_ref', 'rj_admin', 'poli_price', 'acte_price', 'actp_price', 'actd_price', 'obat', 'lab', 'rad', 'other', 'rs_admin')->where('rj_no', $rjNo)->get();

        $this->rjTransfer = $rows
            ->map(
                fn($r) => [
                    'sumber' => ($r->tempadm_flag ?? '-') . ' #' . ($r->tempadm_ref ?? '-'),
                    'rjAdmin' => (int) ($r->rj_admin ?? 0),
                    'poliPrice' => (int) ($r->poli_price ?? 0),
                    'actePrice' => (int) ($r->acte_price ?? 0),
                    'actpPrice' => (int) ($r->actp_price ?? 0),
                    'actdPrice' => (int) ($r->actd_price ?? 0),
                    'obat' => (int) ($r->obat ?? 0),
                    'lab' => (int) ($r->lab ?? 0),
                    'rad' => (int) ($r->rad ?? 0),
                    'other' => (int) ($r->other ?? 0),
                    'rsAdmin' => (int) ($r->rs_admin ?? 0),
                    'total' => (int) ($r->rj_admin ?? 0) + (int) ($r->poli_price ?? 0) + (int) ($r->acte_price ?? 0) + (int) ($r->actp_price ?? 0) + (int) ($r->actd_price ?? 0) + (int) ($r->obat ?? 0) + (int) ($r->lab ?? 0) + (int) ($r->rad ?? 0) + (int) ($r->other ?? 0) + (int) ($r->rs_admin ?? 0),
                ],
            )
            ->toArray();

        $this->sumTransfer = (int) DB::table('rstxn_ugdtempadmins')->where('rj_no', $rjNo)->selectRaw('nvl(sum(rj_admin + poli_price + acte_price + actp_price + actd_price + obat + lab + rad + other + rs_admin), 0) as total')->value('total');
    }

    /* ===============================
     | LISTENER — refresh dari parent
     =============================== */
    #[On('administrasi-ugd.updated')]
    public function onAdministrasiUpdated(): void
    {
        if ($this->rjNo) {
            $this->loadData($this->rjNo);
        }
    }
};
?>

<div class="space-y-4">

    {{-- INFO --}}
    <div
        class="flex items-center gap-2 px-4 py-2.5 text-sm text-blue-700 bg-blue-50 border border-blue-200 rounded-xl dark:bg-blue-900/20 dark:border-blue-600 dark:text-blue-300">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        Data transfer bersumber dari sistem pendaftaran/admisi dan bersifat hanya baca.
    </div>

    {{-- TABEL DATA --}}
    <div class="overflow-hidden bg-white border border-gray-200 rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Data Transfer</h3>
            <x-badge variant="gray">{{ count($rjTransfer) }} record</x-badge>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead
                    class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400 bg-gray-50 dark:bg-gray-800/50">
                    <tr>
                        <th class="px-4 py-3">Sumber</th>
                        <th class="px-4 py-3 text-right">Adm. RS</th>
                        <th class="px-4 py-3 text-right">Adm. RJ</th>
                        <th class="px-4 py-3 text-right">Jasa Karyw.</th>
                        <th class="px-4 py-3 text-right">Jasa Medis</th>
                        <th class="px-4 py-3 text-right">Jasa Dokter</th>
                        <th class="px-4 py-3 text-right">Obat</th>
                        <th class="px-4 py-3 text-right">Lab</th>
                        <th class="px-4 py-3 text-right">Rad</th>
                        <th class="px-4 py-3 text-right">Lain-lain</th>
                        <th class="px-4 py-3 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($rjTransfer as $item)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40 transition">
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300 whitespace-nowrap font-mono text-xs">
                                {{ $item['sumber'] }}</td>
                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 whitespace-nowrap">Rp
                                {{ number_format($item['rsAdmin']) }}</td>
                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 whitespace-nowrap">Rp
                                {{ number_format($item['rjAdmin']) }}</td>
                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 whitespace-nowrap">Rp
                                {{ number_format($item['actePrice']) }}</td>
                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 whitespace-nowrap">Rp
                                {{ number_format($item['actpPrice']) }}</td>
                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 whitespace-nowrap">Rp
                                {{ number_format($item['actdPrice']) }}</td>
                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 whitespace-nowrap">Rp
                                {{ number_format($item['obat']) }}</td>
                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 whitespace-nowrap">Rp
                                {{ number_format($item['lab']) }}</td>
                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 whitespace-nowrap">Rp
                                {{ number_format($item['rad']) }}</td>
                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 whitespace-nowrap">Rp
                                {{ number_format($item['other']) }}</td>
                            <td class="px-4 py-3 text-right font-bold text-gray-900 dark:text-white whitespace-nowrap">
                                Rp {{ number_format($item['total']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="px-4 py-10 text-sm text-center text-gray-400 dark:text-gray-600">
                                <svg class="w-8 h-8 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                Tidak ada data transfer
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if (!empty($rjTransfer))
                    <tfoot class="border-t border-gray-200 bg-gray-50 dark:bg-gray-800/50 dark:border-gray-700">
                        <tr>
                            <td colspan="10" class="px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400">
                                Total Transfer</td>
                            <td
                                class="px-4 py-3 text-sm font-bold text-right text-brand-green dark:text-brand-lime whitespace-nowrap">
                                Rp {{ number_format($sumTransfer) }}
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

</div>
