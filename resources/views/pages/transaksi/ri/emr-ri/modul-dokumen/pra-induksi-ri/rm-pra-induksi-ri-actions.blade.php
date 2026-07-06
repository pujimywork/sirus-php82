<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/pra-induksi-ri/rm-pra-induksi-ri-actions.blade.php

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\WithValidationToast\WithValidationToastTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

new class extends Component {
    use EmrRITrait, MasterPasienTrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public bool $isFormLocked = false;
    public ?string $riHdrNo = null;
    public ?string $regNo = null;
    public bool $disabled = false;
    public array $dataDaftarRi = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-pra-induksi-ri'];

    // ── Asesmen Pra Induksi — PAB 6 / RM 50.a ──
    public array $newForm = [
        'tanggal' => '',
        'tempat' => 'Kamar Operasi (OK)',
        'diagnosisPraAnestesi' => '',
        'rencanaTindakan' => '',
        'amnanese' => '',
        'riwayatAnestesi' => false,
        'riwayatAnestesiJenis' => '',
        'merokok' => false,
        'alkohol' => false,
        'riwayatAlergi' => false,
        'riwayatAlergiJenis' => '',
        'persiapanTransfusi' => false,
        'transfusiJumlah' => '',
        'td' => '',
        'nadi' => '',
        'rr' => '',
        'suhu' => '',
        'pemFisikPernafasan' => '',
        'pemFisikTulangBelakang' => '',
        'pemFisikJantungParu' => '',
        'pemFisikAbdomen' => '',
        'penunjangLab' => '',
        'penunjangEkg' => '',
        'penunjangThorak' => '',
        'klasifikasiAsa' => '',
        'rencanaAnestesi' => '',
        'pemulihanPasca' => '',
        'manajemenNyeri' => '',
        'obatPreMedikasi' => '',
        'ttd' => '',
        'ttdCode' => '',
        'ttdDate' => '',
    ];

    public array $praInduksiList = [];

    public array $asaOptions = ['1', '2', '3', '4', '5'];
    public array $rencanaAnestesiOptions = ['Umum', 'Spinal', 'Regional lain', 'Sedasi'];
    public array $pemulihanOptions = ['Ruang Perawatan', 'ICU/HCU'];
    public array $nyeriOptions = ['IV', 'IM', 'Oral', 'Epidural'];

    public function mount(?string $riHdrNo = null, bool $disabled = false): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-pra-induksi-ri']);

        if ($this->riHdrNo) {
            $data = $this->findDataRI($this->riHdrNo);
            if ($data) {
                $this->dataDaftarRi = $data;
                $this->regNo = $data['regNo'] ?? null;
                $this->praInduksiList = $data['praInduksiRI'] ?? [];
                $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $disabled;
            }
        }
    }

    public function openModal(): void
    {
        if (!$this->riHdrNo || $this->disabled) {
            return;
        }
        $this->resetNewForm();
        $this->resetValidation();
        $data = $this->findDataRI($this->riHdrNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }
        $this->dataDaftarRi = $data;
        $this->regNo = $data['regNo'] ?? null;
        if (!isset($this->dataDaftarRi['praInduksiRI']) || !is_array($this->dataDaftarRi['praInduksiRI'])) {
            $this->dataDaftarRi['praInduksiRI'] = [];
        }
        $this->praInduksiList = $this->dataDaftarRi['praInduksiRI'];
        $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $this->disabled;
        $this->incrementVersion('modal-pra-induksi-ri');
        $this->dispatch('open-modal', name: "rm-pra-induksi-ri-{$this->riHdrNo}");
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: "rm-pra-induksi-ri-{$this->riHdrNo}");
    }

    protected function rules(): array
    {
        return [
            'newForm.tanggal' => 'required|date_format:d/m/Y H:i:s',
            'newForm.diagnosisPraAnestesi' => 'required|string|max:500',
            'newForm.rencanaTindakan' => 'required|string|max:500',
            'newForm.klasifikasiAsa' => 'required|in:1,2,3,4,5',
            'newForm.rencanaAnestesi' => 'required|string|max:100',
            'newForm.transfusiJumlah' => 'nullable|string|max:100',
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'date_format' => 'Format :attribute harus dd/mm/yyyy HH:mm:ss.',
            'in' => ':attribute tidak valid.',
            'max' => ':attribute maksimal :max karakter.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'newForm.tanggal' => 'Tanggal/jam',
            'newForm.diagnosisPraAnestesi' => 'Diagnosis pra anestesi',
            'newForm.rencanaTindakan' => 'Rencana tindakan',
            'newForm.klasifikasiAsa' => 'Klasifikasi ASA',
            'newForm.rencanaAnestesi' => 'Rencana anestesi',
        ];
    }

    public function setTanggalSekarang(): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->newForm['tanggal'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    public function setTtd(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        if (!empty($this->newForm['ttd'])) {
            $this->dispatch('toast', type: 'warning', message: 'Tanda tangan sudah ada.');
            return;
        }
        $this->newForm['ttd'] = auth()->user()->myuser_name ?? '';
        $this->newForm['ttdCode'] = auth()->user()->myuser_code ?? '';
        $this->newForm['ttdDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        $this->dispatch('toast', type: 'success', message: 'Tanda tangan dokter anestesi ditambahkan.');
    }

    public function clearTtd(): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->newForm['ttd'] = '';
        $this->newForm['ttdCode'] = '';
        $this->newForm['ttdDate'] = '';
    }

    public function addEntry(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }
        if (empty($this->newForm['ttd'])) {
            $this->dispatch('toast', type: 'error', message: 'Tanda tangan dokter anestesi belum diisi.');
            return;
        }
        $this->validateWithToast();
        $now = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        $entry = $this->newForm;
        $entry['createdAt'] = $now;

        try {
            DB::transaction(function () use ($entry) {
                $this->lockRIRow($this->riHdrNo);
                $fresh = $this->findDataRI($this->riHdrNo) ?: [];
                if (!isset($fresh['praInduksiRI']) || !is_array($fresh['praInduksiRI'])) {
                    $fresh['praInduksiRI'] = [];
                }
                $fresh['praInduksiRI'][] = $entry;
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
                $this->praInduksiList = $fresh['praInduksiRI'];
                $this->appendAdminLogRI((int) $this->riHdrNo, 'Tambah Asesmen Pra Induksi — ASA ' . ($entry['klasifikasiAsa'] ?? '-') . ' — ' . ($entry['createdAt'] ?? '-'), 'MR');
            });
            $this->incrementVersion('modal-pra-induksi-ri');
            $this->dispatch('toast', type: 'success', message: 'Asesmen pra induksi berhasil disimpan.');
            $this->resetNewForm();
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    public function cetak(string $createdAt)
    {
        $entry = collect($this->praInduksiList)->firstWhere('createdAt', $createdAt);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Data tidak ditemukan.');
            return;
        }
        try {
            $identitasRs = DB::table('rsmst_identitases')->select('int_name', 'int_phone1', 'int_phone2', 'int_fax', 'int_address', 'int_city')->first();
            $pasienData = $this->findDataMasterPasien($this->regNo ?? '');
            $pasien = $pasienData['pasien'] ?? [];
            if (!empty($pasien['tglLahir'])) {
                try {
                    $pasien['thn'] = Carbon::createFromFormat('d/m/Y', $pasien['tglLahir'])->diff(Carbon::now(config('app.timezone')))->format('%y Thn, %m Bln %d Hr');
                } catch (\Throwable) {
                    $pasien['thn'] = '-';
                }
            }
            $ttdPath = null;
            $ttdCode = $entry['ttdCode'] ?? null;
            if ($ttdCode) {
                $path = DB::table('users')->where('myuser_code', $ttdCode)->value('myuser_ttd_image');
                if (!empty($path) && file_exists(public_path('storage/' . $path))) {
                    $ttdPath = public_path('storage/' . $path);
                }
            }
            $data = array_merge($pasien, [
                'dataRi' => $this->dataDaftarRi, 'form' => $entry, 'identitasRs' => $identitasRs,
                'ttdPath' => $ttdPath, 'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
            ]);
            set_time_limit(300);
            $pdf = Pdf::loadView('pages.components.modul-dokumen.r-i.pra-induksi-ri.cetak-pra-induksi-ri-print', ['data' => $data])->setPaper('A4');
            $this->dispatch('toast', type: 'success', message: 'Berhasil mencetak asesmen pra induksi.');
            return response()->streamDownload(fn() => print $pdf->output(), 'pra-induksi-ri-' . ($pasien['regNo'] ?? $this->riHdrNo) . '.pdf');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cetak: ' . $e->getMessage());
        }
    }

    public function hapus(string $createdAt): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menghapus.');
            return;
        }
        try {
            DB::transaction(function () use ($createdAt) {
                $this->lockRIRow($this->riHdrNo);
                $fresh = $this->findDataRI($this->riHdrNo) ?: [];
                if (!isset($fresh['praInduksiRI'])) {
                    throw new \RuntimeException('Data tidak ditemukan.');
                }
                $fresh['praInduksiRI'] = collect($fresh['praInduksiRI'])->reject(fn($item) => ($item['createdAt'] ?? '') === $createdAt)->values()->toArray();
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
                $this->praInduksiList = $fresh['praInduksiRI'];
                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Asesmen Pra Induksi — ' . $createdAt, 'MR');
            });
            $this->incrementVersion('modal-pra-induksi-ri');
            $this->dispatch('toast', type: 'success', message: 'Asesmen pra induksi berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    private function resetNewForm(): void
    {
        $this->newForm = [
            'tanggal' => '', 'tempat' => 'Kamar Operasi (OK)', 'diagnosisPraAnestesi' => '', 'rencanaTindakan' => '',
            'amnanese' => '', 'riwayatAnestesi' => false, 'riwayatAnestesiJenis' => '', 'merokok' => false, 'alkohol' => false,
            'riwayatAlergi' => false, 'riwayatAlergiJenis' => '', 'persiapanTransfusi' => false, 'transfusiJumlah' => '',
            'td' => '', 'nadi' => '', 'rr' => '', 'suhu' => '',
            'pemFisikPernafasan' => '', 'pemFisikTulangBelakang' => '', 'pemFisikJantungParu' => '', 'pemFisikAbdomen' => '',
            'penunjangLab' => '', 'penunjangEkg' => '', 'penunjangThorak' => '', 'klasifikasiAsa' => '',
            'rencanaAnestesi' => '', 'pemulihanPasca' => '', 'manajemenNyeri' => '', 'obatPreMedikasi' => '',
            'ttd' => '', 'ttdCode' => '', 'ttdDate' => '',
        ];
    }
};
?>

<div>
    @php $entriCount = count($praInduksiList ?? []); @endphp

    <div class="p-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex-1 space-y-3">
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">Asesmen Pra Induksi</h3>
                    @if ($entriCount > 0) <x-badge variant="success">{{ $entriCount }} asesmen</x-badge>
                    @else <x-badge variant="warning">Belum ada</x-badge> @endif
                </div>
                <p class="text-base text-muted dark:text-gray-400">
                    Re-asesmen segera sebelum induksi (PAB 6 / RM 50.a): verifikasi kondisi terkini, ASA, rencana
                    anestesi & obat pre-medikasi sesaat sebelum tindakan.
                </p>
                @if ($entriCount > 0)
                    <ul class="space-y-1 text-base text-muted dark:text-gray-300 list-disc pl-5">
                        @foreach (array_slice($praInduksiList, 0, 3) as $entri)
                            <li><span class="font-medium">ASA {{ $entri['klasifikasiAsa'] ?? '-' }} · {{ $entri['rencanaAnestesi'] ?? '-' }}</span>
                                @if (!empty($entri['tanggal'])) <span class="text-sm text-muted-soft">— {{ $entri['tanggal'] }}</span> @endif
                            </li>
                        @endforeach
                        @if ($entriCount > 3) <li class="text-sm italic text-muted-soft">+{{ $entriCount - 3 }} lainnya…</li> @endif
                    </ul>
                @endif
            </div>
            <div class="flex shrink-0">
                <x-primary-button type="button" wire:click="openModal" wire:loading.attr="disabled" wire:target="openModal" :disabled="$disabled || !$riHdrNo" class="gap-2">
                    <span wire:loading.remove wire:target="openModal" class="flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                        Buka Formulir
                    </span>
                    <span wire:loading wire:target="openModal" class="flex items-center gap-1.5"><x-loading class="w-4 h-4" /> Memuat...</span>
                </x-primary-button>
            </div>
        </div>
    </div>

    <x-modal name="rm-pra-induksi-ri-{{ $riHdrNo ?? 'init' }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]" wire:key="{{ $this->renderKey('modal-pra-induksi-ri', [$riHdrNo ?? 'new']) }}">

            <div class="relative px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-fuchsia-500/10">
                                <svg class="w-6 h-6 text-fuchsia-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                            </div>
                            <div>
                                <h2 class="font-semibold text-2xl text-ink dark:text-gray-100">Asesmen Pra Induksi</h2>
                                <p class="mt-0.5 text-base text-muted dark:text-gray-400">PAB 6 / RM 50.a — sesaat sebelum induksi</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 mt-3">
                            <x-badge variant="brand">Rawat Inap</x-badge>
                            @if (count($praInduksiList) > 0) <x-badge variant="info">{{ count($praInduksiList) }} tersimpan</x-badge> @endif
                            @if ($isFormLocked) <x-badge variant="danger">Read Only</x-badge> @endif
                        </div>
                    </div>
                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <span class="sr-only">Close</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                    </x-icon-button>
                </div>
            </div>

            <div class="flex-1 px-4 py-4 bg-surface-soft/70 dark:bg-gray-950/20">
                <div class="max-w-full mx-auto space-y-4">
                    <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo" wire:key="prai-ri-display-pasien-{{ $riHdrNo ?? 'init' }}" />

                    <div class="p-6 space-y-6 bg-canvas border border-hairline shadow-sm sm:p-8 rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                        @if ($isFormLocked)
                            <div class="flex items-center gap-2 px-4 py-2.5 text-base font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                EMR terkunci — data tidak dapat diubah.
                            </div>
                        @endif

                        <section class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <x-input-label value="Tanggal / Jam *" class="mb-1" />
                                <div class="flex items-center gap-2">
                                    <x-text-input wire:model.live="newForm.tanggal" placeholder="dd/mm/yyyy HH:mm:ss" :error="$errors->has('newForm.tanggal')" :disabled="$isFormLocked" class="w-full" />
                                    @if (!$isFormLocked) <x-now-button wire:click="setTanggalSekarang" /> @endif
                                </div>
                                <x-input-error :messages="$errors->get('newForm.tanggal')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Tempat" class="mb-1" />
                                <x-text-input wire:model.live="newForm.tempat" :error="$errors->has('newForm.tempat')" :disabled="$isFormLocked" class="w-full" />
                            </div>
                            <div>
                                <x-input-label value="Diagnosis Pra Anestesi *" class="mb-1" />
                                <x-textarea wire:model.live="newForm.diagnosisPraAnestesi" :error="$errors->has('newForm.diagnosisPraAnestesi')" rows="2" :disabled="$isFormLocked" class="w-full" />
                                <x-input-error :messages="$errors->get('newForm.diagnosisPraAnestesi')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Rencana Tindakan *" class="mb-1" />
                                <x-textarea wire:model.live="newForm.rencanaTindakan" :error="$errors->has('newForm.rencanaTindakan')" rows="2" :disabled="$isFormLocked" class="w-full" />
                                <x-input-error :messages="$errors->get('newForm.rencanaTindakan')" class="mt-1" />
                            </div>
                        </section>

                        <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                            <div>
                                <x-input-label value="Amnanese" class="mb-1" />
                                <x-textarea wire:model.live="newForm.amnanese" :error="$errors->has('newForm.amnanese')" rows="2" :disabled="$isFormLocked" class="w-full" />
                            </div>
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <x-toggle wire:model.live="newForm.riwayatAnestesi" :trueValue="true" :falseValue="false" label="Ada riwayat anestesi" :disabled="$isFormLocked" />
                                <x-toggle wire:model.live="newForm.riwayatAlergi" :trueValue="true" :falseValue="false" label="Ada riwayat alergi" :disabled="$isFormLocked" />
                            </div>
                            @if ($newForm['riwayatAnestesi'])
                                <x-text-input wire:model.live="newForm.riwayatAnestesiJenis" :error="$errors->has('newForm.riwayatAnestesiJenis')" placeholder="Jenis anestesi sebelumnya" :disabled="$isFormLocked" class="w-full" />
                            @endif
                            @if ($newForm['riwayatAlergi'])
                                <x-text-input wire:model.live="newForm.riwayatAlergiJenis" :error="$errors->has('newForm.riwayatAlergiJenis')" placeholder="Jenis alergi" :disabled="$isFormLocked" class="w-full" />
                            @endif
                            <div class="flex flex-wrap gap-4">
                                <x-toggle wire:model.live="newForm.merokok" :trueValue="true" :falseValue="false" label="Merokok" :disabled="$isFormLocked" />
                                <x-toggle wire:model.live="newForm.alkohol" :trueValue="true" :falseValue="false" label="Alkohol" :disabled="$isFormLocked" />
                                <x-toggle wire:model.live="newForm.persiapanTransfusi" :trueValue="true" :falseValue="false" label="Persiapan transfusi" :disabled="$isFormLocked" />
                            </div>
                            @if ($newForm['persiapanTransfusi'])
                                <x-text-input wire:model.live="newForm.transfusiJumlah" :error="$errors->has('newForm.transfusiJumlah')" placeholder="Jumlah / kolf / unit" :disabled="$isFormLocked" class="w-full max-w-xs" />
                            @endif
                        </section>

                        <section class="pt-6 border-t border-hairline dark:border-gray-700">
                            <h3 class="mb-3 text-base font-semibold text-ink dark:text-gray-200">Tanda Vital</h3>
                            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                                <div><x-input-label value="TD" class="mb-1" /><x-text-input wire:model.live="newForm.td" :error="$errors->has('newForm.td')" placeholder="120/80" :disabled="$isFormLocked" class="w-full" /></div>
                                <div><x-input-label value="Nadi" class="mb-1" /><x-text-input wire:model.live="newForm.nadi" :error="$errors->has('newForm.nadi')" :disabled="$isFormLocked" class="w-full" /></div>
                                <div><x-input-label value="RR" class="mb-1" /><x-text-input wire:model.live="newForm.rr" :error="$errors->has('newForm.rr')" :disabled="$isFormLocked" class="w-full" /></div>
                                <div><x-input-label value="Suhu" class="mb-1" /><x-text-input wire:model.live="newForm.suhu" :error="$errors->has('newForm.suhu')" :disabled="$isFormLocked" class="w-full" /></div>
                            </div>
                        </section>

                        <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                            <h3 class="text-base font-semibold text-ink dark:text-gray-200">Pemeriksaan Fisik & Penunjang</h3>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div><x-input-label value="Pernafasan / Jalan Nafas" class="mb-1" /><x-text-input wire:model.live="newForm.pemFisikPernafasan" :error="$errors->has('newForm.pemFisikPernafasan')" :disabled="$isFormLocked" class="w-full" /></div>
                                <div><x-input-label value="Kelainan Tulang Belakang" class="mb-1" /><x-text-input wire:model.live="newForm.pemFisikTulangBelakang" :error="$errors->has('newForm.pemFisikTulangBelakang')" :disabled="$isFormLocked" class="w-full" /></div>
                                <div><x-input-label value="Jantung / Paru-paru" class="mb-1" /><x-text-input wire:model.live="newForm.pemFisikJantungParu" :error="$errors->has('newForm.pemFisikJantungParu')" :disabled="$isFormLocked" class="w-full" /></div>
                                <div><x-input-label value="Abdomen" class="mb-1" /><x-text-input wire:model.live="newForm.pemFisikAbdomen" :error="$errors->has('newForm.pemFisikAbdomen')" :disabled="$isFormLocked" class="w-full" /></div>
                                <div><x-input-label value="Laboratorium" class="mb-1" /><x-text-input wire:model.live="newForm.penunjangLab" :error="$errors->has('newForm.penunjangLab')" :disabled="$isFormLocked" class="w-full" /></div>
                                <div><x-input-label value="EKG" class="mb-1" /><x-text-input wire:model.live="newForm.penunjangEkg" :error="$errors->has('newForm.penunjangEkg')" :disabled="$isFormLocked" class="w-full" /></div>
                                <div><x-input-label value="Thorak" class="mb-1" /><x-text-input wire:model.live="newForm.penunjangThorak" :error="$errors->has('newForm.penunjangThorak')" :disabled="$isFormLocked" class="w-full" /></div>
                            </div>
                        </section>

                        <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                            <h3 class="text-base font-semibold text-ink dark:text-gray-200">Rencana Anestesi</h3>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <x-input-label value="Klasifikasi ASA *" class="mb-1" />
                                    <x-select-input wire:model.live="newForm.klasifikasiAsa" :error="$errors->has('newForm.klasifikasiAsa')" :disabled="$isFormLocked" class="w-full">
                                        <option value="">— pilih —</option>
                                        @foreach ($asaOptions as $opt) <option value="{{ $opt }}">ASA {{ $opt }}</option> @endforeach
                                    </x-select-input>
                                    <x-input-error :messages="$errors->get('newForm.klasifikasiAsa')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Rencana Anestesi *" class="mb-1" />
                                    <x-select-input wire:model.live="newForm.rencanaAnestesi" :error="$errors->has('newForm.rencanaAnestesi')" :disabled="$isFormLocked" class="w-full">
                                        <option value="">— pilih —</option>
                                        @foreach ($rencanaAnestesiOptions as $opt) <option value="{{ $opt }}">{{ $opt }}</option> @endforeach
                                    </x-select-input>
                                    <x-input-error :messages="$errors->get('newForm.rencanaAnestesi')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Rencana Pemulihan Pasca Anestesi" class="mb-1" />
                                    <x-select-input wire:model.live="newForm.pemulihanPasca" :error="$errors->has('newForm.pemulihanPasca')" :disabled="$isFormLocked" class="w-full">
                                        <option value="">— pilih —</option>
                                        @foreach ($pemulihanOptions as $opt) <option value="{{ $opt }}">{{ $opt }}</option> @endforeach
                                    </x-select-input>
                                </div>
                                <div>
                                    <x-input-label value="Manajemen Nyeri" class="mb-1" />
                                    <x-select-input wire:model.live="newForm.manajemenNyeri" :error="$errors->has('newForm.manajemenNyeri')" :disabled="$isFormLocked" class="w-full">
                                        <option value="">— pilih —</option>
                                        @foreach ($nyeriOptions as $opt) <option value="{{ $opt }}">{{ $opt }}</option> @endforeach
                                    </x-select-input>
                                </div>
                            </div>
                            <div>
                                <x-input-label value="Obat Pre-Medikasi (obat / dosis / jam / pelaksana)" class="mb-1" />
                                <x-textarea wire:model.live="newForm.obatPreMedikasi" :error="$errors->has('newForm.obatPreMedikasi')" rows="3" :disabled="$isFormLocked" class="w-full" />
                            </div>
                        </section>

                        <x-signature.ttd-petugas :ttd="$newForm['ttd']" :date="$newForm['ttdDate'] ?? ''"
                            :code="$newForm['ttdCode'] ?? ''" :locked="$isFormLocked" sign="setTtd" clear="clearTtd"
                            title="Tanda Tangan Dokter Anestesi" label="" signLabel="TTD sebagai Dokter Anestesi" clearLabel="Hapus TTD" />

                        @if (count($praInduksiList) > 0)
                            <div class="mt-6 overflow-x-auto">
                                <h3 class="text-base font-semibold text-body dark:text-gray-300 pb-2 border-b border-hairline-soft dark:border-gray-800 mb-3">Daftar Asesmen Tersimpan</h3>
                                <table class="min-w-full text-base border border-hairline rounded-lg dark:border-gray-700">
                                    <thead class="bg-surface-soft dark:bg-gray-800">
                                        <tr class="text-left text-muted dark:text-gray-300">
                                            <th class="px-4 py-2 border-b">Tanggal</th>
                                            <th class="px-4 py-2 border-b">ASA</th>
                                            <th class="px-4 py-2 border-b">Rencana</th>
                                            <th class="px-4 py-2 border-b text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($praInduksiList as $entri)
                                            <tr class="border-b border-hairline dark:border-gray-700 hover:bg-surface-soft dark:hover:bg-gray-800">
                                                <td class="px-4 py-2 text-muted dark:text-gray-400">{{ $entri['tanggal'] ?? '-' }}</td>
                                                <td class="px-4 py-2 font-medium text-ink dark:text-gray-200">ASA {{ $entri['klasifikasiAsa'] ?? '-' }}</td>
                                                <td class="px-4 py-2 text-muted dark:text-gray-400">{{ $entri['rencanaAnestesi'] ?? '-' }}</td>
                                                <td class="px-4 py-2 text-center space-x-2 whitespace-nowrap">
                                                    <x-secondary-button wire:click="cetak('{{ $entri['createdAt'] }}')" wire:loading.attr="disabled" wire:target="cetak('{{ $entri['createdAt'] }}')" class="text-sm py-1 px-2">
                                                        <span wire:loading.remove wire:target="cetak('{{ $entri['createdAt'] }}')" class="flex items-center gap-1">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                                                            Cetak
                                                        </span>
                                                        <span wire:loading wire:target="cetak('{{ $entri['createdAt'] }}')" class="flex items-center gap-1"><x-loading /> ...</span>
                                                    </x-secondary-button>
                                                    @if (!$isFormLocked)
                                                        <x-outline-button type="button" wire:click.prevent="hapus('{{ $entri['createdAt'] }}')" wire:confirm="Yakin hapus asesmen ini?" wire:loading.attr="disabled" class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300 !px-2 !py-1" title="Hapus">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                        </x-outline-button>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                    </div>
                </div>
            </div>

            <div class="sticky bottom-0 z-10 px-6 py-4 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-center justify-end gap-3">
                    <x-secondary-button wire:click="closeModal">Tutup</x-secondary-button>
                    @if ($riHdrNo && !$isFormLocked)
                        <x-primary-button wire:click.prevent="addEntry" wire:loading.attr="disabled" wire:target="addEntry" class="gap-2 min-w-[180px] justify-center">
                            <span wire:loading.remove wire:target="addEntry">Simpan Asesmen</span>
                            <span wire:loading wire:target="addEntry"><x-loading class="w-4 h-4" /> Menyimpan...</span>
                        </x-primary-button>
                    @endif
                </div>
            </div>

        </div>
    </x-modal>
</div>
