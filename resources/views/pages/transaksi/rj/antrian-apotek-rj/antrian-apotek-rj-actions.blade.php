<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use EmrRJTrait, WithRenderVersioningTrait;

    public ?string $rjNo = null;
    public bool $isFormLocked = false;
    public array $dataDaftarPoliRJ = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-telaah-apotek'];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);
    }

    /* ===============================
     | OPEN TELAAH (shared modal)
     =============================== */
    #[On('antrian-apotek.telaah-resep.open')]
    public function openTelaahResep(string $rjNo): void
    {
        $this->openTelaahUnified($rjNo);
    }

    #[On('antrian-apotek.telaah-obat.open')]
    public function openTelaahObat(string $rjNo): void
    {
        $this->openTelaahUnified($rjNo);
    }

    private function openTelaahUnified(string $rjNo): void
    {
        $this->loadData($rjNo);

        // Init telaahResep defaults — merge agar key baru tidak hilang
        if (!isset($this->dataDaftarPoliRJ['telaahResep'])) {
            $this->dataDaftarPoliRJ['telaahResep'] = $this->defaultTelaahResep();
        } else {
            foreach ($this->defaultTelaahResep() as $key => $default) {
                $this->dataDaftarPoliRJ['telaahResep'][$key] ??= $default;
            }
        }

        // Init telaahObat defaults
        if (!isset($this->dataDaftarPoliRJ['telaahObat'])) {
            $this->dataDaftarPoliRJ['telaahObat'] = $this->defaultTelaahObat();
        } else {
            foreach ($this->defaultTelaahObat() as $key => $default) {
                $this->dataDaftarPoliRJ['telaahObat'][$key] ??= $default;
            }
        }

        $this->incrementVersion('modal-telaah-apotek');
        $this->dispatch('open-modal', name: 'telaah-apotek');
    }

    /* ===============================
     | SAVE TELAAH RESEP
     =============================== */
    public function saveTelaahResep(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only.');
            return;
        }

        try {
            DB::transaction(function () {
                $this->lockRJRow($this->rjNo);

                $data = $this->findDataRJ($this->rjNo) ?? [];

                if (empty($data)) {
                    throw new \RuntimeException('Data RJ tidak ditemukan, simpan dibatalkan.');
                }

                $data['telaahResep'] = $this->dataDaftarPoliRJ['telaahResep'] ?? [];

                $this->updateJsonRJ($this->rjNo, $data);
                $this->dataDaftarPoliRJ = $data;
            });

            $this->incrementVersion('modal-telaah-apotek');
            $this->dispatch('toast', type: 'success', message: 'Telaah Resep berhasil disimpan.');
            $this->afterSave();
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | TTD TELAAH RESEP
     =============================== */
    public function ttdTelaahResep(): void
    {
        if (!auth()->user()->hasRole('Apoteker')) {
            $this->dispatch('toast', type: 'error', message: 'Hanya Apoteker yang dapat melakukan TTD-E Telaah Resep.');
            return;
        }

        if (isset($this->dataDaftarPoliRJ['telaahResep']['penanggungJawab'])) {
            $this->dispatch('toast', type: 'info', message: 'TTD-E Telaah Resep sudah dilakukan oleh ' . $this->dataDaftarPoliRJ['telaahResep']['penanggungJawab']['userLog']);
            return;
        }

        try {
            DB::transaction(function () {
                $this->lockRJRow($this->rjNo);

                $data = $this->findDataRJ($this->rjNo) ?? [];

                if (empty($data)) {
                    throw new \RuntimeException('Data RJ tidak ditemukan, simpan dibatalkan.');
                }

                $data['telaahResep'] = $this->dataDaftarPoliRJ['telaahResep'] ?? [];
                $data['telaahResep']['penanggungJawab'] = [
                    'userLog' => auth()->user()->myuser_name,
                    'userLogCode' => auth()->user()->myuser_code,
                    'userLogDate' => Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'),
                ];

                $this->updateJsonRJ($this->rjNo, $data);
                $this->dataDaftarPoliRJ = $data;
            });

            $this->incrementVersion('modal-telaah-apotek');
            $this->dispatch('toast', type: 'success', message: 'TTD-E Telaah Resep berhasil disimpan.');
            $this->afterSave();
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal TTD-E: ' . $e->getMessage());
        }
    }

    /* ===============================
     | SAVE TELAAH OBAT
     =============================== */
    public function saveTelaahObat(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only.');
            return;
        }

        try {
            DB::transaction(function () {
                $this->lockRJRow($this->rjNo);

                $data = $this->findDataRJ($this->rjNo) ?? [];

                if (empty($data)) {
                    throw new \RuntimeException('Data RJ tidak ditemukan, simpan dibatalkan.');
                }

                $data['telaahObat'] = $this->dataDaftarPoliRJ['telaahObat'] ?? [];

                $this->updateJsonRJ($this->rjNo, $data);
                $this->dataDaftarPoliRJ = $data;
            });

            $this->incrementVersion('modal-telaah-apotek');
            $this->dispatch('toast', type: 'success', message: 'Telaah Obat berhasil disimpan.');
            $this->afterSave();
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | TTD TELAAH OBAT
     =============================== */
    public function ttdTelaahObat(): void
    {
        if (!auth()->user()->hasRole('Apoteker')) {
            $this->dispatch('toast', type: 'error', message: 'Hanya Apoteker yang dapat melakukan TTD-E Telaah Obat.');
            return;
        }

        if (isset($this->dataDaftarPoliRJ['telaahObat']['penanggungJawab'])) {
            $this->dispatch('toast', type: 'info', message: 'TTD-E Telaah Obat sudah dilakukan oleh ' . $this->dataDaftarPoliRJ['telaahObat']['penanggungJawab']['userLog']);
            return;
        }

        try {
            DB::transaction(function () {
                $this->lockRJRow($this->rjNo);

                $data = $this->findDataRJ($this->rjNo) ?? [];

                if (empty($data)) {
                    throw new \RuntimeException('Data RJ tidak ditemukan, simpan dibatalkan.');
                }

                $data['telaahObat'] = $this->dataDaftarPoliRJ['telaahObat'] ?? [];
                $data['telaahObat']['penanggungJawab'] = [
                    'userLog' => auth()->user()->myuser_name,
                    'userLogCode' => auth()->user()->myuser_code,
                    'userLogDate' => Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'),
                ];

                $this->updateJsonRJ($this->rjNo, $data);
                $this->dataDaftarPoliRJ = $data;
            });

            $this->incrementVersion('modal-telaah-apotek');
            $this->dispatch('toast', type: 'success', message: 'TTD-E Telaah Obat berhasil disimpan.');
            $this->afterSave();
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal TTD-E: ' . $e->getMessage());
        }
    }

    /* ===============================
     | CLOSE MODAL
     =============================== */
    public function closeTelaah(): void
    {
        $this->dispatch('close-modal', name: 'telaah-apotek');
        $this->resetForm();
    }

    /* ===============================
     | DEFAULT STRUCTURES
     =============================== */
    private function defaultTelaahResep(): array
    {
        return [
            'kejelasanTulisanResep' => ['kejelasanTulisanResep' => 'Ya', 'desc' => ''],
            'tepatObat' => ['tepatObat' => 'Ya', 'desc' => ''],
            'tepatDosis' => ['tepatDosis' => 'Ya', 'desc' => ''],
            'tepatRute' => ['tepatRute' => 'Ya', 'desc' => ''],
            'tepatWaktu' => ['tepatWaktu' => 'Ya', 'desc' => ''],
            'duplikasi' => ['duplikasi' => 'Tidak', 'desc' => ''],
            'alergi' => ['alergi' => 'Tidak', 'desc' => ''],
            'interaksiObat' => ['interaksiObat' => 'Tidak', 'desc' => ''],
            'bbPasienAnak' => ['bbPasienAnak' => 'Ya', 'desc' => ''],
            'kontraIndikasiLain' => ['kontraIndikasiLain' => 'Tidak', 'desc' => ''],
        ];
    }

    private function defaultTelaahObat(): array
    {
        return [
            'obatdgnResep' => ['obatdgnResep' => 'Ya', 'desc' => ''],
            'jmlDosisdgnResep' => ['jmlDosisdgnResep' => 'Ya', 'desc' => ''],
            'rutedgnResep' => ['rutedgnResep' => 'Ya', 'desc' => ''],
            'waktuFrekPemberiandgnResep' => ['waktuFrekPemberiandgnResep' => 'Ya', 'desc' => ''],
        ];
    }

    /* ===============================
     | HELPERS
     =============================== */
    private function loadData(string $rjNo): void
    {
        $this->rjNo = $rjNo;
        $this->isFormLocked = false;

        $data = $this->findDataRJ($rjNo);

        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Jalan tidak ditemukan.');
            return;
        }

        $this->dataDaftarPoliRJ = $data;
    }

    private function afterSave(): void
    {
        $this->dispatch('refresh-after-apotek.saved');
    }

    private function resetForm(): void
    {
        $this->resetVersion();
        $this->rjNo = null;
        $this->isFormLocked = false;
        $this->dataDaftarPoliRJ = [];
    }
};
?>

