<?php
// resources/views/pages/transaksi/ri/emr-ri/rm-observasi-lanjutan-ri-actions.blade.php

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\WithValidationToast\WithValidationToastTrait;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Livewire\Attributes\On;

new class extends Component {
    use EmrRITrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public bool $isFormLocked = false;
    public ?int $riHdrNo = null; // konsisten dengan komponen obat & cairan
    public array $dataDaftarRi = [];

    public array $formEntryObservasi = [
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

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-observasi-lanjutan-ri'];

    public function mount(): void
    {
        $this->registerAreas(['modal-observasi-lanjutan-ri']);
    }

    #[On('open-observasi-lanjutan-ri')]
    public function open(int $riHdrNo): void
    {
        if (empty($riHdrNo)) {
            return;
        }
        $this->riHdrNo = $riHdrNo;
        $this->resetForm();

        $data = $this->findDataRI($riHdrNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }

        $this->dataDaftarRi = $data;
        $this->dataDaftarRi['observasi'] ??= [];
        $this->dataDaftarRi['observasi']['observasiLanjutan'] ??= [
            'tandaVitalTab' => 'Observasi Lanjutan',
            'tandaVital' => [],
        ];

        $this->isFormLocked = $this->checkEmrRIStatus($riHdrNo);
        $this->setWaktuPemeriksaan();
        $this->incrementVersion('modal-observasi-lanjutan-ri');
    }

    public function setWaktuPemeriksaan(): void
    {
        $this->formEntryObservasi['waktuPemeriksaan'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        $this->incrementVersion('modal-observasi-lanjutan-ri');
    }

    #[On('save-rm-observasi-lanjutan-ri')]
    public function addObservasiLanjutan(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }

        $this->formEntryObservasi['pemeriksa'] = auth()->user()->myuser_name ?? '';
        $this->validateWithToast(
            [
                'formEntryObservasi.waktuPemeriksaan' => 'required|date_format:d/m/Y H:i:s',
                'formEntryObservasi.sistolik' => 'required|numeric',
                'formEntryObservasi.distolik' => 'required|numeric',
                'formEntryObservasi.frekuensiNafas' => 'required|numeric',
                'formEntryObservasi.frekuensiNadi' => 'required|numeric',
                'formEntryObservasi.suhu' => 'required|numeric',
                'formEntryObservasi.spo2' => 'required|numeric',
            ],
            [
                'required' => ':attribute wajib diisi.',
                'numeric' => ':attribute harus berupa angka.',
                'date_format' => ':attribute harus format dd/mm/yyyy hh:mm:ss.',
            ],
            [
                'formEntryObservasi.waktuPemeriksaan' => 'Waktu Pemeriksaan',
                'formEntryObservasi.sistolik' => 'Sistolik',
                'formEntryObservasi.distolik' => 'Diastolik',
                'formEntryObservasi.frekuensiNafas' => 'Frekuensi Nafas',
                'formEntryObservasi.frekuensiNadi' => 'Frekuensi Nadi',
                'formEntryObservasi.suhu' => 'Suhu',
                'formEntryObservasi.spo2' => 'SpO₂',
            ],
        );

        try {
            DB::transaction(function () {
                // 1. Lock row
                $this->lockRIRow($this->riHdrNo);

                // 2. Baca data terkini setelah lock
                $data = $this->findDataRI($this->riHdrNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data RI tidak ditemukan.');
                }

                // 3. Inisialisasi struktur jika perlu
                $data['observasi']['observasiLanjutan']['tandaVital'] ??= [];

                // 4. Cek duplikasi waktu
                $exists = collect($data['observasi']['observasiLanjutan']['tandaVital'])->contains('waktuPemeriksaan', $this->formEntryObservasi['waktuPemeriksaan']);
                if ($exists) {
                    throw new \RuntimeException('Waktu pemeriksaan sudah ada.');
                }

                // 5. Tambah data
                $data['observasi']['observasiLanjutan']['tandaVital'][] = array_merge($this->formEntryObservasi, [
                    'sistolik' => (int) $this->formEntryObservasi['sistolik'],
                    'distolik' => (int) $this->formEntryObservasi['distolik'],
                    'frekuensiNafas' => (int) $this->formEntryObservasi['frekuensiNafas'],
                    'frekuensiNadi' => (int) $this->formEntryObservasi['frekuensiNadi'],
                    'suhu' => (float) $this->formEntryObservasi['suhu'],
                    'spo2' => (int) $this->formEntryObservasi['spo2'],
                    'gda' => $this->formEntryObservasi['gda'] === '' ? null : (float) $this->formEntryObservasi['gda'],
                    'gcs' => $this->formEntryObservasi['gcs'] === '' ? null : (int) $this->formEntryObservasi['gcs'],
                ]);

                // 6. Simpan JSON
                $this->updateJsonRI($this->riHdrNo, $data);
                $this->dataDaftarRi = $data;
            });

            $this->reset(['formEntryObservasi']);
            $this->setWaktuPemeriksaan();
            $this->incrementVersion('modal-observasi-lanjutan-ri');
            $this->dispatch('refresh-after-ri.saved', tab: 'observasi', subTab: 'ttv');
            $this->dispatch('toast', type: 'success', message: 'Observasi berhasil disimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    public function removeObservasiLanjutan(string $waktuPemeriksaan): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }

        try {
            DB::transaction(function () use ($waktuPemeriksaan) {
                $this->lockRIRow($this->riHdrNo);
                $data = $this->findDataRI($this->riHdrNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data RI tidak ditemukan.');
                }

                $data['observasi']['observasiLanjutan']['tandaVital'] = collect($data['observasi']['observasiLanjutan']['tandaVital'] ?? [])
                    ->reject(fn($r) => trim($r['waktuPemeriksaan'] ?? '') === trim($waktuPemeriksaan))
                    ->values()
                    ->all();

                $this->updateJsonRI($this->riHdrNo, $data);
                $this->dataDaftarRi = $data;
            });

            $this->incrementVersion('modal-observasi-lanjutan-ri');
            $this->dispatch('refresh-after-ri.saved', tab: 'observasi', subTab: 'ttv');
            $this->dispatch('toast', type: 'success', message: 'Observasi berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->dataDaftarRi = [];
        $this->reset(['formEntryObservasi']);
    }
};
?>

<div>
    <div class="flex flex-col w-full"
        wire:key="{{ $this->renderKey('modal-observasi-lanjutan-ri', [$riHdrNo ?? 'new']) }}">
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

            {{-- FORM INPUT --}}
            @if (!$isFormLocked)
                <div class="p-4 border border-gray-200 rounded-2xl dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40">
                    {{-- BARIS 1: Cairan, Tetesan, Waktu Pemeriksaan --}}
                    <div class="grid grid-cols-12 gap-3 mb-3">
                        <div class="col-span-12 md:col-span-4">
                            <x-input-label value="Cairan" class="mb-1" />
                            <x-text-input wire:model="formEntryObservasi.cairan" placeholder="Jenis cairan"
                                class="w-full" />
                            <x-input-error :messages="$errors->get('formEntryObservasi.cairan')" class="mt-1" />
                        </div>
                        <div class="col-span-12 md:col-span-4">
                            <x-input-label value="Tetesan (tetes/menit)" class="mb-1" />
                            <x-text-input wire:model="formEntryObservasi.tetesan" placeholder="Tetesan/menit"
                                class="w-full" />
                            <x-input-error :messages="$errors->get('formEntryObservasi.tetesan')" class="mt-1" />
                        </div>
                        <div class="col-span-12 md:col-span-4">
                            <x-input-label value="Waktu Pemeriksaan *" class="mb-1" />
                            <div class="flex items-center gap-1">
                                <x-text-input wire:model="formEntryObservasi.waktuPemeriksaan"
                                    placeholder="dd/mm/yyyy HH:ii:ss" class="flex-1" />
                                <x-secondary-button wire:click.prevent="setWaktuPemeriksaan" type="button"
                                    class="text-xs px-2">
                                    Set
                                </x-secondary-button>
                            </div>
                            <x-input-error :messages="$errors->get('formEntryObservasi.waktuPemeriksaan')" class="mt-1" />
                        </div>
                    </div>

                    {{-- BARIS 2: Semua nilai numerik (TD, Nadi, Nafas, Suhu, SpO2, GDA, GCS) + Tombol --}}
                    <div class="grid grid-cols-12 gap-2 items-end">
                        <div class="col-span-2">
                            <x-input-label value="Sistolik (mmHg)" class="mb-1" />
                            <x-text-input wire:model="formEntryObservasi.sistolik" placeholder="120" type="number"
                                class="w-full" />
                            <x-input-error :messages="$errors->get('formEntryObservasi.sistolik')" class="mt-1" />
                        </div>
                        <div class="col-span-2">
                            <x-input-label value="Diastolik (mmHg)" class="mb-1" />
                            <x-text-input wire:model="formEntryObservasi.distolik" placeholder="80" type="number"
                                class="w-full" />
                            <x-input-error :messages="$errors->get('formEntryObservasi.distolik')" class="mt-1" />
                        </div>
                        <div class="col-span-1">
                            <x-input-label value="Nadi (x/mnt)" class="mb-1" />
                            <x-text-input wire:model="formEntryObservasi.frekuensiNadi" placeholder="80" type="number"
                                class="w-full" />
                            <x-input-error :messages="$errors->get('formEntryObservasi.frekuensiNadi')" class="mt-1" />
                        </div>
                        <div class="col-span-1">
                            <x-input-label value="Nafas (x/mnt)" class="mb-1" />
                            <x-text-input wire:model="formEntryObservasi.frekuensiNafas" placeholder="20" type="number"
                                class="w-full" />
                            <x-input-error :messages="$errors->get('formEntryObservasi.frekuensiNafas')" class="mt-1" />
                        </div>
                        <div class="col-span-1">
                            <x-input-label value="Suhu (°C)" class="mb-1" />
                            <x-text-input wire:model="formEntryObservasi.suhu" placeholder="36.5" type="number"
                                step="0.1" class="w-full" />
                            <x-input-error :messages="$errors->get('formEntryObservasi.suhu')" class="mt-1" />
                        </div>
                        <div class="col-span-1">
                            <x-input-label value="SpO₂ (%)" class="mb-1" />
                            <x-text-input wire:model="formEntryObservasi.spo2" placeholder="98" type="number"
                                class="w-full" />
                            <x-input-error :messages="$errors->get('formEntryObservasi.spo2')" class="mt-1" />
                        </div>
                        <div class="col-span-1">
                            <x-input-label value="GDA (g/dL)" class="mb-1" />
                            <x-text-input wire:model="formEntryObservasi.gda" placeholder="100" type="number"
                                step="0.1" class="w-full" />
                            <x-input-error :messages="$errors->get('formEntryObservasi.gda')" class="mt-1" />
                        </div>
                        <div class="col-span-1">
                            <x-input-label value="GCS" class="mb-1" />
                            <x-text-input wire:model="formEntryObservasi.gcs" placeholder="15" type="number"
                                class="w-full" />
                            <x-input-error :messages="$errors->get('formEntryObservasi.gcs')" class="mt-1" />
                        </div>
                    </div>
                </div>
            @endif

            {{-- TABEL DATA --}}
            @php
                $daftarObs = $dataDaftarRi['observasi']['observasiLanjutan']['tandaVital'] ?? [];
                $sortedObs = collect($daftarObs)
                    ->sortByDesc(
                        fn($item) => Carbon::createFromFormat(
                            'd/m/Y H:i:s',
                            $item['waktuPemeriksaan'] ?? '01/01/2000 00:00:00',
                        )->timestamp,
                    )
                    ->values();
            @endphp

            <div
                class="overflow-hidden bg-white border border-gray-200 rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Riwayat Observasi Lanjutan
                    </h3>
                    <x-badge variant="gray">{{ count($daftarObs) }} item</x-badge>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead
                            class="text-xs font-semibold text-gray-500 uppercase bg-gray-50 dark:bg-gray-800/50 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-3">No</th>
                                <th class="px-4 py-3">Waktu</th>
                                <th class="px-4 py-3">Cairan</th>
                                <th class="px-4 py-3">Tetesan</th>
                                <th class="px-4 py-3">TD</th>
                                <th class="px-4 py-3">Nadi</th>
                                <th class="px-4 py-3">Nafas</th>
                                <th class="px-4 py-3">Suhu</th>
                                <th class="px-4 py-3">SpO₂</th>
                                <th class="px-4 py-3">GDA</th>
                                <th class="px-4 py-3">GCS</th>
                                <th class="px-4 py-3">Pemeriksa</th>
                                @if (!$isFormLocked)
                                    <th class="px-4 py-3 text-center w-20">Hapus</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($sortedObs as $item)
                                <tr wire:key="obs-{{ $item['waktuPemeriksaan'] ?? '' }}"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-800/40 transition">
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $loop->iteration }}
                                    </td>
                                    <td class="px-4 py-3 font-mono whitespace-nowrap">
                                        {{ $item['waktuPemeriksaan'] ?? '-' }}</td>
                                    <td class="px-4 py-3">{{ $item['cairan'] ?? '-' }}</td>
                                    <td class="px-4 py-3">{{ $item['tetesan'] ?? '-' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        {{ ($item['sistolik'] ?? '-') . '/' . ($item['distolik'] ?? '-') }}</td>
                                    <td class="px-4 py-3">{{ $item['frekuensiNadi'] ?? '-' }}</td>
                                    <td class="px-4 py-3">{{ $item['frekuensiNafas'] ?? '-' }}</td>
                                    <td class="px-4 py-3">{{ $item['suhu'] ?? '-' }}</td>
                                    <td class="px-4 py-3">{{ $item['spo2'] ?? '-' }}</td>
                                    <td class="px-4 py-3">{{ $item['gda'] ?? '-' }}</td>
                                    <td class="px-4 py-3">{{ $item['gcs'] ?? '-' }}</td>
                                    <td class="px-4 py-3">{{ $item['pemeriksa'] ?? '-' }}</td>
                                    @if (!$isFormLocked)
                                        <td class="px-4 py-3 text-center">
                                            <button type="button"
                                                wire:click.prevent="removeObservasiLanjutan('{{ $item['waktuPemeriksaan'] }}')"
                                                wire:confirm="Hapus data observasi ini?" wire:loading.attr="disabled"
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
                                    <td colspan="{{ $isFormLocked ? 12 : 13 }}"
                                        class="px-4 py-10 text-sm text-center text-gray-400 dark:text-gray-600">
                                        <svg class="w-8 h-8 mx-auto mb-2 opacity-40" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        Belum ada data observasi lanjutan
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
