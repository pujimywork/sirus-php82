<?php
// resources/views/pages/transaksi/ugd/emr-ugd/modul-dokumen/inform-consent/rm-inform-consent-actions.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use Illuminate\Validation\ValidationException;

new class extends Component {
    use EmrUGDTrait, WithRenderVersioningTrait;

    public bool $isFormLocked = false;
    public ?int $rjNo = null;
    public bool $disabled = false;
    public array $dataDaftarUGD = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-inform-consent-ugd'];

    public array $newConsent = [
        'tindakan' => '',
        'diagnosa' => '',
        'komplikasi' => '',
        'tujuan' => '',
        'resiko' => '',
        'alternatif' => '',
        'dokter' => '',
        'wali' => '',
        'waliHubungan' => '',
        'saksi' => '',
        'agreement' => '1',
        'dokterCode' => '',
        'dokterDate' => '',
        'petugasPemeriksa' => '',
        'petugasPemeriksaCode' => '',
        'petugasPemeriksaDate' => '',
    ];

    public string $signature = '';
    public string $signatureSaksi = '';

    public array $agreementOptions = [['value' => '1', 'label' => 'Setuju'], ['value' => '0', 'label' => 'Tidak Setuju']];

    public array $waliHubunganOptions = [
        ['value' => 'pasien', 'label' => 'Pasien Sendiri'],
        ['value' => 'suami', 'label' => 'Suami'],
        ['value' => 'istri', 'label' => 'Istri'],
        ['value' => 'ayah', 'label' => 'Ayah'],
        ['value' => 'ibu', 'label' => 'Ibu'],
        ['value' => 'anak', 'label' => 'Anak'],
        ['value' => 'saudara', 'label' => 'Saudara'],
        ['value' => 'wali_hukum', 'label' => 'Wali Hukum'],
        ['value' => 'lainnya', 'label' => 'Lainnya'],
    ];

    public array $consentList = [];

    // Kunci entri yang sedang diedit (signatureDate = kunci stabil, di-set saat entri pertama dibuat).
    // null = sedang membuat entri baru.
    public ?string $editingKey = null;

    // true = entri terkunci sedang ditampilkan di form dalam mode read-only (lihat saja, tak bisa edit).
    public bool $viewOnly = false;

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(?int $rjNo = null, bool $disabled = false): void
    {
        $this->rjNo = $rjNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-inform-consent-ugd']);

        if ($this->rjNo) {
            $data = $this->findDataUGD($this->rjNo);
            if ($data) {
                $this->dataDaftarUGD = $data;
                $this->consentList = $data['informConsentPasienUGD'] ?? [];
                $this->isFormLocked = $this->checkEmrUGDStatus($this->rjNo) || $disabled;
            }
        }
    }

    /* ===============================
     | OPEN MODAL
     =============================== */
    public function openModal(): void
    {
        if (!$this->rjNo || $this->disabled) {
            return;
        }

        $this->resetNewConsent();
        $this->signature = '';
        $this->signatureSaksi = '';
        $this->editingKey = null;
        $this->viewOnly = false;
        $this->resetValidation();

        $data = $this->findDataUGD($this->rjNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.');
            return;
        }

        $this->dataDaftarUGD = $data;
        if (!isset($this->dataDaftarUGD['informConsentPasienUGD']) || !is_array($this->dataDaftarUGD['informConsentPasienUGD'])) {
            $this->dataDaftarUGD['informConsentPasienUGD'] = [];
        }
        $this->consentList = $this->dataDaftarUGD['informConsentPasienUGD'];
        // Default nama Pasien/Wali = nama pasien & hubungan = Pasien Sendiri (pola penundaan)
        $this->newConsent['wali'] = $this->dataDaftarUGD['regName'] ?? '';
        $this->newConsent['waliHubungan'] = 'pasien';
        $this->isFormLocked = $this->checkEmrUGDStatus($this->rjNo) || $this->disabled;
        $this->incrementVersion('modal-inform-consent-ugd');

        $this->dispatch('open-modal', name: "rm-inform-consent-ugd-{$this->rjNo}");
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: "rm-inform-consent-ugd-{$this->rjNo}");
    }

    /* ===============================
     | VALIDATION
     =============================== */
    protected function rules(): array
    {
        return [
            'newConsent.petugasPemeriksa' => 'required|string|max:150',
            'newConsent.petugasPemeriksaCode' => 'nullable|string',
            'newConsent.tindakan' => 'required|string|max:500',
            'newConsent.diagnosa' => 'required|string|max:500',
            'newConsent.komplikasi' => 'required|string|max:500',
            'newConsent.tujuan' => 'required|string',
            'newConsent.resiko' => 'required|string',
            'newConsent.alternatif' => 'required|string',
            'newConsent.dokter' => 'nullable|string',
            'newConsent.wali' => 'required|string|max:200',
            'newConsent.waliHubungan' => 'required|string|max:50',
            'newConsent.saksi' => 'nullable|string|max:200',
            'newConsent.agreement' => 'required|in:0,1',
            'signature' => 'required|string',
            'signatureSaksi' => 'nullable|string',
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'in' => ':attribute tidak valid.',
            'max' => ':attribute maksimal :max karakter.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'newConsent.petugasPemeriksa' => 'PPA / Profesional Pemberi Asuhan',
            'newConsent.petugasPemeriksaCode' => 'PPA / Profesional Pemberi Asuhan',
            'newConsent.tindakan' => 'Nama tindakan',
            'newConsent.diagnosa' => 'Diagnosa',
            'newConsent.komplikasi' => 'Komplikasi',
            'newConsent.tujuan' => 'Tujuan tindakan',
            'newConsent.resiko' => 'Risiko tindakan',
            'newConsent.alternatif' => 'Alternatif tindakan',
            'newConsent.dokter' => 'Pemberi Informasi',
            'newConsent.wali' => 'Nama pasien/wali',
            'newConsent.waliHubungan' => 'Hubungan wali',
            'newConsent.saksi' => 'Nama saksi',
            'newConsent.agreement' => 'Persetujuan',
            'signature' => 'Tanda tangan pasien/wali',
            'signatureSaksi' => 'Tanda tangan saksi',
        ];
    }

    /* ===============================
     | SET / CLEAR SIGNATURES
     =============================== */
    public function setSignature(string $dataUrl): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->signature = $dataUrl;
        $this->incrementVersion('modal-inform-consent-ugd');
    }

    public function clearSignature(): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->signature = '';
        $this->incrementVersion('modal-inform-consent-ugd');
    }

    public function setSignatureSaksi(string $dataUrl): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->signatureSaksi = $dataUrl;
        $this->incrementVersion('modal-inform-consent-ugd');
    }

    public function clearSignatureSaksi(): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->signatureSaksi = '';
        $this->incrementVersion('modal-inform-consent-ugd');
    }

    /* ===============================
     | TTD PETUGAS (Pemberi Informasi) = FINALIZE
     | Petugas TTD di akhir → validasi lengkap + kunci entri.
     =============================== */
    public function setDokterPenjelas(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        // Semua kolom wajib (termasuk TTD pasien/wali) divalidasi di sini agar field kosong
        // di-highlight MERAH — jangan short-circuit sebelum validate().
        try {
            $this->validate();
        } catch (ValidationException $e) {
            $this->dispatch('toast', type: 'error', message: 'Lengkapi seluruh kolom wajib sebelum TTD petugas.');
            throw $e;
        }

        // Stempel TTD petugas (pemberi informasi) = user login.
        $this->newConsent['dokter'] = auth()->user()->myuser_name ?? '';
        $this->newConsent['dokterCode'] = auth()->user()->myuser_code ?? '';
        $this->newConsent['dokterDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, true, 'Kunci (TTD Petugas)');
            $this->resetNewConsent();
            $this->newConsent['wali'] = $this->dataDaftarUGD['regName'] ?? '';
            $this->newConsent['waliHubungan'] = 'pasien';
            $this->signature = '';
            $this->signatureSaksi = '';
            $this->editingKey = null;
            $this->incrementVersion('modal-inform-consent-ugd');
            $this->dispatch('toast', type: 'success', message: 'Inform Consent ditandatangani petugas dan terkunci.');
            $this->dispatch('refresh-modul-dokumen-ugd-data', rjNo: $this->rjNo);
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal mengunci: ' . $e->getMessage());
        }
    }

    /* ===============================
     | TTD DOKTER MENYUSUL (staged) — dokter membubuhkan TTD pada entri tersimpan
     =============================== */
    public function signDokter(string $signatureDate): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        try {
            DB::transaction(function () use ($signatureDate) {
                $this->lockUGDRow($this->rjNo);
                $fresh = $this->findDataUGD($this->rjNo) ?: [];
                $list = $fresh['informConsentPasienUGD'] ?? [];
                $index = collect($list)->search(fn($it) => ($it['signatureDate'] ?? '') === $signatureDate);
                if ($index === false) {
                    throw new \RuntimeException('Entri tidak ditemukan.');
                }
                if (!empty($list[$index]['dokter'])) {
                    throw new \RuntimeException('TTD dokter sudah ada.');
                }
                $list[$index]['dokter'] = auth()->user()->myuser_name ?? '';
                $list[$index]['dokterCode'] = auth()->user()->myuser_code ?? '';
                $list[$index]['dokterDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
                $fresh['informConsentPasienUGD'] = $list;
                $this->updateJsonUGD($this->rjNo, $fresh);
                $this->dataDaftarUGD = $fresh;
                $this->consentList = $list;
                $this->appendAdminLogUGD((int) $this->rjNo, 'TTD Dokter (menyusul) Inform Consent — entri ' . $signatureDate, 'MR');
            });
            $this->incrementVersion('modal-inform-consent-ugd');
            $this->dispatch('toast', type: 'success', message: 'TTD dokter berhasil ditambahkan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menambahkan TTD: ' . $e->getMessage());
        }
    }

    /* ===============================
     | BUKA KUNCI (unlock) — hanya Admin / Manager keatas.
     | Cabut kunci entri: finalized=false + hapus TTD petugas (dokter penjelas).
     | TTD pasien & saksi DIPERTAHANKAN. Entri kembali draft utk dikoreksi lalu dikunci ulang.
     =============================== */
    private function bolehBukaKunci(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['Admin', 'Manager Umum', 'Manager Medis']);
    }

    public function bukaKunci(string $signatureDate): void
    {
        if (!$this->bolehBukaKunci()) {
            $this->dispatch('toast', type: 'error', message: 'Hanya Admin / Manager yang dapat membuka kunci.');
            return;
        }
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang — form read-only.');
            return;
        }

        try {
            DB::transaction(function () use ($signatureDate) {
                $this->lockUGDRow($this->rjNo);
                $fresh = $this->findDataUGD($this->rjNo) ?: [];
                $list = $fresh['informConsentPasienUGD'] ?? [];
                $index = collect($list)->search(fn($it) => ($it['signatureDate'] ?? '') === $signatureDate);
                if ($index === false) {
                    throw new \RuntimeException('Entri tidak ditemukan.');
                }
                $list[$index]['finalized'] = false;
                $list[$index]['dokter'] = '';
                $list[$index]['dokterCode'] = '';
                $list[$index]['dokterDate'] = '';
                $fresh['informConsentPasienUGD'] = array_values($list);
                $this->updateJsonUGD($this->rjNo, $fresh);
                $this->dataDaftarUGD = $fresh;
                $this->consentList = $fresh['informConsentPasienUGD'];
                $this->appendAdminLogUGD((int) $this->rjNo, 'Buka kunci Inform Consent — entri ' . $signatureDate . ' (oleh ' . (auth()->user()->myuser_name ?? auth()->user()->name ?? '-') . ')', 'MR');
            });
            $this->incrementVersion('modal-inform-consent-ugd');
            $this->dispatch('toast', type: 'success', message: 'Kunci dibuka — entri kembali draft & TTD petugas dicabut. Silakan koreksi lalu kunci ulang.');
            $this->dispatch('refresh-modul-dokumen-ugd-data', rjNo: $this->rjNo);
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal membuka kunci: ' . $e->getMessage());
        }
    }

    /* ===============================
     | PPA (Profesional Pemberi Asuhan) — combobox: pilih dari daftar atau ketik bebas.
     | Ganti LOV dokter agar perawat/bidan/apoteker/gizi & nama lain bisa diisi.
     =============================== */
    // Best-effort resolve kode PPA (myuser_code) dari nama pilihan/ketik. Kosong jika tak cocok
    // — aman: cetak fallback ke nama (kode diresolve ulang di buildConsentEntry).
    private function resolvePpaCode(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }
        $code = DB::table('users')->whereRaw('UPPER(TRIM(myuser_name)) = ?', [strtoupper($name)])->value('myuser_code');

        return !empty($code) ? (string) $code : '';
    }

    // Shortcut isi nama PPA = user login (combobox tetap bisa diedit/diganti setelahnya)
    public function setPpaSaya(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        $user = auth()->user();
        $this->newConsent['petugasPemeriksa'] = $user->myuser_name ?? $user->name ?? '';
        $this->newConsent['petugasPemeriksaCode'] = $user->myuser_code ?? '';
        $this->newConsent['petugasPemeriksaDate'] = '';
    }

    /* ===============================
     | HELPER — status & bentuk entri
     =============================== */
    // Entri dianggap FINAL/terkunci bila flag finalized true; entri lama (tanpa flag) yang sudah
    // ada TTD pasien dianggap final (kompatibilitas data lama).
    public function entryIsFinal(array $e): bool
    {
        return array_key_exists('finalized', $e) ? (bool) $e['finalized'] : !empty($e['signature']);
    }

    // Susun array entri dari state form. $key = signatureDate (kunci stabil); $finalized = status kunci.
    private function buildConsentEntry(string $key, bool $finalized): array
    {
        $now = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        $ppaName = trim($this->newConsent['petugasPemeriksa'] ?? '');

        return [
            'tindakan' => $this->newConsent['tindakan'] ?? '',
            'diagnosa' => $this->newConsent['diagnosa'] ?? '',
            'komplikasi' => $this->newConsent['komplikasi'] ?? '',
            'tujuan' => $this->newConsent['tujuan'] ?? '',
            'resiko' => $this->newConsent['resiko'] ?? '',
            'alternatif' => $this->newConsent['alternatif'] ?? '',
            'dokter' => $this->newConsent['dokter'] ?? '',
            'dokterCode' => $this->newConsent['dokterCode'] ?? '',
            'dokterDate' => $this->newConsent['dokterDate'] ?? '',
            'signature' => $this->signature,
            'signatureDate' => $key,
            'wali' => $this->newConsent['wali'] ?? '',
            'waliHubungan' => $this->newConsent['waliHubungan'] ?? '',
            'signatureSaksi' => $this->signatureSaksi,
            'signatureSaksiDate' => $this->signatureSaksi ? $now : '',
            'saksi' => $this->newConsent['saksi'] ?? '',
            'agreement' => $this->newConsent['agreement'] ?? '1',
            'petugasPemeriksa' => $ppaName,
            'petugasPemeriksaCode' => $ppaName !== '' ? $this->resolvePpaCode($ppaName) : '',
            'petugasPemeriksaDate' => $ppaName !== '' ? $now : '',
            'finalized' => $finalized,
        ];
    }

    // Simpan entri (add/update by $key) dengan status $finalized. Dipakai draft & kunci.
    private function persistEntry(string $key, bool $finalized, string $logVerb): void
    {
        $entry = $this->buildConsentEntry($key, $finalized);

        DB::transaction(function () use ($entry, $key, $logVerb) {
            $this->lockUGDRow($this->rjNo);

            $data = $this->findDataUGD($this->rjNo);
            if (empty($data)) {
                throw new \RuntimeException('Data UGD tidak ditemukan, simpan dibatalkan.');
            }
            if (!isset($data['informConsentPasienUGD']) || !is_array($data['informConsentPasienUGD'])) {
                $data['informConsentPasienUGD'] = [];
            }

            $list = $data['informConsentPasienUGD'];
            $idx = collect($list)->search(fn($it) => ($it['signatureDate'] ?? '') === $key);
            if ($idx === false) {
                $list[] = $entry;
            } else {
                if ($this->entryIsFinal($list[$idx])) {
                    throw new \RuntimeException('Entri sudah terkunci, tidak dapat diubah.');
                }
                $list[$idx] = $entry;
            }
            $data['informConsentPasienUGD'] = array_values($list);

            $this->updateJsonUGD($this->rjNo, $data);
            $this->dataDaftarUGD = $data;
            $this->consentList = $data['informConsentPasienUGD'];

            $this->appendAdminLogUGD((int) $this->rjNo, $logVerb . ' Inform Consent UGD — tindakan "' . ($entry['tindakan'] ?: '-') . '" (' . $key . ')', 'MR');
        });
    }

    /* ===============================
     | SIMPAN DRAFT (nyicil, tanpa validasi lengkap)
     =============================== */
    public function saveDraft(): void
    {
        if ($this->isFormLocked || $this->viewOnly) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }
        if (trim($this->newConsent['tindakan'] ?? '') === '') {
            $this->dispatch('toast', type: 'error', message: 'Nama tindakan wajib diisi untuk menyimpan draft.');
            return;
        }

        $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            $this->persistEntry($key, false, 'Simpan draft');
            $this->editingKey = $key; // lanjut edit entri yang sama, tidak buat duplikat
            $this->incrementVersion('modal-inform-consent-ugd');
            $this->dispatch('toast', type: 'success', message: 'Draft Inform Consent tersimpan.');
            $this->dispatch('refresh-modul-dokumen-ugd-data', rjNo: $this->rjNo);
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan draft: ' . $e->getMessage());
        }
    }

    /* ===============================
     | EDIT / BATAL EDIT entri draft
     =============================== */
    // Muat 1 entri ke form atas (dipakai edit draft & lihat entri terkunci).
    private function hydrateFormFromEntry(array $entry, string $key): void
    {
        $this->newConsent = [
            'tindakan' => $entry['tindakan'] ?? '',
            'diagnosa' => $entry['diagnosa'] ?? '',
            'komplikasi' => $entry['komplikasi'] ?? '',
            'tujuan' => $entry['tujuan'] ?? '',
            'resiko' => $entry['resiko'] ?? '',
            'alternatif' => $entry['alternatif'] ?? '',
            'dokter' => $entry['dokter'] ?? '',
            'wali' => $entry['wali'] ?? '',
            'waliHubungan' => $entry['waliHubungan'] ?? '',
            'saksi' => $entry['saksi'] ?? '',
            'agreement' => $entry['agreement'] ?? '1',
            'dokterCode' => $entry['dokterCode'] ?? '',
            'dokterDate' => $entry['dokterDate'] ?? '',
            'petugasPemeriksa' => $entry['petugasPemeriksa'] ?? '',
            'petugasPemeriksaCode' => $entry['petugasPemeriksaCode'] ?? '',
            'petugasPemeriksaDate' => $entry['petugasPemeriksaDate'] ?? '',
        ];
        $this->signature = $entry['signature'] ?? '';
        $this->signatureSaksi = $entry['signatureSaksi'] ?? '';
        $this->editingKey = $key;
        $this->resetValidation();
        $this->incrementVersion('modal-inform-consent-ugd');
    }

    public function editEntry(string $key): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        $entry = collect($this->consentList)->firstWhere('signatureDate', $key);
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
        $entry = collect($this->consentList)->firstWhere('signatureDate', $key);
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
        $this->resetNewConsent();
        $this->newConsent['wali'] = $this->dataDaftarUGD['regName'] ?? '';
        $this->newConsent['waliHubungan'] = 'pasien';
        $this->signature = '';
        $this->signatureSaksi = '';
        $this->editingKey = null;
        $this->viewOnly = false;
        $this->resetValidation();
        $this->incrementVersion('modal-inform-consent-ugd');
    }

    /* ===============================
     | CETAK
     =============================== */
    public function cetak(string $signatureDate): void
    {
        if (!$this->rjNo) {
            $this->dispatch('toast', type: 'error', message: 'Nomor UGD tidak ditemukan.');
            return;
        }

        $consent = collect($this->consentList)->firstWhere('signatureDate', $signatureDate);
        if (!$consent) {
            $this->dispatch('toast', type: 'error', message: 'Data consent tidak ditemukan.');
            return;
        }

        $this->dispatch('cetak-inform-consent-ugd.open', rjNo: $this->rjNo, signatureDate: $signatureDate);
    }

    /* ===============================
     | HAPUS
     =============================== */
    public function hapus(string $signatureDate): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menghapus.');
            return;
        }

        try {
            DB::transaction(function () use ($signatureDate) {
                $this->lockUGDRow($this->rjNo);

                $data = $this->findDataUGD($this->rjNo);
                if (empty($data)) {
                    throw new \RuntimeException('Data UGD tidak ditemukan.');
                }

                if (!isset($data['informConsentPasienUGD'])) {
                    throw new \RuntimeException('Data consent tidak ditemukan.');
                }

                $removed = collect($data['informConsentPasienUGD'])->firstWhere('signatureDate', $signatureDate);

                $data['informConsentPasienUGD'] = collect($data['informConsentPasienUGD'])->reject(fn($item) => ($item['signatureDate'] ?? '') === $signatureDate)->values()->toArray();

                $this->updateJsonUGD($this->rjNo, $data);
                $this->dataDaftarUGD = $data;
                $this->consentList = $data['informConsentPasienUGD'];

                $this->appendAdminLogUGD((int) $this->rjNo, 'Hapus Inform Consent UGD — tindakan "' . ($removed['tindakan'] ?? '-') . '" TTD ' . $signatureDate, 'MR');
            });

            $this->incrementVersion('modal-inform-consent-ugd');
            $this->dispatch('toast', type: 'success', message: 'Inform Consent berhasil dihapus.');
            $this->dispatch('refresh-modul-dokumen-ugd-data', rjNo: $this->rjNo);
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    private function resetNewConsent(): void
    {
        $this->newConsent = [
            'tindakan' => '',
            'diagnosa' => '',
            'komplikasi' => '',
            'tujuan' => '',
            'resiko' => '',
            'alternatif' => '',
            'dokter' => '',
            'wali' => '',
            'waliHubungan' => '',
            'saksi' => '',
            'agreement' => '1',
            'dokterCode' => '',
            'dokterDate' => '',
            'petugasPemeriksa' => '',
            'petugasPemeriksaCode' => '',
            'petugasPemeriksaDate' => '',
        ];
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->dataDaftarUGD = [];
        $this->consentList = [];
        $this->resetNewConsent();
        $this->signature = '';
        $this->signatureSaksi = '';
        $this->editingKey = null;
        $this->viewOnly = false;
    }
};
?>

