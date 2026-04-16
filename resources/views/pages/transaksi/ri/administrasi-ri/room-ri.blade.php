<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;

new class extends Component {
    use EmrRITrait;

    public bool $isFormLocked  = false;
    public ?int $riHdrNo       = null;
    public array $dataDaftarRI = [];
    public ?array $activeRoom  = null;

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        if ($this->riHdrNo) {
            $this->findData($this->riHdrNo);
        } else {
            $this->dataDaftarRI['RiRoom'] = [];
        }
    }

    private function findData(int $riHdrNo): void
    {
        $rows = DB::table('rsmst_trfrooms')
            ->select(
                DB::raw("to_char(start_date, 'dd/mm/yyyy hh24:mi:ss') as start_date"),
                DB::raw("to_char(end_date,   'dd/mm/yyyy hh24:mi:ss') as end_date"),
                'room_id', 'bed_no', 'room_price', 'perawatan_price', 'common_service',
                DB::raw("ROUND(nvl(day, nvl(end_date, sysdate+1) - nvl(start_date, sysdate))) as day"),
                'trfr_no',
            )
            ->where('rihdr_no', $riHdrNo)
            ->orderByDesc('start_date')
            ->get();

        $this->dataDaftarRI['RiRoom'] = $rows->map(fn($r) => (array) $r)->toArray();

        $active = DB::table('rsmst_trfrooms')
            ->select(
                'room_id', 'bed_no', 'trfr_no',
                DB::raw("to_char(start_date, 'dd/mm/yyyy hh24:mi:ss') as start_date"),
                DB::raw("ROUND(sysdate - start_date) as hari_berjalan"),
                'room_price', 'perawatan_price', 'common_service',
            )
            ->where('rihdr_no', $riHdrNo)
            ->whereNull('end_date')
            ->orderByDesc('trfr_no')
            ->first();

        $this->activeRoom = $active ? (array) $active : null;
    }

    /* ===============================
     | REFRESH SETELAH PINDAH KAMAR
     =============================== */
    #[On('administrasi-ri.updated')]
    public function onUpdated(): void
    {
        if ($this->riHdrNo) {
            $this->findData($this->riHdrNo);
        }
    }

    /* ===============================
     | BUKA MODAL PINDAH / ASSIGN
     =============================== */
    public function openPindahKamar(): void
    {
        $this->dispatch('emr-ri.pindah-kamar.open', riHdrNo: $this->riHdrNo);
    }

    /* ===============================
     | REMOVE ROOM
     =============================== */
    public function removeRoom(int $trfrNo): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }

        try {
            DB::transaction(function () use ($trfrNo) {
                $this->lockRIRow($this->riHdrNo);
                DB::table('rsmst_trfrooms')->where('trfr_no', $trfrNo)->delete();
                $this->appendAdminLog($this->riHdrNo, "Hapus Kamar #{$trfrNo}");
            });

            $this->findData($this->riHdrNo);
            $this->dispatch('administrasi-ri.updated');
            $this->dispatch('toast', type: 'success', message: 'Data kamar berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | UPDATE HARI (DAY) MANUAL
     =============================== */
    public function updateDay(int $trfrNo, $newDay): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }

        $newDay = max(1, (int) $newDay);

        try {
            DB::transaction(function () use ($trfrNo, $newDay) {
                $this->lockRIRow($this->riHdrNo);
                DB::table('rsmst_trfrooms')->where('trfr_no', $trfrNo)->update(['day' => $newDay]);
            });

            $this->findData($this->riHdrNo);
            $this->dispatch('administrasi-ri.updated');
            $this->dispatch('toast', type: 'success', message: "Hari berhasil diubah menjadi {$newDay} hari.");
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal update hari: ' . $e->getMessage());
        }
    }
};
?>

