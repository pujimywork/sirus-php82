<?php
// Step 2: Buat Klaim Baru — registrasi SEP ke E-Klaim Kemenkes.
// Plus tombol Delete Klaim sebagai action sekunder (visible setelah klaim ada).
// (komentar dihindari pakai kata `n e w` standalone supaya Volt parser tidak salah wrap)

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\iDRG\iDrgTrait;

new class extends Component {
    use EmrUGDTrait, MasterPasienTrait, iDrgTrait;

    public ?string $rjNo = null;
    public ?string $nomorSep = null;
    public ?string $patientId = null;
    public ?string $admissionId = null;
    public ?string $hospitalAdmissionId = null;
    public bool $hasClaim = false;
    public bool $hasClaimNumber = false;
    public bool $hasSepRaw = false;
    public bool $idrgFinal = false;

    public function mount(?string $rjNo = null): void
    {
        $this->rjNo = $rjNo;
        $this->reloadState();
    }

    #[On('idrg-state-updated-ugd')]
    public function onStateUpdated(string $rjNo): void
    {
        if ((string) $this->rjNo !== $rjNo) {
            return;
        }
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
        $this->nomorSep = $idrg['nomorSep'] ?? null;
        $this->patientId = $idrg['patientId'] ?? null;
        $this->admissionId = $idrg['admissionId'] ?? null;
        $this->hospitalAdmissionId = $idrg['hospitalAdmissionId'] ?? null;
        $this->hasClaim = !empty($this->nomorSep);
        $this->hasClaimNumber = !empty($idrg['claimNumber']);
        $this->hasSepRaw = !empty(data_get($data, 'sep.noSep'));
        $this->idrgFinal = !empty($idrg['idrgFinal']);
    }

    public function newClaimAction(): void
    {
        if (empty($this->rjNo)) {
            return;
        }
        try {
            $data = $this->findDataUGD($this->rjNo);
            if (empty($data)) {
                throw new \RuntimeException('Data RJ tidak ditemukan.');
            }
            $pasien = $this->findDataMasterPasien($data['regNo'] ?? '')['pasien'] ?? [];
            $idrg = $data['idrg'] ?? [];

            $nomorSep = $idrg['claimNumber'] ?? ($data['sep']['noSep'] ?? '');
            if (empty($nomorSep)) {
                $this->dispatch('toast', type: 'error', message: 'Nomor SEP / claim_number kosong. Pasang SEP VClaim dulu atau generate_claim_number.');
                return;
            }

            $nomorKartu = data_get($pasien, 'identitas.idbpjs')
                ?: data_get($data, 'sep.resSep.peserta.noKartu')
                ?: data_get($data, 'sep.reqSep.t_sep.noKartu')
                ?: '';
            $nomorRm = $data['regNo'] ?? '';
            $namaPasien = $pasien['regName'] ?? ($data['regName'] ?? '');
            $tglLahir = $this->parseBirth($pasien['regBirth'] ?? '');
            $gender = ($pasien['regSex'] ?? 'L') === 'P' ? 2 : 1;

            $res = $this->newClaim($nomorKartu, $nomorSep, $nomorRm, $namaPasien, $tglLahir, $gender)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Buat Klaim Baru'));
                return;
            }

            $idrg['nomorSep'] = $nomorSep;
            $idrg['patientId'] = $res['response']['patient_id'] ?? null;
            $idrg['admissionId'] = $res['response']['admission_id'] ?? null;
            $idrg['hospitalAdmissionId'] = $res['response']['hospital_admission_id'] ?? null;
            $idrg['createdAt'] = now()->toIso8601String();
            $this->saveResult($idrg);
            $this->dispatch('toast', type: 'success', message: "Claim dibuat untuk SEP {$nomorSep}");
            $this->dispatch('idrg-section-changed-ugd', rjNo: (string) $this->rjNo);
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'new_claim gagal: ' . $e->getMessage());
        }
    }

    public function deleteClaimAction(): void
    {
        if (empty($this->rjNo) || empty($this->nomorSep)) {
            return;
        }
        try {
            $coderNik = (string) (auth()->user()->emp_id ?? '');
            if (empty($coderNik)) {
                $this->dispatch('toast', type: 'error', message: 'User aktif tidak punya emp_id. Hubungi admin untuk set Karyawan di profil user.');
                return;
            }

            $res = $this->deleteClaim($this->nomorSep, $coderNik)->getOriginalContent();
            if (($res['metadata']['code'] ?? 0) != 200) {
                $this->dispatch('toast', type: 'error', message: self::describeEklaimError($res['metadata'] ?? [], 'Hapus Klaim'));
                return;
            }

            $nomorSep = $this->nomorSep;
            $this->saveResult([]);
            $this->dispatch('toast', type: 'success', message: "Klaim {$nomorSep} dihapus.");
            $this->dispatch('close-modal', name: 'ugd-idrg');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'delete_claim gagal: ' . $e->getMessage());
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
        $this->dispatch('idrg-state-updated-ugd', rjNo: (string) $this->rjNo);
    }

    private function parseBirth(string $str): string
    {
        if (empty($str)) {
            return Carbon::now()->format('Y-m-d H:i:s');
        }
        try {
            return Carbon::createFromFormat('d/m/Y', $str)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            try {
                return Carbon::parse($str)->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                return Carbon::now()->format('Y-m-d H:i:s');
            }
        }
    }
};
?>

