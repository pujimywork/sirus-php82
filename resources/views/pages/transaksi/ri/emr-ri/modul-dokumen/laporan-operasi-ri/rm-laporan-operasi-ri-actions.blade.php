<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/laporan-operasi-ri/rm-laporan-operasi-ri-actions.blade.php

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
    protected array $renderAreas = ['modal-laporan-operasi-ri'];

    // ── Form entri baru (Laporan Operasi — PAB 7.2 + 7.4) ──
    public array $newForm = [
        'tanggalOperasi' => '',
        'diagnosisPraOp' => '',
        'diagnosisPascaOp' => '',
        'jenisTindakan' => '',
        'namaOperator' => '',
        'asisten1' => '',
        'instrumentor' => '',
        'namaAnestesi' => '',
        'asistenAnestesi' => '',
        'jenisAnestesi' => '',
        'golonganOperasi' => '',
        'macamOperasi' => '',
        'urgensi' => '',
        'jamMulai' => '',
        'jamSelesai' => '',
        'lamaOperasi' => '',
        'posisiPasien' => '',
        'komplikasi' => '',
        'jumlahPerdarahanCc' => '',
        // Transfusi (PAB 7.2 — jumlah darah masuk)
        'transfusiDiberikan' => false,
        'transfusiCc' => '',
        'transfusiJenis' => '',
        'pemeriksaanPa' => 'Tidak',
        'spesimenDetail' => '',
        'uraianLaporan' => '',
        'instruksiPascaBedah' => '',
        // Registry implan (PAB 7.4)
        'implanDipasang' => false,
        'jenisImplan' => '',
        'merkPabrikan' => '',
        'nomorSerial' => '',
        'ukuranImplan' => '',
        'lokasiPemasangan' => '',
        'sifatImplan' => '',
        // TTD operator (DPJP bedah)
        'operatorTtd' => '',
        'operatorTtdCode' => '',
        'operatorTtdDate' => '',
    ];

    public array $laporanList = [];

    public array $golonganOptions = ['Kecil', 'Sedang', 'Besar', 'Besar Khusus'];
    public array $macamOptions = ['Bersih', 'Bersih Terkontaminasi', 'Kontaminasi', 'Kotor'];
    public array $urgensiOptions = ['Elektif', 'Urgen', 'Cito'];
    public array $sifatImplanOptions = ['Permanen', 'Temporer'];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(?string $riHdrNo = null, bool $disabled = false): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-laporan-operasi-ri']);

        if ($this->riHdrNo) {
            $data = $this->findDataRI($this->riHdrNo);
            if ($data) {
                $this->dataDaftarRi = $data;
                $this->regNo = $data['regNo'] ?? null;
                $this->laporanList = $data['laporanOperasiRI'] ?? [];
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
        $this->resetValidation();

        $data = $this->findDataRI($this->riHdrNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }

        $this->dataDaftarRi = $data;
        $this->regNo = $data['regNo'] ?? null;
        if (!isset($this->dataDaftarRi['laporanOperasiRI']) || !is_array($this->dataDaftarRi['laporanOperasiRI'])) {
            $this->dataDaftarRi['laporanOperasiRI'] = [];
        }
        $this->laporanList = $this->dataDaftarRi['laporanOperasiRI'];
        $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $this->disabled;
        $this->incrementVersion('modal-laporan-operasi-ri');

        $this->dispatch('open-modal', name: "rm-laporan-operasi-ri-{$this->riHdrNo}");
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: "rm-laporan-operasi-ri-{$this->riHdrNo}");
    }

    /* ===============================
     | VALIDATION
     =============================== */
    protected function rules(): array
    {
        return [
            'newForm.tanggalOperasi' => 'required|date_format:d/m/Y H:i:s',
            'newForm.diagnosisPraOp' => 'required|string|max:1000',
            'newForm.diagnosisPascaOp' => 'required|string|max:1000',
            'newForm.jenisTindakan' => 'required|string|max:500',
            'newForm.namaOperator' => 'required|string|max:200',
            'newForm.golonganOperasi' => 'required|string',
            'newForm.macamOperasi' => 'required|string',
            'newForm.urgensi' => 'required|string',
            'newForm.jamMulai' => 'nullable|string|max:10',
            'newForm.jamSelesai' => 'nullable|string|max:10',
            'newForm.jumlahPerdarahanCc' => 'nullable|numeric|min:0',
            'newForm.transfusiCc' => 'required_if:newForm.transfusiDiberikan,true|nullable|numeric|min:0',
            'newForm.transfusiJenis' => 'nullable|string|max:200',
            'newForm.pemeriksaanPa' => 'required|string',
            'newForm.spesimenDetail' => 'required_if:newForm.pemeriksaanPa,Ya|nullable|string|max:500',
            'newForm.uraianLaporan' => 'required|string|max:5000',
            // Implan (wajib bila dipasang)
            'newForm.jenisImplan' => 'required_if:newForm.implanDipasang,true|nullable|string|max:200',
            'newForm.merkPabrikan' => 'required_if:newForm.implanDipasang,true|nullable|string|max:200',
            'newForm.nomorSerial' => 'required_if:newForm.implanDipasang,true|nullable|string|max:200',
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'required_if' => ':attribute wajib diisi bila ada pemasangan implan.',
            'date_format' => 'Format :attribute harus dd/mm/yyyy HH:mm:ss (cth: 26/06/2026 02:16:00).',
            'numeric' => ':attribute harus angka.',
            'max' => ':attribute maksimal :max karakter.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'newForm.tanggalOperasi' => 'Tanggal/jam operasi',
            'newForm.diagnosisPraOp' => 'Diagnosis pra-operasi',
            'newForm.diagnosisPascaOp' => 'Diagnosis pasca-operasi',
            'newForm.jenisTindakan' => 'Jenis tindakan operasi',
            'newForm.namaOperator' => 'Nama operator',
            'newForm.golonganOperasi' => 'Golongan operasi',
            'newForm.macamOperasi' => 'Macam operasi',
            'newForm.urgensi' => 'Urgensi operasi',
            'newForm.jumlahPerdarahanCc' => 'Jumlah perdarahan',
            'newForm.transfusiCc' => 'Jumlah transfusi (cc)',
            'newForm.transfusiJenis' => 'Jenis darah/produk transfusi',
            'newForm.pemeriksaanPa' => 'Pemeriksaan PA',
            'newForm.spesimenDetail' => 'Detail spesimen PA',
            'newForm.uraianLaporan' => 'Uraian laporan operasi',
            'newForm.jenisImplan' => 'Jenis implan',
            'newForm.merkPabrikan' => 'Merk/pabrikan implan',
            'newForm.nomorSerial' => 'Nomor serial/lot implan',
        ];
    }

    /* ===============================
     | SET TANGGAL SEKARANG
     =============================== */
    public function setTanggalOperasiSekarang(): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->newForm['tanggalOperasi'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /* ===============================
     | TTD OPERATOR (DPJP Bedah)
     =============================== */
    public function setOperatorTtd(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }

        if (!empty($this->newForm['operatorTtd'])) {
            $this->dispatch('toast', type: 'warning', message: 'Tanda tangan operator sudah ada.');
            return;
        }

        $this->newForm['operatorTtd'] = auth()->user()->myuser_name ?? '';
        $this->newForm['operatorTtdCode'] = auth()->user()->myuser_code ?? '';
        $this->newForm['operatorTtdDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        if (empty($this->newForm['namaOperator'])) {
            $this->newForm['namaOperator'] = $this->newForm['operatorTtd'];
        }
        $this->dispatch('toast', type: 'success', message: 'Tanda tangan operator berhasil ditambahkan.');
    }

    public function clearOperatorTtd(): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->newForm['operatorTtd'] = '';
        $this->newForm['operatorTtdCode'] = '';
        $this->newForm['operatorTtdDate'] = '';
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

        if (empty($this->newForm['operatorTtd'])) {
            $this->dispatch('toast', type: 'error', message: 'Tanda tangan operator belum diisi.');
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
                if (!isset($fresh['laporanOperasiRI']) || !is_array($fresh['laporanOperasiRI'])) {
                    $fresh['laporanOperasiRI'] = [];
                }

                $fresh['laporanOperasiRI'][] = $entry;

                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
                $this->laporanList = $fresh['laporanOperasiRI'];

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Tambah Laporan Operasi — ' . ($entry['jenisTindakan'] ?? '-') . ' — ' . ($entry['createdAt'] ?? '-'), 'MR');
            });

            $this->incrementVersion('modal-laporan-operasi-ri');
            $this->dispatch('toast', type: 'success', message: 'Laporan operasi berhasil disimpan.');

            $this->resetNewForm();
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | CETAK (inline stream PDF)
     =============================== */
    public function cetak(string $createdAt)
    {
        $entry = collect($this->laporanList)->firstWhere('createdAt', $createdAt);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Data laporan operasi tidak ditemukan.');
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

            // TTD Operator (myuser_code → myuser_ttd_image)
            $ttdOperatorPath = null;
            $operatorCode = $entry['operatorTtdCode'] ?? null;
            if ($operatorCode) {
                $ttdPath = DB::table('users')->where('myuser_code', $operatorCode)->value('myuser_ttd_image');
                if (!empty($ttdPath) && file_exists(public_path('storage/' . $ttdPath))) {
                    $ttdOperatorPath = public_path('storage/' . $ttdPath);
                }
            }

            $data = array_merge($pasien, [
                'dataRi' => $this->dataDaftarRi,
                'form' => $entry,
                'identitasRs' => $identitasRs,
                'ttdOperatorPath' => $ttdOperatorPath,
                'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
            ]);

            set_time_limit(300);

            $pdf = Pdf::loadView('pages.components.modul-dokumen.r-i.laporan-operasi-ri.cetak-laporan-operasi-ri-print', ['data' => $data])->setPaper('A4');

            $this->dispatch('toast', type: 'success', message: 'Berhasil mencetak laporan operasi.');
            return response()->streamDownload(fn() => print $pdf->output(), 'laporan-operasi-ri-' . ($pasien['regNo'] ?? $this->riHdrNo) . '.pdf');
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
                if (!isset($fresh['laporanOperasiRI'])) {
                    throw new \RuntimeException('Data laporan operasi tidak ditemukan.');
                }

                $fresh['laporanOperasiRI'] = collect($fresh['laporanOperasiRI'])
                    ->reject(fn($item) => ($item['createdAt'] ?? '') === $createdAt)
                    ->values()
                    ->toArray();

                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
                $this->laporanList = $fresh['laporanOperasiRI'];

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Laporan Operasi — ' . $createdAt, 'MR');
            });

            $this->incrementVersion('modal-laporan-operasi-ri');
            $this->dispatch('toast', type: 'success', message: 'Laporan operasi berhasil dihapus.');
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
            'tanggalOperasi' => '',
            'diagnosisPraOp' => '',
            'diagnosisPascaOp' => '',
            'jenisTindakan' => '',
            'namaOperator' => '',
            'asisten1' => '',
            'instrumentor' => '',
            'namaAnestesi' => '',
            'asistenAnestesi' => '',
            'jenisAnestesi' => '',
            'golonganOperasi' => '',
            'macamOperasi' => '',
            'urgensi' => '',
            'jamMulai' => '',
            'jamSelesai' => '',
            'lamaOperasi' => '',
            'posisiPasien' => '',
            'komplikasi' => '',
            'jumlahPerdarahanCc' => '',
            'transfusiDiberikan' => false,
            'transfusiCc' => '',
            'transfusiJenis' => '',
            'pemeriksaanPa' => 'Tidak',
            'spesimenDetail' => '',
            'uraianLaporan' => '',
            'instruksiPascaBedah' => '',
            'implanDipasang' => false,
            'jenisImplan' => '',
            'merkPabrikan' => '',
            'nomorSerial' => '',
            'ukuranImplan' => '',
            'lokasiPemasangan' => '',
            'sifatImplan' => '',
            'operatorTtd' => '',
            'operatorTtdCode' => '',
            'operatorTtdDate' => '',
        ];
    }
};
?>