<div>
    {{-- ============================================================
     | MODAL: TELAAH RESEP & OBAT (side-by-side grid)
     ============================================================ --}}
    <x-modal name="telaah-apotek" size="full" height="full" focusable>
        <div wire:key="{{ $this->renderKey('modal-telaah-apotek', [$rjNo ?? 'new']) }}">

            {{-- HEADER --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Telaah Resep &amp; Obat
                    </h3>
                    @if (isset($dataDaftarPoliRJ['regName']))
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $dataDaftarPoliRJ['regName'] ?? '' }}
                            &bull; No RJ: {{ $rjNo }}
                        </p>
                    @endif
                </div>
                <x-icon-button color="gray" type="button" wire:click="closeTelaah" class="shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </x-icon-button>
            </div>

            {{-- GRID: TELAAH RESEP (KIRI, 2 unit) | TELAAH OBAT (KANAN, 1 unit) --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 divide-y lg:divide-y-0 lg:divide-x divide-gray-200 dark:divide-gray-700">

                {{-- ══════════════ KOLOM KIRI: TELAAH RESEP (2/3 width) ══════════════ --}}
                <div class="flex flex-col lg:col-span-2">

                {{-- BODY --}}
                <div class="px-6 py-4 overflow-y-auto max-h-[60vh]">
                    @if (isset($dataDaftarPoliRJ['telaahResep']))

                        {{-- Info obat --}}
                        @if (!empty($dataDaftarPoliRJ['eresep']) || !empty($dataDaftarPoliRJ['eresepRacikan']))
                            <div
                                class="mb-4 p-3 bg-blue-50 rounded-xl border border-blue-200 dark:bg-blue-900/20 dark:border-blue-700">
                                <p class="text-xs font-semibold text-blue-700 dark:text-blue-300 mb-1.5">Daftar Obat
                                    dalam Resep</p>
                                <div class="space-y-1">
                                    @foreach ($dataDaftarPoliRJ['eresep'] ?? [] as $obat)
                                        <div class="flex justify-between text-xs text-blue-800 dark:text-blue-200">
                                            <span
                                                class="font-medium uppercase">{{ $obat['productName'] ?? '-' }}</span>
                                            <span class="text-blue-600 dark:text-blue-400 shrink-0">
                                                No.{{ $obat['qty'] ?? '-' }} &mdash;
                                                S{{ $obat['signaX'] ?? '-' }}dd{{ $obat['signaHari'] ?? '-' }}
                                                @if (!empty($obat['catatanKhusus']))
                                                    ({{ $obat['catatanKhusus'] }})
                                                @endif
                                            </span>
                                        </div>
                                    @endforeach
                                    @if (!empty($dataDaftarPoliRJ['eresepRacikan']))
                                        @php $prevNo = null; @endphp
                                        @foreach ($dataDaftarPoliRJ['eresepRacikan'] as $racikan)
                                            @isset($racikan['jenisKeterangan'])
                                                <div
                                                    class="flex justify-between text-xs text-amber-800 dark:text-amber-200
                                                {{ $prevNo !== ($racikan['noRacikan'] ?? null) ? 'mt-1 pt-1 border-t border-amber-200 dark:border-amber-700' : '' }}">
                                                    <span class="font-medium uppercase">
                                                        {{ $racikan['noRacikan'] ?? '-' }}/
                                                        {{ $racikan['productName'] ?? '-' }}
                                                        @if (!empty($racikan['dosis']))
                                                            &mdash; {{ $racikan['dosis'] }}
                                                        @endif
                                                    </span>
                                                    @if (!empty($racikan['qty']))
                                                        <span class="text-amber-600 dark:text-amber-400 shrink-0">
                                                            Jml Racikan {{ $racikan['qty'] }}{{ !empty($racikan['takar']) ? ' ' . $racikan['takar'] : '' }}
                                                            @if (!empty($racikan['catatan']))
                                                                ({{ $racikan['catatan'] }})
                                                            @endif
                                                            @if (!empty($racikan['catatanKhusus']))
                                                                S{{ $racikan['catatanKhusus'] }}
                                                            @endif
                                                        </span>
                                                    @endif
                                                </div>
                                                @php $prevNo = $racikan['noRacikan'] ?? null; @endphp
                                            @endisset
                                        @endforeach
                                    @endif
                                </div>
                            </div>
                        @endif

                        {{-- Form telaah resep --}}
                        @php
                            $telaahResepLabels = [
                                'kejelasanTulisanResep' => 'Kejelasan Tulisan Resep',
                                'tepatObat' => 'Tepat Obat',
                                'tepatDosis' => 'Tepat Dosis',
                                'tepatRute' => 'Tepat Rute',
                                'tepatWaktu' => 'Tepat Waktu',
                                'duplikasi' => 'Duplikasi Obat',
                                'alergi' => 'Riwayat Alergi',
                                'interaksiObat' => 'Interaksi Obat',
                                'bbPasienAnak' => 'BB Pasien Anak',
                                'kontraIndikasiLain' => 'Kontra Indikasi Lain',
                            ];
                        @endphp

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-3">
                            @foreach ($dataDaftarPoliRJ['telaahResep'] as $key => $field)
                                @if ($key === 'penanggungJawab')
                                    @continue
                                @endif
                                @if (!is_array($field) || !isset($field[$key]))
                                    @continue
                                @endif
                                <div
                                    class="p-3 bg-gray-50 rounded-xl dark:bg-gray-800/50 border border-gray-100 dark:border-gray-700">
                                    <div class="flex items-center gap-3">
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                                {{ $telaahResepLabels[$key] ?? $key }}
                                            </p>
                                        </div>
                                        <div class="shrink-0">
                                            <x-toggle
                                                wire:model.live="dataDaftarPoliRJ.telaahResep.{{ $key }}.{{ $key }}"
                                                trueValue="Ya" falseValue="Tidak" :disabled="isset($dataDaftarPoliRJ['telaahResep']['penanggungJawab'])">
                                                {{ ($field[$key] ?? 'Tidak') === 'Ya' ? 'Ya' : 'Tidak' }}
                                            </x-toggle>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <x-text-input wire:model="dataDaftarPoliRJ.telaahResep.{{ $key }}.desc"
                                            class="w-full text-xs py-1.5" placeholder="Catatan (opsional)..."
                                            :disabled="isset($dataDaftarPoliRJ['telaahResep']['penanggungJawab'])" />
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- Ringkasan setelah TTD --}}
                        @if (isset($dataDaftarPoliRJ['telaahResep']['penanggungJawab']))
                            <div
                                class="mt-4 p-3 bg-emerald-50 rounded-xl border border-emerald-100 dark:bg-emerald-900/10 dark:border-emerald-800">
                                <p class="text-xs font-semibold text-emerald-700 dark:text-emerald-300 mb-2">Ringkasan
                                    Telaah Resep</p>
                                <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-1.5">
                                    @foreach ($dataDaftarPoliRJ['telaahResep'] as $key => $field)
                                        @if ($key === 'penanggungJawab')
                                            @continue
                                        @endif
                                        @if (!is_array($field) || !isset($field[$key]))
                                            @continue
                                        @endif
                                        <div class="flex items-center gap-1.5 text-xs">
                                            @if (($field[$key] ?? '') === 'Ya')
                                                <span class="text-emerald-500">✓</span>
                                                <span
                                                    class="text-emerald-700 dark:text-emerald-300">{{ $telaahResepLabels[$key] ?? $key }}</span>
                                            @else
                                                <span class="text-rose-500">✗</span>
                                                <span
                                                    class="text-rose-700 dark:text-rose-400">{{ $telaahResepLabels[$key] ?? $key }}</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @else
                        <div class="py-8 text-center text-gray-400">Memuat data telaah resep...</div>
                    @endif
                </div>

                {{-- FOOTER --}}
                <div
                    class="flex items-center justify-between gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-xl dark:border-gray-700 dark:bg-gray-900">
                    <x-secondary-button wire:click="closeTelaah">Tutup</x-secondary-button>

                    <div class="flex gap-2">
                        @if (!isset($dataDaftarPoliRJ['telaahResep']['penanggungJawab']))
                            <x-outline-button wire:click="saveTelaahResep" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="saveTelaahResep"
                                    class="flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                        stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                                    </svg>
                                    Simpan
                                </span>
                                <span wire:loading wire:target="saveTelaahResep" class="flex items-center gap-1.5">
                                    <x-loading /> Menyimpan...
                                </span>
                            </x-outline-button>

                            @if (auth()->user()->hasRole('Apoteker'))
                                <x-success-button wire:click="ttdTelaahResep" wire:loading.attr="disabled">
                                    <span wire:loading.remove wire:target="ttdTelaahResep"
                                        class="flex items-center gap-1.5">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M15.232 5.232l3.536 3.536M9 13l6.536-6.536a2.5 2.5 0 113.536 3.536L12.536 16.536a4 4 0 01-1.414.95L7 19l1.514-4.122A4 4 0 019 13z" />
                                        </svg>
                                        TTD-E & Selesai
                                    </span>
                                    <span wire:loading wire:target="ttdTelaahResep"
                                        class="flex items-center gap-1.5">
                                        <x-loading /> Proses TTD...
                                    </span>
                                </x-success-button>
                            @else
                                <div
                                    class="px-3 py-2 text-xs text-amber-700 bg-amber-50 rounded-lg border border-amber-200 dark:bg-amber-900/20 dark:text-amber-300 dark:border-amber-700">
                                    TTD-E hanya untuk Apoteker
                                </div>
                            @endif
                        @else
                            <div class="flex items-center gap-1.5 text-xs text-emerald-700 dark:text-emerald-300">
                                <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd" />
                                </svg>
                                <span>
                                    <strong>TTD-E</strong> oleh
                                    {{ $dataDaftarPoliRJ['telaahResep']['penanggungJawab']['userLog'] }}
                                    pada
                                    {{ $dataDaftarPoliRJ['telaahResep']['penanggungJawab']['userLogDate'] }}
                                </span>
                            </div>
                        @endif
                    </div>
                </div>
                </div>

                {{-- ══════════════ KOLOM KANAN: TELAAH OBAT ══════════════ --}}
                <div class="flex flex-col">

                {{-- BODY --}}
                <div class="px-6 py-4 overflow-y-auto max-h-[60vh]">
                    @if (isset($dataDaftarPoliRJ['telaahObat']))

                        {{-- Daftar obat --}}
                        @if (!empty($dataDaftarPoliRJ['eresep']) || !empty($dataDaftarPoliRJ['eresepRacikan']))
                            <div
                                class="mb-4 p-3 bg-blue-50 rounded-xl border border-blue-200 dark:bg-blue-900/20 dark:border-blue-700">
                                <p class="text-xs font-semibold text-blue-700 dark:text-blue-300 mb-1.5">Daftar Obat
                                    dalam Resep</p>
                                <div class="space-y-1">
                                    @foreach ($dataDaftarPoliRJ['eresep'] ?? [] as $idx => $obat)
                                        <div class="flex justify-between text-xs text-blue-800 dark:text-blue-200">
                                            <span>
                                                <span
                                                    class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-blue-200 text-blue-800 dark:bg-blue-800 dark:text-blue-200 text-[9px] font-bold mr-1">{{ $idx + 1 }}</span>
                                                <span
                                                    class="font-medium uppercase">{{ $obat['productName'] ?? '-' }}</span>
                                            </span>
                                            <span class="text-blue-600 dark:text-blue-400 shrink-0">
                                                No.{{ $obat['qty'] ?? '-' }} &mdash;
                                                S{{ $obat['signaX'] ?? '-' }}dd{{ $obat['signaHari'] ?? '-' }}
                                                @if (!empty($obat['catatanKhusus']))
                                                    ({{ $obat['catatanKhusus'] }})
                                                @endif
                                            </span>
                                        </div>
                                    @endforeach
                                    @if (!empty($dataDaftarPoliRJ['eresepRacikan']))
                                        @php $prevNo = null; @endphp
                                        @foreach ($dataDaftarPoliRJ['eresepRacikan'] as $racikan)
                                            @isset($racikan['jenisKeterangan'])
                                                <div
                                                    class="flex justify-between text-xs text-amber-800 dark:text-amber-200
                                                {{ $prevNo !== ($racikan['noRacikan'] ?? null) ? 'mt-1 pt-1 border-t border-amber-200 dark:border-amber-700' : '' }}">
                                                    <span class="font-medium uppercase">
                                                        {{ $racikan['noRacikan'] ?? '-' }}/
                                                        {{ $racikan['productName'] ?? '-' }}
                                                        @if (!empty($racikan['dosis']))
                                                            &mdash; {{ $racikan['dosis'] }}
                                                        @endif
                                                    </span>
                                                    @if (!empty($racikan['qty']))
                                                        <span class="text-amber-600 dark:text-amber-400 shrink-0">
                                                            Jml Racikan {{ $racikan['qty'] }}{{ !empty($racikan['takar']) ? ' ' . $racikan['takar'] : '' }}
                                                            @if (!empty($racikan['catatan']))
                                                                ({{ $racikan['catatan'] }})
                                                            @endif
                                                            @if (!empty($racikan['catatanKhusus']))
                                                                S{{ $racikan['catatanKhusus'] }}
                                                            @endif
                                                        </span>
                                                    @endif
                                                </div>
                                                @php $prevNo = $racikan['noRacikan'] ?? null; @endphp
                                            @endisset
                                        @endforeach
                                    @endif
                                </div>
                            </div>
                        @endif

                        {{-- Form telaah obat --}}
                        @php
                            $telaahObatLabels = [
                                'obatdgnResep' => 'Obat Sesuai Resep',
                                'jmlDosisdgnResep' => 'Jumlah & Dosis Sesuai Resep',
                                'rutedgnResep' => 'Rute Sesuai Resep',
                                'waktuFrekPemberiandgnResep' => 'Waktu & Frekuensi Pemberian',
                            ];
                        @endphp

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @foreach ($dataDaftarPoliRJ['telaahObat'] as $key => $field)
                                @if ($key === 'penanggungJawab')
                                    @continue
                                @endif
                                @if (!is_array($field) || !isset($field[$key]))
                                    @continue
                                @endif
                                <div
                                    class="p-3 bg-gray-50 rounded-xl dark:bg-gray-800/50 border border-gray-100 dark:border-gray-700">
                                    <div class="flex items-center gap-3">
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                                {{ $telaahObatLabels[$key] ?? $key }}
                                            </p>
                                        </div>
                                        <div class="shrink-0">
                                            <x-toggle
                                                wire:model.live="dataDaftarPoliRJ.telaahObat.{{ $key }}.{{ $key }}"
                                                trueValue="Ya" falseValue="Tidak" :disabled="isset($dataDaftarPoliRJ['telaahObat']['penanggungJawab'])">
                                                {{ ($field[$key] ?? 'Tidak') === 'Ya' ? 'Ya' : 'Tidak' }}
                                            </x-toggle>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <x-text-input wire:model="dataDaftarPoliRJ.telaahObat.{{ $key }}.desc"
                                            class="w-full text-xs py-1.5" placeholder="Catatan (opsional)..."
                                            :disabled="isset($dataDaftarPoliRJ['telaahObat']['penanggungJawab'])" />
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- Ringkasan setelah TTD --}}
                        @if (isset($dataDaftarPoliRJ['telaahObat']['penanggungJawab']))
                            <div
                                class="mt-4 p-3 bg-blue-50 rounded-xl border border-blue-100 dark:bg-blue-900/10 dark:border-blue-800">
                                <p class="text-xs font-semibold text-blue-700 dark:text-blue-300 mb-2">Ringkasan
                                    Telaah Obat</p>
                                <div class="grid grid-cols-2 gap-1.5">
                                    @foreach ($dataDaftarPoliRJ['telaahObat'] as $key => $field)
                                        @if ($key === 'penanggungJawab')
                                            @continue
                                        @endif
                                        @if (!is_array($field) || !isset($field[$key]))
                                            @continue
                                        @endif
                                        <div class="flex items-center gap-1.5 text-xs">
                                            @if (($field[$key] ?? '') === 'Ya')
                                                <span class="text-emerald-500">✓</span>
                                                <span
                                                    class="text-emerald-700 dark:text-emerald-300">{{ $telaahObatLabels[$key] ?? $key }}</span>
                                            @else
                                                <span class="text-rose-500">✗</span>
                                                <span
                                                    class="text-rose-700 dark:text-rose-400">{{ $telaahObatLabels[$key] ?? $key }}</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @else
                        <div class="py-8 text-center text-gray-400">Memuat data telaah obat...</div>
                    @endif
                </div>

                {{-- FOOTER --}}
                <div
                    class="flex items-center justify-between gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-xl dark:border-gray-700 dark:bg-gray-900">
                    <x-secondary-button wire:click="closeTelaah">Tutup</x-secondary-button>

                    <div class="flex gap-2">
                        @if (!isset($dataDaftarPoliRJ['telaahObat']['penanggungJawab']))
                            <x-outline-button wire:click="saveTelaahObat" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="saveTelaahObat"
                                    class="flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                        stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                                    </svg>
                                    Simpan
                                </span>
                                <span wire:loading wire:target="saveTelaahObat" class="flex items-center gap-1.5">
                                    <x-loading /> Menyimpan...
                                </span>
                            </x-outline-button>

                            @if (auth()->user()->hasRole('Apoteker'))
                                <x-info-button wire:click="ttdTelaahObat" wire:loading.attr="disabled">
                                    <span wire:loading.remove wire:target="ttdTelaahObat"
                                        class="flex items-center gap-1.5">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M15.232 5.232l3.536 3.536M9 13l6.536-6.536a2.5 2.5 0 113.536 3.536L12.536 16.536a4 4 0 01-1.414.95L7 19l1.514-4.122A4 4 0 019 13z" />
                                        </svg>
                                        TTD-E & Selesai
                                    </span>
                                    <span wire:loading wire:target="ttdTelaahObat"
                                        class="flex items-center gap-1.5">
                                        <x-loading /> Proses TTD...
                                    </span>
                                </x-info-button>
                            @else
                                <div
                                    class="px-3 py-2 text-xs text-amber-700 bg-amber-50 rounded-lg border border-amber-200 dark:bg-amber-900/20 dark:text-amber-300 dark:border-amber-700">
                                    TTD-E hanya untuk Apoteker
                                </div>
                            @endif
                        @else
                            <div class="flex items-center gap-1.5 text-xs text-blue-700 dark:text-blue-300">
                                <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd" />
                                </svg>
                                <span>
                                    <strong>TTD-E</strong> oleh
                                    {{ $dataDaftarPoliRJ['telaahObat']['penanggungJawab']['userLog'] }}
                                    pada
                                    {{ $dataDaftarPoliRJ['telaahObat']['penanggungJawab']['userLogDate'] }}
                                </span>
                            </div>
                        @endif
                    </div>
                </div>
                </div> {{-- /KOLOM KANAN --}}

            </div> {{-- /GRID --}}
        </div>
    </x-modal>
</div>
