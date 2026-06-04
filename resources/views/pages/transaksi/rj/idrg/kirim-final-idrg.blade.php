<?php
// resources/views/pages/transaksi/rj/idrg/kirim-final-idrg.blade.php
// Step 8: Final iDRG + Edit Ulang iDRG.
// Manual 5.10.x: kalau stage 1 punya topup_options, stage 2 wajib dijalankan dulu.

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\iDRG\iDrgTrait;

new class extends Component {
    use EmrRJTrait, iDrgTrait;

    public ?string $rjNo = null;
    public bool $hasClaim = false;
    public bool $hasGroup = false;
    public bool $idrgFinal = false;
    public ?string $idrgFinalAt = null;
    public bool $idrgUngroupable = false;
    public bool $needsStage2 = false;
    public bool $stage2Done = false;
    public bool $klaimFinal = false;

    // SITB (pasien TB) — toggle & validasi pindah ke step Simpan Data Klaim (bawah DPJP).
    // Di sini tinggal state untuk disable tombol Final + guard di final() (cegah E2066).
    public bool $isTb = false;
    public bool $sitbValidated = false;

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
        $data = $this->findDataRJ($this->rjNo);
        if (empty($data)) {
            return;
        }
        $idrg = $data['idrg'] ?? [];
        $this->hasClaim = !empty($idrg['nomorSep']);
        $this->hasGroup = !empty($idrg['idrgGroup']);
        $this->idrgFinal = !empty($idrg['idrgFinal']);
        $this->idrgFinalAt = $idrg['idrgFinalAt'] ?? null;
        $this->idrgUngroupable = !empty($idrg['idrgUngroupable']);
        $this->needsStage2 = !empty($idrg['idrgGroup']['topup_options']);
        $this->stage2Done = !empty($idrg['idrgStage2']);
        $this->klaimFinal = !empty($idrg['klaimFinal']);

        $sitb = $idrg['sitb'] ?? [];
        $this->isTb = !empty($sitb['isTb']);
        $this->sitbValidated = !empty($sitb['validated']);
    }

    public function final(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        try {
            $data = $this->findDataRJ($this->rjNo);
            $idrg = $data['idrg'] ?? [];
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) {
                $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.');
                return;
            }
            if (!empty($idrg['idrgUngroupable'])) {
                $this->dispatch('toast', type: 'error', message: 'Tidak bisa final: grouping iDRG masih ungroupable.');
                return;
            }
            if (empty($idrg['idrgGroup'])) {
                $this->dispatch('toast', type: 'error', message: 'Belum ada grouping iDRG.');
                return;
            }
            if (!empty($idrg['idrgGroup']['topup_options']) && empty($idrg['idrgStage2'])) {
                $this->dispatch('toast', type: 'error', message: 'Stage 1 menghasilkan topup_options — Stage 2 wajib dijalankan dulu.');
                return;
            }
            if (!empty($idrg['sitb']['isTb']) && empty($idrg['sitb']['validated'])) {
                $this->dispatch('toast', type: 'error', message: 'Pasien TB: validasi No. Registrasi SITB dulu sebelum Final iDRG (cegah error E2066).');
                return;
            }

            $res = $this->finalIdrg($nomorSep)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Final iDRG'));
                return;
            }

            $idrg['idrgFinal'] = true;
            $idrg['idrgFinalAt'] = now()->toIso8601String();
            $this->saveResult($idrg);
            $this->dispatch('toast', type: 'success', message: 'iDRG final.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Final iDRG gagal: ' . $e->getMessage());
        }
    }

    public function reedit(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        try {
            $data = $this->findDataRJ($this->rjNo);
            $idrg = $data['idrg'] ?? [];
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) {
                $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.');
                return;
            }

            $res = $this->reeditIdrg($nomorSep)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Edit Ulang iDRG'));
                return;
            }

            $idrg['idrgFinal'] = false;
            $idrg['idrgFinalAt'] = null;
            // Clear hasil grouping (stale) — user wajib re-group setelah edit coding.
            // Card step 6 auto-hidden karena @if (!empty($idrgGroup)).
            $idrg['idrgGroup'] = [];
            $idrg['idrgUngroupable'] = false;
            $idrg['idrgStage2'] = [];
            $idrg['idrgTopupCodesInput'] = '';
            $this->saveResult($idrg);
            $this->dispatch('toast', type: 'success', message: 'iDRG dibuka untuk edit ulang. Hasil grouping di-clear, silakan re-group setelah edit coding.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Re-edit iDRG gagal: ' . $e->getMessage());
        }
    }

    private function saveResult(array $idrg): void
    {
        DB::transaction(function () use ($idrg) {
            $this->lockRJRow($this->rjNo);
            $data = $this->findDataRJ($this->rjNo);
            $data['idrg'] = $idrg;
            $this->updateJsonRJ($this->rjNo, $data);
        });
        $this->dispatch('idrg-section-changed', rjNo: (string) $this->rjNo);
    }
};
?>

