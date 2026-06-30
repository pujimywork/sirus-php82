<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/pra-anestesi-ri/rm-pra-anestesi-ri-actions.blade.php

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
    protected array $renderAreas = ['modal-pra-anestesi-ri'];

    // ── Form entri baru (Pengkajian Pra Anestesi & Pra Sedasi — PAB 4 / RM 50) ──
    public array $newForm = [
        'tanggal' => '',
        'kriteria' => 'Dewasa',
        'diagnosisPraAnestesi' => '',
        'rencanaTindakan' => '',
        'anamnese' => '',
        'riwayatAnestesi' => false,
        'riwayatAnestesiKet' => '',
        'riwayatAlergi' => false,
        'riwayatAlergiKet' => '',
        'obatDikonsumsi' => '',
        'merokok' => false,
        'alkohol' => false,
        // Antropometri & TTV
        'bb' => '',
        'tb' => '',
        'bmi' => '',
        'td' => '',
        'nadi' => '',
        'rr' => '',
        'suhu' => '',
        'skorNyeri' => '',
        // Evaluasi jalan nafas
        'mallampati' => '',
        'bukaMulut' => '',
        'gerakLeher' => '',
        'gigiPalsu' => false,
        'obesitas' => false,
        'sulitVentilasi' => false,
        // Sistem organ & penunjang
        'fungsiOrgan' => '',
        'pemeriksaanLab' => '',
        'pemeriksaanPenunjang' => '',
        // Kesimpulan
        'jenisAnestesi' => '',
        'induksiPraAnestesi' => '',
        'psAsa' => '',
        'penyulit' => '',
        'komplikasi' => '',
        'obatAnalgesikPascaOp' => '',
        // TTD dokter anestesi (auto)
        'ttd' => '',
        'ttdCode' => '',
        'ttdDate' => '',
    ];

    public string $signaturePasien = '';

    public array $praList = [];

    public array $kriteriaOptions = ['Anak', 'Dewasa', 'Geriatri'];
    public array $mallampatiOptions = ['I', 'II', 'III', 'IV'];
    public array $gerakLeherOptions = ['Bebas', 'Terbatas'];
    public array $asaOptions = ['ASA I', 'ASA II', 'ASA III', 'ASA IV', 'ASA V', 'ASA I-E', 'ASA II-E', 'ASA III-E', 'ASA IV-E', 'ASA V-E'];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(?string $riHdrNo = null, bool $disabled = false): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-pra-anestesi-ri']);

        if ($this->riHdrNo) {
            $data = $this->findDataRI($this->riHdrNo);
            if ($data) {
                $this->dataDaftarRi = $data;
                $this->regNo = $data['regNo'] ?? null;
                $this->praList = $data['praAnestesiRI'] ?? [];
                $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $disabled;
            }
        }
    }

    /* ===============================
     | OPEN MODAL
     =============================== */
    public function openModal(): void
    {
        if (!$this->riHdrNo || $this->disabled) {
            return;
        }

        $this->resetNewForm();
        $this->signaturePasien = '';
        $this->resetValidation();

        $data = $this->findDataRI($this->riHdrNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }

        $this->dataDaftarRi = $data;
        $this->regNo = $data['regNo'] ?? null;
        if (!isset($this->dataDaftarRi['praAnestesiRI']) || !is_array($this->dataDaftarRi['praAnestesiRI'])) {
            $this->dataDaftarRi['praAnestesiRI'] = [];
        }
        $this->praList = $this->dataDaftarRi['praAnestesiRI'];
        $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $this->disabled;
        $this->incrementVersion('modal-pra-anestesi-ri');

        $this->dispatch('open-modal', name: "rm-pra-anestesi-ri-{$this->riHdrNo}");
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: "rm-pra-anestesi-ri-{$this->riHdrNo}");
    }

    /* ===============================
     | VALIDATION
     =============================== */
    protected function rules(): array
    {
        return [
            'newForm.tanggal' => 'required|date_format:d/m/Y H:i:s',
            'newForm.kriteria' => 'required|string',
            'newForm.diagnosisPraAnestesi' => 'required|string|max:500',
            'newForm.rencanaTindakan' => 'required|string|max:500',
            'newForm.mallampati' => 'required|in:I,II,III,IV',
            'newForm.psAsa' => 'required|string',
            'newForm.jenisAnestesi' => 'required|string|max:200',
            'newForm.riwayatAnestesiKet' => 'nullable|string|max:300',
            'newForm.riwayatAlergiKet' => 'nullable|string|max:300',
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
            'newForm.kriteria' => 'Kriteria pasien',
            'newForm.diagnosisPraAnestesi' => 'Diagnosis pra anestesi',
            'newForm.rencanaTindakan' => 'Rencana tindakan',
            'newForm.mallampati' => 'Mallampati',
            'newForm.psAsa' => 'PS ASA',
            'newForm.jenisAnestesi' => 'Jenis anestesi',
        ];
    }

    /* ===============================
     | SET TANGGAL & TTD
     =============================== */
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

    public function setSignaturePasien(string $dataUrl): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->signaturePasien = $dataUrl;
        $this->incrementVersion('modal-pra-anestesi-ri');
    }

    public function clearSignaturePasien(): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->signaturePasien = '';
        $this->incrementVersion('modal-pra-anestesi-ri');
    }

    /* ===============================
     | SIMPAN ENTRI BARU
     =============================== */
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
        $entry['signaturePasien'] = $this->signaturePasien;
        $entry['createdAt'] = $now;

        try {
            DB::transaction(function () use ($entry) {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?: [];
                if (!isset($fresh['praAnestesiRI']) || !is_array($fresh['praAnestesiRI'])) {
                    $fresh['praAnestesiRI'] = [];
                }

                $fresh['praAnestesiRI'][] = $entry;

                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
                $this->praList = $fresh['praAnestesiRI'];

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Tambah Pengkajian Pra Anestesi — ' . ($entry['psAsa'] ?? '-') . ' — ' . ($entry['createdAt'] ?? '-'), 'MR');
            });

            $this->incrementVersion('modal-pra-anestesi-ri');
            $this->dispatch('toast', type: 'success', message: 'Pengkajian pra anestesi berhasil disimpan.');

            $this->resetNewForm();
            $this->signaturePasien = '';
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | CETAK
     =============================== */
    public function cetak(string $createdAt)
    {
        $entry = collect($this->praList)->firstWhere('createdAt', $createdAt);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Data pengkajian tidak ditemukan.');
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
                'dataRi' => $this->dataDaftarRi,
                'form' => $entry,
                'identitasRs' => $identitasRs,
                'ttdPath' => $ttdPath,
                'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
            ]);

            set_time_limit(300);

            $pdf = Pdf::loadView('pages.components.modul-dokumen.r-i.pra-anestesi-ri.cetak-pra-anestesi-ri-print', ['data' => $data])->setPaper('A4');

            $this->dispatch('toast', type: 'success', message: 'Berhasil mencetak pengkajian pra anestesi.');
            return response()->streamDownload(fn() => print $pdf->output(), 'pra-anestesi-ri-' . ($pasien['regNo'] ?? $this->riHdrNo) . '.pdf');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cetak: ' . $e->getMessage());
        }
    }

    /* ===============================
     | HAPUS
     =============================== */
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
                if (!isset($fresh['praAnestesiRI'])) {
                    throw new \RuntimeException('Data pengkajian tidak ditemukan.');
                }

                $fresh['praAnestesiRI'] = collect($fresh['praAnestesiRI'])
                    ->reject(fn($item) => ($item['createdAt'] ?? '') === $createdAt)
                    ->values()
                    ->toArray();

                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
                $this->praList = $fresh['praAnestesiRI'];

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Pengkajian Pra Anestesi — ' . $createdAt, 'MR');
            });

            $this->incrementVersion('modal-pra-anestesi-ri');
            $this->dispatch('toast', type: 'success', message: 'Pengkajian pra anestesi berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    /* ===============================
     | RESET
     =============================== */
    private function resetNewForm(): void
    {
        $this->newForm = [
            'tanggal' => '', 'kriteria' => 'Dewasa', 'diagnosisPraAnestesi' => '', 'rencanaTindakan' => '',
            'anamnese' => '', 'riwayatAnestesi' => false, 'riwayatAnestesiKet' => '', 'riwayatAlergi' => false,
            'riwayatAlergiKet' => '', 'obatDikonsumsi' => '', 'merokok' => false, 'alkohol' => false,
            'bb' => '', 'tb' => '', 'bmi' => '', 'td' => '', 'nadi' => '', 'rr' => '', 'suhu' => '', 'skorNyeri' => '',
            'mallampati' => '', 'bukaMulut' => '', 'gerakLeher' => '', 'gigiPalsu' => false, 'obesitas' => false,
            'sulitVentilasi' => false, 'fungsiOrgan' => '', 'pemeriksaanLab' => '', 'pemeriksaanPenunjang' => '',
            'jenisAnestesi' => '', 'induksiPraAnestesi' => '', 'psAsa' => '', 'penyulit' => '', 'komplikasi' => '',
            'obatAnalgesikPascaOp' => '', 'ttd' => '', 'ttdCode' => '', 'ttdDate' => '',
        ];
    }
};
?>

