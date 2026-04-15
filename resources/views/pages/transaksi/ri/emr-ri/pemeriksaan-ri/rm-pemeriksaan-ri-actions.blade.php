<?php
// resources/views/pages/transaksi/ri/emr-ri/pemeriksaan-ri/rm-pemeriksaan-ri-actions.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\WithFileUploads;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

new class extends Component {
    use EmrRITrait, WithRenderVersioningTrait, WithFileUploads;

    public bool $isFormLocked = false;
    public ?string $riHdrNo = null;
    public array $dataDaftarRi = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-pemeriksaan-ri'];

    public $filePDF = null;
    public string $descPDF = '';
    public string $viewFilePDF = '';

    public function mount(): void
    {
        $this->registerAreas(['modal-pemeriksaan-ri']);
    }

    #[On('open-rm-pemeriksaan-ri')]
    public function open(string $riHdrNo): void
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
        $this->dataDaftarRi['pemeriksaan'] ??= [
            'pemeriksaanPenunjang' => ['lab' => [], 'rad' => []],
            'uploadHasilPenunjang' => [],
        ];

        $this->isFormLocked = $this->checkEmrRIStatus($riHdrNo); // ← trait

        $this->dispatch('open-rm-laboratorium-ri', $riHdrNo);
        $this->dispatch('open-rm-radiologi-ri', $riHdrNo);

        $this->incrementVersion('modal-pemeriksaan-ri');
    }

    public function uploadHasilPenunjang(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form terkunci.');
            return;
        }

        $this->validate(
            [
                'filePDF' => 'required|file|mimes:pdf|max:10240',
                'descPDF' => 'required|string|max:255',
            ],
            [
                'filePDF.required' => 'File PDF wajib dipilih.',
                'filePDF.mimes' => 'File harus berformat PDF.',
                'filePDF.max' => 'Maksimal 10 MB.',
                'descPDF.required' => 'Keterangan wajib diisi.',
            ],
        );

        try {
            $path = $this->filePDF->store('uploadHasilPenunjang', 'local');

            DB::transaction(function () use ($path) {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?: [];
                $fresh['pemeriksaan']['uploadHasilPenunjang'][] = [
                    'file' => $path,
                    'desc' => $this->descPDF,
                    'tglUpload' => Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'),
                    'penanggungJawab' => [
                        'userLog' => auth()->user()->myuser_name,
                        'userLogCode' => auth()->user()->myuser_code,
                        'userLogDate' => Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'),
                    ],
                ];
                $this->updateJsonRI($this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
            });

            $this->reset(['filePDF', 'descPDF']);
            $this->resetValidation(['filePDF', 'descPDF']);
            $this->afterSave('File berhasil diupload.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal upload: ' . $e->getMessage());
        }
    }

    public function deleteHasilPenunjang(string $file): void
    {
        if ($this->isFormLocked) {
            return;
        }

        Storage::disk('local')->exists($file) && Storage::disk('local')->delete($file);

        try {
            DB::transaction(function () use ($file) {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?: [];
                $fresh['pemeriksaan']['uploadHasilPenunjang'] = collect($fresh['pemeriksaan']['uploadHasilPenunjang'] ?? [])
                    ->filter(fn($i) => ($i['file'] ?? '') !== $file)
                    ->values()
                    ->toArray();
                $this->updateJsonRI($this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
            });

            $this->afterSave('File berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal hapus: ' . $e->getMessage());
        }
    }

    public function openModalViewPenunjang(string $file): void
    {
        $fullPath = storage_path('app/local/' . ltrim($file, '/'));
        if (!file_exists($fullPath)) {
            $this->dispatch('toast', type: 'error', message: 'File tidak ditemukan di server.');
            return;
        }
        $this->viewFilePDF = 'data:application/pdf;base64,' . base64_encode(file_get_contents($fullPath));
        $this->dispatch('open-modal', name: 'view-penunjang-pdf-ri');
    }

    public function closeModalViewPenunjang(): void
    {
        $this->viewFilePDF = '';
        $this->dispatch('close-modal', name: 'view-penunjang-pdf-ri');
    }

    private function afterSave(string $msg): void
    {
        $this->incrementVersion('modal-pemeriksaan-ri');
        $this->dispatch('toast', type: 'success', message: $msg);
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->filePDF = null;
        $this->descPDF = $this->viewFilePDF = '';
    }

};
?>

