<?php
// resources/views/pages/master/master-dokter/master-dokter.blade.php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    /* ===============================
     | Filter & Pagination
     =============================== */
    public string $searchKeyword = '';
    public int $itemsPerPage = 10;

    /* -------------------- PANEL TARIF V&K PER KELAS (klik baris → panel kanan) -------------------- */
    public ?string $selectedDrId = null;
    public string $selectedDrName = '';

    /** Matrix kelas rawat × tarif V&K dokter terpilih: ['id', 'class_id', 'class_desc', 'visit_price', 'visit_price_bpjs', 'konsul_price', 'konsul_price_bpjs'] */
    public array $tarifKelas = [];

    /** Tarif dasar utk inline edit di tabel, key = dr_id: basic_salary/rs_admin/poli_price/ugd_price/poli_price_bpjs/ugd_price_bpjs */
    public array $hargaDasar = [];

    public function updatedSearchKeyword(): void
    {
        $this->resetPage();
    }

    public function updatedItemsPerPage(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword']);
        $this->itemsPerPage = 10;
        $this->resetPage();
    }

    /* ===============================
     | Child modal triggers
     =============================== */
    public function openCreate(): void
    {
        $this->dispatch('master.dokter.openCreate');
    }

    public function openEdit(string $drId): void
    {
        $this->dispatch('master.dokter.openEdit', drId: $drId);
    }

    public function requestDelete(string $drId): void
    {
        $this->dispatch('master.dokter.requestDelete', drId: $drId);
    }

    /* ===============================
     | PANEL TARIF V&K PER KELAS (rsmst_docvisits)
     | Klik baris dokter → panel kanan, pola master kamar.
     =============================== */
    public function selectDokter(string $drId, string $drName): void
    {
        $this->selectedDrId = $drId;
        $this->selectedDrName = $drName;
        $this->loadTarifKelas();
    }

    private function resetPanelTarif(): void
    {
        $this->selectedDrId = null;
        $this->selectedDrName = '';
        $this->tarifKelas = [];
    }

    private function loadTarifKelas(): void
    {
        if (!$this->selectedDrId) {
            $this->tarifKelas = [];
            return;
        }

        // Oracle treats '' as NULL — pakai whereNotNull saja.
        $kelas = DB::table('rsmst_class')->whereNotNull('class_desc')->orderBy('class_id')->select('class_id', 'class_desc')->get();

        $existing = DB::table('rsmst_docvisits')->where('dr_id', $this->selectedDrId)->select('id', 'class_id', 'visit_price', 'visit_price_bpjs', 'konsul_price', 'konsul_price_bpjs')->get()->keyBy('class_id');

        $this->tarifKelas = $kelas
            ->map(function ($k) use ($existing) {
                $row = $existing[$k->class_id] ?? null;
                return [
                    'id' => $row->id ?? null,
                    'class_id' => (int) $k->class_id,
                    'class_desc' => (string) $k->class_desc,
                    'visit_price' => (int) ($row->visit_price ?? 0),
                    'visit_price_bpjs' => (int) ($row->visit_price_bpjs ?? 0),
                    'konsul_price' => (int) ($row->konsul_price ?? 0),
                    'konsul_price_bpjs' => (int) ($row->konsul_price_bpjs ?? 0),
                ];
            })
            ->values()
            ->toArray();
    }

    /** Upsert satu baris tarif V&K kelas — baris semua-nol dihapus (pola lama modal V&K). */
    private function persistTarifKelasRow(int $idx): void
    {
        $row = $this->tarifKelas[$idx];
        $allZero = (int) $row['visit_price'] === 0 && (int) $row['visit_price_bpjs'] === 0 && (int) $row['konsul_price'] === 0 && (int) $row['konsul_price_bpjs'] === 0;

        $payloadKelas = [
            'visit_price' => (int) ($row['visit_price'] ?? 0),
            'visit_price_bpjs' => (int) ($row['visit_price_bpjs'] ?? 0),
            'konsul_price' => (int) ($row['konsul_price'] ?? 0),
            'konsul_price_bpjs' => (int) ($row['konsul_price_bpjs'] ?? 0),
        ];

        if ($row['id']) {
            if ($allZero) {
                DB::table('rsmst_docvisits')->where('id', $row['id'])->delete();
                $this->tarifKelas[$idx]['id'] = null;
            } else {
                DB::table('rsmst_docvisits')->where('id', $row['id'])->update($payloadKelas);
            }
        } elseif (!$allZero) {
            $nextId = (int) (DB::table('rsmst_docvisits')->max('id') ?? 0) + 1;
            DB::table('rsmst_docvisits')->insert([
                'id' => $nextId,
                'dr_id' => $this->selectedDrId,
                'class_id' => (int) $row['class_id'],
                ...$payloadKelas,
            ]);
            $this->tarifKelas[$idx]['id'] = $nextId;
        }
    }

    /** Auto-save saat blur/Enter di input panel (x-text-input-number sync via $wire.set). */
    public function updatedTarifKelas($value, string $key): void
    {
        $segments = explode('.', $key); // "{idx}.{field}"
        if (count($segments) !== 2) {
            return;
        }
        [$idx, $field] = $segments;
        $idx = (int) $idx;

        if (!$this->selectedDrId || !isset($this->tarifKelas[$idx]) || !in_array($field, ['visit_price', 'visit_price_bpjs', 'konsul_price', 'konsul_price_bpjs'], true)) {
            return;
        }

        if (!is_numeric($value) || (int) $value < 0) {
            $this->dispatch('toast', type: 'error', message: 'Tarif harus berupa angka.');
            return;
        }

        try {
            $this->persistTarifKelasRow($idx);
            $this->dispatch('toast', type: 'success', message: 'Tarif kelas tersimpan.');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal simpan: ' . $e->getMessage());
        }
    }

    public function copyTarifKelasDariBaris(int $idxSource): void
    {
        if (!isset($this->tarifKelas[$idxSource])) {
            return;
        }
        $src = $this->tarifKelas[$idxSource];
        foreach ($this->tarifKelas as $i => $row) {
            if ($i === $idxSource) {
                continue;
            }
            $this->tarifKelas[$i]['visit_price'] = $src['visit_price'];
            $this->tarifKelas[$i]['visit_price_bpjs'] = $src['visit_price_bpjs'];
            $this->tarifKelas[$i]['konsul_price'] = $src['konsul_price'];
            $this->tarifKelas[$i]['konsul_price_bpjs'] = $src['konsul_price_bpjs'];
        }

        try {
            DB::transaction(function () {
                foreach (array_keys($this->tarifKelas) as $i) {
                    $this->persistTarifKelasRow($i);
                }
            });
            $this->dispatch('toast', type: 'success', message: 'Tarif disalin ke semua kelas & tersimpan.');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal simpan: ' . $e->getMessage());
        }
    }

    public function saveTarifKelas(): void
    {
        if (!$this->selectedDrId) {
            return;
        }

        $this->validate(
            [
                'tarifKelas.*.visit_price' => ['nullable', 'numeric', 'min:0'],
                'tarifKelas.*.visit_price_bpjs' => ['nullable', 'numeric', 'min:0'],
                'tarifKelas.*.konsul_price' => ['nullable', 'numeric', 'min:0'],
                'tarifKelas.*.konsul_price_bpjs' => ['nullable', 'numeric', 'min:0'],
            ],
            [
                'tarifKelas.*.visit_price.numeric' => 'Tarif harus berupa angka.',
                'tarifKelas.*.visit_price_bpjs.numeric' => 'Tarif harus berupa angka.',
                'tarifKelas.*.konsul_price.numeric' => 'Tarif harus berupa angka.',
                'tarifKelas.*.konsul_price_bpjs.numeric' => 'Tarif harus berupa angka.',
            ],
        );

        try {
            DB::transaction(function () {
                foreach (array_keys($this->tarifKelas) as $i) {
                    $this->persistTarifKelasRow($i);
                }
            });

            $this->loadTarifKelas();
            $this->dispatch('toast', type: 'success', message: 'Tarif visit & konsul berhasil disimpan.');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal simpan: ' . $e->getMessage());
        }
    }

    /**
     * Inline edit tarif dasar di tabel — auto-save saat blur
     * (x-text-input-number sync via $wire.set, bukan .live).
     */
    public function updatedHargaDasar($value, string $key): void
    {
        $segments = explode('.', $key);
        $field = array_pop($segments);
        $drId = implode('.', $segments);

        if (!in_array($field, ['basic_salary', 'rs_admin', 'poli_price', 'ugd_price', 'poli_price_bpjs', 'ugd_price_bpjs'], true) || $drId === '') {
            return;
        }

        if (!is_numeric($value) || (int) $value < 0) {
            $this->dispatch('toast', type: 'error', message: 'Tarif harus berupa angka.');
            return;
        }

        DB::table('rsmst_doctors')->where('dr_id', $drId)->update([$field => (int) $value]);
        unset($this->rows);
        $this->dispatch('toast', type: 'success', message: 'Tarif dasar tersimpan.');
    }

    /* ===============================
     | Toggle Aktif / Non-aktif langsung dari table
     =============================== */
    public function toggleActive(string $drId): void
    {
        $current = (string) DB::table('rsmst_doctors')->where('dr_id', $drId)->value('active_status');
        $newValue = $current === '1' ? '0' : '1';

        DB::table('rsmst_doctors')
            ->where('dr_id', $drId)
            ->update(['active_status' => $newValue]);

        // Bust computed cache supaya table langsung refresh dengan nilai baru.
        unset($this->rows);

        $this->dispatch(
            'toast',
            type: 'success',
            message: $newValue === '1' ? 'Dokter diaktifkan.' : 'Dokter dinon-aktifkan.',
        );
    }

    /* ===============================
     | Refresh setelah child save
     =============================== */
    #[On('master.dokter.saved')]
    public function refreshAfterSaved(): void
    {
        // Bust computed cache + reset pagination supaya data terbaru tampil
        // (bukan cuma resetPage — kalau page sudah 1, computed tidak otomatis re-eval).
        unset($this->rows);
        $this->resetPage();

        // Sinkronkan panel tarif: dokter terpilih bisa saja di-rename / dihapus.
        if ($this->selectedDrId) {
            $row = DB::table('rsmst_doctors')->where('dr_id', $this->selectedDrId)->first();
            if ($row) {
                $this->selectedDrName = (string) ($row->dr_name ?? '');
                $this->loadTarifKelas();
            } else {
                $this->resetPanelTarif();
            }
        }
    }

    /* ===============================
     | Computed queries
     =============================== */
    #[Computed]
    public function baseQuery()
    {
        $searchKeyword = trim($this->searchKeyword);

        $queryBuilder = DB::table('rsmst_doctors as a')
            ->join('rsmst_polis as b', 'a.poli_id', '=', 'b.poli_id')
            ->select(
                'a.dr_id', 'a.dr_name', 'a.dr_phone', 'a.dr_address',
                'a.kd_dr_bpjs', 'a.dr_uuid', 'a.dr_nik',
                'a.basic_salary', 'a.rs_admin',
                'a.poli_price', 'a.ugd_price',
                'a.poli_price_bpjs', 'a.ugd_price_bpjs',
                'a.active_status',
                'a.poli_id', 'b.poli_desc', 'b.kd_poli_bpjs',
            )
            // Aktif (active_status='1') di atas → grouping per poli → alfabetik nama.
            // Strict check '1' konsisten dengan logic toggle/badge.
            ->orderByRaw("CASE WHEN a.active_status = '1' THEN 0 ELSE 1 END")
            ->orderBy('b.poli_desc', 'asc')
            ->orderBy('a.dr_name', 'asc');

        if ($searchKeyword !== '') {
            $upper = mb_strtoupper($searchKeyword);

            $queryBuilder->where(function ($q) use ($upper, $searchKeyword) {
                if (ctype_digit($searchKeyword)) {
                    $q->orWhere('a.dr_id', $searchKeyword);
                }

                $q->orWhereRaw('UPPER(a.dr_name) LIKE ?', ["%{$upper}%"])
                    ->orWhereRaw('UPPER(a.dr_phone) LIKE ?', ["%{$upper}%"])
                    ->orWhereRaw('UPPER(a.poli_id) LIKE ?', ["%{$upper}%"])
                    ->orWhereRaw('UPPER(b.poli_desc) LIKE ?', ["%{$upper}%"]);
            });
        }

        return $queryBuilder;
    }

    #[Computed]
    public function rows()
    {
        $rows = $this->baseQuery()->paginate($this->itemsPerPage);

        // Snapshot tarif dasar halaman ini utk inline edit (binding x-text-input-number).
        foreach ($rows->items() as $r) {
            $this->hargaDasar[$r->dr_id] = [
                'basic_salary' => (int) ($r->basic_salary ?? 0),
                'rs_admin' => (int) ($r->rs_admin ?? 0),
                'poli_price' => (int) ($r->poli_price ?? 0),
                'ugd_price' => (int) ($r->ugd_price ?? 0),
                'poli_price_bpjs' => (int) ($r->poli_price_bpjs ?? 0),
                'ugd_price_bpjs' => (int) ($r->ugd_price_bpjs ?? 0),
            ];
        }

        return $rows;
    }
};
?>

