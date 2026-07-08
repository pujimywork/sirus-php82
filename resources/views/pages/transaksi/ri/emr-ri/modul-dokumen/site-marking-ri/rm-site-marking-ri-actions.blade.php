<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/site-marking-ri/rm-site-marking-ri-actions.blade.php
// Dokumen Bedah — Penandaan Lokasi Operasi (Site Marking, SKP 4 / RM 49).
// Pola: multi-entri (Draft + Lanjut Isi + TTD-Kunci + Lihat read-only + tabel expandable),
// disimpan ke datadaftarri_json['siteMarkingRI']. Kunci entri stabil = createdAt.
// FINALIZE = setOperatorTtd() (stempel Dokter Operator = user login → kunci entri).
// Field indikator "operator sudah TTD" = operatorTtd. TTD gambar perawat ruangan & kamar bedah
// = field entri biasa (diisi saat draft), BUKAN pemicu kunci.

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
    protected array $renderAreas = ['modal-site-marking-ri'];

    /** Key penyimpanan di datadaftarri_json */
    private string $jsonKey = 'siteMarkingRI';

    // ── Form entri (Penandaan Lokasi Operasi — SKP 4 / RM 49) ──
    public array $newForm = [
        'tanggal' => '',
        'rencanaTindakan' => '',
        'perluPenandaan' => 'Ya',
        'alasanTidakPerlu' => '',
        'regionAnatomi' => '',
        'sisi' => '',
        'detailLokasi' => '',
        'metodePenandaan' => 'Spidol permanen — inisial/tanda operator',
        'pasienDilibatkan' => false,
        'namaPerawatRuangan' => '',
        'namaPerawatKamarBedah' => '',
        // TTD operator (auto user login) = FINALIZE
        'operatorTtd' => '',
        'operatorTtdCode' => '',
        'operatorTtdDate' => '',
    ];

    // TTD gambar (drawn) — field entri biasa, diisi saat draft, BUKAN pemicu kunci.
    public string $signaturePerawatRuangan = '';
    public string $signaturePerawatKamarBedah = '';

    // Tanda titik pada diagram tubuh: [['view'=>'anterior'|'posterior','x'=>float,'y'=>float], ...]
    public array $marks = [];

    public array $markingList = [];

    // Kunci entri yang sedang diedit (createdAt = kunci stabil). null = membuat entri baru.
    public ?string $editingKey = null;
    // true = entri terkunci sedang ditampilkan di form dalam mode read-only (lihat saja).
    public bool $viewOnly = false;

    public array $perluOptions = ['Ya', 'Tidak diperlukan'];
    public array $sisiOptions = ['Kiri', 'Kanan', 'Bilateral', 'Garis tengah', 'Multipel level'];
    public array $regionOptions = [
        'Kepala & Leher', 'Mata', 'THT', 'Gigi & Mulut', 'Dada / Thoraks', 'Payudara',
        'Abdomen', 'Punggung / Spinal', 'Panggul', 'Genitalia',
        'Ekstremitas Atas', 'Ekstremitas Bawah', 'Tangan / Jari Tangan', 'Kaki / Jari Kaki', 'Lainnya',
    ];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(?string $riHdrNo = null, bool $disabled = false): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-site-marking-ri']);

        if ($this->riHdrNo) {
            $data = $this->findDataRI($this->riHdrNo);
            if ($data) {
                $this->dataDaftarRi = $data;
                $this->regNo = $data['regNo'] ?? null;
                $this->markingList = $data[$this->jsonKey] ?? [];
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

        $this->resetFormState();
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
        $this->markingList = $this->dataDaftarRi[$this->jsonKey];
        $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $this->disabled;
        $this->incrementVersion('modal-site-marking-ri');

        $this->dispatch('open-modal', name: "rm-site-marking-ri-{$this->riHdrNo}");
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: "rm-site-marking-ri-{$this->riHdrNo}");
    }

    /* ===============================
     | VALIDATION
     =============================== */
    protected function rules(): array
    {
        return [
            'newForm.tanggal' => 'required|date_format:d/m/Y H:i:s',
            'newForm.rencanaTindakan' => 'required|string|max:500',
            'newForm.perluPenandaan' => 'required|string',
            'newForm.alasanTidakPerlu' => 'required_if:newForm.perluPenandaan,Tidak diperlukan|nullable|string|max:500',
            'newForm.regionAnatomi' => 'required_if:newForm.perluPenandaan,Ya|nullable|string|max:100',
            'newForm.sisi' => 'required_if:newForm.perluPenandaan,Ya|nullable|string|max:50',
            'newForm.detailLokasi' => 'nullable|string|max:300',
            'newForm.metodePenandaan' => 'nullable|string|max:300',
            'newForm.namaPerawatRuangan' => 'required_if:newForm.perluPenandaan,Ya|nullable|string|max:200',
            'newForm.namaPerawatKamarBedah' => 'required_if:newForm.perluPenandaan,Ya|nullable|string|max:200',
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'required_if' => ':attribute wajib diisi.',
            'date_format' => 'Format :attribute harus dd/mm/yyyy HH:mm:ss (cth: 26/06/2026 02:00:00).',
            'max' => ':attribute maksimal :max karakter.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'newForm.tanggal' => 'Tanggal/jam penandaan',
            'newForm.rencanaTindakan' => 'Rencana tindakan operasi',
            'newForm.perluPenandaan' => 'Perlu penandaan',
            'newForm.alasanTidakPerlu' => 'Alasan tidak perlu penandaan',
            'newForm.regionAnatomi' => 'Region anatomi',
            'newForm.sisi' => 'Sisi/lateralitas',
            'newForm.namaPerawatRuangan' => 'Nama perawat ruangan',
            'newForm.namaPerawatKamarBedah' => 'Nama perawat kamar bedah',
        ];
    }

    /* ===============================
     | SET TANGGAL SEKARANG
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
    // ada TTD operator (operatorTtd) dianggap final (kompatibilitas data lama).
    public function entryIsFinal(array $e): bool
    {
        return array_key_exists('finalized', $e) ? (bool) $e['finalized'] : !empty($e['operatorTtd']);
    }

    // Susun array entri dari state form. $key = createdAt (kunci stabil); $finalized = status kunci.
    // Sertakan TTD gambar perawat & marks (dari propertinya) — nol saat "Tidak diperlukan".
    private function buildEntry(string $key, bool $finalized): array
    {
        $perlu = ($this->newForm['perluPenandaan'] ?? '') === 'Ya';

        return array_merge($this->newForm, [
            'signaturePerawatRuangan'    => $perlu ? $this->signaturePerawatRuangan : '',
            'signaturePerawatKamarBedah' => $perlu ? $this->signaturePerawatKamarBedah : '',
            'marks'                      => $perlu ? $this->marks : [],
            'createdAt'                  => $key,
            'finalized'                  => $finalized,
        ]);
    }

    // Cek: minimal inti terisi (tanggal atau rencana tindakan).
    private function adaIntiTerisi(): bool
    {
        return filled($this->newForm['tanggal'] ?? null) || filled($this->newForm['rencanaTindakan'] ?? null);
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
            $this->markingList = $fresh[$this->jsonKey];

            $lokasi = trim(($entry['regionAnatomi'] ?? '') . ' ' . ($entry['sisi'] ?? '')) ?: '-';
            $this->appendAdminLogRI((int) $this->riHdrNo, $logVerb . ' Penandaan Lokasi Operasi — ' . $lokasi . ' (' . $key . ')', 'MR');
        });
    }

    /* ===============================
     | SIMPAN DRAFT (nyicil, tanpa wajib TTD operator)
     =============================== */
    public function saveDraft(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }
        if (!$this->adaIntiTerisi()) {
            $this->dispatch('toast', type: 'error', message: 'Isi minimal Tanggal/Jam atau Rencana Tindakan Operasi.');
            return;
        }

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, false, 'Simpan draft');
            $this->editingKey = $key; // lanjut edit entri yang sama, tidak buat duplikat
            $this->incrementVersion('modal-site-marking-ri');
            $this->dispatch('toast', type: 'success', message: 'Draft tersimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan draft: ' . $e->getMessage());
        }
    }

    /* ===============================
     | TTD OPERATOR = FINALIZE (kunci entri)
     | Validasi penuh + TTD gambar perawat (bila perlu) → stempel operator → kunci.
     =============================== */
    public function setOperatorTtd(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }

        $this->validateWithToast();

        // Bila penandaan diperlukan, TTD gambar perawat ruangan & kamar bedah wajib.
        $perlu = ($this->newForm['perluPenandaan'] ?? '') === 'Ya';
        if ($perlu && (empty($this->signaturePerawatRuangan) || empty($this->signaturePerawatKamarBedah))) {
            $this->dispatch('toast', type: 'error', message: 'Tanda tangan perawat ruangan & perawat kamar bedah wajib diisi sebelum dikunci.');
            return;
        }

        // Stempel TTD Dokter Operator = user login.
        $this->newForm['operatorTtd'] = auth()->user()->myuser_name ?? '';
        $this->newForm['operatorTtdCode'] = auth()->user()->myuser_code ?? '';
        $this->newForm['operatorTtdDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, true, 'Kunci (TTD Operator)');
            $this->resetFormState();
            $this->incrementVersion('modal-site-marking-ri');
            $this->dispatch('toast', type: 'success', message: 'Penandaan ditandatangani Dokter Operator & terkunci.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal mengunci: ' . $e->getMessage());
        }
    }

    /** Batalkan TTD operator pada form (saat draft/edit, sebelum finalize tersimpan). */
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
     | TANDA DIAGRAM TUBUH (klik SVG)
     =============================== */
    public array $validViews = [
        'priaFront', 'priaBack', 'wanitaFront', 'wanitaBack',
        'handPalmKiri', 'handPalmKanan', 'handDorsumKiri', 'handDorsumKanan',
        'footPalmKanan', 'footPalmKiri', 'footDorsumKiri', 'footDorsumKanan',
        'headFront', 'headBack', 'headProfileKiri', 'headProfileKanan',
    ];

    public function addMark(string $view, $x, $y): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        if (!in_array($view, $this->validViews, true)) {
            return;
        }
        // koordinat persen (0..100) relatif panel
        $x = max(0, min(100, (float) $x));
        $y = max(0, min(100, (float) $y));
        $this->marks[] = ['view' => $view, 'x' => round($x, 2), 'y' => round($y, 2)];
    }

    public function undoMark(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        array_pop($this->marks);
    }

    public function clearMarks(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->marks = [];
    }

    /* ===============================
     | SIGNATURE PERAWAT (drawn) — field entri biasa (diisi saat draft)
     =============================== */
    public function setSignaturePerawatRuangan(string $dataUrl): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->signaturePerawatRuangan = $dataUrl;
        $this->incrementVersion('modal-site-marking-ri');
    }

    public function clearSignaturePerawatRuangan(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->signaturePerawatRuangan = '';
        $this->incrementVersion('modal-site-marking-ri');
    }

    public function setSignaturePerawatKamarBedah(string $dataUrl): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->signaturePerawatKamarBedah = $dataUrl;
        $this->incrementVersion('modal-site-marking-ri');
    }

    public function clearSignaturePerawatKamarBedah(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->signaturePerawatKamarBedah = '';
        $this->incrementVersion('modal-site-marking-ri');
    }

    /* ===============================
     | EDIT / LIHAT / BATAL entri
     =============================== */
    // Muat 1 entri ke form atas (dipakai edit draft & lihat entri terkunci). Termasuk TTD gambar & marks.
    private function hydrateFormFromEntry(array $entry, string $key): void
    {
        foreach ($this->newForm as $k => $v) {
            if (is_bool($v)) {
                $this->newForm[$k] = (bool) ($entry[$k] ?? false);
            } elseif (is_array($v)) {
                $this->newForm[$k] = $entry[$k] ?? [];
            } else {
                $this->newForm[$k] = $entry[$k] ?? '';
            }
        }
        $this->signaturePerawatRuangan = $entry['signaturePerawatRuangan'] ?? '';
        $this->signaturePerawatKamarBedah = $entry['signaturePerawatKamarBedah'] ?? '';
        $this->marks = $entry['marks'] ?? [];

        $this->editingKey = $key;
        $this->resetValidation();
        $this->incrementVersion('modal-site-marking-ri');
    }

    public function editEntry(string $key): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        $entry = collect($this->markingList)->firstWhere('createdAt', $key);
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
        $entry = collect($this->markingList)->firstWhere('createdAt', $key);
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
        $this->resetFormState();
        $this->resetValidation();
        $this->incrementVersion('modal-site-marking-ri');
    }

    /* ===============================
     | CETAK (inline stream PDF, per-entri)
     =============================== */
    public function cetak(string $createdAt)
    {
        $entry = collect($this->markingList)->firstWhere('createdAt', $createdAt);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Data penandaan tidak ditemukan.');
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

            $ttdOperatorPath = null;
            $operatorCode = $entry['operatorTtdCode'] ?? null;
            if ($operatorCode) {
                $path = DB::table('users')->where('myuser_code', $operatorCode)->value('myuser_ttd_image');
                if (!empty($path) && file_exists(public_path('storage/' . $path))) {
                    $ttdOperatorPath = public_path('storage/' . $path);
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

            $pdf = Pdf::loadView('pages.components.modul-dokumen.r-i.site-marking-ri.cetak-site-marking-ri-print', ['data' => $data])->setPaper('A4');

            $this->dispatch('toast', type: 'success', message: 'Berhasil mencetak penandaan lokasi operasi.');
            return response()->streamDownload(fn() => print $pdf->output(), 'site-marking-ri-' . ($pasien['regNo'] ?? $this->riHdrNo) . '.pdf');
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
                $fresh[$this->jsonKey] = collect($fresh[$this->jsonKey] ?? [])
                    ->reject(fn($item) => ($item['createdAt'] ?? '') === $createdAt)
                    ->values()
                    ->toArray();

                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
                $this->markingList = $fresh[$this->jsonKey];

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Penandaan Lokasi Operasi — ' . $createdAt, 'MR');
            });

            // Jika entri yang dihapus sedang di form, kosongkan form.
            if ($this->editingKey === $createdAt) {
                $this->cancelEdit();
            }

            $this->incrementVersion('modal-site-marking-ri');
            $this->dispatch('toast', type: 'success', message: 'Penandaan lokasi operasi berhasil dihapus.');
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
            'tanggal' => '',
            'rencanaTindakan' => '',
            'perluPenandaan' => 'Ya',
            'alasanTidakPerlu' => '',
            'regionAnatomi' => '',
            'sisi' => '',
            'detailLokasi' => '',
            'metodePenandaan' => 'Spidol permanen — inisial/tanda operator',
            'pasienDilibatkan' => false,
            'namaPerawatRuangan' => '',
            'namaPerawatKamarBedah' => '',
            'operatorTtd' => '',
            'operatorTtdCode' => '',
            'operatorTtdDate' => '',
        ];
    }

    // Reset form + TTD gambar + marks + state edit/lihat.
    private function resetFormState(): void
    {
        $this->resetNewForm();
        $this->signaturePerawatRuangan = '';
        $this->signaturePerawatKamarBedah = '';
        $this->marks = [];
        $this->editingKey = null;
        $this->viewOnly = false;
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->dataDaftarRi = [];
        $this->markingList = [];
        $this->resetFormState();
    }
};
?>

