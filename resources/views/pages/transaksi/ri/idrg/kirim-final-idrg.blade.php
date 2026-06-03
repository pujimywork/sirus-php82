<?php
// resources/views/pages/transaksi/ri/idrg/kirim-final-idrg.blade.php
// Step 8: Final iDRG + Edit Ulang iDRG.
// Manual 5.10.x: kalau stage 1 punya topup_options, stage 2 wajib dijalankan dulu.

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\iDRG\iDrgTrait;

new class extends Component {
    use EmrRITrait, iDrgTrait;

    public ?string $riHdrNo = null;
    public bool $hasClaim = false;
    public bool $hasGroup = false;
    public bool $idrgFinal = false;
    public ?string $idrgFinalAt = null;
    public bool $idrgUngroupable = false;
    public bool $needsStage2 = false;
    public bool $stage2Done = false;
    public bool $klaimFinal = false;

    // SITB (pasien TB) — wajib validasi No. Registrasi SITB sebelum final (cegah E2066).
    public bool $isTb = false;
    public string $nomorRegisterSitb = '';
    public bool $sitbValidated = false;
    public ?string $sitbValidatedAt = null;

    public function mount(?string $riHdrNo = null): void
    {
        $this->riHdrNo = $riHdrNo;
        $this->reloadState();
    }


    private function reloadState(): void
    {
        if (empty($this->riHdrNo)) {
            return;
        }
        $data = $this->findDataRI($this->riHdrNo);
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
        $this->nomorRegisterSitb = (string) ($sitb['nomor'] ?? '');
        $this->sitbValidated = !empty($sitb['validated']);
        $this->sitbValidatedAt = $sitb['validatedAt'] ?? null;
    }

    /* ===============================
     | SITB (pasien TB)
     =============================== */

    // Persist toggle "Pasien TB" segera supaya status bertahan saat reload / jadi pengingat validasi.
    public function updatedIsTb($value): void
    {
        if (empty($this->riHdrNo)) {
            return;
        }
        $data = $this->findDataRI($this->riHdrNo);
        $idrg = $data['idrg'] ?? [];
        $sitb = $idrg['sitb'] ?? [];
        $sitb['isTb'] = (bool) $value;
        $idrg['sitb'] = $sitb;
        $this->saveResult($idrg);
    }

    public function validateSitbAction(): void
    {
        if (empty($this->riHdrNo)) {
            return;
        }
        try {
            $data = $this->findDataRI($this->riHdrNo);
            $idrg = $data['idrg'] ?? [];
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) {
                $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.');
                return;
            }
            $nomor = trim($this->nomorRegisterSitb);
            if ($nomor === '') {
                $this->dispatch('toast', type: 'error', message: 'No. Registrasi SITB wajib diisi.');
                return;
            }

            $res = $this->validateSitb($nomorSep, $nomor)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Validasi SITB'));
                return;
            }

            $idrg['sitb'] = ['isTb' => true, 'nomor' => $nomor, 'validated' => true, 'validatedAt' => now()->toIso8601String()];
            $this->saveResult($idrg);
            $this->sitbValidated = true;
            $this->sitbValidatedAt = $idrg['sitb']['validatedAt'];
            $this->dispatch('toast', type: 'success', message: 'No. Registrasi SITB tervalidasi.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Validasi SITB gagal: ' . $e->getMessage());
        }
    }

    public function invalidateSitbAction(): void
    {
        if (empty($this->riHdrNo)) {
            return;
        }
        try {
            $data = $this->findDataRI($this->riHdrNo);
            $idrg = $data['idrg'] ?? [];
            $nomorSep = $idrg['nomorSep'] ?? null;
            if (empty($nomorSep)) {
                $this->dispatch('toast', type: 'error', message: 'Klaim belum dibuat.');
                return;
            }

            $res = $this->invalidateSitb($nomorSep)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Batalkan Validasi SITB'));
                return;
            }

            $sitb = $idrg['sitb'] ?? [];
            $sitb['validated'] = false;
            $sitb['validatedAt'] = null;
            $idrg['sitb'] = $sitb;
            $this->saveResult($idrg);
            $this->sitbValidated = false;
            $this->sitbValidatedAt = null;
            $this->dispatch('toast', type: 'success', message: 'Validasi SITB dibatalkan.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Batalkan validasi SITB gagal: ' . $e->getMessage());
        }
    }

    public function final(): void
    {
        if (empty($this->riHdrNo)) {
            return;
        }
        try {
            $data = $this->findDataRI($this->riHdrNo);
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
        if (empty($this->riHdrNo)) {
            return;
        }
        try {
            $data = $this->findDataRI($this->riHdrNo);
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
            $this->lockRIRow($this->riHdrNo);
            $data = $this->findDataRI($this->riHdrNo);
            $data['idrg'] = $idrg;
            $this->updateJsonRI($this->riHdrNo, $data);
        });
        $this->dispatch('idrg-section-changed-ri', riHdrNo: (string) $this->riHdrNo);
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

  {{-- SITB (pasien TB) — toggle reveal No. Registrasi SITB, wajib validasi sebelum Final --}}
  <div class="pt-3 border-t border-gray-100 dark:border-gray-700">
    <x-toggle wire:model.live="isTb" :trueValue="true" :falseValue="false"
        label="Pasien TB (perlu validasi No. Registrasi SITB)" :disabled="$idrgFinal" />
    @if ($isTb)
        <div class="flex flex-wrap items-end gap-2 mt-2">
            <div class="flex-1 min-w-[220px]">
                <x-input-label value="No. Registrasi SITB" class="text-sm" />
                <x-text-input wire:model="nomorRegisterSitb" :disabled="$sitbValidated || $idrgFinal"
                    placeholder="Nomor register SITB pasien TB"
                    class="font-mono text-sm {{ $sitbValidated ? 'bg-emerald-50 dark:bg-emerald-900/20' : '' }}" />
            </div>
            @if (!$sitbValidated)
                <x-primary-button type="button" wire:click="validateSitbAction" wire:loading.attr="disabled"
                    :disabled="$idrgFinal" class="!bg-brand hover:!bg-brand/90">
                    <span wire:loading.remove wire:target="validateSitbAction">Validasi SITB</span>
                    <span wire:loading wire:target="validateSitbAction"><x-loading />...</span>
                </x-primary-button>
            @else
                <button type="button" wire:click="invalidateSitbAction" wire:loading.attr="disabled" @disabled($idrgFinal)
                    class="px-3 py-1.5 text-sm font-medium text-amber-700 bg-amber-50 rounded-lg hover:bg-amber-100 disabled:opacity-50 dark:bg-amber-900/20 dark:text-amber-400 dark:hover:bg-amber-900/30">
                    <span wire:loading.remove wire:target="invalidateSitbAction">↶ Batalkan Validasi</span>
                    <span wire:loading wire:target="invalidateSitbAction"><x-loading />...</span>
                </button>
            @endif
        </div>
        @if ($sitbValidated)
            <div class="mt-1 text-sm text-emerald-600 dark:text-emerald-400">
                ✓ SITB tervalidasi
                @if ($sitbValidatedAt)— <span class="font-mono">{{ $sitbValidatedAt }}</span>@endif
            </div>
        @else
            <div class="mt-1 text-sm text-amber-600 dark:text-amber-400">
                Pasien TB wajib validasi SITB sebelum Final iDRG (cegah E2066).
            </div>
        @endif
    @endif
  </div>
</div>
