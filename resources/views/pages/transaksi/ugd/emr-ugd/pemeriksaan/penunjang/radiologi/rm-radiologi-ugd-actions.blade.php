<?php
// resources/views/pages/transaksi/ugd/emr-ugd/pemeriksaan/penunjang/radiologi/rm-radiologi-ugd-actions.blade.php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\WithValidationToast\WithValidationToastTrait;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;

new class extends Component {
    use WithPagination, WithRenderVersioningTrait, WithValidationToastTrait, EmrUGDTrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['radiologi-order-modal-ugd'];

    public string $rjNo = '';
    public bool $disabled = false;

    public string $searchItem = '';
    public array $selectedItems = [];
    public string $klinisDesc = ''; // Diagnosis/Keterangan Klinis — wajib diisi

    protected function rules(): array
    {
        return [
            'klinisDesc' => 'required|string',
        ];
    }

    protected function messages(): array
    {
        return [
            'klinisDesc.required' => 'Diagnosis/Keterangan Klinis harus diisi.',
        ];
    }

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(string $rjNo = '', bool $disabled = false): void
    {
        $this->rjNo = $rjNo;
        $this->disabled = $disabled;
        $this->registerAreas($this->renderAreas);
    }

    /* ===============================
     | OPEN / CLOSE MODAL
     =============================== */
    public function openModal(): void
    {
        if ($this->disabled) {
            return;
        }

        $this->selectedItems = [];
        $this->searchItem = '';
        $this->klinisDesc = '';
        $this->resetValidation();
        $this->resetPage();
        $this->incrementVersion('radiologi-order-modal-ugd');

        $this->dispatch('open-modal', name: "radiologi-order-ugd-{$this->rjNo}");
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: "radiologi-order-ugd-{$this->rjNo}");
        $this->reset(['selectedItems', 'searchItem', 'klinisDesc']);
    }

    /* ===============================
     | COMPUTED: ITEM LIST
     =============================== */
    #[Computed]
    public function items()
    {
        $search = trim($this->searchItem);

        return DB::table('rsmst_radiologis')->select('rad_id', 'rad_desc', 'rad_price')->whereNotNull('rad_desc')->when($search, fn($q) => $q->whereRaw('UPPER(rad_desc) LIKE ?', ['%' . mb_strtoupper($search) . '%']))->orderBy('rad_desc', 'asc')->paginate(15);
    }

    /* ===============================
     | TOGGLE / REMOVE ITEM
     =============================== */
    public function toggleItem(string $id, string $desc, ?float $price): void
    {
        if (isset($this->selectedItems[$id])) {
            unset($this->selectedItems[$id]);
        } else {
            $this->selectedItems[$id] = [
                'rad_id' => $id,
                'rad_desc' => $desc,
                'rad_price' => $price,
            ];
        }
    }

    public function isSelected(string $id): bool
    {
        return isset($this->selectedItems[$id]);
    }

    public function removeSelected(string $id): void
    {
        unset($this->selectedItems[$id]);
    }

    /* ===============================
     | KIRIM ORDER RADIOLOGI
     =============================== */
    public function kirimRadiologi(): void
    {
        if (empty($this->selectedItems)) {
            $this->dispatch('toast', type: 'warning', message: 'Pilih minimal satu item pemeriksaan.');
            return;
        }

        // Guard: Diagnosis/Keterangan Klinis wajib diisi (rules + toast)
        $this->klinisDesc = trim($this->klinisDesc);
        $this->validateWithToast();

        if ($this->checkUGDStatus($this->rjNo)) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, tidak dapat menambah pemeriksaan.');
            return;
        }

        // Resolve nama dokter pengirim dari dr_id kunjungan UGD
        $ugdData = DB::table('rstxn_ugdhdrs')->select('dr_id')->where('rj_no', $this->rjNo)->first();
        $drPengirimName = $ugdData ? DB::table('rsmst_doctors')->where('dr_id', $ugdData->dr_id)->value('dr_name') : null;

        $now = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        $itemCount = count($this->selectedItems);

        try {
            DB::transaction(function () use ($now, $drPengirimName) {
                foreach ($this->selectedItems as $item) {
                    $radDtlNo = DB::scalar('SELECT NVL(MAX(TO_NUMBER(rad_dtl)) + 1, 1) FROM rstxn_ugdrads');

                    DB::table('rstxn_ugdrads')->insert([
                        'rad_dtl' => $radDtlNo,
                        'rad_id' => $item['rad_id'],
                        'rj_no' => $this->rjNo,
                        'rad_price' => $item['rad_price'],
                        'dr_pengirim' => $drPengirimName,
                        'dr_radiologi' => 'dr. M.A. Budi Purwito, Sp.Rad.',
                        'klinis_desc' => trim($this->klinisDesc),
                        'waktu_entry' => DB::raw("TO_DATE('{$now}','dd/mm/yyyy hh24:mi:ss')"),
                    ]);
                }

                $namaItems = collect($this->selectedItems)->pluck('rad_desc')->filter()->implode(', ');
                $this->appendAdminLogUGD((int) $this->rjNo, 'Order Radiologi UGD — ' . $namaItems, 'MR');
            });

            $this->dispatch('toast', type: 'success', message: "{$itemCount} item radiologi berhasil dikirim.");
            $this->dispatch('radiologi-order-terkirim');
            $this->closeModal();
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal mengirim: ' . $e->getMessage());
        }
    }
};
?>

