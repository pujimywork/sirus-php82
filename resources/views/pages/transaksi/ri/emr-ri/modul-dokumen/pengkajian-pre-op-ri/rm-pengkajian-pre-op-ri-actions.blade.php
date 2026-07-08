<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/pengkajian-pre-op-ri/rm-pengkajian-pre-op-ri-actions.blade.php
// Pengkajian Pre Operasi (RM 49) — persiapan pasien & serah-terima ruangan → OK.
// Pola: multi-entri (Draft + Lanjut Isi + TTD-Kunci + Lihat read-only + tabel expandable),
// disimpan ke datadaftarri_json (jsonKey = pengkajianPreOpRI). Kunci entri stabil = createdAt.
// TTD petugas = stempel nama user login (setTtd = FINALIZE/kunci); clearTtd batalkan TTD form.

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
    protected array $renderAreas = ['modal-pengkajian-pre-op-ri'];

    /** Key penyimpanan di datadaftarri_json */
    private string $jsonKey = 'pengkajianPreOpRI';

    // ── Form entri baru (Pengkajian Pre Operasi keperawatan — RM 49) ──
    public array $newForm = [
        'diagnosaPreOp' => '',
        'rencanaOperasi' => '',
        'dokterOperator' => '',
        'tanggalOperasi' => '',
        'urgensi' => '',
        // TTV / keadaan pra bedah
        'tb' => '',
        'bb' => '',
        'nadi' => '',
        'suhu' => '',
        'rr' => '',
        'hb' => '',
        'golDarah' => '',
        // Persiapan pasien
        'preMedikasi' => '',
        'cairan' => '',
        'obat' => '',
        'puasaMulaiJam' => '',
        'premedikasiJam' => '',
        'sudahDicukur' => false,
        'persiapanDarah' => false,
        'gigiPalsuDilepas' => false,
        'pengosonganKandungKemih' => false,
        'clysma' => false,
        'riwayatPenyakit' => false,
        'riwayatPenyakitKet' => '',
        'lainLain' => '',
        // Persiapan administrasi (sertakan ke OK)
        'adaRekamMedis' => false,
        'adaSuratIjin' => false,
        'adaLab' => false,
        'adaRadiologi' => false,
        'radiologiJenis' => '',
        'adaDiagnostik' => false,
        'diagnostikJenis' => '',
        // Serah terima
        'perawatOk' => '',
        // TTD perawat ruangan (auto)
        'ttd' => '',
        'ttdCode' => '',
        'ttdDate' => '',
    ];

    public array $preOpList = [];

    public array $urgensiOptions = ['Elektif', 'Cito'];

    // Kunci entri yang sedang diedit (createdAt = kunci stabil, di-set saat entri pertama dibuat).
    // null = sedang membuat entri baru.
    public ?string $editingKey = null;

    // true = entri terkunci sedang ditampilkan di form dalam mode read-only (lihat saja, tak bisa edit).
    public bool $viewOnly = false;

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(?string $riHdrNo = null, bool $disabled = false): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-pengkajian-pre-op-ri']);

        if ($this->riHdrNo) {
            $data = $this->findDataRI($this->riHdrNo);
            if ($data) {
                $this->dataDaftarRi = $data;
                $this->regNo = $data['regNo'] ?? null;
                $this->preOpList = $data[$this->jsonKey] ?? [];
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
        $this->preOpList = $this->dataDaftarRi[$this->jsonKey];
        $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $this->disabled;
        $this->incrementVersion('modal-pengkajian-pre-op-ri');

        $this->dispatch('open-modal', name: "rm-pengkajian-pre-op-ri-{$this->riHdrNo}");
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: "rm-pengkajian-pre-op-ri-{$this->riHdrNo}");
    }

    /* ===============================
     | VALIDATION
     =============================== */
    protected function rules(): array
    {
        return [
            'newForm.diagnosaPreOp' => 'required|string|max:500',
            'newForm.rencanaOperasi' => 'required|string|max:500',
            'newForm.dokterOperator' => 'required|string|max:200',
            'newForm.tanggalOperasi' => 'nullable|string|max:30',
            'newForm.urgensi' => 'required|string',
            'newForm.riwayatPenyakitKet' => 'nullable|string|max:300',
            'newForm.radiologiJenis' => 'nullable|string|max:200',
            'newForm.diagnostikJenis' => 'nullable|string|max:200',
            'newForm.lainLain' => 'nullable|string|max:1000',
            'newForm.perawatOk' => 'nullable|string|max:200',
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'max' => ':attribute maksimal :max karakter.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'newForm.diagnosaPreOp' => 'Diagnosa pre operasi',
            'newForm.rencanaOperasi' => 'Rencana operasi',
            'newForm.dokterOperator' => 'Dokter operator',
            'newForm.tanggalOperasi' => 'Tanggal operasi',
            'newForm.urgensi' => 'Urgensi operasi',
            'newForm.perawatOk' => 'Nama perawat OK',
        ];
    }

    /* ===============================
     | HELPER — status & bentuk entri
     =============================== */
    // Entri dianggap FINAL/terkunci bila flag finalized true; entri lama (tanpa flag) yang sudah
    // ada TTD (nama penanda) dianggap final (kompatibilitas data lama).
    public function entryIsFinal(array $e): bool
    {
        return array_key_exists('finalized', $e) ? (bool) $e['finalized'] : !empty($e['ttd']);
    }

    // Susun array entri dari state form. $key = createdAt (kunci stabil); $finalized = status kunci.
    private function buildEntry(string $key, bool $finalized): array
    {
        return [
            'diagnosaPreOp' => $this->newForm['diagnosaPreOp'] ?? '',
            'rencanaOperasi' => $this->newForm['rencanaOperasi'] ?? '',
            'dokterOperator' => $this->newForm['dokterOperator'] ?? '',
            'tanggalOperasi' => $this->newForm['tanggalOperasi'] ?? '',
            'urgensi' => $this->newForm['urgensi'] ?? '',
            'tb' => $this->newForm['tb'] ?? '',
            'bb' => $this->newForm['bb'] ?? '',
            'nadi' => $this->newForm['nadi'] ?? '',
            'suhu' => $this->newForm['suhu'] ?? '',
            'rr' => $this->newForm['rr'] ?? '',
            'hb' => $this->newForm['hb'] ?? '',
            'golDarah' => $this->newForm['golDarah'] ?? '',
            'preMedikasi' => $this->newForm['preMedikasi'] ?? '',
            'cairan' => $this->newForm['cairan'] ?? '',
            'obat' => $this->newForm['obat'] ?? '',
            'puasaMulaiJam' => $this->newForm['puasaMulaiJam'] ?? '',
            'premedikasiJam' => $this->newForm['premedikasiJam'] ?? '',
            'sudahDicukur' => (bool) ($this->newForm['sudahDicukur'] ?? false),
            'persiapanDarah' => (bool) ($this->newForm['persiapanDarah'] ?? false),
            'gigiPalsuDilepas' => (bool) ($this->newForm['gigiPalsuDilepas'] ?? false),
            'pengosonganKandungKemih' => (bool) ($this->newForm['pengosonganKandungKemih'] ?? false),
            'clysma' => (bool) ($this->newForm['clysma'] ?? false),
            'riwayatPenyakit' => (bool) ($this->newForm['riwayatPenyakit'] ?? false),
            'riwayatPenyakitKet' => $this->newForm['riwayatPenyakitKet'] ?? '',
            'lainLain' => $this->newForm['lainLain'] ?? '',
            'adaRekamMedis' => (bool) ($this->newForm['adaRekamMedis'] ?? false),
            'adaSuratIjin' => (bool) ($this->newForm['adaSuratIjin'] ?? false),
            'adaLab' => (bool) ($this->newForm['adaLab'] ?? false),
            'adaRadiologi' => (bool) ($this->newForm['adaRadiologi'] ?? false),
            'radiologiJenis' => $this->newForm['radiologiJenis'] ?? '',
            'adaDiagnostik' => (bool) ($this->newForm['adaDiagnostik'] ?? false),
            'diagnostikJenis' => $this->newForm['diagnostikJenis'] ?? '',
            'perawatOk' => $this->newForm['perawatOk'] ?? '',
            'ttd' => $this->newForm['ttd'] ?? '',
            'ttdCode' => $this->newForm['ttdCode'] ?? '',
            'ttdDate' => $this->newForm['ttdDate'] ?? '',
            'createdAt' => $key,
            'finalized' => $finalized,
        ];
    }

    // Cek: minimal inti pengkajian terisi (untuk draft).
    private function adaIntiPreOp(): bool
    {
        return collect(['diagnosaPreOp', 'rencanaOperasi', 'dokterOperator'])
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
            $this->preOpList = $fresh[$this->jsonKey];

            $this->appendAdminLogRI((int) $this->riHdrNo, $logVerb . ' Pengkajian Pre Operasi — ' . ($entry['rencanaOperasi'] ?: '-') . ' (' . $key . ')', 'MR');
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
        if (!$this->adaIntiPreOp()) {
            $this->dispatch('toast', type: 'error', message: 'Isi minimal salah satu: Diagnosa, Rencana Operasi, atau Dokter Operator.');
            return;
        }

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, false, 'Simpan draft');
            $this->editingKey = $key; // lanjut edit entri yang sama, tidak buat duplikat
            $this->incrementVersion('modal-pengkajian-pre-op-ri');
            $this->dispatch('toast', type: 'success', message: 'Draft tersimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan draft: ' . $e->getMessage());
        }
    }

    /* ===============================
     | TTD PETUGAS = FINALIZE (kunci entri)
     | Stempel nama user login + tgl/jam → kunci entri.
     =============================== */
    public function setTtd(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }

        // Validasi penuh sebelum kunci (field wajib RM 49).
        $this->validateWithToast();

        // Stempel TTD petugas = user login.
        $this->newForm['ttd'] = auth()->user()->myuser_name ?? '';
        $this->newForm['ttdCode'] = auth()->user()->myuser_code ?? '';
        $this->newForm['ttdDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, true, 'Kunci (TTD)');
            $this->resetNewForm();
            $this->editingKey = null;
            $this->viewOnly = false;
            $this->incrementVersion('modal-pengkajian-pre-op-ri');
            $this->dispatch('toast', type: 'success', message: 'Pengkajian ditandatangani & terkunci.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal mengunci: ' . $e->getMessage());
        }
    }

    /** Batalkan TTD pada form (saat draft/edit, sebelum finalize benar-benar tersimpan). */
    public function clearTtd(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->newForm['ttd'] = '';
        $this->newForm['ttdCode'] = '';
        $this->newForm['ttdDate'] = '';
    }

    /* ===============================
     | EDIT / LIHAT / BATAL entri
     =============================== */
    // Muat 1 entri ke form atas (dipakai edit draft & lihat entri terkunci).
    private function hydrateFormFromEntry(array $entry, string $key): void
    {
        foreach ($this->newForm as $k => $v) {
            $this->newForm[$k] = $entry[$k] ?? (is_bool($v) ? false : (is_array($v) ? [] : ''));
        }
        $this->editingKey = $key;
        $this->resetValidation();
        $this->incrementVersion('modal-pengkajian-pre-op-ri');
    }

    public function editEntry(string $key): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        $entry = collect($this->preOpList)->firstWhere('createdAt', $key);
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
        $entry = collect($this->preOpList)->firstWhere('createdAt', $key);
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
        $this->incrementVersion('modal-pengkajian-pre-op-ri');
    }

    /* ===============================
     | CETAK (inline stream PDF, by createdAt)
     =============================== */
    public function cetak(string $createdAt)
    {
        $entry = collect($this->preOpList)->firstWhere('createdAt', $createdAt);
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

            $pdf = Pdf::loadView('pages.components.modul-dokumen.r-i.pengkajian-pre-op-ri.cetak-pengkajian-pre-op-ri-print', ['data' => $data])->setPaper('A4');

            $this->dispatch('toast', type: 'success', message: 'Berhasil mencetak pengkajian pre operasi.');
            return response()->streamDownload(fn() => print $pdf->output(), 'pengkajian-pre-op-ri-' . ($pasien['regNo'] ?? $this->riHdrNo) . '.pdf');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cetak: ' . $e->getMessage());
        }
    }

    /* ===============================
     | HAPUS entri (final atau draft, by createdAt)
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
                $fresh[$this->jsonKey] = collect($fresh[$this->jsonKey] ?? [])
                    ->reject(fn($item) => ($item['createdAt'] ?? '') === $createdAt)
                    ->values()
                    ->toArray();

                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
                $this->preOpList = $fresh[$this->jsonKey];

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Pengkajian Pre Operasi — ' . $createdAt, 'MR');
            });

            // Jika entri yang dihapus sedang di form, kosongkan form.
            if ($this->editingKey === $createdAt) {
                $this->cancelEdit();
            }

            $this->incrementVersion('modal-pengkajian-pre-op-ri');
            $this->dispatch('toast', type: 'success', message: 'Pengkajian pre operasi berhasil dihapus.');
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
            'diagnosaPreOp' => '',
            'rencanaOperasi' => '',
            'dokterOperator' => '',
            'tanggalOperasi' => '',
            'urgensi' => '',
            'tb' => '',
            'bb' => '',
            'nadi' => '',
            'suhu' => '',
            'rr' => '',
            'hb' => '',
            'golDarah' => '',
            'preMedikasi' => '',
            'cairan' => '',
            'obat' => '',
            'puasaMulaiJam' => '',
            'premedikasiJam' => '',
            'sudahDicukur' => false,
            'persiapanDarah' => false,
            'gigiPalsuDilepas' => false,
            'pengosonganKandungKemih' => false,
            'clysma' => false,
            'riwayatPenyakit' => false,
            'riwayatPenyakitKet' => '',
            'lainLain' => '',
            'adaRekamMedis' => false,
            'adaSuratIjin' => false,
            'adaLab' => false,
            'adaRadiologi' => false,
            'radiologiJenis' => '',
            'adaDiagnostik' => false,
            'diagnostikJenis' => '',
            'perawatOk' => '',
            'ttd' => '',
            'ttdCode' => '',
            'ttdDate' => '',
        ];
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->dataDaftarRi = [];
        $this->preOpList = [];
        $this->resetNewForm();
        $this->editingKey = null;
        $this->viewOnly = false;
    }
};
?>

<div>
    {{-- ══ SUMMARY CARD (inline) ══ --}}
    @php $poCount = count($preOpList ?? []); @endphp

    <div class="p-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex-1 space-y-3">
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">Pengkajian Pre Operasi</h3>
                    @if ($poCount > 0)
                        <x-badge variant="success">{{ $poCount }} pengkajian</x-badge>
                    @else
                        <x-badge variant="warning">Belum ada</x-badge>
                    @endif
                </div>
                <p class="text-base text-muted dark:text-gray-400">
                    Persiapan pasien & serah-terima ruangan → OK (RM 49): keadaan pra bedah, persiapan pasien
                    (puasa/cukur/premedikasi), kelengkapan administrasi yang disertakan ke kamar operasi.
                    Tiap entri = 1 pengkajian; simpan draft dulu lalu kunci lewat TTD.
                </p>
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

        @if ($poCount > 0)
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm border border-hairline rounded-lg dark:border-gray-700">
                    <thead class="bg-surface-soft dark:bg-gray-800">
                        <tr class="text-left text-muted dark:text-gray-300">
                            <th class="px-3 py-2 border-b">Tanggal</th>
                            <th class="px-3 py-2 border-b">Rencana Operasi</th>
                            <th class="px-3 py-2 border-b">Petugas (TTD)</th>
                            <th class="px-3 py-2 text-center border-b">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (array_reverse($preOpList) as $po)
                            <tr class="border-b border-hairline dark:border-gray-700">
                                <td class="px-3 py-2 font-medium text-ink dark:text-gray-200">{{ $po['createdAt'] ?? '-' }}</td>
                                <td class="px-3 py-2 text-muted dark:text-gray-400">{{ $po['rencanaOperasi'] ? \Illuminate\Support\Str::limit($po['rencanaOperasi'], 45) : '-' }}</td>
                                <td class="px-3 py-2 text-muted dark:text-gray-400">
                                    @if (!empty($po['ttd'])){{ $po['ttd'] }}@else<x-badge variant="danger">Belum TTD</x-badge>@endif
                                </td>
                                <td class="px-3 py-2 text-center">
                                    @if ($this->entryIsFinal($po))
                                        <x-badge variant="info">Terkunci</x-badge>
                                    @else
                                        <x-badge variant="warning">Draft</x-badge>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- ══ MODAL FORM ══ --}}
    <x-modal name="rm-pengkajian-pre-op-ri-{{ $riHdrNo ?? 'init' }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal-pengkajian-pre-op-ri', [$riHdrNo ?? 'new']) }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-teal-500/10">
                                <svg class="w-6 h-6 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="font-semibold text-2xl text-ink dark:text-gray-100">Pengkajian Pre Operasi
                                </h2>
                                <p class="mt-0.5 text-base text-muted dark:text-gray-400">
                                    RM 49 — persiapan pasien & serah-terima ruangan → kamar operasi
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 mt-3">
                            <x-badge variant="brand">Rawat Inap</x-badge>
                            @if (count($preOpList) > 0)
                                <x-badge variant="info">{{ count($preOpList) }} tersimpan</x-badge>
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

                    <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                        wire:key="po-ri-display-pasien-{{ $riHdrNo ?? 'init' }}" />

                    @php $formRO = $isFormLocked || $viewOnly; @endphp

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

                        @if ($viewOnly)
                            <div class="flex items-center gap-2 px-4 py-2.5 text-base font-medium text-sky-700 bg-sky-50 border border-sky-200 rounded-xl dark:bg-sky-900/20 dark:border-sky-600 dark:text-sky-300">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                Menampilkan entri terkunci <strong>{{ $editingKey }}</strong> (hanya lihat) — klik <strong>Selesai Melihat</strong> untuk kembali ke form entri baru.
                            </div>
                        @elseif ($editingKey && !$isFormLocked)
                            <div class="flex items-center gap-2 px-4 py-2.5 text-base font-medium text-brand-green bg-brand-lime/10 border border-brand-lime/40 rounded-xl dark:text-brand-lime dark:bg-brand-lime/5">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                Sedang melanjutkan entri <strong>{{ $editingKey }}</strong> — <strong>Simpan Perubahan</strong> menyimpan ke entri ini; klik <strong>Entri Baru</strong> untuk menambah pengkajian lain.
                            </div>
                        @endif

                        {{-- ── FORM ENTRI ── --}}
                        <fieldset @disabled($formRO) class="space-y-6">

                            {{-- ══ DATA OPERASI ══ --}}
                            <section class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <x-input-label value="Diagnosa Pre Operasi *" class="mb-1" />
                                    <x-textarea wire:model.live="newForm.diagnosaPreOp" :error="$errors->has('newForm.diagnosaPreOp')" rows="2"
                                        class="w-full" />
                                    <x-input-error :messages="$errors->get('newForm.diagnosaPreOp')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Rencana Operasi *" class="mb-1" />
                                    <x-textarea wire:model.live="newForm.rencanaOperasi" :error="$errors->has('newForm.rencanaOperasi')" rows="2"
                                        class="w-full" />
                                    <x-input-error :messages="$errors->get('newForm.rencanaOperasi')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Dokter Operator *" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.dokterOperator" :error="$errors->has('newForm.dokterOperator')"
                                        class="w-full" />
                                    <x-input-error :messages="$errors->get('newForm.dokterOperator')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Tanggal / Jam Operasi" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.tanggalOperasi" :error="$errors->has('newForm.tanggalOperasi')" placeholder="dd/mm/yyyy HH:mm"
                                        class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Urgensi Operasi *" class="mb-1" />
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($urgensiOptions as $opt)
                                            <x-radio-button :label="$opt" :value="$opt" name="urgensiPreOp"
                                                wire:model.live="newForm.urgensi" />
                                        @endforeach
                                    </div>
                                    <x-input-error :messages="$errors->get('newForm.urgensi')" class="mt-1" />
                                </div>
                            </section>

                            {{-- ══ KEADAAN PRA BEDAH (TTV) ══ --}}
                            <section class="pt-6 border-t border-hairline dark:border-gray-700">
                                <h3 class="mb-3 text-base font-semibold text-ink dark:text-gray-200">Keadaan Pra Bedah</h3>
                                <div class="grid grid-cols-2 gap-4 md:grid-cols-4 lg:grid-cols-7">
                                    <div>
                                        <x-input-label value="TB (cm)" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.tb" :error="$errors->has('newForm.tb')" class="w-full" />
                                    </div>
                                    <div>
                                        <x-input-label value="BB (kg)" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.bb" :error="$errors->has('newForm.bb')" class="w-full" />
                                    </div>
                                    <div>
                                        <x-input-label value="Nadi" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.nadi" :error="$errors->has('newForm.nadi')" class="w-full" />
                                    </div>
                                    <div>
                                        <x-input-label value="Suhu (°C)" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.suhu" :error="$errors->has('newForm.suhu')" class="w-full" />
                                    </div>
                                    <div>
                                        <x-input-label value="RR" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.rr" :error="$errors->has('newForm.rr')" class="w-full" />
                                    </div>
                                    <div>
                                        <x-input-label value="Hb" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.hb" :error="$errors->has('newForm.hb')" class="w-full" />
                                    </div>
                                    <div>
                                        <x-input-label value="Gol. Darah" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.golDarah" :error="$errors->has('newForm.golDarah')"
                                            class="w-full" />
                                    </div>
                                </div>
                            </section>

                            {{-- ══ PERSIAPAN PASIEN ══ --}}
                            <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                                <h3 class="text-base font-semibold text-ink dark:text-gray-200">Persiapan Pasien</h3>
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                    <div>
                                        <x-input-label value="Pre Medikasi" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.preMedikasi" :error="$errors->has('newForm.preMedikasi')"
                                            class="w-full" />
                                    </div>
                                    <div>
                                        <x-input-label value="Cairan" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.cairan" :error="$errors->has('newForm.cairan')"
                                            class="w-full" />
                                    </div>
                                    <div>
                                        <x-input-label value="Obat" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.obat" :error="$errors->has('newForm.obat')"
                                            class="w-full" />
                                    </div>
                                    <div>
                                        <x-input-label value="Puasa Mulai Jam" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.puasaMulaiJam" :error="$errors->has('newForm.puasaMulaiJam')" placeholder="HH:mm"
                                            class="w-full" />
                                    </div>
                                    <div>
                                        <x-input-label value="Premedikasi Jam" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.premedikasiJam" :error="$errors->has('newForm.premedikasiJam')" placeholder="HH:mm"
                                            class="w-full" />
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <x-toggle wire:model.live="newForm.sudahDicukur" :trueValue="true" :falseValue="false"
                                        label="Sudah dicukur / dibersihkan daerah operasi" />
                                    <x-toggle wire:model.live="newForm.persiapanDarah" :trueValue="true" :falseValue="false"
                                        label="Persiapan darah" />
                                    <x-toggle wire:model.live="newForm.gigiPalsuDilepas" :trueValue="true" :falseValue="false"
                                        label="Gigi palsu / kontak lensa / perhiasan dilepas" />
                                    <x-toggle wire:model.live="newForm.pengosonganKandungKemih" :trueValue="true"
                                        :falseValue="false" label="Pengosongan kandung kemih" />
                                    <x-toggle wire:model.live="newForm.clysma" :trueValue="true" :falseValue="false"
                                        label="Clysma / glyserin" />
                                    <x-toggle wire:model.live="newForm.riwayatPenyakit" :trueValue="true" :falseValue="false"
                                        label="Ada riwayat penyakit" />
                                </div>
                                @if ($newForm['riwayatPenyakit'])
                                    <div>
                                        <x-input-label value="Keterangan Riwayat Penyakit" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.riwayatPenyakitKet" :error="$errors->has('newForm.riwayatPenyakitKet')"
                                            class="w-full" />
                                    </div>
                                @endif
                                <div>
                                    <x-input-label value="Lain-lain" class="mb-1" />
                                    <x-textarea wire:model.live="newForm.lainLain" :error="$errors->has('newForm.lainLain')" rows="2"
                                        class="w-full" />
                                </div>
                            </section>

                            {{-- ══ PERSIAPAN ADMINISTRASI (KE OK) ══ --}}
                            <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                                <h3 class="text-base font-semibold text-ink dark:text-gray-200">Persiapan Administrasi
                                    (sertakan bersama pasien ke OK)</h3>
                                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <x-toggle wire:model.live="newForm.adaRekamMedis" :trueValue="true" :falseValue="false"
                                        label="Rekam Medis" />
                                    <x-toggle wire:model.live="newForm.adaSuratIjin" :trueValue="true" :falseValue="false"
                                        label="Surat Ijin Tindakan Operasi" />
                                    <x-toggle wire:model.live="newForm.adaLab" :trueValue="true" :falseValue="false"
                                        label="Hasil Pemeriksaan Laboratorium" />
                                    <x-toggle wire:model.live="newForm.adaRadiologi" :trueValue="true" :falseValue="false"
                                        label="Hasil Pemeriksaan Radiologi" />
                                </div>
                                @if ($newForm['adaRadiologi'])
                                    <div>
                                        <x-input-label value="Jenis Radiologi" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.radiologiJenis" :error="$errors->has('newForm.radiologiJenis')"
                                            placeholder="cth: Thorak Foto / CT-Scan / MRI"
                                            class="w-full" />
                                    </div>
                                @endif
                                <x-toggle wire:model.live="newForm.adaDiagnostik" :trueValue="true" :falseValue="false"
                                    label="Hasil Pemeriksaan Diagnostik" />
                                @if ($newForm['adaDiagnostik'])
                                    <div>
                                        <x-input-label value="Jenis Diagnostik" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.diagnostikJenis" :error="$errors->has('newForm.diagnostikJenis')"
                                            placeholder="cth: USG / Colonoscopi / Gastroscopi"
                                            class="w-full" />
                                    </div>
                                @endif
                            </section>

                            {{-- ══ SERAH TERIMA + TTD ══ --}}
                            <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                                <div class="max-w-md">
                                    <x-input-label value="Perjanjian dengan Perawat OK (nama/kru)" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.perawatOk" :error="$errors->has('newForm.perawatOk')"
                                        class="w-full" />
                                </div>

                                <x-signature.ttd-petugas :ttd="$newForm['ttd']" :date="$newForm['ttdDate'] ?? ''"
                                    :code="$newForm['ttdCode'] ?? ''" :locked="$formRO" sign="setTtd" clear="clearTtd"
                                    title="Tanda Tangan Perawat Ruangan"
                                    nameLabel="Petugas (Perawat Ruangan)" dateLabel="Waktu TTD"
                                    signLabel="TTD Petugas &amp; Kunci" clearLabel="Batal TTD" />
                                @if (!$formRO)
                                    <p class="-mt-2 text-xs text-center text-muted">Menandatangani = mengunci pengkajian ini.</p>
                                @endif
                            </section>
                        </fieldset>

                        {{-- ══ DAFTAR PENGKAJIAN TERSIMPAN (expandable) ══ --}}
                        @if (count($preOpList ?? []))
                            <div class="mt-6">
                                <h3
                                    class="text-base font-semibold text-body dark:text-gray-300 pb-2 border-b border-hairline-soft dark:border-gray-800 mb-3">
                                    Daftar Pengkajian Tersimpan
                                </h3>
                                <p class="mb-3 text-xs italic text-muted-soft">Klik baris untuk lihat detail lengkap</p>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-base border border-hairline rounded-lg dark:border-gray-700">
                                        <thead class="bg-surface-soft dark:bg-gray-800">
                                            <tr class="text-left text-sm font-semibold tracking-wide uppercase text-muted dark:text-gray-300">
                                                <th class="w-8 px-2 py-3 border-b"></th>
                                                <th class="px-4 py-3 border-b">Tanggal</th>
                                                <th class="px-4 py-3 border-b">Rencana Operasi</th>
                                                <th class="px-4 py-3 border-b">Petugas (TTD)</th>
                                                <th class="px-4 py-3 text-center border-b">Status</th>
                                                <th class="px-4 py-3 text-center border-b">Aksi</th>
                                            </tr>
                                        </thead>
                                        @foreach (array_reverse($preOpList) as $entry)
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
                                                        {{ $entry['createdAt'] ?: '-' }}
                                                    </td>
                                                    <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">
                                                        {{ $entry['rencanaOperasi'] ? Str::limit($entry['rencanaOperasi'], 45) : '-' }}
                                                    </td>
                                                    <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">
                                                        @if (!empty($entry['ttd']))
                                                            <span class="font-medium text-ink dark:text-gray-200">{{ $entry['ttd'] }}</span>
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
                                                            <x-secondary-button type="button" wire:click="cetak('{{ $rowKey }}')"
                                                                wire:loading.attr="disabled" wire:target="cetak('{{ $rowKey }}')" class="gap-1.5" title="Cetak">
                                                                <span wire:loading.remove wire:target="cetak('{{ $rowKey }}')" class="flex items-center gap-1.5">
                                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                                    </svg>
                                                                    Cetak
                                                                </span>
                                                                <span wire:loading wire:target="cetak('{{ $rowKey }}')" class="flex items-center gap-1.5"><x-loading class="w-4 h-4" /> Mencetak...</span>
                                                            </x-secondary-button>
                                                            @if (!$isFormLocked)
                                                                <x-outline-button type="button" wire:click.prevent="hapus('{{ $rowKey }}')" wire:confirm="Yakin hapus pengkajian ini?"
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
                                                            <div class="md:col-span-2">
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Diagnosa Pre Operasi</dt>
                                                                <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['diagnosaPreOp'] ?: '-' }}</dd>
                                                            </div>
                                                            <div class="md:col-span-2">
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Rencana Operasi</dt>
                                                                <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['rencanaOperasi'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Dokter Operator</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['dokterOperator'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Tanggal / Jam Operasi</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['tanggalOperasi'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Urgensi</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['urgensi'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">TB / BB</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['tb'] ?: '-' }} cm / {{ $entry['bb'] ?: '-' }} kg</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Nadi</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['nadi'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Suhu (°C)</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['suhu'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">RR</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['rr'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Hb</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['hb'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Gol. Darah</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['golDarah'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Pre Medikasi</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['preMedikasi'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Cairan</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['cairan'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Obat</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['obat'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Puasa Mulai Jam</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['puasaMulaiJam'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Premedikasi Jam</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['premedikasiJam'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Sudah Dicukur</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ !empty($entry['sudahDicukur']) ? 'Ya' : 'Tidak' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Persiapan Darah</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ !empty($entry['persiapanDarah']) ? 'Ya' : 'Tidak' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Gigi Palsu / Perhiasan Dilepas</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ !empty($entry['gigiPalsuDilepas']) ? 'Ya' : 'Tidak' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Pengosongan Kandung Kemih</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ !empty($entry['pengosonganKandungKemih']) ? 'Ya' : 'Tidak' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Clysma / Glyserin</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ !empty($entry['clysma']) ? 'Ya' : 'Tidak' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Riwayat Penyakit</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ !empty($entry['riwayatPenyakit']) ? ('Ya' . (!empty($entry['riwayatPenyakitKet']) ? ' — ' . $entry['riwayatPenyakitKet'] : '')) : 'Tidak' }}</dd>
                                                            </div>
                                                            <div class="md:col-span-2">
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Lain-lain</dt>
                                                                <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['lainLain'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Rekam Medis</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ !empty($entry['adaRekamMedis']) ? 'Ya' : 'Tidak' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Surat Ijin Tindakan</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ !empty($entry['adaSuratIjin']) ? 'Ya' : 'Tidak' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Hasil Laboratorium</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ !empty($entry['adaLab']) ? 'Ya' : 'Tidak' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Hasil Radiologi</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ !empty($entry['adaRadiologi']) ? ('Ya' . (!empty($entry['radiologiJenis']) ? ' — ' . $entry['radiologiJenis'] : '')) : 'Tidak' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Hasil Diagnostik</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ !empty($entry['adaDiagnostik']) ? ('Ya' . (!empty($entry['diagnostikJenis']) ? ' — ' . $entry['diagnostikJenis'] : '')) : 'Tidak' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Perawat OK</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['perawatOk'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Petugas (TTD)</dt>
                                                                <dd class="mt-0.5">
                                                                    @if (!empty($entry['ttd']))
                                                                        <span class="text-ink dark:text-gray-200">{{ $entry['ttd'] }}</span>
                                                                        <span class="text-sm text-muted-soft">— {{ $entry['ttdDate'] ?? '-' }}</span>
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
                            </div>
                        @endif

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
                            <span>Simpan draft dulu, lalu <strong>kunci</strong> lewat tombol <strong>TTD Petugas &amp; Kunci</strong>.</span>
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
                                    title="Kosongkan form untuk menambah pengkajian lain — entri yang sudah tersimpan tidak berubah">
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
