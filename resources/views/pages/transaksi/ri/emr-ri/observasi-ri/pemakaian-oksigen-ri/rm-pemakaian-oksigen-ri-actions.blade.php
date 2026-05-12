<?php
// resources/views/pages/transaksi/ri/emr-ri/observasi-ri/pemakaian-oksigen-ri/rm-pemakaian-oksigen-ri-actions.blade.php

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Livewire\Attributes\On;

new class extends Component {
    use EmrRITrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?int $riHdrNo = null;
    public array $dataDaftarRi = [];

    public array $formEntryOksigen = [
        'jenisAlatOksigen' => 'Nasal Kanul',
        'jenisAlatOksigenDetail' => '',
        'dosisOksigen' => '1-2 L/menit',
        'dosisOksigenDetail' => '',
        'modelPenggunaan' => 'Kontinu',
        'durasiPenggunaan' => '',
        'tanggalWaktuMulai' => '',
        'tanggalWaktuSelesai' => '',
    ];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-pemakaian-oksigen-ri'];

    public function mount(): void
    {
        $this->registerAreas(['modal-pemakaian-oksigen-ri']);
    }

    #[On('open-pemakaian-oksigen-ri')]
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
        $this->dataDaftarRi['observasi']['pemakaianOksigen'] ??= [
            'pemakaianOksigenTab' => 'Pemakaian Oksigen',
            'pemakaianOksigenData' => [],
        ];

        $this->isFormLocked = $this->checkEmrRIStatus($riHdrNo);
        $this->setWaktuMulaiOksigen();
        $this->incrementVersion('modal-pemakaian-oksigen-ri');
    }

    public function setWaktuMulaiOksigen(): void
    {
        $this->formEntryOksigen['tanggalWaktuMulai'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        $this->incrementVersion('modal-pemakaian-oksigen-ri');
    }

    public function setWaktuSelesaiOksigen(): void
    {
        $this->formEntryOksigen['tanggalWaktuSelesai'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        $this->incrementVersion('modal-pemakaian-oksigen-ri');
    }

    public function addPemakaianOksigen(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }

        $this->formEntryOksigen['pemeriksa'] = auth()->user()->myuser_name ?? '';
        $this->validate(
            [
                'formEntryOksigen.jenisAlatOksigen' => 'required|in:Nasal Kanul,Masker Sederhana,Ventilator Non-Invasif,Lainnya',
                'formEntryOksigen.jenisAlatOksigenDetail' => 'required_if:formEntryOksigen.jenisAlatOksigen,Lainnya',
                'formEntryOksigen.dosisOksigen' => 'required|in:1-2 L/menit,3-4 L/menit,2-6 L/menit (Nasal Kanul),5-10 L/menit (Masker),Lainnya',
                'formEntryOksigen.dosisOksigenDetail' => 'required_if:formEntryOksigen.dosisOksigen,Lainnya',
                'formEntryOksigen.modelPenggunaan' => 'nullable|in:Kontinu,Intermiten',
                'formEntryOksigen.durasiPenggunaan' => 'nullable|string',
                'formEntryOksigen.tanggalWaktuMulai' => 'required|date_format:d/m/Y H:i:s',
                'formEntryOksigen.tanggalWaktuSelesai' => 'nullable|date_format:d/m/Y H:i:s|after:formEntryOksigen.tanggalWaktuMulai',
            ],
            [
                'in' => ':attribute tidak valid.',
                'required_if' => 'Detail :attribute wajib diisi kalau pilih Lainnya.',
                'date_format' => 'Format :attribute harus dd/mm/yyyy HH:ii:ss.',
                'after' => 'Waktu Selesai harus setelah Waktu Mulai.',
            ],
            [
                'formEntryOksigen.jenisAlatOksigen' => 'Jenis alat oksigen',
                'formEntryOksigen.jenisAlatOksigenDetail' => 'Detail alat oksigen',
                'formEntryOksigen.dosisOksigen' => 'Dosis oksigen',
                'formEntryOksigen.dosisOksigenDetail' => 'Detail dosis oksigen',
                'formEntryOksigen.modelPenggunaan' => 'Model penggunaan',
                'formEntryOksigen.tanggalWaktuMulai' => 'Waktu mulai',
                'formEntryOksigen.tanggalWaktuSelesai' => 'Waktu selesai',
            ],
        );

        try {
            DB::transaction(function () {
                $this->lockRIRow($this->riHdrNo);
                $data = $this->findDataRI($this->riHdrNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data RI tidak ditemukan.');
                }

                $data['observasi']['pemakaianOksigen']['pemakaianOksigenData'] ??= [];

                $exists = collect($data['observasi']['pemakaianOksigen']['pemakaianOksigenData'])->contains('tanggalWaktuMulai', $this->formEntryOksigen['tanggalWaktuMulai']);
                if ($exists) {
                    throw new \RuntimeException('Waktu mulai sudah ada.');
                }

                $data['observasi']['pemakaianOksigen']['pemakaianOksigenData'][] = array_merge($this->formEntryOksigen, [
                    'pemeriksa' => auth()->user()->myuser_name,
                    'tanggalWaktuSelesai' => $this->formEntryOksigen['tanggalWaktuSelesai'] ?: null,
                ]);

                $this->updateJsonRI($this->riHdrNo, $data);
                $this->dataDaftarRi = $data;
            });

            $this->reset(['formEntryOksigen']);
            $this->setWaktuMulaiOksigen();
            $this->incrementVersion('modal-pemakaian-oksigen-ri');
            $this->dispatch('toast', type: 'success', message: 'Pemakaian oksigen berhasil ditambahkan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /**
     * Update waktu selesai untuk item pemakaian oksigen by waktuMulai (key).
     * Auto-compute durasi: "X jam Y menit" dari diff waktuMulai → waktuSelesai.
     *
     * Pakai waktuMulai sebagai key (bukan index) karena array sudah di-sort di render,
     * jadi index visual berbeda dari index storage.
     */
    public function updateTanggalWaktuSelesai(string $waktuMulai, string $waktuSelesai): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }

        // Validasi format & after constraint
        $validator = Validator::make(
            ['waktuMulai' => $waktuMulai, 'waktuSelesai' => $waktuSelesai],
            [
                'waktuMulai' => 'required|date_format:d/m/Y H:i:s',
                'waktuSelesai' => 'required|date_format:d/m/Y H:i:s|after:waktuMulai',
            ],
            [
                'waktuMulai.required' => 'Waktu mulai tidak ditemukan.',
                'waktuMulai.date_format' => 'Format waktu mulai tidak valid.',
                'waktuSelesai.required' => 'Waktu selesai wajib diisi.',
                'waktuSelesai.date_format' => 'Format waktu selesai harus dd/mm/yyyy HH:ii:ss.',
                'waktuSelesai.after' => 'Waktu selesai harus setelah waktu mulai.',
            ],
        );

        if ($validator->fails()) {
            $this->dispatch('toast', type: 'error', message: $validator->errors()->first());
            return;
        }

        try {
            DB::transaction(function () use ($waktuMulai, $waktuSelesai) {
                $this->lockRIRow($this->riHdrNo);
                $data = $this->findDataRI($this->riHdrNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data RI tidak ditemukan.');
                }

                $list = $data['observasi']['pemakaianOksigen']['pemakaianOksigenData'] ?? [];
                $found = false;

                // Hitung durasi otomatis
                $mulai = Carbon::createFromFormat('d/m/Y H:i:s', $waktuMulai, config('app.timezone'));
                $selesai = Carbon::createFromFormat('d/m/Y H:i:s', $waktuSelesai, config('app.timezone'));
                $totalMinutes = $mulai->diffInMinutes($selesai);
                $jam = intdiv($totalMinutes, 60);
                $menit = $totalMinutes % 60;
                $durasi = $jam . ' jam ' . $menit . ' menit';

                foreach ($list as &$row) {
                    if (trim((string) ($row['tanggalWaktuMulai'] ?? '')) === trim($waktuMulai)) {
                        $row['tanggalWaktuSelesai'] = $waktuSelesai;
                        $row['durasiPenggunaan'] = $durasi;
                        $found = true;
                        break;
                    }
                }
                unset($row);

                if (!$found) {
                    throw new \RuntimeException('Item dengan waktu mulai tersebut tidak ditemukan.');
                }

                $data['observasi']['pemakaianOksigen']['pemakaianOksigenData'] = array_values($list);
                $this->updateJsonRI($this->riHdrNo, $data);
                $this->dataDaftarRi = $data;
            });

            $this->incrementVersion('modal-pemakaian-oksigen-ri');
            $this->dispatch('toast', type: 'success', message: 'Waktu selesai & durasi diperbarui.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal memperbarui: ' . $e->getMessage());
        }
    }

    public function removePemakaianOksigen(string $waktuMulai): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }

        try {
            DB::transaction(function () use ($waktuMulai) {
                $this->lockRIRow($this->riHdrNo);
                $data = $this->findDataRI($this->riHdrNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data RI tidak ditemukan.');
                }

                $data['observasi']['pemakaianOksigen']['pemakaianOksigenData'] = collect($data['observasi']['pemakaianOksigen']['pemakaianOksigenData'] ?? [])
                    ->reject(fn($r) => trim($r['tanggalWaktuMulai'] ?? '') === trim($waktuMulai))
                    ->values()
                    ->all();

                $this->updateJsonRI($this->riHdrNo, $data);
                $this->dataDaftarRi = $data;
            });

            $this->incrementVersion('modal-pemakaian-oksigen-ri');
            $this->dispatch('toast', type: 'success', message: 'Pemakaian oksigen berhasil dihapus.');
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
        $this->reset(['formEntryOksigen']);
    }
};
?>

