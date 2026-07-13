<?php
// resources/views/pages/transaksi/ri/emr-ri/rm-obat-dan-cairan-ri-actions.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\WithValidationToast\WithValidationToastTrait;

new class extends Component {
    use EmrRITrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public bool $isFormLocked = false;
    public ?int $riHdrNo = null;
    public array $dataDaftarRI = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-obat-cairan-ri'];

    // ── Form entry obat dan cairan ──
    public array $obatDanCairan = [
        'productId' => '',
        'namaObatAtauJenisCairan' => '',
        'jumlah' => '',
        'dosis' => '',
        'rute' => '',
        'keterangan' => '',
        'waktuPemberian' => '',
        'pemeriksa' => '',
    ];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas(['modal-obat-cairan-ri']);
    }

    /* ===============================
     | LOV SELECTED — PRODUCT
     =============================== */
    #[On('lov.selected.obat-dan-cairan-ri')]
    public function onProductSelected(?array $payload): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only.');
            return;
        }

        if (!$payload) {
            $this->obatDanCairan['productId'] = '';
            $this->obatDanCairan['namaObatAtauJenisCairan'] = '';
            return;
        }

        $this->obatDanCairan['productId'] = $payload['product_id'];
        $this->obatDanCairan['namaObatAtauJenisCairan'] = $payload['product_name'];
        $this->incrementVersion('modal-obat-cairan-ri');
    }

    /* ===============================
     | OPEN
     =============================== */
    #[On('open-rm-obat-dan-cairan-ri')]
    public function openObatDanCairan(int $riHdrNo): void
    {
        if (empty($riHdrNo)) {
            return;
        }

        $this->riHdrNo = $riHdrNo;
        $this->resetForm();
        $this->resetValidation();

        $data = $this->findDataRI($riHdrNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }

        $this->dataDaftarRI = $data;

        // Inisialisasi struktur jika belum ada
        $this->dataDaftarRI['observasi']['obatDanCairan'] ??= [
            'pemberianObatDanCairanTab' => 'Pemberian Obat Dan Cairan',
            'pemberianObatDanCairan' => [],
        ];
        $this->dataDaftarRI['observasi']['obatDanCairan']['pemberianObatDanCairan'] ??= [];

        // Generate ID untuk data lama yang belum ada ID
        $this->generateIds();

        $this->isFormLocked = $this->checkEmrRIStatus($riHdrNo);

        // Set waktu default
        $this->setWaktuPemberian();

        $this->incrementVersion('modal-obat-cairan-ri');
    }

    /* ===============================
     | VALIDATION
     =============================== */
    protected function rules(): array
    {
        return [
            'obatDanCairan.namaObatAtauJenisCairan' => 'required',
            'obatDanCairan.jumlah' => 'required|numeric',
            'obatDanCairan.dosis' => 'required',
            'obatDanCairan.rute' => 'required',
            'obatDanCairan.keterangan' => 'required',
            'obatDanCairan.waktuPemberian' => 'required|date_format:d/m/Y H:i:s',
            'obatDanCairan.pemeriksa' => 'required',
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'numeric' => ':attribute harus berupa angka.',
            'date_format' => ':attribute harus format dd/mm/yyyy HH:ii:ss.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'obatDanCairan.namaObatAtauJenisCairan' => 'Nama obat / jenis cairan',
            'obatDanCairan.jumlah' => 'Jumlah',
            'obatDanCairan.dosis' => 'Dosis',
            'obatDanCairan.rute' => 'Rute pemberian',
            'obatDanCairan.keterangan' => 'Keterangan',
            'obatDanCairan.waktuPemberian' => 'Waktu pemberian',
            'obatDanCairan.pemeriksa' => 'Pemeriksa',
        ];
    }

    /* ===============================
     | ADD OBAT DAN CAIRAN
     =============================== */
    #[On('save-rm-obat-dan-cairan-ri')]
    public function addObatDanCairan(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }

        $this->obatDanCairan['pemeriksa'] = auth()->user()->myuser_name ?? '';
        $this->validateWithToast();

        try {
            DB::transaction(function () {
                // 1. Lock row dulu
                $this->lockRIRow($this->riHdrNo);

                // 2. Baca data terkini setelah lock
                $data = $this->findDataRI($this->riHdrNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data RI tidak ditemukan, simpan dibatalkan.');
                }

                // 3. Inisialisasi struktur jika belum ada
                $data['observasi']['obatDanCairan'] ??= [
                    'pemberianObatDanCairanTab' => 'Pemberian Obat Dan Cairan',
                    'pemberianObatDanCairan' => [],
                ];
                $data['observasi']['obatDanCairan']['pemberianObatDanCairan'] ??= [];

                // 4. Cek duplikasi waktu
                $exists = collect($data['observasi']['obatDanCairan']['pemberianObatDanCairan'])->firstWhere('waktuPemberian', $this->obatDanCairan['waktuPemberian']);

                if ($exists) {
                    throw new \RuntimeException('Data pada waktu tersebut sudah ada.');
                }

                // 5. Tambah entry baru
                $data['observasi']['obatDanCairan']['pemberianObatDanCairan'][] = array_merge(['id' => uniqid('obat_')], $this->obatDanCairan);
                $data['observasi']['obatDanCairan']['pemberianObatDanCairan'] = array_values($data['observasi']['obatDanCairan']['pemberianObatDanCairan']);

                // 6. Simpan JSON
                $this->updateJsonRI($this->riHdrNo, $data);
                $this->dataDaftarRI = $data;

                // 7. Audit log
                $this->appendAdminLogRI((int) $this->riHdrNo, 'Tambah Obat & Cairan — ' . ($this->obatDanCairan['namaObatAtauJenisCairan'] ?? '-') . ' @ ' . ($this->obatDanCairan['waktuPemberian'] ?? '-'), 'MR');
            });

            // 7. Reset form + notify — di luar transaksi
            $this->resetObatForm();
            $this->setWaktuPemberian();
            $this->incrementVersion('modal-obat-cairan-ri');
            $this->dispatch('refresh-after-ri.saved', tab: 'observasi', subTab: 'obat-cairan');
            $this->dispatch('toast', type: 'success', message: 'Obat & Cairan berhasil ditambahkan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | REMOVE OBAT DAN CAIRAN
     =============================== */
    public function removeObatDanCairan(string $waktuPemberian): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menghapus.');
            return;
        }

        try {
            DB::transaction(function () use ($waktuPemberian) {
                // 1. Lock row dulu
                $this->lockRIRow($this->riHdrNo);

                // 2. Baca data terkini setelah lock
                $data = $this->findDataRI($this->riHdrNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data RI tidak ditemukan.');
                }

                if (!isset($data['observasi']['obatDanCairan']['pemberianObatDanCairan'])) {
                    throw new \RuntimeException('Data obat & cairan tidak ditemukan.');
                }

                // 3. Capture identitas entri sebelum dihapus (untuk log)
                $deletedRow = collect($data['observasi']['obatDanCairan']['pemberianObatDanCairan'])
                    ->firstWhere('waktuPemberian', $waktuPemberian);

                // 4. Hapus berdasarkan waktu pemberian
                $data['observasi']['obatDanCairan']['pemberianObatDanCairan'] = collect($data['observasi']['obatDanCairan']['pemberianObatDanCairan'])
                    ->reject(fn($row) => (string) ($row['waktuPemberian'] ?? '') === (string) $waktuPemberian)
                    ->values()
                    ->all();

                // 5. Simpan JSON
                $this->updateJsonRI($this->riHdrNo, $data);
                $this->dataDaftarRI = $data;

                // 6. Audit log
                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Obat & Cairan — ' . ($deletedRow['namaObatAtauJenisCairan'] ?? '-') . ' @ ' . $waktuPemberian, 'MR');
            });

            // 5. Notify — di luar transaksi
            $this->incrementVersion('modal-obat-cairan-ri');
            $this->dispatch('refresh-after-ri.saved', tab: 'observasi', subTab: 'obat-cairan');
            $this->dispatch('toast', type: 'success', message: 'Obat & Cairan berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    /* ===============================
     | COPY OBAT DAN CAIRAN → FORM
     | Salin data baris ke form entri baru (pola copyCPPT). Waktu di-set ulang ke
     | sekarang agar tidak bentrok duplikasi, pemeriksa dikosongkan (diisi saat Simpan).
     =============================== */
    public function copyObatDanCairan(string $id): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyalin.');
            return;
        }

        $row = collect($this->dataDaftarRI['observasi']['obatDanCairan']['pemberianObatDanCairan'] ?? [])
            ->first(fn($r) => (string) ($r['id'] ?? '') === (string) $id);

        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Data obat & cairan tidak ditemukan.');
            return;
        }

        $this->obatDanCairan = array_merge($this->obatDanCairan, [
            'productId' => $row['productId'] ?? '',
            'namaObatAtauJenisCairan' => $row['namaObatAtauJenisCairan'] ?? '',
            'jumlah' => $row['jumlah'] ?? '',
            'dosis' => $row['dosis'] ?? '',
            'rute' => $row['rute'] ?? '',
            'keterangan' => $row['keterangan'] ?? '',
            'pemeriksa' => '',
        ]);

        // Waktu baru (hindari bentrok duplikasi waktu)
        $this->setWaktuPemberian();
        $this->resetValidation();
        $this->incrementVersion('modal-obat-cairan-ri');
        $this->dispatch('toast', type: 'success', message: 'Data disalin ke form. Sesuaikan lalu klik Tambah.');
    }

    /* ===============================
     | SET WAKTU
     =============================== */
    public function setWaktuPemberian(): void
    {
        $this->obatDanCairan['waktuPemberian'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /* ===============================
     | HELPERS
     =============================== */
    private function resetObatForm(): void
    {
        $this->reset(['obatDanCairan']);
        $this->resetValidation();
    }

    private function generateIds(): void
    {
        if (isset($this->dataDaftarRI['observasi']['obatDanCairan']['pemberianObatDanCairan'])) {
            foreach ($this->dataDaftarRI['observasi']['obatDanCairan']['pemberianObatDanCairan'] as &$item) {
                $item['id'] ??= uniqid('obat_');
            }
        }
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->dataDaftarRI = [];
        $this->reset(['obatDanCairan']);
    }
};
?>

<div>
    <div class="flex flex-col w-full" wire:key="{{ $this->renderKey('modal-obat-cairan-ri', [$riHdrNo ?? 'new']) }}">
        <div
            class="w-full p-4 space-y-6 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

            @if ($isFormLocked)
                <div
                    class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    EMR terkunci — data tidak dapat diubah.
                </div>
            @endif


            {{-- FORM INPUT --}}
            @if (!$isFormLocked)
                <div class="p-4 border border-hairline rounded-2xl dark:border-gray-700 bg-surface-soft dark:bg-gray-800/40">

                    @if (empty($obatDanCairan['productId']))
                        {{-- Fase 1: pilih obat via LOV --}}
                        <livewire:lov.product.lov-product target="obat-dan-cairan-ri" label="Nama Obat / Jenis Cairan"
                            placeholder="Ketik nama/kode obat atau cairan..."
                            wire:key="lov-obat-cairan-ri-{{ $riHdrNo }}-{{ $renderVersions['modal-obat-cairan-ri'] ?? 0 }}" />
                    @else
                        {{-- Fase 2: form isian 1 baris.
                             Enter-chain (pola e-resep): waktu → jumlah → dosis → rute → keterangan → simpan. --}}
                        <div class="flex flex-wrap items-end gap-2">
                            {{-- Waktu Pemberian (DEPAN) — auto-focus, Enter → jumlah --}}
                            <div class="w-64">
                                <x-input-label value="Waktu *" class="mb-1" />
                                <div class="flex items-center gap-1">
                                    <x-text-input wire:model="obatDanCairan.waktuPemberian"
                                        placeholder="dd/mm/yyyy HH:ii:ss" class="flex-1" x-ref="ocWaktu"
                                        x-init="$nextTick(() => $el.focus())"
                                        x-on:keydown.enter.prevent="$refs.ocJumlah.focus()" />
                                    <x-now-button wire:click.prevent="setWaktuPemberian" />
                                </div>
                                <x-input-error :messages="$errors->get('obatDanCairan.waktuPemberian')" class="mt-1" />
                            </div>

                            {{-- Nama Obat (disabled) + tombol Ganti --}}
                            <div class="flex-1 min-w-[200px]">
                                <x-input-label value="Nama Obat / Jenis Cairan *" class="mb-1" />
                                <div class="flex items-center gap-2">
                                    <x-text-input wire:model="obatDanCairan.namaObatAtauJenisCairan" disabled
                                        class="flex-1 text-sm" />
                                    <x-secondary-button type="button" wire:click="$set('obatDanCairan.productId', '')"
                                        class="text-xs whitespace-nowrap shrink-0">
                                        Ganti
                                    </x-secondary-button>
                                </div>
                                <x-input-error :messages="$errors->get('obatDanCairan.namaObatAtauJenisCairan')" class="mt-1" />
                            </div>

                            {{-- Jumlah --}}
                            <div class="w-24">
                                <x-input-label value="Jumlah *" class="mb-1" />
                                <x-text-input wire:model="obatDanCairan.jumlah" placeholder="Jumlah" class="w-full"
                                    x-ref="ocJumlah" x-on:keydown.enter.prevent="$refs.ocDosis.focus()" />
                                <x-input-error :messages="$errors->get('obatDanCairan.jumlah')" class="mt-1" />
                            </div>

                            {{-- Dosis --}}
                            <div class="w-24">
                                <x-input-label value="Dosis *" class="mb-1" />
                                <x-text-input wire:model="obatDanCairan.dosis" placeholder="Dosis" class="w-full"
                                    x-ref="ocDosis" x-on:keydown.enter.prevent="$refs.ocRute.focus()" />
                                <x-input-error :messages="$errors->get('obatDanCairan.dosis')" class="mt-1" />
                            </div>

                            {{-- Rute --}}
                            <div class="w-28">
                                <x-input-label value="Rute *" class="mb-1" />
                                <x-text-input wire:model="obatDanCairan.rute" placeholder="IV / PO / SC ..."
                                    class="w-full" x-ref="ocRute"
                                    x-on:keydown.enter.prevent="$refs.ocKeterangan.focus()" />
                                <x-input-error :messages="$errors->get('obatDanCairan.rute')" class="mt-1" />
                            </div>

                            {{-- Keterangan — field terakhir, Enter = simpan (pola #3: blur dulu) --}}
                            <div class="flex-1 min-w-[180px]">
                                <x-input-label value="Keterangan *" class="mb-1" />
                                <x-text-input wire:model="obatDanCairan.keterangan"
                                    placeholder="Keterangan pemberian..." class="w-full" x-ref="ocKeterangan"
                                    x-on:keydown.enter.prevent="$el.blur(); $wire.addObatDanCairan()" />
                                <x-input-error :messages="$errors->get('obatDanCairan.keterangan')" class="mt-1" />
                            </div>

                        </div>
                    @endif
                </div>
            @endif

            {{-- TABEL DATA --}}
            @php
                $daftarObat = $dataDaftarRI['observasi']['obatDanCairan']['pemberianObatDanCairan'] ?? [];
                $sortedObat = collect($daftarObat)
                    ->sortByDesc(
                        fn($item) => Carbon::createFromFormat(
                            'd/m/Y H:i:s',
                            $item['waktuPemberian'] ?? '01/01/2000 00:00:00',
                        )->timestamp,
                    )
                    ->values();
            @endphp

            <div
                class="overflow-hidden bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-center justify-between px-4 py-3 border-b border-hairline dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-body dark:text-gray-300">Daftar Pemberian Obat &amp;
                        Cairan</h3>
                    <x-badge variant="gray">{{ count($daftarObat) }} item</x-badge>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead
                            class="text-xs font-semibold text-muted uppercase bg-surface-soft dark:bg-gray-800/50 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-3">No</th>
                                <th class="px-4 py-3">Waktu / Pemeriksa</th>
                                <th class="px-4 py-3">Nama Obat / Cairan</th>
                                <th class="px-4 py-3 text-center">Jumlah</th>
                                <th class="px-4 py-3">Dosis</th>
                                <th class="px-4 py-3">Rute</th>
                                <th class="px-4 py-3">Keterangan</th>
                                @if (!$isFormLocked)
                                    <th class="px-4 py-3 text-center w-28">Aksi</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-hairline-soft dark:divide-gray-800">
                            @forelse ($sortedObat as $item)
                                <tr wire:key="obat-{{ $item['id'] ?? $item['waktuPemberian'] }}"
                                    class="hover:bg-surface-soft dark:hover:bg-gray-800/40 transition">
                                    <td class="px-4 py-3 text-muted dark:text-gray-400">{{ $loop->iteration }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="text-xs font-medium text-ink dark:text-gray-100">
                                            {{ $item['waktuPemberian'] ?? '-' }}</div>
                                        <div class="text-xs text-muted-soft">{{ $item['pemeriksa'] ?? '-' }}</div>
                                    </td>
                                    <td
                                        class="px-4 py-3 font-medium text-ink dark:text-gray-200 whitespace-nowrap">
                                        {{ $item['namaObatAtauJenisCairan'] ?? '-' }}</td>
                                    <td class="px-4 py-3 text-center text-body dark:text-gray-300">
                                        {{ $item['jumlah'] ?? '-' }}</td>
                                    <td class="px-4 py-3 text-body dark:text-gray-300 whitespace-nowrap">
                                        {{ $item['dosis'] ?? '-' }}</td>
                                    <td class="px-4 py-3 text-body dark:text-gray-300 whitespace-nowrap">
                                        {{ $item['rute'] ?? '-' }}</td>
                                    <td class="px-4 py-3 text-body dark:text-gray-300">
                                        {{ $item['keterangan'] ?? '-' }}</td>
                                    @if (!$isFormLocked)
                                        <td class="px-4 py-3">
                                            <div class="flex items-center justify-center gap-2">
                                                <x-outline-button type="button"
                                                    wire:click.prevent="copyObatDanCairan('{{ $item['id'] ?? '' }}')"
                                                    wire:loading.attr="disabled"
                                                    class="!text-blue-600 !bg-blue-50 !border-blue-200 hover:!bg-blue-100 hover:!text-blue-700 hover:!border-blue-300 dark:!text-blue-400 dark:!bg-blue-900/20 dark:!border-blue-800/30 dark:hover:!bg-blue-900/30 dark:hover:!text-blue-300"
                                                    title="Salin ke form">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                                    </svg>
                                                </x-outline-button>
                                                <x-outline-button type="button"
                                                    wire:click.prevent="removeObatDanCairan('{{ $item['waktuPemberian'] }}')"
                                                    wire:confirm="Hapus data obat & cairan ini?"
                                                    wire:loading.attr="disabled"
                                                    class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300"
                                                    title="Hapus">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </x-outline-button>
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $isFormLocked ? 7 : 8 }}"
                                        class="px-4 py-10 text-sm text-center text-muted-soft dark:text-gray-600">
                                        <svg class="w-8 h-8 mx-auto mb-2 opacity-40" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        Belum ada data pemberian obat &amp; cairan
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>
