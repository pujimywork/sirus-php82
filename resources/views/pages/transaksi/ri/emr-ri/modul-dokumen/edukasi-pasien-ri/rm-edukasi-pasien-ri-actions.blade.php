<?php
// resources/views/pages/transaksi/ri/emr-ri/edukasi/rm-edukasi-pasien-ri-actions.blade.php

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\WithValidationToast\WithValidationToastTrait;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

new class extends Component {
    use EmrRITrait, MasterPasienTrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public bool $isFormLocked = false;
    public ?string $riHdrNo = null;
    public ?string $regNo = null;
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
            'materiTopikEdukasi' => '',
            'keteranganEdukasi' => '',
            'statusEdukasi' => '',
            'reEdukasi' => ['perlu' => false, 'tglReEdukasi' => '', 'petugasReEdukasi' => ''],
        ],
    ];

    public array $edukasiOptions = ['Pengobatan', 'Rencana Perawatan', 'Diagnosis Medis', 'Pencegahan Infeksi', 'Diet dan Nutrisi', 'Perawatan Luka', 'Aktivitas Fisik', 'Perawatan di Rumah', 'Manajemen Nyeri', 'Dukungan Emosional dan Spiritual', 'Lain-lain'];

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
                $this->regNo = $data['regNo'] ?? null;
                $this->formEntryEdukasi['sasaranEdukasi'] = $data['regName'] ?? '';
                $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $disabled;
            }
        }
    }

    public function setTglEdukasi(): void
    {
        $this->formEntryEdukasi['tglEdukasi'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    public function cetak(int $index)
    {
        $list = $this->dataDaftarRi['edukasiPasien'] ?? [];
        $entry = $list[$index] ?? null;
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Data edukasi tidak ditemukan.');
            return;
        }

        try {
            $identitasRs = DB::table('rsmst_identitases')->select('int_name', 'int_phone1', 'int_phone2', 'int_fax', 'int_address', 'int_city')->first();
            $pasienData = $this->findDataMasterPasien($this->regNo ?? '');
            $pasien = $pasienData['pasien'] ?? [];

            if (!empty($pasien['tglLahir'])) {
                try {
                    $pasien['thn'] = Carbon::createFromFormat('d/m/Y', $pasien['tglLahir'])->diff(Carbon::now(config('app.timezone')))->format('%y Thn, %m Bln %d Hr');
                } catch (\Throwable) {
                    $pasien['thn'] = '-';
                }
            }

            // TTD petugas edukasi (dari petugasEdukasiCode -> users.myuser_ttd_image)
            $ttdPetugasPath = null;
            $petugasCode = $entry['petugasEdukasiCode'] ?? null;
            if ($petugasCode) {
                $ttdPath = DB::table('users')->where('myuser_code', $petugasCode)->value('myuser_ttd_image');
                if (!empty($ttdPath) && file_exists(public_path('storage/' . $ttdPath))) {
                    $ttdPetugasPath = public_path('storage/' . $ttdPath);
                }
            }

            $data = array_merge($pasien, [
                'dataRi' => $this->dataDaftarRi,
                'entry' => $entry,
                'identitasRs' => $identitasRs,
                'ttdPetugasPath' => $ttdPetugasPath,
                'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
            ]);

            set_time_limit(300);

            $pdf = Pdf::loadView('pages.components.modul-dokumen.r-i.edukasi-pasien.cetak-edukasi-pasien-ri-print', ['data' => $data])->setPaper('A4');

            $this->dispatch('toast', type: 'success', message: 'Berhasil mencetak Edukasi Pasien.');
            return response()->streamDownload(fn() => print $pdf->output(), 'edukasi-pasien-ri-' . ($pasien['regNo'] ?? $this->riHdrNo) . '.pdf');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cetak: ' . $e->getMessage());
        }
    }

    public function addEdukasiPasien(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }

        $this->formEntryEdukasi['petugasEdukasi'] = auth()->user()->myuser_name;
        $this->formEntryEdukasi['petugasEdukasiCode'] = auth()->user()->myuser_code;

        $this->validateWithToast(
            [
                'formEntryEdukasi.tglEdukasi' => 'required|date_format:d/m/Y H:i:s',
                'formEntryEdukasi.sasaranEdukasi' => 'required|string|max:100',
                'formEntryEdukasi.hubunganSasaranEdukasidgnPasien' => 'required|string|max:100',
                'formEntryEdukasi.edukasi.kategoriEdukasi' => 'required|array|min:1',
                'formEntryEdukasi.edukasi.materiTopikEdukasi' => 'required|string|max:150',
                'formEntryEdukasi.edukasi.keteranganEdukasi' => 'required|string|max:255',
                'formEntryEdukasi.edukasi.statusEdukasi' => 'required|string|max:100',
            ],
            [
                'formEntryEdukasi.tglEdukasi.required' => 'Tanggal edukasi wajib diisi.',
                'formEntryEdukasi.sasaranEdukasi.required' => 'Sasaran edukasi wajib diisi.',
                'formEntryEdukasi.hubunganSasaranEdukasidgnPasien.required' => 'Hubungan dengan pasien wajib diisi.',
                'formEntryEdukasi.edukasi.kategoriEdukasi.required' => 'Kategori edukasi wajib dipilih.',
                'formEntryEdukasi.edukasi.kategoriEdukasi.min' => 'Pilih minimal satu kategori edukasi.',
                'formEntryEdukasi.edukasi.materiTopikEdukasi.required' => 'Materi / topik edukasi wajib diisi.',
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

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Tambah Edukasi Pasien — entri ' . ($this->formEntryEdukasi['tglEdukasi'] ?? '-'), 'MR');
            });

            $this->reset(['formEntryEdukasi']);
            $this->formEntryEdukasi['sasaranEdukasi'] = $this->dataDaftarRi['regName'] ?? '';
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
                $deletedRow = $fresh['edukasiPasien'][$index] ?? [];
                array_splice($fresh['edukasiPasien'], $index, 1);
                $fresh['edukasiPasien'] = array_values($fresh['edukasiPasien']);
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Edukasi Pasien — entri ' . ($deletedRow['tglEdukasi'] ?? '-'), 'MR');
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
        $this->formEntryEdukasi['sasaranEdukasi'] = $this->dataDaftarRi['regName'] ?? '';
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
        <x-border-form title="Entry Edukasi Pasien" align="start" bgcolor="bg-surface-soft">
            <div class="mt-3 space-y-3">
                <div class="flex items-end gap-3">
                    <div class="flex-1">
                        <x-input-label value="Tanggal Edukasi *" />
                        <x-text-input wire:model="formEntryEdukasi.tglEdukasi" class="w-full mt-1 font-mono" readonly
                            :error="$errors->has('formEntryEdukasi.tglEdukasi')" />
                    </div>
                    <x-now-button wire:click="setTglEdukasi" />
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
                    <div class="grid grid-cols-1 gap-2 mt-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                        @foreach ($edukasiOptions as $opt)
                            <x-toggle wire:model.live="formEntryEdukasi.edukasi.kategoriEdukasi" :label="$opt"
                                :disabled="$isFormLocked" />
                        @endforeach
                    </div>
                    <x-input-error :messages="$errors->get('formEntryEdukasi.edukasi.kategoriEdukasi')" class="mt-1" />
                </div>

                <div>
                    <x-input-label value="Materi / Topik Edukasi *" />
                    <x-text-input wire:model="formEntryEdukasi.edukasi.materiTopikEdukasi" class="w-full mt-1"
                        placeholder="Mis. Cara minum obat antihipertensi, Diet rendah garam..."
                        :error="$errors->has('formEntryEdukasi.edukasi.materiTopikEdukasi')" :disabled="$isFormLocked" />
                    <x-input-error :messages="$errors->get('formEntryEdukasi.edukasi.materiTopikEdukasi')" class="mt-1" />
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
    <x-border-form title="Riwayat Edukasi Pasien" align="start" bgcolor="bg-surface-soft">
        <div class="mt-3 overflow-x-auto bg-canvas border border-hairline rounded-xl dark:border-gray-700 dark:bg-gray-900">
            <table class="min-w-full text-sm">
                <thead class="bg-surface-soft dark:bg-gray-800">
                    <tr class="text-left">
                        <th class="px-4 py-3 text-sm font-medium text-muted dark:text-gray-400 w-12">No</th>
                        <th class="px-4 py-3 text-sm font-medium text-muted dark:text-gray-400">Tanggal</th>
                        <th class="px-4 py-3 text-sm font-medium text-muted dark:text-gray-400">Sasaran</th>
                        <th class="px-4 py-3 text-sm font-medium text-muted dark:text-gray-400">Materi</th>
                        <th class="px-4 py-3 text-sm font-medium text-muted dark:text-gray-400">Status</th>
                        <th class="px-4 py-3 text-sm font-medium text-center text-muted dark:text-gray-400 w-40">Aksi</th>
                    </tr>
                </thead>
                <tbody class="text-muted divide-y divide-hairline dark:divide-gray-700 dark:text-gray-400">
                    @forelse (array_reverse($dataDaftarRi['edukasiPasien'] ?? [], true) as $index => $edu)
                        <tr wire:key="edu-{{ $index }}-{{ $this->renderKey('modal-edukasi-ri') }}"
                            class="align-top hover:bg-surface-soft dark:hover:bg-gray-800/60">
                            <td class="px-4 py-3 font-mono text-sm text-muted dark:text-gray-300">{{ $index + 1 }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="font-mono text-muted dark:text-gray-300">{{ $edu['tglEdukasi'] ?? '-' }}</div>
                                <div class="text-xs text-muted-soft">{{ $edu['petugasEdukasi'] ?? '-' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-ink dark:text-white">{{ $edu['sasaranEdukasi'] ?? '-' }}</div>
                                <div class="text-xs text-muted-soft">{{ $edu['hubunganSasaranEdukasidgnPasien'] ?? '-' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                @if (!empty($edu['edukasi']['materiTopikEdukasi']))
                                    <div class="mb-1 font-medium text-ink dark:text-white">{{ $edu['edukasi']['materiTopikEdukasi'] }}</div>
                                @endif
                                <div class="flex flex-wrap gap-1">
                                    @forelse ($edu['edukasi']['kategoriEdukasi'] ?? [] as $kat)
                                        <x-badge variant="gray">{{ $kat }}</x-badge>
                                    @empty
                                        <span class="text-muted-soft">-</span>
                                    @endforelse
                                </div>
                                @if (!empty($edu['edukasi']['keteranganEdukasi']))
                                    <div class="mt-1 text-xs text-muted dark:text-gray-400">
                                        {{ \Illuminate\Support\Str::limit($edu['edukasi']['keteranganEdukasi'], 80) }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <x-badge variant="{{ ($edu['edukasi']['statusEdukasi'] ?? '') === 'Mengerti' ? 'success' : 'warning' }}">{{ $edu['edukasi']['statusEdukasi'] ?? '-' }}</x-badge>
                                @if ($edu['edukasi']['reEdukasi']['perlu'] ?? false)
                                    <x-badge variant="danger" class="mt-1">Re-Edukasi</x-badge>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-center gap-2">
                                    <x-secondary-button type="button" wire:click="cetak({{ $index }})"
                                        wire:loading.attr="disabled" wire:target="cetak({{ $index }})"
                                        class="px-2 py-1 text-sm">
                                        <span wire:loading.remove wire:target="cetak({{ $index }})" class="flex items-center gap-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                            </svg>
                                            Cetak
                                        </span>
                                        <span wire:loading wire:target="cetak({{ $index }})" class="flex items-center gap-1">
                                            <x-loading /> Mencetak...
                                        </span>
                                    </x-secondary-button>
                                    @if (!$isFormLocked)
                                        <x-outline-button type="button" wire:click="removeEdukasiPasien({{ $index }})"
                                            wire:confirm="Hapus data edukasi ini?" wire:loading.attr="disabled"
                                            class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300 !px-2 !py-1"
                                            title="Hapus">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </x-outline-button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-muted-soft">Belum ada data edukasi pasien.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-border-form>

</div>
