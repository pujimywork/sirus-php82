<?php
// resources/views/pages/transaksi/ri/emr-ri/pemeriksaan-ri/penunjang/laborat/rm-laboratorium-ri-actions.blade.php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Support\OracleLob;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\WithValidationToast\WithValidationToastTrait;
use App\Http\Traits\Txn\Ri\EmrRITrait;

new class extends Component {
    use WithPagination, WithRenderVersioningTrait, WithValidationToastTrait, EmrRITrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['laborat-order-modal-ri'];

    public ?string $riHdrNo = null;
    public bool $disabled = false;

    /* ── State Modal ── */
    public string $searchItem = '';
    public array $selectedItems = []; // [ clabitem_id => [...item] ]
    public string $drId = ''; // dokter pengirim — picker dari relatedDoctors
    public string $klinisDesc = ''; // Diagnosis/Keterangan Klinis — wajib diisi

    protected function rules(): array
    {
        return [
            'drId' => 'required',
            'klinisDesc' => 'required|string',
        ];
    }

    protected function messages(): array
    {
        return [
            'drId.required' => 'Dokter pengirim harus dipilih.',
            'klinisDesc.required' => 'Diagnosis/Keterangan Klinis harus diisi.',
        ];
    }

    public function mount(?string $riHdrNo = null, bool $disabled = false): void
    {
        $this->riHdrNo = $riHdrNo;
        $this->disabled = $disabled;
        $this->registerAreas(['laborat-order-modal-ri']);
    }

    /* ═══════════════════════════════════════
    | OPEN via parent event
    ═══════════════════════════════════════ */
    #[On('open-rm-laboratorium-ri')]
    public function open(string $riHdrNo): void
    {
        if (empty($riHdrNo)) {
            return;
        }
        $this->riHdrNo = $riHdrNo;
    }

    /* ═══════════════════════════════════════
    | OPEN / CLOSE ORDER MODAL
    ═══════════════════════════════════════ */
    public function openModal(): void
    {
        if ($this->disabled) {
            return;
        }

        $this->selectedItems = [];
        $this->searchItem = '';
        $this->drId = '';
        $this->klinisDesc = '';
        $this->resetValidation();
        $this->resetPage();
        $this->incrementVersion('laborat-order-modal-ri');

        $this->dispatch('open-modal', name: "laborat-order-ri-{$this->riHdrNo}");
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: "laborat-order-ri-{$this->riHdrNo}");
        $this->reset(['selectedItems', 'searchItem', 'drId', 'klinisDesc']);
    }

    /*
     | Daftar dokter terkait kunjungan RI ini, sebagai LOV picker dokter pengirim.
     | Sumber: DPJP (rstxn_rihdrs.dr_id) ∪ visite (rstxn_rivisits) ∪ jasa (rstxn_riactdocs)
     |         ∪ leveling dokter (EMR JSON pengkajianAwalPasienRawatInap.levelingDokter).
     */
    #[Computed]
    public function relatedDoctors()
    {
        if (empty($this->riHdrNo)) {
            return collect();
        }

        $drIds = collect()
            ->merge(DB::table('rstxn_rihdrs')->where('rihdr_no', $this->riHdrNo)->pluck('dr_id'))
            ->merge(DB::table('rstxn_rivisits')->where('rihdr_no', $this->riHdrNo)->pluck('dr_id'))
            ->merge(DB::table('rstxn_riactdocs')->where('rihdr_no', $this->riHdrNo)->pluck('dr_id'))
            ->merge($this->levelingDokterIds())
            ->filter(fn($id) => filled($id))
            ->map(fn($id) => (string) $id)
            ->unique()
            ->values();

        if ($drIds->isEmpty()) {
            return collect();
        }

        return DB::table('rsmst_doctors as d')
            ->whereIn('d.dr_id', $drIds->all())
            ->where('d.active_status', '1')
            ->select('d.dr_id', 'd.dr_name')
            ->distinct()
            ->orderBy('d.dr_name')
            ->get();
    }

    /*
     | dr_id leveling dokter DPJP dari EMR JSON (pengkajian awal RI). CLOB dibaca via OracleLob
     | (tahan snapshot-too-old); Oracle tak support JSON_VALUE → json_decode di PHP.
     */
    private function levelingDokterIds(): array
    {
        $row = DB::table('rstxn_rihdrs')
            ->select('datadaftarri_json')
            ->where('rihdr_no', $this->riHdrNo)
            ->first();

        if (!$row) {
            return [];
        }

        try {
            $jsonRaw = OracleLob::read($row->datadaftarri_json ?? null, 'rstxn_rihdrs', 'rihdr_no', $this->riHdrNo, 'datadaftarri_json');
            $data = $jsonRaw !== '' ? json_decode($jsonRaw, true) : null;
        } catch (\Throwable) {
            return [];
        }

        return collect($data['pengkajianAwalPasienRawatInap']['levelingDokter'] ?? [])
            ->pluck('drId')
            ->all();
    }

    /* ═══════════════════════════════════════
    | QUERY ITEM LAB (paginated + search)
    ═══════════════════════════════════════ */
    #[Computed]
    public function items()
    {
        $search = trim($this->searchItem);

        return DB::table('lbmst_clabitems')->select('clabitem_id', 'clabitem_desc', 'price', 'clabitem_group', 'item_code')->whereNull('clabitem_group')->whereNotNull('clabitem_desc')->when($search, fn($q) => $q->whereRaw('UPPER(clabitem_desc) LIKE ?', ['%' . mb_strtoupper($search) . '%']))->orderBy('clabitem_desc')->paginate(15);
    }

    /* ═══════════════════════════════════════
    | TOGGLE / REMOVE SELECTED
    ═══════════════════════════════════════ */
    public function toggleItem(string $id, string $desc, ?float $price, ?string $itemCode): void
    {
        if (isset($this->selectedItems[$id])) {
            unset($this->selectedItems[$id]);
        } else {
            $this->selectedItems[$id] = [
                'clabitem_id' => $id,
                'clabitem_desc' => $desc,
                'price' => $price,
                'item_code' => $itemCode,
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

    /* ═══════════════════════════════════════
    | KIRIM ORDER LABORATORIUM RI
    ═══════════════════════════════════════ */
    public function kirimLaboratorium(): void
    {
        $this->klinisDesc = trim($this->klinisDesc);
        $this->validateWithToast();

        if (empty($this->selectedItems)) {
            $this->dispatch('toast', type: 'warning', message: 'Pilih minimal satu item pemeriksaan.');
            return;
        }

        if ($this->checkRIStatus($this->riHdrNo)) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, tidak dapat menambah pemeriksaan.');
            return;
        }

        $riData = DB::table('rstxn_rihdrs')->select('reg_no')->where('rihdr_no', $this->riHdrNo)->first();

        if (!$riData) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }

        try {
            DB::transaction(function () use ($riData) {
                $now = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
                $checkupNo = DB::scalar('SELECT NVL(MAX(TO_NUMBER(checkup_no)) + 1, 1) FROM lbtxn_checkuphdrs');

                DB::table('lbtxn_checkuphdrs')->insert([
                    'checkup_no' => $checkupNo,
                    'reg_no' => $riData->reg_no,
                    'dr_id' => $this->drId,
                    'checkup_date' => DB::raw("TO_DATE('{$now}','dd/mm/yyyy hh24:mi:ss')"),
                    'status_rjri' => 'RI',
                    'checkup_status' => 'P',
                    'ref_no' => $this->riHdrNo,
                    'klinis_desc' => trim($this->klinisDesc),
                ]);

                foreach ($this->selectedItems as $item) {
                    $this->insertItemAndChildren($checkupNo, $item);
                }

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Order Lab — ' . collect($this->selectedItems)->pluck('clabitem_desc')->implode(', '), 'MR');
            });

            $this->dispatch('laborat-order-terkirim');
            $this->dispatch('toast', type: 'success', message: count($this->selectedItems) . ' item lab berhasil dikirim.');
            $this->closeModal();
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal mengirim: ' . $e->getMessage());
        }
    }

    /* ── helpers ── */
    private function insertItemAndChildren(int $checkupNo, array $item): void
    {
        $dtlNo = DB::scalar('SELECT NVL(TO_NUMBER(MAX(checkup_dtl)) + 1, 1) FROM lbtxn_checkupdtls');
        DB::table('lbtxn_checkupdtls')->insert([
            'clabitem_id' => $item['clabitem_id'],
            'checkup_no' => $checkupNo,
            'checkup_dtl' => $dtlNo,
            'lab_item_code' => $item['item_code'],
            'price' => $item['price'],
        ]);

        $children = DB::table('lbmst_clabitems')->select('clabitem_id', 'item_code', 'price')->where('clabitem_group', $item['clabitem_id'])->orderBy('item_seq')->orderBy('clabitem_desc')->get();

        foreach ($children as $child) {
            $childDtlNo = DB::scalar('SELECT NVL(TO_NUMBER(MAX(checkup_dtl)) + 1, 1) FROM lbtxn_checkupdtls');
            DB::table('lbtxn_checkupdtls')->insert([
                'clabitem_id' => $child->clabitem_id,
                'checkup_no' => $checkupNo,
                'checkup_dtl' => $childDtlNo,
                'lab_item_code' => $child->item_code,
                'price' => $child->price,
            ]);
        }
    }
};
?>

