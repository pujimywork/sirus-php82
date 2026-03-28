<?php
// resources/views/pages/transaksi/ugd/emr-ugd/rm-observasi-ugd-actions.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use EmrUGDTrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $dataDaftarUGD = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-observasi-ugd'];

    // ── Form entry observasi lanjutan ──
    public array $observasiLanjutan = [
        'cairan' => '',
        'tetesan' => '',
        'sistolik' => '',
        'distolik' => '',
        'frekuensiNafas' => '',
        'frekuensiNadi' => '',
        'suhu' => '',
        'spo2' => '',
        'gda' => '',
        'gcs' => '',
        'waktuPemeriksaan' => '',
        'pemeriksa' => '',
    ];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas(['modal-observasi-ugd']);
    }

    /* ===============================
     | OPEN
     =============================== */
    #[On('open-rm-observasi-ugd')]
    public function openObservasi(int $rjNo): void
    {
        if (empty($rjNo)) {
            return;
        }

        $this->rjNo = $rjNo;
        $this->resetForm();
        $this->resetValidation();

        $data = $this->findDataUGD($rjNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.');
            return;
        }

        $this->dataDaftarUGD = $data;

        // Inisialisasi struktur jika belum ada
        $this->dataDaftarUGD['observasi']['observasiLanjutan'] ??= [
            'tandaVitalTab' => 'Observasi Lanjutan',
            'tandaVital' => [],
        ];
        $this->dataDaftarUGD['observasi']['observasiLanjutan']['tandaVital'] ??= [];

        // Generate ID untuk data lama yang belum ada ID
        $this->generateIds('tandaVital', 'observasi_');

        $this->isFormLocked = $this->checkEmrUGDStatus($rjNo);

        // Set waktu default
        $this->setWaktuPemeriksaan();

        $this->incrementVersion('modal-observasi-ugd');
    }

    /* ===============================
     | VALIDATION
     =============================== */
    protected function rules(): array
    {
        return [
            'observasiLanjutan.sistolik' => 'required|numeric',
            'observasiLanjutan.distolik' => 'required|numeric',
            'observasiLanjutan.frekuensiNafas' => 'required|numeric',
            'observasiLanjutan.frekuensiNadi' => 'required|numeric',
            'observasiLanjutan.suhu' => 'required|numeric',
            'observasiLanjutan.spo2' => 'required|numeric',
            'observasiLanjutan.waktuPemeriksaan' => 'required|date_format:d/m/Y H:i:s',
            'observasiLanjutan.pemeriksa' => 'required',
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
            'observasiLanjutan.sistolik' => 'TD Sistolik',
            'observasiLanjutan.distolik' => 'TD Diastolik',
            'observasiLanjutan.frekuensiNafas' => 'Frekuensi Nafas',
            'observasiLanjutan.frekuensiNadi' => 'Frekuensi Nadi',
            'observasiLanjutan.suhu' => 'Suhu',
            'observasiLanjutan.spo2' => 'SpO₂',
            'observasiLanjutan.waktuPemeriksaan' => 'Waktu Pemeriksaan',
            'observasiLanjutan.pemeriksa' => 'Pemeriksa',
        ];
    }

    /* ===============================
     | ADD OBSERVASI LANJUTAN
     =============================== */
    #[On('save-rm-observasi-ugd')]
    public function addObservasiLanjutan(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }

        $this->observasiLanjutan['pemeriksa'] = auth()->user()->myuser_name ?? '';
        $this->validate();

        try {
            DB::transaction(function () {
                // 1. Lock row dulu
                $this->lockUGDRow($this->rjNo);

                // 2. Baca data terkini setelah lock
                $data = $this->findDataUGD($this->rjNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data UGD tidak ditemukan, simpan dibatalkan.');
                }

                // 3. Inisialisasi struktur jika belum ada
                $data['observasi']['observasiLanjutan'] ??= [
                    'tandaVitalTab' => 'Observasi Lanjutan',
                    'tandaVital' => [],
                ];
                $data['observasi']['observasiLanjutan']['tandaVital'] ??= [];

                // 4. Cek duplikasi waktu
                $exists = collect($data['observasi']['observasiLanjutan']['tandaVital'])->firstWhere('waktuPemeriksaan', $this->observasiLanjutan['waktuPemeriksaan']);

                if ($exists) {
                    throw new \RuntimeException('Data pada waktu tersebut sudah ada.');
                }

                // 5. Tambah entry baru
                $data['observasi']['observasiLanjutan']['tandaVital'][] = array_merge(['id' => uniqid('observasi_')], $this->observasiLanjutan);
                $data['observasi']['observasiLanjutan']['tandaVital'] = array_values($data['observasi']['observasiLanjutan']['tandaVital']);

                // 6. Log
                $data['observasi']['observasiLanjutan']['tandaVitalLog'] = [
                    'userLogDesc' => 'Form Entry Observasi Lanjutan',
                    'userLog' => auth()->user()->myuser_name ?? '',
                    'userLogDate' => Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'),
                ];

                // 7. Simpan JSON
                $this->updateJsonUGD($this->rjNo, $data);
                $this->dataDaftarUGD = $data;
            });

            // 8. Reset form + notify — di luar transaksi
            $this->resetObservasiForm();
            $this->setWaktuPemeriksaan();
            $this->incrementVersion('modal-observasi-ugd');
            $this->dispatch('toast', type: 'success', message: 'Observasi Lanjutan berhasil ditambahkan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | REMOVE OBSERVASI LANJUTAN
     =============================== */
    public function removeObservasiLanjutan(string $waktuPemeriksaan): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menghapus.');
            return;
        }

        try {
            DB::transaction(function () use ($waktuPemeriksaan) {
                // 1. Lock row dulu
                $this->lockUGDRow($this->rjNo);

                // 2. Baca data terkini setelah lock
                $data = $this->findDataUGD($this->rjNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data UGD tidak ditemukan.');
                }

                if (!isset($data['observasi']['observasiLanjutan']['tandaVital'])) {
                    throw new \RuntimeException('Data observasi tidak ditemukan.');
                }

                // 3. Hapus berdasarkan waktu
                $data['observasi']['observasiLanjutan']['tandaVital'] = collect($data['observasi']['observasiLanjutan']['tandaVital'])
                    ->reject(fn($row) => (string) ($row['waktuPemeriksaan'] ?? '') === (string) $waktuPemeriksaan)
                    ->values()
                    ->all();

                // 4. Update log
                $data['observasi']['observasiLanjutan']['tandaVitalLog'] = [
                    'userLogDesc' => 'Hapus Observasi Lanjutan',
                    'userLog' => auth()->user()->myuser_name ?? '',
                    'userLogDate' => Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'),
                ];

                // 5. Simpan JSON
                $this->updateJsonUGD($this->rjNo, $data);
                $this->dataDaftarUGD = $data;
            });

            // 6. Notify — di luar transaksi
            $this->incrementVersion('modal-observasi-ugd');
            $this->dispatch('toast', type: 'success', message: 'Observasi Lanjutan berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    /* ===============================
     | SET WAKTU
     =============================== */
    public function setWaktuPemeriksaan(): void
    {
        $this->observasiLanjutan['waktuPemeriksaan'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /* ===============================
     | HELPERS
     =============================== */
    private function resetObservasiForm(): void
    {
        $this->reset(['observasiLanjutan']);
        $this->resetValidation();
    }

    private function generateIds(string $key, string $prefix): void
    {
        if (isset($this->dataDaftarUGD['observasi']['observasiLanjutan'][$key])) {
            foreach ($this->dataDaftarUGD['observasi']['observasiLanjutan'][$key] as &$item) {
                $item['id'] ??= uniqid($prefix);
            }
        }
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->dataDaftarUGD = [];
        $this->reset(['observasiLanjutan']);
    }
};
?>

<div>
    <div class="flex flex-col w-full" wire:key="{{ $this->renderKey('modal-observasi-ugd', [$rjNo ?? 'new']) }}">
        <div
            class="w-full p-4 space-y-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

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

            @if (isset($dataDaftarUGD['observasi']['observasiLanjutan']))

                {{-- FORM INPUT --}}
                @if (!$isFormLocked)
                    <div
                        class="grid grid-cols-12 gap-3 p-4 border border-gray-200 rounded-2xl dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40">

                        {{-- Cairan + Tetesan --}}
                        <div class="col-span-12 md:col-span-6">
                            <x-input-label value="Cairan" class="mb-1" />
                            <div class="relative">
                                <x-text-input wire:model="observasiLanjutan.cairan" placeholder="Cairan"
                                    class="w-full pr-8" />
                                <span
                                    class="absolute inset-y-0 right-2 flex items-center text-xs text-gray-400 pointer-events-none">ml</span>
                            </div>
                            <x-input-error :messages="$errors->get('observasiLanjutan.cairan')" class="mt-1" />
                        </div>

                        <div class="col-span-12 md:col-span-6">
                            <x-input-label value="Tetesan" class="mb-1" />
                            <div class="relative">
                                <x-text-input wire:model="observasiLanjutan.tetesan" placeholder="Tetesan/menit"
                                    class="w-full pr-16" />
                                <span
                                    class="absolute inset-y-0 right-2 flex items-center text-xs text-gray-400 pointer-events-none">gtt/menit</span>
                            </div>
                        </div>

                        {{-- Tanda Vital --}}
                        <div class="col-span-6 md:col-span-2">
                            <x-input-label value="TD Sistolik *" class="mb-1" />
                            <div class="relative">
                                <x-text-input wire:model="observasiLanjutan.sistolik" placeholder="Sistolik"
                                    class="w-full pr-12" />
                                <span
                                    class="absolute inset-y-0 right-2 flex items-center text-xs text-gray-400 pointer-events-none">mmHg</span>
                            </div>
                            <x-input-error :messages="$errors->get('observasiLanjutan.sistolik')" class="mt-1" />
                        </div>

                        <div class="col-span-6 md:col-span-2">
                            <x-input-label value="TD Diastolik *" class="mb-1" />
                            <div class="relative">
                                <x-text-input wire:model="observasiLanjutan.distolik" placeholder="Diastolik"
                                    class="w-full pr-12" />
                                <span
                                    class="absolute inset-y-0 right-2 flex items-center text-xs text-gray-400 pointer-events-none">mmHg</span>
                            </div>
                            <x-input-error :messages="$errors->get('observasiLanjutan.distolik')" class="mt-1" />
                        </div>

                        <div class="col-span-6 md:col-span-2">
                            <x-input-label value="Frekuensi Nafas *" class="mb-1" />
                            <div class="relative">
                                <x-text-input wire:model="observasiLanjutan.frekuensiNafas" placeholder="Nafas"
                                    class="w-full pr-14" />
                                <span
                                    class="absolute inset-y-0 right-2 flex items-center text-xs text-gray-400 pointer-events-none">x/mnt</span>
                            </div>
                            <x-input-error :messages="$errors->get('observasiLanjutan.frekuensiNafas')" class="mt-1" />
                        </div>

                        <div class="col-span-6 md:col-span-2">
                            <x-input-label value="Frekuensi Nadi *" class="mb-1" />
                            <div class="relative">
                                <x-text-input wire:model="observasiLanjutan.frekuensiNadi" placeholder="Nadi"
                                    class="w-full pr-14" />
                                <span
                                    class="absolute inset-y-0 right-2 flex items-center text-xs text-gray-400 pointer-events-none">x/mnt</span>
                            </div>
                            <x-input-error :messages="$errors->get('observasiLanjutan.frekuensiNadi')" class="mt-1" />
                        </div>

                        <div class="col-span-6 md:col-span-2">
                            <x-input-label value="Suhu *" class="mb-1" />
                            <div class="relative">
                                <x-text-input wire:model="observasiLanjutan.suhu" placeholder="Suhu"
                                    class="w-full pr-6" />
                                <span
                                    class="absolute inset-y-0 right-2 flex items-center text-xs text-gray-400 pointer-events-none">°C</span>
                            </div>
                            <x-input-error :messages="$errors->get('observasiLanjutan.suhu')" class="mt-1" />
                        </div>

                        <div class="col-span-6 md:col-span-2">
                            <x-input-label value="SpO₂ *" class="mb-1" />
                            <div class="relative">
                                <x-text-input wire:model="observasiLanjutan.spo2" placeholder="SpO₂"
                                    class="w-full pr-5" />
                                <span
                                    class="absolute inset-y-0 right-2 flex items-center text-xs text-gray-400 pointer-events-none">%</span>
                            </div>
                            <x-input-error :messages="$errors->get('observasiLanjutan.spo2')" class="mt-1" />
                        </div>

                        <div class="col-span-6 md:col-span-3">
                            <x-input-label value="GDA" class="mb-1" />
                            <div class="relative">
                                <x-text-input wire:model="observasiLanjutan.gda" placeholder="Gula Darah Acak"
                                    class="w-full pr-12" />
                                <span
                                    class="absolute inset-y-0 right-2 flex items-center text-xs text-gray-400 pointer-events-none">mg/dL</span>
                            </div>
                        </div>

                        <div class="col-span-6 md:col-span-3">
                            <x-input-label value="GCS" class="mb-1" />
                            <x-text-input wire:model="observasiLanjutan.gcs" placeholder="E V M" class="w-full" />
                        </div>

                        {{-- Waktu + Aksi --}}
                        <div class="col-span-12 md:col-span-6">
                            <x-input-label value="Waktu Pemeriksaan *" class="mb-1" />
                            <div class="flex items-center gap-2">
                                <x-text-input wire:model="observasiLanjutan.waktuPemeriksaan"
                                    placeholder="dd/mm/yyyy hh:mm:ss" class="grow" />
                                <x-secondary-button wire:click.prevent="setWaktuPemeriksaan" type="button"
                                    class="text-xs whitespace-nowrap">
                                    Set sekarang
                                </x-secondary-button>
                            </div>
                            <x-input-error :messages="$errors->get('observasiLanjutan.waktuPemeriksaan')" class="mt-1" />
                        </div>

                        <div class="col-span-12 md:col-span-6 flex items-end">
                            <x-primary-button wire:click.prevent="addObservasiLanjutan" wire:loading.attr="disabled"
                                wire:target="addObservasiLanjutan" class="gap-2">
                                <span wire:loading.remove wire:target="addObservasiLanjutan">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 4v16m8-8H4" />
                                    </svg>
                                </span>
                                <span wire:loading wire:target="addObservasiLanjutan"><x-loading
                                        class="w-4 h-4" /></span>
                                Tambah Observasi
                            </x-primary-button>
                        </div>

                    </div>
                @endif

                {{-- TABEL DATA --}}
                @php
                    $tandaVitalData = $dataDaftarUGD['observasi']['observasiLanjutan']['tandaVital'] ?? [];
                    $sortedTtv = collect($tandaVitalData)
                        ->sortByDesc(
                            fn($item) => \Carbon\Carbon::createFromFormat(
                                'd/m/Y H:i:s',
                                $item['waktuPemeriksaan'] ?? '01/01/2000 00:00:00',
                            )->timestamp,
                        )
                        ->values();
                @endphp

                <div
                    class="overflow-hidden bg-white border border-gray-200 rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                    <div
                        class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Daftar Observasi Lanjutan
                        </h3>
                        <x-badge variant="gray">{{ count($tandaVitalData) }} item</x-badge>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead
                                class="text-xs font-semibold text-gray-500 uppercase bg-gray-50 dark:bg-gray-800/50 dark:text-gray-400">
                                <tr class="text-center">
                                    <th class="px-4 py-3">No</th>
                                    <th class="px-4 py-3">Waktu / Pemeriksa</th>
                                    <th class="px-4 py-3">TD</th>
                                    <th class="px-4 py-3">Nadi</th>
                                    <th class="px-4 py-3">Nafas</th>
                                    <th class="px-4 py-3">Suhu</th>
                                    <th class="px-4 py-3">SpO₂</th>
                                    <th class="px-4 py-3">GDA</th>
                                    <th class="px-4 py-3">GCS</th>
                                    <th class="px-4 py-3">Cairan / Tetesan</th>
                                    @if (!$isFormLocked)
                                        <th class="px-4 py-3 text-center">Hapus</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse ($sortedTtv as $obs)
                                    <tr wire:key="ttv-{{ $obs['id'] ?? $obs['waktuPemeriksaan'] }}"
                                        class="text-center hover:bg-gray-50 dark:hover:bg-gray-800/40 transition">
                                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $loop->iteration }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <div class="font-medium text-gray-900 dark:text-gray-100 text-xs">
                                                {{ $obs['waktuPemeriksaan'] ?? '-' }}</div>
                                            <div class="text-xs text-gray-400">{{ $obs['pemeriksa'] ?? '-' }}</div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-gray-700 dark:text-gray-300">
                                            {{ $obs['sistolik'] ?? '-' }}/{{ $obs['distolik'] ?? '-' }} <span
                                                class="text-xs">mmHg</span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-gray-700 dark:text-gray-300">
                                            {{ $obs['frekuensiNadi'] ?? '-' }} <span class="text-xs">x/mnt</span></td>
                                        <td class="px-4 py-3 whitespace-nowrap text-gray-700 dark:text-gray-300">
                                            {{ $obs['frekuensiNafas'] ?? '-' }} <span class="text-xs">x/mnt</span></td>
                                        <td class="px-4 py-3 whitespace-nowrap text-gray-700 dark:text-gray-300">
                                            {{ $obs['suhu'] ?? '-' }} <span class="text-xs">°C</span></td>
                                        <td class="px-4 py-3 whitespace-nowrap text-gray-700 dark:text-gray-300">
                                            {{ $obs['spo2'] ?? '-' }} <span class="text-xs">%</span></td>
                                        <td class="px-4 py-3 whitespace-nowrap text-gray-700 dark:text-gray-300">
                                            {{ $obs['gda'] ?? '-' }} <span class="text-xs">mg/dL</span></td>
                                        <td class="px-4 py-3 whitespace-nowrap text-gray-700 dark:text-gray-300">
                                            {{ $obs['gcs'] ?? '-' }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-gray-700 dark:text-gray-300">
                                            <div>{{ $obs['cairan'] ?? '-' }} <span class="text-xs">ml</span></div>
                                            <div class="text-xs text-gray-400">{{ $obs['tetesan'] ?? '-' }} gtt/mnt
                                            </div>
                                        </td>
                                        @if (!$isFormLocked)
                                            <td class="px-4 py-3">
                                                <button type="button"
                                                    wire:click.prevent="removeObservasiLanjutan('{{ $obs['waktuPemeriksaan'] }}')"
                                                    wire:confirm="Hapus data observasi ini?"
                                                    wire:loading.attr="disabled"
                                                    class="inline-flex items-center justify-center w-8 h-8 text-red-500 transition rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 hover:text-red-600">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
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
                                            <svg class="w-8 h-8 mx-auto mb-2 opacity-40" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    stroke-width="1.5"
                                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            Belum ada data observasi
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- GRAFIK --}}
                @if (!empty($tandaVitalData))
                    @php
                        $sortedForChart = collect($tandaVitalData)
                            ->sortBy(
                                fn($item) => \Carbon\Carbon::createFromFormat(
                                    'd/m/Y H:i:s',
                                    $item['waktuPemeriksaan'] ?? '01/01/2000 00:00:00',
                                )->timestamp,
                            )
                            ->values();
                        $chartLabels = $sortedForChart->pluck('waktuPemeriksaan')->toArray();
                        $chartSuhu = $sortedForChart
                            ->map(fn($i) => is_numeric($i['suhu'] ?? null) ? (float) $i['suhu'] : null)
                            ->toArray();
                        $chartNadi = $sortedForChart
                            ->map(fn($i) => is_numeric($i['frekuensiNadi'] ?? null) ? (int) $i['frekuensiNadi'] : null)
                            ->toArray();
                    @endphp
                    <div class="p-4 bg-white border border-gray-200 rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                        <p class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-300">Grafik Suhu &amp; Nadi
                        </p>
                        <div wire:ignore>
                            <canvas id="observasiChart-{{ $rjNo }}"></canvas>
                        </div>
                        <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                const ctx = document.getElementById('observasiChart-{{ $rjNo }}');
                                if (!ctx) return;
                                new Chart(ctx, {
                                    type: 'line',
                                    data: {
                                        labels: {!! json_encode($chartLabels) !!},
                                        datasets: [{
                                                label: 'Suhu (°C)',
                                                data: {!! json_encode($chartSuhu) !!},
                                                borderColor: 'rgba(54,162,235,1)',
                                                borderWidth: 2,
                                                fill: false
                                            },
                                            {
                                                label: 'Nadi (x/mnt)',
                                                data: {!! json_encode($chartNadi) !!},
                                                borderColor: 'rgba(255,99,132,1)',
                                                borderWidth: 2,
                                                fill: false
                                            }
                                        ]
                                    },
                                    options: {
                                        scales: {
                                            y: {
                                                beginAtZero: false
                                            }
                                        }
                                    }
                                });
                            });
                        </script>
                    </div>
                @endif
            @else
                <div class="flex flex-col items-center justify-center py-16 text-gray-300 dark:text-gray-600">
                    <svg class="w-10 h-10 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <p class="text-sm font-medium">Data UGD belum dimuat</p>
                </div>
            @endif

        </div>
    </div>
</div>
