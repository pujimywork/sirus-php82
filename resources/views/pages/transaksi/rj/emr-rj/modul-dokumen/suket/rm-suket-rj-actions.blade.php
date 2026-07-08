<?php

use Livewire\Component;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\WithValidationToast\WithValidationToastTrait;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Carbon\Carbon;

new class extends Component {
    use EmrRJTrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public array $dataDaftarPoliRJ = [];

    // renderVersions
    public array $renderVersions = [];
    protected array $renderAreas = ['modal-suket-rj'];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(): void
    {
        $this->registerAreas(['modal-suket-rj']);
    }

    public function rendering(): void
    {
        $default = $this->getDefaultSuket();
        $current = $this->dataDaftarPoliRJ['suket'] ?? [];
        $this->dataDaftarPoliRJ['suket'] = array_replace_recursive($default, $current);
    }

    /* ===============================
     | OPEN REKAM MEDIS - SUKET
     =============================== */
    #[On('open-rm-suket-rj')]
    public function openSuket($rjNo): void
    {
        if (empty($rjNo)) {
            return;
        }

        $this->rjNo = $rjNo;

        $this->resetForm();
        $this->resetValidation();

        // Ambil data kunjungan RJ
        $dataDaftarPoliRJ = $this->findDataRJ($rjNo);

        if (!$dataDaftarPoliRJ) {
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Jalan tidak ditemukan.');
            return;
        }

        $this->dataDaftarPoliRJ = $dataDaftarPoliRJ;

        // Initialize suket data jika belum ada
        $this->dataDaftarPoliRJ['suket'] ??= $this->getDefaultSuket();

        // Normalisasi data legacy:
        // - Regenerate mulaiIstirahatOptions ke struktur baru ([value, label])
        // - Strip suffix " (Hari Ini)"/" (Besok)" dari mulaiIstirahat agar Carbon parse aman
        $fresh = $this->getDefaultSuket();
        $this->dataDaftarPoliRJ['suket']['suketIstirahat']['mulaiIstirahatOptions']
            = $fresh['suketIstirahat']['mulaiIstirahatOptions'];
        $mulai = (string) ($this->dataDaftarPoliRJ['suket']['suketIstirahat']['mulaiIstirahat'] ?? '');
        $this->dataDaftarPoliRJ['suket']['suketIstirahat']['mulaiIstirahat']
            = trim(preg_replace('/\s*\(.+?\)\s*$/', '', $mulai)) ?: $fresh['suketIstirahat']['mulaiIstirahat'];

        // 🔥 INCREMENT: Refresh seluruh modal suket
        $this->incrementVersion('modal-suket-rj');

        // Cek status lock
        if ($this->checkEmrRJStatus($rjNo)) {
            $this->isFormLocked = true;
        }
    }

    /* ===============================
     | GET DEFAULT SUKET STRUCTURE
     =============================== */
    private function getDefaultSuket(): array
    {
        try {
            $rjDate = Carbon::createFromFormat('d/m/Y H:i:s', $this->dataDaftarPoliRJ['rjDate'] ?? '');
        } catch (\Throwable) {
            $rjDate = Carbon::now(config('app.timezone'));
        }

        $hariIni = $rjDate->format('d/m/Y');
        $besok = $rjDate->copy()->addDay()->format('d/m/Y');

        return [
            'suketSehatTab' => 'Suket Sehat',
            'suketSehat' => [
                'suketSehat' => '',
            ],

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
     | VALIDATION RULES
     =============================== */
    protected function rules(): array
    {
        return [
            'dataDaftarPoliRJ.suket.suketIstirahat.suketIstirahatHari' => 'nullable|integer|min:1',
        ];
    }

    protected function messages(): array
    {
        return [
            'dataDaftarPoliRJ.suket.suketIstirahat.suketIstirahatHari.integer' => ':attribute harus berupa angka.',
            'dataDaftarPoliRJ.suket.suketIstirahat.suketIstirahatHari.min' => ':attribute minimal 1 hari.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'dataDaftarPoliRJ.suket.suketIstirahat.suketIstirahatHari' => 'Jumlah Hari Istirahat',
        ];
    }

    /* ===============================
     | SAVE SUKET
     =============================== */
    #[On('save-rm-suket-rj')]
    public function save(): void
    {
        // 1. Read-only guard — selalu dengan toast
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only, tidak dapat menyimpan data.');
            return;
        }

        // 2. Guard: properti lokal belum ter-load
        if (empty($this->dataDaftarPoliRJ)) {
            $this->dispatch('toast', type: 'error', message: 'Data kunjungan tidak ditemukan, silakan buka ulang form.');
            return;
        }

        // 3. Validasi Livewire rules
        $this->validateWithToast();

        try {
            DB::transaction(function () {
                // 4. Lock row di DB (SELECT FOR UPDATE) — cegah race condition
                $this->lockRJRow($this->rjNo);

                // 5. Ambil data terkini dari DB (setelah lock)
                $data = $this->findDataRJ($this->rjNo) ?? [];

                // 6. Guard: data DB kosong — jangan overwrite JSON dengan array kosong
                if (empty($data)) {
                    $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan, simpan dibatalkan.');
                    return;
                }

                // Tangkap status baru/lama sebelum overwrite (key suket belum ada saat pertama disimpan)
                $isBaru = empty($data['suket']);

                // 7. Set hanya key 'suket' — key lain tidak tersentuh
                $data['suket'] = $this->dataDaftarPoliRJ['suket'] ?? [];

                // 8. Persist + sync properti lokal
                $this->updateJsonRJ($this->rjNo, $data);
                $this->dataDaftarPoliRJ = $data;
                $this->appendAdminLogRJ((int) $this->rjNo, ($isBaru ? 'Buat' : 'Update') . ' Surat Keterangan — mulai istirahat ' . ($data['suket']['suketIstirahat']['mulaiIstirahat'] ?? '-'), 'MR');
            });

            $this->afterSave('Surat Keterangan berhasil disimpan.');
        } catch (\RuntimeException $e) {
            // lockRJRow() throws RuntimeException jika row tidak ditemukan
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | CETAK SUKET
     =============================== */
    public function cetakSuketSehat(): void
    {
        $this->dispatch('cetak-suket-sehat-rj.open', rjNo: $this->rjNo);
    }

    public function cetakSuketSakit(): void
    {
        $this->dispatch('cetak-suket-sakit-rj.open', rjNo: $this->rjNo);
    }

    /* ===============================
     | CLOSE MODAL
     =============================== */
    public function openModal(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $this->resetValidation();
        $this->dispatch('open-modal', name: "rm-suket-rj-{$this->rjNo}");
    }

    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: "rm-suket-rj-{$this->rjNo}");
    }

    /* ===============================
     | HELPERS
     =============================== */
    private function afterSave(string $message): void
    {
        $this->incrementVersion('modal-suket-rj');
        $this->dispatch('toast', type: 'success', message: $message);
        // Reset dirty state di EMR RJ parent (<x-dirty-modal-content>).
        $this->dispatch('refresh-after-rj.saved');
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
    }
};

?>

<div>
    {{-- RINGKASAN + TOMBOL (pola General Consent) --}}
    <div class="p-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex-1 space-y-2">
                <h3 class="text-base font-semibold text-ink dark:text-gray-200">Surat Keterangan</h3>
                <p class="text-base text-muted dark:text-gray-400">
                    Surat Keterangan Sehat &amp; Surat Keterangan Istirahat (sakit) untuk pasien rawat jalan.
                </p>
            </div>
            <div class="flex shrink-0">
                <x-primary-button type="button" wire:click="openModal" wire:loading.attr="disabled"
                    wire:target="openModal" :disabled="!$rjNo" class="gap-2">
                    <span wire:loading.remove wire:target="openModal" class="flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                        Buka Surat Keterangan
                    </span>
                    <span wire:loading wire:target="openModal" class="flex items-center gap-1.5">
                        <x-loading class="w-4 h-4" /> Memuat...
                    </span>
                </x-primary-button>
            </div>
        </div>
    </div>

    {{-- MODAL FORM --}}
    <x-modal name="rm-suket-rj-{{ $rjNo ?? 'init' }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]" wire:key="{{ $this->renderKey('modal-suket-rj', [$rjNo ?? 'new']) }}">
            {{-- HEADER MODAL --}}
            <div class="flex items-center justify-between gap-4 px-6 py-4 border-b border-hairline bg-surface-soft dark:border-gray-700">
                <h2 class="text-xl font-semibold text-ink dark:text-gray-100">Surat Keterangan</h2>
                <x-icon-button color="gray" type="button" wire:click="closeModal">
                    <span class="sr-only">Close</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                            clip-rule="evenodd" />
                    </svg>
                </x-icon-button>
            </div>

            {{-- Display Pasien (selaras General Consent) --}}
            <div class="px-4 pt-4">
                <livewire:pages::transaksi.rj.display-pasien-rj.display-pasien-rj :rjNo="$rjNo"
                    wire:key="suket-rj-display-pasien-{{ $rjNo ?? 'init' }}" />
            </div>

        {{-- BODY --}}
        <div class="w-full mx-auto">
            <div
                class="w-full p-4 space-y-6 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                @if (isset($dataDaftarPoliRJ['suket']))
                    <div class="w-full">
                        <div id="SuketRawatJalan" x-data="{ activeTab: '{{ $dataDaftarPoliRJ['suket']['suketSehatTab'] ?? 'Suket Sehat' }}' }" class="w-full">

                            {{-- TAB NAVIGATION --}}
                            <x-scrollable-tabs class="w-full px-2 mb-2 border-b border-hairline dark:border-gray-700">
                                <div class="flex flex-nowrap w-full gap-2 -mb-px">

                                    {{-- SUKET SEHAT TAB --}}
                                    <x-tab variant="underline"
                                        active-expr="activeTab === '{{ $dataDaftarPoliRJ['suket']['suketSehatTab'] ?? 'Suket Sehat' }}'"
                                        x-on:click="activeTab = '{{ $dataDaftarPoliRJ['suket']['suketSehatTab'] ?? 'Suket Sehat' }}'">
                                        {{ $dataDaftarPoliRJ['suket']['suketSehatTab'] ?? 'Suket Sehat' }}
                                    </x-tab>

                                    {{-- SUKET ISTIRAHAT TAB --}}
                                    <x-tab variant="underline"
                                        active-expr="activeTab === '{{ $dataDaftarPoliRJ['suket']['suketIstirahatTab'] ?? 'Suket Istirahat' }}'"
                                        x-on:click="activeTab = '{{ $dataDaftarPoliRJ['suket']['suketIstirahatTab'] ?? 'Suket Istirahat' }}'">
                                        {{ $dataDaftarPoliRJ['suket']['suketIstirahatTab'] ?? 'Suket Istirahat' }}
                                    </x-tab>

                                </div>
                            </x-scrollable-tabs>

                            {{-- TAB CONTENTS --}}
                            <div class="w-full p-4">

                                {{-- SUKET SEHAT TAB CONTENT --}}
                                @if (isset($dataDaftarPoliRJ['suket']['suketSehatTab']))
                                    <div class="w-full"
                                        x-show.transition.in.opacity.duration.600="activeTab === '{{ $dataDaftarPoliRJ['suket']['suketSehatTab'] ?? 'Suket Sehat' }}'">
                                        @include('pages.transaksi.rj.emr-rj.modul-dokumen.suket.tab.suket-sehat-tab')
                                    </div>
                                @endif

                                {{-- SUKET ISTIRAHAT TAB CONTENT --}}
                                @if (isset($dataDaftarPoliRJ['suket']['suketIstirahatTab']))
                                    <div class="w-full"
                                        x-show.transition.in.opacity.duration.600="activeTab === '{{ $dataDaftarPoliRJ['suket']['suketIstirahatTab'] ?? 'Suket Istirahat' }}'">
                                        @include('pages.transaksi.rj.emr-rj.modul-dokumen.suket.tab.suket-istirahat-tab')
                                    </div>
                                @endif

                            </div>
                        </div>
                    </div>
                @endif

            </div>

            {{-- FOOTER — tombol Cetak per-surat ada di dalam tab masing-masing --}}
            @if ($rjNo && !$isFormLocked)
                <div
                    class="sticky bottom-0 z-10 px-4 py-3 mt-4 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700 rounded-b-2xl">
                    <div class="flex flex-wrap items-center justify-end gap-3">
                        <x-secondary-button type="button" wire:click="closeModal" class="min-w-[120px] justify-center">
                            Tutup
                        </x-secondary-button>
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
        </div>
    </div>
    </x-modal>

    {{-- Cetak components — daftar sekali di parent/modal --}}
    <livewire:pages::components.modul-dokumen.r-j.suket-sakit.cetak-suket-sakit-rj wire:key="cetak-suket-sakit-rj" />
    <livewire:pages::components.modul-dokumen.r-j.suket-sehat.cetak-suket-sehat-rj wire:key="cetak-suket-sehat-rj" />
</div>
