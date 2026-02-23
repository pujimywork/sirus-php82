<?php

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Reactive;

new class extends Component {
    /** target untuk membedakan LOV ini dipakai di form mana */
    public string $target = 'default';

    /** UI */
    public string $label = 'Cari Dokter';
    public string $placeholder = 'Ketik nama/kode dokter...';

    /** state */
    public string $search = '';
    public array $options = [];
    public bool $isOpen = false;
    public int $selectedIndex = 0;

    /** selected state (buat mode selected + edit) */
    public ?array $selected = null;

    /**
     * Mode edit: parent bisa kirim dr_id yang sudah tersimpan.
     * Cukup kirim initialDrId, sisanya akan di-load dari DB.
     */
    #[Reactive]
    public ?string $initialDrId = null;

    /**
     * Filter berdasarkan poli_id jika diperlukan
     * Berguna untuk form yang hanya ingin menampilkan dokter dari poli tertentu
     */
    #[Reactive]
    public ?string $filterPoliId = null;

    /**
     * Mode disabled: jika true, tombol "Ubah" akan hilang saat selected.
     * Berguna untuk form yang sudah selesai/tidak boleh diedit.
     */
    public bool $disabled = false;

    /**
     * Tampilkan info tambahan di dropdown
     */
    public bool $showAdditionalInfo = true;

    public function mount(): void
    {
        $this->loadInitialData();
    }

    protected function loadInitialData(): void
    {
        if (empty($this->initialDrId)) {
            return;
        }

        $row = DB::table('rsmst_doctors as a')->leftJoin('rsmst_polis as b', 'a.poli_id', '=', 'b.poli_id')->select('a.dr_id', 'a.dr_address', 'a.dr_phone', 'a.dr_name', 'a.poli_id', 'a.poli_price', 'a.ugd_price', 'a.basic_salary', 'a.contribution_status', 'a.active_status', 'a.rs_admin', 'a.kd_dr_bpjs', 'a.dr_uuid', 'a.dr_nik', 'a.poli_price_bpjs', 'a.ugd_price_bpjs', 'b.poli_desc', 'b.kd_poli_bpjs', 'b.poli_uuid', 'b.spesialis_status')->where('a.dr_id', $this->initialDrId)->first();

        if ($row) {
            $this->setSelectedFromRow($row);
        }
    }

    protected function setSelectedFromRow($row): void
    {
        $this->selected = [
            'dr_id' => (string) $row->dr_id,
            'dr_address' => (string) ($row->dr_address ?? ''),
            'dr_phone' => (string) ($row->dr_phone ?? ''),
            'dr_name' => (string) ($row->dr_name ?? ''),
            'poli_id' => (string) ($row->poli_id ?? ''),
            'poli_desc' => (string) ($row->poli_desc ?? ''),
            'poli_price' => (string) ($row->poli_price ?? ''),
            'ugd_price' => (string) ($row->ugd_price ?? ''),
            'basic_salary' => (string) ($row->basic_salary ?? ''),
            'contribution_status' => (string) ($row->contribution_status ?? ''),
            'active_status' => (string) ($row->active_status ?? ''),
            'rs_admin' => (string) ($row->rs_admin ?? ''),
            'kd_dr_bpjs' => (string) ($row->kd_dr_bpjs ?? ''),
            'dr_uuid' => (string) ($row->dr_uuid ?? ''),
            'dr_nik' => (string) ($row->dr_nik ?? ''),
            'poli_price_bpjs' => (string) ($row->poli_price_bpjs ?? ''),
            'ugd_price_bpjs' => (string) ($row->ugd_price_bpjs ?? ''),
            'kd_poli_bpjs' => (string) ($row->kd_poli_bpjs ?? ''),
            'spesialis_status' => (string) ($row->spesialis_status ?? ''),
        ];
    }

    public function updatedSearch(): void
    {
        // kalau sudah selected, jangan cari lagi
        if ($this->selected !== null) {
            return;
        }

        $keyword = trim($this->search);

        // minimal 2 char
        if (mb_strlen($keyword) < 2) {
            $this->closeAndResetList();
            return;
        }

        // ===== 1) exact match by dr_id =====
        if (ctype_digit($keyword)) {
            $exactQuery = DB::table('rsmst_doctors as a')->leftJoin('rsmst_polis as b', 'a.poli_id', '=', 'b.poli_id')->select('a.dr_id', 'a.dr_address', 'a.dr_phone', 'a.dr_name', 'a.poli_id', 'a.poli_price', 'a.ugd_price', 'a.basic_salary', 'a.contribution_status', 'a.active_status', 'a.rs_admin', 'a.kd_dr_bpjs', 'a.dr_uuid', 'a.dr_nik', 'a.poli_price_bpjs', 'a.ugd_price_bpjs', 'b.poli_desc', 'b.kd_poli_bpjs', 'b.poli_uuid', 'b.spesialis_status')->where('a.dr_id', $keyword);

            // Tambah filter poli jika ada
            if (!empty($this->filterPoliId)) {
                $exactQuery->where('a.poli_id', $this->filterPoliId);
            }

            $exactRow = $exactQuery->first();

            if ($exactRow) {
                $this->dispatchSelected($this->mapRowToPayload($exactRow));
                return;
            }
        }

        // ===== 2) search by dr_name / poli_desc partial =====
        $upperKeyword = mb_strtoupper($keyword);

        $query = DB::table('rsmst_doctors as a')
            ->leftJoin('rsmst_polis as b', 'a.poli_id', '=', 'b.poli_id')
            ->select('a.dr_id', 'a.dr_address', 'a.dr_phone', 'a.dr_name', 'a.poli_id', 'a.poli_price', 'a.ugd_price', 'a.basic_salary', 'a.contribution_status', 'a.active_status', 'a.rs_admin', 'a.kd_dr_bpjs', 'a.dr_uuid', 'a.dr_nik', 'a.poli_price_bpjs', 'a.ugd_price_bpjs', 'b.poli_desc', 'b.kd_poli_bpjs', 'b.poli_uuid', 'b.spesialis_status')
            ->where(function ($q) use ($keyword, $upperKeyword) {
                if (ctype_digit($keyword)) {
                    $q->orWhere('a.dr_id', 'like', "%{$keyword}%");
                }

                $q->orWhereRaw('UPPER(a.dr_name) LIKE ?', ["%{$upperKeyword}%"]);
                $q->orWhereRaw('UPPER(b.poli_desc) LIKE ?', ["%{$upperKeyword}%"]);

                // Tambah pencarian berdasarkan NIK
                if (!empty($keyword)) {
                    $q->orWhere('a.dr_nik', 'like', "%{$keyword}%");
                }
            })
            ->orderBy('a.dr_name');

        // Tambah filter poli jika ada
        if (!empty($this->filterPoliId)) {
            $query->where('a.poli_id', $this->filterPoliId);
        }

        $rows = $query->limit(50)->get();

        $this->options = $rows
            ->map(function ($row) {
                return $this->mapRowToOption($row);
            })
            ->toArray();

        $this->isOpen = count($this->options) > 0;
        $this->selectedIndex = 0;

        if ($this->isOpen) {
            $this->dispatch('lov-scroll', id: $this->getId(), index: $this->selectedIndex);
        }
    }

    protected function mapRowToPayload($row): array
    {
        return [
            'dr_id' => (string) $row->dr_id,
            'dr_address' => (string) ($row->dr_address ?? ''),
            'dr_phone' => (string) ($row->dr_phone ?? ''),
            'dr_name' => (string) ($row->dr_name ?? ''),
            'poli_id' => (string) ($row->poli_id ?? ''),
            'poli_desc' => (string) ($row->poli_desc ?? ''),
            'poli_price' => (string) ($row->poli_price ?? ''),
            'ugd_price' => (string) ($row->ugd_price ?? ''),
            'basic_salary' => (string) ($row->basic_salary ?? ''),
            'contribution_status' => (string) ($row->contribution_status ?? ''),
            'active_status' => (string) ($row->active_status ?? ''),
            'rs_admin' => (string) ($row->rs_admin ?? ''),
            'kd_dr_bpjs' => (string) ($row->kd_dr_bpjs ?? ''),
            'dr_uuid' => (string) ($row->dr_uuid ?? ''),
            'dr_nik' => (string) ($row->dr_nik ?? ''),
            'poli_price_bpjs' => (string) ($row->poli_price_bpjs ?? ''),
            'ugd_price_bpjs' => (string) ($row->ugd_price_bpjs ?? ''),
            'kd_poli_bpjs' => (string) ($row->kd_poli_bpjs ?? ''),
            'spesialis_status' => (string) ($row->spesialis_status ?? ''),
        ];
    }

    protected function mapRowToOption($row): array
    {
        $drId = (string) $row->dr_id;
        $drName = (string) ($row->dr_name ?? '');
        $poliDesc = (string) ($row->poli_desc ?? '');
        $activeStatus = (string) ($row->active_status ?? '');
        $contributionStatus = (string) ($row->contribution_status ?? '');
        $drNik = (string) ($row->dr_nik ?? '');
        $spesialisStatus = (string) ($row->spesialis_status ?? '');

        $statusLabel = $activeStatus === '1' ? 'Aktif' : 'Tidak Aktif';
        $statusClass = $activeStatus === '1' ? 'text-green-600' : 'text-red-600';

        $contributionLabel = $contributionStatus === '1' ? 'Kontribusi' : 'Non Kontribusi';
        $contributionClass = $contributionStatus === '1' ? 'text-blue-600' : 'text-gray-500';

        $spesialisLabel = $spesialisStatus === '1' ? 'Spesialis' : 'Umum';
        $spesialisClass = $spesialisStatus === '1' ? 'text-purple-600' : 'text-gray-500';

        $parts = array_filter([$drId ? "ID: {$drId}" : null, $drNik ? "NIK: {$drNik}" : null, $poliDesc ? "Poli: {$poliDesc}" : null]);

        return [
            // payload
            'dr_id' => $drId,
            'dr_address' => (string) ($row->dr_address ?? ''),
            'dr_phone' => (string) ($row->dr_phone ?? ''),
            'dr_name' => $drName,
            'poli_id' => (string) ($row->poli_id ?? ''),
            'poli_desc' => $poliDesc,
            'poli_price' => (string) ($row->poli_price ?? ''),
            'ugd_price' => (string) ($row->ugd_price ?? ''),
            'basic_salary' => (string) ($row->basic_salary ?? ''),
            'contribution_status' => $contributionStatus,
            'active_status' => $activeStatus,
            'rs_admin' => (string) ($row->rs_admin ?? ''),
            'kd_dr_bpjs' => (string) ($row->kd_dr_bpjs ?? ''),
            'dr_uuid' => (string) ($row->dr_uuid ?? ''),
            'dr_nik' => $drNik,
            'poli_price_bpjs' => (string) ($row->poli_price_bpjs ?? ''),
            'ugd_price_bpjs' => (string) ($row->ugd_price_bpjs ?? ''),
            'kd_poli_bpjs' => (string) ($row->kd_poli_bpjs ?? ''),
            'spesialis_status' => $spesialisStatus,

            // UI
            'label' => $drName ?: '-',
            'hint' => implode(' • ', $parts),
            'status' => $statusLabel,
            'status_class' => $statusClass,
            'contribution' => $contributionLabel,
            'contribution_class' => $contributionClass,
            'spesialis' => $spesialisLabel,
            'spesialis_class' => $spesialisClass,
        ];
    }

    public function clearSelected(): void
    {
        // Jika disabled, tidak bisa clear selected
        if ($this->disabled) {
            return;
        }

        $this->selected = null;
        $this->resetLov();

        // Dispatch event ke parent bahwa selection di-clear
        $this->dispatch('lov.cleared.' . $this->target, target: $this->target);
    }

    public function close(): void
    {
        $this->isOpen = false;
    }

    public function resetLov(): void
    {
        $this->reset(['search', 'options', 'isOpen', 'selectedIndex']);
    }

    public function selectNext(): void
    {
        if (!$this->isOpen || count($this->options) === 0) {
            return;
        }

        $this->selectedIndex = ($this->selectedIndex + 1) % count($this->options);
        $this->dispatch('lov-scroll', id: $this->getId(), index: $this->selectedIndex);
    }

    public function selectPrevious(): void
    {
        if (!$this->isOpen || count($this->options) === 0) {
            return;
        }

        $this->selectedIndex--;
        if ($this->selectedIndex < 0) {
            $this->selectedIndex = count($this->options) - 1;
        }

        $this->dispatch('lov-scroll', id: $this->getId(), index: $this->selectedIndex);
    }

    public function choose(int $index): void
    {
        if (!isset($this->options[$index])) {
            return;
        }

        $payload = [
            'dr_id' => $this->options[$index]['dr_id'] ?? '',
            'dr_address' => $this->options[$index]['dr_address'] ?? '',
            'dr_phone' => $this->options[$index]['dr_phone'] ?? '',
            'dr_name' => $this->options[$index]['dr_name'] ?? '',
            'poli_id' => $this->options[$index]['poli_id'] ?? '',
            'poli_desc' => $this->options[$index]['poli_desc'] ?? '',
            'poli_price' => $this->options[$index]['poli_price'] ?? '',
            'ugd_price' => $this->options[$index]['ugd_price'] ?? '',
            'basic_salary' => $this->options[$index]['basic_salary'] ?? '',
            'contribution_status' => $this->options[$index]['contribution_status'] ?? '',
            'active_status' => $this->options[$index]['active_status'] ?? '',
            'rs_admin' => $this->options[$index]['rs_admin'] ?? '',
            'kd_dr_bpjs' => $this->options[$index]['kd_dr_bpjs'] ?? '',
            'dr_uuid' => $this->options[$index]['dr_uuid'] ?? '',
            'dr_nik' => $this->options[$index]['dr_nik'] ?? '',
            'poli_price_bpjs' => $this->options[$index]['poli_price_bpjs'] ?? '',
            'ugd_price_bpjs' => $this->options[$index]['ugd_price_bpjs'] ?? '',
            'kd_poli_bpjs' => $this->options[$index]['kd_poli_bpjs'] ?? '',
            'spesialis_status' => $this->options[$index]['spesialis_status'] ?? '',
        ];

        $this->dispatchSelected($payload);
    }

    public function chooseHighlighted(): void
    {
        $this->choose($this->selectedIndex);
    }

    /* helpers */

    protected function closeAndResetList(): void
    {
        $this->options = [];
        $this->isOpen = false;
        $this->selectedIndex = 0;
    }

    protected function dispatchSelected(array $payload): void
    {
        // set selected -> UI berubah jadi nama + tombol ubah
        $this->selected = $payload;

        // bersihkan mode search
        $this->search = '';
        $this->options = [];
        $this->isOpen = false;
        $this->selectedIndex = 0;

        // emit ke parent
        $this->dispatch('lov.selected.' . $this->target, target: $this->target, payload: $payload);
    }

    public function updatedInitialDrId($value): void
    {
        // Reset state
        $this->selected = null;
        $this->search = '';
        $this->options = [];
        $this->isOpen = false;

        if (empty($value)) {
            return;
        }

        $row = DB::table('rsmst_doctors as a')->leftJoin('rsmst_polis as b', 'a.poli_id', '=', 'b.poli_id')->select('a.dr_id', 'a.dr_address', 'a.dr_phone', 'a.dr_name', 'a.poli_id', 'a.poli_price', 'a.ugd_price', 'a.basic_salary', 'a.contribution_status', 'a.active_status', 'a.rs_admin', 'a.kd_dr_bpjs', 'a.dr_uuid', 'a.dr_nik', 'a.poli_price_bpjs', 'a.ugd_price_bpjs', 'b.poli_desc', 'b.kd_poli_bpjs', 'b.poli_uuid', 'b.spesialis_status')->where('a.dr_id', $value)->first();

        if ($row) {
            $this->setSelectedFromRow($row);
        }
    }

    /**
     * Get display text for selected item
     */
    public function getSelectedDisplayProperty(): string
    {
        if (!$this->selected) {
            return '';
        }

        $parts = array_filter([$this->selected['dr_name'] ?? '', $this->selected['poli_desc'] ?? '']);

        return implode(' - ', $parts);
    }
};
?>

