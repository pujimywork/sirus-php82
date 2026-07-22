<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/pra-anestesi-ri/rm-pra-anestesi-ri-actions.blade.php
// Pengkajian Pra Anestesi & Pra Sedasi (PAB 4 / RM 50) — dokter anestesi.
// Pola: multi-entri (Draft + Lanjut Isi + TTD-Kunci + Lihat read-only + tabel expandable),
// disimpan ke datadaftarri_json (key: praAnestesiRI). Kunci entri stabil = createdAt.
// TTD PETUGAS (dokter anestesi) = stempel nama user login (setTtd = FINALIZE/kunci).
// TTD GAMBAR pasien (signature-pad) = FIELD ENTRI biasa (diisi saat draft/edit), BUKAN pemicu kunci.

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

    // ── Form entri (Pengkajian Pra Anestesi & Pra Sedasi — PAB 4 / RM 50) ──
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
        // TTD petugas (dokter anestesi) — stempel nama user login (FINALIZE)
        'ttd' => '',
        'ttdCode' => '',
        'ttdDate' => '',
    ];

    // TTD GAMBAR pasien/keluarga (signature-pad) — field entri biasa, BUKAN pemicu kunci.
    public string $signaturePasien = '';

    public array $praList = [];

    // Kunci entri yang sedang diedit (createdAt = kunci stabil). null = membuat entri baru.
    public ?string $editingKey = null;
    // true = entri terkunci sedang ditampilkan di form dalam mode read-only.
    public bool $viewOnly = false;

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
     | OPEN / CLOSE MODAL
     =============================== */
    public function openModal(): void
    {
        if (!$this->riHdrNo || $this->disabled) {
            return;
        }

        $this->resetNewForm();
        $this->signaturePasien = '';
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
     | SET TANGGAL/JAM SEKARANG
     =============================== */
    public function setTanggalSekarang(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->newForm['tanggal'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /* ===============================
     | HELPER — status & bentuk entri
     =============================== */
    // Entri dianggap FINAL/terkunci bila flag finalized true; entri lama (tanpa flag) yang sudah
    // ada TTD petugas dianggap final (kompatibilitas data lama).
    public function entryIsFinal(array $e): bool
    {
        return array_key_exists('finalized', $e) ? (bool) $e['finalized'] : !empty($e['ttd']);
    }

    // Susun array entri dari state form. $key = createdAt (kunci stabil); $finalized = status kunci.
    // Sertakan TTD GAMBAR pasien dari propertinya ($signaturePasien).
    private function buildEntry(string $key, bool $finalized): array
    {
        $entry = $this->newForm;
        $entry['signaturePasien'] = $this->signaturePasien;
        $entry['createdAt'] = $key;
        $entry['finalized'] = $finalized;
        return $entry;
    }

    // Cek: minimal salah satu isi inti terisi (untuk draft & sebelum kunci).
    private function adaIsiInti(): bool
    {
        return collect(['diagnosisPraAnestesi', 'rencanaTindakan', 'jenisAnestesi', 'psAsa'])
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
            if (!isset($fresh['praAnestesiRI']) || !is_array($fresh['praAnestesiRI'])) {
                $fresh['praAnestesiRI'] = [];
            }

            $list = $fresh['praAnestesiRI'];
            $idx = collect($list)->search(fn($it) => ($it['createdAt'] ?? '') === $key);
            if ($idx === false) {
                $list[] = $entry;
            } else {
                if ($this->entryIsFinal($list[$idx])) {
                    throw new \RuntimeException('Entri sudah terkunci, tidak dapat diubah.');
                }
                $list[$idx] = $entry;
            }
            $fresh['praAnestesiRI'] = array_values($list);

            $this->updateJsonRI((int) $this->riHdrNo, $fresh);
            $this->dataDaftarRi = $fresh;
            $this->praList = $fresh['praAnestesiRI'];

            $this->appendAdminLogRI((int) $this->riHdrNo, $logVerb . ' Pengkajian Pra Anestesi — ' . ($entry['psAsa'] ?: '-') . ' (' . $key . ')', 'MR');
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
        if (!$this->adaIsiInti()) {
            $this->dispatch('toast', type: 'error', message: 'Isi minimal salah satu: Diagnosis, Rencana Tindakan, Jenis Anestesi, atau PS ASA.');
            return;
        }

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, false, 'Simpan draft');
            $this->editingKey = $key; // lanjut edit entri yang sama, tidak buat duplikat
            $this->incrementVersion('modal-pra-anestesi-ri');
            $this->dispatch('toast', type: 'success', message: 'Draft tersimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan draft: ' . $e->getMessage());
        }
    }

    /* ===============================
     | TTD PETUGAS = FINALIZE (kunci entri)
     | Stempel nama user login (dokter anestesi) + tgl/jam → kunci entri.
     =============================== */
    public function setTtd(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        if (!$this->adaIsiInti()) {
            $this->dispatch('toast', type: 'error', message: 'Isi minimal salah satu: Diagnosis, Rencana Tindakan, Jenis Anestesi, atau PS ASA sebelum TTD.');
            return;
        }

        // Enforce aturan lengkap sebelum mengunci.
        $this->validateWithToast();

        // Stempel TTD petugas = user login (dokter anestesi).
        $this->newForm['ttd'] = auth()->user()->myuser_name ?? '';
        $this->newForm['ttdCode'] = auth()->user()->myuser_code ?? '';
        $this->newForm['ttdDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, true, 'Kunci (TTD)');
            $this->resetNewForm();
            $this->signaturePasien = '';
            $this->editingKey = null;
            $this->viewOnly = false;
            $this->incrementVersion('modal-pra-anestesi-ri');
            $this->dispatch('toast', type: 'success', message: 'Pengkajian ditandatangani & terkunci.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal mengunci: ' . $e->getMessage());
        }
    }

    /** Batalkan TTD petugas pada form (saat draft/edit, sebelum finalize tersimpan). */
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
     | TTD GAMBAR PASIEN (field entri biasa — BUKAN pemicu kunci)
     =============================== */
    public function setSignaturePasien(string $dataUrl): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->signaturePasien = $dataUrl;
        $this->incrementVersion('modal-pra-anestesi-ri');
    }

    public function clearSignaturePasien(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->signaturePasien = '';
        $this->incrementVersion('modal-pra-anestesi-ri');
    }

    /* ===============================
     | EDIT / LIHAT / BATAL entri
     =============================== */
    // Muat 1 entri ke form atas (dipakai edit draft & lihat entri terkunci). Termasuk TTD gambar pasien.
    private function hydrateFormFromEntry(array $entry, string $key): void
    {
        foreach ($this->newForm as $k => $v) {
            $this->newForm[$k] = $entry[$k] ?? (is_array($v) ? [] : ($v === false ? false : ''));
        }
        $this->signaturePasien = $entry['signaturePasien'] ?? '';
        $this->editingKey = $key;
        $this->resetValidation();
        $this->incrementVersion('modal-pra-anestesi-ri');
    }

    public function editEntry(string $key): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        $entry = collect($this->praList)->firstWhere('createdAt', $key);
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
        $entry = collect($this->praList)->firstWhere('createdAt', $key);
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
        $this->signaturePasien = '';
        $this->editingKey = null;
        $this->viewOnly = false;
        $this->resetValidation();
        $this->incrementVersion('modal-pra-anestesi-ri');
    }

    /* ===============================
     | CETAK (per-entri)
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
     | HAPUS entri (final atau draft)
     =============================== */
    public function hapus(string $createdAt): void
    {
        if (!auth()->user()?->can('dokumen.hapus')) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berwenang menghapus entri.');
            return;
        }
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

            // Jika entri yang dihapus sedang di form, kosongkan form.
            if ($this->editingKey === $createdAt) {
                $this->cancelEdit();
            }

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

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->dataDaftarRi = [];
        $this->praList = [];
        $this->resetNewForm();
        $this->signaturePasien = '';
        $this->editingKey = null;
        $this->viewOnly = false;
    }
};
?>

