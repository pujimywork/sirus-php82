<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public string $accId        = '';
    public string $accDesc      = '';
    public string $accDkStatus  = 'D';
    public string $tanggal      = '';
    public string $tahun        = '';
    public string $saldoCurrent = '0';
    public string $saldoTarget  = '0';

    public array $renderVersions = [];
    protected array $renderAreas = ['modal'];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    #[On('keuangan.saldo-kas.openEdit')]
    public function openEdit(string $accId, string $tanggal): void
    {
        if (!auth()->user()?->hasRole('Admin')) {
            $this->dispatch('toast', type: 'error', message: 'Hanya admin yang bisa mengedit saldo.');
            return;
        }

        $row = DB::table('acmst_accounts')
            ->select('acc_id', 'acc_name', 'acc_dk_status')
            ->where('acc_id', $accId)
            ->first();

        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Akun kas tidak ditemukan.');
            return;
        }

        $this->accId       = (string) $row->acc_id;
        $this->accDesc     = (string) ($row->acc_name ?? '');
        $this->accDkStatus = (string) ($row->acc_dk_status ?? 'D');
        $this->tanggal     = $tanggal;
        $this->tahun       = substr($tanggal, 0, 4);

        $this->saldoCurrent = (string) $this->hitungSaldoTanggal($this->accId, $this->accDkStatus, $tanggal);
        $this->saldoTarget  = $this->saldoCurrent;

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'saldo-kas-actions');
    }

    /**
     * Saldo current — sama formula dgn parent.
     */
    private function hitungSaldoTanggal(string $accId, string $dkStatus, string $tanggal): float
    {
        $tahun = (int) substr($tanggal, 0, 4);

        $sa = DB::table('tktxn_saldoawalakuns')
            ->where('acc_id', $accId)->where('sa_year', (string) $tahun)->first();

        $saldoAwalTahun = $dkStatus === 'D'
            ? (float) ($sa->sa_acc_d ?? 0)
            : (float) ($sa->sa_acc_k ?? 0);

        if ($dkStatus === 'D') {
            $arus = (float) DB::table('tkview_accounts')
                ->where('txn_acc_k', $accId)
                ->whereBetween(DB::raw("TO_CHAR(txn_date,'YYYY-MM-DD')"), [
                    sprintf('%04d-01-01', $tahun), $tanggal,
                ])
                ->sum(DB::raw('NVL(txn_k,0) - NVL(txn_d,0)'));
        } else {
            $arus = (float) DB::table('tkview_accounts')
                ->where('txn_acc', $accId)
                ->whereBetween(DB::raw("TO_CHAR(txn_date,'YYYY-MM-DD')"), [
                    sprintf('%04d-01-01', $tahun), $tanggal,
                ])
                ->sum(DB::raw('NVL(txn_d,0) - NVL(txn_k,0)'));
        }

        return $saldoAwalTahun + $arus;
    }

    public function save(): void
    {
        if (!auth()->user()?->hasRole('Admin')) {
            $this->dispatch('toast', type: 'error', message: 'Hanya admin yang bisa mengedit saldo.');
            return;
        }

        $this->validate([
            'saldoTarget' => 'required|numeric',
        ], [
            'saldoTarget.required' => 'Saldo target wajib diisi.',
            'saldoTarget.numeric'  => 'Saldo target harus berupa angka.',
        ]);

        $target = (float) $this->saldoTarget;
        $tahun  = (int) $this->tahun;

        // Mengikuti legacy: arus_year = sum total tahun ini (Jan–Des).
        // updatesaldo = target_saldo - arus_year → simpan ke saldo_awal_tahun.
        if ($this->accDkStatus === 'D') {
            $arusYear = (float) DB::table('tkview_accounts')
                ->where('txn_acc_k', $this->accId)
                ->whereRaw("TO_CHAR(txn_date,'YYYY') = ?", [(string) $tahun])
                ->sum(DB::raw('NVL(txn_k,0) - NVL(txn_d,0)'));

            $updateSaldo = $target - $arusYear;

            $exists = DB::table('tktxn_saldoawalakuns')
                ->where('acc_id', $this->accId)->where('sa_year', (string) $tahun)->exists();

            if ($exists) {
                DB::table('tktxn_saldoawalakuns')
                    ->where('acc_id', $this->accId)->where('sa_year', (string) $tahun)
                    ->update(['sa_acc_d' => $updateSaldo]);
            } else {
                DB::table('tktxn_saldoawalakuns')->insert([
                    'acc_id'   => $this->accId,
                    'sa_year'  => (string) $tahun,
                    'sa_acc_d' => $updateSaldo,
                    'sa_acc_k' => 0,
                ]);
            }
        } else {
            $arusYear = (float) DB::table('tkview_accounts')
                ->where('txn_acc', $this->accId)
                ->whereRaw("TO_CHAR(txn_date,'YYYY') = ?", [(string) $tahun])
                ->sum(DB::raw('NVL(txn_d,0) - NVL(txn_k,0)'));

            $updateSaldo = $target - $arusYear;

            $exists = DB::table('tktxn_saldoawalakuns')
                ->where('acc_id', $this->accId)->where('sa_year', (string) $tahun)->exists();

            if ($exists) {
                DB::table('tktxn_saldoawalakuns')
                    ->where('acc_id', $this->accId)->where('sa_year', (string) $tahun)
                    ->update(['sa_acc_k' => $updateSaldo]);
            } else {
                DB::table('tktxn_saldoawalakuns')->insert([
                    'acc_id'   => $this->accId,
                    'sa_year'  => (string) $tahun,
                    'sa_acc_d' => 0,
                    'sa_acc_k' => $updateSaldo,
                ]);
            }
        }

        $this->dispatch('toast', type: 'success',
            message: "Saldo awal tahun {$tahun} di-update untuk akun {$this->accId}.");
        $this->closeModal();
        $this->dispatch('keuangan.saldo-kas.saved');
    }

    public function closeModal(): void
    {
        $this->reset(['accId', 'accDesc', 'accDkStatus',
                      'tanggal', 'tahun', 'saldoCurrent', 'saldoTarget']);
        $this->resetValidation();
        $this->dispatch('close-modal', name: 'saldo-kas-actions');
        $this->resetVersion();
    }
};
?>

