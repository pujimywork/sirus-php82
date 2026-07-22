<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/laporan-persalinan-ri/rm-laporan-persalinan-ri-actions.blade.php
// Dokumen VK/Kebidanan — Laporan Tindakan Persalinan (RM 44.c).
// Pola: multi-entri (Draft + Lanjut Isi + TTD-Kunci + Lihat read-only + tabel expandable),
// disimpan ke datadaftarri_json. Tiap entri = 1 laporan persalinan; cetak = SATU lembar per entri.
// Kunci entri stabil = createdAt. TTD = stempel nama user login (ttdSaya = FINALIZE/kunci), tanpa TTD gambar.
// [scan] = field dari form fisik; [akr] = tambahan akreditasi (PONEK / Prognas 1).

use Livewire\Component;
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
    protected array $renderAreas = ['modal-laporan-persalinan-ri'];

    /** Key penyimpanan di datadaftarri_json */
    private string $jsonKey = 'laporanPersalinanRI';

    public array $newForm = [
        // Jenis Partus
        'jenisPartus'         => '',   // Partus Spontan | Partus Buatan
        'indikasi'            => '',
        // BAYI
        'bayiLahirTgl'        => '',
        'bayiLahirJam'        => '',
        'bayiBb'              => '',   // gr
        'bayiPb'              => '',   // cm
        'bayiApgar'           => '',   // mis 7-8-9
        'bayiResusitasi'      => '',   // Ya | Tidak
        'bayiJenisKelamin'    => '',   // Laki-laki | Perempuan
        'bayiKeadaan'         => '',   // Hidup | Mati
        'ukKepalaBt'          => '',   // cm
        'ukKepalaBp'          => '',   // cm
        'ukKepalaFo'          => '',   // cm
        'ukKepalaMo'          => '',   // cm
        'ukKepalaOb'          => '',   // cm
        'caputSuksedanium'    => '',
        'cephalHematoma'      => '',
        'atresiaAni'          => '',
        'bayiLain'            => '',
        // PLASENTA
        'plasentaLahirTgl'    => '',
        'plasentaLahirJam'    => '',
        'plasentaCara'        => '',   // Spontan | Manual
        'plasentaJenis'       => '',   // Lengkap | Tidak Lengkap
        'plasentaBerat'       => '',   // gr
        'plasentaDiameter'    => '',   // cm
        // TALI PUSAT
        'taliPusatInsersi'    => '',
        'taliPusatPanjang'    => '',   // cm
        // SELAPUT JANIN
        'selaputKeadaan'      => '',   // Lengkap | Tidak Lengkap
        'selaputRobekan'      => '',
        'selaputLain'         => '',
        // PERLUKAAN JALAN LAHIR
        'lukaPerineum'        => '',
        'episiotomi'          => '',   // Ya | Tidak
        'rupturaPerinei'      => '',   // Tidak | Tk I | Tk II | Tk III
        'lukaVagina'          => '',
        'lukaServiks'         => '',
        // KALA IV
        'kalaIvHb'            => '',
        'kalaIvSuhu'         => '',
        'kalaIvTd'           => '',
        'kalaIvNadi'         => '',
        'kalaIvRr'           => '',
        'kalaIvTfu'          => '',
        'kalaIvKontraksi'    => '',
        'perdarahanKalaIii'  => '',   // cc
        'perdarahanKalaIv'   => '',   // cc
        // TAMBAHAN AKREDITASI (PONEK / Prognas 1)
        'imdDilakukan'       => '',   // [akr] Ya | Tidak
        'imdJam'             => '',   // [akr]
        'imdDurasiMenit'     => '',   // [akr]
        'imdAlasanTidak'     => '',   // [akr]
        'rawatGabung'        => '',   // [akr] Ya | Tidak
        'asiKonseling'       => '',   // [akr] Ya | Tidak
        'pmkDilakukan'       => '',   // [akr] Ya | Tidak | Tidak Perlu (BBLR)
        // Penutup
        'ttd'                => '',   // nama penanda-tangan (myuser_name)
        'ttdDate'            => '',   // tgl/jam TTD (d/m/Y H:i:s)
        'ttdCode'            => '',   // myuser_code penanda-tangan
    ];

    public array $entriList = [];

    // Kunci entri yang sedang diedit (createdAt = kunci stabil, di-set saat entri pertama dibuat).
    // null = sedang membuat entri baru.
    public ?string $editingKey = null;

    // true = entri terkunci sedang ditampilkan di form dalam mode read-only (lihat saja, tak bisa edit).
    public bool $viewOnly = false;

    protected function rules(): array
    {
        return [];
    }

    protected function messages(): array
    {
        return [];
    }

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(?string $riHdrNo = null, bool $disabled = false): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-laporan-persalinan-ri']);

        if ($this->riHdrNo) {
            $data = $this->findDataRI($this->riHdrNo);
            if ($data) {
                $this->dataDaftarRi = $data;
                $this->regNo = $data['regNo'] ?? null;
                $this->entriList = $data[$this->jsonKey] ?? [];
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
        $this->entriList = $this->dataDaftarRi[$this->jsonKey];
        $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $this->disabled;

        $this->incrementVersion('modal-laporan-persalinan-ri');
        $this->dispatch('open-modal', name: 'laporan-persalinan-ri');
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: 'laporan-persalinan-ri');
    }

    /* ===============================
     | SET TANGGAL/JAM SEKARANG
     =============================== */
    public function setTglJamSekarang(string $field): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->newForm[$field] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
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
            'jenisPartus'      => $this->newForm['jenisPartus'] ?? '',
            'indikasi'         => $this->newForm['indikasi'] ?? '',
            'bayiLahirTgl'     => $this->newForm['bayiLahirTgl'] ?? '',
            'bayiLahirJam'     => $this->newForm['bayiLahirJam'] ?? '',
            'bayiBb'           => $this->newForm['bayiBb'] ?? '',
            'bayiPb'           => $this->newForm['bayiPb'] ?? '',
            'bayiApgar'        => $this->newForm['bayiApgar'] ?? '',
            'bayiResusitasi'   => $this->newForm['bayiResusitasi'] ?? '',
            'bayiJenisKelamin' => $this->newForm['bayiJenisKelamin'] ?? '',
            'bayiKeadaan'      => $this->newForm['bayiKeadaan'] ?? '',
            'ukKepalaBt'       => $this->newForm['ukKepalaBt'] ?? '',
            'ukKepalaBp'       => $this->newForm['ukKepalaBp'] ?? '',
            'ukKepalaFo'       => $this->newForm['ukKepalaFo'] ?? '',
            'ukKepalaMo'       => $this->newForm['ukKepalaMo'] ?? '',
            'ukKepalaOb'       => $this->newForm['ukKepalaOb'] ?? '',
            'caputSuksedanium' => $this->newForm['caputSuksedanium'] ?? '',
            'cephalHematoma'   => $this->newForm['cephalHematoma'] ?? '',
            'atresiaAni'       => $this->newForm['atresiaAni'] ?? '',
            'bayiLain'         => $this->newForm['bayiLain'] ?? '',
            'plasentaLahirTgl' => $this->newForm['plasentaLahirTgl'] ?? '',
            'plasentaLahirJam' => $this->newForm['plasentaLahirJam'] ?? '',
            'plasentaCara'     => $this->newForm['plasentaCara'] ?? '',
            'plasentaJenis'    => $this->newForm['plasentaJenis'] ?? '',
            'plasentaBerat'    => $this->newForm['plasentaBerat'] ?? '',
            'plasentaDiameter' => $this->newForm['plasentaDiameter'] ?? '',
            'taliPusatInsersi' => $this->newForm['taliPusatInsersi'] ?? '',
            'taliPusatPanjang' => $this->newForm['taliPusatPanjang'] ?? '',
            'selaputKeadaan'   => $this->newForm['selaputKeadaan'] ?? '',
            'selaputRobekan'   => $this->newForm['selaputRobekan'] ?? '',
            'selaputLain'      => $this->newForm['selaputLain'] ?? '',
            'lukaPerineum'     => $this->newForm['lukaPerineum'] ?? '',
            'episiotomi'       => $this->newForm['episiotomi'] ?? '',
            'rupturaPerinei'   => $this->newForm['rupturaPerinei'] ?? '',
            'lukaVagina'       => $this->newForm['lukaVagina'] ?? '',
            'lukaServiks'      => $this->newForm['lukaServiks'] ?? '',
            'kalaIvHb'         => $this->newForm['kalaIvHb'] ?? '',
            'kalaIvSuhu'       => $this->newForm['kalaIvSuhu'] ?? '',
            'kalaIvTd'         => $this->newForm['kalaIvTd'] ?? '',
            'kalaIvNadi'       => $this->newForm['kalaIvNadi'] ?? '',
            'kalaIvRr'         => $this->newForm['kalaIvRr'] ?? '',
            'kalaIvTfu'        => $this->newForm['kalaIvTfu'] ?? '',
            'kalaIvKontraksi'  => $this->newForm['kalaIvKontraksi'] ?? '',
            'perdarahanKalaIii' => $this->newForm['perdarahanKalaIii'] ?? '',
            'perdarahanKalaIv' => $this->newForm['perdarahanKalaIv'] ?? '',
            'imdDilakukan'     => $this->newForm['imdDilakukan'] ?? '',
            'imdJam'           => $this->newForm['imdJam'] ?? '',
            'imdDurasiMenit'   => $this->newForm['imdDurasiMenit'] ?? '',
            'imdAlasanTidak'   => $this->newForm['imdAlasanTidak'] ?? '',
            'rawatGabung'      => $this->newForm['rawatGabung'] ?? '',
            'asiKonseling'     => $this->newForm['asiKonseling'] ?? '',
            'pmkDilakukan'     => $this->newForm['pmkDilakukan'] ?? '',
            'ttd'              => $this->newForm['ttd'] ?? '',
            'ttdCode'          => $this->newForm['ttdCode'] ?? '',
            'ttdDate'          => $this->newForm['ttdDate'] ?? '',
            'createdAt'        => $key,
            'finalized'        => $finalized,
        ];
    }

    // Cek: minimal salah satu isian inti persalinan terisi.
    private function adaIntiPersalinan(): bool
    {
        return collect(['jenisPartus', 'bayiLahirTgl', 'bayiBb', 'bayiJenisKelamin', 'bayiApgar'])
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
            $this->entriList = $fresh[$this->jsonKey];

            $this->appendAdminLogRI((int) $this->riHdrNo, $logVerb . ' Laporan Persalinan — ' . ($entry['jenisPartus'] ?: '-') . ' (' . $key . ')', 'MR');
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
        if (!$this->adaIntiPersalinan()) {
            $this->dispatch('toast', type: 'error', message: 'Isi minimal salah satu: Jenis Partus, Tgl Lahir Bayi, Berat, Jenis Kelamin, atau APGAR.');
            return;
        }

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, false, 'Simpan draft');
            $this->editingKey = $key; // lanjut edit entri yang sama, tidak buat duplikat
            $this->incrementVersion('modal-laporan-persalinan-ri');
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
    public function ttdSaya(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        if (!$this->adaIntiPersalinan()) {
            $this->dispatch('toast', type: 'error', message: 'Isi minimal salah satu: Jenis Partus, Tgl Lahir Bayi, Berat, Jenis Kelamin, atau APGAR sebelum TTD.');
            return;
        }

        // Stempel TTD petugas = user login.
        $this->newForm['ttd']     = auth()->user()->myuser_name ?? '';
        $this->newForm['ttdCode'] = auth()->user()->myuser_code ?? '';
        $this->newForm['ttdDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, true, 'Kunci (TTD)');
            $this->resetNewForm();
            $this->editingKey = null;
            $this->viewOnly = false;
            $this->incrementVersion('modal-laporan-persalinan-ri');
            $this->dispatch('toast', type: 'success', message: 'Laporan persalinan ditandatangani & terkunci.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal mengunci: ' . $e->getMessage());
        }
    }

    /** Batalkan TTD pada form (saat draft/edit, sebelum finalize benar-benar tersimpan). */
    public function hapusTtd(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            return;
        }
        $this->newForm['ttd']     = '';
        $this->newForm['ttdCode'] = '';
        $this->newForm['ttdDate'] = '';
    }

    /* ===============================
     | EDIT / LIHAT / BATAL entri
     =============================== */
    // Muat 1 entri ke form atas (dipakai edit draft & lihat entri terkunci). TANPA TTD gambar.
    private function hydrateFormFromEntry(array $entry, string $key): void
    {
        foreach ($this->newForm as $k => $v) {
            $this->newForm[$k] = $entry[$k] ?? (is_array($v) ? [] : '');
        }
        $this->editingKey = $key;
        $this->resetValidation();
        $this->incrementVersion('modal-laporan-persalinan-ri');
    }

    public function editEntry(string $key): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        $entry = collect($this->entriList)->firstWhere('createdAt', $key);
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
        $entry = collect($this->entriList)->firstWhere('createdAt', $key);
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
        $this->incrementVersion('modal-laporan-persalinan-ri');
    }

    private function resetNewForm(): void
    {
        foreach ($this->newForm as $k => $v) {
            $this->newForm[$k] = is_array($v) ? [] : '';
        }
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->dataDaftarRi = [];
        $this->entriList = [];
        $this->resetNewForm();
        $this->editingKey = null;
        $this->viewOnly = false;
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
                $fresh[$this->jsonKey] = collect($fresh[$this->jsonKey] ?? [])
                    ->reject(fn($e) => ($e['createdAt'] ?? null) === $createdAt)
                    ->values()
                    ->all();

                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
                $this->entriList = $fresh[$this->jsonKey];

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Laporan Persalinan — ' . $createdAt, 'MR');
            });

            // Jika entri yang dihapus sedang di form, kosongkan form.
            if ($this->editingKey === $createdAt) {
                $this->cancelEdit();
            }

            $this->incrementVersion('modal-laporan-persalinan-ri');
            $this->dispatch('toast', type: 'success', message: 'Entri dihapus.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    /* ===============================
     | CETAK (per-ENTRI: 1 laporan = 1 lembar)
     =============================== */
    public function cetak(string $createdAt)
    {
        $entry = collect($this->entriList)->firstWhere('createdAt', $createdAt);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Data laporan persalinan tidak ditemukan.');
            return;
        }

        try {
            $identitasRs = DB::table('rsmst_identitases')
                ->select('int_name', 'int_phone1', 'int_phone2', 'int_fax', 'int_address', 'int_city')->first();
            $pasienData = $this->findDataMasterPasien($this->regNo ?? '');
            $pasien = $pasienData['pasien'] ?? [];

            if (!empty($pasien['tglLahir'])) {
                try {
                    $pasien['thn'] = Carbon::createFromFormat('d/m/Y', $pasien['tglLahir'])
                        ->diff(Carbon::now(config('app.timezone')))->format('%y Thn, %m Bln %d Hr');
                } catch (\Throwable) {
                    $pasien['thn'] = '-';
                }
            }

            // TTD (myuser_code -> myuser_ttd_image) untuk stempel di cetakan
            $ttdPath = null;
            $ttdCode = $entry['ttdCode'] ?? null;
            if ($ttdCode) {
                $ttdImg = DB::table('users')->where('myuser_code', $ttdCode)->value('myuser_ttd_image');
                if (!empty($ttdImg) && file_exists(public_path('storage/' . $ttdImg))) {
                    $ttdPath = public_path('storage/' . $ttdImg);
                }
            }

            $data = array_merge($pasien, [
                'ttdPath'      => $ttdPath,
                'dataRi'       => $this->dataDaftarRi,
                'form'         => $entry,
                'identitasRs'  => $identitasRs,
                'tglCetak'     => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
            ]);

            set_time_limit(300);
            $pdf = Pdf::loadView('pages.components.modul-dokumen.r-i.laporan-persalinan-ri.cetak-laporan-persalinan-ri-print', ['data' => $data])->setPaper('A4');

            return response()->streamDownload(fn() => print $pdf->output(), 'laporan-persalinan-' . ($pasien['regNo'] ?? $this->riHdrNo) . '.pdf');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cetak: ' . $e->getMessage());
        }
    }
};
?>

