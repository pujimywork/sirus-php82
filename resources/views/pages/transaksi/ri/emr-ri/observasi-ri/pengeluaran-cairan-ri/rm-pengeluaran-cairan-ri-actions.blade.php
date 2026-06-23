<?php
// resources/views/pages/transaksi/ri/emr-ri/rm-pengeluaran-cairan-ri-actions.blade.php

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
    public ?int $riHdrNo = null;
    public array $dataDaftarRi = [];

    public array $formEntryPengeluaran = [
        'waktuPengeluaran' => '',
        'jenisOutput' => '',
        'volume' => '',
        'warnaKarakteristik' => '',
        'keterangan' => '',
        'pemeriksa' => '',
    ];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-pengeluaran-cairan-ri'];

    public function mount(): void
    {
        $this->registerAreas(['modal-pengeluaran-cairan-ri']);
    }

    #[On('open-pengeluaran-cairan-ri')]
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
        $this->dataDaftarRi['observasi']['pengeluaranCairan'] ??= [
            'pengeluaranCairanTab' => 'Pengeluaran Cairan',
            'pengeluaranCairan' => [],
        ];

        $this->isFormLocked = $this->checkRIStatus($riHdrNo);
        $this->setWaktuPengeluaran(); // set default waktu
        $this->incrementVersion('modal-pengeluaran-cairan-ri');
    }

    public function setWaktuPengeluaran(): void
    {
        $this->formEntryPengeluaran['waktuPengeluaran'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        $this->incrementVersion('modal-pengeluaran-cairan-ri');
    }

    #[On('save-rm-pengeluaran-cairan-ri')]
    public function addPengeluaranCairan(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }

        $this->formEntryPengeluaran['pemeriksa'] = auth()->user()->myuser_name ?? '';
        $this->validateWithToast(
            [
                'formEntryPengeluaran.waktuPengeluaran' => 'required|date_format:d/m/Y H:i:s',
                'formEntryPengeluaran.jenisOutput' => 'required',
                'formEntryPengeluaran.volume' => 'required|numeric',
            ],
            [
                'required' => ':attribute wajib diisi.',
                'numeric' => ':attribute harus berupa angka.',
                'date_format' => ':attribute harus format dd/mm/yyyy hh:mm:ss.',
            ],
            [
                'formEntryPengeluaran.waktuPengeluaran' => 'Waktu Pengeluaran',
                'formEntryPengeluaran.jenisOutput' => 'Jenis Output',
                'formEntryPengeluaran.volume' => 'Volume',
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
                $data['observasi']['pengeluaranCairan']['pengeluaranCairan'] ??= [];

                // 4. Cek duplikasi waktu
                $exists = collect($data['observasi']['pengeluaranCairan']['pengeluaranCairan'])->contains('waktuPengeluaran', $this->formEntryPengeluaran['waktuPengeluaran']);
                if ($exists) {
                    throw new \RuntimeException('Waktu pengeluaran sudah ada.');
                }

                // 5. Tambah data
                $data['observasi']['pengeluaranCairan']['pengeluaranCairan'][] = array_merge($this->formEntryPengeluaran, [
                    'volume' => (float) $this->formEntryPengeluaran['volume'],
                ]);

                // 6. Simpan JSON
                $this->updateJsonRI($this->riHdrNo, $data);
                $this->dataDaftarRi = $data;

                // 7. Audit log
                $this->appendAdminLogRI((int) $this->riHdrNo, 'Tambah Pengeluaran Cairan — ' . ($this->formEntryPengeluaran['jenisOutput'] ?? '-') . ' @ ' . ($this->formEntryPengeluaran['waktuPengeluaran'] ?? '-'), 'MR');
            });

            $this->reset(['formEntryPengeluaran']);
            $this->setWaktuPengeluaran(); // set ulang waktu setelah reset
            $this->incrementVersion('modal-pengeluaran-cairan-ri');
            $this->dispatch('refresh-after-ri.saved', tab: 'observasi', subTab: 'pengeluaran');
            $this->dispatch('toast', type: 'success', message: 'Pengeluaran cairan berhasil ditambahkan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    public function removePengeluaranCairan(string $waktuPengeluaran): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }

        try {
            DB::transaction(function () use ($waktuPengeluaran) {
                $this->lockRIRow($this->riHdrNo);
                $data = $this->findDataRI($this->riHdrNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data RI tidak ditemukan.');
                }

                $deletedRow = collect($data['observasi']['pengeluaranCairan']['pengeluaranCairan'] ?? [])
                    ->first(fn($r) => trim($r['waktuPengeluaran'] ?? '') === trim($waktuPengeluaran));

                $data['observasi']['pengeluaranCairan']['pengeluaranCairan'] = collect($data['observasi']['pengeluaranCairan']['pengeluaranCairan'] ?? [])
                    ->reject(fn($r) => trim($r['waktuPengeluaran'] ?? '') === trim($waktuPengeluaran))
                    ->values()
                    ->all();

                $this->updateJsonRI($this->riHdrNo, $data);
                $this->dataDaftarRi = $data;

                // Audit log
                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Pengeluaran Cairan — ' . ($deletedRow['jenisOutput'] ?? '-') . ' @ ' . $waktuPengeluaran, 'MR');
            });

            $this->incrementVersion('modal-pengeluaran-cairan-ri');
            $this->dispatch('refresh-after-ri.saved', tab: 'observasi', subTab: 'pengeluaran');
            $this->dispatch('toast', type: 'success', message: 'Pengeluaran cairan berhasil dihapus.');
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
        $this->reset(['formEntryPengeluaran']);
    }
};
?>

<div>
    <div class="flex flex-col w-full"
        wire:key="{{ $this->renderKey('modal-pengeluaran-cairan-ri', [$riHdrNo ?? 'new']) }}">
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
                    {{-- Enter-chain (pola e-resep): waktu → jenisOutput → volume → warna → keterangan → simpan. --}}
                    <div class="flex flex-wrap items-end gap-2">

                        {{-- Waktu Pengeluaran (DEPAN) — auto-focus, Enter → jenisOutput --}}
                        <div class="w-64">
                            <x-input-label value="Waktu *" class="mb-1" />
                            <div class="flex items-center gap-1">
                                <x-text-input wire:model="formEntryPengeluaran.waktuPengeluaran"
                                    placeholder="dd/mm/yyyy HH:ii:ss" class="flex-1" x-ref="pcWaktu"
                                    x-init="$nextTick(() => $el.focus())"
                                    x-on:keydown.enter.prevent="$refs.pcJenis.focus()" />
                                <x-now-button wire:click.prevent="setWaktuPengeluaran" />
                            </div>
                            <x-input-error :messages="$errors->get('formEntryPengeluaran.waktuPengeluaran')" class="mt-1" />
                        </div>

                        {{-- Jenis Output --}}
                        <div class="w-40">
                            <x-input-label value="Jenis Output *" class="mb-1" />
                            <x-text-input wire:model="formEntryPengeluaran.jenisOutput"
                                placeholder="Urine / Feses / dll" class="w-full"
                                x-ref="pcJenis" x-on:keydown.enter.prevent="$refs.pcVolume.focus()" />
                            <x-input-error :messages="$errors->get('formEntryPengeluaran.jenisOutput')" class="mt-1" />
                        </div>

                        {{-- Volume (native number, desimal aman) --}}
                        <div class="w-24">
                            <x-input-label value="Volume (ml) *" class="mb-1" />
                            <x-text-input wire:model="formEntryPengeluaran.volume" type="number" step="any"
                                placeholder="0" class="w-full" x-ref="pcVolume"
                                x-on:keydown.enter.prevent="$refs.pcWarna.focus()" />
                            <x-input-error :messages="$errors->get('formEntryPengeluaran.volume')" class="mt-1" />
                        </div>

                        {{-- Warna / Karakteristik --}}
                        <div class="flex-1 min-w-[150px]">
                            <x-input-label value="Warna / Karakteristik" class="mb-1" />
                            <x-text-input wire:model="formEntryPengeluaran.warnaKarakteristik"
                                placeholder="Kuning jernih, berdarah, dll" class="w-full" x-ref="pcWarna"
                                x-on:keydown.enter.prevent="$refs.pcKeterangan.focus()" />
                            <x-input-error :messages="$errors->get('formEntryPengeluaran.warnaKarakteristik')" class="mt-1" />
                        </div>

                        {{-- Keterangan — field terakhir, Enter = simpan (pola #3: blur dulu) --}}
                        <div class="flex-1 min-w-[150px]">
                            <x-input-label value="Keterangan" class="mb-1" />
                            <x-text-input wire:model="formEntryPengeluaran.keterangan" placeholder="Catatan tambahan..."
                                class="w-full" x-ref="pcKeterangan"
                                x-on:keydown.enter.prevent="$el.blur(); $wire.addPengeluaranCairan()" />
                            <x-input-error :messages="$errors->get('formEntryPengeluaran.keterangan')" class="mt-1" />
                        </div>


                    </div>
                </div>
            @endif

            {{-- TABEL DATA --}}
            @php
                $daftarPengeluaran = $dataDaftarRi['observasi']['pengeluaranCairan']['pengeluaranCairan'] ?? [];
                $sortedPengeluaran = collect($daftarPengeluaran)
                    ->sortByDesc(
                        fn($item) => Carbon::createFromFormat(
                            'd/m/Y H:i:s',
                            $item['waktuPengeluaran'] ?? '01/01/2000 00:00:00',
                        )->timestamp,
                    )
                    ->values();
            @endphp

            <div
                class="overflow-hidden bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-center justify-between px-4 py-3 border-b border-hairline dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-body dark:text-gray-300">Riwayat Pengeluaran Cairan
                    </h3>
                    <x-badge variant="gray">{{ count($daftarPengeluaran) }} item</x-badge>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead
                            class="text-xs font-semibold text-muted uppercase bg-surface-soft dark:bg-gray-800/50 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-3">No</th>
                                <th class="px-4 py-3">Waktu</th>
                                <th class="px-4 py-3">Jenis Output</th>
                                <th class="px-4 py-3 text-center">Volume (ml)</th>
                                <th class="px-4 py-3">Warna/Karakteristik</th>
                                <th class="px-4 py-3">Keterangan</th>
                                <th class="px-4 py-3">Pemeriksa</th>
                                @if (!$isFormLocked)
                                    <th class="px-4 py-3 text-center w-20">Hapus</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-hairline-soft dark:divide-gray-800">
                            @forelse ($sortedPengeluaran as $item)
                                <tr wire:key="pc-{{ $item['waktuPengeluaran'] ?? '' }}"
                                    class="hover:bg-surface-soft dark:hover:bg-gray-800/40 transition">
                                    <td class="px-4 py-3 text-muted dark:text-gray-400">{{ $loop->iteration }}
                                    </td>
                                    <td class="px-4 py-3 font-mono whitespace-nowrap">
                                        {{ $item['waktuPengeluaran'] ?? '-' }}</td>
                                    <td class="px-4 py-3">{{ $item['jenisOutput'] ?? '-' }}</td>
                                    <td class="px-4 py-3 text-center">{{ $item['volume'] ?? '-' }}</td>
                                    <td class="px-4 py-3">{{ $item['warnaKarakteristik'] ?? '-' }}</td>
                                    <td class="px-4 py-3">{{ $item['keterangan'] ?? '-' }}</td>
                                    <td class="px-4 py-3">{{ $item['pemeriksa'] ?? '-' }}</td>
                                    @if (!$isFormLocked)
                                        <td class="px-4 py-3 text-center">
                                            <x-outline-button type="button"
                                                wire:click.prevent="removePengeluaranCairan('{{ $item['waktuPengeluaran'] }}')"
                                                wire:confirm="Hapus data pengeluaran cairan ini?"
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
                                        Belum ada data pengeluaran cairan
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
