<?php
// Komponen Modal Satu Sehat UGD — pola sama satu-sehat-rj-actions.
// Trigger dari parent: dispatch event 'daftar-ugd.satu-sehat.open' dengan rjNo (= rj_no ugdhdrs).
// CATATAN UGD (beda dari RJ): Encounter class EMER, tanpa poli (lokasi via env/DB),
//   anamnesa tanpa SNOMED → tidak ada kartu Chief Complaint & Allergy.

use Livewire\Component;
use Livewire\Attributes\On;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;

new class extends Component {
    use EmrUGDTrait;

    public ?string $rjNo = null;
    public array $dataDaftarUGD = [];

    public function mount(?string $initialRjNo = null): void
    {
        if (!empty($initialRjNo)) {
            $this->rjNo = $initialRjNo;
            $this->loadData();
        }
    }

    #[On('daftar-ugd.satu-sehat.open')]
    public function handleOpenSatuSehat(string $rjNo): void
    {
        $this->rjNo = $rjNo;

        if (!$this->loadData()) {
            return;
        }

        $this->dispatch('open-modal', name: 'ugd-satu-sehat');
    }

    #[On('ugd-satu-sehat.refresh')]
    public function onRefresh(string $rjNo): void
    {
        if ((string) $this->rjNo !== $rjNo) {
            return;
        }
        $this->loadData();
    }

    private function loadData(): bool
    {
        if (empty($this->rjNo)) {
            return false;
        }
        $data = $this->findDataUGD($this->rjNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.');
            return false;
        }
        $this->dataDaftarUGD = $data;
        return true;
    }
};
?>

<div>
    <x-modal name="ugd-satu-sehat" size="full" height="full" focusable>
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
                                    <span class="text-sm font-normal text-muted dark:text-gray-400">— UGD (Emergency)</span>
                                </h2>
                                <p class="mt-0.5 text-sm text-muted dark:text-gray-400">
                                    <span class="font-semibold">{{ $dataDaftarUGD['regName'] ?? '-' }}</span>
                                    &mdash; RM: {{ $dataDaftarUGD['regNo'] ?? '-' }}
                                    &mdash; UGD: {{ $rjNo ?? '-' }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <x-icon-button color="gray" type="button"
                        x-on:click="$dispatch('close-modal', { name: 'ugd-satu-sehat' })">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY — SFC self-contained (UGD: tanpa Chief Complaint & Allergy, anamnesa tanpa SNOMED) --}}
            <div class="flex-1 px-6 py-6 overflow-y-auto bg-surface-soft/70 dark:bg-gray-950/20">
                <div class="max-w-4xl mx-auto space-y-3">
                    <livewire:pages::transaksi.ugd.satu-sehat.kirim-encounter :rjNo="$rjNo"
                        wire:key="ss-encounter-ugd-{{ $rjNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ugd.satu-sehat.kirim-condition :rjNo="$rjNo"
                        wire:key="ss-condition-ugd-{{ $rjNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ugd.satu-sehat.kirim-observation :rjNo="$rjNo"
                        wire:key="ss-observation-ugd-{{ $rjNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ugd.satu-sehat.kirim-procedure :rjNo="$rjNo"
                        wire:key="ss-procedure-ugd-{{ $rjNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ugd.satu-sehat.kirim-medication-request :rjNo="$rjNo"
                        wire:key="ss-medication-request-ugd-{{ $rjNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ugd.satu-sehat.kirim-medication-dispense :rjNo="$rjNo"
                        wire:key="ss-medication-dispense-ugd-{{ $rjNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ugd.satu-sehat.kirim-lab :rjNo="$rjNo"
                        wire:key="ss-lab-ugd-{{ $rjNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ugd.satu-sehat.kirim-radiologi :rjNo="$rjNo"
                        wire:key="ss-radiologi-ugd-{{ $rjNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ugd.satu-sehat.kirim-clinical-impression :rjNo="$rjNo"
                        wire:key="ss-clinical-impression-ugd-{{ $rjNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ugd.satu-sehat.kirim-chief-complaint :rjNo="$rjNo"
                        wire:key="ss-chief-complaint-ugd-{{ $rjNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ugd.satu-sehat.kirim-allergy :rjNo="$rjNo"
                        wire:key="ss-allergy-ugd-{{ $rjNo ?? 'none' }}" />
                    <livewire:pages::transaksi.ugd.satu-sehat.kirim-penilaian :rjNo="$rjNo"
                        wire:key="ss-penilaian-ugd-{{ $rjNo ?? 'none' }}" />
                </div>
            </div>
        </div>
    </x-modal>
</div>
