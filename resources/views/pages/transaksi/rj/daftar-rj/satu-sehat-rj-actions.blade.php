<?php
// Komponen Modal Satu Sehat RJ.
// Dipisah dari daftar-rj-actions supaya orchestrator tetap ramping.
// Trigger dari parent: dispatch event 'daftar-rj.openSatuSehat' dengan rjNo.

use Livewire\Component;
use Livewire\Attributes\On;
use App\Http\Traits\Txn\Rj\EmrRJTrait;

new class extends Component {
    use EmrRJTrait;

    public ?string $rjNo = null;
    public array $dataDaftarPoliRJ = [];

    public function mount(?string $initialRjNo = null): void
    {
        if (!empty($initialRjNo)) {
            $this->rjNo = $initialRjNo;
            $this->loadData();
        }
    }

    #[On('daftar-rj.openSatuSehat')]
    public function handleOpenSatuSehat(string $rjNo): void
    {
        $this->rjNo = $rjNo;

        if (!$this->loadData()) {
            return;
        }

        $this->dispatch('open-modal', name: 'rj-satu-sehat');
    }

    #[On('rj-satu-sehat.refresh')]
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
        $data = $this->findDataRJ($this->rjNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Jalan tidak ditemukan.');
            return false;
        }
        $this->dataDaftarPoliRJ = $data;
        return true;
    }
};
?>

<div>
    <x-modal name="rj-satu-sehat" size="full" height="full" focusable>
        <div class="flex flex-col min-h-0">
            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
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
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Kirim Satu Sehat
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    <span class="font-semibold">{{ $dataDaftarPoliRJ['regName'] ?? '-' }}</span>
                                    &mdash; RM: {{ $dataDaftarPoliRJ['regNo'] ?? '-' }}
                                    &mdash; RJ: {{ $rjNo ?? '-' }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <x-icon-button color="gray" type="button"
                        x-on:click="$dispatch('close-modal', { name: 'rj-satu-sehat' })">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY — 5 SFC self-contained --}}
            <div class="flex-1 px-6 py-6 overflow-y-auto bg-gray-50/70 dark:bg-gray-950/20">
                <div class="max-w-4xl mx-auto space-y-3">
                    <livewire:pages::transaksi.rj.satu-sehat.kirim-encounter :rjNo="$rjNo"
                        wire:key="ss-encounter-rj-{{ $rjNo ?? 'none' }}" />
                    <livewire:pages::transaksi.rj.satu-sehat.kirim-condition :rjNo="$rjNo"
                        wire:key="ss-condition-rj-{{ $rjNo ?? 'none' }}" />
                    <livewire:pages::transaksi.rj.satu-sehat.kirim-observation :rjNo="$rjNo"
                        wire:key="ss-observation-rj-{{ $rjNo ?? 'none' }}" />
                    <livewire:pages::transaksi.rj.satu-sehat.kirim-procedure :rjNo="$rjNo"
                        wire:key="ss-procedure-rj-{{ $rjNo ?? 'none' }}" />
                    <livewire:pages::transaksi.rj.satu-sehat.kirim-medication-request :rjNo="$rjNo"
                        wire:key="ss-medication-request-rj-{{ $rjNo ?? 'none' }}" />
                </div>
            </div>
        </div>
    </x-modal>
</div>
