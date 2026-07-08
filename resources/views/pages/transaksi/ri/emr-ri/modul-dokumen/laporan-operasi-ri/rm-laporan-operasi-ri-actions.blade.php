<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/laporan-operasi-ri/rm-laporan-operasi-ri-actions.blade.php
// Laporan Operasi (BAP) — PAB 7.2 & 7.4.
// Pola: multi-entri (Draft + Lanjut Isi + TTD-Kunci + Lihat read-only + tabel expandable),
// disimpan ke datadaftarri_json['laporanOperasiRI']. Kunci entri stabil = createdAt.
// TTD Operator = stempel nama user login (setOperatorTtd = FINALIZE/kunci entri).
// Indikator "operator sudah TTD" = field operatorTtd (nama), dipakai entryIsFinal.

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

    /** Key penyimpanan di datadaftarri_json */
    private string $jsonKey = 'laporanOperasiRI';

    // ── Form entri (Laporan Operasi — PAB 7.2 + 7.4) ──
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
        // TTD operator (DPJP bedah) — pengisian ini = FINALIZE/kunci entri
        'operatorTtd' => '',
        'operatorTtdCode' => '',
        'operatorTtdDate' => '',
    ];

    public array $laporanList = [];

    public array $golonganOptions = ['Kecil', 'Sedang', 'Besar', 'Besar Khusus'];
    public array $macamOptions = ['Bersih', 'Bersih Terkontaminasi', 'Kontaminasi', 'Kotor'];
    public array $urgensiOptions = ['Elektif', 'Urgen', 'Cito'];
    public array $sifatImplanOptions = ['Permanen', 'Temporer'];

    // Kunci entri yang sedang diedit (createdAt = kunci stabil). null = membuat entri baru.
    public ?string $editingKey = null;

    // true = entri terkunci sedang ditampilkan di form dalam mode read-only (lihat saja).
    public bool $viewOnly = false;

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
                $this->laporanList = $data[$this->jsonKey] ?? [];
                $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $disabled;
            }
        }
    }

    /* ===============================
     | OPEN / CLOSE MODAL
     =============================== */
    public function openModal(): void
    {
        if (!$this->riHdrNo || $this->disabled) {
            return;
        }

        $this->resetNewForm();
        $this->editingKey = null;
        $this->viewOnly = false;
        $this->resetValidation();

        $data = $this->findDataRI($this->riHdrNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }

        $this->dataDaftarRi = $data;
        $this->regNo = $data['regNo'] ?? null;
        if (!isset($this->dataDaftarRi[$this->jsonKey]) || !is_array($this->dataDaftarRi[$this->jsonKey])) {
            $this->dataDaftarRi[$this->jsonKey] = [];
        }
        $this->laporanList = $this->dataDaftarRi[$this->jsonKey];
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
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->newForm['tanggalOperasi'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /* ===============================
     | HELPER — status & bentuk entri
     =============================== */
    // Entri FINAL/terkunci bila flag finalized true; entri lama (tanpa flag) yang sudah
    // ada TTD operator (nama) dianggap final (kompatibilitas data lama).
    public function entryIsFinal(array $e): bool
    {
        return array_key_exists('finalized', $e) ? (bool) $e['finalized'] : !empty($e['operatorTtd']);
    }

    // Susun array entri dari state form. $key = createdAt (kunci stabil); $finalized = status kunci.
    private function buildEntry(string $key, bool $finalized): array
    {
        $entry = $this->newForm;
        $entry['createdAt'] = $key;
        $entry['finalized'] = $finalized;
        return $entry;
    }

    // Cek: minimal salah satu isian inti terisi (untuk draft).
    private function adaLaporanInti(): bool
    {
        return collect(['tanggalOperasi', 'jenisTindakan', 'diagnosisPraOp', 'uraianLaporan'])
            ->contains(fn($k) => filled($this->newForm[$k] ?? null));
    }

    // Simpan entri (add/update by createdAt) dengan status $finalized. Dipakai draft & kunci.
    private function persistEntry(string $key, bool $finalized, string $logVerb): void
    {
        $entry = $this->buildEntry($key, $finalized);

        DB::transaction(function () use ($entry, $key, $logVerb) {
            $this->lockRIRow($this->riHdrNo);

            $fresh = $this->findDataRI($this->riHdrNo) ?: [];
            if (empty($fresh)) {
                throw new \RuntimeException('Data RI tidak ditemukan, simpan dibatalkan.');
            }
            if (!isset($fresh[$this->jsonKey]) || !is_array($fresh[$this->jsonKey])) {
                $fresh[$this->jsonKey] = [];
            }

            $list = $fresh[$this->jsonKey];
            $idx = collect($list)->search(fn($it) => ($it['createdAt'] ?? '') === $key);
            if ($idx === false) {
                $list[] = $entry;
            } else {
                if ($this->entryIsFinal($list[$idx])) {
                    throw new \RuntimeException('Entri sudah terkunci, tidak dapat diubah.');
                }
                $list[$idx] = $entry;
            }
            $fresh[$this->jsonKey] = array_values($list);

            $this->updateJsonRI((int) $this->riHdrNo, $fresh);
            $this->dataDaftarRi = $fresh;
            $this->laporanList = $fresh[$this->jsonKey];

            $this->appendAdminLogRI((int) $this->riHdrNo, $logVerb . ' Laporan Operasi — ' . ($entry['jenisTindakan'] ?: '-') . ' (' . $key . ')', 'MR');
        });
    }

    /* ===============================
     | SIMPAN DRAFT (nyicil, tanpa wajib TTD)
     =============================== */
    public function saveDraft(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }
        if (!$this->adaLaporanInti()) {
            $this->dispatch('toast', type: 'error', message: 'Isi minimal salah satu: Tgl Operasi, Jenis Tindakan, Diagnosis Pra-Op, atau Uraian Laporan.');
            return;
        }

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, false, 'Simpan draft');
            $this->editingKey = $key; // lanjut edit entri yang sama, tidak buat duplikat
            $this->incrementVersion('modal-laporan-operasi-ri');
            $this->dispatch('toast', type: 'success', message: 'Draft tersimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan draft: ' . $e->getMessage());
        }
    }

    /* ===============================
     | TTD OPERATOR = FINALIZE (kunci entri)
     | Stempel nama user login + tgl/jam → validasi lengkap → kunci entri.
     =============================== */
    public function setOperatorTtd(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }

        // Stempel TTD operator = user login (jadikan operator bila belum diisi).
        $this->newForm['operatorTtd'] = auth()->user()->myuser_name ?? '';
        $this->newForm['operatorTtdCode'] = auth()->user()->myuser_code ?? '';
        $this->newForm['operatorTtdDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        if (empty($this->newForm['namaOperator'])) {
            $this->newForm['namaOperator'] = $this->newForm['operatorTtd'];
        }

        // Validasi lengkap sebelum kunci (throw ValidationException → hentikan bila gagal).
        $this->validateWithToast();

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, true, 'Kunci (TTD)');
            $this->resetNewForm();
            $this->editingKey = null;
            $this->viewOnly = false;
            $this->incrementVersion('modal-laporan-operasi-ri');
            $this->dispatch('toast', type: 'success', message: 'Laporan operasi ditandatangani & terkunci.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal mengunci: ' . $e->getMessage());
        }
    }

    /** Batalkan TTD pada form (saat draft/edit, sebelum finalize benar-benar tersimpan). */
    public function clearOperatorTtd(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->newForm['operatorTtd'] = '';
        $this->newForm['operatorTtdCode'] = '';
        $this->newForm['operatorTtdDate'] = '';
    }

    /* ===============================
     | EDIT / LIHAT / BATAL entri
     =============================== */
    // Muat 1 entri ke form atas (dipakai edit draft & lihat entri terkunci).
    private function hydrateFormFromEntry(array $entry, string $key): void
    {
        foreach ($this->newForm as $k => $v) {
            if (array_key_exists($k, $entry)) {
                $this->newForm[$k] = $entry[$k];
            } else {
                $this->newForm[$k] = is_bool($v) ? false : (is_array($v) ? [] : '');
            }
        }
        $this->editingKey = $key;
        $this->resetValidation();
        $this->incrementVersion('modal-laporan-operasi-ri');
    }

    public function editEntry(string $key): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        $entry = collect($this->laporanList)->firstWhere('createdAt', $key);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Entri tidak ditemukan.');
            return;
        }
        if ($this->entryIsFinal($entry)) {
            $this->dispatch('toast', type: 'warning', message: 'Entri sudah terkunci, tidak dapat diedit.');
            return;
        }

        $this->viewOnly = false;
        $this->hydrateFormFromEntry($entry, $key);
        $this->dispatch('toast', type: 'info', message: 'Draft dimuat untuk dilanjutkan.');
    }

    // Lihat entri terkunci: muat ke form atas dalam mode read-only.
    public function viewEntry(string $key): void
    {
        $entry = collect($this->laporanList)->firstWhere('createdAt', $key);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Entri tidak ditemukan.');
            return;
        }

        $this->viewOnly = true;
        $this->hydrateFormFromEntry($entry, $key);
        $this->dispatch('toast', type: 'info', message: 'Menampilkan entri terkunci (hanya lihat).');
    }

    public function cancelEdit(): void
    {
        $this->resetNewForm();
        $this->editingKey = null;
        $this->viewOnly = false;
        $this->resetValidation();
        $this->incrementVersion('modal-laporan-operasi-ri');
    }

    /* ===============================
     | CETAK (per-entri, inline stream PDF)
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
     | HAPUS entri (final atau draft)
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
                if (!isset($fresh[$this->jsonKey])) {
                    throw new \RuntimeException('Data laporan operasi tidak ditemukan.');
                }

                $fresh[$this->jsonKey] = collect($fresh[$this->jsonKey])
                    ->reject(fn($item) => ($item['createdAt'] ?? '') === $createdAt)
                    ->values()
                    ->toArray();

                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
                $this->laporanList = $fresh[$this->jsonKey];

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Laporan Operasi — ' . $createdAt, 'MR');
            });

            // Jika entri yang dihapus sedang di form, kosongkan form.
            if ($this->editingKey === $createdAt) {
                $this->cancelEdit();
            }

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

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->dataDaftarRi = [];
        $this->laporanList = [];
        $this->resetNewForm();
        $this->editingKey = null;
        $this->viewOnly = false;
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
                    PA, perdarahan &amp; registry implan. Diisi operator <span class="font-medium">segera setelah
                        operasi</span> (PAB 7.2 &amp; 7.4). Bisa dicicil (Draft), lalu dikunci lewat TTD Operator.
                </p>

                @if ($loCount > 0)
                    <ul class="space-y-1 text-base text-muted dark:text-gray-300 list-disc pl-5">
                        @foreach (array_slice($laporanList, 0, 3) as $lo)
                            <li>
                                <span class="font-medium">{{ \Illuminate\Support\Str::limit($lo['jenisTindakan'] ?? '-', 60) ?: '-' }}</span>
                                @if (!empty($lo['tanggalOperasi']))
                                    <span class="text-sm text-muted-soft">— {{ $lo['tanggalOperasi'] }}</span>
                                @endif
                                @if ($this->entryIsFinal($lo))
                                    <x-badge variant="info">Terkunci</x-badge>
                                @else
                                    <x-badge variant="warning">Draft</x-badge>
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
                                    PAB 7.2 &amp; 7.4 — tiap entri = 1 laporan operasi; cicil Draft lalu kunci lewat TTD Operator
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
            <div class="flex-1 px-4 py-4 overflow-y-auto bg-surface-soft/70 dark:bg-gray-950/20">
                <div class="max-w-full mx-auto space-y-4">

                    {{-- Display Pasien --}}
                    <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                        wire:key="lo-ri-display-pasien-{{ $riHdrNo ?? 'init' }}" />

                    @php $formRO = $isFormLocked || $viewOnly; @endphp

                    {{-- BANNER: read-only / view-only / editing --}}
                    @if ($isFormLocked)
                        <div class="flex items-center gap-2 px-4 py-2.5 text-base font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            EMR terkunci — data tidak dapat diubah.
                        </div>
                    @endif

                    @if ($viewOnly)
                        <div class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-sky-700 bg-sky-50 border border-sky-200 rounded-lg dark:bg-sky-900/20 dark:border-sky-600 dark:text-sky-300">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            Menampilkan entri terkunci <strong>{{ $editingKey }}</strong> (hanya lihat) — klik <strong>Selesai Melihat</strong> untuk kembali ke form entri baru.
                        </div>
                    @elseif ($editingKey && !$isFormLocked)
                        <div class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-brand-green bg-brand-lime/10 border border-brand-lime/40 rounded-lg dark:text-brand-lime dark:bg-brand-lime/5">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Sedang melanjutkan entri <strong>{{ $editingKey }}</strong> — <strong>Simpan Perubahan</strong> menyimpan ke entri ini; klik <strong>Entri Baru</strong> untuk menambah laporan lain.
                        </div>
                    @endif

                    <div
                        class="p-6 space-y-6 bg-canvas border border-hairline shadow-sm sm:p-8 rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                        {{-- ── FORM ENTRI (1 laporan) ── --}}
                        <fieldset @disabled($formRO) class="space-y-6">

                            {{-- ══ WAKTU & URGENSI ══ --}}
                            <section class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <x-input-label value="Tanggal / Jam Operasi *" class="mb-1" />
                                    <div class="flex items-center gap-2">
                                        <x-text-input wire:model.live="newForm.tanggalOperasi"
                                            placeholder="dd/mm/yyyy HH:mm:ss" :error="$errors->has('newForm.tanggalOperasi')"
                                            class="w-full" />
                                        @if (!$formRO)
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
                                                wire:model.live="newForm.urgensi" />
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
                                            class="w-full" />
                                        <x-input-error :messages="$errors->get('newForm.diagnosisPraOp')" class="mt-1" />
                                    </div>
                                    <div>
                                        <x-input-label value="Diagnosis Pasca-operasi *" class="mb-1" />
                                        <x-textarea wire:model.live="newForm.diagnosisPascaOp" :error="$errors->has('newForm.diagnosisPascaOp')" rows="2"
                                            class="w-full" />
                                        <x-input-error :messages="$errors->get('newForm.diagnosisPascaOp')" class="mt-1" />
                                    </div>
                                </div>
                                <div>
                                    <x-input-label value="Jenis Tindakan Operasi *" class="mb-1" />
                                    <x-textarea wire:model.live="newForm.jenisTindakan" :error="$errors->has('newForm.jenisTindakan')" rows="2"
                                        placeholder="cth: Disartikulasi metatarso-phalangeal digiti III pedis (D)"
                                        class="w-full" />
                                    <x-input-error :messages="$errors->get('newForm.jenisTindakan')" class="mt-1" />
                                </div>
                            </section>

                            {{-- ══ TIM BEDAH & ANESTESI ══ --}}
                            <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                                <h3 class="text-base font-semibold text-ink dark:text-gray-200">Tim Bedah & Anestesi</h3>
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                    <div>
                                        <x-input-label value="Nama Operator *" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.namaOperator" :error="$errors->has('newForm.namaOperator')"
                                            class="w-full" />
                                        <x-input-error :messages="$errors->get('newForm.namaOperator')" class="mt-1" />
                                    </div>
                                    <div>
                                        <x-input-label value="Asisten 1" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.asisten1" :error="$errors->has('newForm.asisten1')"
                                            class="w-full" />
                                    </div>
                                    <div>
                                        <x-input-label value="Instrumentor" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.instrumentor" :error="$errors->has('newForm.instrumentor')"
                                            class="w-full" />
                                    </div>
                                    <div>
                                        <x-input-label value="Nama Anestesi" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.namaAnestesi" :error="$errors->has('newForm.namaAnestesi')"
                                            class="w-full" />
                                    </div>
                                    <div>
                                        <x-input-label value="Asisten Anestesi" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.asistenAnestesi" :error="$errors->has('newForm.asistenAnestesi')"
                                            class="w-full" />
                                    </div>
                                    <div>
                                        <x-input-label value="Jenis Anestesi" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.jenisAnestesi" :error="$errors->has('newForm.jenisAnestesi')"
                                            placeholder="cth: Regional / Spinal / GA"
                                            class="w-full" />
                                    </div>
                                </div>
                            </section>

                            {{-- ══ KLASIFIKASI & WAKTU OPERASI ══ --}}
                            <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <x-input-label value="Golongan Operasi *" class="mb-1" />
                                        <x-select-input wire:model.live="newForm.golonganOperasi" :error="$errors->has('newForm.golonganOperasi')"
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
                                        <x-select-input wire:model.live="newForm.macamOperasi" :error="$errors->has('newForm.macamOperasi')"
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
                                            class="w-full" />
                                    </div>
                                    <div>
                                        <x-input-label value="Jam Selesai" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.jamSelesai" :error="$errors->has('newForm.jamSelesai')" placeholder="HH:mm"
                                            class="w-full" />
                                    </div>
                                    <div>
                                        <x-input-label value="Lama Operasi" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.lamaOperasi" :error="$errors->has('newForm.lamaOperasi')" placeholder="cth: 2 jam"
                                            class="w-full" />
                                    </div>
                                </div>
                            </section>

                            {{-- ══ TEMUAN & PASCA ══ --}}
                            <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <x-input-label value="Posisi Pasien" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.posisiPasien" :error="$errors->has('newForm.posisiPasien')" placeholder="cth: Supine"
                                            class="w-full" />
                                    </div>
                                    <div>
                                        <x-input-label value="Jumlah Perdarahan (cc)" class="mb-1" />
                                        <x-text-input type="number" wire:model.live="newForm.jumlahPerdarahanCc" :error="$errors->has('newForm.jumlahPerdarahanCc')"
                                            class="w-full" />
                                        <x-input-error :messages="$errors->get('newForm.jumlahPerdarahanCc')" class="mt-1" />
                                    </div>
                                </div>
                                {{-- Transfusi (PAB 7.2 — jumlah darah masuk) --}}
                                <div class="space-y-3">
                                    <x-toggle wire:model.live="newForm.transfusiDiberikan" :trueValue="true"
                                        :falseValue="false" label="Diberikan transfusi darah/produk darah?" />
                                    @if ($newForm['transfusiDiberikan'])
                                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                            <div>
                                                <x-input-label value="Jumlah Transfusi Masuk (cc) *" class="mb-1" />
                                                <x-text-input type="number" wire:model.live="newForm.transfusiCc" :error="$errors->has('newForm.transfusiCc')"
                                                    class="w-full" />
                                                <x-input-error :messages="$errors->get('newForm.transfusiCc')" class="mt-1" />
                                            </div>
                                            <div>
                                                <x-input-label value="Jenis Darah / Produk Darah" class="mb-1" />
                                                <x-text-input wire:model.live="newForm.transfusiJenis" :error="$errors->has('newForm.transfusiJenis')"
                                                    placeholder="cth: PRC 2 kantong / WB / FFP"
                                                    class="w-full" />
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                <div>
                                    <x-input-label value="Komplikasi" class="mb-1" />
                                    <x-textarea wire:model.live="newForm.komplikasi" :error="$errors->has('newForm.komplikasi')" rows="2"
                                        placeholder="Tuliskan komplikasi, atau 'Tidak ada'"
                                        class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Pemeriksaan PA (spesimen ke Patologi Anatomi) *" class="mb-1" />
                                    <div class="flex flex-wrap gap-2">
                                        @foreach (['Ya', 'Tidak'] as $opt)
                                            <x-radio-button :label="$opt" :value="$opt" name="pemeriksaanPa"
                                                wire:model.live="newForm.pemeriksaanPa" />
                                        @endforeach
                                    </div>
                                    <x-input-error :messages="$errors->get('newForm.pemeriksaanPa')" class="mt-1" />
                                </div>
                                @if (($newForm['pemeriksaanPa'] ?? '') === 'Ya')
                                    <div>
                                        <x-input-label value="Detail Spesimen yang Dikirim ke PA *" class="mb-1" />
                                        <x-textarea wire:model.live="newForm.spesimenDetail" :error="$errors->has('newForm.spesimenDetail')" rows="2"
                                            placeholder="cth: Jaringan nekrotik digiti III pedis (D)"
                                            class="w-full" />
                                        <x-input-error :messages="$errors->get('newForm.spesimenDetail')" class="mt-1" />
                                    </div>
                                @endif
                                <div>
                                    <x-input-label value="Uraian Tindakan & Temuan Operasi *" class="mb-1" />
                                    <x-textarea wire:model.live="newForm.uraianLaporan" :error="$errors->has('newForm.uraianLaporan')" rows="6"
                                        placeholder="Narasi langkah operasi & temuan..."
                                        class="w-full" />
                                    <x-input-error :messages="$errors->get('newForm.uraianLaporan')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Instruksi Pasca-bedah" class="mb-1" />
                                    <x-textarea wire:model.live="newForm.instruksiPascaBedah" :error="$errors->has('newForm.instruksiPascaBedah')" rows="3"
                                        class="w-full" />
                                </div>
                            </section>

                            {{-- ══ REGISTRY IMPLAN (PAB 7.4) ══ --}}
                            <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                                <x-toggle wire:model.live="newForm.implanDipasang" :trueValue="true" :falseValue="false"
                                    label="Ada pemasangan implan? (PAB 7.4)" />

                                @if ($newForm['implanDipasang'])
                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                        <div>
                                            <x-input-label value="Jenis Implan *" class="mb-1" />
                                            <x-text-input wire:model.live="newForm.jenisImplan" :error="$errors->has('newForm.jenisImplan')"
                                                class="w-full" />
                                            <x-input-error :messages="$errors->get('newForm.jenisImplan')" class="mt-1" />
                                        </div>
                                        <div>
                                            <x-input-label value="Merk / Pabrikan *" class="mb-1" />
                                            <x-text-input wire:model.live="newForm.merkPabrikan" :error="$errors->has('newForm.merkPabrikan')"
                                                class="w-full" />
                                            <x-input-error :messages="$errors->get('newForm.merkPabrikan')" class="mt-1" />
                                        </div>
                                        <div>
                                            <x-input-label value="Nomor Serial / Lot *" class="mb-1" />
                                            <x-text-input wire:model.live="newForm.nomorSerial" :error="$errors->has('newForm.nomorSerial')"
                                                class="w-full" />
                                            <x-input-error :messages="$errors->get('newForm.nomorSerial')" class="mt-1" />
                                        </div>
                                        <div>
                                            <x-input-label value="Ukuran / Spesifikasi" class="mb-1" />
                                            <x-text-input wire:model.live="newForm.ukuranImplan" :error="$errors->has('newForm.ukuranImplan')"
                                                class="w-full" />
                                        </div>
                                        <div>
                                            <x-input-label value="Lokasi Pemasangan" class="mb-1" />
                                            <x-text-input wire:model.live="newForm.lokasiPemasangan" :error="$errors->has('newForm.lokasiPemasangan')"
                                                class="w-full" />
                                        </div>
                                        <div>
                                            <x-input-label value="Sifat" class="mb-1" />
                                            <x-select-input wire:model.live="newForm.sifatImplan" :error="$errors->has('newForm.sifatImplan')"
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

                            {{-- ══ TTD OPERATOR & KUNCI ══ --}}
                            <x-signature.ttd-petugas :ttd="$newForm['operatorTtd']"
                                :date="$newForm['operatorTtdDate'] ?? ''" :code="$newForm['operatorTtdCode'] ?? ''"
                                :locked="$formRO" sign="setOperatorTtd" clear="clearOperatorTtd"
                                title="TTD Operator & Kunci"
                                nameLabel="Operator (DPJP Bedah)" dateLabel="Waktu TTD"
                                signLabel="TTD Operator &amp; Kunci" clearLabel="Batal TTD" />
                            @if (!$formRO)
                                <p class="-mt-2 text-xs text-center text-muted">Menandatangani sebagai Operator = memvalidasi &amp; mengunci laporan operasi ini.</p>
                            @endif
                        </fieldset>

                        {{-- ══ DAFTAR LAPORAN TERSIMPAN (expandable) ══ --}}
                        <div class="pt-6 border-t border-hairline dark:border-gray-700">
                            <h3 class="text-base font-semibold text-body dark:text-gray-300 pb-2 border-b border-hairline-soft dark:border-gray-800 mb-3">
                                Daftar Laporan Operasi Tersimpan
                            </h3>
                            @if (count($laporanList ?? []))
                                <p class="mb-3 text-xs italic text-muted-soft">Klik baris untuk lihat detail lengkap</p>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-base border border-hairline rounded-lg dark:border-gray-700">
                                        <thead class="bg-surface-soft dark:bg-gray-800">
                                            <tr class="text-left text-sm font-semibold tracking-wide uppercase text-muted dark:text-gray-300">
                                                <th class="w-8 px-2 py-3 border-b"></th>
                                                <th class="px-4 py-3 border-b">Tgl Operasi</th>
                                                <th class="px-4 py-3 border-b">Tindakan</th>
                                                <th class="px-4 py-3 border-b">Operator (TTD)</th>
                                                <th class="px-4 py-3 text-center border-b">Status</th>
                                                <th class="px-4 py-3 text-center border-b">Aksi</th>
                                            </tr>
                                        </thead>
                                        @foreach (array_reverse($laporanList) as $entry)
                                            @php
                                                $isFinal = $this->entryIsFinal($entry);
                                                $rowKey = $entry['createdAt'] ?? '';
                                            @endphp
                                            <tbody x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }" class="border-b border-hairline dark:border-gray-700">
                                                <tr @click="open = !open"
                                                    class="cursor-pointer hover:bg-surface-soft dark:hover:bg-gray-800 {{ $editingKey && $editingKey === $rowKey ? 'bg-brand-lime/10 dark:bg-brand-lime/5' : '' }}">
                                                    <td class="px-2 py-3 text-center align-middle">
                                                        <svg class="w-4 h-4 mx-auto transition-transform text-muted" :class="{ 'rotate-90': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                        </svg>
                                                    </td>
                                                    <td class="px-4 py-3 font-semibold align-middle text-ink dark:text-gray-100">
                                                        {{ $entry['tanggalOperasi'] ?: ($rowKey ?: '-') }}
                                                    </td>
                                                    <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">
                                                        {{ $entry['jenisTindakan'] ? Str::limit($entry['jenisTindakan'], 45) : '-' }}
                                                    </td>
                                                    <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">
                                                        @if (!empty($entry['operatorTtd']))
                                                            <span class="font-medium text-ink dark:text-gray-200">{{ $entry['operatorTtd'] }}</span>
                                                        @else
                                                            <x-badge variant="danger">Belum TTD</x-badge>
                                                        @endif
                                                    </td>
                                                    <td class="px-4 py-3 text-center align-middle">
                                                        @if ($isFinal)
                                                            <x-badge variant="info">Terkunci</x-badge>
                                                        @else
                                                            <x-badge variant="warning">Draft</x-badge>
                                                        @endif
                                                    </td>
                                                    <td class="px-4 py-3 text-center align-middle" @click.stop>
                                                        <div class="flex items-center justify-center gap-2">
                                                            @if (!$isFinal && !$isFormLocked)
                                                                <x-primary-button type="button" wire:click="editEntry('{{ $rowKey }}')" wire:loading.attr="disabled" wire:target="editEntry('{{ $rowKey }}')" class="gap-1.5" title="Lanjutkan mengisi entri ini">
                                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                                    </svg>
                                                                    Lanjut Isi
                                                                </x-primary-button>
                                                            @endif
                                                            @if ($isFinal)
                                                                <x-secondary-button type="button" wire:click="viewEntry('{{ $rowKey }}')" wire:loading.attr="disabled" wire:target="viewEntry('{{ $rowKey }}')" class="gap-1.5" title="Lihat detail (read-only) di form atas">
                                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                                    </svg>
                                                                    Lihat
                                                                </x-secondary-button>
                                                            @endif
                                                            <x-secondary-button type="button" wire:click="cetak('{{ $rowKey }}')" wire:loading.attr="disabled" wire:target="cetak('{{ $rowKey }}')" class="gap-1.5" title="Cetak laporan operasi ini">
                                                                <span wire:loading.remove wire:target="cetak('{{ $rowKey }}')" class="flex items-center gap-1.5">
                                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                                    </svg>
                                                                    Cetak
                                                                </span>
                                                                <span wire:loading wire:target="cetak('{{ $rowKey }}')" class="flex items-center gap-1.5"><x-loading class="w-4 h-4" /> Mencetak...</span>
                                                            </x-secondary-button>
                                                            @if (!$isFormLocked)
                                                                <x-outline-button type="button" wire:click.prevent="hapus('{{ $rowKey }}')" wire:confirm="Yakin hapus laporan operasi ini?"
                                                                    wire:loading.attr="disabled"
                                                                    class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300"
                                                                    title="Hapus">
                                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                    </svg>
                                                                </x-outline-button>
                                                            @endif
                                                        </div>
                                                    </td>
                                                </tr>

                                                {{-- DETAIL (expand) --}}
                                                <tr x-show="open" x-cloak>
                                                    <td colspan="6" class="px-4 py-4 bg-surface-soft/60 dark:bg-gray-950/30">
                                                        <dl class="grid grid-cols-1 gap-x-8 gap-y-3 md:grid-cols-2">
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Tgl / Jam Operasi</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['tanggalOperasi'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Urgensi</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['urgensi'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Diagnosis Pra-Op</dt>
                                                                <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['diagnosisPraOp'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Diagnosis Pasca-Op</dt>
                                                                <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['diagnosisPascaOp'] ?: '-' }}</dd>
                                                            </div>
                                                            <div class="md:col-span-2">
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Jenis Tindakan</dt>
                                                                <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['jenisTindakan'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Nama Operator</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['namaOperator'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Asisten 1</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['asisten1'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Instrumentor</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['instrumentor'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Nama Anestesi</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['namaAnestesi'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Asisten Anestesi</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['asistenAnestesi'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Jenis Anestesi</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['jenisAnestesi'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Golongan Operasi</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['golonganOperasi'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Macam Operasi</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['macamOperasi'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Jam Mulai — Selesai</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['jamMulai'] ?: '-') }} — {{ ($entry['jamSelesai'] ?: '-') }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Lama Operasi</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['lamaOperasi'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Posisi Pasien</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['posisiPasien'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Jumlah Perdarahan (cc)</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['jumlahPerdarahanCc'] !== '' ? $entry['jumlahPerdarahanCc'] : '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Transfusi</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">
                                                                    @if (!empty($entry['transfusiDiberikan']))
                                                                        {{ ($entry['transfusiCc'] ?: '-') }} cc {{ $entry['transfusiJenis'] ? '— ' . $entry['transfusiJenis'] : '' }}
                                                                    @else
                                                                        Tidak
                                                                    @endif
                                                                </dd>
                                                            </div>
                                                            <div class="md:col-span-2">
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Komplikasi</dt>
                                                                <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['komplikasi'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Pemeriksaan PA</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['pemeriksaanPa'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Detail Spesimen PA</dt>
                                                                <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['spesimenDetail'] ?: '-' }}</dd>
                                                            </div>
                                                            <div class="md:col-span-2">
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Uraian Tindakan &amp; Temuan</dt>
                                                                <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['uraianLaporan'] ?: '-' }}</dd>
                                                            </div>
                                                            <div class="md:col-span-2">
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Instruksi Pasca-bedah</dt>
                                                                <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['instruksiPascaBedah'] ?: '-' }}</dd>
                                                            </div>
                                                            <div class="md:col-span-2">
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Implan (PAB 7.4)</dt>
                                                                <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">
                                                                    @if (!empty($entry['implanDipasang']))
                                                                        {{ $entry['jenisImplan'] ?: '-' }} — {{ $entry['merkPabrikan'] ?: '-' }} — S/N {{ $entry['nomorSerial'] ?: '-' }}
                                                                        @if (!empty($entry['ukuranImplan'])) — Ukuran {{ $entry['ukuranImplan'] }}@endif
                                                                        @if (!empty($entry['lokasiPemasangan'])) — Lokasi {{ $entry['lokasiPemasangan'] }}@endif
                                                                        @if (!empty($entry['sifatImplan'])) ({{ $entry['sifatImplan'] }})@endif
                                                                    @else
                                                                        Tidak ada implan
                                                                    @endif
                                                                </dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Operator (TTD)</dt>
                                                                <dd class="mt-0.5">
                                                                    @if (!empty($entry['operatorTtd']))
                                                                        <span class="text-ink dark:text-gray-200">{{ $entry['operatorTtd'] }}</span>
                                                                        <span class="text-sm text-muted-soft">— {{ $entry['operatorTtdDate'] ?? '-' }}</span>
                                                                    @else
                                                                        <x-badge variant="danger">Belum TTD</x-badge>
                                                                    @endif
                                                                </dd>
                                                            </div>
                                                        </dl>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        @endforeach
                                    </table>
                                </div>
                            @else
                                <p class="text-base text-muted dark:text-gray-400">Belum ada laporan operasi tersimpan.</p>
                            @endif
                        </div>

                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    @if ($viewOnly)
                        <p class="flex items-center gap-1.5 text-sm text-sky-600 dark:text-sky-400">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <span>Mode lihat — entri terkunci, tidak dapat diubah.</span>
                        </p>
                    @elseif (!$isFormLocked)
                        <p class="flex items-center gap-1.5 text-sm text-muted dark:text-gray-400">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Simpan draft dulu, lalu <strong>kunci</strong> lewat tombol <strong>TTD Operator &amp; Kunci</strong>.</span>
                        </p>
                    @else
                        <span></span>
                    @endif

                    <div class="flex flex-wrap items-center justify-end gap-3">
                        <x-secondary-button type="button" wire:click="closeModal">Tutup</x-secondary-button>

                        @if ($viewOnly)
                            <x-primary-button wire:click.prevent="cancelEdit" wire:target="cancelEdit"
                                wire:loading.attr="disabled" class="gap-1.5 min-w-[160px] justify-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                Selesai Melihat
                            </x-primary-button>
                        @elseif (!$isFormLocked)
                            @if ($editingKey)
                                <x-outline-button wire:click.prevent="cancelEdit" wire:target="cancelEdit"
                                    wire:loading.attr="disabled" class="gap-1.5"
                                    title="Kosongkan form untuk menambah laporan lain — entri yang sudah tersimpan tidak berubah">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Entri Baru
                                </x-outline-button>
                            @endif
                            <x-primary-button wire:click.prevent="saveDraft" wire:loading.attr="disabled"
                                wire:target="saveDraft" class="gap-2 min-w-[160px] justify-center">
                                <span wire:loading.remove wire:target="saveDraft" class="flex items-center gap-1.5">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 21v-8H7v8M7 3v5h8M5 3h11l4 4v12a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z" />
                                    </svg>
                                    {{ $editingKey ? 'Simpan Perubahan' : 'Simpan Draft' }}
                                </span>
                                <span wire:loading wire:target="saveDraft"><x-loading class="w-4 h-4" /> Menyimpan...</span>
                            </x-primary-button>
                        @endif
                    </div>
                </div>
            </div>

        </div>
    </x-modal>
</div>