<div>
    {{-- ══ SUMMARY CARD (inline di tab) ══ --}}
    @php $lpCount = count($entriList ?? []); @endphp
    <div class="p-5 border shadow-sm bg-canvas border-hairline rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex-1 space-y-2">
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">Laporan Tindakan Persalinan</h3>
                    @if ($lpCount > 0)
                        <x-badge variant="success">{{ $lpCount }} entri</x-badge>
                    @else
                        <x-badge variant="warning">Belum ada</x-badge>
                    @endif
                </div>
                <p class="text-sm text-muted dark:text-gray-400">
                    Laporan tindakan persalinan (RM 44.c) — jenis partus, data bayi &amp; APGAR, plasenta, tali pusat,
                    selaput janin, perlukaan jalan lahir, Kala IV, serta IMD/Rawat Gabung/ASI (PONEK/Prognas 1). Diisi Dokter.
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

        @if ($lpCount > 0)
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm border border-hairline rounded-lg dark:border-gray-700">
                    <thead class="bg-surface-soft dark:bg-gray-800">
                        <tr class="text-left text-muted dark:text-gray-300">
                            <th class="px-3 py-2 border-b">Tgl / Jam</th>
                            <th class="px-3 py-2 border-b">Jenis Partus</th>
                            <th class="px-3 py-2 border-b">Petugas (TTD)</th>
                            <th class="px-3 py-2 text-center border-b">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (array_reverse($entriList) as $e)
                            <tr class="border-b border-hairline dark:border-gray-700">
                                <td class="px-3 py-2 font-medium text-ink dark:text-gray-200">{{ $e['createdAt'] ?? '-' }}</td>
                                <td class="px-3 py-2 text-muted dark:text-gray-400">{{ $e['jenisPartus'] ?: '-' }}</td>
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
    <x-modal name="laporan-persalinan-ri" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
             wire:key="{{ $this->renderKey('modal-laporan-persalinan-ri', [$riHdrNo ?? 'new']) }}">

            {{-- HEADER --}}
            <div class="px-6 py-4 border-b shrink-0 bg-surface-soft border-hairline dark:border-gray-700">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                            <svg class="w-6 h-6 text-brand-green dark:text-brand-lime" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-ink dark:text-gray-100">Laporan Tindakan Persalinan</h2>
                            <p class="mt-0.5 text-sm text-muted dark:text-gray-400">RM 44.c — kebidanan (VK). Tiap entri = 1 laporan. Diisi Dokter.</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        @if (count($entriList) > 0)
                            <x-badge variant="info">{{ count($entriList) }} tersimpan</x-badge>
                        @endif
                        @if ($isFormLocked)
                            <x-badge variant="danger">Read Only</x-badge>
                        @endif
                        <x-icon-button color="gray" type="button" wire:click="closeModal">
                            <span class="sr-only">Tutup</span>
                            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </x-icon-button>
                    </div>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 overflow-y-auto bg-surface-soft dark:bg-gray-950/20">
                <div class="max-w-5xl mx-auto space-y-4">

                    <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                        wire:key="laporan-persalinan-display-pasien-{{ $riHdrNo }}" />

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
                            Sedang melanjutkan entri <strong>{{ $editingKey }}</strong> — <strong>Simpan Perubahan</strong> menyimpan ke entri ini; klik <strong>Entri Baru</strong> untuk menambah laporan lain.
                        </div>
                    @endif

                    {{-- ── FORM ENTRI ── --}}
                    <fieldset @disabled($formRO) class="space-y-4">

                        {{-- 1. Jenis Partus --}}
                        <x-border-form title="1. Jenis Partus">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <x-input-label value="Jenis Partus" />
                                    <x-select-input wire:model="newForm.jenisPartus" class="w-full mt-1">
                                        <option value="">—</option>
                                        <option value="Partus Spontan">Partus Spontan</option>
                                        <option value="Partus Buatan">Partus Buatan</option>
                                    </x-select-input>
                                </div>
                                <div>
                                    <x-input-label value="Indikasi" />
                                    <x-text-input wire:model="newForm.indikasi" class="w-full mt-1" placeholder="Indikasi tindakan" />
                                </div>
                            </div>
                        </x-border-form>

                        {{-- 2. Bayi --}}
                        <x-border-form title="2. Bayi">
                            <div class="space-y-3">
                                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                                    <div class="sm:col-span-2">
                                        <x-input-label value="Lahir — Tgl / Jam" />
                                        <div class="flex gap-1 mt-1">
                                            <x-text-input wire:model="newForm.bayiLahirTgl" class="w-full" placeholder="dd/mm/yyyy HH:mm:ss" />
                                            @if (!$formRO)
                                                <x-now-button wire:click="setTglJamSekarang('bayiLahirTgl')" />
                                            @endif
                                        </div>
                                    </div>
                                    <div><x-input-label value="Berat (gr)" /><x-text-input type="number" wire:model="newForm.bayiBb" class="w-full mt-1" /></div>
                                    <div><x-input-label value="Panjang (cm)" /><x-text-input type="number" wire:model="newForm.bayiPb" class="w-full mt-1" /></div>
                                    <div><x-input-label value="APGAR Score" /><x-text-input wire:model="newForm.bayiApgar" class="w-full mt-1" placeholder="mis. 7-8-9" /></div>
                                    <div>
                                        <x-input-label value="Resusitasi" />
                                        <x-select-input wire:model="newForm.bayiResusitasi" class="w-full mt-1">
                                            <option value="">—</option>
                                            <option value="Ya">Ya</option>
                                            <option value="Tidak">Tidak</option>
                                        </x-select-input>
                                    </div>
                                    <div>
                                        <x-input-label value="Jenis Kelamin" />
                                        <x-select-input wire:model="newForm.bayiJenisKelamin" class="w-full mt-1">
                                            <option value="">—</option>
                                            <option value="Laki-laki">Laki-laki</option>
                                            <option value="Perempuan">Perempuan</option>
                                        </x-select-input>
                                    </div>
                                    <div>
                                        <x-input-label value="Keadaan" />
                                        <x-select-input wire:model="newForm.bayiKeadaan" class="w-full mt-1">
                                            <option value="">—</option>
                                            <option value="Hidup">Hidup</option>
                                            <option value="Mati">Mati</option>
                                        </x-select-input>
                                    </div>
                                </div>
                                <div>
                                    <x-input-label value="Ukuran Kepala (cm)" />
                                    <div class="grid grid-cols-2 gap-3 mt-1 sm:grid-cols-5">
                                        <div><x-input-label value="BT" class="text-xs" /><x-text-input type="number" wire:model="newForm.ukKepalaBt" class="w-full mt-1" /></div>
                                        <div><x-input-label value="BP" class="text-xs" /><x-text-input type="number" wire:model="newForm.ukKepalaBp" class="w-full mt-1" /></div>
                                        <div><x-input-label value="FO" class="text-xs" /><x-text-input type="number" wire:model="newForm.ukKepalaFo" class="w-full mt-1" /></div>
                                        <div><x-input-label value="MO" class="text-xs" /><x-text-input type="number" wire:model="newForm.ukKepalaMo" class="w-full mt-1" /></div>
                                        <div><x-input-label value="OB" class="text-xs" /><x-text-input type="number" wire:model="newForm.ukKepalaOb" class="w-full mt-1" /></div>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                    <div><x-input-label value="Caput Suksedanium" /><x-text-input wire:model="newForm.caputSuksedanium" class="w-full mt-1" /></div>
                                    <div><x-input-label value="Cephal Hematoma" /><x-text-input wire:model="newForm.cephalHematoma" class="w-full mt-1" /></div>
                                    <div><x-input-label value="Atresia Ani" /><x-text-input wire:model="newForm.atresiaAni" class="w-full mt-1" /></div>
                                    <div><x-input-label value="Lain-lain" /><x-text-input wire:model="newForm.bayiLain" class="w-full mt-1" /></div>
                                </div>
                            </div>
                        </x-border-form>

                        {{-- 3. Plasenta --}}
                        <x-border-form title="3. Plasenta">
                            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                                <div class="sm:col-span-2">
                                    <x-input-label value="Lahir — Tgl / Jam" />
                                    <div class="flex gap-1 mt-1">
                                        <x-text-input wire:model="newForm.plasentaLahirTgl" class="w-full" placeholder="dd/mm/yyyy HH:mm:ss" />
                                        @if (!$formRO)
                                            <x-now-button wire:click="setTglJamSekarang('plasentaLahirTgl')" />
                                        @endif
                                    </div>
                                </div>
                                <div>
                                    <x-input-label value="Cara Lahir" />
                                    <x-select-input wire:model="newForm.plasentaCara" class="w-full mt-1">
                                        <option value="">—</option>
                                        <option value="Spontan">Spontan</option>
                                        <option value="Manual">Manual</option>
                                    </x-select-input>
                                </div>
                                <div>
                                    <x-input-label value="Jenis" />
                                    <x-select-input wire:model="newForm.plasentaJenis" class="w-full mt-1">
                                        <option value="">—</option>
                                        <option value="Lengkap">Lengkap</option>
                                        <option value="Tidak Lengkap">Tidak Lengkap</option>
                                    </x-select-input>
                                </div>
                                <div><x-input-label value="Berat (gr)" /><x-text-input type="number" wire:model="newForm.plasentaBerat" class="w-full mt-1" /></div>
                                <div><x-input-label value="Diameter (cm)" /><x-text-input type="number" wire:model="newForm.plasentaDiameter" class="w-full mt-1" /></div>
                            </div>
                        </x-border-form>

                        {{-- 4. Tali Pusat --}}
                        <x-border-form title="4. Tali Pusat">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div><x-input-label value="Insersi" /><x-text-input wire:model="newForm.taliPusatInsersi" class="w-full mt-1" placeholder="Sentral / Marginal / Velamentosa" /></div>
                                <div><x-input-label value="Panjang (cm)" /><x-text-input type="number" wire:model="newForm.taliPusatPanjang" class="w-full mt-1" /></div>
                            </div>
                        </x-border-form>

                        {{-- 5. Selaput Janin --}}
                        <x-border-form title="5. Selaput Janin">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div>
                                    <x-input-label value="Keadaan" />
                                    <x-select-input wire:model="newForm.selaputKeadaan" class="w-full mt-1">
                                        <option value="">—</option>
                                        <option value="Lengkap">Lengkap</option>
                                        <option value="Tidak Lengkap">Tidak Lengkap</option>
                                    </x-select-input>
                                </div>
                                <div><x-input-label value="Robekan" /><x-text-input wire:model="newForm.selaputRobekan" class="w-full mt-1" /></div>
                                <div><x-input-label value="Lain-lain" /><x-text-input wire:model="newForm.selaputLain" class="w-full mt-1" /></div>
                            </div>
                        </x-border-form>

                        {{-- 6. Perlukaan Jalan Lahir --}}
                        <x-border-form title="6. Perlukaan Jalan Lahir">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <div><x-input-label value="Luka Perineum" /><x-text-input wire:model="newForm.lukaPerineum" class="w-full mt-1" /></div>
                                <div>
                                    <x-input-label value="Episiotomi" />
                                    <x-select-input wire:model="newForm.episiotomi" class="w-full mt-1">
                                        <option value="">—</option>
                                        <option value="Ya">Ya</option>
                                        <option value="Tidak">Tidak</option>
                                    </x-select-input>
                                </div>
                                <div>
                                    <x-input-label value="Ruptura Perinei" />
                                    <x-select-input wire:model="newForm.rupturaPerinei" class="w-full mt-1">
                                        <option value="">—</option>
                                        <option value="Tidak">Tidak</option>
                                        <option value="Tk I">Tk I</option>
                                        <option value="Tk II">Tk II</option>
                                        <option value="Tk III">Tk III</option>
                                    </x-select-input>
                                </div>
                                <div><x-input-label value="Luka Vagina" /><x-text-input wire:model="newForm.lukaVagina" class="w-full mt-1" /></div>
                                <div><x-input-label value="Luka Serviks" /><x-text-input wire:model="newForm.lukaServiks" class="w-full mt-1" /></div>
                            </div>
                        </x-border-form>

                        {{-- 7. Kala IV --}}
                        <x-border-form title="7. Kala IV">
                            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                                <div><x-input-label value="Hb" /><x-text-input type="number" wire:model="newForm.kalaIvHb" class="w-full mt-1" /></div>
                                <div><x-input-label value="Suhu (°C)" /><x-text-input type="number" wire:model="newForm.kalaIvSuhu" class="w-full mt-1" /></div>
                                <div><x-input-label value="TD (mmHg)" /><x-text-input wire:model="newForm.kalaIvTd" class="w-full mt-1" placeholder="120/80" /></div>
                                <div><x-input-label value="Nadi (x/mnt)" /><x-text-input type="number" wire:model="newForm.kalaIvNadi" class="w-full mt-1" /></div>
                                <div><x-input-label value="RR (x/mnt)" /><x-text-input type="number" wire:model="newForm.kalaIvRr" class="w-full mt-1" /></div>
                                <div><x-input-label value="TFU" /><x-text-input wire:model="newForm.kalaIvTfu" class="w-full mt-1" /></div>
                                <div><x-input-label value="Kontraksi Uterus" /><x-text-input wire:model="newForm.kalaIvKontraksi" class="w-full mt-1" /></div>
                                <div><x-input-label value="Perdarahan Kala III (cc)" /><x-text-input type="number" wire:model="newForm.perdarahanKalaIii" class="w-full mt-1" /></div>
                                <div><x-input-label value="Perdarahan Kala IV (cc)" /><x-text-input type="number" wire:model="newForm.perdarahanKalaIv" class="w-full mt-1" /></div>
                            </div>
                        </x-border-form>

                        {{-- 8. IMD & Rawat Gabung (PONEK / Prognas 1) [akr] --}}
                        <x-border-form title="8. IMD, Rawat Gabung & ASI (PONEK / Prognas 1)">
                            {{-- [akr] --}}
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                {{-- [akr] --}}
                                <div>
                                    <x-input-label value="IMD Dilakukan" />
                                    <x-select-input wire:model="newForm.imdDilakukan" class="w-full mt-1">
                                        <option value="">—</option>
                                        <option value="Ya">Ya</option>
                                        <option value="Tidak">Tidak</option>
                                    </x-select-input>
                                </div>
                                {{-- [akr] --}}
                                <div><x-input-label value="IMD — Jam" /><x-text-input type="time" wire:model="newForm.imdJam" class="w-full mt-1" /></div>
                                {{-- [akr] --}}
                                <div><x-input-label value="IMD — Durasi (menit)" /><x-text-input type="number" wire:model="newForm.imdDurasiMenit" class="w-full mt-1" /></div>
                                {{-- [akr] --}}
                                <div><x-input-label value="Alasan bila IMD tidak" /><x-text-input wire:model="newForm.imdAlasanTidak" class="w-full mt-1" /></div>
                                {{-- [akr] --}}
                                <div>
                                    <x-input-label value="Rawat Gabung" />
                                    <x-select-input wire:model="newForm.rawatGabung" class="w-full mt-1">
                                        <option value="">—</option>
                                        <option value="Ya">Ya</option>
                                        <option value="Tidak">Tidak</option>
                                    </x-select-input>
                                </div>
                                {{-- [akr] --}}
                                <div>
                                    <x-input-label value="Konseling ASI" />
                                    <x-select-input wire:model="newForm.asiKonseling" class="w-full mt-1">
                                        <option value="">—</option>
                                        <option value="Ya">Ya</option>
                                        <option value="Tidak">Tidak</option>
                                    </x-select-input>
                                </div>
                                {{-- [akr] Perawatan Metode Kanguru untuk BBLR --}}
                                <div>
                                    <x-input-label value="PMK (Metode Kanguru) — BBLR" />
                                    <x-select-input wire:model="newForm.pmkDilakukan" class="w-full mt-1">
                                        <option value="">—</option>
                                        <option value="Ya">Ya</option>
                                        <option value="Tidak">Tidak</option>
                                        <option value="Tidak Perlu">Tidak Perlu</option>
                                    </x-select-input>
                                </div>
                            </div>
                        </x-border-form>

                        {{-- ══ TTD PETUGAS & KUNCI ══ --}}
                        <x-signature.ttd-petugas :ttd="$newForm['ttd']" :code="$newForm['ttdCode'] ?? ''"
                            :date="$newForm['ttdDate'] ?? ''" :locked="$formRO" sign="ttdSaya" clear="hapusTtd"
                            title="Tanda Tangan Petugas"
                            nameLabel="Petugas (Dokter / Bidan)" dateLabel="Waktu TTD"
                            signLabel="TTD Petugas &amp; Kunci" clearLabel="Batal TTD" />
                        @if (!$formRO)
                            <p class="-mt-2 text-xs text-center text-muted">Menandatangani = mengunci laporan persalinan ini.</p>
                        @endif
                    </fieldset>

                    {{-- ── DAFTAR ENTRI TERSIMPAN (expandable) ── --}}
                    <x-border-form title="Riwayat Laporan Persalinan Tersimpan">
                        @if (count($entriList ?? []))
                            <div class="mb-3">
                                <span class="text-xs italic text-muted-soft">Klik baris untuk lihat detail lengkap</span>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm border border-hairline rounded-lg dark:border-gray-700">
                                    <thead class="bg-surface-soft dark:bg-gray-800">
                                        <tr class="text-left text-sm font-semibold tracking-wide uppercase text-muted dark:text-gray-300">
                                            <th class="w-8 px-2 py-3 border-b"></th>
                                            <th class="px-4 py-3 border-b">Tgl / Jam</th>
                                            <th class="px-4 py-3 border-b">Jenis Partus</th>
                                            <th class="px-4 py-3 border-b">Petugas (TTD)</th>
                                            <th class="px-4 py-3 text-center border-b">Status</th>
                                            <th class="px-4 py-3 text-center border-b">Aksi</th>
                                        </tr>
                                    </thead>
                                    @foreach (array_reverse($entriList) as $entry)
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
                                                    {{ $rowKey ?: '-' }}
                                                </td>
                                                <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">
                                                    {{ $entry['jenisPartus'] ?: '-' }}
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
                                                        <x-secondary-button type="button" wire:click="cetak('{{ $rowKey }}')" wire:loading.attr="disabled" wire:target="cetak('{{ $rowKey }}')" class="gap-1.5" title="Cetak laporan ini">
                                                            <span wire:loading.remove wire:target="cetak('{{ $rowKey }}')" class="flex items-center gap-1.5">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                                </svg>
                                                                Cetak
                                                            </span>
                                                            <span wire:loading wire:target="cetak('{{ $rowKey }}')" class="flex items-center gap-1.5"><x-loading class="w-5 h-5" /> Mencetak...</span>
                                                        </x-secondary-button>
                                                        </div>
                                                        @if (!$isFormLocked)
                                                            <div class="flex items-center justify-center gap-2">
                                                            @can('dokumen.hapus')
                                                            <x-outline-button type="button" wire:click.prevent="hapus('{{ $rowKey }}')" wire:confirm="Yakin hapus laporan persalinan ini?"
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
                                                <td colspan="6" class="px-4 py-4 bg-surface-soft/60 dark:bg-gray-950/30">
                                                    <dl class="grid grid-cols-1 gap-x-8 gap-y-3 md:grid-cols-2">
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Jenis Partus</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['jenisPartus'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Indikasi</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['indikasi'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Bayi Lahir — Tgl / Jam</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['bayiLahirTgl'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Berat / Panjang</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['bayiBb'] ?: '-' }} gr / {{ $entry['bayiPb'] ?: '-' }} cm</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">APGAR Score</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['bayiApgar'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Resusitasi</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['bayiResusitasi'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Jenis Kelamin</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['bayiJenisKelamin'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Keadaan Bayi</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['bayiKeadaan'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Ukuran Kepala (BT/BP/FO/MO/OB)</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['ukKepalaBt'] ?: '-' }} / {{ $entry['ukKepalaBp'] ?: '-' }} / {{ $entry['ukKepalaFo'] ?: '-' }} / {{ $entry['ukKepalaMo'] ?: '-' }} / {{ $entry['ukKepalaOb'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Caput Suksedanium</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['caputSuksedanium'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Cephal Hematoma</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['cephalHematoma'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Atresia Ani</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['atresiaAni'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Bayi — Lain-lain</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['bayiLain'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Plasenta Lahir — Tgl / Jam</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['plasentaLahirTgl'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Plasenta — Cara / Jenis</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['plasentaCara'] ?: '-' }} / {{ $entry['plasentaJenis'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Plasenta — Berat / Diameter</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['plasentaBerat'] ?: '-' }} gr / {{ $entry['plasentaDiameter'] ?: '-' }} cm</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Tali Pusat — Insersi / Panjang</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['taliPusatInsersi'] ?: '-' }} / {{ $entry['taliPusatPanjang'] ?: '-' }} cm</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Selaput Janin — Keadaan</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['selaputKeadaan'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Selaput Janin — Robekan</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['selaputRobekan'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Selaput Janin — Lain-lain</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['selaputLain'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Luka Perineum</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['lukaPerineum'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Episiotomi</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['episiotomi'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Ruptura Perinei</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['rupturaPerinei'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Luka Vagina / Serviks</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['lukaVagina'] ?: '-' }} / {{ $entry['lukaServiks'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Kala IV — Hb / Suhu</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['kalaIvHb'] ?: '-' }} / {{ $entry['kalaIvSuhu'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Kala IV — TD / Nadi / RR</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['kalaIvTd'] ?: '-' }} / {{ $entry['kalaIvNadi'] ?: '-' }} / {{ $entry['kalaIvRr'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Kala IV — TFU / Kontraksi</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['kalaIvTfu'] ?: '-' }} / {{ $entry['kalaIvKontraksi'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Perdarahan Kala III / IV (cc)</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['perdarahanKalaIii'] ?: '-' }} / {{ $entry['perdarahanKalaIv'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">IMD Dilakukan [akr]</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['imdDilakukan'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">IMD — Jam / Durasi [akr]</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['imdJam'] ?: '-' }} / {{ $entry['imdDurasiMenit'] ?: '-' }} mnt</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Alasan bila IMD tidak [akr]</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['imdAlasanTidak'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Rawat Gabung [akr]</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['rawatGabung'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Konseling ASI [akr]</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['asiKonseling'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">PMK (Metode Kanguru) [akr]</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $entry['pmkDilakukan'] ?: '-' }}</dd>
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
                            <p class="text-sm text-muted dark:text-gray-400">Belum ada laporan persalinan tersimpan.</p>
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
