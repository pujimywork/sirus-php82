<?php
// resources/views/pages/transaksi/ugd/idrg/kirim-print-klaim.blade.php
// Step 16: Cetak Klaim (PDF) + Cek Status Klaim. Plus PDF viewer inline.

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\iDRG\iDrgTrait;

new class extends Component {
    use EmrUGDTrait, iDrgTrait;

    public ?string $rjNo = null;
    public bool $klaimFinal = false;
    public array $claimStatus = [];
    public ?string $pdfBase64 = null;
    public ?string $pdfNomorSep = null;

    public function mount(?string $rjNo = null): void
    {
        $this->rjNo = $rjNo;
        $this->reloadState();
    }


    private function reloadState(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        $data = $this->findDataUGD($this->rjNo);
        if (empty($data)) {
            return;
        }
        $idrg = $data['idrg'] ?? [];
        $this->klaimFinal = !empty($idrg['klaimFinal']);
        $this->claimStatus = $idrg['claimStatus'] ?? [];
    }

    public function print(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        try {
            $data = $this->findDataUGD($this->rjNo);
            $idrg = $data['idrg'] ?? [];
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) {
                $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.');
                return;
            }
            // Kriteria 23
            if (empty($idrg['klaimFinal'])) {
                $this->dispatch('toast', type: 'error', message: 'Klaim harus final terlebih dahulu.');
                return;
            }

            $res = $this->printClaim($nomorSep)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Cetak Klaim'));
                return;
            }

            $pdfBase64 = $res['response']['data'] ?? ($res['response'] ?? null);
            if (empty($pdfBase64) || !is_string($pdfBase64)) {
                $this->dispatch('toast', type: 'error', message: 'Response cetak tidak berisi PDF.');
                return;
            }

            $this->pdfBase64 = $pdfBase64;
            $this->pdfNomorSep = $nomorSep;
            $this->dispatch('toast', type: 'success', message: 'PDF klaim siap.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Cetak klaim gagal: ' . $e->getMessage());
        }
    }

    public function getStatus(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        try {
            $data = $this->findDataUGD($this->rjNo);
            $idrg = $data['idrg'] ?? [];
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) {
                $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.');
                return;
            }

            $res = $this->getClaimStatus($nomorSep)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Cek Status Klaim'));
                return;
            }

            $idrg['claimStatus'] = $res['response'] ?? [];
            $this->saveResult($idrg);
            $this->dispatch('toast', type: 'info', message: 'Status: ' . ($idrg['claimStatus']['nmStatusSep'] ?? '-'));
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Get claim status gagal: ' . $e->getMessage());
        }
    }

    private function saveResult(array $idrg): void
    {
        DB::transaction(function () use ($idrg) {
            $this->lockUGDRow($this->rjNo);
            $data = $this->findDataUGD($this->rjNo);
            $data['idrg'] = $idrg;
            $this->updateJsonUGD($this->rjNo, $data);
        });
        $this->dispatch('idrg-section-changed-ugd', rjNo: (string) $this->rjNo);
    }
};
?>

<div class="p-4 space-y-3 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700">
    <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div
                class="flex items-center justify-center w-8 h-8 rounded-full {{ !empty($pdfBase64) ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500' }}">
                <span class="text-sm font-bold">17</span>
            </div>
            <div>
                <div class="font-semibold text-gray-800 dark:text-gray-100">Cetak Klaim & Cek Status</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    @if (!empty($claimStatus))
                        Status: <span class="font-medium text-gray-700 dark:text-gray-300">{{ $claimStatus['nmStatusSep'] ?? '-' }}</span>
                    @else
                        claim_print + get_claim_status (BPJS).
                    @endif
                </div>
            </div>
        </div>
        <div class="flex flex-wrap items-center justify-end gap-2 shrink-0">
            <button type="button" wire:click="getStatus" wire:loading.attr="disabled" @disabled(!$klaimFinal)
                class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 disabled:opacity-50 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                <span wire:loading.remove wire:target="getStatus">Cek Status</span>
                <span wire:loading wire:target="getStatus"><x-loading />...</span>
            </button>
            <x-primary-button type="button" wire:click="print" wire:loading.attr="disabled" :disabled="!$klaimFinal"
                class="!bg-brand hover:!bg-brand/90 min-w-[160px] {{ !empty($pdfBase64) ? '!bg-emerald-600' : '' }}">
                <span wire:loading.remove wire:target="print">{{ !empty($pdfBase64) ? 'Cetak Ulang' : 'Cetak Klaim' }}</span>
                <span wire:loading wire:target="print"><x-loading />...</span>
            </x-primary-button>
        </div>
    </div>

    @if (!empty($pdfBase64))
        <div class="p-3 border-2 rounded-lg border-brand/40 dark:border-brand-lime/40">
            <div class="flex items-center justify-between mb-2">
                <div class="text-sm font-semibold text-brand dark:text-brand-lime">
                    PDF Klaim — SEP <span class="font-mono">{{ $pdfNomorSep ?? '-' }}</span>
                </div>
                <a href="data:application/pdf;base64,{{ $pdfBase64 }}"
                    download="klaim-{{ $pdfNomorSep ?? 'eklaim' }}.pdf"
                    class="px-3 py-1 text-sm font-semibold text-white rounded-lg bg-brand hover:bg-brand/90">
                    Download PDF
                </a>
            </div>
            <iframe src="data:application/pdf;base64,{{ $pdfBase64 }}"
                class="w-full h-[600px] border border-gray-200 rounded-lg dark:border-gray-700"
                title="PDF Klaim E-Klaim"></iframe>
        </div>
    @endif
</div>