<div>
    {{-- ══ SUMMARY CARD (inline di tab) ══ --}}
    @php $icCount = count($consentList ?? []); @endphp

    <div
        class="p-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-3">
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">
                        Inform Consent
                    </h3>
                    @if ($icCount > 0)
                        <x-badge variant="success">{{ $icCount }} tindakan</x-badge>
                    @else
                        <x-badge variant="warning">Belum ada</x-badge>
                    @endif
                </div>

                <div class="flex shrink-0">
                    <x-primary-button type="button" wire:click="openModal" wire:loading.attr="disabled"
                        wire:target="openModal" :disabled="$disabled || !$rjNo" class="gap-2">
                        <span wire:loading.remove wire:target="openModal" class="flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                            Buka Inform Consent
                        </span>
                        <span wire:loading wire:target="openModal" class="flex items-center gap-1.5">
                            <x-loading class="w-4 h-4" /> Memuat...
                        </span>
                    </x-primary-button>
                </div>
            </div>

            <p class="text-base text-muted dark:text-gray-400">
                Persetujuan tindakan medis per-tindakan: tujuan, risiko, alternatif, serta tanda tangan
                pasien/wali, dokter penjelas, dan saksi.
            </p>

            @if ($icCount > 0)
                <div class="overflow-x-auto">
                    <h4 class="mb-2 text-sm font-semibold text-body dark:text-gray-300">Daftar Inform Consent Tersimpan</h4>
                    <table class="min-w-full text-sm border border-hairline rounded-lg dark:border-gray-700">
                        <thead class="bg-surface-soft dark:bg-gray-800">
                            <tr class="text-left text-muted dark:text-gray-300">
                                <th class="px-3 py-2 border-b">Tindakan</th>
                                <th class="px-3 py-2 border-b">Tanggal</th>
                                <th class="px-3 py-2 border-b">Pemberi Informasi</th>
                                <th class="px-3 py-2 border-b text-center">Persetujuan</th>
                                <th class="px-3 py-2 border-b text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (array_reverse($consentList) as $ic)
                                <tr class="border-b border-hairline dark:border-gray-700">
                                    <td class="px-3 py-2 font-medium text-ink dark:text-gray-200">
                                        {{ \Illuminate\Support\Str::limit($ic['tindakan'] ?? '-', 50) }}
                                    </td>
                                    <td class="px-3 py-2 text-muted dark:text-gray-400">{{ $ic['signatureDate'] ?? '-' }}</td>
                                    <td class="px-3 py-2 text-muted dark:text-gray-400">
                                        @if (!empty($ic['dokter'])){{ $ic['dokter'] }}@else<x-badge variant="danger">Belum TTD</x-badge>@endif
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        @if (($ic['agreement'] ?? '1') === '1')
                                            <x-badge variant="success">Menyetujui</x-badge>
                                        @else
                                            <x-badge variant="danger">Menolak</x-badge>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        @if ($this->entryIsFinal($ic))
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
    </div>

    {{-- ══ MODAL FORM ══ --}}
    <x-modal name="rm-inform-consent-ugd-{{ $rjNo ?? 'init' }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal-inform-consent-ugd', [$rjNo ?? 'new']) }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>

                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div
                                class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                                <svg class="w-6 h-6 text-brand-green dark:text-brand-lime" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                </svg>
                            </div>

                            <div>
                                <h2 class="font-semibold text-2xl text-ink dark:text-gray-100">Inform Consent</h2>
                                <p class="mt-0.5 text-base text-muted dark:text-gray-400">
                                    Persetujuan tindakan medis UGD — tampilan ini dapat diputar ke arah pasien
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2 mt-3">
                            <x-badge variant="danger">UGD</x-badge>
                            @if ($icCount > 0)
                                <x-badge variant="info">{{ $icCount }} tersimpan</x-badge>
                            @endif
                            @if ($isFormLocked)
                                <x-badge variant="danger">Read Only</x-badge>
                            @endif
                        </div>
                    </div>

                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <span class="sr-only">Close</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20"
                            fill="currentColor">
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
                    <livewire:pages::transaksi.ugd.display-pasien-ugd.display-pasien-ugd :rjNo="$rjNo"
                        wire:key="ic-ugd-display-pasien-{{ $rjNo ?? 'init' }}" />

                    <div
                        class="p-4 space-y-4 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                        @php $formRO = $isFormLocked || $viewOnly; @endphp

                        @if ($isFormLocked)
                            <div
                                class="flex items-center gap-2 px-4 py-2.5 mb-4 text-base font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                                EMR terkunci — data tidak dapat diubah.
                            </div>
                        @endif

                        @if ($viewOnly)
                            <div
                                class="flex items-center gap-2 px-4 py-2.5 mb-2 text-base font-medium text-sky-700 bg-sky-50 border border-sky-200 rounded-xl dark:bg-sky-900/20 dark:border-sky-600 dark:text-sky-300">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                Menampilkan entri terkunci <strong>{{ $editingKey }}</strong> (hanya lihat) — klik <strong>Selesai Melihat</strong> untuk kembali ke form entri baru.
                            </div>
                        @elseif ($editingKey && !$isFormLocked)
                            <div
                                class="flex items-center gap-2 px-4 py-2.5 mb-2 text-base font-medium text-brand-green bg-brand-lime/10 border border-brand-lime/40 rounded-xl dark:text-brand-lime dark:bg-brand-lime/5">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                Sedang melanjutkan entri <strong>{{ $editingKey }}</strong> — <strong>Simpan Perubahan</strong> menyimpan ke entri ini; klik <strong>Entri Baru</strong> untuk menambah tindakan lain.
                            </div>
                        @endif

                        {{-- ══ INFORMASI TINDAKAN ══ --}}
                        <section class="space-y-4">
                            <h3 class="text-base font-semibold text-ink dark:text-gray-200">
                                Informasi Tindakan
                            </h3>

                            <div>
                                <x-input-label value="PPA — Profesional Pemberi Asuhan *" class="mb-1" />
                                @if (!$formRO)
                                    <div class="flex items-start gap-2">
                                        <div class="flex-1">
                                            <x-ppa-combobox wireModel="newConsent.petugasPemeriksa"
                                                :disabled="$formRO" />
                                        </div>
                                        <x-outline-button type="button" wire:click.prevent="setPpaSaya" class="shrink-0"
                                            title="Isi dengan nama saya (user login)">
                                            Saya
                                        </x-outline-button>
                                    </div>
                                    <p class="mt-1 text-xs text-muted">
                                        Pilih dokter / perawat / bidan / apoteker / gizi dari daftar, atau ketik nama PPA
                                        yang memberi informasi bila berbeda dari user login.
                                    </p>
                                @else
                                    <div
                                        class="p-3 border border-hairline bg-surface-soft rounded-xl dark:bg-gray-800 dark:border-gray-700">
                                        <div class="font-semibold text-ink dark:text-gray-200">
                                            {{ $newConsent['petugasPemeriksa'] ?: '—' }}
                                        </div>
                                        @if (!empty($newConsent['petugasPemeriksaDate']))
                                            <div class="mt-1 text-sm text-muted">
                                                {{ $newConsent['petugasPemeriksaDate'] }}
                                            </div>
                                        @endif
                                    </div>
                                @endif
                                <x-input-error :messages="$errors->get('newConsent.petugasPemeriksa')" class="mt-1" />
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <x-input-label value="Diagnosa *" class="mb-1" />
                                    <x-text-input wire:model.live="newConsent.diagnosa" :error="$errors->has('newConsent.diagnosa')"
                                        placeholder="Diagnosa kerja / penyakit..." :disabled="$formRO"
                                        class="w-full" />
                                    <x-input-error :messages="$errors->get('newConsent.diagnosa')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Komplikasi *" class="mb-1" />
                                    <x-text-input wire:model.live="newConsent.komplikasi" :error="$errors->has('newConsent.komplikasi')"
                                        placeholder="Kemungkinan komplikasi..." :disabled="$formRO"
                                        class="w-full" />
                                    <x-input-error :messages="$errors->get('newConsent.komplikasi')" class="mt-1" />
                                </div>
                            </div>

                            <div>
                                <x-input-label value="Nama Tindakan / Prosedur *" class="mb-1" />
                                <x-text-input wire:model.live="newConsent.tindakan" :error="$errors->has('newConsent.tindakan')"
                                    placeholder="Contoh: Hecting, Resusitasi, Pemberian O2..." :disabled="$formRO"
                                    class="w-full" />
                                <x-input-error :messages="$errors->get('newConsent.tindakan')" class="mt-1" />
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div>
                                    <x-input-label value="Tujuan Tindakan / Terapi *" class="mb-1" />
                                    <x-textarea wire:model.live="newConsent.tujuan" :error="$errors->has('newConsent.tujuan')" rows="3"
                                        placeholder="Uraian singkat mengenai tujuan tindakan..."
                                        :disabled="$formRO" />
                                    <x-input-error :messages="$errors->get('newConsent.tujuan')" class="mt-1" />
                                </div>

                                <div>
                                    <x-input-label value="Risiko Tindakan / Terapi *" class="mb-1" />
                                    <x-textarea wire:model.live="newConsent.resiko" :error="$errors->has('newConsent.resiko')" rows="3"
                                        placeholder="Kemungkinan risiko / efek samping..." :disabled="$formRO" />
                                    <x-input-error :messages="$errors->get('newConsent.resiko')" class="mt-1" />
                                </div>

                                <div>
                                    <x-input-label value="Alternatif Tindakan / Terapi *" class="mb-1" />
                                    <x-textarea wire:model.live="newConsent.alternatif" :error="$errors->has('newConsent.alternatif')" rows="3"
                                        placeholder="Alternatif lain yang dapat dilakukan..."
                                        :disabled="$formRO" />
                                    <x-input-error :messages="$errors->get('newConsent.alternatif')" class="mt-1" />
                                </div>
                            </div>

                            <div class="md:max-w-xs">
                                <x-input-label value="Persetujuan *" class="mb-1" />
                                <x-select-input wire:model.live="newConsent.agreement" :error="$errors->has('newConsent.agreement')" :disabled="$formRO"
                                    class="w-full">
                                    @foreach ($agreementOptions as $opt)
                                        <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                                    @endforeach
                                </x-select-input>
                                <x-input-error :messages="$errors->get('newConsent.agreement')" class="mt-1" />
                            </div>

                            @if (($newConsent['agreement'] ?? '1') === '1')
                                <div
                                    class="flex items-start gap-3 px-4 py-3 text-base border rounded-xl bg-emerald-50 border-emerald-200 text-emerald-800 dark:bg-emerald-900/20 dark:border-emerald-700 dark:text-emerald-200">
                                    <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <div>
                                        <p class="font-semibold">Pasien MENYETUJUI tindakan</p>
                                        <p class="mt-0.5">
                                            Setelah ditandatangani, dokumen dicetak sebagai
                                            <strong>Persetujuan Tindakan Medis (Inform Consent)</strong> dan tindakan
                                            dapat dilakukan.
                                        </p>
                                    </div>
                                </div>
                            @else
                                <div
                                    class="flex items-start gap-3 px-4 py-3 text-base border rounded-xl bg-rose-50 border-rose-200 text-rose-800 dark:bg-rose-900/20 dark:border-rose-700 dark:text-rose-200">
                                    <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    <div>
                                        <p class="font-semibold">Pasien MENOLAK tindakan</p>
                                        <p class="mt-0.5">
                                            Dokumen akan tercatat sebagai
                                            <strong>Penolakan Tindakan Medis</strong>. Pasien/wali memahami risiko medis
                                            atas penolakan tersebut dan bersedia menandatangani sebagai bukti penolakan.
                                            Tindakan tidak akan dilakukan.
                                        </p>
                                    </div>
                                </div>
                            @endif
                        </section>

                        {{-- ══ TANDA TANGAN ══ --}}
                        <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                            <h3 class="text-base font-semibold text-ink dark:text-gray-200">
                                Tanda Tangan
                            </h3>

                            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                                {{-- Pasien / Wali --}}
                                <div class="flex flex-col">
                                    <div
                                        class="mb-2 text-sm font-semibold tracking-wide text-center text-muted uppercase dark:text-gray-400">
                                        Pasien / Wali
                                    </div>
                                    <x-input-error :messages="$errors->get('signature')" class="mb-2" />
                                    @if (!empty($signature))
                                        <x-signature.signature-result :signature="$signature" :date="''"
                                            :disabled="$formRO" wireMethod="clearSignature" />
                                    @elseif (!$formRO)
                                        <x-signature.signature-pad wireMethod="setSignature" />
                                    @else
                                        <p class="py-8 text-base italic text-center text-muted-soft">Belum
                                            ditandatangani.</p>
                                    @endif

                                    <div class="mt-3">
                                        <x-input-label value="Nama Pasien / Wali *" class="mb-1" />
                                        <x-text-input wire:model.live="newConsent.wali" :error="$errors->has('newConsent.wali')"
                                            placeholder="Nama lengkap pasien atau wali..." :disabled="$formRO"
                                            class="w-full" />
                                        <x-input-error :messages="$errors->get('newConsent.wali')" class="mt-1" />
                                    </div>

                                    <div class="mt-2">
                                        <x-input-label value="Hubungan dengan Pasien *" class="mb-1" />
                                        <x-select-input wire:model.live="newConsent.waliHubungan" :error="$errors->has('newConsent.waliHubungan')"
                                            :disabled="$formRO" class="w-full">
                                            <option value="">— Pilih hubungan —</option>
                                            @foreach ($waliHubunganOptions as $opt)
                                                <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                                            @endforeach
                                        </x-select-input>
                                        <x-input-error :messages="$errors->get('newConsent.waliHubungan')"
                                            class="mt-1" />
                                    </div>
                                </div>

                                {{-- Saksi --}}
                                <div class="flex flex-col">
                                    <div
                                        class="mb-2 text-sm font-semibold tracking-wide text-center text-muted uppercase dark:text-gray-400">
                                        Saksi
                                    </div>
                                    <x-input-error :messages="$errors->get('signatureSaksi')" class="mb-2" />
                                    @if (!empty($signatureSaksi))
                                        <x-signature.signature-result :signature="$signatureSaksi" :date="''"
                                            :disabled="$formRO" wireMethod="clearSignatureSaksi" />
                                    @elseif (!$formRO)
                                        <x-signature.signature-pad wireMethod="setSignatureSaksi" />
                                    @else
                                        <p class="py-8 text-base italic text-center text-muted-soft">Belum
                                            ditandatangani.</p>
                                    @endif

                                    <div class="mt-3">
                                        <x-input-label value="Nama Saksi" class="mb-1" />
                                        <x-text-input wire:model.live="newConsent.saksi" :error="$errors->has('newConsent.saksi')" placeholder="Nama saksi..."
                                            :disabled="$formRO" class="w-full" />
                                        <x-input-error :messages="$errors->get('newConsent.saksi')" class="mt-1" />
                                    </div>
                                </div>

                                {{-- Dokter Penjelas --}}
                                <div class="flex flex-col">
                                    <div
                                        class="mb-2 text-sm font-semibold tracking-wide text-center text-muted uppercase dark:text-gray-400">
                                        Pemberi Informasi
                                    </div>
                                    @if (empty($newConsent['dokter']))
                                        @if (!$formRO)
                                            <div
                                                class="flex flex-col items-center justify-center flex-1 gap-2 p-6 border-2 border-gray-300 border-dashed rounded-xl dark:border-gray-700">
                                                <x-primary-button wire:click.prevent="setDokterPenjelas"
                                                    wire:loading.attr="disabled" wire:target="setDokterPenjelas"
                                                    class="gap-2">
                                                    <span wire:loading.remove wire:target="setDokterPenjelas"
                                                        class="flex items-center gap-1.5">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 012.828 2.828L11.828 15.828a4 4 0 01-2.828 1.172H7v-2a4 4 0 011.172-2.828z" />
                                                        </svg>
                                                        TTD Petugas &amp; Kunci
                                                    </span>
                                                    <span wire:loading wire:target="setDokterPenjelas">
                                                        <x-loading class="w-4 h-4" /> Mengunci...
                                                    </span>
                                                </x-primary-button>
                                                <p class="text-xs text-center text-muted">Menandatangani = validasi &amp; mengunci consent ini.</p>
                                            </div>
                                        @else
                                            <p class="py-8 text-base italic text-center text-muted-soft">Belum
                                                ditandatangani.</p>
                                        @endif
                                    @else
                                        <div
                                            class="flex flex-col items-center justify-center flex-1 p-4 border border-hairline bg-surface-soft rounded-xl dark:bg-gray-800 dark:border-gray-700">
                                            <div class="font-semibold text-center text-ink dark:text-gray-200">
                                                {{ $newConsent['dokter'] }}
                                            </div>
                                            @if (!empty($newConsent['dokterCode']))
                                                <div class="text-sm text-muted mt-0.5">
                                                    Kode: {{ $newConsent['dokterCode'] }}
                                                </div>
                                            @endif
                                            <div class="mt-1 text-sm text-muted">
                                                {{ $newConsent['dokterDate'] ?? '-' }}
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </section>

                        {{-- DAFTAR CONSENT TERSIMPAN --}}
                        @if (count($consentList) > 0)
                            <div class="mt-6 overflow-x-auto">
                                <div class="flex items-center justify-between gap-2 pb-2 border-b border-hairline-soft dark:border-gray-800 mb-3">
                                    <h3 class="text-base font-semibold text-body dark:text-gray-300">
                                        Daftar Inform Consent Tersimpan
                                    </h3>
                                    <span class="text-xs italic text-muted-soft">Klik baris untuk lihat detail lengkap</span>
                                </div>
                                <table class="min-w-full text-base border border-hairline rounded-lg dark:border-gray-700">
                                    <thead class="bg-surface-soft dark:bg-gray-800">
                                        <tr class="text-left text-sm font-semibold tracking-wide text-muted uppercase dark:text-gray-300">
                                            <th class="w-8 px-2 py-3 border-b"></th>
                                            <th class="px-4 py-3 border-b">Tindakan</th>
                                            <th class="px-4 py-3 border-b">Tanggal Dibuat</th>
                                            <th class="px-4 py-3 border-b">Pemberi Informasi</th>
                                            <th class="px-4 py-3 border-b text-center">Persetujuan</th>
                                            <th class="px-4 py-3 border-b text-center">Status</th>
                                            <th class="px-4 py-3 border-b text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    @foreach (array_reverse($consentList) as $consent)
                                        @php
                                            $isFinal = $this->entryIsFinal($consent);
                                            $rowKey = $consent['signatureDate'] ?? '';
                                            $waliHub = collect($waliHubunganOptions)->firstWhere('value', $consent['waliHubungan'] ?? '')['label'] ?? ($consent['waliHubungan'] ?? '');
                                        @endphp
                                        <tbody x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }" class="border-b border-hairline dark:border-gray-700">
                                            <tr @click="open = !open"
                                                class="cursor-pointer hover:bg-surface-soft dark:hover:bg-gray-800 {{ $editingKey && $editingKey === $rowKey ? 'bg-brand-lime/10 dark:bg-brand-lime/5' : '' }}">
                                                <td class="px-2 py-3 text-center align-middle">
                                                    <svg class="w-4 h-4 mx-auto text-muted transition-transform" :class="{ 'rotate-90': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                    </svg>
                                                </td>
                                                <td class="px-4 py-3 align-middle font-semibold text-ink dark:text-gray-100">
                                                    {{ Str::limit($consent['tindakan'] ?: '(tanpa nama tindakan)', 50) }}
                                                </td>
                                                <td class="px-4 py-3 align-middle text-sm tabular-nums text-muted dark:text-gray-400">
                                                    {{ $rowKey ?: '-' }}
                                                </td>
                                                <td class="px-4 py-3 align-middle text-muted dark:text-gray-300">
                                                    @if (!empty($consent['dokter']))
                                                        <span class="font-medium text-ink dark:text-gray-200">{{ $consent['dokter'] }}</span>
                                                    @else
                                                        <x-badge variant="danger">Belum TTD</x-badge>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 align-middle text-center">
                                                    @if (($consent['agreement'] ?? '1') === '1')
                                                        <x-badge variant="success">Menyetujui</x-badge>
                                                    @else
                                                        <x-badge variant="danger">Menolak</x-badge>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 align-middle text-center">
                                                    @if ($isFinal)
                                                        <x-badge variant="info">Terkunci</x-badge>
                                                    @else
                                                        <x-badge variant="warning">Draft</x-badge>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 align-middle text-center" @click.stop>
                                                    <div class="flex items-center justify-center gap-2">
                                                        @if (!$isFinal && !$isFormLocked)
                                                            <x-primary-button type="button" wire:click="editEntry('{{ $rowKey }}')" wire:loading.attr="disabled" wire:target="editEntry('{{ $rowKey }}')" class="gap-1.5" title="Lanjutkan mengisi entri ini">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                                </svg>
                                                                Lanjut Isi
                                                            </x-primary-button>
                                                        @endif
                                                        @if ($isFinal && empty($consent['dokter']) && !$isFormLocked)
                                                            <x-primary-button type="button" wire:click="signDokter('{{ $rowKey }}')" wire:loading.attr="disabled" wire:target="signDokter('{{ $rowKey }}')" class="gap-1.5" title="Tanda tangan petugas menyusul">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 012.828 2.828L11.828 15.828a4 4 0 01-2.828 1.172H7v-2a4 4 0 011.172-2.828z" />
                                                                </svg>
                                                                TTD Petugas
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
                                                            <x-secondary-button wire:click="cetak('{{ $rowKey }}')" wire:loading.attr="disabled" wire:target="cetak('{{ $rowKey }}')" class="gap-1.5">
                                                                <span wire:loading.remove wire:target="cetak('{{ $rowKey }}')" class="flex items-center gap-1.5">
                                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                                    </svg>
                                                                    Cetak
                                                                </span>
                                                                <span wire:loading wire:target="cetak('{{ $rowKey }}')" class="flex items-center gap-1.5"><x-loading class="w-5 h-5" /> Mencetak...</span>
                                                            </x-secondary-button>
                                                            @if (!$isFormLocked)
                                                                @hasanyrole('Admin|Manager Umum|Manager Medis')
                                                                    <x-ghost-button type="button" wire:click.prevent="bukaKunci('{{ $rowKey }}')"
                                                                        wire:confirm="Buka kunci Inform Consent ini? TTD petugas akan dicabut & entri kembali menjadi draft untuk dikoreksi."
                                                                        wire:loading.attr="disabled" wire:target="bukaKunci('{{ $rowKey }}')"
                                                                        class="gap-1.5 !text-red-600 !bg-red-600/5 !border-red-600/20 hover:!bg-red-600/10 hover:!text-red-700 hover:!border-red-600/30 focus:!ring-red-600/20 dark:!text-red-400 dark:!bg-red-500/10 dark:!border-red-500/20 dark:hover:!bg-red-500/20 dark:hover:!border-red-500/30"
                                                                        title="Buka kunci (Admin/Manager) — cabut TTD petugas, entri jadi draft">
                                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-8 4h10a2 2 0 012 2v5a2 2 0 01-2 2H8a2 2 0 01-2-2v-5a2 2 0 012-2z" />
                                                                        </svg>
                                                                        Buka Kunci
                                                                    </x-ghost-button>
                                                                @endhasanyrole
                                                            @endif
                                                        @endif
                                                        @if (!$isFormLocked)
                                                            <x-outline-button type="button" wire:click.prevent="hapus('{{ $rowKey }}')" wire:confirm="Yakin hapus Inform Consent ini?"
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
                                                <td colspan="7" class="px-4 py-4 bg-surface-soft/60 dark:bg-gray-950/30">
                                                    <dl class="grid grid-cols-1 gap-x-8 gap-y-3 md:grid-cols-2">
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">PPA — Profesional Pemberi Asuhan</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $consent['petugasPemeriksa'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Nama Tindakan / Prosedur</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $consent['tindakan'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Diagnosa</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $consent['diagnosa'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Komplikasi</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $consent['komplikasi'] ?: '-' }}</dd>
                                                        </div>
                                                        <div class="md:col-span-2">
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Tujuan Tindakan / Terapi</dt>
                                                            <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $consent['tujuan'] ?: '-' }}</dd>
                                                        </div>
                                                        <div class="md:col-span-2">
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Risiko Tindakan / Terapi</dt>
                                                            <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $consent['resiko'] ?: '-' }}</dd>
                                                        </div>
                                                        <div class="md:col-span-2">
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Alternatif Tindakan / Terapi</dt>
                                                            <dd class="mt-0.5 whitespace-pre-line text-ink dark:text-gray-200">{{ $consent['alternatif'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Nama Pasien / Wali</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $consent['wali'] ?: '-' }}@if ($waliHub) <span class="text-muted">({{ $waliHub }})</span>@endif</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Saksi</dt>
                                                            <dd class="mt-0.5 text-ink dark:text-gray-200">{{ $consent['saksi'] ?: '-' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">TTD Pasien/Wali</dt>
                                                            <dd class="mt-0.5">
                                                                @if (!empty($consent['signature']))
                                                                    <span class="text-success-deep dark:text-green-300">Sudah TTD</span>
                                                                    <span class="text-sm text-muted-soft">— {{ $consent['signatureDate'] ?? '-' }}</span>
                                                                @else
                                                                    <x-badge variant="danger">Belum TTD</x-badge>
                                                                @endif
                                                            </dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-xs font-semibold tracking-wide uppercase text-muted-soft">Pemberi Informasi (Petugas)</dt>
                                                            <dd class="mt-0.5">
                                                                @if (!empty($consent['dokter']))
                                                                    <span class="text-ink dark:text-gray-200">{{ $consent['dokter'] }}</span>
                                                                    <span class="text-sm text-muted-soft">— {{ $consent['dokterDate'] ?? '-' }}</span>
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
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <span>Mode lihat — entri terkunci, tidak dapat diubah.</span>
                        </p>
                    @elseif ($rjNo && !$isFormLocked)
                        <p class="flex items-center gap-1.5 text-sm text-muted dark:text-gray-400">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Simpan draft dulu, lalu <strong>kunci</strong> lewat tombol <strong>TTD Petugas &amp; Kunci</strong> di kolom Pemberi Informasi.</span>
                        </p>
                    @else
                        <span></span>
                    @endif

                    <div class="flex flex-wrap items-center justify-end gap-3">
                        <x-secondary-button wire:click="closeModal">Tutup</x-secondary-button>

                        @if ($viewOnly)
                            <x-primary-button wire:click.prevent="cancelEdit" wire:target="cancelEdit"
                                wire:loading.attr="disabled" class="gap-1.5 min-w-[160px] justify-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                Selesai Melihat
                            </x-primary-button>
                        @elseif ($rjNo && !$isFormLocked)
                            @if ($editingKey)
                                <x-outline-button wire:click.prevent="cancelEdit" wire:target="cancelEdit"
                                    wire:loading.attr="disabled" class="gap-1.5"
                                    title="Kosongkan form untuk menambah tindakan lain — entri yang sudah tersimpan tidak berubah">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 4v16m8-8H4" />
                                    </svg>
                                    Entri Baru
                                </x-outline-button>
                            @endif
                            <x-primary-button wire:click.prevent="saveDraft" wire:loading.attr="disabled"
                                wire:target="saveDraft" class="gap-2 min-w-[160px] justify-center">
                                <span wire:loading.remove wire:target="saveDraft" class="flex items-center gap-1.5">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17 21v-8H7v8M7 3v5h8M5 3h11l4 4v12a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z" />
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

    {{-- Cetak component --}}
    <livewire:pages::components.modul-dokumen.u-g-d.inform-consent.cetak-inform-consent
        wire:key="cetak-inform-consent-ugd-{{ $rjNo ?? 'init' }}" />
</div>
