<?php

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
    protected array $renderAreas = ['modal-suket-ugd'];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas(['modal-suket-ugd']);
    }

    public function rendering(): void
    {
        $default = $this->getDefaultSuket();
        $current = $this->dataDaftarUGD['suket'] ?? [];
        $this->dataDaftarUGD['suket'] = array_replace_recursive($default, $current);
    }

    /* ===============================
     | OPEN
     =============================== */
    #[On('open-rm-suket-ugd')]
    public function openSuket($rjNo): void
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

        $this->dataDaftarUGD['suket'] ??= $this->getDefaultSuket();

        // Normalisasi data legacy:
        // - Regenerate mulaiIstirahatOptions ke struktur baru ([value, label])
        // - Strip suffix " (Hari Ini)"/" (Besok)" dari mulaiIstirahat agar Carbon parse aman
        $fresh = $this->getDefaultSuket();
        $this->dataDaftarUGD['suket']['suketIstirahat']['mulaiIstirahatOptions']
            = $fresh['suketIstirahat']['mulaiIstirahatOptions'];
        $mulai = (string) ($this->dataDaftarUGD['suket']['suketIstirahat']['mulaiIstirahat'] ?? '');
        $this->dataDaftarUGD['suket']['suketIstirahat']['mulaiIstirahat']
            = trim(preg_replace('/\s*\(.+?\)\s*$/', '', $mulai)) ?: $fresh['suketIstirahat']['mulaiIstirahat'];

        $this->isFormLocked = $this->checkEmrUGDStatus($rjNo);
        $this->incrementVersion('modal-suket-ugd');
    }

    /* ===============================
     | VALIDATION
     =============================== */
    protected function rules(): array
    {
        return [
            'dataDaftarUGD.suket.suketIstirahat.suketIstirahatHari' => 'nullable|integer|min:1',
        ];
    }

    protected function messages(): array
    {
        return [
            'dataDaftarUGD.suket.suketIstirahat.suketIstirahatHari.integer' => ':attribute harus berupa angka.',
            'dataDaftarUGD.suket.suketIstirahat.suketIstirahatHari.min' => ':attribute minimal 1 hari.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'dataDaftarUGD.suket.suketIstirahat.suketIstirahatHari' => 'Jumlah Hari Istirahat',
        ];
    }

    /* ===============================
     | SAVE
     =============================== */
    #[On('save-rm-suket-ugd')]
    public function save(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }

        $this->validate();

        try {
            DB::transaction(function () {
                // 1. Lock row dulu — cegah race condition update JSON bersamaan
                $this->lockUGDRow($this->rjNo);

                // 2. Baca data terkini setelah lock
                $data = $this->findDataUGD($this->rjNo) ?? [];

                if (empty($data)) {
                    throw new \RuntimeException('Data UGD tidak ditemukan, simpan dibatalkan.');
                }

                // Tangkap status sebelum overwrite (untuk verb log Buat/Update)
                $isBaru = empty($data['suket']);

                // 3. Patch hanya key suket
                $data['suket'] = $this->dataDaftarUGD['suket'] ?? [];

                $this->updateJsonUGD($this->rjNo, $data);
                $this->dataDaftarUGD = $data;

                $this->appendAdminLogUGD((int) $this->rjNo, ($isBaru ? 'Buat' : 'Update') . ' Surat Keterangan UGD — mulai istirahat ' . ($data['suket']['suketIstirahat']['mulaiIstirahat'] ?? '-'), 'MR');
            });

            // 4. Notify + increment — di luar transaksi
            $this->afterSave('Surat Keterangan berhasil disimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | CETAK
     =============================== */
    public function cetakSuketSehat(): void
    {
        // Event name UGD pakai suffix -ugd agar match listener di cetak-suket-sehat-ugd component
        // (listener cetak-suket-sehat.open tanpa suffix dipakai oleh RJ component).
        $this->dispatch('cetak-suket-sehat-ugd.open', rjNo: $this->rjNo);
    }

    public function cetakSuketSakit(): void
    {
        $this->dispatch('cetak-suket-sakit-ugd.open', rjNo: $this->rjNo);
    }

    /* ===============================
     | DEFAULT STRUCTURE
     =============================== */
    private function getDefaultSuket(): array
    {
        try {
            $rjDate = Carbon::createFromFormat('d/m/Y H:i:s', $this->dataDaftarUGD['rjDate'] ?? '');
        } catch (\Throwable) {
            $rjDate = Carbon::now(config('app.timezone'));
        }

        $hariIni = $rjDate->format('d/m/Y');
        $besok = $rjDate->copy()->addDay()->format('d/m/Y');

        return [
            'suketSehatTab' => 'Suket Sehat',
            'suketSehat' => ['suketSehat' => ''],

            'suketIstirahatTab' => 'Suket Istirahat',
            'suketIstirahat' => [
                'mulaiIstirahat' => $hariIni,
                // Options dipisah value (d/m/Y murni untuk Carbon::createFromFormat) dan label (tampilan)
                'mulaiIstirahatOptions' => [
                    ['value' => $hariIni, 'label' => "{$hariIni} (Hari Ini)"],
                    ['value' => $besok, 'label' => "{$besok} (Besok)"],
                ],
                'suketIstirahatHari' => '2',
                'suketIstirahat' => '',
            ],
        ];
    }

    /* ===============================
     | HELPERS
     =============================== */
    private function afterSave(string $message): void
    {
        $this->incrementVersion('modal-suket-ugd');
        $this->dispatch('toast', type: 'success', message: $message);
        // Reset dirty state di EMR UGD parent (<x-dirty-modal-content>).
        $this->dispatch('refresh-after-ugd.saved');
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
    }
};
?>

<div>
    <div class="flex flex-col w-full" wire:key="{{ $this->renderKey('modal-suket-ugd', [$rjNo ?? 'new']) }}">
        <div class="w-full mx-auto">
            <div
                class="w-full p-4 space-y-6 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                @if ($isFormLocked)
                    <div
                        class="flex items-center gap-2 px-4 py-2.5 text-base font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        EMR terkunci — data tidak dapat diubah.
                    </div>
                @endif

                @if (isset($dataDaftarUGD['suket']))
                    <div class="w-full">
                        <div x-data="{ activeTab: '{{ $dataDaftarUGD['suket']['suketSehatTab'] ?? 'Suket Sehat' }}' }" class="w-full">

                            {{-- TAB NAVIGATION --}}
                            <x-scrollable-tabs class="w-full px-2 mb-2 border-b border-hairline dark:border-gray-700">
                                <ul
                                    class="flex flex-nowrap whitespace-nowrap w-full -mb-px text-sm font-medium text-center text-muted dark:text-gray-400">

                                    <li class="mr-2">
                                        <label
                                            class="inline-block p-4 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-muted hover:border-gray-300"
                                            :class="activeTab === '{{ $dataDaftarUGD['suket']['suketSehatTab'] ?? 'Suket Sehat' }}'
                                                ?
                                                'text-brand border-brand dark:text-emerald-300 dark:border-emerald-400 bg-surface-soft' : ''"
                                            @click="activeTab = '{{ $dataDaftarUGD['suket']['suketSehatTab'] ?? 'Suket Sehat' }}'">
                                            {{ $dataDaftarUGD['suket']['suketSehatTab'] ?? 'Suket Sehat' }}
                                        </label>
                                    </li>

                                    <li class="mr-2">
                                        <label
                                            class="inline-block p-4 border-b-2 border-transparent rounded-t-lg cursor-pointer hover:text-muted hover:border-gray-300"
                                            :class="activeTab === '{{ $dataDaftarUGD['suket']['suketIstirahatTab'] ?? 'Suket Istirahat' }}'
                                                ?
                                                'text-brand border-brand dark:text-emerald-300 dark:border-emerald-400 bg-surface-soft' : ''"
                                            @click="activeTab = '{{ $dataDaftarUGD['suket']['suketIstirahatTab'] ?? 'Suket Istirahat' }}'">
                                            {{ $dataDaftarUGD['suket']['suketIstirahatTab'] ?? 'Suket Istirahat' }}
                                        </label>
                                    </li>

                                </ul>
                            </x-scrollable-tabs>

                            {{-- TAB CONTENTS --}}
                            <div class="w-full p-4">

                                @if (isset($dataDaftarUGD['suket']['suketSehatTab']))
                                    <div class="w-full"
                                        x-show.transition.in.opacity.duration.600="activeTab === '{{ $dataDaftarUGD['suket']['suketSehatTab'] ?? 'Suket Sehat' }}'">
                                        @include('pages.transaksi.ugd.emr-ugd.modul-dokumen.suket.tab.suket-sehat-tab')
                                    </div>
                                @endif

                                @if (isset($dataDaftarUGD['suket']['suketIstirahatTab']))
                                    <div class="w-full"
                                        x-show.transition.in.opacity.duration.600="activeTab === '{{ $dataDaftarUGD['suket']['suketIstirahatTab'] ?? 'Suket Istirahat' }}'">
                                        @include('pages.transaksi.ugd.emr-ugd.modul-dokumen.suket.tab.suket-istirahat-tab')
                                    </div>
                                @endif

                            </div>
                        </div>
                    </div>

                    {{-- FOOTER — tombol Cetak per-surat ada di dalam tab masing-masing --}}
                    @if (!$isFormLocked)
                        <div
                            class="sticky bottom-0 z-10 px-4 py-3 mt-4 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700 rounded-b-2xl">
                            <div class="flex flex-wrap items-center justify-end gap-3">
                                <x-primary-button wire:click.prevent="save" wire:loading.attr="disabled"
                                    wire:target="save" class="gap-2 min-w-[200px] justify-center">
                                    <span wire:loading.remove wire:target="save">
                                        <svg class="inline w-4 h-4 mr-1 -ml-1" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1-4l-4 4-4-4m4 4V4" />
                                        </svg>
                                        Simpan Surat Keterangan
                                    </span>
                                    <span wire:loading wire:target="save"><x-loading class="w-4 h-4" /> Menyimpan...</span>
                                </x-primary-button>
                            </div>
                        </div>
                    @endif

                @endif

            </div>
        </div>
    </div>

    {{-- Cetak components --}}
    <livewire:pages::components.modul-dokumen.u-g-d.suket-sakit.cetak-suket-sakit-ugd wire:key="cetak-suket-sakit-ugd" />
    <livewire:pages::components.modul-dokumen.u-g-d.suket-sehat.cetak-suket-sehat-ugd wire:key="cetak-suket-sehat-ugd" />
</div>
