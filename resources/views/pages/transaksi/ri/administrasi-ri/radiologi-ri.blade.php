<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;

new class extends Component {
    use EmrRITrait;

    public ?int $riHdrNo = null;
    public array $dataRad = [];

    // Lock ke transaksi induk RI (seragam dgn modul lab): terkunci bila RI tidak aktif.
    public bool $isFormLocked = false;
    public string $formLockReason = '';

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
        $rows = DB::table('rstxn_riradiologs')
            ->join('rsmst_radiologis', 'rstxn_riradiologs.rad_id', '=', 'rsmst_radiologis.rad_id')
            ->select(
                DB::raw("to_char(rirad_date, 'dd/mm/yyyy hh24:mi:ss') as rirad_date"),
                'rstxn_riradiologs.rad_id',
                'rsmst_radiologis.rad_desc',
                'rstxn_riradiologs.rirad_price',
                'rstxn_riradiologs.rirad_no',
            )
            ->where('rstxn_riradiologs.rihdr_no', $riHdrNo)
            ->orderByDesc('rstxn_riradiologs.rirad_date')
            ->get();

        $this->dataRad = $rows->map(fn($r) => (array) $r)->toArray();

        $this->evaluasiLock();
    }

    /* Status transaksi induk RI — kunci bila RI sudah ditutup/dibatalkan (tidak
       lagi 'I' = dirawat). Reason granular disamakan gaya dgn modul lab. */
    private function evaluasiLock(): void
    {
        $this->isFormLocked = false;
        $this->formLockReason = '';

        if (!$this->riHdrNo) {
            return;
        }

        $s = DB::table('rstxn_rihdrs')->where('rihdr_no', $this->riHdrNo)->value('ri_status');

        $this->formLockReason = match ($s) {
            'I' => '',
            'P' => 'Transaksi RI sudah ditutup — data radiologi terkunci.',
            'F' => 'Transaksi RI sudah dibatalkan — data radiologi terkunci.',
            default => empty($s) ? '' : 'Transaksi RI tidak aktif — data radiologi terkunci.',
        };

        $this->isFormLocked = $this->formLockReason !== '';
    }

    /* Hapus 1 baris radiologi RI (sekaligus baris biaya). Guard lock induk +
       audit log ke userLogs RI (appendAdminLogRI, kategori ADMIN — seragam RJ/UGD). */
    public function removeRad(int $riradNo): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: $this->formLockReason ?: 'Transaksi terkunci.');
            return;
        }

        try {
            DB::transaction(function () use ($riradNo) {
                $this->lockRIRow($this->riHdrNo);

                DB::table('rstxn_riradiologs')->where('rirad_no', $riradNo)->delete();

                $this->appendAdminLogRI($this->riHdrNo, 'Hapus Radiologi #' . $riradNo);
            });

            $this->findData($this->riHdrNo);
            $this->dispatch('administrasi-ri.updated');
            $this->dispatch('toast', type: 'success', message: 'Radiologi berhasil dihapus.');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }
};
?>

<div class="space-y-4">
    {{-- LOCKED BANNER --}}
    @if ($isFormLocked)
        <div
            class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
            {{ $formLockReason }}
        </div>
    @endif

    <div class="overflow-hidden bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between px-4 py-3 border-b border-hairline dark:border-gray-700">
            <h3 class="text-sm font-semibold text-body dark:text-gray-300">Daftar Radiologi</h3>
            <x-badge variant="gray">{{ count($dataRad) }} item</x-badge>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs font-semibold text-muted uppercase dark:text-gray-400 bg-surface-soft dark:bg-gray-800/50">
                    <tr>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3">Kode</th>
                        <th class="px-4 py-3">Radiologi</th>
                        <th class="px-4 py-3 text-right">Tarif</th>
                        <th class="px-4 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline-soft dark:divide-gray-800">
                    @forelse ($dataRad as $item)
                        <tr wire:key="radiologi-ri-{{ $item['rirad_no'] ?? $loop->index }}" class="transition hover:bg-surface-soft dark:hover:bg-gray-800/40">
                            <td class="px-4 py-3 font-mono text-xs text-muted whitespace-nowrap">{{ $item['rirad_date'] ?? '-' }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-muted dark:text-gray-400 whitespace-nowrap">{{ $item['rad_id'] }}</td>
                            <td class="px-4 py-3 text-ink dark:text-gray-200 whitespace-nowrap">{{ $item['rad_desc'] }}</td>
                            <td class="px-4 py-3 font-semibold text-right text-ink dark:text-gray-200 whitespace-nowrap">
                                Rp {{ number_format($item['rirad_price'] ?? 0) }}
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                @if ($isFormLocked)
                                    <span class="text-xs italic text-muted-soft dark:text-gray-600">Terkunci</span>
                                @else
                                    <button type="button"
                                        wire:click="removeRad({{ $item['rirad_no'] }})"
                                        wire:confirm="Hapus radiologi ini?"
                                        wire:loading.attr="disabled" wire:target="removeRad({{ $item['rirad_no'] }})"
                                        class="text-red-500 hover:text-red-700 disabled:opacity-50"
                                        title="Hapus radiologi">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-sm text-center text-muted-soft dark:text-gray-600">
                                Belum ada data radiologi
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if (!empty($dataRad))
                    <tfoot class="border-t border-hairline bg-surface-soft dark:bg-gray-800/50 dark:border-gray-700">
                        <tr>
                            <td colspan="3" class="px-4 py-3 text-sm font-semibold text-muted dark:text-gray-400">Total</td>
                            <td class="px-4 py-3 text-sm font-bold text-right text-ink dark:text-white">
                                Rp {{ number_format(collect($dataRad)->sum('rirad_price')) }}
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