<div class="p-4 space-y-3 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700">
  <div class="flex items-center justify-between">
    <div class="flex items-center gap-3">
        <div
            class="flex items-center justify-center w-8 h-8 rounded-full {{ $idrgFinal ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500' }}">
            <span class="text-sm font-bold">8</span>
        </div>
        <div>
            <div class="font-semibold text-gray-800 dark:text-gray-100">Final iDRG</div>
            <div class="text-sm text-gray-500 dark:text-gray-400">
                @if ($idrgUngroupable)
                    <span class="text-rose-600 dark:text-rose-400">Ungroupable — tidak bisa final.</span>
                @elseif ($needsStage2 && !$stage2Done)
                    <span class="text-amber-600 dark:text-amber-400">Stage 2 belum dijalankan — final terkunci.</span>
                @elseif ($idrgFinal && $idrgFinalAt)
                    Final pada <span class="font-mono">{{ $idrgFinalAt }}</span>
                @else
                    Finalisasi coding iDRG.
                @endif
            </div>
        </div>
    </div>
    <div class="flex flex-wrap items-center justify-end gap-2 shrink-0">
        @if ($idrgFinal && !$klaimFinal)
            <button type="button" wire:click="reedit" wire:loading.attr="disabled"
                wire:confirm="Buka kembali iDRG untuk edit ulang?"
                class="px-3 py-1.5 text-sm font-medium text-amber-700 bg-amber-50 rounded-lg hover:bg-amber-100 disabled:opacity-50 dark:bg-amber-900/20 dark:text-amber-400 dark:hover:bg-amber-900/30">
                <span wire:loading.remove wire:target="reedit">↶ Edit Ulang iDRG</span>
                <span wire:loading wire:target="reedit"><x-loading />...</span>
            </button>
        @endif
        <x-primary-button type="button" wire:click="final" wire:loading.attr="disabled"
            :disabled="!$hasClaim || !$hasGroup || $idrgUngroupable || $idrgFinal || ($needsStage2 && !$stage2Done) || ($isTb && !$sitbValidated)"
            class="!bg-brand hover:!bg-brand/90 min-w-[160px] {{ $idrgFinal ? '!bg-emerald-600' : '' }}">
            <span wire:loading.remove wire:target="final">{{ $idrgFinal ? 'Selesai' : 'Final iDRG' }}</span>
            <span wire:loading wire:target="final"><x-loading />...</span>
        </x-primary-button>
    </div>
  </div>

  {{-- SITB (pasien TB) — toggle & validasi ada di step Simpan Data Klaim (bawah DPJP) --}}
  @if ($isTb && !$sitbValidated)
      <div class="pt-3 text-sm border-t border-gray-100 dark:border-gray-700 text-amber-600 dark:text-amber-400">
          Pasien TB wajib validasi No. Registrasi SITB sebelum Final iDRG (cegah E2066) —
          validasi di step Simpan Data Klaim (bawah field DPJP).
      </div>
  @endif
</div>