<x-lov.dropdown :id="$this->getId()" :isOpen="$isOpen" :selectedIndex="$selectedIndex" close="close">
    <x-input-label :value="$label" />

    <div class="relative mt-1">
        @if ($selected === null)
            {{-- Mode cari --}}
            @if (!$disabled)
                <x-text-input type="text" class="block w-full" :placeholder="$placeholder" wire:model.live.debounce.250ms="search"
                    wire:keydown.escape.prevent="resetLov" wire:keydown.arrow-down.prevent="selectNext"
                    wire:keydown.arrow-up.prevent="selectPrevious" wire:keydown.enter.prevent="chooseHighlighted"
                    autocomplete="off" />
            @else
                <x-text-input type="text" class="block w-full bg-gray-100 cursor-not-allowed dark:bg-gray-800"
                    :placeholder="$placeholder" disabled />
            @endif
        @else
            {{-- Mode selected --}}
            <div class="flex items-center gap-2">
                <div class="flex-1">
                    <x-text-input type="text" class="block w-full bg-gray-50 dark:bg-gray-800" :value="$this->selectedDisplay"
                        disabled />
                </div>

                @if (!$disabled)
                    <x-secondary-button type="button" wire:click="clearSelected" class="px-4 whitespace-nowrap">
                        Ubah
                    </x-secondary-button>
                @endif
            </div>

            {{-- Informasi tambahan dokter yang dipilih --}}
            {{-- @if ($showAdditionalInfo && ($selected['dr_phone'] || $selected['dr_nik'] || $selected['poli_price'] || $selected['basic_salary'] || $selected['spesialis_status'] !== ''))
                <div class="grid grid-cols-2 gap-2 p-2 mt-2 text-xs rounded-lg bg-gray-50 dark:bg-gray-800">
                    @if ($selected['dr_phone'])
                        <div><span class="font-medium">Telp:</span> {{ $selected['dr_phone'] }}</div>
                    @endif

                    @if ($selected['dr_nik'])
                        <div><span class="font-medium">NIK:</span> {{ $selected['dr_nik'] }}</div>
                    @endif

                    @if ($selected['spesialis_status'] !== '')
                        <div><span class="font-medium">Tipe:</span>
                            <span
                                class="{{ $selected['spesialis_status'] === '1' ? 'text-purple-600' : 'text-gray-500' }}">
                                {{ $selected['spesialis_status'] === '1' ? 'Spesialis' : 'Umum' }}
                            </span>
                        </div>
                    @endif

                    @if ($selected['contribution_status'] !== '')
                        <div><span class="font-medium">Kontribusi:</span>
                            <span
                                class="{{ $selected['contribution_status'] === '1' ? 'text-blue-600' : 'text-gray-500' }}">
                                {{ $selected['contribution_status'] === '1' ? 'Ya' : 'Tidak' }}
                            </span>
                        </div>
                    @endif
                </div>
            @endif --}}
        @endif

        {{-- dropdown hanya saat mode cari dan tidak disabled --}}
        @if ($isOpen && $selected === null && !$disabled)
            <div
                class="absolute z-50 w-full mt-2 overflow-hidden bg-white border border-gray-200 shadow-lg rounded-xl dark:bg-gray-900 dark:border-gray-700">
                <ul class="overflow-y-auto divide-y divide-gray-100 max-h-72 dark:divide-gray-800">
                    @foreach ($options as $index => $option)
                        <li wire:key="lov-dr-{{ $option['dr_id'] ?? $index }}-{{ $index }}"
                            x-ref="lovItem{{ $index }}">
                            <x-lov.item wire:click="choose({{ $index }})" :active="$index === $selectedIndex">
                                <div class="flex items-center justify-between">
                                    <div class="font-semibold text-gray-900 dark:text-gray-100">
                                        {{ $option['label'] ?? '-' }}
                                    </div>
                                    <div class="flex items-center gap-2">
                                        @if (!empty($option['spesialis']))
                                            <div class="text-xs {{ $option['spesialis_class'] ?? 'text-gray-500' }}">
                                                {{ $option['spesialis'] }}
                                            </div>
                                        @endif
                                        <div class="text-xs {{ $option['contribution_class'] ?? 'text-gray-500' }}">
                                            {{ $option['contribution'] ?? '' }}
                                        </div>
                                        <div class="text-xs {{ $option['status_class'] ?? 'text-gray-500' }}">
                                            {{ $option['status'] ?? '' }}
                                        </div>
                                    </div>
                                </div>

                                @if (!empty($option['hint']))
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $option['hint'] }}
                                    </div>
                                @endif

                                {{-- Tampilkan info harga jika ada --}}
                                @if ($showAdditionalInfo)
                                    <div class="flex flex-wrap gap-3 mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        @if (!empty($option['poli_price']) && $option['poli_price'] > 0)
                                            <span>Poli: Rp
                                                {{ number_format($option['poli_price'], 0, ',', '.') }}</span>
                                        @endif
                                        @if (!empty($option['ugd_price']) && $option['ugd_price'] > 0)
                                            <span>UGD: Rp {{ number_format($option['ugd_price'], 0, ',', '.') }}</span>
                                        @endif
                                        @if (!empty($option['poli_price_bpjs']) && $option['poli_price_bpjs'] > 0)
                                            <span>BPJS Poli: Rp
                                                {{ number_format($option['poli_price_bpjs'], 0, ',', '.') }}</span>
                                        @endif
                                    </div>
                                @endif
                            </x-lov.item>
                        </li>
                    @endforeach
                </ul>

                @if (mb_strlen(trim($search)) >= 2 && count($options) === 0)
                    <div class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                        Data dokter tidak ditemukan.
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-lov.dropdown>
