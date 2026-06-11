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
     | LISTENER — sync lock saat parent broadcast (post/batal transaksi)
     =============================== */
    #[On('ri.administrasi-selesai')]
    public function onAdministrasiSelesai(?int $riHdrNo = null): void
    {
        if (!$riHdrNo) return;
        // Re-check status DB — lock kalau completed, unlock kalau di-batal-kan.
        if ((int) ($this->riHdrNo ?? 0) === $riHdrNo) {
            $this->isFormLocked = $this->checkRIStatus($this->riHdrNo);
        }
    }

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
        $rows = DB::table('rsmst_trfrooms as t')
            ->leftJoin('rsmst_rooms as r', 'r.room_id', '=', 't.room_id')
            ->select(
                DB::raw("to_char(t.start_date, 'dd/mm/yyyy hh24:mi:ss') as start_date"),
                DB::raw("to_char(t.end_date,   'dd/mm/yyyy hh24:mi:ss') as end_date"),
                't.room_id', 'r.room_name', 't.bed_no',
                't.room_price', 't.perawatan_price', 't.common_service',
                DB::raw("ROUND(nvl(t.day, nvl(t.end_date, sysdate+1) - nvl(t.start_date, sysdate))) as day"),
                't.trfr_no',
            )
            ->where('t.rihdr_no', $riHdrNo)
            ->orderByDesc('t.start_date')
            ->get();

        $this->dataDaftarRI['RiRoom'] = $rows->map(fn($r) => (array) $r)->toArray();

        $active = DB::table('rsmst_trfrooms as t')
            ->leftJoin('rsmst_rooms as r', 'r.room_id', '=', 't.room_id')
            ->select(
                't.room_id', 'r.room_name', 't.bed_no', 't.trfr_no',
                DB::raw("to_char(t.start_date, 'dd/mm/yyyy hh24:mi:ss') as start_date"),
                DB::raw("ROUND(sysdate - t.start_date) as hari_berjalan"),
                't.room_price', 't.perawatan_price', 't.common_service',
            )
            ->where('t.rihdr_no', $riHdrNo)
            ->whereNull('t.end_date')
            ->orderByDesc('t.trfr_no')
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
                $this->appendAdminLogRI($this->riHdrNo, "Hapus Kamar #{$trfrNo}");
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

        // Boleh 0 — kamar transit (mis. UGD sebentar) tidak dihitung biaya
        $newDay = max(0, (int) $newDay);

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

    /* ===============================
     | UPDATE TARIF (KAMAR / PERAWATAN / CS) — via wire:model inline
     =============================== */
    public function updated($property, $value): void
    {
        // Hanya tangani edit tarif inline: dataDaftarRI.RiRoom.{idx}.{kolom}
        if (!preg_match('/^dataDaftarRI\.RiRoom\.(\d+)\.(room_price|perawatan_price|common_service)$/', $property, $m)) {
            return;
        }

        [, $idx, $kolom] = $m;
        $label = match ($kolom) {
            'room_price'      => 'kamar',
            'perawatan_price' => 'perawatan',
            'common_service'  => 'CS',
        };

        $trfrNo = (int) ($this->dataDaftarRI['RiRoom'][(int) $idx]['trfr_no'] ?? 0);
        if (!$trfrNo) {
            return;
        }

        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            $this->findData($this->riHdrNo);
            return;
        }

        $value = max(0, (int) $value);

        // Skip kalau nilai tidak berubah (blur tanpa edit) — tanpa query update & toast
        $current = (int) DB::table('rsmst_trfrooms')->where('trfr_no', $trfrNo)->value($kolom);
        if ($current === $value) {
            return;
        }

        try {
            DB::transaction(function () use ($trfrNo, $kolom, $label, $value) {
                $this->lockRIRow($this->riHdrNo);
                DB::table('rsmst_trfrooms')->where('trfr_no', $trfrNo)->update([$kolom => $value]);
                $this->appendAdminLogRI($this->riHdrNo, 'Ubah tarif ' . $label . " kamar #{$trfrNo} menjadi Rp " . number_format($value));
            });

            $this->findData($this->riHdrNo);
            $this->dispatch('administrasi-ri.updated');
            $this->dispatch('toast', type: 'success', message: 'Tarif ' . $label . ' berhasil diubah menjadi Rp ' . number_format($value) . '.');
        } catch (\RuntimeException $e) {
            $this->findData($this->riHdrNo);
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->findData($this->riHdrNo);
            $this->dispatch('toast', type: 'error', message: 'Gagal update tarif: ' . $e->getMessage());
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
                        <div class="mt-1 text-xs text-success dark:text-success space-x-3">
                            <span>Kamar: <strong>Rp {{ number_format($activeRoom['room_price'] ?? 0) }}</strong>/hr</span>
                            <span>Perawatan: <strong>Rp {{ number_format($activeRoom['perawatan_price'] ?? 0) }}</strong>/hr</span>
                            <span>CS: <strong>Rp {{ number_format($activeRoom['common_service'] ?? 0) }}</strong>/hr</span>
                        </div>
                    </div>
                </div>
                @if (!$isFormLocked)
                    @hasanyrole('Mr|Admin|Perawat|Tu')
                        <button type="button" wire:click="openPindahKamar"
                            class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-semibold rounded-xl text-white bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 transition shrink-0 shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                            </svg>
                            Pindah Kamar
                        </button>
                    @endhasanyrole
                @endif
            </div>
        </div>
    @elseif (!$isFormLocked)
        <div class="p-4 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-2xl text-center">
            <div class="text-sm text-muted dark:text-gray-400 mb-3">Pasien belum di-assign ke kamar</div>
            @hasanyrole('Mr|Admin|Perawat|Tu')
                <button type="button" wire:click="openPindahKamar"
                    class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold rounded-xl text-white bg-emerald-600 hover:bg-emerald-700 dark:bg-emerald-500 transition shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Assign Kamar
                </button>
            @endhasanyrole
        </div>
    @endif

    {{-- ========================================================
         RIWAYAT TRANSFER KAMAR
         ======================================================== --}}
    <div class="overflow-hidden bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between px-4 py-3 border-b border-hairline dark:border-gray-700">
            <h3 class="text-sm font-semibold text-body dark:text-gray-300">Riwayat Kamar</h3>
            <x-badge variant="gray">{{ count($dataDaftarRI['RiRoom'] ?? []) }} record</x-badge>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs font-semibold text-muted uppercase dark:text-gray-400 bg-surface-soft dark:bg-gray-800/50">
                    <tr>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Kamar / Bed</th>
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
                <tbody class="divide-y divide-hairline-soft dark:divide-gray-800">
                    @forelse ($dataDaftarRI['RiRoom'] ?? [] as $item)
                        @php
                            $isActive = empty($item['end_date']);
                            $day      = (int) ($item['day'] ?? 1);
                            $subtotal = (($item['room_price'] ?? 0) + ($item['perawatan_price'] ?? 0) + ($item['common_service'] ?? 0)) * $day;
                            $nextTrfr = $dataDaftarRI['RiRoom'][$loop->index + 1]['trfr_no'] ?? null;
                            // Enter di CS/Hr → fokus Kamar/Hr baris berikutnya (directive @if dilarang di atribut komponen)
                            $nextFocusJs = $nextTrfr
                                ? "setTimeout(() => document.getElementById('harga-kamar-{$nextTrfr}')?.focus(), 100)"
                                : '';
                        @endphp
                        <tr wire:key="room-ri-{{ $item['trfr_no'] ?? $loop->index }}" class="transition {{ $isActive ? 'bg-emerald-50/50 dark:bg-emerald-900/10' : 'hover:bg-surface-soft dark:hover:bg-gray-800/40' }}">
                            <td class="px-4 py-3">
                                @if ($isActive)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-800 dark:text-emerald-200">Aktif</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-surface-soft text-muted dark:bg-gray-800 dark:text-gray-400">Selesai</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="text-sm font-semibold text-ink dark:text-gray-200 leading-tight">
                                    {{ $item['room_name'] ?? $item['room_id'] }}
                                </div>
                                <div class="text-xs text-muted dark:text-gray-400 mt-0.5">
                                    Bed <span class="font-semibold text-body dark:text-gray-300">{{ $item['bed_no'] ?? '-' }}</span>
                                    <span class="ml-1 font-mono text-[10px] text-muted-soft">· {{ $item['room_id'] }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-muted whitespace-nowrap">{{ $item['start_date'] ?? '-' }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-muted whitespace-nowrap">{{ $item['end_date'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-right text-body dark:text-gray-300">
                                @if (!$isFormLocked)
                                    <input type="number" min="0"
                                        value="{{ $day }}"
                                        x-on:change="$wire.updateDay({{ $item['trfr_no'] }}, $event.target.value)"
                                        class="w-16 px-2 py-1 text-xs font-semibold text-right bg-canvas border border-gray-300 rounded-lg dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200 focus:ring-1 focus:ring-blue-500 focus:border-blue-500
                                        [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" />
                                @else
                                    {{ $day }}
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right text-muted dark:text-gray-400 whitespace-nowrap">
                                @if (!$isFormLocked)
                                    <x-text-input-number
                                        id="harga-kamar-{{ $item['trfr_no'] }}"
                                        wire:model="dataDaftarRI.RiRoom.{{ $loop->index }}.room_price"
                                        x-on:keydown.enter.prevent="$el.blur(); setTimeout(() => document.getElementById('harga-prwtn-{{ $item['trfr_no'] }}')?.focus(), 100)"
                                        class="!w-24 px-2 py-1 text-xs font-semibold" />
                                @else
                                    Rp {{ number_format($item['room_price'] ?? 0) }}
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right text-muted dark:text-gray-400 whitespace-nowrap">
                                @if (!$isFormLocked)
                                    <x-text-input-number
                                        id="harga-prwtn-{{ $item['trfr_no'] }}"
                                        wire:model="dataDaftarRI.RiRoom.{{ $loop->index }}.perawatan_price"
                                        x-on:keydown.enter.prevent="$el.blur(); setTimeout(() => document.getElementById('harga-cs-{{ $item['trfr_no'] }}')?.focus(), 100)"
                                        class="!w-24 px-2 py-1 text-xs font-semibold" />
                                @else
                                    Rp {{ number_format($item['perawatan_price'] ?? 0) }}
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right text-muted dark:text-gray-400 whitespace-nowrap">
                                @if (!$isFormLocked)
                                    <x-text-input-number
                                        id="harga-cs-{{ $item['trfr_no'] }}"
                                        wire:model="dataDaftarRI.RiRoom.{{ $loop->index }}.common_service"
                                        x-on:keydown.enter.prevent="$el.blur(); {{ $nextFocusJs }}"
                                        class="!w-24 px-2 py-1 text-xs font-semibold" />
                                @else
                                    Rp {{ number_format($item['common_service'] ?? 0) }}
                                @endif
                            </td>
                            <td class="px-4 py-3 font-semibold text-right text-ink dark:text-gray-200 whitespace-nowrap">
                                Rp {{ number_format($subtotal) }}
                            </td>
                            @if (!$isFormLocked)
                                <td class="px-4 py-3 text-center">
                                    <x-outline-button type="button"
                                        wire:click.prevent="removeRoom({{ $item['trfr_no'] }})"
                                        wire:confirm="Hapus data kamar ini?"
                                        wire:loading.attr="disabled"
                                        wire:target="removeRoom({{ $item['trfr_no'] }})"
                                        class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300" title="Hapus">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </x-outline-button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isFormLocked ? 9 : 10 }}"
                                class="px-4 py-10 text-sm text-center text-muted-soft dark:text-gray-600">
                                Belum ada data kamar
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if (!empty($dataDaftarRI['RiRoom']))
                    <tfoot class="border-t border-hairline bg-surface-soft dark:bg-gray-800/50 dark:border-gray-700">
                        <tr>
                            <td colspan="8" class="px-4 py-3 text-sm font-semibold text-muted dark:text-gray-400">Total</td>
                            <td class="px-4 py-3 text-sm font-bold text-right text-ink dark:text-white">
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