<div>
    {{-- ══ SUMMARY CARD (inline) ══ --}}
    @php $smCount = count($markingList ?? []); @endphp

    <div class="p-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex-1 space-y-3">
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">Penandaan Lokasi Operasi</h3>
                    @if ($smCount > 0)
                        <x-badge variant="success">{{ $smCount }} penandaan</x-badge>
                    @else
                        <x-badge variant="warning">Belum ada</x-badge>
                    @endif
                </div>
                <p class="text-base text-muted dark:text-gray-400">
                    Site marking (SKP 4): penandaan sisi/lokasi operasi sebelum tindakan, melibatkan pasien, diverifikasi
                    Perawat Ruangan, Perawat Kamar Bedah &amp; Dokter Operator. Tiap entri bisa dicicil sebagai Draft lalu
                    dikunci dengan TTD Dokter Operator.
                </p>
                @if ($smCount > 0)
                    <ul class="space-y-1 text-base text-muted dark:text-gray-300 list-disc pl-5">
                        @foreach (array_slice(array_reverse($markingList), 0, 3) as $sm)
                            <li>
                                <span class="font-medium">{{ trim(($sm['regionAnatomi'] ?? '-') . ' ' . ($sm['sisi'] ?? '')) ?: 'Tidak diperlukan' }}</span>
                                @if (!empty($sm['tanggal']))
                                    <span class="text-sm text-muted-soft">— {{ $sm['tanggal'] }}</span>
                                @endif
                                @if ($this->entryIsFinal($sm))
                                    <x-badge variant="info">Terkunci</x-badge>
                                @else
                                    <x-badge variant="warning">Draft</x-badge>
                                @endif
                            </li>
                        @endforeach
                        @if ($smCount > 3)
                            <li class="text-sm italic text-muted-soft">+{{ $smCount - 3 }} lainnya…</li>
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
    <x-modal name="rm-site-marking-ri-{{ $riHdrNo ?? 'init' }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal-site-marking-ri', [$riHdrNo ?? 'new']) }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-indigo-500/10">
                                <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="font-semibold text-2xl text-ink dark:text-gray-100">Penandaan Lokasi Operasi
                                </h2>
                                <p class="mt-0.5 text-base text-muted dark:text-gray-400">
                                    SKP 4 — site marking sebelum operasi, verifikasi 3 pihak
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 mt-3">
                            <x-badge variant="brand">Rawat Inap</x-badge>
                            @if (count($markingList) > 0)
                                <x-badge variant="info">{{ count($markingList) }} tersimpan</x-badge>
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

                    <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                        wire:key="sm-ri-display-pasien-{{ $riHdrNo ?? 'init' }}" />

                    @php $formRO = $isFormLocked || $viewOnly; @endphp

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
                            Sedang melanjutkan entri <strong>{{ $editingKey }}</strong> — <strong>Simpan Perubahan</strong> menyimpan ke entri ini; klik <strong>Entri Baru</strong> untuk menambah penandaan lain.
                        </div>
                    @endif

                    {{-- ── FORM ENTRI ── --}}
                    <fieldset @disabled($formRO)
                        class="p-6 space-y-6 bg-canvas border border-hairline shadow-sm sm:p-8 rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                        {{-- ══ TANGGAL & RENCANA ══ --}}
                        <section class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <x-input-label value="Tanggal / Jam Penandaan *" class="mb-1" />
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
                                <x-input-label value="Rencana Tindakan Operasi *" class="mb-1" />
                                <x-text-input wire:model.live="newForm.rencanaTindakan" :error="$errors->has('newForm.rencanaTindakan')"
                                    class="w-full" />
                                <x-input-error :messages="$errors->get('newForm.rencanaTindakan')" class="mt-1" />
                            </div>
                        </section>

                        {{-- ══ PERLU PENANDAAN? ══ --}}
                        <section class="pt-6 space-y-3 border-t border-hairline dark:border-gray-700">
                            <x-input-label value="Penandaan Lokasi *" class="mb-1" />
                            <div class="flex flex-wrap gap-2">
                                @foreach ($perluOptions as $opt)
                                    <x-radio-button :label="$opt" :value="$opt" name="perluPenandaan"
                                        wire:model.live="newForm.perluPenandaan" />
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('newForm.perluPenandaan')" class="mt-1" />

                            @if (($newForm['perluPenandaan'] ?? '') === 'Tidak diperlukan')
                                <div class="mt-2">
                                    <x-input-label value="Alasan Tidak Diperlukan *" class="mb-1" />
                                    <x-textarea wire:model.live="newForm.alasanTidakPerlu" :error="$errors->has('newForm.alasanTidakPerlu')" rows="2"
                                        placeholder="cth: organ tunggal / garis tengah / kasus tidak melibatkan lateralitas"
                                        class="w-full" />
                                    <x-input-error :messages="$errors->get('newForm.alasanTidakPerlu')" class="mt-1" />
                                </div>
                            @endif
                        </section>

                        {{-- ══ DETAIL LOKASI (bila perlu) ══ --}}
                        @if (($newForm['perluPenandaan'] ?? '') === 'Ya')
                            <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <x-input-label value="Region Anatomi *" class="mb-1" />
                                        <x-select-input wire:model.live="newForm.regionAnatomi" :error="$errors->has('newForm.regionAnatomi')"
                                            class="w-full">
                                            <option value="">— pilih —</option>
                                            @foreach ($regionOptions as $opt)
                                                <option value="{{ $opt }}">{{ $opt }}</option>
                                            @endforeach
                                        </x-select-input>
                                        <x-input-error :messages="$errors->get('newForm.regionAnatomi')" class="mt-1" />
                                    </div>
                                    <div>
                                        <x-input-label value="Sisi / Lateralitas *" class="mb-1" />
                                        <x-select-input wire:model.live="newForm.sisi" :error="$errors->has('newForm.sisi')"
                                            class="w-full">
                                            <option value="">— pilih —</option>
                                            @foreach ($sisiOptions as $opt)
                                                <option value="{{ $opt }}">{{ $opt }}</option>
                                            @endforeach
                                        </x-select-input>
                                        <x-input-error :messages="$errors->get('newForm.sisi')" class="mt-1" />
                                    </div>
                                </div>
                                <div>
                                    <x-input-label value="Detail Lokasi" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.detailLokasi" :error="$errors->has('newForm.detailLokasi')"
                                        placeholder="cth: digiti III pedis (D)" class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Metode Penandaan" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.metodePenandaan" :error="$errors->has('newForm.metodePenandaan')"
                                        class="w-full" />
                                </div>
                                <x-toggle wire:model.live="newForm.pasienDilibatkan" :trueValue="true"
                                    :falseValue="false" label="Pasien dilibatkan saat penandaan" />
                            </section>

                            {{-- ══ DIAGRAM PENANDAAN (klik tubuh) ══ --}}
                            <section class="pt-6 space-y-3 border-t border-hairline dark:border-gray-700" x-data="{}">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">Diagram Penandaan Lokasi</h3>
                                    @if (!$formRO)
                                        <div class="flex gap-2">
                                            <x-secondary-button type="button" wire:click="undoMark" class="text-sm py-1 px-2">Hapus tanda terakhir</x-secondary-button>
                                            <x-outline-button type="button" wire:click="clearMarks" wire:confirm="Bersihkan semua tanda?" class="!px-2 !py-1 text-sm">Bersihkan</x-outline-button>
                                        </div>
                                    @endif
                                </div>
                                <p class="text-sm text-muted-soft dark:text-gray-500">
                                    Klik pada panel (tubuh / kepala / tangan / kaki) untuk menandai lokasi operasi. Tanda bernomor urut per panel & tersimpan untuk dicetak.
                                </p>

                                <x-site-marking-diagram :marks="$marks" :editable="!$formRO"
                                    wire-add-mark="addMark" />

                                @if (count($marks) > 0)
                                    <p class="text-sm text-center text-muted dark:text-gray-400">{{ count($marks) }} tanda ditempatkan.</p>
                                @endif
                            </section>

                            {{-- ══ TTD PERAWAT (verifikasi) — field entri, diisi saat draft ══ --}}
                            <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                                <h3 class="text-base font-semibold text-ink dark:text-gray-200">Verifikasi Perawat</h3>
                                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                    {{-- Perawat Ruangan --}}
                                    <div class="flex flex-col">
                                        <div class="mb-2 text-sm font-semibold tracking-wide text-center text-muted uppercase dark:text-gray-400">
                                            Perawat Ruangan</div>
                                        @if (!empty($signaturePerawatRuangan))
                                            <x-signature.signature-result :signature="$signaturePerawatRuangan" :date="''"
                                                :disabled="$formRO" wireMethod="clearSignaturePerawatRuangan" />
                                        @elseif (!$formRO)
                                            <x-signature.signature-pad wireMethod="setSignaturePerawatRuangan" />
                                        @else
                                            <p class="py-8 text-base italic text-center text-muted-soft">Belum ditandatangani.</p>
                                        @endif
                                        <div class="mt-3">
                                            <x-input-label value="Nama Perawat Ruangan *" class="mb-1" />
                                            <x-text-input wire:model.live="newForm.namaPerawatRuangan" :error="$errors->has('newForm.namaPerawatRuangan')"
                                                class="w-full" />
                                            <x-input-error :messages="$errors->get('newForm.namaPerawatRuangan')" class="mt-1" />
                                        </div>
                                    </div>

                                    {{-- Perawat Kamar Bedah --}}
                                    <div class="flex flex-col">
                                        <div class="mb-2 text-sm font-semibold tracking-wide text-center text-muted uppercase dark:text-gray-400">
                                            Perawat Kamar Bedah</div>
                                        @if (!empty($signaturePerawatKamarBedah))
                                            <x-signature.signature-result :signature="$signaturePerawatKamarBedah" :date="''"
                                                :disabled="$formRO" wireMethod="clearSignaturePerawatKamarBedah" />
                                        @elseif (!$formRO)
                                            <x-signature.signature-pad wireMethod="setSignaturePerawatKamarBedah" />
                                        @else
                                            <p class="py-8 text-base italic text-center text-muted-soft">Belum ditandatangani.</p>
                                        @endif
                                        <div class="mt-3">
                                            <x-input-label value="Nama Perawat Kamar Bedah *" class="mb-1" />
                                            <x-text-input wire:model.live="newForm.namaPerawatKamarBedah" :error="$errors->has('newForm.namaPerawatKamarBedah')"
                                                class="w-full" />
                                            <x-input-error :messages="$errors->get('newForm.namaPerawatKamarBedah')" class="mt-1" />
                                        </div>
                                    </div>
                                </div>
                            </section>
                        @endif

                        {{-- ══ TTD DOKTER OPERATOR = FINALIZE / KUNCI ══ --}}
                        <x-signature.ttd-petugas :ttd="$newForm['operatorTtd']" :date="$newForm['operatorTtdDate'] ?? ''"
                            :code="$newForm['operatorTtdCode'] ?? ''" :locked="$formRO" sign="setOperatorTtd" clear="clearOperatorTtd"
                            title="Tanda Tangan Dokter Operator" nameLabel="Dokter Operator" dateLabel="Waktu TTD"
                            signLabel="TTD Operator &amp; Kunci" clearLabel="Batal TTD" />
                        @if (!$formRO)
                            <p class="-mt-2 text-xs text-center text-muted">Menandatangani sebagai Dokter Operator = mengunci penandaan ini.</p>
                        @endif
                    </fieldset>

                    {{-- ── DAFTAR PENANDAAN TERSIMPAN (expandable) ── --}}
                    <div class="p-6 bg-canvas border border-hairline shadow-sm sm:p-8 rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                        <h3 class="text-base font-semibold text-body dark:text-gray-300 pb-2 border-b border-hairline-soft dark:border-gray-800 mb-3">
                            Daftar Penandaan Tersimpan
                        </h3>
                        @if (count($markingList ?? []))
                            <p class="mb-3 text-xs italic text-muted-soft">Klik baris untuk lihat detail lengkap</p>
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm border border-hairline rounded-lg dark:border-gray-700">
                                    <thead class="bg-surface-soft dark:bg-gray-800">
                                        <tr class="text-left text-sm font-semibold tracking-wide uppercase text-muted dark:text-gray-300">
                                            <th class="w-8 px-2 py-3 border-b"></th>
                                            <th class="px-4 py-3 border-b">Tgl / Jam</th>
                                            <th class="px-4 py-3 border-b">Lokasi</th>
                                            <th class="px-4 py-3 border-b">Operator (TTD)</th>
                                            <th class="px-4 py-3 text-center border-b">Status</th>
                                            <th class="px-4 py-3 text-center border-b">Aksi</th>
                                        </tr>
                                    </thead>
                                    @foreach (array_reverse($markingList) as $entry)
                                        @php
                                            $isFinal = $this->entryIsFinal($entry);
                                            $rowKey = $entry['createdAt'] ?? '';
                                            $perluEntry = ($entry['perluPenandaan'] ?? '') === 'Ya';
                                            $lokasiEntry = $perluEntry ? trim(($entry['regionAnatomi'] ?? '') . ' ' . ($entry['sisi'] ?? '')) : 'Tidak diperlukan';
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
                                                <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">
                                                    {{ $lokasiEntry ?: '-' }}
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
                                                        <x-secondary-button type="button" wire:click="cetak('{{ $rowKey }}')" wire:loading.attr="disabled" wire:target="cetak('{{ $rowKey }}')" class="gap-1.5" title="Cetak">
                                                            <span wire:loading.remove wire:target="cetak('{{ $rowKey }}')" class="flex items-center gap-1">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                                </svg>
                                                                Cetak
                                                            </span>
                                                            <span wire:loading wire:target="cetak('{{ $rowKey }}')" class="flex items-center gap-1"><x-loading class="w-4 h-4" /> Mencetak...</span>
                                                        </x-secondary-button>
                                                        @if (!$isFormLocked)
                                                            <x-outline-button type="button" wire:click.prevent="hapus('{{ $rowKey }}')" wire:confirm="Yakin hapus penandaan ini?"
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
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Tgl / Jam Penandaan</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['tanggal'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Rencana Tindakan Operasi</dt>
                                                            <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['rencanaTindakan'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Perlu Penandaan</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['perluPenandaan'] ?: '-' }}</dd>
                                                        </div>
                                                        @if (!$perluEntry)
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Alasan Tidak Diperlukan</dt>
                                                                <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['alasanTidakPerlu'] ?: '-' }}</dd>
                                                            </div>
                                                        @else
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Region Anatomi</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['regionAnatomi'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Sisi / Lateralitas</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['sisi'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Detail Lokasi</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['detailLokasi'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Metode Penandaan</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['metodePenandaan'] ?: '-' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Pasien Dilibatkan</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ !empty($entry['pasienDilibatkan']) ? 'Ya' : 'Tidak' }}</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Jumlah Tanda Diagram</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">{{ count($entry['marks'] ?? []) }} tanda</dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Perawat Ruangan</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">
                                                                    {{ $entry['namaPerawatRuangan'] ?: '-' }}
                                                                    @if (!empty($entry['signaturePerawatRuangan']))
                                                                        <x-badge variant="success">TTD ada</x-badge>
                                                                    @else
                                                                        <x-badge variant="danger">Belum TTD</x-badge>
                                                                    @endif
                                                                </dd>
                                                            </div>
                                                            <div>
                                                                <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Perawat Kamar Bedah</dt>
                                                                <dd class="mt-0.5 text-ink dark:text-gray-200">
                                                                    {{ $entry['namaPerawatKamarBedah'] ?: '-' }}
                                                                    @if (!empty($entry['signaturePerawatKamarBedah']))
                                                                        <x-badge variant="success">TTD ada</x-badge>
                                                                    @else
                                                                        <x-badge variant="danger">Belum TTD</x-badge>
                                                                    @endif
                                                                </dd>
                                                            </div>
                                                        @endif
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Dokter Operator (TTD)</dt>
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
                            <p class="text-sm text-muted dark:text-gray-400">Belum ada penandaan tersimpan.</p>
                        @endif
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
                                    title="Kosongkan form untuk menambah penandaan lain — entri yang sudah tersimpan tidak berubah">
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