<div>
    {{-- ══ SUMMARY CARD (inline) ══ --}}
    @php $loCount = count($laporanList ?? []); @endphp

    <div class="p-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex-1 space-y-3">
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">
                        Laporan Operasi
                    </h3>
                    @if ($loCount > 0)
                        <x-badge variant="success">{{ $loCount }} laporan</x-badge>
                    @else
                        <x-badge variant="warning">Belum ada</x-badge>
                    @endif
                </div>

                <p class="text-base text-muted dark:text-gray-400">
                    Laporan operasi (BAP) memuat diagnosis pra/pasca-op, tim bedah, uraian temuan, komplikasi, spesimen
                    PA, perdarahan & registry implan. Diisi operator <span class="font-medium">segera setelah
                        operasi</span> (PAB 7.2 &amp; 7.4).
                </p>

                @if ($loCount > 0)
                    <ul class="space-y-1 text-base text-muted dark:text-gray-300 list-disc pl-5">
                        @foreach (array_slice($laporanList, 0, 3) as $lo)
                            <li>
                                <span class="font-medium">{{ \Illuminate\Support\Str::limit($lo['jenisTindakan'] ?? '-', 60) ?: '-' }}</span>
                                @if (!empty($lo['tanggalOperasi']))
                                    <span class="text-sm text-muted-soft">— {{ $lo['tanggalOperasi'] }}</span>
                                @endif
                            </li>
                        @endforeach
                        @if ($loCount > 3)
                            <li class="text-sm italic text-muted-soft">+{{ $loCount - 3 }} lainnya…</li>
                        @endif
                    </ul>
                @endif
            </div>

            <div class="flex shrink-0">
                <x-primary-button type="button" wire:click="openModal" wire:loading.attr="disabled"
                    wire:target="openModal" :disabled="$disabled || !$riHdrNo" class="gap-2">
                    <span wire:loading.remove wire:target="openModal" class="flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M14 5l7 7m0 0l-7 7m7-7H3" />
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
    <x-modal name="rm-laporan-operasi-ri-{{ $riHdrNo ?? 'init' }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal-laporan-operasi-ri', [$riHdrNo ?? 'new']) }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-rose-500/10">
                                <svg class="w-6 h-6 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="font-semibold text-2xl text-ink dark:text-gray-100">Laporan Operasi (BAP)</h2>
                                <p class="mt-0.5 text-base text-muted dark:text-gray-400">
                                    PAB 7.2 &amp; 7.4 — diisi lengkap oleh operator sebelum pasien dipindah ke ruang lain
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 mt-3">
                            <x-badge variant="brand">Rawat Inap</x-badge>
                            @if (count($laporanList) > 0)
                                <x-badge variant="info">{{ count($laporanList) }} tersimpan</x-badge>
                            @endif
                            @if ($isFormLocked)
                                <x-badge variant="danger">Read Only</x-badge>
                            @endif
                        </div>
                    </div>

                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <span class="sr-only">Close</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-surface-soft/70 dark:bg-gray-950/20">
                <div class="max-w-full mx-auto space-y-4">

                    {{-- Display Pasien --}}
                    <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                        wire:key="lo-ri-display-pasien-{{ $riHdrNo ?? 'init' }}" />

                    <div
                        class="p-6 space-y-6 bg-canvas border border-hairline shadow-sm sm:p-8 rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                        @if ($isFormLocked)
                            <div
                                class="flex items-center gap-2 px-4 py-2.5 text-base font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                                EMR terkunci — data tidak dapat diubah.
                            </div>
                        @endif

                        {{-- ══ WAKTU & URGENSI ══ --}}
                        <section class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <x-input-label value="Tanggal / Jam Operasi *" class="mb-1" />
                                <div class="flex items-center gap-2">
                                    <x-text-input wire:model.live="newForm.tanggalOperasi"
                                        placeholder="dd/mm/yyyy HH:mm:ss" :error="$errors->has('newForm.tanggalOperasi')"
                                        :disabled="$isFormLocked" class="w-full" />
                                    @if (!$isFormLocked)
                                        <x-now-button wire:click="setTanggalOperasiSekarang" />
                                    @endif
                                </div>
                                <x-input-error :messages="$errors->get('newForm.tanggalOperasi')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Urgensi Operasi *" class="mb-1" />
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($urgensiOptions as $opt)
                                        <x-radio-button :label="$opt" :value="$opt" name="urgensi"
                                            wire:model.live="newForm.urgensi" :disabled="$isFormLocked" />
                                    @endforeach
                                </div>
                                <x-input-error :messages="$errors->get('newForm.urgensi')" class="mt-1" />
                            </div>
                        </section>

                        {{-- ══ DIAGNOSIS & TINDAKAN ══ --}}
                        <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <x-input-label value="Diagnosis Pra-operasi *" class="mb-1" />
                                    <x-textarea wire:model.live="newForm.diagnosisPraOp" :error="$errors->has('newForm.diagnosisPraOp')" rows="2"
                                        :disabled="$isFormLocked" class="w-full" />
                                    <x-input-error :messages="$errors->get('newForm.diagnosisPraOp')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Diagnosis Pasca-operasi *" class="mb-1" />
                                    <x-textarea wire:model.live="newForm.diagnosisPascaOp" :error="$errors->has('newForm.diagnosisPascaOp')" rows="2"
                                        :disabled="$isFormLocked" class="w-full" />
                                    <x-input-error :messages="$errors->get('newForm.diagnosisPascaOp')" class="mt-1" />
                                </div>
                            </div>
                            <div>
                                <x-input-label value="Jenis Tindakan Operasi *" class="mb-1" />
                                <x-textarea wire:model.live="newForm.jenisTindakan" :error="$errors->has('newForm.jenisTindakan')" rows="2"
                                    placeholder="cth: Disartikulasi metatarso-phalangeal digiti III pedis (D)"
                                    :disabled="$isFormLocked" class="w-full" />
                                <x-input-error :messages="$errors->get('newForm.jenisTindakan')" class="mt-1" />
                            </div>
                        </section>

                        {{-- ══ TIM BEDAH & ANESTESI ══ --}}
                        <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                            <h3 class="text-base font-semibold text-ink dark:text-gray-200">Tim Bedah & Anestesi</h3>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div>
                                    <x-input-label value="Nama Operator *" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.namaOperator" :error="$errors->has('newForm.namaOperator')" :disabled="$isFormLocked"
                                        class="w-full" />
                                    <x-input-error :messages="$errors->get('newForm.namaOperator')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Asisten 1" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.asisten1" :error="$errors->has('newForm.asisten1')" :disabled="$isFormLocked"
                                        class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Instrumentor" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.instrumentor" :error="$errors->has('newForm.instrumentor')" :disabled="$isFormLocked"
                                        class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Nama Anestesi" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.namaAnestesi" :error="$errors->has('newForm.namaAnestesi')" :disabled="$isFormLocked"
                                        class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Asisten Anestesi" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.asistenAnestesi" :error="$errors->has('newForm.asistenAnestesi')" :disabled="$isFormLocked"
                                        class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Jenis Anestesi" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.jenisAnestesi" :error="$errors->has('newForm.jenisAnestesi')"
                                        placeholder="cth: Regional / Spinal / GA" :disabled="$isFormLocked"
                                        class="w-full" />
                                </div>
                            </div>
                        </section>

                        {{-- ══ KLASIFIKASI & WAKTU OPERASI ══ --}}
                        <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <x-input-label value="Golongan Operasi *" class="mb-1" />
                                    <x-select-input wire:model.live="newForm.golonganOperasi" :error="$errors->has('newForm.golonganOperasi')" :disabled="$isFormLocked"
                                        class="w-full">
                                        <option value="">— pilih —</option>
                                        @foreach ($golonganOptions as $opt)
                                            <option value="{{ $opt }}">{{ $opt }}</option>
                                        @endforeach
                                    </x-select-input>
                                    <x-input-error :messages="$errors->get('newForm.golonganOperasi')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Macam Operasi (kelas luka) *" class="mb-1" />
                                    <x-select-input wire:model.live="newForm.macamOperasi" :error="$errors->has('newForm.macamOperasi')" :disabled="$isFormLocked"
                                        class="w-full">
                                        <option value="">— pilih —</option>
                                        @foreach ($macamOptions as $opt)
                                            <option value="{{ $opt }}">{{ $opt }}</option>
                                        @endforeach
                                    </x-select-input>
                                    <x-input-error :messages="$errors->get('newForm.macamOperasi')" class="mt-1" />
                                </div>
                            </div>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div>
                                    <x-input-label value="Jam Mulai" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.jamMulai" :error="$errors->has('newForm.jamMulai')" placeholder="HH:mm"
                                        :disabled="$isFormLocked" class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Jam Selesai" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.jamSelesai" :error="$errors->has('newForm.jamSelesai')" placeholder="HH:mm"
                                        :disabled="$isFormLocked" class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Lama Operasi" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.lamaOperasi" :error="$errors->has('newForm.lamaOperasi')" placeholder="cth: 2 jam"
                                        :disabled="$isFormLocked" class="w-full" />
                                </div>
                            </div>
                        </section>

                        {{-- ══ TEMUAN & PASCA ══ --}}
                        <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <x-input-label value="Posisi Pasien" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.posisiPasien" :error="$errors->has('newForm.posisiPasien')" placeholder="cth: Supine"
                                        :disabled="$isFormLocked" class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Jumlah Perdarahan (cc)" class="mb-1" />
                                    <x-text-input type="number" wire:model.live="newForm.jumlahPerdarahanCc" :error="$errors->has('newForm.jumlahPerdarahanCc')"
                                        :disabled="$isFormLocked" class="w-full" />
                                    <x-input-error :messages="$errors->get('newForm.jumlahPerdarahanCc')" class="mt-1" />
                                </div>
                            </div>
                            {{-- Transfusi (PAB 7.2 — jumlah darah masuk) --}}
                            <div class="space-y-3">
                                <x-toggle wire:model.live="newForm.transfusiDiberikan" :trueValue="true"
                                    :falseValue="false" label="Diberikan transfusi darah/produk darah?"
                                    :disabled="$isFormLocked" />
                                @if ($newForm['transfusiDiberikan'])
                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div>
                                            <x-input-label value="Jumlah Transfusi Masuk (cc) *" class="mb-1" />
                                            <x-text-input type="number" wire:model.live="newForm.transfusiCc" :error="$errors->has('newForm.transfusiCc')"
                                                :disabled="$isFormLocked" class="w-full" />
                                            <x-input-error :messages="$errors->get('newForm.transfusiCc')" class="mt-1" />
                                        </div>
                                        <div>
                                            <x-input-label value="Jenis Darah / Produk Darah" class="mb-1" />
                                            <x-text-input wire:model.live="newForm.transfusiJenis" :error="$errors->has('newForm.transfusiJenis')"
                                                placeholder="cth: PRC 2 kantong / WB / FFP" :disabled="$isFormLocked"
                                                class="w-full" />
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <div>
                                <x-input-label value="Komplikasi" class="mb-1" />
                                <x-textarea wire:model.live="newForm.komplikasi" :error="$errors->has('newForm.komplikasi')" rows="2"
                                    placeholder="Tuliskan komplikasi, atau 'Tidak ada'" :disabled="$isFormLocked"
                                    class="w-full" />
                            </div>
                            <div>
                                <x-input-label value="Pemeriksaan PA (spesimen ke Patologi Anatomi) *" class="mb-1" />
                                <div class="flex flex-wrap gap-2">
                                    @foreach (['Ya', 'Tidak'] as $opt)
                                        <x-radio-button :label="$opt" :value="$opt" name="pemeriksaanPa"
                                            wire:model.live="newForm.pemeriksaanPa" :disabled="$isFormLocked" />
                                    @endforeach
                                </div>
                                <x-input-error :messages="$errors->get('newForm.pemeriksaanPa')" class="mt-1" />
                            </div>
                            @if (($newForm['pemeriksaanPa'] ?? '') === 'Ya')
                                <div>
                                    <x-input-label value="Detail Spesimen yang Dikirim ke PA *" class="mb-1" />
                                    <x-textarea wire:model.live="newForm.spesimenDetail" :error="$errors->has('newForm.spesimenDetail')" rows="2"
                                        placeholder="cth: Jaringan nekrotik digiti III pedis (D)" :disabled="$isFormLocked"
                                        class="w-full" />
                                    <x-input-error :messages="$errors->get('newForm.spesimenDetail')" class="mt-1" />
                                </div>
                            @endif
                            <div>
                                <x-input-label value="Uraian Tindakan & Temuan Operasi *" class="mb-1" />
                                <x-textarea wire:model.live="newForm.uraianLaporan" :error="$errors->has('newForm.uraianLaporan')" rows="6"
                                    placeholder="Narasi langkah operasi & temuan..." :disabled="$isFormLocked"
                                    class="w-full" />
                                <x-input-error :messages="$errors->get('newForm.uraianLaporan')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Instruksi Pasca-bedah" class="mb-1" />
                                <x-textarea wire:model.live="newForm.instruksiPascaBedah" :error="$errors->has('newForm.instruksiPascaBedah')" rows="3"
                                    :disabled="$isFormLocked" class="w-full" />
                            </div>
                        </section>

                        {{-- ══ REGISTRY IMPLAN (PAB 7.4) ══ --}}
                        <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                            <x-toggle wire:model.live="newForm.implanDipasang" :trueValue="true" :falseValue="false"
                                label="Ada pemasangan implan? (PAB 7.4)" :disabled="$isFormLocked" />

                            @if ($newForm['implanDipasang'])
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                    <div>
                                        <x-input-label value="Jenis Implan *" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.jenisImplan" :error="$errors->has('newForm.jenisImplan')" :disabled="$isFormLocked"
                                            class="w-full" />
                                        <x-input-error :messages="$errors->get('newForm.jenisImplan')" class="mt-1" />
                                    </div>
                                    <div>
                                        <x-input-label value="Merk / Pabrikan *" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.merkPabrikan" :error="$errors->has('newForm.merkPabrikan')" :disabled="$isFormLocked"
                                            class="w-full" />
                                        <x-input-error :messages="$errors->get('newForm.merkPabrikan')" class="mt-1" />
                                    </div>
                                    <div>
                                        <x-input-label value="Nomor Serial / Lot *" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.nomorSerial" :error="$errors->has('newForm.nomorSerial')" :disabled="$isFormLocked"
                                            class="w-full" />
                                        <x-input-error :messages="$errors->get('newForm.nomorSerial')" class="mt-1" />
                                    </div>
                                    <div>
                                        <x-input-label value="Ukuran / Spesifikasi" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.ukuranImplan" :error="$errors->has('newForm.ukuranImplan')" :disabled="$isFormLocked"
                                            class="w-full" />
                                    </div>
                                    <div>
                                        <x-input-label value="Lokasi Pemasangan" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.lokasiPemasangan" :error="$errors->has('newForm.lokasiPemasangan')" :disabled="$isFormLocked"
                                            class="w-full" />
                                    </div>
                                    <div>
                                        <x-input-label value="Sifat" class="mb-1" />
                                        <x-select-input wire:model.live="newForm.sifatImplan" :error="$errors->has('newForm.sifatImplan')" :disabled="$isFormLocked"
                                            class="w-full">
                                            <option value="">— pilih —</option>
                                            @foreach ($sifatImplanOptions as $opt)
                                                <option value="{{ $opt }}">{{ $opt }}</option>
                                            @endforeach
                                        </x-select-input>
                                    </div>
                                </div>
                            @endif
                        </section>

                        {{-- ══ TTD OPERATOR ══ --}}
                        <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                            <h3 class="text-base font-semibold text-ink dark:text-gray-200">Tanda Tangan Operator</h3>
                            <div class="max-w-md">
                                @if (empty($newForm['operatorTtd']))
                                    @if (!$isFormLocked)
                                        <div
                                            class="flex items-center justify-center p-6 border-2 border-gray-300 border-dashed rounded-xl dark:border-gray-700">
                                            <x-primary-button wire:click.prevent="setOperatorTtd"
                                                wire:loading.attr="disabled" wire:target="setOperatorTtd" class="gap-2">
                                                <span wire:loading.remove wire:target="setOperatorTtd"
                                                    class="flex items-center gap-1.5">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 012.828 2.828L11.828 15.828a4 4 0 01-2.828 1.172H7v-2a4 4 0 011.172-2.828z" />
                                                    </svg>
                                                    TTD sebagai Operator
                                                </span>
                                                <span wire:loading wire:target="setOperatorTtd">
                                                    <x-loading class="w-4 h-4" /> Menyimpan...
                                                </span>
                                            </x-primary-button>
                                        </div>
                                    @else
                                        <p class="py-8 text-base italic text-center text-muted-soft">Belum
                                            ditandatangani.</p>
                                    @endif
                                @else
                                    <div
                                        class="flex flex-col items-center justify-center p-4 border border-hairline bg-surface-soft rounded-xl dark:bg-gray-800 dark:border-gray-700">
                                        <div class="font-semibold text-center text-ink dark:text-gray-200">
                                            {{ $newForm['operatorTtd'] }}
                                        </div>
                                        @if (!empty($newForm['operatorTtdCode']))
                                            <div class="text-sm text-muted mt-0.5">Kode:
                                                {{ $newForm['operatorTtdCode'] }}</div>
                                        @endif
                                        <div class="mt-1 text-sm text-muted">{{ $newForm['operatorTtdDate'] ?? '-' }}
                                        </div>
                                        @if (!$isFormLocked)
                                            <x-outline-button type="button" wire:click.prevent="clearOperatorTtd"
                                                class="mt-2 !px-2 !py-1 text-sm">Hapus TTD</x-outline-button>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </section>

                        {{-- ══ DAFTAR TERSIMPAN ══ --}}
                        @if (count($laporanList) > 0)
                            <div class="mt-6 overflow-x-auto">
                                <h3
                                    class="text-base font-semibold text-body dark:text-gray-300 pb-2 border-b border-hairline-soft dark:border-gray-800 mb-3">
                                    Daftar Laporan Operasi Tersimpan
                                </h3>
                                <table
                                    class="min-w-full text-base border border-hairline rounded-lg dark:border-gray-700">
                                    <thead class="bg-surface-soft dark:bg-gray-800">
                                        <tr class="text-left text-muted dark:text-gray-300">
                                            <th class="px-4 py-2 border-b">Tgl Operasi</th>
                                            <th class="px-4 py-2 border-b">Tindakan</th>
                                            <th class="px-4 py-2 border-b">Operator</th>
                                            <th class="px-4 py-2 border-b text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($laporanList as $lo)
                                            <tr
                                                class="border-b border-hairline dark:border-gray-700 hover:bg-surface-soft dark:hover:bg-gray-800">
                                                <td class="px-4 py-2 text-muted dark:text-gray-400">
                                                    {{ $lo['tanggalOperasi'] ?? '-' }}</td>
                                                <td class="px-4 py-2 font-medium text-ink dark:text-gray-200">
                                                    {{ $lo['jenisTindakan'] ? Str::limit($lo['jenisTindakan'], 45) : '-' }}
                                                </td>
                                                <td class="px-4 py-2 text-muted dark:text-gray-400">
                                                    {{ $lo['namaOperator'] ?? '-' }}</td>
                                                <td class="px-4 py-2 text-center space-x-2 whitespace-nowrap">
                                                    <x-secondary-button wire:click="cetak('{{ $lo['createdAt'] }}')"
                                                        wire:loading.attr="disabled"
                                                        wire:target="cetak('{{ $lo['createdAt'] }}')"
                                                        class="text-sm py-1 px-2">
                                                        <span wire:loading.remove
                                                            wire:target="cetak('{{ $lo['createdAt'] }}')"
                                                            class="flex items-center gap-1">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24" stroke-width="2">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                            </svg>
                                                            Cetak
                                                        </span>
                                                        <span wire:loading
                                                            wire:target="cetak('{{ $lo['createdAt'] }}')"
                                                            class="flex items-center gap-1"><x-loading />
                                                            Mencetak...</span>
                                                    </x-secondary-button>
                                                    @if (!$isFormLocked)
                                                        <x-outline-button type="button"
                                                            wire:click.prevent="hapus('{{ $lo['createdAt'] }}')"
                                                            wire:confirm="Yakin hapus laporan operasi ini?"
                                                            wire:loading.attr="disabled"
                                                            class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300 !px-2 !py-1"
                                                            title="Hapus">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2"
                                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                            </svg>
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
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-center justify-end gap-3">
                    <x-secondary-button wire:click="closeModal">Tutup</x-secondary-button>

                    @if ($riHdrNo && !$isFormLocked)
                        <x-primary-button wire:click.prevent="addEntry" wire:loading.attr="disabled"
                            wire:target="addEntry" class="gap-2 min-w-[180px] justify-center">
                            <span wire:loading.remove wire:target="addEntry">Simpan Laporan Operasi</span>
                            <span wire:loading wire:target="addEntry"><x-loading class="w-4 h-4" />
                                Menyimpan...</span>
                        </x-primary-button>
                    @endif
                </div>
            </div>

        </div>
    </x-modal>
</div>
