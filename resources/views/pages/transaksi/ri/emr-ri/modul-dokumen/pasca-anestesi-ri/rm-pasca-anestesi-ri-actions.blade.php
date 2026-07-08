<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/pasca-anestesi-ri/rm-pasca-anestesi-ri-actions.blade.php
// Monitoring Pasca Anestesi (RR) — PAB 6.1 / RM 55.
// Pola: multi-entri (Draft + Lanjut Isi + TTD-Kunci + Lihat read-only + tabel expandable),
// disimpan ke datadaftarri_json (key pascaAnestesiRI). Kunci entri stabil = createdAt.
// TTD Petugas RR (setTtd) = FINALIZE/kunci (stempel nama user login), tanpa TTD gambar.

use Livewire\Component;
use Livewire\Attributes\Computed;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\WithValidationToast\WithValidationToastTrait;
use Illuminate\Support\Facades\DB;
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
    protected array $renderAreas = ['modal-pasca-anestesi-ri'];

    /** Key penyimpanan di datadaftarri_json */
    private string $jsonKey = 'pascaAnestesiRI';

    // ── Form entri (Monitoring Pasca Anestesi — PAB 6.1 / RM 55) ──
    public array $newForm = [
        'jamMasuk' => '',
        'jamKeluar' => '',
        'keadaanUmum' => '',
        'td' => '',
        'nadi' => '',
        'rr' => '',
        'suhu' => '',
        'spo2' => '',
        'jenisAnestesi' => 'Umum',
        'aldrete' => [
            'kesadaran' => '',
            'pernafasan' => '',
            'sirkulasi' => '',
            'aktivitas' => '',
            'warnaKulit' => '',
        ],
        'bromage' => '',
        'skalaNyeri' => '',
        'rekomendasi' => '',
        'keteranganRekomendasi' => '',
        // TTD Perawat RR
        'ttd' => '',
        'ttdCode' => '',
        'ttdDate' => '',
    ];

    public array $pascaList = [];

    // Kunci entri yang sedang diedit (createdAt = kunci stabil). null = membuat entri baru.
    public ?string $editingKey = null;

    // true = entri terkunci ditampilkan di form dalam mode read-only (lihat saja).
    public bool $viewOnly = false;

    public array $jenisAnestesiOptions = ['Umum', 'Regional / Spinal'];
    public array $rekomendasiOptions = ['Kembali ke ruangan rawat inap', 'Pindah ke ICU/HCU', 'Pulang (ODC)', 'Lain-lain'];

    // Aldrete: skor 0–2 per item
    public array $aldreteItems = [
        'kesadaran' => ['label' => 'Kesadaran', 'opsi' => ['2' => 'Sadar penuh', '1' => 'Bangun bila dipanggil', '0' => 'Tidak ada respon']],
        'pernafasan' => ['label' => 'Pernafasan', 'opsi' => ['2' => 'Nafas dalam & batuk bebas', '1' => 'Dangkal / sesak', '0' => 'Apnea / nafas dibantu']],
        'sirkulasi' => ['label' => 'Sirkulasi (TD)', 'opsi' => ['2' => '±20% nilai pra-op', '1' => '±20–50% nilai pra-op', '0' => '>50% nilai pra-op']],
        'aktivitas' => ['label' => 'Aktivitas / Pergerakan', 'opsi' => ['2' => 'Gerak 4 ekstremitas', '1' => 'Gerak 2 ekstremitas', '0' => 'Tidak dapat bergerak']],
        'warnaKulit' => ['label' => 'Warna Kulit / SpO2', 'opsi' => ['2' => 'Merah muda / SpO2 >92% udara kamar', '1' => 'Pucat / perlu O2', '0' => 'Sianosis']],
    ];

    // Bromage Score (anestesi regional/spinal) — sesuai form RS
    public array $bromageOptions = [
        '3' => 'Gerakan penuh tungkai',
        '2' => 'Mampu memfleksikan lutut',
        '1' => 'Tidak mampu memfleksikan pergelangan kaki',
        '0' => 'Tidak mampu menggerakkan tungkai',
    ];

    #[Computed]
    public function totalAldrete(): int
    {
        return collect($this->newForm['aldrete'] ?? [])
            ->filter(fn($nilai) => $nilai !== '' && $nilai !== null)
            ->sum(fn($nilai) => (int) $nilai);
    }

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(?string $riHdrNo = null, bool $disabled = false): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-pasca-anestesi-ri']);

        if ($this->riHdrNo) {
            $data = $this->findDataRI($this->riHdrNo);
            if ($data) {
                $this->dataDaftarRi = $data;
                $this->regNo = $data['regNo'] ?? null;
                $this->pascaList = $data[$this->jsonKey] ?? [];
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
        $this->pascaList = $this->dataDaftarRi[$this->jsonKey];
        $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $this->disabled;
        $this->incrementVersion('modal-pasca-anestesi-ri');

        $this->dispatch('open-modal', name: "rm-pasca-anestesi-ri-{$this->riHdrNo}");
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: "rm-pasca-anestesi-ri-{$this->riHdrNo}");
    }

    /* ===============================
     | VALIDATION
     =============================== */
    protected function rules(): array
    {
        return [
            'newForm.jamMasuk' => 'required|string|max:20',
            'newForm.jamKeluar' => 'nullable|string|max:20',
            'newForm.keadaanUmum' => 'nullable|string|max:300',
            'newForm.jenisAnestesi' => 'required|string',
            'newForm.aldrete.kesadaran' => 'required|in:0,1,2',
            'newForm.aldrete.pernafasan' => 'required|in:0,1,2',
            'newForm.aldrete.sirkulasi' => 'required|in:0,1,2',
            'newForm.aldrete.aktivitas' => 'required|in:0,1,2',
            'newForm.aldrete.warnaKulit' => 'required|in:0,1,2',
            'newForm.bromage' => 'required_if:newForm.jenisAnestesi,Regional / Spinal|nullable|in:0,1,2,3',
            'newForm.skalaNyeri' => 'nullable|integer|min:0|max:10',
            'newForm.rekomendasi' => 'required|string',
            'newForm.keteranganRekomendasi' => 'nullable|string|max:300',
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'required_if' => ':attribute wajib diisi untuk anestesi regional/spinal.',
            'in' => ':attribute tidak valid.',
            'integer' => ':attribute harus angka.',
            'max' => ':attribute maksimal :max.',
            'min' => ':attribute minimal :min.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'newForm.jamMasuk' => 'Jam masuk RR',
            'newForm.jenisAnestesi' => 'Jenis anestesi',
            'newForm.aldrete.kesadaran' => 'Aldrete — Kesadaran',
            'newForm.aldrete.pernafasan' => 'Aldrete — Pernafasan',
            'newForm.aldrete.sirkulasi' => 'Aldrete — Sirkulasi',
            'newForm.aldrete.aktivitas' => 'Aldrete — Aktivitas',
            'newForm.aldrete.warnaKulit' => 'Aldrete — Warna kulit',
            'newForm.bromage' => 'Bromage score',
            'newForm.skalaNyeri' => 'Skala nyeri',
            'newForm.rekomendasi' => 'Rekomendasi',
        ];
    }

    /* ===============================
     | SET JAM SEKARANG
     =============================== */
    public function setJamMasukSekarang(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->newForm['jamMasuk'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    public function setJamKeluarSekarang(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->newForm['jamKeluar'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /* ===============================
     | HELPER — status & bentuk entri
     =============================== */
    // Entri dianggap FINAL/terkunci bila flag finalized true; entri lama (tanpa flag) yang sudah
    // ada TTD dianggap final (kompatibilitas data lama).
    public function entryIsFinal(array $e): bool
    {
        return array_key_exists('finalized', $e) ? (bool) $e['finalized'] : !empty($e['ttd']);
    }

    // Susun array entri dari state form. $key = createdAt (kunci stabil); $finalized = status kunci.
    private function buildEntry(string $key, bool $finalized): array
    {
        return [
            'jamMasuk' => $this->newForm['jamMasuk'] ?? '',
            'jamKeluar' => $this->newForm['jamKeluar'] ?? '',
            'keadaanUmum' => $this->newForm['keadaanUmum'] ?? '',
            'td' => $this->newForm['td'] ?? '',
            'nadi' => $this->newForm['nadi'] ?? '',
            'rr' => $this->newForm['rr'] ?? '',
            'suhu' => $this->newForm['suhu'] ?? '',
            'spo2' => $this->newForm['spo2'] ?? '',
            'jenisAnestesi' => $this->newForm['jenisAnestesi'] ?? 'Umum',
            'aldrete' => [
                'kesadaran' => $this->newForm['aldrete']['kesadaran'] ?? '',
                'pernafasan' => $this->newForm['aldrete']['pernafasan'] ?? '',
                'sirkulasi' => $this->newForm['aldrete']['sirkulasi'] ?? '',
                'aktivitas' => $this->newForm['aldrete']['aktivitas'] ?? '',
                'warnaKulit' => $this->newForm['aldrete']['warnaKulit'] ?? '',
            ],
            'totalAldrete' => $this->totalAldrete(),
            'bromage' => $this->newForm['bromage'] ?? '',
            'skalaNyeri' => $this->newForm['skalaNyeri'] ?? '',
            'rekomendasi' => $this->newForm['rekomendasi'] ?? '',
            'keteranganRekomendasi' => $this->newForm['keteranganRekomendasi'] ?? '',
            'ttd' => $this->newForm['ttd'] ?? '',
            'ttdCode' => $this->newForm['ttdCode'] ?? '',
            'ttdDate' => $this->newForm['ttdDate'] ?? '',
            'createdAt' => $key,
            'finalized' => $finalized,
        ];
    }

    // Cek: minimal salah satu data inti terisi (untuk simpan draft yang lebih longgar).
    private function adaIntiPasca(): bool
    {
        if (filled($this->newForm['jamMasuk'] ?? null)) {
            return true;
        }
        foreach (($this->newForm['aldrete'] ?? []) as $v) {
            if (filled($v)) {
                return true;
            }
        }
        return collect(['td', 'nadi', 'rr', 'suhu', 'spo2', 'keadaanUmum'])
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
            $this->pascaList = $fresh[$this->jsonKey];

            $this->appendAdminLogRI((int) $this->riHdrNo, $logVerb . ' Monitoring Pasca Anestesi — Aldrete ' . ($entry['totalAldrete'] ?? '-') . '/10 — ' . ($entry['jamMasuk'] ?: '-') . ' (' . $key . ')', 'MR');
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
        if (!$this->adaIntiPasca()) {
            $this->dispatch('toast', type: 'error', message: 'Isi minimal Jam Masuk RR, tanda vital, atau salah satu skor Aldrete.');
            return;
        }

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, false, 'Simpan draft');
            $this->editingKey = $key; // lanjut edit entri yang sama, tidak buat duplikat
            $this->incrementVersion('modal-pasca-anestesi-ri');
            $this->dispatch('toast', type: 'success', message: 'Draft tersimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan draft: ' . $e->getMessage());
        }
    }

    /* ===============================
     | TTD PETUGAS RR = FINALIZE (kunci entri)
     | Validasi penuh → stempel nama user login → kunci entri.
     =============================== */
    public function setTtd(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }

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
            $this->incrementVersion('modal-pasca-anestesi-ri');
            $this->dispatch('toast', type: 'success', message: 'Monitoring ditandatangani & terkunci.');
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
            if (is_array($v)) {
                foreach ($v as $sk => $sv) {
                    $this->newForm[$k][$sk] = $entry[$k][$sk] ?? '';
                }
            } else {
                $this->newForm[$k] = $entry[$k] ?? '';
            }
        }
        $this->editingKey = $key;
        $this->resetValidation();
        $this->incrementVersion('modal-pasca-anestesi-ri');
    }

    public function editEntry(string $key): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        $entry = collect($this->pascaList)->firstWhere('createdAt', $key);
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
        $entry = collect($this->pascaList)->firstWhere('createdAt', $key);
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
        $this->incrementVersion('modal-pasca-anestesi-ri');
    }

    /* ===============================
     | CETAK (inline stream PDF, per-entri)
     =============================== */
    public function cetak(string $createdAt)
    {
        $entry = collect($this->pascaList)->firstWhere('createdAt', $createdAt);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Data monitoring tidak ditemukan.');
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
                'aldreteItems' => $this->aldreteItems,
                'bromageOptions' => $this->bromageOptions,
                'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
            ]);

            set_time_limit(300);

            $pdf = Pdf::loadView('pages.components.modul-dokumen.r-i.pasca-anestesi-ri.cetak-pasca-anestesi-ri-print', ['data' => $data])->setPaper('A4');

            $this->dispatch('toast', type: 'success', message: 'Berhasil mencetak monitoring pasca anestesi.');
            return response()->streamDownload(fn() => print $pdf->output(), 'pasca-anestesi-ri-' . ($pasien['regNo'] ?? $this->riHdrNo) . '.pdf');
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
                $this->pascaList = $fresh[$this->jsonKey];

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Monitoring Pasca Anestesi — ' . $createdAt, 'MR');
            });

            // Jika entri yang dihapus sedang di form, kosongkan form.
            if ($this->editingKey === $createdAt) {
                $this->cancelEdit();
            }

            $this->incrementVersion('modal-pasca-anestesi-ri');
            $this->dispatch('toast', type: 'success', message: 'Monitoring pasca anestesi berhasil dihapus.');
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
            'jamMasuk' => '',
            'jamKeluar' => '',
            'keadaanUmum' => '',
            'td' => '',
            'nadi' => '',
            'rr' => '',
            'suhu' => '',
            'spo2' => '',
            'jenisAnestesi' => 'Umum',
            'aldrete' => [
                'kesadaran' => '',
                'pernafasan' => '',
                'sirkulasi' => '',
                'aktivitas' => '',
                'warnaKulit' => '',
            ],
            'bromage' => '',
            'skalaNyeri' => '',
            'rekomendasi' => '',
            'keteranganRekomendasi' => '',
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
        $this->pascaList = [];
        $this->resetNewForm();
        $this->editingKey = null;
        $this->viewOnly = false;
    }
};
?>