<div wire:key="laboratorium-ri-{{ $riHdrNo ?? 'new' }}">

    {{-- Tombol Order --}}
    @if (!$disabled)
        <div class="mb-3">
            <x-primary-button type="button" wire:click="openModal" wire:loading.attr="disabled" wire:target="openModal">
                <span wire:loading.remove wire:target="openModal" class="flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Order Laboratorium
                </span>
                <span wire:loading wire:target="openModal" class="flex items-center gap-1.5">
                    <x-loading /> Memuat...
                </span>
            </x-primary-button>
        </div>
    @endif

    {{-- Daftar Lab ditampilkan via rm-daftar-laborat-ri (DB-direct) di parent --}}

    {{-- ═══════════ MODAL ORDER LAB RI ═══════════ --}}
    <x-modal name="laborat-order-ri-{{ $riHdrNo ?? 'new' }}" size="full" height="full"
        focusable>
        <div class="flex flex-col h-full"
            wire:key="{{ $this->renderKey('laborat-order-modal-ri', [$riHdrNo ?? 'new']) }}">

            {{-- Header --}}
            <div class="relative px-6 py-4 border-b border-hairline dark:border-gray-700 shrink-0">
                <div class="absolute inset-0 opacity-[0.05]"
                    style="background-image:radial-gradient(currentColor 1px,transparent 1px);background-size:14px 14px">
                </div>
                <div class="relative flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand/10 dark:bg-brand/15">
                            <svg class="w-5 h-5 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.78 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-ink dark:text-gray-100">Order Pemeriksaan
                                Laboratorium</h2>
                            <p class="text-xs text-muted">No. RI: <span
                                    class="font-mono font-medium">{{ $riHdrNo }}</span></p>
                        </div>
                    </div>
                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- Display Pasien RI --}}
            <div class="border-b border-hairline dark:border-gray-700 shrink-0">
                <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                    wire:key="display-pasien-ri-lab-{{ $riHdrNo }}" />
            </div>

            {{-- Body: dua kolom — KIRI pilih item, KANAN dokter+diagnosis+keranjang --}}
            <div class="flex flex-col flex-1 min-h-0 lg:flex-row">

                {{-- KIRI: Search + Item Grid --}}
                <div class="flex flex-col flex-1 min-h-0">

                    {{-- Search --}}
                    <div class="px-6 py-3 border-b border-hairline-soft dark:border-gray-700 shrink-0">
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-muted-soft" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <input type="text" wire:model.live.debounce.300ms="searchItem"
                                placeholder="Cari item pemeriksaan..."
                                class="w-full py-2 pl-10 pr-4 text-sm border border-gray-300 rounded-lg
                                          focus:ring-2 focus:ring-brand/30 focus:border-brand
                                          dark:bg-gray-800 dark:border-gray-600 dark:text-gray-100" />
                        </div>
                    </div>

                    {{-- Item Grid --}}
                    <div class="flex-1 p-5 overflow-y-auto bg-surface-soft/70 dark:bg-gray-950/20">
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-4">
                            @forelse ($this->items as $item)
                                @php $selected = $this->isSelected($item->clabitem_id); @endphp
                                <button type="button"
                                    wire:click="toggleItem('{{ $item->clabitem_id }}', '{{ addslashes($item->clabitem_desc) }}', {{ $item->price ?? 'null' }}, '{{ $item->item_code }}')"
                                    class="relative flex flex-col items-center justify-center p-3 rounded-xl border-2 text-center transition-all
                                           {{ $selected
                                               ? 'border-brand bg-brand/10 text-brand shadow-sm'
                                               : 'border-hairline bg-canvas hover:border-brand/40 hover:bg-brand/5 text-body dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300' }}">

                                    @if ($selected)
                                        <span
                                            class="absolute top-1.5 right-1.5 flex items-center justify-center w-4 h-4 bg-brand rounded-full">
                                            <svg class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                                    d="M5 13l4 4L19 7" />
                                            </svg>
                                        </span>
                                    @endif

                                    <p class="text-xs font-medium leading-tight">{{ $item->clabitem_desc }}</p>

                                    @if ($item->price)
                                        <p
                                            class="mt-1 text-[10px] {{ $selected ? 'text-brand/70' : 'text-muted-soft' }}">
                                            {{ number_format($item->price) }}
                                        </p>
                                    @endif
                                </button>
                            @empty
                                <div class="py-12 text-center text-muted-soft col-span-full">
                                    <svg class="w-10 h-10 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                    <p class="text-sm">Tidak ada item ditemukan</p>
                                </div>
                            @endforelse
                        </div>

                        @if ($this->items->hasPages())
                            <div class="mt-4">{{ $this->items->links() }}</div>
                        @endif
                    </div>
                </div>

                {{-- KANAN: Dokter Pengirim + Diagnosis + Keranjang --}}
                <div
                    class="flex flex-col w-full min-h-0 border-t lg:w-96 shrink-0 lg:border-t-0 lg:border-l border-hairline dark:border-gray-700 bg-canvas dark:bg-gray-900">

                    {{-- Dokter Pengirim + Diagnosis --}}
                    <div class="px-5 py-3 space-y-3 border-b border-hairline-soft dark:border-gray-700">
                        <div>
                            <x-input-label value="Dokter Pengirim" required />
                            <select wire:model.defer="drId"
                                class="block w-full mt-1 text-sm border-gray-300 rounded-lg shadow-sm focus:border-brand focus:ring-brand dark:bg-gray-800 dark:border-gray-600 dark:text-gray-100">
                                <option value="">— Pilih dokter pengirim —</option>
                                @foreach ($this->relatedDoctors as $dr)
                                    <option value="{{ $dr->dr_id }}">{{ $dr->dr_name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('drId')" class="mt-1" />
                            <p class="mt-1 text-xs text-muted">Dokter terkait kunjungan (DPJP / visite / jasa).</p>
                        </div>
                        <div>
                            <x-input-label value="Diagnosis/Keterangan Klinis" required />
                            <textarea wire:model="klinisDesc" rows="2"
                                placeholder="Diagnosis kerja / keterangan klinis pasien..."
                                class="w-full mt-1 text-sm border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-brand/30 focus:border-brand dark:bg-gray-800 dark:border-gray-600 dark:text-gray-100"></textarea>
                            <x-input-error :messages="$errors->get('klinisDesc')" class="mt-1" />
                        </div>
                    </div>

                    {{-- Header keranjang --}}
                    <div class="flex items-center justify-between px-5 pt-3 pb-1.5">
                        <p class="text-sm font-semibold text-ink dark:text-gray-100">Item Dipilih</p>
                        @if (!empty($selectedItems))
                            <span
                                class="px-2 py-0.5 text-xs font-semibold text-brand bg-brand/10 border border-brand/30 rounded-full">
                                {{ count($selectedItems) }}
                            </span>
                        @endif
                    </div>

                    {{-- List item dipilih (keranjang) --}}
                    <div class="flex-1 px-5 pb-4 space-y-1.5 overflow-y-auto">
                        @forelse ($selectedItems as $id => $sel)
                            <div
                                class="flex items-start justify-between gap-2 p-2.5 border rounded-lg border-brand/20 bg-brand/5">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium leading-tight text-brand">{{ $sel['clabitem_desc'] }}
                                    </p>
                                    @if ($sel['price'])
                                        <p class="mt-0.5 text-[11px] text-brand/60">{{ number_format($sel['price']) }}</p>
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
                                        d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
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
                class="sticky bottom-0 z-10 px-6 py-4 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700 shrink-0">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        @if (!empty($selectedItems))
                            <span
                                class="inline-flex items-center gap-1.5 px-3 py-1 text-sm font-medium text-brand bg-brand/10 border border-brand/30 rounded-full">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 13l4 4L19 7" />
                                </svg>
                                {{ count($selectedItems) }} item dipilih
                            </span>
                        @else
                            <span class="text-xs italic text-muted-soft">Klik item untuk memilih pemeriksaan</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-3">
                        <x-secondary-button wire:click="closeModal">Batal</x-secondary-button>
                        @if (!empty($selectedItems))
                            <x-primary-button type="button" wire:click="kirimLaboratorium"
                                wire:loading.attr="disabled" wire:target="kirimLaboratorium">
                                <span wire:loading.remove wire:target="kirimLaboratorium"
                                    class="flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                    </svg>
                                    Kirim Order
                                </span>
                                <span wire:loading wire:target="kirimLaboratorium" class="flex items-center gap-1.5">
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