<div>
    <div class="mb-3">
        <x-primary-button type="button" wire:click="openModal" wire:loading.attr="disabled" wire:target="openModal"
            :disabled="$disabled">
            <span wire:loading.remove wire:target="openModal" class="flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Order Radiologi
            </span>
            <span wire:loading wire:target="openModal" class="flex items-center gap-1.5">
                <x-loading /> Memuat...
            </span>
        </x-primary-button>
    </div>

    <x-modal name="radiologi-order-ugd-{{ $rjNo }}" size="full"
        height="full" focusable>
        <div class="flex flex-col h-full"
            wire:key="{{ $this->renderKey('radiologi-order-modal-ugd', [$rjNo ?: 'empty']) }}">

            {{-- Header --}}
            <div class="relative px-6 py-4 border-b border-hairline dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.05]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>
                <div class="relative flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-blue/10 dark:bg-brand-blue/15">
                            <svg class="w-5 h-5 text-brand-blue" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-ink dark:text-gray-100">Order Pemeriksaan
                                Radiologi</h2>
                            <p class="text-sm text-muted">No. UGD: <span
                                    class="font-mono font-medium">{{ $rjNo }}</span></p>
                        </div>
                    </div>
                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- Display Pasien UGD --}}
            <div class="border-b border-hairline dark:border-gray-700">
                <livewire:pages::transaksi.ugd.display-pasien-ugd.display-pasien-ugd :rjNo="$rjNo"
                    wire:key="display-pasien-ugd-radiologi-{{ $rjNo }}" />
            </div>

            {{-- Body: dua kolom — KIRI pilih item, KANAN diagnosis + keranjang --}}
            <div class="flex flex-col flex-1 min-h-0 lg:flex-row">

                {{-- KIRI: Search + Item Grid --}}
                <div class="flex flex-col flex-1 min-h-0">

                    {{-- Search --}}
                    <div class="px-6 py-3 border-b border-hairline-soft dark:border-gray-700">
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-muted-soft" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <input type="text" wire:model.live.debounce.300ms="searchItem"
                                placeholder="Cari item pemeriksaan radiologi..."
                                class="w-full py-2 pl-10 pr-4 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-blue/30 focus:border-brand-blue dark:bg-gray-800 dark:border-gray-600 dark:text-gray-100" />
                        </div>
                    </div>

                    {{-- Item Grid --}}
                    <div class="flex-1 p-5 overflow-y-auto bg-surface-soft/70 dark:bg-gray-950/20">
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-4">
                            @forelse ($this->items as $item)
                                @php $selected = $this->isSelected($item->rad_id); @endphp
                                <button type="button"
                                    wire:click="toggleItem('{{ $item->rad_id }}', '{{ addslashes($item->rad_desc) }}', {{ $item->rad_price ?? 'null' }})"
                                    class="relative flex flex-col items-center justify-center p-3 rounded-xl border-2 text-center transition-all
                                        {{ $selected
                                            ? 'border-brand-blue bg-brand-blue/10 text-brand-blue shadow-sm'
                                            : 'border-hairline bg-canvas hover:border-brand-blue/40 hover:bg-brand-blue/5 text-body dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300' }}">

                                    @if ($selected)
                                        <span
                                            class="absolute top-1.5 right-1.5 flex items-center justify-center w-4 h-4 bg-brand-blue rounded-full">
                                            <svg class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                                    d="M5 13l4 4L19 7" />
                                            </svg>
                                        </span>
                                    @endif

                                    <svg class="w-6 h-6 mb-1.5 {{ $selected ? 'text-brand-blue' : 'text-gray-300 dark:text-gray-600' }}"
                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                            d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z" />
                                    </svg>

                                    <p class="text-sm font-medium leading-tight">{{ $item->rad_desc }}</p>
                                    @if ($item->rad_price)
                                        <p
                                            class="mt-1 text-[10px] {{ $selected ? 'text-brand-blue/70' : 'text-muted-soft' }}">
                                            {{ number_format($item->rad_price) }}
                                        </p>
                                    @endif
                                </button>
                            @empty
                                <div class="py-12 text-center text-muted-soft col-span-full">
                                    <svg class="w-10 h-10 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z" />
                                    </svg>
                                    <p class="text-base">Tidak ada item ditemukan</p>
                                </div>
                            @endforelse
                        </div>

                        @if ($this->items->hasPages())
                            <div class="mt-4">{{ $this->items->links() }}</div>
                        @endif
                    </div>
                </div>

                {{-- KANAN: Diagnosis + Keranjang item dipilih --}}
                <div
                    class="flex flex-col w-full min-h-0 border-t lg:w-96 shrink-0 lg:border-t-0 lg:border-l border-hairline dark:border-gray-700 bg-canvas dark:bg-gray-900">

                    {{-- Diagnosis/Keterangan Klinis --}}
                    <div class="px-5 py-3 border-b border-hairline-soft dark:border-gray-700">
                        <x-input-label value="Diagnosis/Keterangan Klinis" required />
                        <textarea wire:model="klinisDesc" rows="2"
                            placeholder="Diagnosis kerja / keterangan klinis pasien..."
                            class="w-full mt-1 text-sm border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-brand-blue/30 focus:border-brand-blue dark:bg-gray-800 dark:border-gray-600 dark:text-gray-100"></textarea>
                        <x-input-error :messages="$errors->get('klinisDesc')" class="mt-1" />
                    </div>

                    {{-- Header keranjang --}}
                    <div class="flex items-center justify-between px-5 pt-3 pb-1.5">
                        <p class="text-sm font-semibold text-ink dark:text-gray-100">Item Dipilih</p>
                        @if (!empty($selectedItems))
                            <span
                                class="px-2 py-0.5 text-xs font-semibold text-brand-blue bg-brand-blue/10 border border-brand-blue/30 rounded-full">
                                {{ count($selectedItems) }}
                            </span>
                        @endif
                    </div>

                    {{-- List item dipilih (keranjang) --}}
                    <div class="flex-1 px-5 pb-4 space-y-1.5 overflow-y-auto">
                        @forelse ($selectedItems as $id => $sel)
                            <div
                                class="flex items-start justify-between gap-2 p-2.5 border rounded-lg border-brand-blue/20 bg-brand-blue/5">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium leading-tight text-brand-blue">
                                        {{ $sel['rad_desc'] }}</p>
                                    @if ($sel['rad_price'])
                                        <p class="mt-0.5 text-[11px] text-brand-blue/60">
                                            {{ number_format($sel['rad_price']) }}</p>
                                    @endif
                                </div>
                                <button type="button" wire:click="removeSelected('{{ $id }}')"
                                    class="mt-0.5 shrink-0 text-muted-soft hover:text-red-500 transition-colors">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                        @empty
                            <div
                                class="flex flex-col items-center justify-center h-full py-10 text-center text-muted-soft">
                                <svg class="w-10 h-10 mb-2 text-gray-300" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z" />
                                </svg>
                                <p class="text-sm font-medium">Belum ada item dipilih</p>
                                <p class="mt-0.5 text-xs text-muted-soft">Klik item di kiri untuk memilih</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Footer --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        @if (!empty($selectedItems))
                            <span
                                class="inline-flex items-center gap-1.5 px-3 py-1 text-base font-medium text-brand-blue bg-brand-blue/10 border border-brand-blue/30 rounded-full">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 13l4 4L19 7" />
                                </svg>
                                {{ count($selectedItems) }} item dipilih
                            </span>
                        @else
                            <span class="text-sm italic text-muted-soft">Klik item untuk memilih pemeriksaan</span>
                        @endif
                    </div>

                    <div class="flex items-center gap-3">
                        <x-secondary-button wire:click="closeModal">Batal</x-secondary-button>

                        @if (!empty($selectedItems))
                            <x-primary-button type="button" wire:click="kirimRadiologi" wire:loading.attr="disabled"
                                wire:target="kirimRadiologi">
                                <span wire:loading.remove wire:target="kirimRadiologi"
                                    class="flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                    </svg>
                                    Kirim Order
                                </span>
                                <span wire:loading wire:target="kirimRadiologi" class="flex items-center gap-1.5">
                                    <x-loading /> Mengirim...
                                </span>
                            </x-primary-button>
                        @endif
                    </div>
                </div>
            </div>

        </div>
    </x-modal>
</div>