<div>
    {{-- ══ SUMMARY CARD (inline) ══ --}}
    @php $paCount = count($pascaList ?? []); @endphp

    <div class="p-5 border shadow-sm bg-canvas border-hairline rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex-1 space-y-2">
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">Monitoring Pasca Anestesi (RR)</h3>
                    @if ($paCount > 0)
                        <x-badge variant="success">{{ $paCount }} catatan</x-badge>
                    @else
                        <x-badge variant="warning">Belum ada</x-badge>
                    @endif
                </div>
                <p class="text-sm text-muted dark:text-gray-400">
                    Pemulihan di Recovery Room (PAB 6.1 / RM 55): skor <span class="font-medium">Aldrete</span> (anestesi
                    umum) &amp; <span class="font-medium">Bromage</span> (regional/spinal), skala nyeri, rekomendasi
                    pemindahan pasien. Tiap entri = 1 catatan pemantauan.
                </p>
            </div>

            <div class="flex items-center gap-2 shrink-0">
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

        @if ($paCount > 0)
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm border border-hairline rounded-lg dark:border-gray-700">
                    <thead class="bg-surface-soft dark:bg-gray-800">
                        <tr class="text-left text-muted dark:text-gray-300">
                            <th class="px-3 py-2 border-b">Jam Masuk</th>
                            <th class="px-3 py-2 border-b">Aldrete</th>
                            <th class="px-3 py-2 border-b">Petugas (TTD)</th>
                            <th class="px-3 py-2 text-center border-b">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (array_reverse($pascaList) as $e)
                            <tr class="border-b border-hairline dark:border-gray-700">
                                <td class="px-3 py-2 font-medium text-ink dark:text-gray-200">{{ $e['jamMasuk'] ?: ($e['createdAt'] ?? '-') }}</td>
                                <td class="px-3 py-2 text-muted dark:text-gray-400">{{ $e['totalAldrete'] ?? '-' }}/10</td>
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
    <x-modal name="rm-pasca-anestesi-ri-{{ $riHdrNo ?? 'init' }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal-pasca-anestesi-ri', [$riHdrNo ?? 'new']) }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-hairline dark:border-gray-700 bg-surface-soft">
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-sky-500/10">
                                <svg class="w-6 h-6 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M3 12h4l2 5 4-10 2 5h6" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-2xl font-semibold text-ink dark:text-gray-100">Monitoring Pasca Anestesi
                                </h2>
                                <p class="mt-0.5 text-base text-muted dark:text-gray-400">
                                    PAB 6.1 / RM 55 — Aldrete &amp; Bromage di Recovery Room · tiap entri = 1 catatan
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 mt-3">
                            <x-badge variant="brand">Rawat Inap</x-badge>
                            @if (count($pascaList) > 0)
                                <x-badge variant="info">{{ count($pascaList) }} tersimpan</x-badge>
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
                <div class="max-w-5xl mx-auto space-y-4">

                    <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                        wire:key="pa-ri-display-pasien-{{ $riHdrNo ?? 'init' }}" />

                    @php $formRO = $isFormLocked || $viewOnly; @endphp

                    @if ($isFormLocked)
                        <div class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium border rounded-lg text-amber-800 bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-300">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            Mode tampilan saja (read-only) — pasien sudah pulang / EMR terkunci.
                        </div>
                    @endif

                    @if ($viewOnly)
                        <div class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium border rounded-lg text-sky-700 bg-sky-50 border-sky-200 dark:bg-sky-900/20 dark:border-sky-600 dark:text-sky-300">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            Menampilkan entri terkunci <strong>{{ $editingKey }}</strong> (hanya lihat) — klik <strong>Selesai Melihat</strong> untuk kembali ke form entri baru.
                        </div>
                    @elseif ($editingKey && !$isFormLocked)
                        <div class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium border rounded-lg text-brand-green bg-brand-lime/10 border-brand-lime/40 dark:text-brand-lime dark:bg-brand-lime/5">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Sedang melanjutkan entri <strong>{{ $editingKey }}</strong> — <strong>Simpan Perubahan</strong> menyimpan ke entri ini; klik <strong>Entri Baru</strong> untuk menambah catatan lain.
                        </div>
                    @endif

                    {{-- ── FORM ENTRI (1 catatan pemantauan) ── --}}
                    <fieldset @disabled($formRO)
                        class="p-6 space-y-6 bg-canvas border border-hairline shadow-sm sm:p-8 rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                        {{-- ══ JAM & KEADAAN ══ --}}
                        <section class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <x-input-label value="Jam Masuk RR *" class="mb-1" />
                                <div class="flex items-center gap-2">
                                    <x-text-input wire:model.live="newForm.jamMasuk" placeholder="dd/mm/yyyy HH:mm:ss"
                                        :error="$errors->has('newForm.jamMasuk')" class="w-full" />
                                    @if (!$formRO)
                                        <x-now-button wire:click="setJamMasukSekarang" />
                                    @endif
                                </div>
                                <x-input-error :messages="$errors->get('newForm.jamMasuk')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Jam Keluar RR" class="mb-1" />
                                <div class="flex items-center gap-2">
                                    <x-text-input wire:model.live="newForm.jamKeluar" :error="$errors->has('newForm.jamKeluar')"
                                        placeholder="dd/mm/yyyy HH:mm:ss" class="w-full" />
                                    @if (!$formRO)
                                        <x-now-button wire:click="setJamKeluarSekarang" />
                                    @endif
                                </div>
                            </div>
                        </section>

                        <section>
                            <x-input-label value="Keadaan Umum" class="mb-1" />
                            <x-text-input wire:model.live="newForm.keadaanUmum" :error="$errors->has('newForm.keadaanUmum')"
                                placeholder="cth: Sadar penuh, nafas spontan adekuat" class="w-full" />
                        </section>

                        {{-- ══ TTV SAAT MASUK RR ══ --}}
                        <section class="pt-6 border-t border-hairline dark:border-gray-700">
                            <h3 class="mb-3 text-base font-semibold text-ink dark:text-gray-200">Tanda Vital (saat masuk
                                RR)</h3>
                            <div class="grid grid-cols-2 gap-4 md:grid-cols-5">
                                <div>
                                    <x-input-label value="TD (mmHg)" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.td" :error="$errors->has('newForm.td')" placeholder="120/80"
                                        class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Nadi" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.nadi" :error="$errors->has('newForm.nadi')" class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="RR" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.rr" :error="$errors->has('newForm.rr')" class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Suhu (°C)" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.suhu" :error="$errors->has('newForm.suhu')" class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="SpO2 (%)" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.spo2" :error="$errors->has('newForm.spo2')" class="w-full" />
                                </div>
                            </div>
                        </section>

                        {{-- ══ JENIS ANESTESI ══ --}}
                        <section class="pt-6 space-y-3 border-t border-hairline dark:border-gray-700">
                            <x-input-label value="Jenis Anestesi *" class="mb-1" />
                            <div class="flex flex-wrap gap-2">
                                @foreach ($jenisAnestesiOptions as $opt)
                                    <x-radio-button :label="$opt" :value="$opt" name="jenisAnestesi"
                                        wire:model.live="newForm.jenisAnestesi" />
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('newForm.jenisAnestesi')" class="mt-1" />
                        </section>

                        {{-- ══ ALDRETE SCORE ══ --}}
                        <section class="pt-6 space-y-3 border-t border-hairline dark:border-gray-700">
                            <div class="flex items-center justify-between">
                                <h3 class="text-base font-semibold text-ink dark:text-gray-200">Aldrete Score</h3>
                                <span
                                    class="px-3 py-1 text-base font-bold rounded-lg {{ $this->totalAldrete >= 8 ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' }}">
                                    Total: {{ $this->totalAldrete }}/10
                                </span>
                            </div>
                            <div class="space-y-4">
                                @foreach ($aldreteItems as $key => $item)
                                    <div>
                                        <x-input-label :value="$item['label'] . ' *'" class="mb-1" />
                                        <div class="flex flex-wrap gap-2">
                                            @foreach ($item['opsi'] as $skor => $teks)
                                                <x-radio-button :label="$skor . ' — ' . $teks" :value="$skor"
                                                    name="aldrete_{{ $key }}"
                                                    wire:model.live="newForm.aldrete.{{ $key }}" />
                                            @endforeach
                                        </div>
                                        <x-input-error :messages="$errors->get('newForm.aldrete.' . $key)" class="mt-1" />
                                    </div>
                                @endforeach
                            </div>
                            <p class="text-sm text-muted-soft dark:text-gray-500">
                                Kriteria: total Aldrete ≥ 8 → boleh pindah ke ruangan rawat inap (anestesi umum).
                            </p>
                        </section>

                        {{-- ══ BROMAGE SCORE (regional/spinal) ══ --}}
                        @if (($newForm['jenisAnestesi'] ?? '') === 'Regional / Spinal')
                            <section class="pt-6 space-y-3 border-t border-hairline dark:border-gray-700">
                                <h3 class="text-base font-semibold text-ink dark:text-gray-200">Bromage Score
                                    (regional/spinal)</h3>
                                <div class="flex flex-col gap-2">
                                    @foreach ($bromageOptions as $skor => $teks)
                                        <x-radio-button :label="$skor . ' — ' . $teks" :value="$skor" name="bromage"
                                            wire:model.live="newForm.bromage" />
                                    @endforeach
                                </div>
                                <x-input-error :messages="$errors->get('newForm.bromage')" class="mt-1" />
                                <p class="text-sm text-muted-soft dark:text-gray-500">
                                    Kriteria pemindahan pasien regional mengikuti pemulihan blok motorik (Bromage) sesuai
                                    kebijakan RS.
                                </p>
                            </section>
                        @endif

                        {{-- ══ SKALA NYERI & REKOMENDASI ══ --}}
                        <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                            <div class="max-w-xs">
                                <x-input-label value="Skala Nyeri (0–10)" class="mb-1" />
                                <x-text-input type="number" wire:model.live="newForm.skalaNyeri" :error="$errors->has('newForm.skalaNyeri')"
                                    class="w-full" />
                                <x-input-error :messages="$errors->get('newForm.skalaNyeri')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Rekomendasi *" class="mb-1" />
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($rekomendasiOptions as $opt)
                                        <x-radio-button :label="$opt" :value="$opt" name="rekomendasi"
                                            wire:model.live="newForm.rekomendasi" />
                                    @endforeach
                                </div>
                                <x-input-error :messages="$errors->get('newForm.rekomendasi')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Keterangan Rekomendasi" class="mb-1" />
                                <x-text-input wire:model.live="newForm.keteranganRekomendasi" :error="$errors->has('newForm.keteranganRekomendasi')"
                                    class="w-full" />
                            </div>
                        </section>

                        {{-- ══ TTD PETUGAS RR & KUNCI ══ --}}
                        <x-signature.ttd-petugas :ttd="$newForm['ttd']" :code="$newForm['ttdCode'] ?? ''"
                            :date="$newForm['ttdDate'] ?? ''" :locked="$formRO" sign="setTtd" clear="clearTtd"
                            title="Tanda Tangan Petugas Recovery Room"
                            nameLabel="Petugas (Perawat RR)" dateLabel="Waktu TTD"
                            signLabel="TTD Petugas &amp; Kunci" clearLabel="Batal TTD" />
                        @if (!$formRO)
                            <p class="-mt-2 text-xs text-center text-muted">Menandatangani = mengunci monitoring ini.</p>
                        @endif
                    </fieldset>

                    {{-- ── DAFTAR CATATAN TERSIMPAN (expandable) ── --}}
                    <x-border-form title="Catatan Monitoring Tersimpan">
                        @if (count($pascaList ?? []))
                            <div class="flex items-center justify-between gap-2 mb-3">
                                <span class="text-xs italic text-muted-soft">Klik baris untuk lihat detail lengkap</span>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm border border-hairline rounded-lg dark:border-gray-700">
                                    <thead class="bg-surface-soft dark:bg-gray-800">
                                        <tr class="text-left text-sm font-semibold tracking-wide uppercase text-muted dark:text-gray-300">
                                            <th class="w-8 px-2 py-3 border-b"></th>
                                            <th class="px-4 py-3 border-b">Jam Masuk</th>
                                            <th class="px-4 py-3 border-b">Aldrete</th>
                                            <th class="px-4 py-3 border-b">Petugas (TTD)</th>
                                            <th class="px-4 py-3 text-center border-b">Status</th>
                                            <th class="px-4 py-3 text-center border-b">Aksi</th>
                                        </tr>
                                    </thead>
                                    @foreach (array_reverse($pascaList) as $entry)
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
                                                    {{ $entry['jamMasuk'] ?: ($rowKey ?: '-') }}
                                                </td>
                                                <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">
                                                    {{ $entry['totalAldrete'] ?? '-' }}/10
                                                    @if (($entry['jenisAnestesi'] ?? '') === 'Regional / Spinal')
                                                        <span class="text-sm text-muted-soft">· Bromage {{ $entry['bromage'] ?? '-' }}</span>
                                                    @endif
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
                                                        <x-secondary-button type="button" wire:click="cetak('{{ $rowKey }}')" wire:loading.attr="disabled" wire:target="cetak('{{ $rowKey }}')" class="gap-1.5" title="Cetak monitoring">
                                                            <span wire:loading.remove wire:target="cetak('{{ $rowKey }}')" class="flex items-center gap-1.5">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                                </svg>
                                                                Cetak
                                                            </span>
                                                            <span wire:loading wire:target="cetak('{{ $rowKey }}')" class="flex items-center gap-1.5"><x-loading class="w-4 h-4" /></span>
                                                        </x-secondary-button>
                                                        @if (!$isFormLocked)
                                                            <x-outline-button type="button" wire:click.prevent="hapus('{{ $rowKey }}')" wire:confirm="Yakin hapus monitoring ini?"
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
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Jam Masuk RR</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['jamMasuk'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Jam Keluar RR</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['jamKeluar'] ?: '-' }}</dd>
                                                        </div>
                                                        <div class="md:col-span-2">
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Keadaan Umum</dt>
                                                            <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['keadaanUmum'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Tekanan Darah</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['td'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Nadi</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['nadi'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">RR</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['rr'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Suhu (°C)</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['suhu'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">SpO2 (%)</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['spo2'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Jenis Anestesi</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['jenisAnestesi'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Total Aldrete</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['totalAldrete'] ?? '-' }}/10</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Aldrete — Kesadaran</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['aldrete']['kesadaran'] ?? '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Aldrete — Pernafasan</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['aldrete']['pernafasan'] ?? '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Aldrete — Sirkulasi</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['aldrete']['sirkulasi'] ?? '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Aldrete — Aktivitas</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['aldrete']['aktivitas'] ?? '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Aldrete — Warna Kulit</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['aldrete']['warnaKulit'] ?? '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Bromage Score</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['bromage'] !== '' && $entry['bromage'] !== null ? $entry['bromage'] : '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Skala Nyeri</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['skalaNyeri'] !== '' && $entry['skalaNyeri'] !== null ? $entry['skalaNyeri'] : '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Rekomendasi</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['rekomendasi'] ?: '-' }}</dd>
                                                        </div>
                                                        <div class="md:col-span-2">
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Keterangan Rekomendasi</dt>
                                                            <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $entry['keteranganRekomendasi'] ?: '-' }}</dd>
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
                        @else
                            <p class="text-sm text-muted dark:text-gray-400">Belum ada catatan monitoring tersimpan.</p>
                        @endif
                    </x-border-form>

                </div>
            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 px-6 py-3 border-t shrink-0 bg-surface-soft border-hairline dark:bg-gray-900 dark:border-gray-700">
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
                                    title="Kosongkan form untuk menambah catatan lain — entri yang sudah tersimpan tidak berubah">
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