<div>
    <x-modal name="saldo-kas-actions" focusable>
        <div class="p-6 space-y-5"
             wire:key="{{ $this->renderKey('modal', [$accId, $tanggal]) }}">

            <div>
                <h2 class="text-lg font-semibold text-ink dark:text-gray-100">
                    Edit Saldo Awal Tahun
                </h2>
                <p class="mt-1 text-sm text-muted dark:text-gray-400">
                    Sistem akan back-calc saldo awal tahun {{ $tahun }} agar saldo per
                    {{ $tanggal ? \Carbon\Carbon::parse($tanggal)->format('d/m/Y') : '' }}
                    sama dengan target yang Anda tentukan.
                </p>
            </div>

            <x-border-form title="Konteks">
                <dl class="grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs text-muted dark:text-gray-400">Akun Kas</dt>
                        <dd class="font-medium">
                            <span class="font-mono">{{ $accId }}</span> — {{ $accDesc }}
                            <span class="ml-1 px-1.5 text-[10px] rounded {{ $accDkStatus === 'D' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700' }}">
                                {{ $accDkStatus }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted dark:text-gray-400">Saldo Saat Ini</dt>
                        <dd class="font-mono font-medium">
                            Rp {{ number_format((float) $saldoCurrent, 0, ',', '.') }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted dark:text-gray-400">Tahun Saldo Awal</dt>
                        <dd class="font-medium">{{ $tahun }}</dd>
                    </div>
                </dl>
            </x-border-form>

            <div>
                <x-input-label for="saldoTarget" value="Saldo Target Per Tanggal" :required="true" />
                <x-text-input id="saldoTarget" type="number" step="0.01"
                    wire:model.live="saldoTarget"
                    :error="$errors->has('saldoTarget')"
                    class="block w-full mt-1 font-mono" />
                <p class="mt-1 text-xs text-muted dark:text-gray-400">
                    Saldo awal tahun akan dihitung mundur agar posisi per tanggal = nilai ini.
                </p>
                <x-input-error :messages="$errors->get('saldoTarget')" class="mt-1" />
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <x-secondary-button type="button" wire:click="closeModal">Batal</x-secondary-button>
                <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled">
                    <span wire:loading.remove>Simpan Saldo</span>
                    <span wire:loading>Saving...</span>
                </x-primary-button>
            </div>
        </div>
    </x-modal>
</div>