<div>
    {{-- ══ SUMMARY CARD ══ --}}
    @php $pCount = count($praList ?? []); @endphp

    <div class="p-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex-1 space-y-3">
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">Pengkajian Pra Anestesi & Pra Sedasi</h3>
                    @if ($pCount > 0)
                        <x-badge variant="success">{{ $pCount }} pengkajian</x-badge>
                    @else
                        <x-badge variant="warning">Belum ada</x-badge>
                    @endif
                </div>
                <p class="text-base text-muted dark:text-gray-400">
                    Asesmen pra anestesi (PAB 4 / RM 50) oleh dokter anestesi: anamnese, jalan nafas (Mallampati),
                    status fisik ASA, rencana teknik anestesi & analgesia pasca-op.
                </p>
                @if ($pCount > 0)
                    <ul class="space-y-1 text-base text-muted dark:text-gray-300 list-disc pl-5">
                        @foreach (array_slice($praList, 0, 3) as $p)
                            <li>
                                <span class="font-medium">{{ $p['psAsa'] ?? '-' }} · {{ \Illuminate\Support\Str::limit($p['jenisAnestesi'] ?? '-', 40) }}</span>
                                @if (!empty($p['tanggal']))
                                    <span class="text-sm text-muted-soft">— {{ $p['tanggal'] }}</span>
                                @endif
                            </li>
                        @endforeach
                        @if ($pCount > 3)
                            <li class="text-sm italic text-muted-soft">+{{ $pCount - 3 }} lainnya…</li>
                        @endif
                    </ul>
                @endif
            </div>

            <div class="flex shrink-0">
                <x-primary-button type="button" wire:click="openModal" wire:loading.attr="disabled"
                    wire:target="openModal" :disabled="$disabled || !$riHdrNo" class="gap-2">
                    <span wire:loading.remove wire:target="openModal" class="flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                        Buka Formulir
                    </span>
                    <span wire:loading wire:target="openModal" class="flex items-center gap-1.5">
                        <x-loading class="w-4 h-4" /> Memuat...
                    </span>
                </x-primary-button>
            </div>
        </div>
    </div>

    {{-- ══ MODAL FORM ══ --}}
    <x-modal name="rm-pra-anestesi-ri-{{ $riHdrNo ?? 'init' }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal-pra-anestesi-ri', [$riHdrNo ?? 'new']) }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-violet-500/10">
                                <svg class="w-6 h-6 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="font-semibold text-2xl text-ink dark:text-gray-100">Pengkajian Pra Anestesi & Pra Sedasi</h2>
                                <p class="mt-0.5 text-base text-muted dark:text-gray-400">PAB 4 / RM 50 — dokter anestesi</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 mt-3">
                            <x-badge variant="brand">Rawat Inap</x-badge>
                            @if (count($praList) > 0)
                                <x-badge variant="info">{{ count($praList) }} tersimpan</x-badge>
                            @endif
                            @if ($isFormLocked)
                                <x-badge variant="danger">Read Only</x-badge>
                            @endif
                        </div>
                    </div>
                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <span class="sr-only">Close</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-surface-soft/70 dark:bg-gray-950/20">
                <div class="max-w-full mx-auto space-y-4">

                    <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                        wire:key="pra-ri-display-pasien-{{ $riHdrNo ?? 'init' }}" />

                    <div class="p-6 space-y-6 bg-canvas border border-hairline shadow-sm sm:p-8 rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                        @if ($isFormLocked)
                            <div class="flex items-center gap-2 px-4 py-2.5 text-base font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                                EMR terkunci — data tidak dapat diubah.
                            </div>
                        @endif

                        {{-- ══ DATA DASAR ══ --}}
                        <section class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <x-input-label value="Tanggal / Jam *" class="mb-1" />
                                <div class="flex items-center gap-2">
                                    <x-text-input wire:model.live="newForm.tanggal" placeholder="dd/mm/yyyy HH:mm:ss"
                                        :error="$errors->has('newForm.tanggal')" :disabled="$isFormLocked" class="w-full" />
                                    @if (!$isFormLocked)
                                        <x-now-button wire:click="setTanggalSekarang" />
                                    @endif
                                </div>
                                <x-input-error :messages="$errors->get('newForm.tanggal')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Kriteria Pasien *" class="mb-1" />
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($kriteriaOptions as $opt)
                                        <x-radio-button :label="$opt" :value="$opt" name="kriteria"
                                            wire:model.live="newForm.kriteria" :disabled="$isFormLocked" />
                                    @endforeach
                                </div>
                            </div>
                            <div>
                                <x-input-label value="Diagnosis Pra Anestesi *" class="mb-1" />
                                <x-textarea wire:model.live="newForm.diagnosisPraAnestesi" rows="2" :disabled="$isFormLocked" class="w-full" />
                                <x-input-error :messages="$errors->get('newForm.diagnosisPraAnestesi')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Rencana Tindakan *" class="mb-1" />
                                <x-textarea wire:model.live="newForm.rencanaTindakan" rows="2" :disabled="$isFormLocked" class="w-full" />
                                <x-input-error :messages="$errors->get('newForm.rencanaTindakan')" class="mt-1" />
                            </div>
                        </section>

                        {{-- ══ ANAMNESE & RIWAYAT ══ --}}
                        <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                            <div>
                                <x-input-label value="Anamnese" class="mb-1" />
                                <x-textarea wire:model.live="newForm.anamnese" rows="2" :disabled="$isFormLocked" class="w-full" />
                            </div>
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <x-toggle wire:model.live="newForm.riwayatAnestesi" :trueValue="true" :falseValue="false" label="Ada riwayat anestesi" :disabled="$isFormLocked" />
                                <x-toggle wire:model.live="newForm.riwayatAlergi" :trueValue="true" :falseValue="false" label="Ada riwayat alergi" :disabled="$isFormLocked" />
                            </div>
                            @if ($newForm['riwayatAnestesi'])
                                <x-text-input wire:model.live="newForm.riwayatAnestesiKet" placeholder="Keterangan riwayat anestesi" :disabled="$isFormLocked" class="w-full" />
                            @endif
                            @if ($newForm['riwayatAlergi'])
                                <x-text-input wire:model.live="newForm.riwayatAlergiKet" placeholder="Keterangan alergi" :disabled="$isFormLocked" class="w-full" />
                            @endif
                            <div>
                                <x-input-label value="Obat yang Sedang Dikonsumsi" class="mb-1" />
                                <x-text-input wire:model.live="newForm.obatDikonsumsi" :disabled="$isFormLocked" class="w-full" />
                            </div>
                            <div class="flex flex-wrap gap-4">
                                <x-toggle wire:model.live="newForm.merokok" :trueValue="true" :falseValue="false" label="Merokok" :disabled="$isFormLocked" />
                                <x-toggle wire:model.live="newForm.alkohol" :trueValue="true" :falseValue="false" label="Alkohol" :disabled="$isFormLocked" />
                            </div>
                        </section>

                        {{-- ══ ANTROPOMETRI & TTV ══ --}}
                        <section class="pt-6 border-t border-hairline dark:border-gray-700">
                            <h3 class="mb-3 text-base font-semibold text-ink dark:text-gray-200">Antropometri & Tanda Vital</h3>
                            <div class="grid grid-cols-2 gap-4 md:grid-cols-4 lg:grid-cols-8">
                                <div><x-input-label value="BB (kg)" class="mb-1" /><x-text-input wire:model.live="newForm.bb" :disabled="$isFormLocked" class="w-full" /></div>
                                <div><x-input-label value="TB (cm)" class="mb-1" /><x-text-input wire:model.live="newForm.tb" :disabled="$isFormLocked" class="w-full" /></div>
                                <div><x-input-label value="BMI" class="mb-1" /><x-text-input wire:model.live="newForm.bmi" :disabled="$isFormLocked" class="w-full" /></div>
                                <div><x-input-label value="TD" class="mb-1" /><x-text-input wire:model.live="newForm.td" placeholder="120/80" :disabled="$isFormLocked" class="w-full" /></div>
                                <div><x-input-label value="Nadi" class="mb-1" /><x-text-input wire:model.live="newForm.nadi" :disabled="$isFormLocked" class="w-full" /></div>
                                <div><x-input-label value="RR" class="mb-1" /><x-text-input wire:model.live="newForm.rr" :disabled="$isFormLocked" class="w-full" /></div>
                                <div><x-input-label value="Suhu" class="mb-1" /><x-text-input wire:model.live="newForm.suhu" :disabled="$isFormLocked" class="w-full" /></div>
                                <div><x-input-label value="Skor Nyeri" class="mb-1" /><x-text-input wire:model.live="newForm.skorNyeri" :disabled="$isFormLocked" class="w-full" /></div>
                            </div>
                        </section>

                        {{-- ══ EVALUASI JALAN NAFAS ══ --}}
                        <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                            <h3 class="text-base font-semibold text-ink dark:text-gray-200">Evaluasi Jalan Nafas</h3>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div>
                                    <x-input-label value="Mallampati *" class="mb-1" />
                                    <x-select-input wire:model.live="newForm.mallampati" :disabled="$isFormLocked" class="w-full">
                                        <option value="">— pilih —</option>
                                        @foreach ($mallampatiOptions as $opt)
                                            <option value="{{ $opt }}">{{ $opt }}</option>
                                        @endforeach
                                    </x-select-input>
                                    <x-input-error :messages="$errors->get('newForm.mallampati')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Buka Mulut (cm)" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.bukaMulut" :disabled="$isFormLocked" class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Gerak Leher" class="mb-1" />
                                    <x-select-input wire:model.live="newForm.gerakLeher" :disabled="$isFormLocked" class="w-full">
                                        <option value="">— pilih —</option>
                                        @foreach ($gerakLeherOptions as $opt)
                                            <option value="{{ $opt }}">{{ $opt }}</option>
                                        @endforeach
                                    </x-select-input>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-4">
                                <x-toggle wire:model.live="newForm.gigiPalsu" :trueValue="true" :falseValue="false" label="Gigi palsu" :disabled="$isFormLocked" />
                                <x-toggle wire:model.live="newForm.obesitas" :trueValue="true" :falseValue="false" label="Obesitas" :disabled="$isFormLocked" />
                                <x-toggle wire:model.live="newForm.sulitVentilasi" :trueValue="true" :falseValue="false" label="Prediksi sulit ventilasi" :disabled="$isFormLocked" />
                            </div>
                        </section>

                        {{-- ══ SISTEM ORGAN & PENUNJANG ══ --}}
                        <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                            <div>
                                <x-input-label value="Catatan Fungsi Sistem Organ (pernafasan/kardiovaskuler/neuro/renal/endokrin/lain)" class="mb-1" />
                                <x-textarea wire:model.live="newForm.fungsiOrgan" rows="2" :disabled="$isFormLocked" class="w-full" />
                            </div>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <x-input-label value="Pemeriksaan Laboratorium" class="mb-1" />
                                    <x-textarea wire:model.live="newForm.pemeriksaanLab" rows="2" :disabled="$isFormLocked" class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Pemeriksaan Penunjang (X-Ray/EKG/dll)" class="mb-1" />
                                    <x-textarea wire:model.live="newForm.pemeriksaanPenunjang" rows="2" :disabled="$isFormLocked" class="w-full" />
                                </div>
                            </div>
                        </section>

                        {{-- ══ KESIMPULAN ══ --}}
                        <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                            <h3 class="text-base font-semibold text-ink dark:text-gray-200">Kesimpulan Evaluasi Pra Anestesi</h3>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <x-input-label value="Jenis Anestesi *" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.jenisAnestesi" placeholder="cth: GA / Spinal / Sedasi" :disabled="$isFormLocked" class="w-full" />
                                    <x-input-error :messages="$errors->get('newForm.jenisAnestesi')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="PS ASA *" class="mb-1" />
                                    <x-select-input wire:model.live="newForm.psAsa" :disabled="$isFormLocked" class="w-full">
                                        <option value="">— pilih —</option>
                                        @foreach ($asaOptions as $opt)
                                            <option value="{{ $opt }}">{{ $opt }}</option>
                                        @endforeach
                                    </x-select-input>
                                    <x-input-error :messages="$errors->get('newForm.psAsa')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Induksi Pra Anestesi" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.induksiPraAnestesi" :disabled="$isFormLocked" class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Penyulit" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.penyulit" :disabled="$isFormLocked" class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Komplikasi" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.komplikasi" :disabled="$isFormLocked" class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Obat Analgesik Pasca Operasi" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.obatAnalgesikPascaOp" :disabled="$isFormLocked" class="w-full" />
                                </div>
                            </div>
                        </section>

                        {{-- ══ TTD ══ --}}
                        <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                {{-- Dokter Anestesi --}}
                                <div class="flex flex-col">
                                    <div class="mb-2 text-sm font-semibold tracking-wide text-center text-muted uppercase dark:text-gray-400">Dokter Anestesi</div>
                                    @if (empty($newForm['ttd']))
                                        @if (!$isFormLocked)
                                            <div class="flex items-center justify-center flex-1 p-6 border-2 border-gray-300 border-dashed rounded-xl dark:border-gray-700">
                                                <x-primary-button wire:click.prevent="setTtd" wire:loading.attr="disabled" wire:target="setTtd" class="gap-2">
                                                    <span wire:loading.remove wire:target="setTtd">TTD Dokter Anestesi</span>
                                                    <span wire:loading wire:target="setTtd"><x-loading class="w-4 h-4" /> ...</span>
                                                </x-primary-button>
                                            </div>
                                        @else
                                            <p class="py-8 text-base italic text-center text-muted-soft">Belum ditandatangani.</p>
                                        @endif
                                    @else
                                        <div class="flex flex-col items-center justify-center flex-1 p-4 border border-hairline bg-surface-soft rounded-xl dark:bg-gray-800 dark:border-gray-700">
                                            <div class="font-semibold text-center text-ink dark:text-gray-200">{{ $newForm['ttd'] }}</div>
                                            @if (!empty($newForm['ttdCode']))
                                                <div class="text-sm text-muted mt-0.5">Kode: {{ $newForm['ttdCode'] }}</div>
                                            @endif
                                            <div class="mt-1 text-sm text-muted">{{ $newForm['ttdDate'] ?? '-' }}</div>
                                            @if (!$isFormLocked)
                                                <x-outline-button type="button" wire:click.prevent="clearTtd" class="mt-2 !px-2 !py-1 text-sm">Hapus TTD</x-outline-button>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                                {{-- Pasien / Keluarga --}}
                                <div class="flex flex-col">
                                    <div class="mb-2 text-sm font-semibold tracking-wide text-center text-muted uppercase dark:text-gray-400">Pasien / Keluarga</div>
                                    @if (!empty($signaturePasien))
                                        <x-signature.signature-result :signature="$signaturePasien" :date="''" :disabled="$isFormLocked" wireMethod="clearSignaturePasien" />
                                    @elseif (!$isFormLocked)
                                        <x-signature.signature-pad wireMethod="setSignaturePasien" />
                                    @else
                                        <p class="py-8 text-base italic text-center text-muted-soft">Belum ditandatangani.</p>
                                    @endif
                                </div>
                            </div>
                        </section>

                        {{-- ══ DAFTAR TERSIMPAN ══ --}}
                        @if (count($praList) > 0)
                            <div class="mt-6 overflow-x-auto">
                                <h3 class="text-base font-semibold text-body dark:text-gray-300 pb-2 border-b border-hairline-soft dark:border-gray-800 mb-3">Daftar Pengkajian Tersimpan</h3>
                                <table class="min-w-full text-base border border-hairline rounded-lg dark:border-gray-700">
                                    <thead class="bg-surface-soft dark:bg-gray-800">
                                        <tr class="text-left text-muted dark:text-gray-300">
                                            <th class="px-4 py-2 border-b">Tanggal</th>
                                            <th class="px-4 py-2 border-b">ASA</th>
                                            <th class="px-4 py-2 border-b">Jenis Anestesi</th>
                                            <th class="px-4 py-2 border-b text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($praList as $p)
                                            <tr class="border-b border-hairline dark:border-gray-700 hover:bg-surface-soft dark:hover:bg-gray-800">
                                                <td class="px-4 py-2 text-muted dark:text-gray-400">{{ $p['tanggal'] ?? '-' }}</td>
                                                <td class="px-4 py-2 font-medium text-ink dark:text-gray-200">{{ $p['psAsa'] ?? '-' }}</td>
                                                <td class="px-4 py-2 text-muted dark:text-gray-400">{{ $p['jenisAnestesi'] ? Str::limit($p['jenisAnestesi'], 40) : '-' }}</td>
                                                <td class="px-4 py-2 text-center space-x-2 whitespace-nowrap">
                                                    <x-secondary-button wire:click="cetak('{{ $p['createdAt'] }}')" wire:loading.attr="disabled" wire:target="cetak('{{ $p['createdAt'] }}')" class="text-sm py-1 px-2">
                                                        <span wire:loading.remove wire:target="cetak('{{ $p['createdAt'] }}')" class="flex items-center gap-1">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                                                            Cetak
                                                        </span>
                                                        <span wire:loading wire:target="cetak('{{ $p['createdAt'] }}')" class="flex items-center gap-1"><x-loading /> ...</span>
                                                    </x-secondary-button>
                                                    @if (!$isFormLocked)
                                                        <x-outline-button type="button" wire:click.prevent="hapus('{{ $p['createdAt'] }}')" wire:confirm="Yakin hapus pengkajian ini?" wire:loading.attr="disabled" class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300 !px-2 !py-1" title="Hapus">
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

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 px-6 py-4 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-center justify-end gap-3">
                    <x-secondary-button wire:click="closeModal">Tutup</x-secondary-button>
                    @if ($riHdrNo && !$isFormLocked)
                        <x-primary-button wire:click.prevent="addEntry" wire:loading.attr="disabled" wire:target="addEntry" class="gap-2 min-w-[180px] justify-center">
                            <span wire:loading.remove wire:target="addEntry">Simpan Pengkajian</span>
                            <span wire:loading wire:target="addEntry"><x-loading class="w-4 h-4" /> Menyimpan...</span>
                        </x-primary-button>
                    @endif
                </div>
            </div>

        </div>
    </x-modal>
</div>