<div wire:key="{{ $this->renderKey('modal-pemeriksaan-ri', [$riHdrNo ?? 'new']) }}" x-data="{ activeTab: 'order' }">

    @if ($isFormLocked)
        <div
            class="flex items-center gap-2 px-4 py-2.5 mb-3 rounded-lg
                    bg-amber-50 border border-amber-200 text-amber-800
                    dark:bg-amber-900/20 dark:border-amber-700 dark:text-amber-300 text-sm">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
            </svg>
            Pasien sudah pulang — form dalam mode <strong>read-only</strong>.
        </div>
    @endif

    {{-- ── TAB UTAMA ── --}}
    <div class="border-b border-gray-200 dark:border-gray-700 mb-4">
        <ul class="flex flex-wrap -mb-px text-xs font-medium text-gray-500 dark:text-gray-400">
            <li class="mr-2">
                <button type="button" @click="activeTab = 'order'"
                    :class="activeTab === 'order'
                        ?
                        'text-brand border-brand bg-gray-100 dark:bg-gray-800' :
                        'border-transparent hover:text-gray-600 hover:border-gray-300'"
                    class="inline-flex items-center gap-2 px-4 py-2.5 border-b-2 rounded-t-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Order Penunjang
                </button>
            </li>
            <li class="mr-2">
                <button type="button" @click="activeTab = 'upload'"
                    :class="activeTab === 'upload'
                        ?
                        'text-brand border-brand bg-gray-100 dark:bg-gray-800' :
                        'border-transparent hover:text-gray-600 hover:border-gray-300'"
                    class="inline-flex items-center gap-2 px-4 py-2.5 border-b-2 rounded-t-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    Upload Penunjang
                </button>
            </li>
            <li class="mr-2">
                <button type="button" @click="activeTab = 'hasil'"
                    :class="activeTab === 'hasil'
                        ?
                        'text-brand border-brand bg-gray-100 dark:bg-gray-800' :
                        'border-transparent hover:text-gray-600 hover:border-gray-300'"
                    class="inline-flex items-center gap-2 px-4 py-2.5 border-b-2 rounded-t-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    Hasil Penunjang
                </button>
            </li>
        </ul>
    </div>

    {{-- ════════════ TAB 1 — ORDER PENUNJANG ════════════ --}}
    {{-- Tombol order & modal ada di dalam child component masing-masing --}}
    <div x-show="activeTab === 'order'" x-transition.opacity.duration.200ms class="space-y-4">

        <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Laboratorium</h3>
            <livewire:pages::transaksi.ri.emr-ri.pemeriksaan-ri.penunjang.laborat.rm-laborat-ri-actions
                :riHdrNo="$riHdrNo" :disabled="$isFormLocked" wire:key="lab-order-ri-{{ $riHdrNo }}" />
        </div>

        <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Radiologi</h3>
            <livewire:pages::transaksi.ri.emr-ri.pemeriksaan-ri.penunjang.radiologi.rm-radiologi-ri-actions
                :riHdrNo="$riHdrNo" :disabled="$isFormLocked" wire:key="rad-order-ri-{{ $riHdrNo }}" />
        </div>

    </div>

    {{-- ════════════ TAB 2 — UPLOAD PENUNJANG ════════════ --}}
    <div x-show="activeTab === 'upload'" x-transition.opacity.duration.200ms class="space-y-4">

        @hasanyrole('Perawat|Admin')
            <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-700">
                <h3 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Upload Hasil Penunjang</h3>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">

                    <div>
                        <x-input-label value="File PDF" />
                        <x-text-input type="file" wire:model="filePDF" accept="application/pdf" :disabled="$isFormLocked"
                            class="mt-1 block w-full" />
                        <div wire:loading wire:target="filePDF"
                            class="mt-1 h-1 w-full bg-brand/30 rounded-full overflow-hidden">
                            <div class="h-1 bg-brand animate-pulse rounded-full w-full"></div>
                        </div>
                        <x-input-error :messages="$errors->get('filePDF')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label value="Keterangan" />
                        <x-text-input wire:model.live.debounce.400ms="descPDF" placeholder="Keterangan hasil penunjang..."
                            maxlength="255" :disabled="$isFormLocked" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('descPDF')" class="mt-1" />
                    </div>

                    <div class="flex items-end">
                        <div wire:loading wire:target="uploadHasilPenunjang,filePDF" class="w-full">
                            <x-primary-button disabled class="w-full justify-center">
                                <svg class="w-4 h-4 animate-spin mr-2" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                        stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
                                </svg>
                                Mengupload...
                            </x-primary-button>
                        </div>
                        <div wire:loading.remove wire:target="uploadHasilPenunjang,filePDF" class="w-full">
                            <x-primary-button type="button" wire:click="uploadHasilPenunjang" :disabled="$isFormLocked"
                                class="w-full justify-center">
                                Upload Penunjang
                            </x-primary-button>
                        </div>
                    </div>

                </div>
            </div>
        @endhasanyrole

        <div
            class="overflow-x-auto bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-700">
            <table class="w-full text-sm text-left text-gray-600 dark:text-gray-400">
                <thead class="text-xs font-semibold text-gray-700 uppercase bg-gray-100 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 w-44">Tgl Upload</th>
                        <th class="px-4 py-3">Keterangan</th>
                        <th class="px-4 py-3 w-28 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($dataDaftarRi['pemeriksaan']['uploadHasilPenunjang'] ?? [] as $item)
                        <tr class="group hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                            <td class="px-4 py-2 whitespace-nowrap text-gray-700 dark:text-gray-300">
                                {{ $item['tglUpload'] ?? '-' }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $item['desc'] ?? '-' }}</td>
                            <td class="px-4 py-2">
                                <div class="flex items-center justify-center gap-2">
                                    @hasanyrole('Perawat|Admin|Dokter')
                                        <x-icon-button wire:click="openModalViewPenunjang('{{ $item['file'] ?? '' }}')"
                                            title="Lihat PDF">
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                                <path fill-rule="evenodd"
                                                    d="M5 6a1 1 0 0 1 1-1h12a1 1 0 1 1 0 2H6a1 1 0 0 1-1-1Zm0 12a1 1 0 0 1 1-1h12a1 1 0 1 1 0 2H6a1 1 0 0 1-1-1Zm1.65-9.76A1 1 0 0 0 5 9v6a1 1 0 0 0 1.65.76l3.5-3a1 1 0 0 0 0-1.52l-3.5-3ZM12 10a1 1 0 0 1 1-1h5a1 1 0 1 1 0 2h-5a1 1 0 0 1-1-1Zm0 4a1 1 0 0 1 1-1h5a1 1 0 1 1 0 2h-5a1 1 0 0 1-1-1Z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        </x-icon-button>
                                    @endhasanyrole
                                    @hasanyrole('Perawat|Admin')
                                        <x-confirm-button variant="danger" :action="'deleteHasilPenunjang(\'' . ($item['file'] ?? '') . '\')'" title="Hapus File"
                                            message="Yakin ingin menghapus file {{ $item['desc'] ?? '' }}?"
                                            confirmText="Ya, hapus" cancelText="Batal" :disabled="$isFormLocked">
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 18 20">
                                                <path
                                                    d="M17 4h-4V2a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v2H1a1 1 0 0 0 0 2h1v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V6h1a1 1 0 1 0 0-2ZM7 2h4v2H7V2Zm1 14a1 1 0 1 1-2 0V8a1 1 0 0 1 2 0v8Zm4 0a1 1 0 0 1-2 0V8a1 1 0 0 1 2 0v8Z" />
                                            </svg>
                                        </x-confirm-button>
                                    @endhasanyrole
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-8 text-center text-sm text-gray-400 dark:text-gray-500">
                                Belum ada file penunjang yang diupload.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>{{-- end tab upload --}}

    {{-- ════════════ TAB 3 — HASIL PENUNJANG ════════════ --}}
    <div x-show="activeTab === 'hasil'" x-transition.opacity.duration.200ms x-data="{ subTab: 'laboratorium' }">

        <div class="border-b border-gray-200 dark:border-gray-700 mb-4">
            <ul class="flex flex-wrap -mb-px text-xs font-medium text-gray-500 dark:text-gray-400">
                <li class="mr-2">
                    <button type="button" @click="subTab = 'laboratorium'"
                        :class="subTab === 'laboratorium'
                            ?
                            'text-brand border-brand bg-gray-100 dark:bg-gray-800 dark:text-brand dark:border-brand' :
                            'border-transparent hover:text-gray-600 hover:border-gray-300 dark:hover:text-gray-300'"
                        class="inline-flex items-center gap-2 px-4 py-2.5 border-b-2 rounded-t-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.78 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                        </svg>
                        Laboratorium
                    </button>
                </li>
                <li class="mr-2">
                    <button type="button" @click="subTab = 'radiologi'"
                        :class="subTab === 'radiologi'
                            ?
                            'text-brand border-brand bg-gray-100 dark:bg-gray-800 dark:text-brand dark:border-brand' :
                            'border-transparent hover:text-gray-600 hover:border-gray-300 dark:hover:text-gray-300'"
                        class="inline-flex items-center gap-2 px-4 py-2.5 border-b-2 rounded-t-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z" />
                        </svg>
                        Radiologi
                    </button>
                </li>
                <li class="mr-2">
                    <button type="button" @click="subTab = 'upload'"
                        :class="subTab === 'upload'
                            ?
                            'text-brand border-brand bg-gray-100 dark:bg-gray-800 dark:text-brand dark:border-brand' :
                            'border-transparent hover:text-gray-600 hover:border-gray-300 dark:hover:text-gray-300'"
                        class="inline-flex items-center gap-2 px-4 py-2.5 border-b-2 rounded-t-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                        Upload Penunjang
                    </button>
                </li>
            </ul>
        </div>

        <div x-show="subTab === 'laboratorium'" x-cloak>
            <livewire:pages::components.rekam-medis.penunjang.laboratorium-display.laboratorium-display
                :regNo="$dataDaftarRi['regNo'] ?? ''" wire:key="emr-ri.laboratorium-display-{{ $dataDaftarRi['regNo'] ?? 'new' }}" />
        </div>

        <div x-show="subTab === 'radiologi'" x-cloak>
            <livewire:pages::components.rekam-medis.penunjang.radiologi-display.radiologi-display :regNo="$dataDaftarRi['regNo'] ?? ''"
                wire:key="emr-ri.radiologi-display-{{ $dataDaftarRi['regNo'] ?? 'new' }}" />
        </div>

        <div x-show="subTab === 'upload'" x-cloak>
            <livewire:pages::components.rekam-medis.penunjang.upload-penunjang-display.upload-penunjang-display
                :regNo="$dataDaftarRi['regNo'] ?? ''" wire:key="emr-ri.upload-penunjang-display-{{ $dataDaftarRi['regNo'] ?? 'new' }}" />
        </div>

    </div>{{-- end tab hasil --}}

    {{-- ════════════ MODAL LIHAT PDF ════════════ --}}
    <x-modal name="view-penunjang-pdf-ri" size="full" height="full" focusable>
        <div class="flex flex-col h-[calc(100vh-4rem)]" wire:key="view-penunjang-pdf-ri-{{ $viewFilePDF }}">

            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700 shrink-0">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image:radial-gradient(currentColor 1px,transparent 1px);background-size:14px 14px">
                </div>
                <div class="relative flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex items-center justify-center w-10 h-10 rounded-xl bg-yellow-400/10 dark:bg-yellow-400/15">
                            <svg class="w-5 h-5 text-yellow-500" fill="currentColor" viewBox="0 0 24 24">
                                <path
                                    d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm-1 1.5L18.5 9H13V3.5zM8 13h8v1.5H8V13zm0 3h8v1.5H8V16zm0-6h3v1.5H8V10z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Hasil Penunjang</h2>
                            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Preview file PDF hasil penunjang
                                pasien</p>
                        </div>
                    </div>
                    <x-icon-button color="gray" type="button" wire:click="closeModalViewPenunjang">
                        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            <div class="flex-1 min-h-0 bg-gray-100 dark:bg-gray-950">
                @if ($viewFilePDF)
                    <iframe src="{{ $viewFilePDF }}" class="w-full h-full border-0"
                        type="application/pdf"></iframe>
                @endif
            </div>

            <div class="shrink-0 px-6 py-4 bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm text-gray-500 dark:text-gray-400">File dibuka dalam mode preview — tidak dapat
                        diedit.</p>
                    <x-secondary-button type="button" wire:click="closeModalViewPenunjang">Tutup</x-secondary-button>
                </div>
            </div>

        </div>
    </x-modal>

</div>{{-- end single root --}}