<div class="space-y-4" wire:key="room-ri-{{ $riHdrNo ?? 'new' }}">

    {{-- LOCKED BANNER --}}
    @if ($isFormLocked)
        <div class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
            Pasien sudah pulang — transaksi terkunci.
        </div>
    @endif

    {{-- ========================================================
         KAMAR AKTIF SEKARANG
         ======================================================== --}}
    @if ($activeRoom)
        <div class="p-4 border border-emerald-200 rounded-2xl bg-emerald-50 dark:bg-emerald-900/20 dark:border-emerald-700">
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-800 text-emerald-600 dark:text-emerald-300 shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-base font-bold text-emerald-800 dark:text-emerald-200">
                                {{ $activeRoom['room_id'] }}
                            </span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-200 text-emerald-800 dark:bg-emerald-700 dark:text-emerald-100">
                                AKTIF
                            </span>
                        </div>
                        <div class="mt-0.5 text-sm text-emerald-700 dark:text-emerald-300">
                            Bed <span class="font-semibold">{{ $activeRoom['bed_no'] ?? '-' }}</span>
                            &nbsp;·&nbsp; Masuk: <span class="font-mono text-xs">{{ $activeRoom['start_date'] ?? '-' }}</span>
                            &nbsp;·&nbsp; Hari ke-<span class="font-semibold">{{ $activeRoom['hari_berjalan'] ?? 0 }}</span>
                        </div>
                        <div class="mt-1 text-xs text-emerald-600 dark:text-emerald-400 space-x-3">
                            <span>Kamar: <strong>Rp {{ number_format($activeRoom['room_price'] ?? 0) }}</strong>/hr</span>
                            <span>Perawatan: <strong>Rp {{ number_format($activeRoom['perawatan_price'] ?? 0) }}</strong>/hr</span>
                            <span>CS: <strong>Rp {{ number_format($activeRoom['common_service'] ?? 0) }}</strong>/hr</span>
                        </div>
                    </div>
                </div>
                @if (!$isFormLocked)
                    <button type="button" wire:click="openPindahKamar"
                        class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-semibold rounded-xl text-white bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 transition shrink-0 shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                        </svg>
                        Pindah Kamar
                    </button>
                @endif
            </div>
        </div>
    @elseif (!$isFormLocked)
        <div class="p-4 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-2xl text-center">
            <div class="text-sm text-gray-500 dark:text-gray-400 mb-3">Pasien belum di-assign ke kamar</div>
            <button type="button" wire:click="openPindahKamar"
                class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold rounded-xl text-white bg-emerald-600 hover:bg-emerald-700 dark:bg-emerald-500 transition shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Assign Kamar
            </button>
        </div>
    @endif

    {{-- ========================================================
         RIWAYAT TRANSFER KAMAR
         ======================================================== --}}
    <div class="overflow-hidden bg-white border border-gray-200 rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Riwayat Kamar</h3>
            <x-badge variant="gray">{{ count($dataDaftarRI['RiRoom'] ?? []) }} record</x-badge>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs font-semibold text-gray-500 uppercase dark:text-gray-400 bg-gray-50 dark:bg-gray-800/50">
                    <tr>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Kamar</th>
                        <th class="px-4 py-3">Bed</th>
                        <th class="px-4 py-3">Mulai</th>
                        <th class="px-4 py-3">Selesai</th>
                        <th class="px-4 py-3 text-right">Hari</th>
                        <th class="px-4 py-3 text-right">Kamar/Hr</th>
                        <th class="px-4 py-3 text-right">Prwtn/Hr</th>
                        <th class="px-4 py-3 text-right">CS/Hr</th>
                        <th class="px-4 py-3 text-right">Subtotal</th>
                        @if (!$isFormLocked) <th class="w-16 px-4 py-3 text-center">Hapus</th> @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($dataDaftarRI['RiRoom'] ?? [] as $item)
                        @php
                            $isActive = empty($item['end_date']);
                            $day      = (int) ($item['day'] ?? 1);
                            $subtotal = (($item['room_price'] ?? 0) + ($item['perawatan_price'] ?? 0) + ($item['common_service'] ?? 0)) * $day;
                        @endphp
                        <tr class="transition {{ $isActive ? 'bg-emerald-50/50 dark:bg-emerald-900/10' : 'hover:bg-gray-50 dark:hover:bg-gray-800/40' }}">
                            <td class="px-4 py-3">
                                @if ($isActive)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-800 dark:text-emerald-200">Aktif</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400">Selesai</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-mono text-xs font-semibold text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ $item['room_id'] }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ $item['bed_no'] ?? '-' }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-500 whitespace-nowrap">{{ $item['start_date'] ?? '-' }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-500 whitespace-nowrap">{{ $item['end_date'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">
                                @if (!$isFormLocked)
                                    <input type="number" min="1"
                                        value="{{ $day }}"
                                        x-on:change="$wire.updateDay({{ $item['trfr_no'] }}, $event.target.value)"
                                        class="w-16 px-2 py-1 text-xs font-semibold text-right bg-white border border-gray-300 rounded-lg dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200 focus:ring-1 focus:ring-blue-500 focus:border-blue-500
                                        [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" />
                                @else
                                    {{ $day }}
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-400 whitespace-nowrap">Rp {{ number_format($item['room_price'] ?? 0) }}</td>
                            <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-400 whitespace-nowrap">Rp {{ number_format($item['perawatan_price'] ?? 0) }}</td>
                            <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-400 whitespace-nowrap">Rp {{ number_format($item['common_service'] ?? 0) }}</td>
                            <td class="px-4 py-3 font-semibold text-right text-gray-800 dark:text-gray-200 whitespace-nowrap">
                                Rp {{ number_format($subtotal) }}
                            </td>
                            @if (!$isFormLocked)
                                <td class="px-4 py-3 text-center">
                                    <button type="button"
                                        wire:click.prevent="removeRoom({{ $item['trfr_no'] }})"
                                        wire:confirm="Hapus data kamar ini?"
                                        wire:loading.attr="disabled"
                                        wire:target="removeRoom({{ $item['trfr_no'] }})"
                                        class="inline-flex items-center justify-center w-8 h-8 text-red-500 transition rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 hover:text-red-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isFormLocked ? 10 : 11 }}"
                                class="px-4 py-10 text-sm text-center text-gray-400 dark:text-gray-600">
                                Belum ada data kamar
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if (!empty($dataDaftarRI['RiRoom']))
                    <tfoot class="border-t border-gray-200 bg-gray-50 dark:bg-gray-800/50 dark:border-gray-700">
                        <tr>
                            <td colspan="9" class="px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400">Total</td>
                            <td class="px-4 py-3 text-sm font-bold text-right text-gray-900 dark:text-white">
                                Rp {{ number_format(collect($dataDaftarRI['RiRoom'])->sum(function ($r) {
                                    $d = (int)($r['day'] ?? 1);
                                    return (($r['room_price'] ?? 0) + ($r['perawatan_price'] ?? 0) + ($r['common_service'] ?? 0)) * $d;
                                })) }}
                            </td>
                            @if (!$isFormLocked) <td></td> @endif
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

</div>
