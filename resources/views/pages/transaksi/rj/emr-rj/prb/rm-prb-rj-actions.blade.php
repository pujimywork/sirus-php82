<?php
// resources/views/pages/transaksi/rj/emr-rj/prb/rm-prb-rj-actions.blade.php
// Program Rujuk Balik (PRB) — Rawat Jalan

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\BPJS\VclaimTrait;

new class extends Component {
    use EmrRJTrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;

    public array $dataDaftarPoliRJ = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-prb-rj'];

    // Form PRB — terpisah dari dataDaftarPoliRJ agar aman dari re-fetch
    public array $formPRB = [];

    // Step PRB: 1=Data Pasien & Program, 2=Obat, 3=Kirim
    public int $stepPRB = 1;

    // Referensi dari BPJS
    public array $listDiagnosaPRB = [];
    public array $listObatPRB = [];
    public string $searchObat = '';
    public bool $showObatLov = false;

    // Obat PRB
    public array $formObat = [
        'kdObat' => '',
        'namaObat' => '',
        'signa1' => '1',
        'signa2' => '1',
        'jmlObat' => '',
    ];

    /* ═══════════════════════════════════════
     | MOUNT
    ═══════════════════════════════════════ */
    public function mount(): void
    {
        $this->registerAreas(['modal-prb-rj']);
        $this->openPRB($this->rjNo);
    }

    public function rendering(): void
    {
        $default = $this->getDefaultPRB();
        $current = $this->dataDaftarPoliRJ['prb'] ?? [];
        $this->dataDaftarPoliRJ['prb'] = array_replace_recursive($default, $current);
    }

    /* ═══════════════════════════════════════
     | OPEN
    ═══════════════════════════════════════ */
    public function openPRB(int $rjNo): void
    {
        $this->resetFormEntry();
        $this->rjNo = $rjNo;
        $this->resetValidation();

        $dataDaftarPoliRJ = $this->findDataRJ($rjNo);
        if (!$dataDaftarPoliRJ) {
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Jalan tidak ditemukan.');
            return;
        }

        $this->dataDaftarPoliRJ = $dataDaftarPoliRJ;

        $this->formPRB = !empty($dataDaftarPoliRJ['prb']) && is_array($dataDaftarPoliRJ['prb'])
            ? $dataDaftarPoliRJ['prb']
            : $this->getDefaultPRB();

        if ($this->checkEmrRJStatus($rjNo)) {
            $this->isFormLocked = true;
        }

        $this->incrementVersion('modal-prb-rj');
    }

    protected function resetFormEntry(): void
    {
        $this->reset(['formPRB', 'formObat', 'listObatPRB', 'searchObat', 'showObatLov', 'stepPRB']);
        $this->stepPRB = 1;
        $this->resetVersion();
        $this->isFormLocked = false;
    }

    /* ═══════════════════════════════════════
     | STEP NAVIGATION
    ═══════════════════════════════════════ */
    public function nextStep(): void
    {
        if ($this->stepPRB === 1) {
            // Validasi step 1 sebelum lanjut
            $this->validate([
                'formPRB.noSep'      => 'required',
                'formPRB.noKartu'    => 'required',
                'formPRB.alamat'     => 'required',
                'formPRB.programPRB' => 'required',
                'formPRB.kodeDPJP'   => 'required',
                'formPRB.keterangan' => 'required',
                'formPRB.saran'      => 'required',
            ]);
        }
        if ($this->stepPRB < 3) {
            $this->stepPRB++;
        }
    }

    public function prevStep(): void
    {
        if ($this->stepPRB > 1) {
            $this->stepPRB--;
        }
    }

    public function goToStep(int $step): void
    {
        if ($step >= 1 && $step <= 3) {
            $this->stepPRB = $step;
        }
    }

    /* ═══════════════════════════════════════
     | DEFAULT PRB STRUCTURE
    ═══════════════════════════════════════ */
    private function getDefaultPRB(): array
    {
        $noSEP = DB::table('rsview_rjkasir')->where('rj_no', $this->rjNo)->value('vno_sep') ?? '';

        // Ambil data pasien dari dataDaftarPoliRJ
        $regNo = $this->dataDaftarPoliRJ['regNo'] ?? '';
        $pasien = $regNo ? DB::table('rsmst_pasiens')
            ->select('reg_no', 'no_bpjs', 'reg_address', 'reg_email')
            ->where('reg_no', $regNo)
            ->first() : null;

        // Kode DPJP dari dokter RJ
        $drId = $this->dataDaftarPoliRJ['drId'] ?? '';
        $kodeDPJP = $drId ? (DB::table('rsmst_doctors')->where('dr_id', $drId)->value('kd_dr_bpjs') ?? '') : '';

        return [
            'noSrb'      => '',  // dari BPJS setelah insert
            'noSep'      => $noSEP,
            'noKartu'    => $pasien->no_bpjs ?? '',
            'alamat'     => $pasien->reg_address ?? '',
            'email'      => $pasien->reg_email ?? '',
            'programPRB' => '',
            'programPRBNama' => '',
            'kodeDPJP'   => $kodeDPJP,
            'kodeDPJPNama' => $this->dataDaftarPoliRJ['drDesc'] ?? '',
            'keterangan' => '',
            'saran'      => '',
            'obat'       => [],
        ];
    }

    /* ═══════════════════════════════════════
     | OBAT — Tambah / Hapus
    ═══════════════════════════════════════ */
    public function tambahObat(): void
    {
        if (empty($this->formObat['kdObat'])) {
            $this->dispatch('toast', type: 'warning', message: 'Kode obat harus diisi.');
            return;
        }
        if (empty($this->formObat['jmlObat'])) {
            $this->dispatch('toast', type: 'warning', message: 'Jumlah obat harus diisi.');
            return;
        }

        $this->formPRB['obat'][] = [
            'kdObat'   => $this->formObat['kdObat'],
            'namaObat' => $this->formObat['namaObat'],
            'signa1'   => $this->formObat['signa1'],
            'signa2'   => $this->formObat['signa2'],
            'jmlObat'  => $this->formObat['jmlObat'],
        ];

        $this->formObat = ['kdObat' => '', 'namaObat' => '', 'signa1' => '1', 'signa2' => '1', 'jmlObat' => ''];
        $this->incrementVersion('modal-prb-rj');
    }

    public function hapusObat(int $index): void
    {
        unset($this->formPRB['obat'][$index]);
        $this->formPRB['obat'] = array_values($this->formPRB['obat']);
        $this->incrementVersion('modal-prb-rj');
    }

    /* ═══════════════════════════════════════
     | LOAD DIAGNOSA PRB dari BPJS
    ═══════════════════════════════════════ */
    public function loadDiagnosaPRB(): void
    {
        if (!empty($this->listDiagnosaPRB)) {
            return; // sudah dimuat
        }

        try {
            $response = VclaimTrait::ref_diagnosa_prb()->getOriginalContent();
            $code = $response['metadata']['code'] ?? 500;

            if ($code == 200) {
                $this->listDiagnosaPRB = $response['response']['list'] ?? [];
            } else {
                $this->dispatch('toast', type: 'warning', message: 'Diagnosa PRB: ' . ($response['metadata']['message'] ?? '-'));
            }
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Error diagnosa PRB: ' . $e->getMessage());
        }
    }

    /* ═══════════════════════════════════════
     | CARI OBAT GENERIK PRB dari BPJS
    ═══════════════════════════════════════ */
    public function cariObatPRB(): void
    {
        if (strlen($this->searchObat) < 3) {
            $this->dispatch('toast', type: 'warning', message: 'Keyword obat minimal 3 karakter.');
            return;
        }

        try {
            $response = VclaimTrait::ref_obat_prb($this->searchObat)->getOriginalContent();
            $code = $response['metadata']['code'] ?? 500;

            if ($code == 200) {
                $this->listObatPRB = $response['response']['list'] ?? [];
                $this->showObatLov = true;

                if (empty($this->listObatPRB)) {
                    $this->dispatch('toast', type: 'warning', message: 'Obat tidak ditemukan.');
                }
            } else {
                $this->listObatPRB = [];
                $this->dispatch('toast', type: 'warning', message: 'Cari obat: ' . ($response['metadata']['message'] ?? '-'));
            }
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Error cari obat: ' . $e->getMessage());
        }

        $this->incrementVersion('modal-prb-rj');
    }

    public function pilihObat(int $index): void
    {
        $obat = $this->listObatPRB[$index] ?? null;
        if (!$obat) {
            return;
        }

        $this->formObat['kdObat'] = $obat['kode'] ?? '';
        $this->formObat['namaObat'] = $obat['nama'] ?? '';
        $this->showObatLov = false;
        $this->listObatPRB = [];
        $this->searchObat = '';

        $this->incrementVersion('modal-prb-rj');
    }

    /* ═══════════════════════════════════════
     | VALIDATION
    ═══════════════════════════════════════ */
    protected function rules(): array
    {
        return [
            'formPRB.noSep'      => 'required',
            'formPRB.noKartu'    => 'required',
            'formPRB.alamat'     => 'required',
            'formPRB.programPRB' => 'required',
            'formPRB.kodeDPJP'   => 'required',
            'formPRB.keterangan' => 'required',
            'formPRB.saran'      => 'required',
        ];
    }

    protected function messages(): array
    {
        return [
            'formPRB.noSep.required'      => 'No. SEP wajib diisi.',
            'formPRB.noKartu.required'    => 'No. Kartu BPJS wajib diisi.',
            'formPRB.alamat.required'     => 'Alamat wajib diisi.',
            'formPRB.programPRB.required' => 'Program PRB wajib dipilih.',
            'formPRB.kodeDPJP.required'   => 'Kode DPJP wajib diisi.',
            'formPRB.keterangan.required' => 'Keterangan wajib diisi.',
            'formPRB.saran.required'      => 'Saran dokter wajib diisi.',
        ];
    }

    /* ═══════════════════════════════════════
     | PUSH PRB ke BPJS
    ═══════════════════════════════════════ */
    private function pushPRBtoBPJS(): void
    {
        if (($this->dataDaftarPoliRJ['klaimStatus'] ?? '') !== 'BPJS' && ($this->dataDaftarPoliRJ['klaimId'] ?? '') !== 'JM') {
            return;
        }

        $isUpdate = !empty($this->formPRB['noSrb']);

        // Build obat array tanpa namaObat (tidak diperlukan BPJS)
        $obatBPJS = collect($this->formPRB['obat'] ?? [])->map(fn($o) => [
            'kdObat'  => $o['kdObat'],
            'signa1'  => $o['signa1'],
            'signa2'  => $o['signa2'],
            'jmlObat' => $o['jmlObat'],
        ])->toArray();

        $payload = [
            'noSep'      => $this->formPRB['noSep'],
            'noKartu'    => $this->formPRB['noKartu'],
            'alamat'     => $this->formPRB['alamat'],
            'email'      => $this->formPRB['email'] ?? '',
            'programPRB' => $this->formPRB['programPRB'],
            'kodeDPJP'   => $this->formPRB['kodeDPJP'],
            'keterangan' => $this->formPRB['keterangan'],
            'saran'      => $this->formPRB['saran'],
            'user'       => (string) (auth()->user()->myuser_code ?? '0'),
            'obat'       => $obatBPJS,
        ];

        if ($isUpdate) {
            $payload['noSrb'] = $this->formPRB['noSrb'];
        }

        $response = $isUpdate
            ? VclaimTrait::prb_update($payload)->getOriginalContent()
            : VclaimTrait::prb_insert($payload)->getOriginalContent();

        $code = $response['metadata']['code'] ?? 0;
        $message = $response['metadata']['message'] ?? '';
        $label = $isUpdate ? 'UPDATE PRB' : 'INSERT PRB';

        if ($code == 200) {
            if (!$isUpdate) {
                $this->formPRB['noSrb'] = $response['response']['noSRB'] ?? ($response['response'] ?? '');
            }
            $this->dispatch('toast', type: 'success', message: "{$label} {$code} {$message}");
        } else {
            $this->dispatch('toast', type: 'error', message: "{$label} {$code} {$message}");
        }
    }

    /* ═══════════════════════════════════════
     | HAPUS PRB dari BPJS
    ═══════════════════════════════════════ */
    public function hapusPRB(): void
    {
        if (empty($this->formPRB['noSrb'])) {
            $this->dispatch('toast', type: 'error', message: 'Tidak ada SRB untuk dihapus.');
            return;
        }

        try {
            $response = VclaimTrait::prb_delete([
                'noSrb' => $this->formPRB['noSrb'],
                'noSep' => $this->formPRB['noSep'],
                'user'  => (string) (auth()->user()->myuser_code ?? '0'),
            ])->getOriginalContent();

            $code = $response['metadata']['code'] ?? 500;
            $msg = $response['metadata']['message'] ?? '-';

            if ($code == 200) {
                $this->formPRB['noSrb'] = '';

                DB::transaction(function () {
                    $this->lockRJRow($this->rjNo);
                    $data = $this->findDataRJ($this->rjNo) ?? [];
                    $data['prb'] = $this->formPRB;
                    $this->updateJsonRJ($this->rjNo, $data);
                    $this->dataDaftarPoliRJ = $data;
                });

                $this->incrementVersion('modal-prb-rj');
                $this->dispatch('toast', type: 'success', message: "PRB berhasil dihapus ({$code}): {$msg}");
            } else {
                $this->dispatch('toast', type: 'error', message: "Hapus PRB gagal ({$code}): {$msg}");
            }
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Error hapus PRB: ' . $e->getMessage());
        }
    }

    /* ═══════════════════════════════════════
     | SAVE
     | Alur sama seperti SKDP:
     | 1. Guard + re-fetch cek tindakLanjut
     | 2. Validate
     | 3. Push ke BPJS (di luar transaksi)
     | 4. Simpan ke DB
    ═══════════════════════════════════════ */
    public function save(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form dalam mode read-only.');
            return;
        }

        if (empty($this->dataDaftarPoliRJ)) {
            $this->dispatch('toast', type: 'error', message: 'Data kunjungan tidak ditemukan, silakan buka ulang form.');
            return;
        }

        // Re-fetch cek tindakLanjut
        $freshData = $this->findDataRJ($this->rjNo);
        if (($freshData['perencanaan']['tindakLanjut']['tindakLanjut'] ?? '') !== 'PRB') {
            return;
        }

        // Sync klaim status
        $this->dataDaftarPoliRJ['klaimStatus'] = $freshData['klaimStatus'] ?? '';
        $this->dataDaftarPoliRJ['klaimId'] = $freshData['klaimId'] ?? '';

        // Init jika belum pernah mount
        if (empty($this->formPRB['noSep'])) {
            $this->dataDaftarPoliRJ = $freshData;
            $this->formPRB = !empty($freshData['prb']) ? $freshData['prb'] : $this->getDefaultPRB();
        }

        $this->validate();

        // Push ke BPJS — DI LUAR transaksi
        $this->pushPRBtoBPJS();

        // Simpan ke DB
        try {
            DB::transaction(function () {
                $this->lockRJRow($this->rjNo);
                $data = $this->findDataRJ($this->rjNo) ?? [];

                if (empty($data)) {
                    $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.');
                    return;
                }

                $data['prb'] = $this->formPRB;
                $this->updateJsonRJ($this->rjNo, $data);
                $this->dataDaftarPoliRJ = $data;
            });

            $this->incrementVersion('modal-prb-rj');
            $this->dispatch('toast', type: 'success', message: 'PRB berhasil disimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    public function cetakPRB(): void
    {
        if (empty($this->rjNo) || empty($this->formPRB['noSrb'])) {
            $this->dispatch('toast', type: 'error', message: 'Data PRB belum tersedia untuk dicetak.');
            return;
        }
        $this->dispatch('cetak-prb-rj.open', rjNo: (string) $this->rjNo);
    }
};
?>

<div>
    <div class="flex flex-col w-full" wire:key="{{ $this->renderKey('modal-prb-rj', [$rjNo ?? 'new']) }}">
        <div class="w-full mx-auto">
            <div
                class="w-full p-4 space-y-6 bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                @if (!empty($formPRB['noSep']))

                    {{-- Header + Badge --}}
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Program Rujuk Balik (PRB)</h3>
                        @if (!empty($formPRB['noSrb']))
                            <x-badge variant="success">SRB: {{ $formPRB['noSrb'] }}</x-badge>
                        @else
                            <x-badge variant="warning">Belum dikirim ke BPJS</x-badge>
                        @endif
                    </div>

                    {{-- ══ STEPPER INDICATOR ══ --}}
                    <div class="flex items-center justify-center gap-0 mb-6">
                        @foreach ([1 => 'Data Pasien & Program', 2 => 'Obat PRB', 3 => 'Kirim & Cetak'] as $step => $label)
                            <button type="button" wire:click="goToStep({{ $step }})"
                                class="flex items-center gap-2 group">
                                <span class="flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold transition-colors
                                    {{ $stepPRB === $step ? 'bg-blue-600 text-white' : ($stepPRB > $step ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-500 dark:bg-gray-700 dark:text-gray-400') }}">
                                    @if ($stepPRB > $step)
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    @else
                                        {{ $step }}
                                    @endif
                                </span>
                                <span class="text-xs font-medium {{ $stepPRB === $step ? 'text-blue-600 dark:text-blue-400' : 'text-gray-500 dark:text-gray-400' }}">{{ $label }}</span>
                            </button>
                            @if ($step < 3)
                                <div class="w-8 h-px mx-1 {{ $stepPRB > $step ? 'bg-green-400' : 'bg-gray-300 dark:bg-gray-600' }}"></div>
                            @endif
                        @endforeach
                    </div>

                    {{-- ══════════════════════════════════════
                         STEP 1: Data Pasien & Program PRB
                    ══════════════════════════════════════ --}}
                    @if ($stepPRB === 1)
                        <div class="space-y-4">
                            <p class="text-xs text-gray-500 dark:text-gray-400">Lengkapi data pasien, pilih program PRB, isi keterangan & saran dokter.</p>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                {{-- No SEP --}}
                                <div>
                                    <x-input-label value="No. SEP" class="mb-1" />
                                    <x-text-input wire:model="formPRB.noSep" :disabled="true" class="w-full" />
                                </div>

                                {{-- No Kartu --}}
                                <div>
                                    <x-input-label value="No. Kartu BPJS *" class="mb-1" />
                                    <x-text-input wire:model.live="formPRB.noKartu" :disabled="$isFormLocked" class="w-full" :error="$errors->has('formPRB.noKartu')" />
                                    <x-input-error :messages="$errors->get('formPRB.noKartu')" class="mt-1" />
                                </div>

                                {{-- Alamat --}}
                                <div>
                                    <x-input-label value="Alamat *" class="mb-1" />
                                    <x-text-input wire:model.live="formPRB.alamat" :disabled="$isFormLocked" class="w-full" :error="$errors->has('formPRB.alamat')" />
                                    <x-input-error :messages="$errors->get('formPRB.alamat')" class="mt-1" />
                                </div>

                                {{-- Email --}}
                                <div>
                                    <x-input-label value="Email" class="mb-1" />
                                    <x-text-input wire:model.live="formPRB.email" :disabled="$isFormLocked" class="w-full" placeholder="email@contoh.com" />
                                </div>

                                {{-- Program PRB --}}
                                <div x-init="$wire.loadDiagnosaPRB()">
                                    <x-input-label value="Program PRB *" class="mb-1" />
                                    @if (!empty($listDiagnosaPRB))
                                        <x-select-input wire:model.live="formPRB.programPRB" class="w-full" :disabled="$isFormLocked" :error="$errors->has('formPRB.programPRB')">
                                            <option value="">-- Pilih Program PRB --</option>
                                            @foreach ($listDiagnosaPRB as $diag)
                                                <option value="{{ trim($diag['kode'] ?? '') }}">{{ trim($diag['kode'] ?? '') . ' - ' . ($diag['nama'] ?? '') }}</option>
                                            @endforeach
                                        </x-select-input>
                                    @else
                                        <x-select-input wire:model.live="formPRB.programPRB" class="w-full" :disabled="$isFormLocked" :error="$errors->has('formPRB.programPRB')">
                                            <option value="">-- Pilih Program PRB --</option>
                                            <option value="01">01 - Diabetes Mellitus</option>
                                            <option value="02">02 - Hypertensi</option>
                                            <option value="03">03 - Asthma</option>
                                            <option value="04">04 - Penyakit Jantung</option>
                                            <option value="05">05 - PPOK</option>
                                            <option value="06">06 - Schizophrenia</option>
                                            <option value="07">07 - Stroke</option>
                                            <option value="08">08 - Epilepsi</option>
                                            <option value="09">09 - Systemic Lupus Erythematosus</option>
                                        </x-select-input>
                                    @endif
                                    <x-input-error :messages="$errors->get('formPRB.programPRB')" class="mt-1" />
                                </div>

                                {{-- Kode DPJP --}}
                                <div>
                                    <x-input-label value="Kode DPJP *" class="mb-1" />
                                    <div class="flex gap-2">
                                        <x-text-input wire:model.live="formPRB.kodeDPJP" class="w-32" :disabled="$isFormLocked" :error="$errors->has('formPRB.kodeDPJP')" />
                                        <x-text-input wire:model="formPRB.kodeDPJPNama" class="flex-1" :disabled="true" placeholder="Nama dokter DPJP" />
                                    </div>
                                    <x-input-error :messages="$errors->get('formPRB.kodeDPJP')" class="mt-1" />
                                </div>

                                {{-- Keterangan --}}
                                <div>
                                    <x-input-label value="Keterangan *" class="mb-1" />
                                    <x-text-input wire:model.live="formPRB.keterangan" :disabled="$isFormLocked" class="w-full" placeholder="Keterangan pasien" :error="$errors->has('formPRB.keterangan')" />
                                    <x-input-error :messages="$errors->get('formPRB.keterangan')" class="mt-1" />
                                </div>

                                {{-- Saran --}}
                                <div>
                                    <x-input-label value="Saran Dokter *" class="mb-1" />
                                    <x-text-input wire:model.live="formPRB.saran" :disabled="$isFormLocked" class="w-full" placeholder="Saran dokter pemberi rujuk balik" :error="$errors->has('formPRB.saran')" />
                                    <x-input-error :messages="$errors->get('formPRB.saran')" class="mt-1" />
                                </div>
                            </div>

                            {{-- Next --}}
                            @if (!$isFormLocked)
                                <div class="flex justify-end pt-3">
                                    <x-primary-button type="button" wire:click="nextStep">
                                        Lanjut ke Obat &rarr;
                                    </x-primary-button>
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- ══════════════════════════════════════
                         STEP 2: Obat PRB
                    ══════════════════════════════════════ --}}
                    @if ($stepPRB === 2)
                        <div class="space-y-4">
                            <p class="text-xs text-gray-500 dark:text-gray-400">Cari & tambahkan obat generik PRB dari referensi BPJS.</p>

                            {{-- Tabel obat yang sudah ditambah --}}
                            @if (!empty($formPRB['obat']))
                                <div class="overflow-y-auto border border-gray-200 rounded-lg max-h-48 dark:border-gray-700">
                                    <table class="w-full text-xs">
                                        <thead class="sticky top-0 bg-gray-50 dark:bg-gray-800">
                                            <tr>
                                                <th class="px-2 py-1 text-left">#</th>
                                                <th class="px-2 py-1 text-left">Kode Obat</th>
                                                <th class="px-2 py-1 text-left">Nama</th>
                                                <th class="px-2 py-1 text-center">Signa</th>
                                                <th class="px-2 py-1 text-center">Jml</th>
                                                @if (!$isFormLocked)
                                                    <th class="px-2 py-1"></th>
                                                @endif
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($formPRB['obat'] as $idx => $obat)
                                                <tr class="border-t border-gray-100 dark:border-gray-700">
                                                    <td class="px-2 py-1">{{ $idx + 1 }}</td>
                                                    <td class="px-2 py-1 font-mono">{{ $obat['kdObat'] ?? '' }}</td>
                                                    <td class="px-2 py-1">{{ $obat['namaObat'] ?? '' }}</td>
                                                    <td class="px-2 py-1 text-center">{{ ($obat['signa1'] ?? '') . ' x ' . ($obat['signa2'] ?? '') }}</td>
                                                    <td class="px-2 py-1 text-center">{{ $obat['jmlObat'] ?? '' }}</td>
                                                    @if (!$isFormLocked)
                                                        <td class="px-2 py-1 text-center">
                                                            <button type="button" wire:click="hapusObat({{ $idx }})" class="text-red-500 hover:text-red-700">Hapus</button>
                                                        </td>
                                                    @endif
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="p-4 text-center border border-dashed border-gray-300 rounded-lg dark:border-gray-600">
                                    <p class="text-xs text-gray-400">Belum ada obat. Cari dan tambahkan obat di bawah.</p>
                                </div>
                            @endif

                            {{-- Cari obat generik dari BPJS --}}
                            @if (!$isFormLocked)
                                <div class="space-y-2">
                                    <div class="flex gap-2">
                                        <x-text-input wire:model="searchObat" class="flex-1" placeholder="Cari obat generik PRB (min 3 huruf)..." x-on:keyup.enter="$wire.cariObatPRB()" />
                                        <x-secondary-button type="button" wire:click="cariObatPRB" wire:loading.attr="disabled" class="text-xs shrink-0">
                                            <span wire:loading.remove wire:target="cariObatPRB">Cari Obat</span>
                                            <span wire:loading wire:target="cariObatPRB"><x-loading /></span>
                                        </x-secondary-button>
                                    </div>

                                    {{-- LOV Obat --}}
                                    @if ($showObatLov && !empty($listObatPRB))
                                        <div class="overflow-y-auto border border-gray-200 rounded-lg max-h-36 dark:border-gray-700">
                                            <table class="w-full text-xs">
                                                <thead class="sticky top-0 bg-gray-50 dark:bg-gray-800">
                                                    <tr>
                                                        <th class="px-2 py-1 text-left">Kode</th>
                                                        <th class="px-2 py-1 text-left">Nama Obat</th>
                                                        <th class="px-2 py-1"></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($listObatPRB as $idx => $ob)
                                                        <tr class="border-t border-gray-100 cursor-pointer hover:bg-blue-50 dark:hover:bg-blue-900/20 dark:border-gray-700" wire:click="pilihObat({{ $idx }})">
                                                            <td class="px-2 py-1 font-mono">{{ $ob['kode'] ?? '' }}</td>
                                                            <td class="px-2 py-1">{{ $ob['nama'] ?? '' }}</td>
                                                            <td class="px-2 py-1 text-blue-500">Pilih</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif

                                    {{-- Form tambah obat --}}
                                    <div class="flex flex-wrap items-end gap-2 p-2 rounded-lg bg-gray-50 dark:bg-gray-800">
                                        <div>
                                            <x-input-label value="Kode Obat" class="mb-1" />
                                            <x-text-input wire:model="formObat.kdObat" class="w-32" placeholder="Kode" />
                                        </div>
                                        <div>
                                            <x-input-label value="Nama Obat" class="mb-1" />
                                            <x-text-input wire:model="formObat.namaObat" class="w-48" placeholder="Nama" :disabled="true" />
                                        </div>
                                        <div>
                                            <x-input-label value="Signa" class="mb-1" />
                                            <div class="flex items-center gap-1">
                                                <x-text-input wire:model="formObat.signa1" class="w-12 text-center" />
                                                <span class="text-xs text-gray-500">x</span>
                                                <x-text-input wire:model="formObat.signa2" class="w-12 text-center" />
                                            </div>
                                        </div>
                                        <div>
                                            <x-input-label value="Jumlah" class="mb-1" />
                                            <x-text-input wire:model="formObat.jmlObat" class="w-16 text-center" />
                                        </div>
                                        <x-secondary-button type="button" wire:click="tambahObat" class="text-xs">+ Tambah</x-secondary-button>
                                    </div>
                                </div>
                            @endif

                            {{-- Prev / Next --}}
                            <div class="flex justify-between pt-3">
                                <x-secondary-button type="button" wire:click="prevStep">&larr; Kembali</x-secondary-button>
                                <x-primary-button type="button" wire:click="nextStep">Lanjut ke Kirim &rarr;</x-primary-button>
                            </div>
                        </div>
                    @endif

                    {{-- ══════════════════════════════════════
                         STEP 3: Review & Kirim ke BPJS
                    ══════════════════════════════════════ --}}
                    @if ($stepPRB === 3)
                        <div class="space-y-4">
                            <p class="text-xs text-gray-500 dark:text-gray-400">Review data PRB lalu kirim ke BPJS.</p>

                            {{-- Review ringkasan --}}
                            <div class="p-3 space-y-2 text-xs border border-gray-200 rounded-lg bg-gray-50 dark:bg-gray-800 dark:border-gray-700">
                                <div class="grid grid-cols-2 gap-x-4 gap-y-1">
                                    <span class="text-gray-500">No. SEP</span>
                                    <span class="font-mono">{{ $formPRB['noSep'] ?? '-' }}</span>
                                    <span class="text-gray-500">No. Kartu</span>
                                    <span class="font-mono">{{ $formPRB['noKartu'] ?? '-' }}</span>
                                    <span class="text-gray-500">Program PRB</span>
                                    <span>{{ $formPRB['programPRB'] ?? '-' }}</span>
                                    <span class="text-gray-500">Kode DPJP</span>
                                    <span>{{ ($formPRB['kodeDPJP'] ?? '') . ' - ' . ($formPRB['kodeDPJPNama'] ?? '') }}</span>
                                    <span class="text-gray-500">Keterangan</span>
                                    <span>{{ $formPRB['keterangan'] ?? '-' }}</span>
                                    <span class="text-gray-500">Saran</span>
                                    <span>{{ $formPRB['saran'] ?? '-' }}</span>
                                    <span class="text-gray-500">Jumlah Obat</span>
                                    <span>{{ count($formPRB['obat'] ?? []) }} item</span>
                                </div>

                                {{-- Daftar obat ringkas --}}
                                @if (!empty($formPRB['obat']))
                                    <div class="pt-2 mt-2 border-t border-gray-200 dark:border-gray-700">
                                        <p class="mb-1 font-semibold text-gray-600 dark:text-gray-400">Obat:</p>
                                        @foreach ($formPRB['obat'] as $idx => $obat)
                                            <p>{{ $idx + 1 }}. {{ $obat['namaObat'] ?? $obat['kdObat'] ?? '' }} — {{ ($obat['signa1'] ?? '') . 'x' . ($obat['signa2'] ?? '') }} ({{ $obat['jmlObat'] ?? '' }})</p>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            {{-- No SRB (readonly) --}}
                            <div>
                                <x-input-label value="No. Surat Rujuk Balik (SRB)" class="mb-1" />
                                <x-text-input wire:model="formPRB.noSrb" placeholder="Terisi otomatis setelah kirim ke BPJS" :disabled="true" class="w-full" />
                            </div>

                            {{-- Tombol Cetak --}}
                            @if (!empty($formPRB['noSrb']))
                                <div class="flex justify-end">
                                    <x-secondary-button type="button" wire:click="cetakPRB" wire:loading.attr="disabled">
                                        <span wire:loading.remove wire:target="cetakPRB" class="inline-flex items-center gap-1">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18.75 12H5.25" />
                                            </svg>
                                            Cetak Surat Rujuk Balik
                                        </span>
                                        <span wire:loading wire:target="cetakPRB" class="inline-flex items-center gap-1"><x-loading /> Mencetak...</span>
                                    </x-secondary-button>
                                </div>
                            @endif

                            {{-- Prev / Kirim / Hapus --}}
                            <div class="flex items-center justify-between pt-3">
                                <x-secondary-button type="button" wire:click="prevStep">&larr; Kembali</x-secondary-button>

                                @if (!$isFormLocked)
                                    <div class="flex gap-2">
                                        @if (!empty($formPRB['noSrb']))
                                            <x-danger-button type="button" wire:click="hapusPRB" wire:loading.attr="disabled"
                                                wire:confirm="Yakin hapus PRB {{ $formPRB['noSrb'] }} dari BPJS?">
                                                <span wire:loading.remove wire:target="hapusPRB">Hapus PRB</span>
                                                <span wire:loading wire:target="hapusPRB"><x-loading /> Menghapus...</span>
                                            </x-danger-button>
                                        @endif

                                        <x-success-button type="button" wire:click="save" wire:loading.attr="disabled">
                                            <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-2">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                                                </svg>
                                                {{ !empty($formPRB['noSrb']) ? 'Update PRB ke BPJS' : 'Kirim PRB ke BPJS' }}
                                            </span>
                                            <span wire:loading wire:target="save" class="inline-flex items-center gap-2"><x-loading /> Mengirim...</span>
                                        </x-success-button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                @endif

            </div>
        </div>
    </div>

    {{-- Cetak PRB --}}
    <livewire:pages::components.modul-dokumen.b-p-j-s.cetak-prb.cetak-prb wire:key="cetak-prb-rj-{{ $rjNo ?? 'init' }}" />
</div>
