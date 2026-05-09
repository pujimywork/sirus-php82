<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use EmrRITrait, WithRenderVersioningTrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-pindah-kamar-ri'];

    public bool   $isOpen   = false;
    public ?int   $riHdrNo  = null;

    public ?array $activeRoom = null;   // kamar sekarang (end_date null)
    public array $availableBeds = [];   // list bed master + occupancy status
    public bool $forceOccupiedBed = false;  // toggle paksa pilih bed terpakai

    public array $formEntry = [
        'trfrDate'       => '',
        'roomId'         => '',
        'roomName'       => '',
        'roomBedNo'      => '',
        'roomPrice'      => '',
        'perawatanPrice' => '',
        'commonService'  => '',
        'roomDay'        => '1',
    ];

    private function nowFormatted(): string
    {
        return Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);
    }

    /* ===============================
     | OPEN — dipanggil dari EMR RI & Daftar RI
     =============================== */
    #[On('emr-ri.pindah-kamar.open')]
    public function openModal(int|string $riHdrNo): void
    {
        $this->riHdrNo  = (int) $riHdrNo;
        $this->isOpen   = true;

        $this->resetFormEntry();
        $this->loadActiveRoom();
        $this->incrementVersion('modal-pindah-kamar-ri');

        $this->dispatch('open-modal', name: 'pindah-kamar-ri');
    }

    private function loadActiveRoom(): void
    {
        $active = DB::table('rsmst_trfrooms')
            ->select(
                'room_id', 'bed_no', 'trfr_no',
                DB::raw("to_char(start_date, 'dd/mm/yyyy hh24:mi:ss') as start_date"),
                DB::raw("ROUND(sysdate - start_date) as hari_berjalan"),
                'room_price', 'perawatan_price', 'common_service',
            )
            ->where('rihdr_no', $this->riHdrNo)
            ->whereNull('end_date')
            ->orderByDesc('trfr_no')
            ->first();

        $this->activeRoom = $active ? (array) $active : null;
    }

    /* ===============================
     | LOV SELECTED — ROOM BARU
     =============================== */
    #[On('lov.selected.pindah-kamar-ri')]
    public function onRoomSelected(?array $payload): void
    {
        if (!$payload) {
            $this->formEntry['roomId']    = '';
            $this->formEntry['roomName']  = '';
            $this->formEntry['roomBedNo'] = '';
            return;
        }

        $this->formEntry['roomId']    = $payload['room_id'];
        $this->formEntry['roomName']  = $payload['room_name'];
        $this->formEntry['roomBedNo'] = $payload['bed_no'] ?? '';

        $room = DB::table('rsmst_rooms')
            ->select('room_price', 'perawatan_price', 'common_service')
            ->where('room_id', $payload['room_id'])
            ->first();

        $this->formEntry['roomPrice']      = $room->room_price      ?? 0;
        $this->formEntry['perawatanPrice'] = $room->perawatan_price ?? 0;
        $this->formEntry['commonService']  = $room->common_service  ?? 0;

        $this->loadBedsForRoom($payload['room_id']);

        $this->dispatch('focus-input-pindah-bed');
    }

    private function loadBedsForRoom(string $roomId): void
    {
        $rows = DB::table('rsmst_beds as b')
            ->leftJoin('rsmst_trfrooms as t', function ($j) {
                $j->on('t.room_id', '=', 'b.room_id')
                  ->on('t.bed_no', '=', 'b.bed_no')
                  ->whereNull('t.end_date');
            })
            ->select('b.bed_no', 'b.bed_desc', 't.rihdr_no as occupied_by')
            ->where('b.room_id', $roomId)
            ->orderBy('b.bed_no')
            ->get();

        $this->availableBeds = $rows->map(fn($r) => [
            'bed_no'      => $r->bed_no,
            'bed_desc'    => $r->bed_desc,
            'is_occupied' => !is_null($r->occupied_by),
            'occupied_by' => $r->occupied_by,
        ])->toArray();
    }

    public function selectBed(string $bedNo): void
    {
        $this->formEntry['roomBedNo'] = $bedNo;
        $this->resetErrorBag('formEntry.roomBedNo');
    }

    public function isSelectedBedOccupied(): bool
    {
        $sel = $this->formEntry['roomBedNo'] ?? '';
        if ($sel === '') return false;
        foreach ($this->availableBeds as $b) {
            if ($b['bed_no'] === $sel) return $b['is_occupied'];
        }
        return false;
    }

    public function getOccupantInfo(string $bedNo): ?int
    {
        foreach ($this->availableBeds as $b) {
            if ($b['bed_no'] === $bedNo && $b['is_occupied']) {
                return $b['occupied_by'] ?? null;
            }
        }
        return null;
    }

    /* ===============================
     | SIMPAN PINDAH KAMAR
     =============================== */
    public function simpanPindahKamar(): void
    {
        $this->validate(
            [
                'formEntry.trfrDate'       => 'bail|required|date_format:d/m/Y H:i:s',
                'formEntry.roomId'         => 'bail|required|exists:rsmst_rooms,room_id',
                'formEntry.roomBedNo'      => 'bail|required',
                'formEntry.roomPrice'      => 'bail|required|numeric|min:0',
                'formEntry.perawatanPrice' => 'bail|required|numeric|min:0',
                'formEntry.commonService'  => 'bail|required|numeric|min:0',
                'formEntry.roomDay'        => 'bail|required|numeric|min:1',
            ],
            [
                'formEntry.trfrDate.required'       => 'Tanggal pindah wajib diisi.',
                'formEntry.trfrDate.date_format'    => 'Format: dd/mm/yyyy hh:mm:ss.',
                'formEntry.roomId.required'         => 'Kamar baru wajib dipilih.',
                'formEntry.roomId.exists'           => 'Kamar tidak valid.',
                'formEntry.roomBedNo.required'      => 'Nomor bed wajib diisi.',
                'formEntry.roomPrice.required'      => 'Tarif kamar wajib diisi.',
                'formEntry.roomDay.required'        => 'Jumlah hari wajib diisi.',
                'formEntry.roomDay.min'             => 'Minimal 1 hari.',
            ],
        );

        try {
            DB::transaction(function () {
                $this->lockRIRow($this->riHdrNo);

                // Tutup kamar aktif sekarang
                if ($this->activeRoom) {
                    $longDay = DB::table('rsmst_trfrooms')
                        ->select(DB::raw("ROUND(TO_DATE('" . $this->formEntry['trfrDate'] . "','dd/mm/yyyy hh24:mi:ss') - start_date) as day"))
                        ->where('trfr_no', $this->activeRoom['trfr_no'])
                        ->first();

                    DB::table('rsmst_trfrooms')
                        ->where('trfr_no', $this->activeRoom['trfr_no'])
                        ->update([
                            'end_date' => DB::raw("TO_DATE('" . $this->formEntry['trfrDate'] . "','dd/mm/yyyy hh24:mi:ss')"),
                            'day'      => max(1, (int) ($longDay->day ?? 1)),
                        ]);
                }

                // Insert kamar baru
                $last = DB::table('rsmst_trfrooms')
                    ->select(DB::raw("nvl(max(trfr_no)+1,1) as trfr_no_max"))
                    ->first();

                DB::table('rsmst_trfrooms')->insert([
                    'trfr_no'         => $last->trfr_no_max,
                    'rihdr_no'        => $this->riHdrNo,
                    'room_id'         => $this->formEntry['roomId'],
                    'bed_no'          => $this->formEntry['roomBedNo'],
                    'start_date'      => DB::raw("TO_DATE('" . $this->formEntry['trfrDate'] . "','dd/mm/yyyy hh24:mi:ss')"),
                    'room_price'      => $this->formEntry['roomPrice'],
                    'perawatan_price' => $this->formEntry['perawatanPrice'],
                    'common_service'  => $this->formEntry['commonService'],
                    'day'             => $this->formEntry['roomDay'],
                ]);

                // Update room aktif di header RI
                DB::table('rstxn_rihdrs')
                    ->where('rihdr_no', $this->riHdrNo)
                    ->update([
                        'room_id' => $this->formEntry['roomId'],
                        'bed_no'  => $this->formEntry['roomBedNo'],
                    ]);

                $from = $this->activeRoom ? $this->activeRoom['room_id'] : '-';
                $this->appendAdminLogRI(
                    $this->riHdrNo,
                    "Pindah Kamar: {$from} → {$this->formEntry['roomName']} Bed {$this->formEntry['roomBedNo']}"
                );
            });

            $this->dispatch('toast', type: 'success', message: 'Pasien berhasil dipindahkan ke ' . $this->formEntry['roomName'] . ' Bed ' . $this->formEntry['roomBedNo'] . '.');
            $this->dispatch('administrasi-ri.updated');
            $this->dispatch('daftar-ri.refresh');
            $this->closeModal();
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
        $this->resetFormEntry();
        $this->activeRoom       = null;
        $this->availableBeds    = [];
        $this->forceOccupiedBed = false;
        $this->riHdrNo          = null;
        $this->resetValidation();
        $this->dispatch('close-modal', name: 'pindah-kamar-ri');
    }

    public function refreshTrfrDate(): void
    {
        $this->formEntry['trfrDate'] = $this->nowFormatted();
        $this->resetErrorBag('formEntry.trfrDate');
    }

    public function resetFormEntry(): void
    {
        $this->reset(['formEntry']);
        $this->formEntry['trfrDate'] = $this->nowFormatted();
        $this->formEntry['roomDay']  = '1';
        $this->availableBeds         = [];
        $this->resetValidation();
        $this->incrementVersion('modal-pindah-kamar-ri');
    }
};
?>

