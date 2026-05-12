<?php
// resources/views/pages/transaksi/ri/emr-ri/edukasi/rm-edukasi-pasien-ri-actions.blade.php

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new class extends Component {
    use EmrRITrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?string $riHdrNo = null;
    public bool $disabled = false;
    public array $dataDaftarRi = [];

    public array $formEntryEdukasi = [
        'tglEdukasi' => '',
        'petugasEdukasi' => '',
        'petugasEdukasiCode' => '',
        'sasaranEdukasi' => '',
        'hubunganSasaranEdukasidgnPasien' => '',
        'sasaranEdukasiSignature' => '',
        'edukasi' => [
            'kategoriEdukasi' => [],
            'keteranganEdukasi' => '',
            'statusEdukasi' => '',
            'reEdukasi' => ['perlu' => false, 'tglReEdukasi' => '', 'petugasReEdukasi' => ''],
        ],
    ];

    public array $edukasiOptions = ['Pengobatan', 'Rencana Perawatan', 'Diagnosa Medis', 'Pencegahan Infeksi', 'Diet dan Nutrisi', 'Perawatan Luka', 'Aktivitas Fisik', 'Perawatan di Rumah', 'Manajemen Nyeri', 'Dukungan Emosional dan Spiritual', 'Lain-lain'];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-edukasi-ri'];

    public function mount(?string $riHdrNo = null, bool $disabled = false): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-edukasi-ri']);

        if ($this->riHdrNo) {
            $data = $this->findDataRI($this->riHdrNo);
            if ($data) {
                $this->dataDaftarRi = $data;
                $this->dataDaftarRi['edukasiPasien'] ??= [];
                $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $disabled;
            }
        }
    }

    public function setTglEdukasi(): void
    {
        $this->formEntryEdukasi['tglEdukasi'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    public function addEdukasiPasien(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }

        $this->formEntryEdukasi['petugasEdukasi'] = auth()->user()->myuser_name;
        $this->formEntryEdukasi['petugasEdukasiCode'] = auth()->user()->myuser_code;

        $this->validate(
            [
                'formEntryEdukasi.tglEdukasi' => 'required|date_format:d/m/Y H:i:s',
                'formEntryEdukasi.sasaranEdukasi' => 'required|string|max:100',
                'formEntryEdukasi.hubunganSasaranEdukasidgnPasien' => 'required|string|max:100',
                'formEntryEdukasi.edukasi.kategoriEdukasi' => 'required|array|min:1',
                'formEntryEdukasi.edukasi.keteranganEdukasi' => 'required|string|max:255',
                'formEntryEdukasi.edukasi.statusEdukasi' => 'required|string|max:100',
            ],
            [
                'formEntryEdukasi.tglEdukasi.required' => 'Tanggal edukasi wajib diisi.',
                'formEntryEdukasi.sasaranEdukasi.required' => 'Sasaran edukasi wajib diisi.',
                'formEntryEdukasi.hubunganSasaranEdukasidgnPasien.required' => 'Hubungan dengan pasien wajib diisi.',
                'formEntryEdukasi.edukasi.kategoriEdukasi.required' => 'Kategori edukasi wajib dipilih.',
                'formEntryEdukasi.edukasi.kategoriEdukasi.min' => 'Pilih minimal satu kategori edukasi.',
                'formEntryEdukasi.edukasi.keteranganEdukasi.required' => 'Keterangan edukasi wajib diisi.',
                'formEntryEdukasi.edukasi.statusEdukasi.required' => 'Status edukasi wajib diisi.',
            ],
        );

        try {
            DB::transaction(function () {
                // ← trait pattern
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                $fresh['edukasiPasien'][] = $this->formEntryEdukasi;
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
            });

            $this->reset(['formEntryEdukasi']);
            $this->afterSave('Edukasi pasien berhasil disimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    public function removeEdukasiPasien(int $index): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }

        try {
            DB::transaction(function () use ($index) {
                // ← trait pattern
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                array_splice($fresh['edukasiPasien'], $index, 1);
                $fresh['edukasiPasien'] = array_values($fresh['edukasiPasien']);
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
            });

            $this->afterSave('Edukasi berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    private function afterSave(string $msg): void
    {
        $this->incrementVersion('modal-edukasi-ri');
        $this->dispatch('toast', type: 'success', message: $msg);
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->reset(['formEntryEdukasi']);
    }
};
?>

<div class="space-y-4" wire:key="{{ $this->renderKey('modal-edukasi-ri', [$riHdrNo ?? 'new']) }}">

    @if ($isFormLocked)
        <div
            class="flex items-center gap-2 px-4 py-2.5 mb-2 rounded-lg
                    bg-amber-50 border border-amber-200 text-amber-800
                    dark:bg-amber-900/20 dark:border-amber-700 dark:text-amber-300 text-sm">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
            </svg>
            Pasien sudah pulang — form dalam mode <strong>read-only</strong>.
        </div>
    @endif

    {{-- ── FORM ENTRY EDUKASI ── --}}
    @if (!$isFormLocked)
        <x-border-form title="Entry Edukasi Pasien" align="start" bgcolor="bg-gray-50">
            <div class="mt-3 space-y-3">
                <div class="flex items-end gap-3">
                    <div class="flex-1">
                        <x-input-label value="Tanggal Edukasi *" />
                        <x-text-input wire:model="formEntryEdukasi.tglEdukasi" class="w-full mt-1 font-mono" readonly
                            :error="$errors->has('formEntryEdukasi.tglEdukasi')" />
                    </div>
                    <x-secondary-button wire:click="setTglEdukasi" type="button">Sekarang</x-secondary-button>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <x-input-label value="Sasaran Edukasi *" />
                        <x-text-input wire:model="formEntryEdukasi.sasaranEdukasi" class="w-full mt-1"
                            placeholder="Nama yang menerima edukasi..." :error="$errors->has('formEntryEdukasi.sasaranEdukasi')" />
                        <x-input-error :messages="$errors->get('formEntryEdukasi.sasaranEdukasi')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label value="Hubungan dengan Pasien *" />
                        <x-select-input wire:model="formEntryEdukasi.hubunganSasaranEdukasidgnPasien"
                            class="w-full mt-1" :error="$errors->has('formEntryEdukasi.hubunganSasaranEdukasidgnPasien')">
                            <option value="">— Pilih —</option>
                            @foreach (['Pasien', 'Suami/Istri', 'Orang Tua', 'Anak', 'Saudara', 'Lainnya'] as $hub)
                                <option value="{{ $hub }}">{{ $hub }}</option>
                            @endforeach
                        </x-select-input>
                    </div>
                </div>

                <div>
                    <x-input-label value="Kategori Edukasi * (pilih satu atau lebih)" />
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($edukasiOptions as $opt)
                            <x-toggle wire:model.live="formEntryEdukasi.edukasi.kategoriEdukasi" :label="$opt"
                                :disabled="$isFormLocked" />
                        @endforeach
                    </div>
                    <x-input-error :messages="$errors->get('formEntryEdukasi.edukasi.kategoriEdukasi')" class="mt-1" />
                </div>

                <div>
                    <x-input-label value="Keterangan Edukasi *" />
                    <x-textarea wire:model="formEntryEdukasi.edukasi.keteranganEdukasi" class="w-full mt-1"
                        rows="3" placeholder="Penjelasan edukasi yang diberikan..." :error="$errors->has('formEntryEdukasi.edukasi.keteranganEdukasi')" />
                </div>

                <div>
                    <x-input-label value="Status Edukasi *" />
                    <div class="mt-2 flex gap-4">
                        @foreach (['Mengerti', 'Tidak Mengerti', 'Perlu Pengulangan'] as $st)
                            <x-radio-button :label="$st" :value="$st" name="statusEdukasi"
                                wire:model.live="formEntryEdukasi.edukasi.statusEdukasi" :disabled="$isFormLocked" />
                        @endforeach
                    </div>
                    <x-input-error :messages="$errors->get('formEntryEdukasi.edukasi.statusEdukasi')" class="mt-1" />
                </div>

                <div>
                    <x-toggle wire:model.live="formEntryEdukasi.edukasi.reEdukasi.perlu" label="Perlu Re-Edukasi"
                        :disabled="$isFormLocked" />
                </div>

                <div class="flex justify-end">
                    <x-primary-button wire:click="addEdukasiPasien" type="button">+ Simpan Edukasi</x-primary-button>
                </div>
            </div>
        </x-border-form>
    @endif

    {{-- ── LIST EDUKASI ── --}}
    <x-border-form title="Riwayat Edukasi Pasien" align="start" bgcolor="bg-gray-50">
        <div class="mt-3 space-y-3">
            @forelse ($dataDaftarRi['edukasiPasien'] ?? [] as $idx => $edu)
                <div wire:key="edu-{{ $idx }}-{{ $this->renderKey('modal-edukasi-ri') }}"
                    class="border border-gray-200 dark:border-gray-700 rounded-lg p-3 bg-white dark:bg-gray-800 text-xs space-y-2">
                    <div class="flex justify-between items-start">
                        <div>
                            <span
                                class="font-semibold text-gray-800 dark:text-gray-100">{{ $edu['sasaranEdukasi'] ?? '-' }}</span>
                            <span
                                class="text-gray-500 ml-1">({{ $edu['hubunganSasaranEdukasidgnPasien'] ?? '-' }})</span>
                            <span class="block font-mono text-gray-400">{{ $edu['tglEdukasi'] ?? '-' }} |
                                {{ $edu['petugasEdukasi'] ?? '-' }}</span>
                        </div>
                        @if (!$isFormLocked)
                            <x-icon-button variant="danger" wire:click="removeEdukasiPasien({{ $idx }})"
                                wire:confirm="Hapus data edukasi ini?" tooltip="Hapus">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </x-icon-button>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-1">
                        @foreach ($edu['edukasi']['kategoriEdukasi'] ?? [] as $kat)
                            <x-badge badgecolor="default">{{ $kat }}</x-badge>
                        @endforeach
                    </div>
                    <p class="text-gray-700 dark:text-gray-300">{{ $edu['edukasi']['keteranganEdukasi'] ?? '-' }}</p>
                    <div>Status: <x-badge
                            variant="{{ ($edu['edukasi']['statusEdukasi'] ?? '') === 'Mengerti' ? 'success' : 'warning' }}">{{ $edu['edukasi']['statusEdukasi'] ?? '-' }}</x-badge>
                        @if ($edu['edukasi']['reEdukasi']['perlu'] ?? false)
                            <x-badge variant="danger" class="ml-1">Perlu Re-Edukasi</x-badge>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-xs text-center text-gray-400 py-4">Belum ada data edukasi pasien.</p>
            @endforelse
        </div>
    </x-border-form>

</div>