<div>
    <x-page-title
        title="Master Dokter"
        subtitle="Kelola data dokter untuk aplikasi" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-white dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR: sticky, search + per-page + tambah --}}
            <div
                class="sticky z-30 px-4 py-3 bg-white border-b border-gray-200 top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">

                    <div class="w-full lg:max-w-md">
                        <x-input-label for="searchKeyword" value="Cari Dokter" class="sr-only" />
                        <x-text-input id="searchKeyword" type="text" wire:model.live.debounce.300ms="searchKeyword"
                            placeholder="Cari nama / ID / poli / telepon..." class="block w-full" />
                    </div>

                    <div class="flex items-center justify-end gap-2">
                        <div class="w-28">
                            <x-select-input wire:model.live="itemsPerPage">
                                <option value="5">5</option>
                                <option value="7">7</option>
                                <option value="10">10</option>
                                <option value="15">15</option>
                                <option value="20">20</option>
                                <option value="100">100</option>
                            </x-select-input>
                        </div>

                        <x-primary-button type="button" wire:click="openCreate">
                            + Tambah Data Dokter Baru
                        </x-primary-button>
                        <x-toolbar-refresh-reset :label="null" />
                    </div>
                </div>
            </div>

            {{-- TABLE CARD (kiri) + PANEL TARIF V&K PER KELAS (kanan) — pola master kamar --}}
            <div class="mt-4 grid grid-cols-1 lg:grid-cols-12 gap-4 flex-1 min-h-0">
            <div
                class="lg:col-span-7 flex flex-col min-h-0 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                {{-- Scroll area — pola tampilan padat mirip master-pasien:
                     setiap cell multi-baris dengan label kecil di subtitle. --}}
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="min-w-full text-sm border-separate border-spacing-y-2">
                        <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800">
                            <tr class="text-left">
                                <th class="px-6 py-3.5 text-sm font-medium text-gray-500 dark:text-gray-400">Dokter &amp; Poli</th>
                                <th class="px-6 py-3.5 text-sm font-medium text-gray-500 dark:text-gray-400">Tarif &amp; Admin</th>
                                <th class="px-6 py-3.5 text-sm font-medium text-center text-gray-500 dark:text-gray-400">Aksi</th>
                            </tr>
                        </thead>

                        <tbody class="text-gray-500 dark:text-gray-400">
                            @forelse ($this->rows as $row)
                                @php $isSelected = $selectedDrId === (string) $row->dr_id; @endphp
                                {{-- Klik baris → panel tarif V&K per kelas di kanan --}}
                                <tr wire:key="dokter-row-{{ $row->dr_id }}"
                                    wire:click="selectDokter('{{ $row->dr_id }}', '{{ addslashes($row->dr_name) }}')"
                                    class="cursor-pointer transition rounded-2xl shadow-sm ring-1 ring-gray-200 dark:ring-gray-700 {{ $isSelected ? 'bg-gray-100 dark:bg-gray-700 hover:shadow-lg hover:bg-gray-200 dark:hover:bg-gray-600' : 'bg-white dark:bg-gray-900 hover:shadow-lg hover:bg-gray-50 dark:hover:bg-gray-800' }}">

                                    {{-- DOKTER & POLI: setiap data ada label-nya biar jelas --}}
                                    <td class="px-6 py-4 align-top">
                                        {{-- ID + toggle status sebaris (hemat kolom STATUS) --}}
                                        <div class="text-sm text-gray-600 dark:text-gray-300">ID Dokter</div>
                                        @php $isActive = (string) $row->active_status === '1'; @endphp
                                        <div class="flex items-center gap-3">
                                            <div class="text-base font-mono font-bold text-brand dark:text-brand-lime whitespace-nowrap">{{ $row->dr_id }}</div>
                                            <span wire:click.stop>
                                                <x-toggle wire:key="dokter-toggle-{{ $row->dr_id }}-{{ $isActive ? 1 : 0 }}"
                                                    :current="$isActive ? '1' : '0'" trueValue="1" falseValue="0"
                                                    wireClick="toggleActive('{{ $row->dr_id }}')">
                                                    {{ $isActive ? 'Aktif' : 'Nonaktif' }}
                                                </x-toggle>
                                            </span>
                                        </div>

                                        <div class="mt-1 text-sm text-gray-600 dark:text-gray-300">Nama Dokter</div>
                                        <div class="text-base font-bold text-gray-900 dark:text-white">{{ $row->dr_name }}</div>

                                        <div class="mt-1 text-sm text-gray-600 dark:text-gray-300">Poli</div>
                                        <div class="text-sm text-gray-900 dark:text-gray-100">
                                            {{ $row->poli_desc }}
                                            @if (!empty($row->kd_poli_bpjs))
                                                <span class="ml-1 text-sm font-normal text-gray-600 dark:text-gray-400">(Kode BPJS Poli: <span class="font-mono text-gray-900 dark:text-gray-100">{{ $row->kd_poli_bpjs }}</span>)</span>
                                            @endif
                                        </div>

                                        @if (!empty($row->dr_nik) || !empty($row->kd_dr_bpjs) || !empty($row->dr_uuid))
                                            <div class="mt-2 pt-1.5 border-t border-gray-200 dark:border-gray-700 space-y-1 text-sm">
                                                @if (!empty($row->dr_nik))
                                                    <div>
                                                        <span class="text-gray-600 dark:text-gray-300">NIK Dokter:</span>
                                                        <span class="ml-1 font-mono text-gray-900 dark:text-gray-100">{{ $row->dr_nik }}</span>
                                                    </div>
                                                @endif
                                                @if (!empty($row->kd_dr_bpjs))
                                                    <div>
                                                        <span class="text-gray-600 dark:text-gray-300">Kode Dokter BPJS:</span>
                                                        <span class="ml-1 font-mono text-gray-900 dark:text-gray-100">{{ $row->kd_dr_bpjs }}</span>
                                                    </div>
                                                @endif
                                                @if (!empty($row->dr_uuid))
                                                    <div>
                                                        <span class="text-gray-600 dark:text-gray-300">UUID Satusehat:</span>
                                                        <span class="ml-1 font-mono text-gray-900 dark:text-gray-100">{{ $row->dr_uuid }}</span>
                                                    </div>
                                                @endif
                                            </div>
                                        @endif

                                        {{-- Kontak (gabung di kolom ini, hemat lebar tabel) --}}
                                        <div class="mt-2 pt-1.5 border-t border-gray-200 dark:border-gray-700 space-y-1 text-sm">
                                            <div>
                                                <span class="text-gray-600 dark:text-gray-300">Telepon:</span>
                                                <span class="ml-1 font-mono text-gray-900 dark:text-gray-100">{{ $row->dr_phone ?? '-' }}</span>
                                            </div>
                                            <div class="max-w-xs">
                                                <span class="text-gray-600 dark:text-gray-300">Alamat:</span>
                                                <span class="ml-1 text-gray-900 dark:text-gray-100" title="{{ $row->dr_address }}">{{ $row->dr_address ?? '-' }}</span>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- TARIF & ADMIN: inline edit, auto-save saat blur.
                                         Track grid 7rem eksplisit — jangan andalkan w-* di komponen
                                         (kalah vs w-full bawaan & kolaps di tabel sempit). --}}
                                    <td class="px-6 py-4 align-top" wire:click.stop>
                                        {{-- Gaji & Administrasi RS — garis tipis antar baris --}}
                                        <div class="grid grid-cols-[auto_7rem] gap-x-3 gap-y-1.5 text-sm items-center">
                                            <span class="text-gray-600 dark:text-gray-300 whitespace-nowrap">Gaji Pokok</span>
                                            <x-text-input-number wire:model="hargaDasar.{{ $row->dr_id }}.basic_salary"
                                                wire:key="hd-gaji-{{ $row->dr_id }}" x-on:keydown.enter.prevent="$el.blur()" />

                                            <div class="col-span-2 border-t border-gray-100 dark:border-gray-800"></div>

                                            <span class="text-gray-600 dark:text-gray-300 whitespace-nowrap">Admin RS</span>
                                            <x-text-input-number wire:model="hargaDasar.{{ $row->dr_id }}.rs_admin"
                                                wire:key="hd-admin-{{ $row->dr_id }}" x-on:keydown.enter.prevent="$el.blur()" />
                                        </div>

                                        {{-- Tarif Poli & UGD — mini table bergaris; saat baris aktif header ikut tema hijau --}}
                                        <div class="mt-2 overflow-hidden border rounded-xl {{ $isSelected ? 'border-gray-300 dark:border-gray-600' : 'border-gray-200 dark:border-gray-700' }}">
                                            <table class="w-full text-sm">
                                                <thead class="text-xs uppercase {{ $isSelected ? 'bg-gray-200 text-gray-700 dark:bg-gray-600 dark:text-gray-100' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300' }}">
                                                    <tr>
                                                        <th class="px-2 py-1.5 text-left font-medium"></th>
                                                        <th class="px-2 py-1.5 w-28 text-center font-medium">Umum</th>
                                                        <th class="px-2 py-1.5 w-28 text-center font-medium">BPJS</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="text-gray-500 divide-y divide-gray-100 dark:divide-gray-800 dark:text-gray-400">
                                                    <tr>
                                                        <td class="px-2 py-1.5 whitespace-nowrap text-gray-600 dark:text-gray-300">Tarif Poli</td>
                                                        <td class="px-1.5 py-1.5 border-l border-gray-100 dark:border-gray-800">
                                                            <x-text-input-number wire:model="hargaDasar.{{ $row->dr_id }}.poli_price"
                                                                wire:key="hd-poli-{{ $row->dr_id }}" x-on:keydown.enter.prevent="$el.blur()" />
                                                        </td>
                                                        <td class="px-1.5 py-1.5 border-l border-gray-100 dark:border-gray-800">
                                                            <x-text-input-number wire:model="hargaDasar.{{ $row->dr_id }}.poli_price_bpjs"
                                                                wire:key="hd-polib-{{ $row->dr_id }}" x-on:keydown.enter.prevent="$el.blur()" />
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td class="px-2 py-1.5 whitespace-nowrap text-gray-600 dark:text-gray-300">Tarif UGD</td>
                                                        <td class="px-1.5 py-1.5 border-l border-gray-100 dark:border-gray-800">
                                                            <x-text-input-number wire:model="hargaDasar.{{ $row->dr_id }}.ugd_price"
                                                                wire:key="hd-ugd-{{ $row->dr_id }}" x-on:keydown.enter.prevent="$el.blur()" />
                                                        </td>
                                                        <td class="px-1.5 py-1.5 border-l border-gray-100 dark:border-gray-800">
                                                            <x-text-input-number wire:model="hargaDasar.{{ $row->dr_id }}.ugd_price_bpjs"
                                                                wire:key="hd-ugdb-{{ $row->dr_id }}" x-on:keydown.enter.prevent="$el.blur()" />
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </td>

                                    {{-- AKSI --}}
                                    <td class="px-6 py-4 align-top" wire:click.stop>
                                        <div class="flex flex-wrap justify-end gap-2">
                                            <x-secondary-button type="button"
                                                wire:click="openEdit('{{ $row->dr_id }}')" class="px-2 py-1 text-sm">
                                                Edit
                                            </x-secondary-button>

                                            <x-confirm-button variant="danger"
                                                :action="'requestDelete(\'' . $row->dr_id . '\')'"
                                                title="Hapus Dokter"
                                                :message="'Yakin hapus dokter ' . $row->dr_name . '?'"
                                                confirmText="Ya, hapus" cancelText="Batal"
                                                class="px-2 py-1 text-sm">
                                                Hapus
                                            </x-confirm-button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">
                                        Data belum ada.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Pagination sticky bawah card --}}
                <div
                    class="sticky bottom-0 z-10 px-4 py-3 bg-white border-t border-gray-200 rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>

            {{-- PANEL TARIF VISIT & KONSUL PER KELAS (kanan) --}}
            <div class="lg:col-span-5 flex flex-col min-h-0 bg-white border border-gray-200 shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-800/40 rounded-t-2xl">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Tarif Visit &amp; Konsul per Kelas</h3>
                    @if ($selectedDrId)
                        <div class="mt-1 flex items-center gap-2 text-xs">
                            <span class="px-1.5 py-0.5 rounded font-mono font-bold bg-gray-200/70 dark:bg-gray-700/60 text-gray-700 dark:text-gray-200">{{ $selectedDrId }}</span>
                            <span class="font-semibold text-brand-green dark:text-brand-lime">{{ $selectedDrName }}</span>
                        </div>
                    @endif
                </div>

                @if (!$selectedDrId)
                    <div class="flex flex-col items-center justify-center flex-1 py-12 text-gray-400 dark:text-gray-500">
                        <svg class="w-10 h-10 mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                        <p class="text-sm">Klik baris dokter di sebelah kiri untuk kelola tarif visit &amp; konsul per kelas.</p>
                    </div>
                @else
                    <div class="flex-1 min-h-0 overflow-y-auto p-4 space-y-3">
                        <div
                            class="flex items-center gap-2 px-3 py-2 text-xs text-blue-700 bg-blue-50 border border-blue-200 rounded-xl dark:bg-blue-900/20 dark:border-blue-700 dark:text-blue-300">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Tarif 0 = tidak berlaku. Set semua kolom = 0 untuk menghapus tarif kelas tsb.
                        </div>

                        <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-xl">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-800/50 text-xs text-gray-500 uppercase">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium" rowspan="2">Kelas</th>
                                        <th class="px-2 py-1.5 text-center font-medium border-l border-gray-200 dark:border-gray-700" colspan="2">Visit</th>
                                        <th class="px-2 py-1.5 text-center font-medium border-l border-gray-200 dark:border-gray-700" colspan="2">Konsul</th>
                                        <th class="px-2 py-2 w-10 text-center font-medium" rowspan="2" title="Salin tarif baris ke semua kelas lain">Copy</th>
                                    </tr>
                                    <tr>
                                        <th class="px-2 py-1.5 text-right font-medium border-l border-gray-200 dark:border-gray-700">Umum</th>
                                        <th class="px-2 py-1.5 text-right font-medium">BPJS</th>
                                        <th class="px-2 py-1.5 text-right font-medium border-l border-gray-200 dark:border-gray-700">Umum</th>
                                        <th class="px-2 py-1.5 text-right font-medium">BPJS</th>
                                    </tr>
                                </thead>
                                <tbody class="text-gray-500 divide-y divide-gray-100 dark:divide-gray-800 dark:text-gray-400">
                                    @forelse ($tarifKelas as $idx => $rowKelas)
                                        <tr wire:key="tarif-vk-{{ $selectedDrId }}-{{ $rowKelas['class_id'] }}">
                                            <td class="px-3 py-2 whitespace-nowrap">
                                                <div class="font-semibold text-gray-800 dark:text-gray-200">{{ $rowKelas['class_desc'] }}</div>
                                                <div class="text-xs text-gray-500 font-mono">ID: {{ $rowKelas['class_id'] }}</div>
                                            </td>
                                            <td class="px-1.5 py-2 border-l border-gray-100 dark:border-gray-800">
                                                <x-text-input-number wire:model="tarifKelas.{{ $idx }}.visit_price" x-on:keydown.enter.prevent="$el.blur()" />
                                            </td>
                                            <td class="px-1.5 py-2">
                                                <x-text-input-number wire:model="tarifKelas.{{ $idx }}.visit_price_bpjs" x-on:keydown.enter.prevent="$el.blur()" />
                                            </td>
                                            <td class="px-1.5 py-2 border-l border-gray-100 dark:border-gray-800">
                                                <x-text-input-number wire:model="tarifKelas.{{ $idx }}.konsul_price" x-on:keydown.enter.prevent="$el.blur()" />
                                            </td>
                                            <td class="px-1.5 py-2">
                                                <x-text-input-number wire:model="tarifKelas.{{ $idx }}.konsul_price_bpjs" x-on:keydown.enter.prevent="$el.blur()" />
                                            </td>
                                            <td class="px-1.5 py-2 text-center">
                                                <button type="button" wire:click="copyTarifKelasDariBaris({{ $idx }})"
                                                    wire:confirm="Salin tarif baris ini ke semua kelas lainnya?"
                                                    class="inline-flex items-center justify-center w-7 h-7 text-gray-500 dark:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                                                    title="Salin tarif baris ini ke semua kelas lain">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="px-3 py-6 text-center text-xs text-gray-400 italic">
                                                Data kelas belum tersedia.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="sticky bottom-0 z-10 px-4 py-3 bg-white border-t border-gray-200 rounded-b-2xl dark:bg-gray-900 dark:border-gray-700 flex justify-end">
                        <x-primary-button type="button" wire:click="saveTarifKelas"
                            wire:loading.attr="disabled" wire:target="saveTarifKelas">
                            <span wire:loading.remove wire:target="saveTarifKelas">Simpan Tarif</span>
                            <span wire:loading wire:target="saveTarifKelas"><x-loading class="w-4 h-4" /> Menyimpan...</span>
                        </x-primary-button>
                    </div>
                @endif
            </div>
            </div>

            {{-- Child actions component --}}
            <livewire:pages::master.master-dokter.master-dokter-actions wire:key="master-dokter-actions" />

        </div>
    </div>
</div>
