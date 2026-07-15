<?php
// Komponen Modal Satu Sehat RI (Rawat Inap).
// Port dari satu-sehat-rj-actions. Trigger: event 'daftar-ri.satu-sehat.open' dengan riHdrNo.
// Body memuat SFC self-contained per resource (Encounter dulu; resource lain menyusul).

use Livewire\Component;
use Livewire\Attributes\On;
use App\Http\Traits\Txn\Ri\EmrRITrait;

new class extends Component {
    use EmrRITrait;

    public ?string $riHdrNo = null;
    public array $dataDaftarRI = [];

    public function mount(?string $initialRiHdrNo = null): void
    {
        if (!empty($initialRiHdrNo)) {
            $this->riHdrNo = $initialRiHdrNo;
            $this->loadData();
        }
    }

    #[On('daftar-ri.satu-sehat.open')]
    public function handleOpenSatuSehat(string $riHdrNo): void
    {
        $this->riHdrNo = $riHdrNo;

        if (!$this->loadData()) {
            return;
        }

        $this->dispatch('open-modal', name: 'ri-satu-sehat');
    }

    #[On('ri-satu-sehat.refresh')]
    public function onRefresh(string $riHdrNo): void
    {
        if ((string) $this->riHdrNo !== $riHdrNo) {
            return;
        }
        $this->loadData();
    }

    private function loadData(): bool
    {
        if (empty($this->riHdrNo)) {
            return false;
        }
        $data = $this->findDataRI($this->riHdrNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Inap tidak ditemukan.');
            return false;
        }
        $this->dataDaftarRI = $data;
        return true;
    }
};
?>

<div>
    <x-modal name="ri-satu-sehat" size="full" height="full" focusable>
        <div class="flex flex-col min-h-0">
            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div
                                class="flex items-center justify-center w-10 h-10 rounded-xl bg-teal-500/10 dark:bg-teal-400/15">
                                <svg class="w-6 h-6 text-teal-600 dark:text-teal-400" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="font-semibold text-2xl text-ink dark:text-gray-100">Kirim Satu Sehat
                                    <span class="text-sm font-normal text-muted dark:text-gray-400">— Rawat Inap</span>
                                </h2>
                                <p class="mt-0.5 text-sm text-muted dark:text-gray-400">
                                    <span class="font-semibold">{{ $dataDaftarRI['regName'] ?? '-' }}</span>
                                    &mdash; RM: {{ $dataDaftarRI['regNo'] ?? '-' }}
                                    &mdash; RI: {{ $riHdrNo ?? '-' }}
                                    @if (!empty($dataDaftarRI['roomDesc']))
                                        &mdash; Kamar: {{ $dataDaftarRI['roomDesc'] }}
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                    <x-icon-button color="gray" type="button"
                        x-on:click="$dispatch('close-modal', { name: 'ri-satu-sehat' })">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY — SFC self-contained per resource --}}
            <div class="flex-1 px-6 py-6 overflow-y-auto bg-surface-soft/70 dark:bg-gray-950/20">
                <div class="max-w-4xl mx-auto space-y-3">
                    <livewire:pages::transaksi.ri.satu-sehat.kirim-encounter :riHdrNo="$riHdrNo"
                        wire:key="ss-encounter-ri-{{ $riHdrNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ri.satu-sehat.kirim-episode :riHdrNo="$riHdrNo"
                        wire:key="ss-episode-ri-{{ $riHdrNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ri.satu-sehat.kirim-condition :riHdrNo="$riHdrNo"
                        wire:key="ss-condition-ri-{{ $riHdrNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ri.satu-sehat.kirim-procedure :riHdrNo="$riHdrNo"
                        wire:key="ss-procedure-ri-{{ $riHdrNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ri.satu-sehat.kirim-observation :riHdrNo="$riHdrNo"
                        wire:key="ss-observation-ri-{{ $riHdrNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ri.satu-sehat.kirim-medication-request :riHdrNo="$riHdrNo"
                        wire:key="ss-medication-request-ri-{{ $riHdrNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ri.satu-sehat.kirim-medication-dispense :riHdrNo="$riHdrNo"
                        wire:key="ss-medication-dispense-ri-{{ $riHdrNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ri.satu-sehat.kirim-lab :riHdrNo="$riHdrNo"
                        wire:key="ss-lab-ri-{{ $riHdrNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ri.satu-sehat.kirim-radiologi :riHdrNo="$riHdrNo"
                        wire:key="ss-radiologi-ri-{{ $riHdrNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ri.satu-sehat.kirim-cppt :riHdrNo="$riHdrNo"
                        wire:key="ss-cppt-ri-{{ $riHdrNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ri.satu-sehat.kirim-diet :riHdrNo="$riHdrNo"
                        wire:key="ss-diet-ri-{{ $riHdrNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ri.satu-sehat.kirim-penilaian :riHdrNo="$riHdrNo"
                        wire:key="ss-penilaian-ri-{{ $riHdrNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ri.satu-sehat.kirim-observasi-lanjutan :riHdrNo="$riHdrNo"
                        wire:key="ss-observasi-lanjutan-ri-{{ $riHdrNo ?? 'none' }}" />
                    {{-- ChiefComplaint & Allergy RI butuh SNOMED (dinonaktifkan sementara).
                         Aktifkan bersama LOV SNOMED di rm-pengkajian-dokter-ri-actions (false → true). --}}
                    @if (false)
                    <livewire:pages::transaksi.ri.satu-sehat.kirim-chief-complaint :riHdrNo="$riHdrNo"
                        wire:key="ss-chief-complaint-ri-{{ $riHdrNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ri.satu-sehat.kirim-allergy :riHdrNo="$riHdrNo"
                        wire:key="ss-allergy-ri-{{ $riHdrNo ?? 'none' }}" />
                    @endif

                    {{-- Lab & Radiologi (cabang status_rjri='RI') menyusul. --}}
                </div>
            </div>
        </div>
    </x-modal>
</div>