<div>
    <div class="flex flex-col w-full"
        wire:key="{{ $this->renderKey('modal-pemakaian-oksigen-ri', [$riHdrNo ?? 'new']) }}">
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
                    <div class="grid grid-cols-12 gap-3">

                        {{-- Jenis Alat Oksigen --}}
                        <div class="col-span-2">
                            <x-input-label value="Jenis Alat *" class="mb-1" />
                            <x-select-input wire:model="formEntryOksigen.jenisAlatOksigen" class="w-full">
                                <option value="Nasal Kanul">Nasal Kanul</option>
                                <option value="Masker Sederhana">Masker Sederhana</option>
                                <option value="Ventilator Non-Invasif">Ventilator Non-Invasif</option>
                                <option value="Lainnya">Lainnya</option>
                            </x-select-input>
                            <x-input-error :messages="$errors->get('formEntryOksigen.jenisAlatOksigen')" class="mt-1" />
                        </div>

                        {{-- Detail Jenis Alat (jika Lainnya) --}}
                        @if ($formEntryOksigen['jenisAlatOksigen'] === 'Lainnya')
                            <div class="col-span-2">
                                <x-input-label value="Detail Alat" class="mb-1" />
                                <x-text-input wire:model="formEntryOksigen.jenisAlatOksigenDetail"
                                    placeholder="Sebutkan..." class="w-full" />
                                <x-input-error :messages="$errors->get('formEntryOksigen.jenisAlatOksigenDetail')" class="mt-1" />
                            </div>
                        @endif

                        {{-- Dosis Oksigen --}}
                        <div class="col-span-2">
                            <x-input-label value="Dosis *" class="mb-1" />
                            <x-select-input wire:model="formEntryOksigen.dosisOksigen" class="w-full">
                                <option value="1-2 L/menit">1-2 L/menit</option>
                                <option value="3-4 L/menit">3-4 L/menit</option>
                                <option value="2-6 L/menit (Nasal Kanul)">2-6 L/menit (Nasal Kanul)</option>
                                <option value="5-10 L/menit (Masker)">5-10 L/menit (Masker)</option>
                                <option value="Lainnya">Lainnya</option>
                            </x-select-input>
                            <x-input-error :messages="$errors->get('formEntryOksigen.dosisOksigen')" class="mt-1" />
                        </div>

                        {{-- Detail Dosis (jika Lainnya) --}}
                        @if ($formEntryOksigen['dosisOksigen'] === 'Lainnya')
                            <div class="col-span-2">
                                <x-input-label value="Detail Dosis" class="mb-1" />
                                <x-text-input wire:model="formEntryOksigen.dosisOksigenDetail"
                                    placeholder="cth: 6 L/menit" class="w-full" />
                                <x-input-error :messages="$errors->get('formEntryOksigen.dosisOksigenDetail')" class="mt-1" />
                            </div>
                        @endif

                        {{-- Model Penggunaan --}}
                        <div class="col-span-1">
                            <x-input-label value="Model" class="mb-1" />
                            <x-select-input wire:model="formEntryOksigen.modelPenggunaan" class="w-full">
                                <option value="Kontinu">Kontinu</option>
                                <option value="Intermiten">Intermiten</option>
                            </x-select-input>
                            <x-input-error :messages="$errors->get('formEntryOksigen.modelPenggunaan')" class="mt-1" />
                        </div>

                        {{-- Durasi Penggunaan --}}
                        <div class="col-span-1">
                            <x-input-label value="Durasi" class="mb-1" />
                            <x-text-input wire:model="formEntryOksigen.durasiPenggunaan" placeholder="4 jam"
                                class="w-full" />
                            <x-input-error :messages="$errors->get('formEntryOksigen.durasiPenggunaan')" class="mt-1" />
                        </div>

                        {{-- Waktu Mulai --}}
                        <div class="col-span-3">
                            <x-input-label value="Waktu Mulai *" class="mb-1" />
                            <div class="flex items-center gap-1">
                                <x-text-input wire:model="formEntryOksigen.tanggalWaktuMulai"
                                    placeholder="dd/mm/yyyy HH:ii:ss" class="flex-1" />
                                <x-secondary-button wire:click.prevent="setWaktuMulaiOksigen" type="button"
                                    class="text-xs px-2">
                                    Set
                                </x-secondary-button>
                            </div>
                            <x-input-error :messages="$errors->get('formEntryOksigen.tanggalWaktuMulai')" class="mt-1" />
                        </div>

                        {{-- Waktu Selesai (opsional) --}}
                        <div class="col-span-3">
                            <x-input-label value="Waktu Selesai" class="mb-1" />
                            <div class="flex items-center gap-1">
                                <x-text-input wire:model="formEntryOksigen.tanggalWaktuSelesai"
                                    placeholder="dd/mm/yyyy HH:ii:ss" class="flex-1" />
                                <x-secondary-button wire:click.prevent="setWaktuSelesaiOksigen" type="button"
                                    class="text-xs px-2">
                                    Set
                                </x-secondary-button>
                            </div>
                            <x-input-error :messages="$errors->get('formEntryOksigen.tanggalWaktuSelesai')" class="mt-1" />
                        </div>

                        {{-- Tombol Tambah --}}
                        <div class="col-span-1 flex items-end">
                            <x-primary-button wire:click.prevent="addPemakaianOksigen" wire:loading.attr="disabled"
                                wire:target="addPemakaianOksigen" class="gap-1 h-[38px] w-full justify-center">
                                <span wire:loading.remove wire:target="addPemakaianOksigen">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 4v16m8-8H4" />
                                    </svg>
                                </span>
                                <span wire:loading wire:target="addPemakaianOksigen"><x-loading
                                        class="w-4 h-4" /></span>
                                Tambah
                            </x-primary-button>
                        </div>

                    </div>
                </div>
            @endif

            {{-- TABEL DATA --}}
            @php
                $daftarOksigen = $dataDaftarRi['observasi']['pemakaianOksigen']['pemakaianOksigenData'] ?? [];
                $sortedOksigen = collect($daftarOksigen)
                    ->sortByDesc(
                        fn($item) => Carbon::createFromFormat(
                            'd/m/Y H:i:s',
                            $item['tanggalWaktuMulai'] ?? '01/01/2000 00:00:00',
                        )->timestamp,
                    )
                    ->values();
            @endphp

            <div
                class="overflow-hidden bg-white border border-gray-200 rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Riwayat Pemakaian Oksigen</h3>
                    <x-badge variant="gray">{{ count($daftarOksigen) }} item</x-badge>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead
                            class="text-xs font-semibold text-gray-500 uppercase bg-gray-50 dark:bg-gray-800/50 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-3">No</th>
                                <th class="px-4 py-3">Waktu Mulai</th>
                                <th class="px-4 py-3">Waktu Selesai</th>
                                <th class="px-4 py-3">Jenis Alat</th>
                                <th class="px-4 py-3">Dosis</th>
                                <th class="px-4 py-3">Model</th>
                                <th class="px-4 py-3">Durasi</th>
                                <th class="px-4 py-3">Pemeriksa</th>
                                @if (!$isFormLocked)
                                    <th class="px-4 py-3 text-center w-20">Hapus</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($sortedOksigen as $item)
                                @php
                                    $itemMulai = $item['tanggalWaktuMulai'] ?? '';
                                    $itemSelesai = $item['tanggalWaktuSelesai'] ?? '';
                                    $editKey = 'o2edit-' . md5($itemMulai);
                                @endphp
                                <tr wire:key="o2-{{ $itemMulai }}"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-800/40 transition"
                                    x-data="{ editing: false, val: '{{ $itemSelesai }}' }">
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $loop->iteration }}
                                    </td>
                                    <td class="px-4 py-3 font-mono whitespace-nowrap">
                                        {{ $itemMulai ?: '-' }}</td>
                                    <td class="px-4 py-3 font-mono whitespace-nowrap">
                                        @if (!$isFormLocked && empty($itemSelesai))
                                            <div class="flex items-center gap-1">
                                                <input type="text" x-model="val"
                                                    placeholder="dd/mm/yyyy HH:ii:ss"
                                                    class="w-44 px-2 py-1 text-xs border rounded font-mono
                                                        border-gray-200 dark:border-gray-700 dark:bg-gray-800" />
                                                <button type="button"
                                                    x-on:click="val = (new Date()).toLocaleDateString('id-ID', {day:'2-digit',month:'2-digit',year:'numeric'}).replace(/\//g,'/') + ' ' + (new Date()).toLocaleTimeString('id-ID', {hour12:false})"
                                                    class="px-2 py-1 text-[10px] rounded bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600"
                                                    title="Isi waktu sekarang">Now</button>
                                                <button type="button"
                                                    x-on:click="$wire.updateTanggalWaktuSelesai('{{ $itemMulai }}', val)"
                                                    class="px-2 py-1 text-[10px] rounded bg-emerald-600 hover:bg-emerald-700 text-white"
                                                    title="Simpan waktu selesai & hitung durasi">Set</button>
                                            </div>
                                        @elseif (!$isFormLocked && !empty($itemSelesai))
                                            <div class="flex items-center gap-1">
                                                <span x-show="!editing">{{ $itemSelesai }}</span>
                                                <input x-show="editing" type="text" x-model="val"
                                                    placeholder="dd/mm/yyyy HH:ii:ss"
                                                    class="w-44 px-2 py-1 text-xs border rounded font-mono
                                                        border-gray-200 dark:border-gray-700 dark:bg-gray-800" />
                                                <button type="button" x-show="!editing"
                                                    x-on:click="editing = true"
                                                    class="px-2 py-1 text-[10px] rounded bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600"
                                                    title="Ubah waktu selesai">Edit</button>
                                                <button type="button" x-show="editing"
                                                    x-on:click="$wire.updateTanggalWaktuSelesai('{{ $itemMulai }}', val); editing = false"
                                                    class="px-2 py-1 text-[10px] rounded bg-emerald-600 hover:bg-emerald-700 text-white">Set</button>
                                                <button type="button" x-show="editing"
                                                    x-on:click="editing = false; val = '{{ $itemSelesai }}'"
                                                    class="px-2 py-1 text-[10px] rounded bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600">Batal</button>
                                            </div>
                                        @else
                                            {{ $itemSelesai ?: '-' }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ $item['jenisAlatOksigen'] ?? '-' }}
                                        @if (($item['jenisAlatOksigen'] ?? '') === 'Lainnya' && !empty($item['jenisAlatOksigenDetail']))
                                            <span
                                                class="text-xs text-gray-500">({{ $item['jenisAlatOksigenDetail'] }})</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ $item['dosisOksigen'] ?? '-' }}
                                        @if (($item['dosisOksigen'] ?? '') === 'Lainnya' && !empty($item['dosisOksigenDetail']))
                                            <span
                                                class="text-xs text-gray-500">({{ $item['dosisOksigenDetail'] }})</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">{{ $item['modelPenggunaan'] ?? '-' }}</td>
                                    <td class="px-4 py-3">{{ $item['durasiPenggunaan'] ?? '-' }}</td>
                                    <td class="px-4 py-3">{{ $item['pemeriksa'] ?? '-' }}</td>
                                    @if (!$isFormLocked)
                                        <td class="px-4 py-3 text-center">
                                            <button type="button"
                                                wire:click.prevent="removePemakaianOksigen('{{ $item['tanggalWaktuMulai'] }}')"
                                                wire:confirm="Hapus data pemakaian oksigen ini?"
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
                                    <td colspan="{{ $isFormLocked ? 8 : 9 }}"
                                        class="px-4 py-10 text-sm text-center text-gray-400 dark:text-gray-600">
                                        <svg class="w-8 h-8 mx-auto mb-2 opacity-40" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        Belum ada data pemakaian oksigen
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