<div class="p-4 space-y-3 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700">
    <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div
                class="flex items-center justify-center w-8 h-8 rounded-full {{ $hasClaim ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500' }}">
                <span class="text-sm font-bold">2</span>
            </div>
            <div>
                <div class="font-semibold text-gray-800 dark:text-gray-100">Buat Klaim Baru</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Kirim new_claim ke E-Klaim Kemenkes.</div>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if ($hasClaim && !$idrgFinal)
                <button type="button" wire:click="deleteClaimAction" wire:loading.attr="disabled"
                    wire:confirm="Yakin hapus klaim {{ $nomorSep }}? Semua progress iDRG/INACBG ikut hilang."
                    class="px-3 py-1.5 text-xs font-medium text-red-700 bg-red-50 rounded-lg hover:bg-red-100 disabled:opacity-50 dark:bg-red-900/20 dark:text-red-400 dark:hover:bg-red-900/30">
                    <span wire:loading.remove wire:target="deleteClaimAction">Hapus Klaim</span>
                    <span wire:loading wire:target="deleteClaimAction"><x-loading />...</span>
                </button>
            @endif
            <x-primary-button type="button" wire:click="newClaimAction" wire:loading.attr="disabled"
                :disabled="$hasClaim || (!$hasSepRaw && !$hasClaimNumber)"
                class="!bg-brand hover:!bg-brand/90 {{ $hasClaim ? '!bg-emerald-600' : '' }}">
                <span wire:loading.remove wire:target="newClaimAction">{{ $hasClaim ? 'Selesai' : 'Buat Klaim Baru' }}</span>
                <span wire:loading wire:target="newClaimAction"><x-loading />...</span>
            </x-primary-button>
        </div>
    </div>

    @if ($hasClaim)
        <div class="grid grid-cols-2 gap-3 px-3 py-2 text-xs rounded-lg bg-gray-50 md:grid-cols-4 dark:bg-gray-800">
            <div>
                <div class="text-gray-500">Nomor SEP</div>
                <div class="font-mono font-semibold text-gray-800 dark:text-gray-100">{{ $nomorSep ?? '-' }}</div>
            </div>
            <div>
                <div class="text-gray-500">Patient ID</div>
                <div class="font-mono text-gray-700 dark:text-gray-300">{{ $patientId ?? '-' }}</div>
            </div>
            <div>
                <div class="text-gray-500">Admission ID</div>
                <div class="font-mono text-gray-700 dark:text-gray-300">{{ $admissionId ?? '-' }}</div>
            </div>
            <div>
                <div class="text-gray-500">Hospital Admission ID</div>
                <div class="font-mono text-gray-700 dark:text-gray-300">{{ $hospitalAdmissionId ?? '-' }}</div>
            </div>
        </div>
    @endif
</div>
