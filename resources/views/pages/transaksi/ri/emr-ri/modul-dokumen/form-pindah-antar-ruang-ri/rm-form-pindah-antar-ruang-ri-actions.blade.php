<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/form-pindah-antar-ruang-ri/rm-form-pindah-antar-ruang-ri-actions.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use EmrRITrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?string $riHdrNo = null;
    public bool $disabled = false;
    public array $dataDaftarRi = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-form-pindah-ri'];

    /** Track entry yang sedang di-edit (null = entry baru) */
    public ?string $editingTglPindah = null;

    public array $newPindah = [
        'tglPindah' => '',
        'tglTerima' => '',
        'dariRoomId' => '',
        'dariRoomDesc' => '',
        'dariBedNo' => '',
        'keRoomId' => '',
        'keRoomDesc' => '',
        'keBedNo' => '',
        'alasanPindah' => '',
        'kondisiKirim' => [
            'sistolik' => '',
            'diastolik' => '',
            'frekuensiNafas' => '',
            'frekuensiNadi' => '',
            'suhu' => '',
            'spo2' => '',
            'gcs' => '',
            'keadaanPasien' => '',
        ],
        'kondisiTerima' => [
            'sistolik' => '',
            'diastolik' => '',
            'frekuensiNafas' => '',
            'frekuensiNadi' => '',
            'suhu' => '',
            'spo2' => '',
            'gcs' => '',
            'keadaanPasien' => '',
        ],
        'petugasPengirim' => '',
        'petugasPengirimCode' => '',
        'petugasPengirimDate' => '',
        'petugasPenerima' => '',
        'petugasPenerimaCode' => '',
        'petugasPenerimaDate' => '',
    ];

    public array $listPindah = [];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(?string $riHdrNo = null, bool $disabled = false): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-form-pindah-ri']);

        if ($this->riHdrNo) {
            $data = $this->findDataRI($this->riHdrNo);
            if ($data) {
                $this->dataDaftarRi = $data;
                $this->listPindah = $data['formPindahAntarRuangRI'] ?? [];
                $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $disabled;
            }
        }
    }

    /* ===============================
     | OPEN MODAL
     =============================== */
    public function openModal(): void
    {
        if (!$this->riHdrNo || $this->disabled) {
            return;
        }

        $this->resetNewPindah();
        $this->editingTglPindah = null;
        $this->resetValidation();

        $data = $this->findDataRI($this->riHdrNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }

        $this->dataDaftarRi = $data;
        $this->listPindah = $data['formPindahAntarRuangRI'] ?? [];

        // Auto-fill "Dari ruang" dari kamar pasien saat ini
        $this->newPindah['dariRoomId'] = $data['roomId'] ?? '';
        $this->newPindah['dariRoomDesc'] = $data['roomDesc'] ?? '';
        $this->newPindah['dariBedNo'] = $data['bedNo'] ?? '';

        $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $this->disabled;
        $this->incrementVersion('modal-form-pindah-ri');

        $this->dispatch('open-modal', name: "rm-form-pindah-ri-{$this->riHdrNo}");
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: "rm-form-pindah-ri-{$this->riHdrNo}");
    }

    /* ===============================
     | LOAD ENTRY UNTUK DI-EDIT/LANJUTKAN
     =============================== */
    public function editPindah(string $tglPindah): void
    {
        if ($this->isFormLocked) {
            return;
        }

        $entry = collect($this->listPindah)->firstWhere('tglPindah', $tglPindah);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Entry tidak ditemukan.');
            return;
        }

        if ($this->isEntryLocked($entry)) {
            $this->dispatch('toast', type: 'warning', message: 'Entry sudah selesai (kedua TTD lengkap), tidak dapat diedit.');
            return;
        }

        $this->editingTglPindah = $tglPindah;
        $this->resetValidation();
        $this->newPindah = array_replace_recursive($this->newPindah, $entry);
        $this->incrementVersion('modal-form-pindah-ri');
    }

    public function batalEdit(): void
    {
        $this->editingTglPindah = null;
        $this->resetNewPindah();
        $this->newPindah['dariRoomId'] = $this->dataDaftarRi['roomId'] ?? '';
        $this->newPindah['dariRoomDesc'] = $this->dataDaftarRi['roomDesc'] ?? '';
        $this->newPindah['dariBedNo'] = $this->dataDaftarRi['bedNo'] ?? '';
        $this->resetValidation();
        $this->incrementVersion('modal-form-pindah-ri');
    }

    /** Cek apakah entry sudah final (kedua TTD ada) */
    public function isEntryLocked(array $entry): bool
    {
        return !empty($entry['petugasPengirim']) && !empty($entry['petugasPenerima']);
    }

    /* ===============================
     | LOV ROOM TUJUAN — listener
     =============================== */
    #[On('lov.selected.pindahRiKeRuang')]
    public function onRoomSelected(string $target, array $payload): void
    {
        if ($this->isFormLocked) {
            return;
        }

        $this->newPindah['keRoomId'] = $payload['room_id'] ?? '';
        $this->newPindah['keRoomDesc'] = $payload['room_name'] ?? '';
        $this->newPindah['keBedNo'] = $payload['bed_no'] ?? '';
    }

    #[On('lov.cleared.pindahRiKeRuang')]
    public function onRoomCleared(string $target): void
    {
        if ($this->isFormLocked) {
            return;
        }

        $this->newPindah['keRoomId'] = '';
        $this->newPindah['keRoomDesc'] = '';
        $this->newPindah['keBedNo'] = '';
    }

    /* ===============================
     | VALIDATION
     | Stage 1 (pengirim): tglPindah, ke ruang, alasan wajib
     | Stage 2 (penerima): tambahan tglTerima wajib
     =============================== */
    protected function rules(): array
    {
        $rules = [
            'newPindah.tglPindah' => 'required|date_format:d/m/Y H:i:s',
            'newPindah.keRoomId' => 'required|string',
            'newPindah.keRoomDesc' => 'required|string|max:200',
            'newPindah.alasanPindah' => 'required|string|max:500',
        ];

        // Kalau penerima sudah TTD, tglTerima wajib
        if (!empty($this->newPindah['petugasPenerima'])) {
            $rules['newPindah.tglTerima'] = 'required|date_format:d/m/Y H:i:s';
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'date_format' => ':attribute harus format dd/mm/yyyy HH:ii:ss.',
            'max' => ':attribute maksimal :max karakter.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'newPindah.tglPindah' => 'Tanggal pindah',
            'newPindah.tglTerima' => 'Tanggal terima',
            'newPindah.keRoomId' => 'Ruang tujuan',
            'newPindah.keRoomDesc' => 'Ruang tujuan',
            'newPindah.alasanPindah' => 'Alasan pindah',
        ];
    }

    /* ===============================
     | TANGGAL — set sekarang
     =============================== */
    public function setTglPindahSekarang(): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->newPindah['tglPindah'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    public function setTglTerimaSekarang(): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->newPindah['tglTerima'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /* ===============================
     | TTD PETUGAS PENGIRIM & PENERIMA
     =============================== */
    public function setPetugasPengirim(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        if (!empty($this->newPindah['petugasPengirim'])) {
            $this->dispatch('toast', type: 'warning', message: 'Petugas Pengirim sudah TTD.');
            return;
        }

        $this->newPindah['petugasPengirim'] = auth()->user()->myuser_name ?? '';
        $this->newPindah['petugasPengirimCode'] = auth()->user()->myuser_code ?? '';
        $this->newPindah['petugasPengirimDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        // Auto-set tglPindah kalau belum diisi
        if (empty($this->newPindah['tglPindah'])) {
            $this->newPindah['tglPindah'] = $this->newPindah['petugasPengirimDate'];
        }
    }

    public function setPetugasPenerima(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        if (empty($this->newPindah['petugasPengirim'])) {
            $this->dispatch('toast', type: 'error', message: 'Petugas Pengirim harus TTD terlebih dahulu.');
            return;
        }
        if (!empty($this->newPindah['petugasPenerima'])) {
            $this->dispatch('toast', type: 'warning', message: 'Petugas Penerima sudah TTD.');
            return;
        }

        $this->newPindah['petugasPenerima'] = auth()->user()->myuser_name ?? '';
        $this->newPindah['petugasPenerimaCode'] = auth()->user()->myuser_code ?? '';
        $this->newPindah['petugasPenerimaDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        // Auto-set tglTerima
        if (empty($this->newPindah['tglTerima'])) {
            $this->newPindah['tglTerima'] = $this->newPindah['petugasPenerimaDate'];
        }
    }

    /* ===============================
     | SAVE — tambah baru / update existing
     =============================== */
    public function save(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }

        $this->validate();

        if (empty($this->newPindah['petugasPengirim'])) {
            $this->dispatch('toast', type: 'error', message: 'Petugas Pengirim belum TTD.');
            return;
        }

        try {
            DB::transaction(function () {
                $this->lockRIRow($this->riHdrNo);

                $data = $this->findDataRI($this->riHdrNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data RI tidak ditemukan, simpan dibatalkan.');
                }

                if (!isset($data['formPindahAntarRuangRI']) || !is_array($data['formPindahAntarRuangRI'])) {
                    $data['formPindahAntarRuangRI'] = [];
                }

                if ($this->editingTglPindah !== null) {
                    // UPDATE existing entry
                    $found = false;
                    foreach ($data['formPindahAntarRuangRI'] as $idx => $row) {
                        if (($row['tglPindah'] ?? '') === $this->editingTglPindah) {
                            // Cegah override entry yang sudah locked
                            if ($this->isEntryLocked($row)) {
                                throw new \RuntimeException('Entry sudah final, tidak dapat diubah.');
                            }
                            $data['formPindahAntarRuangRI'][$idx] = $this->newPindah;
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        throw new \RuntimeException('Entry yang akan diupdate tidak ditemukan.');
                    }
                } else {
                    // ADD new entry
                    $data['formPindahAntarRuangRI'][] = $this->newPindah;
                }

                $this->updateJsonRI((int) $this->riHdrNo, $data);
                $this->dataDaftarRi = $data;
                $this->listPindah = $data['formPindahAntarRuangRI'];
            });

            $this->incrementVersion('modal-form-pindah-ri');

            $isFinal = !empty($this->newPindah['petugasPenerima']);
            $msg = $isFinal ? 'Form Pindah berhasil diselesaikan (kedua TTD lengkap).' : 'Form Pindah disimpan — menunggu TTD Penerima.';
            $this->dispatch('toast', type: 'success', message: $msg);

            $this->editingTglPindah = null;
            $this->resetNewPindah();
            $this->newPindah['dariRoomId'] = $this->dataDaftarRi['roomId'] ?? '';
            $this->newPindah['dariRoomDesc'] = $this->dataDaftarRi['roomDesc'] ?? '';
            $this->newPindah['dariBedNo'] = $this->dataDaftarRi['bedNo'] ?? '';
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | HAPUS — hanya entry yang BELUM final
     =============================== */
    public function hapus(string $tglPindah): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }

        $entry = collect($this->listPindah)->firstWhere('tglPindah', $tglPindah);
        if ($entry && $this->isEntryLocked($entry)) {
            $this->dispatch('toast', type: 'error', message: 'Entry sudah final (kedua TTD), tidak dapat dihapus.');
            return;
        }

        try {
            DB::transaction(function () use ($tglPindah) {
                $this->lockRIRow($this->riHdrNo);

                $data = $this->findDataRI($this->riHdrNo);
                if (empty($data) || !isset($data['formPindahAntarRuangRI'])) {
                    throw new \RuntimeException('Data tidak ditemukan.');
                }

                $data['formPindahAntarRuangRI'] = collect($data['formPindahAntarRuangRI'])
                    ->reject(fn($item) => ($item['tglPindah'] ?? '') === $tglPindah)
                    ->values()
                    ->toArray();

                $this->updateJsonRI((int) $this->riHdrNo, $data);
                $this->dataDaftarRi = $data;
                $this->listPindah = $data['formPindahAntarRuangRI'];
            });

            $this->incrementVersion('modal-form-pindah-ri');
            $this->dispatch('toast', type: 'success', message: 'Riwayat pindah berhasil dihapus.');
            if ($this->editingTglPindah === $tglPindah) {
                $this->batalEdit();
            }
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    /* ===============================
     | RESET HELPERS
     =============================== */
    private function resetNewPindah(): void
    {
        $this->newPindah = [
            'tglPindah' => '',
            'tglTerima' => '',
            'dariRoomId' => $this->dataDaftarRi['roomId'] ?? '',
            'dariRoomDesc' => $this->dataDaftarRi['roomDesc'] ?? '',
            'dariBedNo' => $this->dataDaftarRi['bedNo'] ?? '',
            'keRoomId' => '',
            'keRoomDesc' => '',
            'keBedNo' => '',
            'alasanPindah' => '',
            'kondisiKirim' => [
                'sistolik' => '',
                'diastolik' => '',
                'frekuensiNafas' => '',
                'frekuensiNadi' => '',
                'suhu' => '',
                'spo2' => '',
                'gcs' => '',
                'keadaanPasien' => '',
            ],
            'kondisiTerima' => [
                'sistolik' => '',
                'diastolik' => '',
                'frekuensiNafas' => '',
                'frekuensiNadi' => '',
                'suhu' => '',
                'spo2' => '',
                'gcs' => '',
                'keadaanPasien' => '',
            ],
            'petugasPengirim' => '',
            'petugasPengirimCode' => '',
            'petugasPengirimDate' => '',
            'petugasPenerima' => '',
            'petugasPenerimaCode' => '',
            'petugasPenerimaDate' => '',
        ];
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->dataDaftarRi = [];
        $this->listPindah = [];
        $this->editingTglPindah = null;
        $this->resetNewPindah();
    }
};
?>

<div>
    {{-- ══ SUMMARY CARD (inline di tab) ══ --}}
    @php
        $pindahCount = count($listPindah ?? []);
        $inTransitCount = collect($listPindah ?? [])
            ->filter(fn($r) => !empty($r['petugasPengirim']) && empty($r['petugasPenerima']))
            ->count();
    @endphp

    <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex-1 space-y-3">
                <div class="flex items-center gap-2 flex-wrap">
                    <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200">
                        Form Pindah Antar Ruang
                    </h3>
                    @if ($pindahCount > 0)
                        <x-badge variant="success">{{ $pindahCount }} riwayat</x-badge>
                    @else
                        <x-badge variant="warning">Belum ada</x-badge>
                    @endif
                    @if ($inTransitCount > 0)
                        <x-badge variant="warning">{{ $inTransitCount }} dalam transit</x-badge>
                    @endif
                </div>

                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Serah-terima pasien antar ruang. Petugas Pengirim TTD dulu — entry tetap dapat dilanjutkan
                    Petugas Penerima sampai keduanya TTD (terkunci).
                </p>

                @if ($pindahCount > 0)
                    <ul class="space-y-1 text-sm text-gray-600 dark:text-gray-300 list-disc pl-5">
                        @foreach (array_slice($listPindah, -3) as $row)
                            <li>
                                <span class="font-medium">{{ $row['dariRoomDesc'] ?? '-' }}</span>
                                <span class="mx-1 text-xs text-gray-400">→</span>
                                <span class="font-medium">{{ $row['keRoomDesc'] ?? '-' }}</span>
                                @if (!empty($row['tglPindah']))
                                    <span class="text-xs text-gray-400">— {{ $row['tglPindah'] }}</span>
                                @endif
                                @if (empty($row['petugasPenerima']))
                                    <x-badge variant="warning" class="ml-1">Transit</x-badge>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="flex shrink-0">
                <x-primary-button type="button" wire:click="openModal" wire:loading.attr="disabled"
                    wire:target="openModal" :disabled="$disabled || !$riHdrNo" class="gap-2">
                    <span wire:loading.remove wire:target="openModal" class="flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                        Buka Form Pindah
                    </span>
                    <span wire:loading wire:target="openModal" class="flex items-center gap-1.5">
                        <x-loading class="w-4 h-4" /> Memuat...
                    </span>
                </x-primary-button>
            </div>
        </div>
    </div>

    {{-- ══ MODAL FORM ══ --}}
    <x-modal name="rm-form-pindah-ri-{{ $riHdrNo ?? 'init' }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal-form-pindah-ri', [$riHdrNo ?? 'new']) }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div
                                class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                                <svg class="w-6 h-6 text-brand-green dark:text-brand-lime" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                </svg>
                            </div>

                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                    Form Pindah Antar Ruang
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Pengirim TTD &rarr; Penerima lanjutkan TTD &rarr; Final (terkunci)
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2 mt-3">
                            <x-badge variant="info">Rawat Inap</x-badge>
                            @if ($pindahCount > 0)
                                <x-badge variant="success">{{ $pindahCount }} tersimpan</x-badge>
                            @endif
                            @if ($inTransitCount > 0)
                                <x-badge variant="warning">{{ $inTransitCount }} transit</x-badge>
                            @endif
                            @if ($isFormLocked)
                                <x-badge variant="danger">Read Only</x-badge>
                            @endif
                            @if ($editingTglPindah !== null)
                                <x-badge variant="warning">Mode: Lanjutkan Entry</x-badge>
                            @endif
                        </div>
                    </div>

                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <span class="sr-only">Close</span>
                        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20">
                <div class="max-w-full mx-auto space-y-4">

                    <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                        wire:key="form-pindah-ri-display-pasien-{{ $riHdrNo ?? 'init' }}" />

                    {{-- ══ PANDUAN PENGISIAN (collapsible) ══ --}}
                    <div x-data="{ open: true }"
                        class="overflow-hidden border rounded-2xl bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-700">
                        <button type="button" @click="open = !open"
                            class="flex items-center justify-between w-full px-4 py-3 text-sm font-semibold text-blue-900 transition-colors hover:bg-blue-100 dark:text-blue-200 dark:hover:bg-blue-900/30">
                            <span class="flex items-center gap-2">
                                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Cara Pengisian Form Pindah
                            </span>
                            <svg class="w-4 h-4 transition-transform" :class="open ? 'rotate-180' : ''"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <div x-show="open" x-collapse class="px-4 pb-4 space-y-3 text-sm text-blue-900 dark:text-blue-200">
                            <p>
                                Form ini diisi <strong>dua tahap</strong> — mirip pencatatan oksigen (jam mulai &amp; jam stop):
                                Pengirim isi &amp; TTD dulu, lalu Penerima melanjutkan setelah pasien sampai di
                                ruang tujuan.
                            </p>

                            <ol class="space-y-2 ml-6 list-decimal">
                                <li>
                                    <strong>Petugas Pengirim</strong> (perawat ruang asal): isi
                                    <em>Tanggal Pindah</em>, <em>Ke Ruangan</em>, <em>Alasan</em>,
                                    <em>Kondisi Saat Dikirim</em> (TTV), lalu klik <strong>TTD Pengirim</strong> →
                                    <strong>Simpan</strong>.
                                    <div class="text-xs text-blue-700 dark:text-blue-300 mt-0.5">
                                        Entry masuk daftar bawah dengan status
                                        <span class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-medium bg-amber-100 text-amber-800 rounded">
                                            Transit
                                        </span>
                                        — boleh ditutup; data tersimpan.
                                    </div>
                                </li>
                                <li>
                                    <strong>Petugas Penerima</strong> (perawat ruang tujuan): buka modal ini &amp;
                                    klik <strong>Lanjutkan</strong> pada entry Transit → form ter-load dengan
                                    data pengirim.
                                </li>
                                <li>
                                    Isi <em>Kondisi Saat Diterima</em> (TTV), klik <strong>TTD Penerima</strong>,
                                    lalu <strong>Update Entry</strong>.
                                </li>
                                <li>
                                    Setelah <strong>kedua TTD lengkap</strong>, status berubah jadi
                                    <span class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-medium bg-emerald-100 text-emerald-800 rounded">
                                        Selesai
                                    </span>
                                    dan entry <strong>terkunci permanen</strong> (tidak bisa diedit / dihapus).
                                </li>
                            </ol>

                            <p class="text-xs text-blue-700 dark:text-blue-300">
                                Catatan: tanggal/jam pindah &amp; terima auto-isi saat TTD jika kosong. Kalau pengirim
                                belum TTD, section &amp; tombol Penerima dikunci agar urutan tetap benar.
                            </p>
                        </div>
                    </div>

                    @if ($editingTglPindah !== null)
                        <div
                            class="flex items-center justify-between gap-3 px-4 py-3 text-sm border rounded-xl bg-amber-50 border-amber-200 text-amber-800 dark:bg-amber-900/20 dark:border-amber-700 dark:text-amber-200">
                            <div class="flex items-start gap-2">
                                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                                <div>
                                    <p class="font-semibold">Melanjutkan entry transit</p>
                                    <p class="mt-0.5">
                                        Entry tgl <strong>{{ $editingTglPindah }}</strong> — silakan TTD sebagai
                                        Petugas Penerima atau revisi data sebelum simpan akhir.
                                    </p>
                                </div>
                            </div>
                            <x-secondary-button type="button" wire:click="batalEdit" class="shrink-0">
                                Batal
                            </x-secondary-button>
                        </div>
                    @endif

                    <div
                        class="p-6 space-y-6 bg-white border border-gray-200 shadow-sm sm:p-8 rounded-2xl dark:bg-gray-900 dark:border-gray-700">

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

                        {{-- ══ ASAL & TUJUAN ══ --}}
                        <section class="space-y-4">
                            <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200">
                                Asal &amp; Tujuan
                            </h3>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <x-input-label value="Tanggal Pindah (kirim) *" class="mb-1" />
                                    <div class="flex gap-2">
                                        <x-text-input wire:model.live="newPindah.tglPindah"
                                            placeholder="dd/mm/yyyy hh:ii:ss" :disabled="$isFormLocked" class="flex-1" />
                                        @if (!$isFormLocked)
                                            <x-primary-button type="button" wire:click="setTglPindahSekarang">
                                                Sekarang
                                            </x-primary-button>
                                        @endif
                                    </div>
                                    <x-input-error :messages="$errors->get('newPindah.tglPindah')" class="mt-1" />
                                </div>

                                <div>
                                    <x-input-label value="Tanggal Diterima"
                                        class="mb-1 {{ empty($newPindah['petugasPengirim']) ? 'text-gray-400' : '' }}" />
                                    <div class="flex gap-2">
                                        <x-text-input wire:model.live="newPindah.tglTerima"
                                            placeholder="dd/mm/yyyy hh:ii:ss"
                                            :disabled="$isFormLocked || empty($newPindah['petugasPengirim'])"
                                            class="flex-1" />
                                        @if (!$isFormLocked && !empty($newPindah['petugasPengirim']))
                                            <x-primary-button type="button" wire:click="setTglTerimaSekarang">
                                                Sekarang
                                            </x-primary-button>
                                        @endif
                                    </div>
                                    <x-input-error :messages="$errors->get('newPindah.tglTerima')" class="mt-1" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <x-input-label value="Dari Ruangan / Bed (saat ini)" class="mb-1" />
                                    <div
                                        class="px-3 py-2 text-sm border border-gray-200 bg-gray-50 rounded-md dark:bg-gray-800 dark:border-gray-700">
                                        <span class="font-semibold text-gray-800 dark:text-gray-200">
                                            {{ $newPindah['dariRoomDesc'] ?? '-' }}
                                        </span>
                                        @if (!empty($newPindah['dariBedNo']))
                                            <span class="text-gray-500">/ Bed {{ $newPindah['dariBedNo'] }}</span>
                                        @endif
                                    </div>
                                </div>

                                <div>
                                    <x-input-label value="Ke Ruangan / Bed *" class="mb-1" />
                                    @if (!$isFormLocked)
                                        <livewire:lov.room.lov-room target="pindahRiKeRuang" label=""
                                            :initialRoomId="$newPindah['keRoomId'] ?? null"
                                            wire:key="lov-room-pindah-ri-{{ $riHdrNo ?? 'init' }}-{{ $editingTglPindah ?? 'new' }}-{{ $renderVersions['modal-form-pindah-ri'] ?? 0 }}" />
                                    @elseif (!empty($newPindah['keRoomDesc']))
                                        <div
                                            class="px-3 py-2 text-sm border border-gray-200 bg-gray-50 rounded-md dark:bg-gray-800 dark:border-gray-700">
                                            <span class="font-semibold text-gray-800 dark:text-gray-200">
                                                {{ $newPindah['keRoomDesc'] }}
                                            </span>
                                            @if (!empty($newPindah['keBedNo']))
                                                <span class="text-gray-500">/ Bed {{ $newPindah['keBedNo'] }}</span>
                                            @endif
                                        </div>
                                    @else
                                        <p class="text-sm italic text-gray-400">Belum dipilih.</p>
                                    @endif
                                    <x-input-error :messages="$errors->get('newPindah.keRoomId')" class="mt-1" />
                                    <x-input-error :messages="$errors->get('newPindah.keRoomDesc')" class="mt-1" />
                                </div>
                            </div>

                            <div>
                                <x-input-label value="Alasan Pindah *" class="mb-1" />
                                <x-textarea wire:model.live="newPindah.alasanPindah" rows="2"
                                    placeholder="Mis. perubahan kelas, kebutuhan ruang isolasi, permintaan keluarga..."
                                    :disabled="$isFormLocked" />
                                <x-input-error :messages="$errors->get('newPindah.alasanPindah')" class="mt-1" />
                            </div>
                        </section>

                        {{-- ══ KONDISI SAAT KIRIM (PENGIRIM) ══ --}}
                        <section class="pt-6 space-y-4 border-t border-gray-200 dark:border-gray-700">
                            <div class="flex items-center justify-between gap-2 flex-wrap">
                                <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200">
                                    Kondisi Saat Dikirim
                                </h3>
                                <span class="text-xs text-gray-500">Diisi Petugas Pengirim</span>
                            </div>

                            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                                <div>
                                    <x-input-label value="TD Sistolik" class="mb-1" />
                                    <x-text-input wire:model.live="newPindah.kondisiKirim.sistolik" type="number"
                                        placeholder="mmHg" :disabled="$isFormLocked" class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="TD Diastolik" class="mb-1" />
                                    <x-text-input wire:model.live="newPindah.kondisiKirim.diastolik" type="number"
                                        placeholder="mmHg" :disabled="$isFormLocked" class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Nadi" class="mb-1" />
                                    <x-text-input wire:model.live="newPindah.kondisiKirim.frekuensiNadi"
                                        type="number" placeholder="x/menit" :disabled="$isFormLocked"
                                        class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Nafas (RR)" class="mb-1" />
                                    <x-text-input wire:model.live="newPindah.kondisiKirim.frekuensiNafas"
                                        type="number" placeholder="x/menit" :disabled="$isFormLocked"
                                        class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Suhu" class="mb-1" />
                                    <x-text-input wire:model.live="newPindah.kondisiKirim.suhu" type="number"
                                        step="0.1" placeholder="°C" :disabled="$isFormLocked" class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="SpO2" class="mb-1" />
                                    <x-text-input wire:model.live="newPindah.kondisiKirim.spo2" type="number"
                                        placeholder="%" :disabled="$isFormLocked" class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="GCS" class="mb-1" />
                                    <x-text-input wire:model.live="newPindah.kondisiKirim.gcs"
                                        placeholder="E_M_V_" :disabled="$isFormLocked" class="w-full" />
                                </div>
                            </div>

                            <div>
                                <x-input-label value="Keadaan Umum (saat dikirim)" class="mb-1" />
                                <x-textarea wire:model.live="newPindah.kondisiKirim.keadaanPasien" rows="2"
                                    placeholder="Mis. sadar, lemah, terpasang infus RL..." :disabled="$isFormLocked" />
                            </div>
                        </section>

                        {{-- ══ KONDISI SAAT TERIMA (PENERIMA) ══ --}}
                        @php $disableTerima = $isFormLocked || empty($newPindah['petugasPengirim']); @endphp
                        <section class="pt-6 space-y-4 border-t border-gray-200 dark:border-gray-700">
                            <div class="flex items-center justify-between gap-2 flex-wrap">
                                <h3
                                    class="text-base font-semibold {{ $disableTerima ? 'text-gray-400' : 'text-gray-800 dark:text-gray-200' }}">
                                    Kondisi Saat Diterima
                                </h3>
                                <span class="text-xs text-gray-500">
                                    @if ($disableTerima)
                                        Menunggu TTD Pengirim
                                    @else
                                        Diisi Petugas Penerima
                                    @endif
                                </span>
                            </div>

                            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                                <div>
                                    <x-input-label value="TD Sistolik" class="mb-1" />
                                    <x-text-input wire:model.live="newPindah.kondisiTerima.sistolik" type="number"
                                        placeholder="mmHg" :disabled="$disableTerima" class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="TD Diastolik" class="mb-1" />
                                    <x-text-input wire:model.live="newPindah.kondisiTerima.diastolik" type="number"
                                        placeholder="mmHg" :disabled="$disableTerima" class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Nadi" class="mb-1" />
                                    <x-text-input wire:model.live="newPindah.kondisiTerima.frekuensiNadi"
                                        type="number" placeholder="x/menit" :disabled="$disableTerima"
                                        class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Nafas (RR)" class="mb-1" />
                                    <x-text-input wire:model.live="newPindah.kondisiTerima.frekuensiNafas"
                                        type="number" placeholder="x/menit" :disabled="$disableTerima"
                                        class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Suhu" class="mb-1" />
                                    <x-text-input wire:model.live="newPindah.kondisiTerima.suhu" type="number"
                                        step="0.1" placeholder="°C" :disabled="$disableTerima" class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="SpO2" class="mb-1" />
                                    <x-text-input wire:model.live="newPindah.kondisiTerima.spo2" type="number"
                                        placeholder="%" :disabled="$disableTerima" class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="GCS" class="mb-1" />
                                    <x-text-input wire:model.live="newPindah.kondisiTerima.gcs"
                                        placeholder="E_M_V_" :disabled="$disableTerima" class="w-full" />
                                </div>
                            </div>

                            <div>
                                <x-input-label value="Keadaan Umum (saat diterima)" class="mb-1" />
                                <x-textarea wire:model.live="newPindah.kondisiTerima.keadaanPasien" rows="2"
                                    placeholder="Diisi setelah pasien diterima di ruang tujuan..."
                                    :disabled="$disableTerima" />
                            </div>
                        </section>

                        {{-- ══ TANDA TANGAN ══ --}}
                        <section class="pt-6 space-y-4 border-t border-gray-200 dark:border-gray-700">
                            <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200">
                                Tanda Tangan
                            </h3>

                            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                {{-- Pengirim --}}
                                <div class="flex flex-col">
                                    <div
                                        class="mb-2 text-xs font-semibold tracking-wide text-center text-gray-500 uppercase dark:text-gray-400">
                                        Petugas Pengirim
                                    </div>
                                    @if (empty($newPindah['petugasPengirim']))
                                        @if (!$isFormLocked)
                                            <div
                                                class="flex items-center justify-center flex-1 p-6 border-2 border-gray-300 border-dashed rounded-xl dark:border-gray-700">
                                                <x-primary-button wire:click.prevent="setPetugasPengirim"
                                                    wire:loading.attr="disabled" wire:target="setPetugasPengirim"
                                                    class="gap-2">
                                                    <span wire:loading.remove wire:target="setPetugasPengirim"
                                                        class="flex items-center gap-1.5">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 012.828 2.828L11.828 15.828a4 4 0 01-2.828 1.172H7v-2a4 4 0 011.172-2.828z" />
                                                        </svg>
                                                        TTD Pengirim
                                                    </span>
                                                    <span wire:loading wire:target="setPetugasPengirim">
                                                        <x-loading class="w-4 h-4" /> Menyimpan...
                                                    </span>
                                                </x-primary-button>
                                            </div>
                                        @else
                                            <p class="py-8 text-sm italic text-center text-gray-400">Belum
                                                ditandatangani.</p>
                                        @endif
                                    @else
                                        <div
                                            class="flex flex-col items-center justify-center flex-1 p-4 border border-emerald-200 bg-emerald-50 rounded-xl dark:bg-emerald-900/20 dark:border-emerald-700">
                                            <div class="font-semibold text-center text-gray-800 dark:text-gray-200">
                                                {{ $newPindah['petugasPengirim'] }}
                                            </div>
                                            @if (!empty($newPindah['petugasPengirimCode']))
                                                <div class="text-xs text-gray-500 mt-0.5">
                                                    Kode: {{ $newPindah['petugasPengirimCode'] }}
                                                </div>
                                            @endif
                                            <div class="mt-1 text-xs text-gray-500">
                                                {{ $newPindah['petugasPengirimDate'] ?? '-' }}
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                {{-- Penerima --}}
                                <div class="flex flex-col">
                                    <div
                                        class="mb-2 text-xs font-semibold tracking-wide text-center {{ empty($newPindah['petugasPengirim']) ? 'text-gray-300' : 'text-gray-500' }} uppercase dark:text-gray-400">
                                        Petugas Penerima
                                    </div>
                                    @if (empty($newPindah['petugasPenerima']))
                                        @if (!$isFormLocked && !empty($newPindah['petugasPengirim']))
                                            <div
                                                class="flex items-center justify-center flex-1 p-6 border-2 border-gray-300 border-dashed rounded-xl dark:border-gray-700">
                                                <x-primary-button wire:click.prevent="setPetugasPenerima"
                                                    wire:loading.attr="disabled" wire:target="setPetugasPenerima"
                                                    class="gap-2">
                                                    <span wire:loading.remove wire:target="setPetugasPenerima"
                                                        class="flex items-center gap-1.5">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 012.828 2.828L11.828 15.828a4 4 0 01-2.828 1.172H7v-2a4 4 0 011.172-2.828z" />
                                                        </svg>
                                                        TTD Penerima
                                                    </span>
                                                    <span wire:loading wire:target="setPetugasPenerima">
                                                        <x-loading class="w-4 h-4" /> Menyimpan...
                                                    </span>
                                                </x-primary-button>
                                            </div>
                                        @else
                                            <p class="py-8 text-sm italic text-center text-gray-400">
                                                @if (empty($newPindah['petugasPengirim']))
                                                    Menunggu TTD Pengirim.
                                                @else
                                                    Belum ditandatangani.
                                                @endif
                                            </p>
                                        @endif
                                    @else
                                        <div
                                            class="flex flex-col items-center justify-center flex-1 p-4 border border-emerald-200 bg-emerald-50 rounded-xl dark:bg-emerald-900/20 dark:border-emerald-700">
                                            <div class="font-semibold text-center text-gray-800 dark:text-gray-200">
                                                {{ $newPindah['petugasPenerima'] }}
                                            </div>
                                            @if (!empty($newPindah['petugasPenerimaCode']))
                                                <div class="text-xs text-gray-500 mt-0.5">
                                                    Kode: {{ $newPindah['petugasPenerimaCode'] }}
                                                </div>
                                            @endif
                                            <div class="mt-1 text-xs text-gray-500">
                                                {{ $newPindah['petugasPenerimaDate'] ?? '-' }}
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </section>

                    </div>

                    {{-- ══ DAFTAR RIWAYAT PINDAH ══ --}}
                    @if (count($listPindah) > 0)
                        <div
                            class="p-6 space-y-4 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                            <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200">
                                Riwayat Pindah Tersimpan
                            </h3>
                            <div class="overflow-x-auto">
                                <table
                                    class="min-w-full text-sm border border-gray-200 rounded-lg dark:border-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-800">
                                        <tr class="text-left text-gray-600 dark:text-gray-300">
                                            <th class="px-3 py-2 border-b">Status</th>
                                            <th class="px-3 py-2 border-b">Tgl Kirim</th>
                                            <th class="px-3 py-2 border-b">Dari → Ke</th>
                                            <th class="px-3 py-2 border-b">Pengirim</th>
                                            <th class="px-3 py-2 border-b">Penerima</th>
                                            <th class="px-3 py-2 border-b text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($listPindah as $row)
                                            @php
                                                $rowLocked =
                                                    !empty($row['petugasPengirim']) &&
                                                    !empty($row['petugasPenerima']);
                                            @endphp
                                            <tr
                                                class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">
                                                <td class="px-3 py-2">
                                                    @if ($rowLocked)
                                                        <x-badge variant="success">Selesai</x-badge>
                                                    @else
                                                        <x-badge variant="warning">Transit</x-badge>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-2 text-gray-600 dark:text-gray-400">
                                                    {{ $row['tglPindah'] ?? '-' }}
                                                </td>
                                                <td class="px-3 py-2">
                                                    <div class="font-medium">
                                                        {{ $row['dariRoomDesc'] ?? '-' }}
                                                        <span class="text-gray-400">→</span>
                                                        {{ $row['keRoomDesc'] ?? '-' }}
                                                    </div>
                                                    @if (!empty($row['alasanPindah']))
                                                        <div class="text-xs text-gray-500 mt-0.5">
                                                            {{ Str::limit($row['alasanPindah'], 60) }}
                                                        </div>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-2 text-gray-600 dark:text-gray-400">
                                                    {{ $row['petugasPengirim'] ?? '-' }}
                                                    @if (!empty($row['petugasPengirimDate']))
                                                        <div class="text-xs text-gray-400 mt-0.5">
                                                            {{ $row['petugasPengirimDate'] }}
                                                        </div>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-2 text-gray-600 dark:text-gray-400">
                                                    {{ $row['petugasPenerima'] ?? '—' }}
                                                    @if (!empty($row['petugasPenerimaDate']))
                                                        <div class="text-xs text-gray-400 mt-0.5">
                                                            {{ $row['petugasPenerimaDate'] }}
                                                        </div>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-2 text-center space-x-1 whitespace-nowrap">
                                                    @if (!$rowLocked && !$isFormLocked)
                                                        <x-secondary-button
                                                            wire:click="editPindah('{{ $row['tglPindah'] }}')"
                                                            class="text-xs py-1 px-2">
                                                            Lanjutkan
                                                        </x-secondary-button>
                                                        <x-confirm-button variant="danger"
                                                            :action="'hapus(\'' . $row['tglPindah'] . '\')'"
                                                            title="Hapus Riwayat Pindah"
                                                            message="Yakin hapus catatan pindah ini?"
                                                            confirmText="Ya, hapus" cancelText="Batal"
                                                            class="text-xs py-1 px-2">
                                                            Hapus
                                                        </x-confirm-button>
                                                    @else
                                                        <span class="text-xs text-gray-400 italic">Terkunci</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-center justify-end gap-3">
                    <x-secondary-button wire:click="closeModal">Tutup</x-secondary-button>

                    @if ($riHdrNo && !$isFormLocked)
                        <x-primary-button wire:click.prevent="save" wire:loading.attr="disabled" wire:target="save"
                            class="gap-2 min-w-[200px] justify-center">
                            <span wire:loading.remove wire:target="save">
                                @if ($editingTglPindah !== null)
                                    Update Entry Pindah
                                @else
                                    Simpan Pindah Pasien
                                @endif
                            </span>
                            <span wire:loading wire:target="save"><x-loading class="w-4 h-4" /> Menyimpan...</span>
                        </x-primary-button>
                    @endif
                </div>
            </div>

        </div>
    </x-modal>
</div>