<div>
    <x-modal name="pindah-kamar-ri" size="2xl" focusable>
        <div class="p-6 space-y-5"
            wire:key="{{ $this->renderKey('modal-pindah-kamar-ri', [$riHdrNo ?? 'new']) }}"
            x-data
            x-on:focus-input-pindah-bed.window="$nextTick(() => $refs.inputPindahBed?.focus())">

            {{-- Header --}}
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center w-9 h-9 rounded-xl bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Pindah Kamar</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">No. RI: <span class="font-mono font-semibold">{{ $riHdrNo ?? '-' }}</span></p>
                    </div>
                </div>
                <button type="button" wire:click="closeModal"
                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Kamar sekarang --}}
            @if ($activeRoom)
                <div class="flex items-center gap-3 p-3 rounded-xl bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                    <div class="text-xs text-gray-500 dark:text-gray-400 shrink-0">Kamar sekarang</div>
                    <div class="flex items-center gap-2">
                        <span class="font-mono font-bold text-gray-800 dark:text-gray-200">{{ $activeRoom['room_id'] }}</span>
                        <span class="text-gray-400">·</span>
                        <span class="text-sm text-gray-700 dark:text-gray-300">Bed <strong>{{ $activeRoom['bed_no'] ?? '-' }}</strong></span>
                        <span class="text-gray-400">·</span>
                        <span class="text-xs text-gray-500">Hari ke-{{ $activeRoom['hari_berjalan'] ?? 0 }}</span>
                    </div>
                    <svg class="w-4 h-4 text-blue-500 shrink-0 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                    </svg>
                    <div class="text-xs font-semibold text-blue-600 dark:text-blue-400 shrink-0">Kamar baru</div>
                </div>
            @else
                <div class="p-3 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 text-xs text-amber-700 dark:text-amber-300">
                    Pasien belum memiliki kamar aktif — akan dilakukan assign kamar awal.
                </div>
            @endif

            {{-- Form --}}
            @if (empty($formEntry['roomId']))
                <livewire:lov.room.lov-room target="pindah-kamar-ri" label="Pilih Kamar Baru"
                    placeholder="Ketik kode/nama kamar..."
                    wire:key="lov-pindah-room-{{ $riHdrNo }}-{{ $renderVersions['modal-pindah-kamar-ri'] ?? 0 }}" />
            @else
                <div class="space-y-4">
                    {{-- Row 1: Tgl Pindah + Kamar + Bed --}}
                    <div class="grid grid-cols-12 gap-3">
                        <div class="col-span-5">
                            <x-input-label value="Tanggal Pindah" class="mb-1" />
                            <div class="flex gap-1">
                                <x-text-input wire:model="formEntry.trfrDate" placeholder="dd/mm/yyyy hh:mm:ss"
                                    class="flex-1 text-sm font-mono min-w-0" />
                                <button type="button" wire:click="refreshTrfrDate" title="Waktu sekarang"
                                    class="shrink-0 px-2 text-gray-400 hover:text-blue-500 dark:hover:text-blue-400 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                </button>
                            </div>
                            @error('formEntry.trfrDate') <x-input-error :messages="$message" class="mt-1" /> @enderror
                        </div>
                        <div class="col-span-5">
                            <x-input-label value="Kamar Baru" class="mb-1" />
                            <x-text-input wire:model="formEntry.roomName" disabled class="w-full text-sm" />
                        </div>
                        <div class="col-span-2">
                            <x-input-label value="Bed No" class="mb-1" />
                            <x-text-input wire:model.live="formEntry.roomBedNo" class="w-full text-sm" placeholder="-"
                                x-ref="inputPindahBed"
                                x-on:keydown.enter.prevent="$wire.simpanPindahKamar()" />
                            @error('formEntry.roomBedNo') <x-input-error :messages="$message" class="mt-1" /> @enderror
                        </div>
                    </div>

                    {{-- Bed picker --}}
                    @if (!empty($availableBeds))
                        <div>
                            <x-input-label value="Pilih Bed Tersedia" class="mb-1" />
                            <div class="flex flex-wrap items-center gap-2">
                                @foreach ($availableBeds as $bed)
                                    @php
                                        $isOcc = $bed['is_occupied'];
                                        $isSel = ($formEntry['roomBedNo'] ?? '') === $bed['bed_no'];
                                        $clickable = !$isOcc || $forceOccupiedBed;
                                    @endphp
                                    <button type="button"
                                        @if($clickable) wire:click="selectBed('{{ $bed['bed_no'] }}')" @endif
                                        @disabled(!$clickable)
                                        title="{{ $bed['bed_desc'] ?? '' }}{{ $isOcc ? ' — terpakai oleh RI #' . $bed['occupied_by'] : '' }}"
                                        class="px-3 py-1.5 rounded-lg text-xs font-mono font-semibold border transition
                                            {{ $isSel
                                                ? ($isOcc
                                                    ? 'bg-amber-500 text-white border-amber-500 ring-2 ring-amber-300 dark:ring-amber-700'
                                                    : 'bg-blue-600 text-white border-blue-600 ring-2 ring-blue-300 dark:ring-blue-700')
                                                : ($isOcc
                                                    ? ($forceOccupiedBed
                                                        ? 'bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-300 border-amber-300 dark:border-amber-700 hover:border-amber-500'
                                                        : 'bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-600 border-gray-200 dark:border-gray-700 cursor-not-allowed line-through')
                                                    : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-700 hover:border-blue-500 hover:text-blue-600') }}">
                                        Bed {{ $bed['bed_no'] }}
                                        @if ($isOcc)
                                            <span class="ml-1 text-[10px]">· RI #{{ $bed['occupied_by'] }}</span>
                                        @endif
                                    </button>
                                @endforeach
                                <x-toggle wire:model.live="forceOccupiedBed" :trueValue="true" :falseValue="false"
                                    label="Paksa pilih bed terpakai" class="ml-2" />
                            </div>
                            @if ($this->isSelectedBedOccupied())
                                <div class="mt-2 flex items-start gap-1.5 px-2.5 py-1.5 text-[11px] text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-900/20 border border-amber-300 dark:border-amber-700 rounded-lg">
                                    <svg class="w-3.5 h-3.5 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span>Bed dipilih masih ditempati RI #{{ $this->getOccupantInfo($formEntry['roomBedNo'] ?? '') }}. Pastikan koordinasi atau update data sebelum simpan.</span>
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- Row 2: Tarif ×3 + Hari --}}
                    <div class="grid grid-cols-12 gap-3">
                        <div class="col-span-3">
                            <x-input-label value="Tarif Kamar/Hari" class="mb-1" />
                            <x-text-input-number wire:model="formEntry.roomPrice" />
                            @error('formEntry.roomPrice') <x-input-error :messages="$message" class="mt-1" /> @enderror
                        </div>
                        <div class="col-span-3">
                            <x-input-label value="Perawatan/Hari" class="mb-1" />
                            <x-text-input-number wire:model="formEntry.perawatanPrice" />
                            @error('formEntry.perawatanPrice') <x-input-error :messages="$message" class="mt-1" /> @enderror
                        </div>
                        <div class="col-span-4">
                            <x-input-label value="Common Service/Hari" class="mb-1" />
                            <x-text-input-number wire:model="formEntry.commonService" />
                            @error('formEntry.commonService') <x-input-error :messages="$message" class="mt-1" /> @enderror
                        </div>
                        <div class="col-span-2">
                            <x-input-label value="Est. Hari" class="mb-1" />
                            <x-text-input wire:model.live="formEntry.roomDay" placeholder="Hari" class="w-full text-sm"
                                x-on:keydown.enter.prevent="$wire.simpanPindahKamar()" />
                            @error('formEntry.roomDay') <x-input-error :messages="$message" class="mt-1" /> @enderror
                        </div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="flex justify-end gap-2 pt-2 border-t border-gray-100 dark:border-gray-800">
                    <x-secondary-button wire:click="resetFormEntry" type="button">
                        Ganti Kamar
                    </x-secondary-button>
                    <x-secondary-button wire:click="closeModal" type="button">
                        Batal
                    </x-secondary-button>
                    <x-primary-button wire:click.prevent="simpanPindahKamar" wire:loading.attr="disabled"
                        wire:target="simpanPindahKamar">
                        <span wire:loading.remove wire:target="simpanPindahKamar">
                            {{ $activeRoom ? 'Konfirmasi Pindah' : 'Assign Kamar' }}
                        </span>
                        <span wire:loading wire:target="simpanPindahKamar"><x-loading class="w-4 h-4" /></span>
                    </x-primary-button>
                </div>
            @endif

        </div>
    </x-modal>
</div>