<div>
    {{-- ══ SUMMARY CARD ══ --}}
    @php $entriCount = count($praList ?? []); @endphp

    <div class="p-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex-1 space-y-2">
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">Pengkajian Pra Anestesi & Pra Sedasi</h3>
                    @if ($entriCount > 0)
                        <x-badge variant="success">{{ $entriCount }} pengkajian</x-badge>
                    @else
                        <x-badge variant="warning">Belum ada</x-badge>
                    @endif
                </div>
                <p class="text-sm text-muted dark:text-gray-400">
                    Asesmen pra anestesi (PAB 4 / RM 50) oleh dokter anestesi: anamnese, jalan nafas (Mallampati),
                    status fisik ASA, rencana teknik anestesi & analgesia pasca-op. Tiap entri = 1 pengkajian.
                </p>
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

        @if ($entriCount > 0)
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm border border-hairline rounded-lg dark:border-gray-700">
                    <thead class="bg-surface-soft dark:bg-gray-800">
                        <tr class="text-left text-muted dark:text-gray-300">
                            <th class="px-3 py-2 border-b">Tanggal</th>
                            <th class="px-3 py-2 border-b">ASA</th>
                            <th class="px-3 py-2 border-b">Petugas (TTD)</th>
                            <th class="px-3 py-2 text-center border-b">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (array_reverse($praList) as $e)
                            <tr class="border-b border-hairline dark:border-gray-700">
                                <td class="px-3 py-2 font-medium text-ink dark:text-gray-200">{{ $e['tanggal'] ?: ($e['createdAt'] ?? '-') }}</td>
                                <td class="px-3 py-2 text-muted dark:text-gray-400">{{ $e['psAsa'] ?: '-' }}</td>
                                <td class="px-3 py-2 text-muted dark:text-gray-400">
                                    @if (!empty($e['ttd'])){{ $e['ttd'] }}@else<x-badge variant="danger">Belum TTD</x-badge>@endif
                                </td>
                                <td class="px-3 py-2 text-center">
                                    @if ($this->entryIsFinal($e))
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
            <div class="flex-1 px-4 py-4 overflow-y-auto bg-surface-soft/70 dark:bg-gray-950/20">
                <div class="max-w-full mx-auto space-y-4">

                    <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                        wire:key="pra-ri-display-pasien-{{ $riHdrNo ?? 'init' }}" />

                    @php $formRO = $isFormLocked || $viewOnly; @endphp

                    @if ($isFormLocked)
                        <div class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium border rounded-lg text-amber-800 bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-300">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            Mode tampilan saja (read-only) — pasien sudah pulang / form terkunci.
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
                            Sedang melanjutkan entri <strong>{{ $editingKey }}</strong> — <strong>Simpan Perubahan</strong> menyimpan ke entri ini; klik <strong>Entri Baru</strong> untuk menambah pengkajian lain.
                        </div>
                    @endif

                    <div class="p-6 space-y-6 bg-canvas border border-hairline shadow-sm sm:p-8 rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                        {{-- ── FORM ENTRI (1 pengkajian) ── --}}
                        <fieldset @disabled($formRO) class="space-y-6">

                            {{-- ══ DATA DASAR ══ --}}
                            <section class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <x-input-label value="Tanggal / Jam *" class="mb-1" />
                                    <div class="flex items-center gap-2">
                                        <x-text-input wire:model.live="newForm.tanggal" placeholder="dd/mm/yyyy HH:mm:ss"
                                            :error="$errors->has('newForm.tanggal')" class="w-full" />
                                        @if (!$formRO)
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
                                                wire:model.live="newForm.kriteria" :disabled="$formRO" />
                                        @endforeach
                                    </div>
                                </div>
                                <div>
                                    <x-input-label value="Diagnosis Pra Anestesi *" class="mb-1" />
                                    <x-textarea wire:model.live="newForm.diagnosisPraAnestesi" :error="$errors->has('newForm.diagnosisPraAnestesi')" rows="2" class="w-full" />
                                    <x-input-error :messages="$errors->get('newForm.diagnosisPraAnestesi')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Rencana Tindakan *" class="mb-1" />
                                    <x-textarea wire:model.live="newForm.rencanaTindakan" :error="$errors->has('newForm.rencanaTindakan')" rows="2" class="w-full" />
                                    <x-input-error :messages="$errors->get('newForm.rencanaTindakan')" class="mt-1" />
                                </div>
                            </section>

                            {{-- ══ ANAMNESE & RIWAYAT ══ --}}
                            <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                                <div>
                                    <x-input-label value="Anamnese" class="mb-1" />
                                    <x-textarea wire:model.live="newForm.anamnese" :error="$errors->has('newForm.anamnese')" rows="2" class="w-full" />
                                </div>
                                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <x-toggle wire:model.live="newForm.riwayatAnestesi" :trueValue="true" :falseValue="false" label="Ada riwayat anestesi" :disabled="$formRO" />
                                    <x-toggle wire:model.live="newForm.riwayatAlergi" :trueValue="true" :falseValue="false" label="Ada riwayat alergi" :disabled="$formRO" />
                                </div>
                                @if ($newForm['riwayatAnestesi'])
                                    <x-text-input wire:model.live="newForm.riwayatAnestesiKet" :error="$errors->has('newForm.riwayatAnestesiKet')" placeholder="Keterangan riwayat anestesi" class="w-full" />
                                @endif
                                @if ($newForm['riwayatAlergi'])
                                    <x-text-input wire:model.live="newForm.riwayatAlergiKet" :error="$errors->has('newForm.riwayatAlergiKet')" placeholder="Keterangan alergi" class="w-full" />
                                @endif
                                <div>
                                    <x-input-label value="Obat yang Sedang Dikonsumsi" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.obatDikonsumsi" :error="$errors->has('newForm.obatDikonsumsi')" class="w-full" />
                                </div>
                                <div class="flex flex-wrap gap-4">
                                    <x-toggle wire:model.live="newForm.merokok" :trueValue="true" :falseValue="false" label="Merokok" :disabled="$formRO" />
                                    <x-toggle wire:model.live="newForm.alkohol" :trueValue="true" :falseValue="false" label="Alkohol" :disabled="$formRO" />
                                </div>
                            </section>

                            {{-- ══ ANTROPOMETRI & TTV ══ --}}
                            <section class="pt-6 border-t border-hairline dark:border-gray-700">
                                <h3 class="mb-3 text-base font-semibold text-ink dark:text-gray-200">Antropometri & Tanda Vital</h3>
                                <div class="grid grid-cols-2 gap-4 md:grid-cols-4 lg:grid-cols-8">
                                    <div><x-input-label value="BB (kg)" class="mb-1" /><x-text-input wire:model.live="newForm.bb" :error="$errors->has('newForm.bb')" class="w-full" /></div>
                                    <div><x-input-label value="TB (cm)" class="mb-1" /><x-text-input wire:model.live="newForm.tb" :error="$errors->has('newForm.tb')" class="w-full" /></div>
                                    <div><x-input-label value="BMI" class="mb-1" /><x-text-input wire:model.live="newForm.bmi" :error="$errors->has('newForm.bmi')" class="w-full" /></div>
                                    <div><x-input-label value="TD" class="mb-1" /><x-text-input wire:model.live="newForm.td" :error="$errors->has('newForm.td')" placeholder="120/80" class="w-full" /></div>
                                    <div><x-input-label value="Nadi" class="mb-1" /><x-text-input wire:model.live="newForm.nadi" :error="$errors->has('newForm.nadi')" class="w-full" /></div>
                                    <div><x-input-label value="RR" class="mb-1" /><x-text-input wire:model.live="newForm.rr" :error="$errors->has('newForm.rr')" class="w-full" /></div>
                                    <div><x-input-label value="Suhu" class="mb-1" /><x-text-input wire:model.live="newForm.suhu" :error="$errors->has('newForm.suhu')" class="w-full" /></div>
                                    <div><x-input-label value="Skor Nyeri" class="mb-1" /><x-text-input wire:model.live="newForm.skorNyeri" :error="$errors->has('newForm.skorNyeri')" class="w-full" /></div>
                                </div>
                            </section>

                            {{-- ══ EVALUASI JALAN NAFAS ══ --}}
                            <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                                <h3 class="text-base font-semibold text-ink dark:text-gray-200">Evaluasi Jalan Nafas</h3>
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                    <div>
                                        <x-input-label value="Mallampati *" class="mb-1" />
                                        <x-select-input wire:model.live="newForm.mallampati" :error="$errors->has('newForm.mallampati')" class="w-full">
                                            <option value="">— pilih —</option>
                                            @foreach ($mallampatiOptions as $opt)
                                                <option value="{{ $opt }}">{{ $opt }}</option>
                                            @endforeach
                                        </x-select-input>
                                        <x-input-error :messages="$errors->get('newForm.mallampati')" class="mt-1" />
                                    </div>
                                    <div>
                                        <x-input-label value="Buka Mulut (cm)" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.bukaMulut" :error="$errors->has('newForm.bukaMulut')" class="w-full" />
                                    </div>
                                    <div>
                                        <x-input-label value="Gerak Leher" class="mb-1" />
                                        <x-select-input wire:model.live="newForm.gerakLeher" :error="$errors->has('newForm.gerakLeher')" class="w-full">
                                            <option value="">— pilih —</option>
                                            @foreach ($gerakLeherOptions as $opt)
                                                <option value="{{ $opt }}">{{ $opt }}</option>
                                            @endforeach
                                        </x-select-input>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-4">
                                    <x-toggle wire:model.live="newForm.gigiPalsu" :trueValue="true" :falseValue="false" label="Gigi palsu" :disabled="$formRO" />
                                    <x-toggle wire:model.live="newForm.obesitas" :trueValue="true" :falseValue="false" label="Obesitas" :disabled="$formRO" />
                                    <x-toggle wire:model.live="newForm.sulitVentilasi" :trueValue="true" :falseValue="false" label="Prediksi sulit ventilasi" :disabled="$formRO" />
                                </div>
                            </section>

                            {{-- ══ SISTEM ORGAN & PENUNJANG ══ --}}
                            <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                                <div>
                                    <x-input-label value="Catatan Fungsi Sistem Organ (pernafasan/kardiovaskuler/neuro/renal/endokrin/lain)" class="mb-1" />
                                    <x-textarea wire:model.live="newForm.fungsiOrgan" :error="$errors->has('newForm.fungsiOrgan')" rows="2" class="w-full" />
                                </div>
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <x-input-label value="Pemeriksaan Laboratorium" class="mb-1" />
                                        <x-textarea wire:model.live="newForm.pemeriksaanLab" :error="$errors->has('newForm.pemeriksaanLab')" rows="2" class="w-full" />
                                    </div>
                                    <div>
                                        <x-input-label value="Pemeriksaan Penunjang (X-Ray/EKG/dll)" class="mb-1" />
                                        <x-textarea wire:model.live="newForm.pemeriksaanPenunjang" :error="$errors->has('newForm.pemeriksaanPenunjang')" rows="2" class="w-full" />
                                    </div>
                                </div>
                            </section>

                            {{-- ══ KESIMPULAN ══ --}}
                            <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                                <h3 class="text-base font-semibold text-ink dark:text-gray-200">Kesimpulan Evaluasi Pra Anestesi</h3>
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <x-input-label value="Jenis Anestesi *" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.jenisAnestesi" :error="$errors->has('newForm.jenisAnestesi')" placeholder="cth: GA / Spinal / Sedasi" class="w-full" />
                                        <x-input-error :messages="$errors->get('newForm.jenisAnestesi')" class="mt-1" />
                                    </div>
                                    <div>
                                        <x-input-label value="PS ASA *" class="mb-1" />
                                        <x-select-input wire:model.live="newForm.psAsa" :error="$errors->has('newForm.psAsa')" class="w-full">
                                            <option value="">— pilih —</option>
                                            @foreach ($asaOptions as $opt)
                                                <option value="{{ $opt }}">{{ $opt }}</option>
                                            @endforeach
                                        </x-select-input>
                                        <x-input-error :messages="$errors->get('newForm.psAsa')" class="mt-1" />
                                    </div>
                                    <div>
                                        <x-input-label value="Induksi Pra Anestesi" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.induksiPraAnestesi" :error="$errors->has('newForm.induksiPraAnestesi')" class="w-full" />
                                    </div>
                                    <div>
                                        <x-input-label value="Penyulit" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.penyulit" :error="$errors->has('newForm.penyulit')" class="w-full" />
                                    </div>
                                    <div>
                                        <x-input-label value="Komplikasi" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.komplikasi" :error="$errors->has('newForm.komplikasi')" class="w-full" />
                                    </div>
                                    <div>
                                        <x-input-label value="Obat Analgesik Pasca Operasi" class="mb-1" />
                                        <x-text-input wire:model.live="newForm.obatAnalgesikPascaOp" :error="$errors->has('newForm.obatAnalgesikPascaOp')" class="w-full" />
                                    </div>
                                </div>
                            </section>

                            {{-- ══ TTD ══ --}}
                            <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                                {{-- Kiri = TTD gambar Pasien/Keluarga (field entri biasa); Kanan = TTD Petugas (kunci) --}}
                                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                    {{-- Pasien / Keluarga (KIRI) — TTD gambar; pad hanya saat form aktif, hasil selalu tampil --}}
                                    <div class="flex flex-col">
                                        <div class="mb-2 text-sm font-semibold tracking-wide text-center text-muted uppercase dark:text-gray-400">Pasien / Keluarga</div>
                                        @if (!empty($signaturePasien))
                                            <x-signature.signature-result :signature="$signaturePasien" :date="''" :disabled="$formRO" wireMethod="clearSignaturePasien" />
                                        @elseif (!$formRO)
                                            <x-signature.signature-pad wireMethod="setSignaturePasien" />
                                        @else
                                            <p class="py-8 text-base italic text-center text-muted-soft">Belum ditandatangani.</p>
                                        @endif
                                    </div>
                                    {{-- Petugas / Dokter Anestesi (KANAN) — stempel = FINALIZE/kunci --}}
                                    <x-signature.ttd-petugas :framed="false" :ttd="$newForm['ttd']"
                                        :date="$newForm['ttdDate'] ?? ''" :code="$newForm['ttdCode'] ?? ''"
                                        :locked="$formRO" sign="setTtd" clear="clearTtd"
                                        title="TTD Petugas & Kunci" label="Dokter Anestesi"
                                        nameLabel="Dokter Anestesi" dateLabel="Waktu TTD"
                                        signLabel="TTD Petugas &amp; Kunci" clearLabel="Batal TTD" />
                                </div>
                                @if (!$formRO)
                                    <p class="text-xs text-center text-muted">Menandatangani = mengunci pengkajian ini. TTD gambar pasien tersimpan sebagai isian entri.</p>
                                @endif
                            </section>
                        </fieldset>

                        {{-- ── DAFTAR PENGKAJIAN TERSIMPAN (expandable) ── --}}
                        <div class="pt-6 border-t border-hairline dark:border-gray-700">
                            <h3 class="text-base font-semibold text-body dark:text-gray-300 pb-2 border-b border-hairline-soft dark:border-gray-800 mb-3">Daftar Pengkajian Tersimpan</h3>
                            @if (count($praList ?? []))
                                <p class="mb-3 text-xs italic text-muted-soft">Klik baris untuk lihat detail lengkap</p>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-sm border border-hairline rounded-lg dark:border-gray-700">
                                        <thead class="bg-surface-soft dark:bg-gray-800">
                                            <tr class="text-left text-sm font-semibold tracking-wide uppercase text-muted dark:text-gray-300">
                                                <th class="w-8 px-2 py-3 border-b"></th>
                                                <th class="px-4 py-3 border-b">Tanggal</th>
                                                <th class="px-4 py-3 border-b">ASA</th>
                                                <th class="px-4 py-3 border-b">Jenis Anestesi</th>
                                                <th class="px-4 py-3 border-b">Petugas (TTD)</th>
                                                <th class="px-4 py-3 text-center border-b">Status</th>
                                                <th class="px-4 py-3 text-center border-b">Aksi</th>
                                            </tr>
                                        </thead>
                                        @foreach (array_reverse($praList) as $entry)
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
                                                        {{ $entry['tanggal'] ?: ($rowKey ?: '-') }}
                                                    </td>
                                                    <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">{{ $entry['psAsa'] ?: '-' }}</td>
                                                    <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">{{ $entry['jenisAnestesi'] ? Str::limit($entry['jenisAnestesi'], 40) : '-' }}</td>
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
                                                        <div class="flex flex-col items-center gap-2">
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
                                                            <x-secondary-button type="button" wire:click="cetak('{{ $rowKey }}')" wire:loading.attr="disabled" wire:target="cetak('{{ $rowKey }}')" class="gap-1.5" title="Cetak">
                                                                <span wire:loading.remove wire:target="cetak('{{ $rowKey }}')" class="flex items-center gap-1.5">
                                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                                                                    Cetak
                                                                </span>
                                                                <span wire:loading wire:target="cetak('{{ $rowKey }}')" class="flex items-center gap-1"><x-loading class="w-4 h-4" /> ...</span>
                                                            </x-secondary-button>
                                                            </div>
                                                            @if (!$isFormLocked)
                                                                <div class="flex items-center justify-center gap-2">
                                                                @can('dokumen.hapus')
                                                                <x-outline-button type="button" wire:click.prevent="hapus('{{ $rowKey }}')" wire:confirm="Yakin hapus pengkajian ini?"
                                                                    wire:loading.attr="disabled"
                                                                    class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300"
                                                                    title="Hapus">
                                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                    </svg>
                                                                </x-outline-button>
                                                                @endcan
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </td>
                                                </tr>

                                                {{-- DETAIL (expand) --}}
                                                <tr x-show="open" x-cloak>
                                                    <td colspan="7" class="px-4 py-4 bg-surface-soft/60 dark:bg-gray-950/30">
                                                        <dl class="grid grid-cols-1 gap-x-8 gap-y-3 md:grid-cols-2">
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Tanggal / Jam</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['tanggal'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Kriteria Pasien</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['kriteria'] ?: '-' }}</dd>
                                                            </div>
                                                            <div class="md:col-span-2">
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Diagnosis Pra Anestesi</dt>
                                                                <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['diagnosisPraAnestesi'] ?: '-' }}</dd>
                                                            </div>
                                                            <div class="md:col-span-2">
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Rencana Tindakan</dt>
                                                                <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['rencanaTindakan'] ?: '-' }}</dd>
                                                            </div>
                                                            <div class="md:col-span-2">
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Anamnese</dt>
                                                                <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['anamnese'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Riwayat Anestesi</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['riwayatAnestesi'] ?? false) ? 'Ya' : 'Tidak' }}{{ !empty($entry['riwayatAnestesiKet']) ? ' — ' . $entry['riwayatAnestesiKet'] : '' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Riwayat Alergi</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['riwayatAlergi'] ?? false) ? 'Ya' : 'Tidak' }}{{ !empty($entry['riwayatAlergiKet']) ? ' — ' . $entry['riwayatAlergiKet'] : '' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Obat Dikonsumsi</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['obatDikonsumsi'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Merokok / Alkohol</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['merokok'] ?? false) ? 'Merokok' : '—' }} / {{ ($entry['alkohol'] ?? false) ? 'Alkohol' : '—' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">BB / TB / BMI</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['bb'] ?: '-' }} kg / {{ $entry['tb'] ?: '-' }} cm / {{ $entry['bmi'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">TD / Nadi / RR / Suhu</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['td'] ?: '-' }} · {{ $entry['nadi'] ?: '-' }} · {{ $entry['rr'] ?: '-' }} · {{ $entry['suhu'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Skor Nyeri</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['skorNyeri'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Mallampati</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['mallampati'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Buka Mulut / Gerak Leher</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['bukaMulut'] ?: '-' }} / {{ $entry['gerakLeher'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Gigi Palsu / Obesitas / Sulit Ventilasi</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ ($entry['gigiPalsu'] ?? false) ? 'Ya' : '—' }} / {{ ($entry['obesitas'] ?? false) ? 'Ya' : '—' }} / {{ ($entry['sulitVentilasi'] ?? false) ? 'Ya' : '—' }}</dd>
                                                            </div>
                                                            <div class="md:col-span-2">
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Fungsi Sistem Organ</dt>
                                                                <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['fungsiOrgan'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Pemeriksaan Laboratorium</dt>
                                                                <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['pemeriksaanLab'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Pemeriksaan Penunjang</dt>
                                                                <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['pemeriksaanPenunjang'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Jenis Anestesi</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['jenisAnestesi'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">PS ASA</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['psAsa'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Induksi Pra Anestesi</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['induksiPraAnestesi'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Penyulit</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['penyulit'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Komplikasi</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['komplikasi'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Obat Analgesik Pasca Op</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['obatAnalgesikPascaOp'] ?: '-' }}</dd>
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
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">TTD Pasien / Keluarga</dt>
                                                                <dd class="mt-0.5">
                                                                    @if (!empty($entry['signaturePasien']))
                                                                        <x-badge variant="success">Ada</x-badge>
                                                                    @else
                                                                        <x-badge variant="warning">Belum ada</x-badge>
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
                                <p class="text-sm text-muted dark:text-gray-400">Belum ada pengkajian tersimpan.</p>
                            @endif
                        </div>

                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 px-6 py-4 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
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
