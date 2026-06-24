<?php

namespace App\Http\Livewire\Pages\Master\MasterPasien;

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Database\QueryException;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\SATUSEHAT\PatientTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\WithValidationToast\WithValidationToastTrait;
use Carbon\Carbon;

new class extends Component {
    use MasterPasienTrait, PatientTrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public string $formMode = 'create'; // create|edit
    public string $regNo = '';
    public array $renderVersions = [];

    // Data utama pasien
    public array $dataPasien = [];

    // Data LOV
    public array $jenisKelaminOptions = [];
    public array $agamaOptions = [];
    public array $statusPerkawinanOptions = [];
    public array $pendidikanOptions = [];
    public array $pekerjaanOptions = [];
    public array $golonganDarahOptions = [];
    public array $hubunganDgnPasienOptions = [];
    public array $statusOptions = [];

    public int $domisilSyncTick = 0;

    // Variabel untuk field tambahan di blade
    public string $bpjspasienCode = '';
    public string $pasienUuid = '';

    public function mount(): void
    {
        $this->registerAreas(['modal', 'alamat_identitas', 'alamat_domisil']);
        $this->initializeLOVOptions();
    }

    protected function initializeLOVOptions(): void
    {
        // JENIS KELAMIN - konsisten dengan trait
        $this->jenisKelaminOptions = [['id' => 0, 'desc' => 'Tidak diketahui'], ['id' => 1, 'desc' => 'Laki-laki'], ['id' => 2, 'desc' => 'Perempuan'], ['id' => 3, 'desc' => 'Tidak dapat ditentukan'], ['id' => 4, 'desc' => 'Tidak Mengisi']];

        // AGAMA - konsisten dengan trait
        $this->agamaOptions = [['id' => 1, 'desc' => 'Islam'], ['id' => 2, 'desc' => 'Kristen (Protestan)'], ['id' => 3, 'desc' => 'Katolik'], ['id' => 4, 'desc' => 'Hindu'], ['id' => 5, 'desc' => 'Budha'], ['id' => 6, 'desc' => 'Konghucu'], ['id' => 7, 'desc' => 'Penghayat'], ['id' => 8, 'desc' => 'Lain-lain']];

        // STATUS PERKAWINAN - konsisten dengan trait
        $this->statusPerkawinanOptions = [['id' => 1, 'desc' => 'Belum Kawin'], ['id' => 2, 'desc' => 'Kawin'], ['id' => 3, 'desc' => 'Cerai Hidup'], ['id' => 4, 'desc' => 'Cerai Mati']];

        // PENDIDIKAN - konsisten dengan trait
        $this->pendidikanOptions = [['id' => 0, 'desc' => 'Tidak Sekolah'], ['id' => 1, 'desc' => 'SD'], ['id' => 2, 'desc' => 'SLTP Sederajat'], ['id' => 3, 'desc' => 'SLTA Sederajat'], ['id' => 4, 'desc' => 'D1-D3'], ['id' => 5, 'desc' => 'D4'], ['id' => 6, 'desc' => 'S1'], ['id' => 7, 'desc' => 'S2'], ['id' => 8, 'desc' => 'S3']];

        // PEKERJAAN - konsisten dengan trait
        $this->pekerjaanOptions = [['id' => 0, 'desc' => 'Tidak Bekerja'], ['id' => 1, 'desc' => 'PNS'], ['id' => 2, 'desc' => 'TNI/POLRI'], ['id' => 3, 'desc' => 'BUMN'], ['id' => 4, 'desc' => 'Pegawai Swasta/ Wiraswasta'], ['id' => 5, 'desc' => 'Lain-Lain']];

        // GOLONGAN DARAH - UPDATE sesuai trait (lebih lengkap)
        $this->golonganDarahOptions = [['id' => 1, 'desc' => 'A'], ['id' => 2, 'desc' => 'B'], ['id' => 3, 'desc' => 'AB'], ['id' => 4, 'desc' => 'O'], ['id' => 5, 'desc' => 'A+'], ['id' => 6, 'desc' => 'A-'], ['id' => 7, 'desc' => 'B+'], ['id' => 8, 'desc' => 'B-'], ['id' => 9, 'desc' => 'AB+'], ['id' => 10, 'desc' => 'AB-'], ['id' => 11, 'desc' => 'O+'], ['id' => 12, 'desc' => 'O-'], ['id' => 13, 'desc' => 'Tidak Tahu'], ['id' => 14, 'desc' => 'O Rhesus'], ['id' => 15, 'desc' => '#']];

        // HUBUNGAN DENGAN PASIEN - konsisten dengan trait
        $this->hubunganDgnPasienOptions = [['id' => 1, 'desc' => 'Diri Sendiri'], ['id' => 2, 'desc' => 'Orang Tua'], ['id' => 3, 'desc' => 'Anak'], ['id' => 4, 'desc' => 'Suami / Istri'], ['id' => 5, 'desc' => 'Kerabat / Saudara'], ['id' => 6, 'desc' => 'Lain-lain']];

        // STATUS - konsisten dengan trait
        $this->statusOptions = [['id' => 0, 'desc' => 'Tidak Aktif / Batal'], ['id' => 1, 'desc' => 'Aktif / Hidup'], ['id' => 2, 'desc' => 'Meninggal']];
    }

    #[On('master.pasien.openCreate')]
    public function openCreate(): void
    {
        $this->resetFormFields();
        $this->formMode = 'create';

        // Isi data default dari template trait (regNo dikosongkan, di-generate saat save)
        $this->dataPasien = $this->getDefaultPasienTemplate();
        $this->dataPasien['pasien']['regNo'] = '';
        $this->regNo = '';
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-pasien-actions');
    }

    /** Generate regNo baru saat simpan (tidak bisa diinput manual) */
    protected function generateRegNo(): string
    {
        $maxRegNo = DB::table('rsmst_pasiens')->whereRaw("reg_no LIKE '%Z'")->whereRaw("REGEXP_LIKE(REPLACE(reg_no,'Z',''), '^\d+$')")->max(DB::raw("TO_NUMBER(REPLACE(reg_no,'Z',''))"));

        $nextNo = ($maxRegNo ?? 0) + 1;
        return sprintf('%07s', $nextNo) . 'Z';
    }

    #[On('master.pasien.openEdit')]
    public function openEdit(string $regNo): void
    {
        $this->resetFormFields();
        $this->formMode = 'edit';
        $this->regNo = $regNo;

        // Menggunakan trait untuk mendapatkan data pasien
        $this->dataPasien = $this->findDataMasterPasien($regNo);
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-pasien-actions');
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: 'master-pasien-actions');
        $this->resetFormFields();
    }

    protected function resetFormFields(): void
    {
        $this->reset(['dataPasien', 'regNo', 'bpjspasienCode', 'pasienUuid']);

        $this->resetValidation();
    }

    // Validation Rules - SUDAH BENAR
    protected function rules(): array
    {
        return [
            // regNo: di-generate otomatis saat create, tidak bisa diedit
            'dataPasien.pasien.regNo' => $this->formMode === 'edit' ? ['required', 'string'] : [],
            'dataPasien.pasien.regName' => ['required', 'string', 'min:3', 'max:200'],
            'dataPasien.pasien.tempatLahir' => ['required', 'string', 'max:100'],
            'dataPasien.pasien.tglLahir' => ['required', 'date_format:d/m/Y'],
            'dataPasien.pasien.jenisKelamin.jenisKelaminId' => ['required', 'numeric', 'in:1,2'],
            'dataPasien.pasien.agama.agamaId' => ['required', 'numeric'],
            'dataPasien.pasien.statusPerkawinan.statusPerkawinanId' => ['required', 'numeric'],
            'dataPasien.pasien.pendidikan.pendidikanId' => ['required', 'numeric'],
            'dataPasien.pasien.pekerjaan.pekerjaanId' => ['required', 'numeric'],
            'dataPasien.pasien.identitas.nik' => ['required', 'string', 'regex:/^\d{16}$/'],
            'dataPasien.pasien.identitas.alamat' => ['required', 'string', 'max:500'],
            'dataPasien.pasien.identitas.rt' => ['required', 'string', 'max:10'],
            'dataPasien.pasien.identitas.rw' => ['required', 'string', 'max:10'],
            'dataPasien.pasien.identitas.desaId' => ['required', 'string'],
            'dataPasien.pasien.identitas.kotaId' => ['required', 'string'],
            'dataPasien.pasien.identitas.propinsiId' => ['required', 'string'],
            'dataPasien.pasien.kontak.nomerTelponSelulerPasien' => ['required', 'string', 'regex:/^[0-9]{6,15}$/'],
            'dataPasien.pasien.hubungan.namaPenanggungJawab' => ['nullable', 'string', 'max:200'],
            'dataPasien.pasien.hubungan.nomerTelponSelulerPenanggungJawab' => ['nullable', 'string', 'regex:/^[0-9]{6,15}$/'],
            'dataPasien.pasien.hubungan.namaAyah' => ['nullable', 'string', 'max:200'],
            'dataPasien.pasien.hubungan.nomerTelponSelulerAyah' => ['nullable', 'string', 'regex:/^[0-9]{6,15}$/'],
            'dataPasien.pasien.hubungan.namaIbu' => ['nullable', 'string', 'max:200'],
            'dataPasien.pasien.hubungan.nomerTelponSelulerIbu' => ['nullable', 'string', 'regex:/^[0-9]{6,15}$/'],
        ];
    }

    protected function messages(): array
    {
        return [
            // RegNo
            'dataPasien.pasien.regNo.required' => 'ID Pasien wajib diisi.',
            'dataPasien.pasien.regNo.unique' => 'ID Pasien sudah digunakan, silakan gunakan ID lain.',
            'dataPasien.pasien.regNo.max' => 'ID Pasien maksimal :max karakter.',

            // RegName
            'dataPasien.pasien.regName.required' => 'Nama Pasien wajib diisi.',
            'dataPasien.pasien.regName.min' => 'Nama Pasien minimal :min karakter.',
            'dataPasien.pasien.regName.max' => 'Nama Pasien maksimal :max karakter.',

            // Tempat Lahir
            'dataPasien.pasien.tempatLahir.required' => 'Tempat Lahir wajib diisi.',
            'dataPasien.pasien.tempatLahir.max' => 'Tempat Lahir maksimal :max karakter.',

            // Tanggal Lahir
            'dataPasien.pasien.tglLahir.required' => 'Tanggal Lahir wajib diisi.',
            'dataPasien.pasien.tglLahir.date_format' => 'Format Tanggal Lahir harus dd/mm/yyyy.',

            // Jenis Kelamin
            'dataPasien.pasien.jenisKelamin.jenisKelaminId.required' => 'Jenis Kelamin wajib dipilih.',
            'dataPasien.pasien.jenisKelamin.jenisKelaminId.numeric' => 'Jenis Kelamin harus berupa angka.',
            'dataPasien.pasien.jenisKelamin.jenisKelaminId.in' => 'Jenis Kelamin harus Laki-laki atau Perempuan.',

            // Agama
            'dataPasien.pasien.agama.agamaId.required' => 'Agama wajib dipilih.',
            'dataPasien.pasien.agama.agamaId.numeric' => 'Agama harus berupa angka.',

            // Status Perkawinan
            'dataPasien.pasien.statusPerkawinan.statusPerkawinanId.required' => 'Status Perkawinan wajib dipilih.',
            'dataPasien.pasien.statusPerkawinan.statusPerkawinanId.numeric' => 'Status Perkawinan harus berupa angka.',

            // Pendidikan
            'dataPasien.pasien.pendidikan.pendidikanId.required' => 'Pendidikan wajib dipilih.',
            'dataPasien.pasien.pendidikan.pendidikanId.numeric' => 'Pendidikan harus berupa angka.',

            // Pekerjaan
            'dataPasien.pasien.pekerjaan.pekerjaanId.required' => 'Pekerjaan wajib dipilih.',
            'dataPasien.pasien.pekerjaan.pekerjaanId.numeric' => 'Pekerjaan harus berupa angka.',

            // Identitas - NIK
            'dataPasien.pasien.identitas.nik.required' => 'NIK wajib diisi.',
            'dataPasien.pasien.identitas.nik.regex' => 'NIK harus 16 digit angka.',

            // Identitas - Alamat
            'dataPasien.pasien.identitas.alamat.required' => 'Alamat wajib diisi.',
            'dataPasien.pasien.identitas.alamat.max' => 'Alamat maksimal :max karakter.',

            // Identitas - RT
            'dataPasien.pasien.identitas.rt.required' => 'RT wajib diisi.',
            'dataPasien.pasien.identitas.rt.max' => 'RT maksimal :max karakter.',

            // Identitas - RW
            'dataPasien.pasien.identitas.rw.required' => 'RW wajib diisi.',
            'dataPasien.pasien.identitas.rw.max' => 'RW maksimal :max karakter.',

            // Identitas - Desa & Kota
            'dataPasien.pasien.identitas.desaId.required' => 'Desa wajib dipilih (cari nama desa, kecamatan, atau kota).',
            'dataPasien.pasien.identitas.kotaId.required' => 'Kota/Kabupaten wajib diisi (otomatis dari pilihan desa).',
            'dataPasien.pasien.identitas.propinsiId.required' => 'Provinsi wajib diisi (otomatis dari pilihan desa).',

            // Kontak - No HP Pasien
            'dataPasien.pasien.kontak.nomerTelponSelulerPasien.required' => 'No. HP Pasien wajib diisi.',
            'dataPasien.pasien.kontak.nomerTelponSelulerPasien.regex' => 'No. HP Pasien harus 6-15 digit angka.',

            // Hubungan - Nama Penanggung Jawab
            'dataPasien.pasien.hubungan.namaPenanggungJawab.max' => 'Nama Penanggung Jawab maksimal :max karakter.',

            // Hubungan - No HP Penanggung Jawab
            'dataPasien.pasien.hubungan.nomerTelponSelulerPenanggungJawab.regex' => 'No. HP Penanggung Jawab harus 6-15 digit angka.',

            // Hubungan - Nama Ayah
            'dataPasien.pasien.hubungan.namaAyah.max' => 'Nama Ayah maksimal :max karakter.',

            // Hubungan - No HP Ayah
            'dataPasien.pasien.hubungan.nomerTelponSelulerAyah.regex' => 'No. HP Ayah harus 6-15 digit angka.',

            // Hubungan - Nama Ibu
            'dataPasien.pasien.hubungan.namaIbu.max' => 'Nama Ibu maksimal :max karakter.',

            // Hubungan - No HP Ibu
            'dataPasien.pasien.hubungan.nomerTelponSelulerIbu.regex' => 'No. HP Ibu harus 6-15 digit angka.',
        ];
    }

    // Save Data - SUDAH DIPERBAIKI
    public function save(): void
    {
        $this->validateWithToast();

        // update regDateStore -- akhir admisi
        if (empty($this->dataPasien['pasien']['regDateStore'])) {
            $this->dataPasien['pasien']['regDateStore'] = Carbon::now()->format('d/m/Y H:i:s');
        }

        $pasien = $this->dataPasien['pasien'] ?? [];
        $identitas = $pasien['identitas'] ?? [];
        $kontak = $pasien['kontak'] ?? [];

        // Untuk edit, regNo sudah ada
        $regNo = $pasien['regNo'] ?? null;
        if ($this->formMode !== 'create' && !$regNo) {
            $this->dispatch('toast', type: 'error', message: 'regNo kosong.');
            return;
        }

        $lockKey = 'lock:rsmst_pasiens:create';

        try {
            Cache::lock($lockKey, 15)->block(5, function () use ($pasien, $identitas, $kontak) {
                DB::transaction(function () use ($pasien, $identitas, $kontak) {
                    // Generate regNo di dalam lock+transaction untuk cegah race condition
                    if ($this->formMode === 'create') {
                        $regNo = $this->generateRegNo();
                        $this->dataPasien['pasien']['regNo'] = $regNo;
                        $this->regNo = $regNo;
                    } else {
                        $regNo = $pasien['regNo'];
                    }

                    $saveData = [
                        'reg_no' => $regNo,
                        'reg_name' => strtoupper($pasien['regName'] ?? ''),
                        'sex' => (int) ($pasien['jenisKelamin']['jenisKelaminId'] ?? 0) === 1 ? 'L' : ((int) ($pasien['jenisKelamin']['jenisKelaminId'] ?? 0) === 2 ? 'P' : null),
                        'birth_date' => !empty($pasien['tglLahir']) ? DB::raw("to_date('{$pasien['tglLahir']}', 'dd/mm/yyyy')") : null,
                        'birth_place' => strtoupper($pasien['tempatLahir'] ?? ''),
                        'nik_bpjs' => $identitas['nik'] ?? null,
                        'nokartu_bpjs' => $identitas['idbpjs'] ?? null,
                        'patient_uuid' => $identitas['patientUuid'] ?? null,
                        'blood' => $pasien['golonganDarah']['golonganDarahId'] ?? null,
                        'marital_status' => ($pasien['statusPerkawinan']['statusPerkawinanId'] ?? 1) == 1 ? 'S' : (($pasien['statusPerkawinan']['statusPerkawinanId'] ?? 1) == 2 ? 'M' : (($pasien['statusPerkawinan']['statusPerkawinanId'] ?? 1) == 3 ? 'D' : 'W')),
                        'rel_id' => $pasien['agama']['agamaId'] ?? '1',
                        'edu_id' => $pasien['pendidikan']['pendidikanId'] ?? '3',
                        'job_id' => $pasien['pekerjaan']['pekerjaanId'] ?? '4',
                        'kk' => strtoupper($pasien['hubungan']['namaPenanggungJawab'] ?? ''),
                        'nyonya' => strtoupper($pasien['hubungan']['namaIbu'] ?? ''),
                        'address' => $identitas['alamat'] ?? '',
                        'rt' => $identitas['rt'] ?? '',
                        'rw' => $identitas['rw'] ?? '',
                        'des_id' => $identitas['desaId'] ?? '',
                        'kec_id' => $identitas['kecamatanId'] ?? '',
                        'kab_id' => $identitas['kotaId'] ?? '3504',
                        'prop_id' => $identitas['propinsiId'] ?? '35',
                        'phone' => $kontak['nomerTelponSelulerPasien'] ?? '',
                    ];

                    if ($this->formMode === 'create') {
                        // Cek duplikat sebelum insert
                        if (DB::table('rsmst_pasiens')->where('reg_no', $regNo)->exists()) {
                            throw new \Exception("No RM {$regNo} sudah terpakai. Silakan ulangi proses pendaftaran.");
                        }
                        $saveData['reg_date'] = DB::raw('SYSDATE');
                        DB::table('rsmst_pasiens')->insert($saveData);
                    } else {
                        DB::table('rsmst_pasiens')->where('reg_no', $regNo)->update($saveData);
                    }

                    $pasienData = $this->findDataMasterPasien($regNo);

                    // ✅ incoming dari form tapi buang null/empty biar tidak hapus data lama
                    $incomingPasien = $this->dataPasien['pasien'] ?? [];

                    // ✅ (recommended) whitelist field master pasien saja
                    $allowed = ['regName', 'gelarDepan', 'gelarBelakang', 'namaPanggilan', 'tempatLahir', 'tglLahir', 'thn', 'bln', 'hari', 'jenisKelamin', 'agama', 'statusPerkawinan', 'pendidikan', 'pekerjaan', 'golonganDarah', 'kewarganegaraan', 'suku', 'bahasa', 'status', 'domisil', 'identitas', 'kontak', 'hubungan', 'regDate', 'pasientidakdikenal', 'regDateStore'];

                    $incomingPasien = array_intersect_key($incomingPasien, array_flip($allowed));
                    //khusus array [] checkbox
                    if (isset($incomingPasien['domisil']['samadgnidentitas'])) {
                        $pasienData['pasien']['domisil']['samadgnidentitas'] = $incomingPasien['domisil']['samadgnidentitas'];
                    }
                    // ✅ patch/merge (bukan overwrite total)
                    $pasienData['pasien'] = array_replace_recursive($pasienData['pasien'] ?? [], $incomingPasien);

                    // safety
                    $pasienData['pasien']['regNo'] = $regNo;

                    // Sinkronkan *Desc dengan *Id berdasarkan *Options — agar JSON tidak basi
                    // saat user ganti dropdown (sebelumnya hanya Id ter-update di Livewire state).
                    $this->syncOptionDescriptions($pasienData['pasien']);

                    $this->updateJsonMasterPasien($regNo, $pasienData);
                });
            });

            $this->dispatch('toast', type: 'success', message: $this->formMode === 'create' ? "Data pasien berhasil disimpan. No RM: {$this->regNo}" : 'Data pasien berhasil diupdate.');

            // Setelah create, switch ke mode edit agar user bisa lihat regNo & lanjut edit
            if ($this->formMode === 'create') {
                $this->formMode = 'edit';
            }

            $this->dispatch('master.pasien.saved');
        } catch (LockTimeoutException $e) {
            $this->dispatch('toast', type: 'error', message: 'Sistem sibuk, gagal memperoleh lock. Coba lagi.');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan data: ' . $e->getMessage());
            \Log::error('Error saving pasien: ' . $e->getMessage());
        }
    }

    // Delete Handler
    #[On('master.pasien.requestDelete')]
    public function deleteFromGrid(string $regNo): void
    {
        try {
            // Cek apakah pasien sudah punya transaksi
            $isUsed = DB::table('rstxn_rjhdrs')->where('reg_no', $regNo)->exists() || DB::table('rstxn_ugdhdrs')->where('reg_no', $regNo)->exists() || DB::table('rstxn_rihdrs')->where('reg_no', $regNo)->exists();

            if ($isUsed) {
                $this->dispatch('toast', type: 'error', message: 'Pasien sudah dipakai pada transaksi, tidak bisa dihapus.');
                return;
            }

            $deleted = DB::table('rsmst_pasiens')->where('reg_no', $regNo)->delete();

            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Data pasien tidak ditemukan.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'Data pasien berhasil dihapus.');
            $this->dispatch('master.pasien.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Pasien tidak bisa dihapus karena masih dipakai di data lain.');
                return;
            }

            throw $e;
        }
    }

    public function updated($name, $value)
    {
        if ($name === 'dataPasien.pasien.tglLahir') {
            $this->syncUmurFromTglLahir($value);
        }

        // Sync *Desc real-time saat user ganti dropdown — kalau tidak, JSON snapshot
        // akan simpan Desc default ("Laki-laki", "Belum Kawin", dst) meski Id sudah diubah.
        static $optionFieldMap = [
            'dataPasien.pasien.jenisKelamin.jenisKelaminId' => ['jenisKelamin', 'jenisKelaminId', 'jenisKelaminDesc', 'jenisKelaminOptions'],
            'dataPasien.pasien.agama.agamaId' => ['agama', 'agamaId', 'agamaDesc', 'agamaOptions'],
            'dataPasien.pasien.statusPerkawinan.statusPerkawinanId' => ['statusPerkawinan', 'statusPerkawinanId', 'statusPerkawinanDesc', 'statusPerkawinanOptions'],
            'dataPasien.pasien.pendidikan.pendidikanId' => ['pendidikan', 'pendidikanId', 'pendidikanDesc', 'pendidikanOptions'],
            'dataPasien.pasien.pekerjaan.pekerjaanId' => ['pekerjaan', 'pekerjaanId', 'pekerjaanDesc', 'pekerjaanOptions'],
            'dataPasien.pasien.golonganDarah.golonganDarahId' => ['golonganDarah', 'golonganDarahId', 'golonganDarahDesc', 'golonganDarahOptions'],
            'dataPasien.pasien.status.statusId' => ['status', 'statusId', 'statusDesc', 'statusOptions'],
            'dataPasien.pasien.hubungan.hubunganDgnPasien.hubunganDgnPasienId' => null,
        ];
        if (array_key_exists($name, $optionFieldMap)) {
            $map = $optionFieldMap[$name];
            if ($map) {
                [$group, $idKey, $descKey, $optionsKey] = $map;
                $node = $this->dataPasien['pasien'][$group] ?? [];
                $desc = $this->lookupDescFromOptions($node[$optionsKey] ?? [], $value, $idKey, $descKey);
                if ($desc !== null) {
                    $this->dataPasien['pasien'][$group][$descKey] = $desc;
                }
            }
        }

        $this->domisilSyncTick++;

        if ($name === 'dataPasien.pasien.domisil.samadgnidentitas') {
            $checked = $value;
            $this->dataPasien['pasien']['domisil']['samadgnidentitas'] = $checked;

            if ($checked === 'Y') {
                $this->dataPasien['pasien']['domisil']['alamat'] = $this->dataPasien['pasien']['identitas']['alamat'] ?? '';
                $this->dataPasien['pasien']['domisil']['rt'] = $this->dataPasien['pasien']['identitas']['rt'] ?? '';
                $this->dataPasien['pasien']['domisil']['rw'] = $this->dataPasien['pasien']['identitas']['rw'] ?? '';
                $this->dataPasien['pasien']['domisil']['kodepos'] = $this->dataPasien['pasien']['identitas']['kodepos'] ?? '';

                // penting: kota dulu, baru desa (biar nggak ke-reset)
                $this->dataPasien['pasien']['domisil']['kotaId'] = $this->dataPasien['pasien']['identitas']['kotaId'] ?? '';
                $this->dataPasien['pasien']['domisil']['kotaName'] = $this->dataPasien['pasien']['identitas']['kotaName'] ?? '';
                $this->dataPasien['pasien']['domisil']['propinsiId'] = $this->dataPasien['pasien']['identitas']['propinsiId'] ?? '';
                $this->dataPasien['pasien']['domisil']['propinsiName'] = $this->dataPasien['pasien']['identitas']['propinsiName'] ?? '';

                $this->dataPasien['pasien']['domisil']['desaId'] = $this->dataPasien['pasien']['identitas']['desaId'] ?? '';
                $this->dataPasien['pasien']['domisil']['desaName'] = $this->dataPasien['pasien']['identitas']['desaName'] ?? '';
                $this->dataPasien['pasien']['domisil']['kecamatanid'] = $this->dataPasien['pasien']['identitas']['kecamatanid'] ?? '';
                $this->dataPasien['pasien']['domisil']['kecamatanName'] = $this->dataPasien['pasien']['identitas']['kecamatanName'] ?? '';

                $this->dataPasien['pasien']['domisil']['negara'] = $this->dataPasien['pasien']['identitas']['negara'] ?? '';
            } else {
                $this->dataPasien['pasien']['domisil']['alamat'] = '';
                $this->dataPasien['pasien']['domisil']['rt'] = '';
                $this->dataPasien['pasien']['domisil']['rw'] = '';
                $this->dataPasien['pasien']['domisil']['kodepos'] = '';
                $this->dataPasien['pasien']['domisil']['desaId'] = '';
                $this->dataPasien['pasien']['domisil']['desaName'] = '';
                $this->dataPasien['pasien']['domisil']['kecamatanid'] = '';
                $this->dataPasien['pasien']['domisil']['kecamatanName'] = '';
                $this->dataPasien['pasien']['domisil']['kotaId'] = '';
                $this->dataPasien['pasien']['domisil']['kotaName'] = '';
                $this->dataPasien['pasien']['domisil']['propinsiId'] = '';
                $this->dataPasien['pasien']['domisil']['propinsiName'] = '';
                $this->dataPasien['pasien']['domisil']['negara'] = '';
            }
        }
    }

    private function syncUmurFromTglLahir($value): void
    {
        if (blank($value)) {
            $this->resetUmur();
            $this->dataPasien = $this->dataPasien;
            return;
        }

        try {
            $dob = Carbon::createFromFormat('d/m/Y', $value)->startOfDay();

            if ($dob->isFuture()) {
                $this->resetUmur();
                $this->dataPasien = $this->dataPasien;
                return;
            }

            $diff = $dob->diff(Carbon::today());

            $this->dataPasien['pasien']['thn'] = (string) $diff->y;
            $this->dataPasien['pasien']['bln'] = (string) $diff->m;
            $this->dataPasien['pasien']['hari'] = (string) $diff->d;

            // ✅ penting
            $this->dataPasien = $this->dataPasien;
        } catch (\Throwable $e) {
            $this->resetUmur();
            $this->dataPasien = $this->dataPasien;
        }
    }

    private function resetUmur(): void
    {
        $this->dataPasien['pasien']['thn'] = '';
        $this->dataPasien['pasien']['bln'] = '';
        $this->dataPasien['pasien']['hari'] = '';
    }

    /**
     * Cari Desc dari array options berdasarkan Id.
     * Loose compare (== bukan ===) karena Id di JSON kadang string ("2"), kadang int (2).
     */
    private function lookupDescFromOptions(array $options, $id, string $idKey, string $descKey): ?string
    {
        foreach ($options as $opt) {
            if (isset($opt[$idKey]) && $opt[$idKey] == $id) {
                return $opt[$descKey] ?? null;
            }
        }
        return null;
    }

    /**
     * Sinkronkan semua *Desc dari *Id+*Options di JSON pasien — defensive sebelum simpan.
     * Diperlukan karena dropdown Livewire hanya update Id, Desc bisa tetap nilai default lama.
     */
    private function syncOptionDescriptions(array &$pasien): void
    {
        $groups = [
            ['jenisKelamin', 'jenisKelaminId', 'jenisKelaminDesc', 'jenisKelaminOptions'],
            ['agama', 'agamaId', 'agamaDesc', 'agamaOptions'],
            ['statusPerkawinan', 'statusPerkawinanId', 'statusPerkawinanDesc', 'statusPerkawinanOptions'],
            ['pendidikan', 'pendidikanId', 'pendidikanDesc', 'pendidikanOptions'],
            ['pekerjaan', 'pekerjaanId', 'pekerjaanDesc', 'pekerjaanOptions'],
            ['golonganDarah', 'golonganDarahId', 'golonganDarahDesc', 'golonganDarahOptions'],
            ['status', 'statusId', 'statusDesc', 'statusOptions'],
        ];
        foreach ($groups as [$group, $idKey, $descKey, $optionsKey]) {
            $node = $pasien[$group] ?? null;
            if (!is_array($node) || !isset($node[$idKey])) {
                continue;
            }
            $desc = $this->lookupDescFromOptions($node[$optionsKey] ?? [], $node[$idKey], $idKey, $descKey);
            if ($desc !== null) {
                $pasien[$group][$descKey] = $desc;
            }
        }

        // hubunganDgnPasien — nested di pasien.hubungan
        $hub = $pasien['hubungan']['hubunganDgnPasien'] ?? null;
        if (is_array($hub) && isset($hub['hubunganDgnPasienId'])) {
            $desc = $this->lookupDescFromOptions(
                $hub['hubunganDgnPasienOptions'] ?? [],
                $hub['hubunganDgnPasienId'],
                'hubunganDgnPasienId',
                'hubunganDgnPasienDesc',
            );
            if ($desc !== null) {
                $pasien['hubungan']['hubunganDgnPasien']['hubunganDgnPasienDesc'] = $desc;
            }
        }
    }

    #[On('lov.selected.desa_identitas')]
    public function desa_identitas(string $target, array $payload): void
    {
        $this->dataPasien['pasien']['identitas']['desaId'] = $payload['des_id'] ?? '';
        $this->dataPasien['pasien']['identitas']['desaName'] = $payload['des_name'] ?? '';
        $this->dataPasien['pasien']['identitas']['kecamatanId'] = $payload['kec_id'] ?? '';
        $this->dataPasien['pasien']['identitas']['kecamatanName'] = $payload['kec_name'] ?? '';
        // Auto-fill kota & provinsi dari desa
        $this->dataPasien['pasien']['identitas']['kotaId'] = $payload['kab_id'] ?? '';
        $this->dataPasien['pasien']['identitas']['kotaName'] = $payload['kab_name'] ?? '';
        $this->dataPasien['pasien']['identitas']['propinsiId'] = $payload['prop_id'] ?? '';
        $this->dataPasien['pasien']['identitas']['propinsiName'] = $payload['prop_name'] ?? '';
        $this->incrementVersion('alamat_identitas');
    }

    #[On('lov.selected.desa_domisil')]
    public function desa_domisil(string $target, array $payload): void
    {
        $this->dataPasien['pasien']['domisil']['desaId'] = $payload['des_id'] ?? '';
        $this->dataPasien['pasien']['domisil']['desaName'] = $payload['des_name'] ?? '';
        $this->dataPasien['pasien']['domisil']['kecamatanId'] = $payload['kec_id'] ?? '';
        $this->dataPasien['pasien']['domisil']['kecamatanName'] = $payload['kec_name'] ?? '';
        // Auto-fill kota & provinsi dari desa
        $this->dataPasien['pasien']['domisil']['kotaId'] = $payload['kab_id'] ?? '';
        $this->dataPasien['pasien']['domisil']['kotaName'] = $payload['kab_name'] ?? '';
        $this->dataPasien['pasien']['domisil']['propinsiId'] = $payload['prop_id'] ?? '';
        $this->dataPasien['pasien']['domisil']['propinsiName'] = $payload['prop_name'] ?? '';
        $this->incrementVersion('alamat_domisil');
    }


    /**
     * Update atau generate UUID Pasien dari SATUSEHAT berdasarkan NIK
     */
    public function UpdatepatientUuid(string $nik = ''): void
    {
        // Validasi NIK
        if (empty($nik)) {
            $this->dispatch('toast', type: 'warning', message: 'NIK pasien wajib diisi terlebih dahulu.');
            return;
        }

        if (strlen($nik) !== 16) {
            $this->dispatch('toast', type: 'warning', message: 'NIK harus 16 digit.');
            return;
        }

        try {
            // 1. Inisialisasi koneksi SATUSEHAT
            $this->initializeSatuSehat();

            // 2. Cari Patient berdasarkan NIK
            $searchResult = $this->searchPatient(['nik' => $nik]);
            $entries = collect($searchResult['entry'] ?? []);

            // 3. Jika tidak ada, buat pasien baru
            if ($entries->isEmpty()) {
                $this->dispatch('toast', type: 'warning', message: "Tidak ada pasien ditemukan dengan NIK: {$nik}. Persiapan create pasien baru.");

                // Siapkan data untuk create patient
                $patientData = [
                    'name' => $this->dataPasien['pasien']['regName'] ?? '',
                    'given_name' => $this->dataPasien['pasien']['regName'] ?? '',
                    'family_name' => '',
                    'birth_date' => $this->formatDateToYmd($this->dataPasien['pasien']['tglLahir'] ?? ''),
                    'gender' => $this->mapGenderToSatusehat($this->dataPasien['pasien']['jenisKelamin']['jenisKelaminId'] ?? 0),
                    'phone' => $this->dataPasien['pasien']['kontak']['nomerTelponSelulerPasien'] ?? '',
                    'nik' => $nik,
                    'bpjs_number' => $this->dataPasien['pasien']['identitas']['idbpjs'] ?? null,
                    'marital_status' => $this->mapMaritalStatusToSatusehat($this->dataPasien['pasien']['statusPerkawinan']['statusPerkawinanId'] ?? 1),
                    'address' => $this->buildAddressPayload($this->dataPasien['pasien']['identitas'] ?? []),
                ];

                // Create patient ke SATUSEHAT
                $result = $this->createPatient($patientData);
                $createdUuid = $result['id'] ?? null;

                if ($createdUuid) {
                    // Simpan UUID ke data pasien
                    $this->dataPasien['pasien']['identitas']['patientUuid'] = $createdUuid;
                    $this->pasienUuid = $createdUuid;

                    $this->dispatch('toast', type: 'success', message: "Pasien baru berhasil dibuat di SATUSEHAT (UUID: {$createdUuid})");
                } else {
                    $this->dispatch('toast', type: 'error', message: 'Gagal membuat pasien baru di SATUSEHAT.');
                }

                return;
            }

            // 4. Ambil UUID Patient pertama dari hasil pencarian
            $newUuid = $entries->pluck('resource.id')->first();
            $currentUuid = $this->dataPasien['pasien']['identitas']['patientUuid'] ?? null;

            // 5. Jika belum ada UUID tersimpan, set dan notify
            if (empty($currentUuid)) {
                $this->dataPasien['pasien']['identitas']['patientUuid'] = $newUuid;
                $this->pasienUuid = $newUuid;

                $this->dispatch('toast', type: 'success', message: "patientUuid di-set ke {$newUuid}");
                return;
            }

            // 6. Jika UUID sudah sama, beri info
            if ($currentUuid === $newUuid) {
                $this->dispatch('toast', type: 'info', message: 'patientUuid sudah sesuai dengan data terbaru');
                return;
            }

            // 7. Jika berbeda, cek apakah UUID lama masih ada dalam hasil pencarian
            $oldStillExists = $entries->pluck('resource.id')->contains($currentUuid);

            if ($oldStillExists) {
                $this->dispatch('toast', type: 'success', message: "patientUuid lama ({$currentUuid}) masih ditemukan");
            } else {
                $this->dispatch('toast', type: 'warning', message: "patientUuid lama ({$currentUuid}) tidak ada di hasil terbaru, disarankan update ke UUID baru: {$newUuid}");

                // Optional: Auto update ke UUID baru
                // $this->dataPasien['pasien']['identitas']['patientUuid'] = $newUuid;
                // $this->pasienUuid = $newUuid;
            }
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Error saat memproses UUID: ' . $e->getMessage());
            \Log::error('Error UpdatepatientUuid: ' . $e->getMessage());
        }
    }

    /**
     * Format tanggal dari dd/mm/yyyy ke yyyy-mm-dd
     */
    private function formatDateToYmd(?string $date): ?string
    {
        if (empty($date)) {
            return null;
        }

        try {
            $parts = explode('/', $date);
            if (count($parts) === 3) {
                return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
            }
        } catch (\Exception $e) {
            return null;
        }

        return null;
    }

    /**
     * Map gender ID ke kode SATUSEHAT
     */
    private function mapGenderToSatusehat(int $genderId): string
    {
        return match ($genderId) {
            1 => 'male',
            2 => 'female',
            default => 'unknown',
        };
    }

    /**
     * Map status perkawinan ID ke kode SATUSEHAT
     */
    private function mapMaritalStatusToSatusehat(int $statusId): string
    {
        return match ($statusId) {
            1 => 'S', // Belum Kawin -> Never Married
            2 => 'M', // Kawin -> Married
            3 => 'D', // Cerai Hidup -> Divorced
            4 => 'W', // Cerai Mati -> Widowed
            default => 'U', // Unknown
        };
    }

    /**
     * Build address payload untuk SATUSEHAT
     */
    private function buildAddressPayload(array $identitas): array
    {
        $address = [];

        if (!empty($identitas['alamat'])) {
            $address['line'] = [$identitas['alamat']];
        }

        if (!empty($identitas['desaName'])) {
            $address['city'] = $identitas['desaName'];
        }

        if (!empty($identitas['kecamatanName'])) {
            $address['district'] = $identitas['kecamatanName'];
        }

        if (!empty($identitas['kotaName'])) {
            $address['city'] = $identitas['kotaName'];
        }

        if (!empty($identitas['propinsiName'])) {
            $address['state'] = $identitas['propinsiName'];
        }

        if (!empty($identitas['kodepos'])) {
            $address['postalCode'] = $identitas['kodepos'];
        }

        $address['country'] = 'ID';
        $address['use'] = 'home';

        return $address;
    }
};

?>


<div>
    <x-modal name="master-pasien-actions" size="full" height="full" focusable>
        <x-dirty-modal-content
            name="master-pasien-actions"
            event="master.pasien.saved"
            label="Pasien"
            :wireKey="$this->renderKey('modal', [$formMode, $regNo])">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 bg-surface-soft">

                <div class="relative flex items-start justify-between gap-4">
                    <div class="flex items-start min-w-0 gap-3">
                        <div
                            class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15 shrink-0">
                            <img src="{{ asset('images/Logogram black solid.png') }}" alt="RSI Madinah"
                                class="block w-6 h-6 dark:hidden" />
                            <img src="{{ asset('images/Logogram white solid.png') }}" alt="RSI Madinah"
                                class="hidden w-6 h-6 dark:block" />
                        </div>

                        <div class="min-w-0">
                            {{-- Eyebrow: aksi (brand, bold) + mode --}}
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-xs font-bold tracking-[0.12em] uppercase text-brand-green dark:text-brand-lime">{{ $formMode === 'edit' ? 'Ubah Data Pasien' : 'Tambah Data Pasien' }}</span>
                                <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
                                    {{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Tambah' }}
                                </x-badge>
                            </div>

                            @if (!empty($dataPasien['pasien']['regName']))
                                {{-- Hero: nama pasien --}}
                                {{-- No RM (mono, gelap, agak tebal) --}}
                                <div class="mt-1.5 font-mono text-sm font-semibold tracking-wide text-ink dark:text-gray-100">
                                    No. RM {{ $dataPasien['pasien']['regNo'] ?? '-' }}
                                </div>
                                {{-- Nama /(JK) — hero serif --}}
                                <h2 class="mt-1 font-semibold text-3xl leading-tight text-ink dark:text-white">
                                    {{ strtoupper($dataPasien['pasien']['regName']) }}
                                    @if (!empty($dataPasien['pasien']['jenisKelamin']['jenisKelaminDesc']))
                                        <span class="font-sans text-lg font-normal text-muted dark:text-gray-400">/ ({{ $dataPasien['pasien']['jenisKelamin']['jenisKelaminDesc'] }})</span>
                                    @endif
                                </h2>
                                {{-- Tgl Lahir (umur) — agak tebal, umur aksen brand --}}
                                <div class="mt-1.5 text-sm font-medium leading-relaxed text-body dark:text-gray-300">
                                    <span class="font-normal text-muted">Tgl Lahir:</span> {{ $dataPasien['pasien']['tglLahir'] ?? '-' }}
                                    @if (isset($dataPasien['pasien']['thn']))
                                        <span class="font-semibold text-brand-green dark:text-brand-lime">({{ $dataPasien['pasien']['thn'] ?? 0 }} th {{ $dataPasien['pasien']['bln'] ?? 0 }} bln {{ $dataPasien['pasien']['hari'] ?? 0 }} hr)</span>
                                    @endif
                                </div>
                                {{-- Alamat — agak tebal biar mudah dibaca --}}
                                @if (!empty($dataPasien['pasien']['identitas']['alamat']))
                                    <div class="text-sm font-medium leading-relaxed text-body dark:text-gray-300">
                                        {{ $dataPasien['pasien']['identitas']['alamat'] }}
                                    </div>
                                @endif
                            @else
                                <h2 class="mt-1 font-semibold text-3xl leading-tight text-ink dark:text-white">Pasien Baru</h2>
                                <p class="mt-0.5 text-sm text-muted dark:text-gray-400">
                                    Lengkapi informasi pasien untuk kebutuhan aplikasi.
                                </p>
                            @endif
                        </div>
                    </div>

                    <x-icon-button color="gray" type="button" x-on:click="tryClose()" class="shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>


            {{-- BODY — bertab (pola EMR: Alpine, instan tanpa reload) --}}
            <div class="flex-1 px-4 py-4 overflow-y-auto bg-surface-soft dark:bg-gray-950/20" x-enter-chain>
                <div class="w-full mx-auto" x-data="{ activeTab: 'data-pasien' }">

                    {{-- TAB NAV --}}
                    <x-tabs variant="underline" class="mb-4">
                        <x-tab active-expr="activeTab === 'data-pasien'" x-on:click="activeTab = 'data-pasien'">Data Pasien</x-tab>
                        <x-tab active-expr="activeTab === 'identitas-alamat'" x-on:click="activeTab = 'identitas-alamat'">Identitas &amp; Alamat</x-tab>
                        <x-tab active-expr="activeTab === 'kontak-keluarga'" x-on:click="activeTab = 'kontak-keluarga'">Kontak &amp; Keluarga</x-tab>
                        @if ($formMode === 'edit' && !empty($regNo))
                            <x-tab active-expr="activeTab === 'rekam-medis'" x-on:click="activeTab = 'rekam-medis'">Rekam Medis</x-tab>
                        @endif
                    </x-tabs>

                    {{-- TAB: DATA PASIEN --}}
                    <div x-show="activeTab === 'data-pasien'" x-cloak x-transition.opacity.duration.200ms class="space-y-4">
                        {{-- DATA DASAR PASIEN --}}
                        @include('pages.master.master-pasien.master-pasien-actions-data-dasar-pasien')

                        {{-- DATA SOSIAL --}}
                        @include('pages.master.master-pasien.master-pasien-actions-data-sosial')

                        {{-- DATA BUDAYA --}}
                        @include('pages.master.master-pasien.master-pasien-actions-data-budaya')
                    </div>

                    {{-- TAB: IDENTITAS & ALAMAT --}}
                    <div x-show="activeTab === 'identitas-alamat'" x-cloak x-transition.opacity.duration.200ms class="space-y-4">
                        {{-- IDENTITAS --}}
                        @include('pages.master.master-pasien.master-pasien-actions-identitas')

                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            {{-- ALAMAT IDENTITAS --}}
                            @include('pages.master.master-pasien.master-pasien-actions-alamat-identitas')

                            {{-- ALAMAT DOMISILI --}}
                            @include('pages.master.master-pasien.master-pasien-actions-alamat-domisili')
                        </div>
                    </div>

                    {{-- TAB: KONTAK & KELUARGA --}}
                    <div x-show="activeTab === 'kontak-keluarga'" x-cloak x-transition.opacity.duration.200ms>
                        <div class="grid items-start grid-cols-1 gap-2 lg:grid-cols-2">
                            @include('pages.master.master-pasien.master-pasien-actions-kontak')
                            @include('pages.master.master-pasien.master-pasien-actions-hubungan-keluarga')
                        </div>
                    </div>

                    {{-- TAB: REKAM MEDIS — komponen yang sama dgn EMR RJ/UGD/RI (level pasien/regNo):
                         riwayat kunjungan + filter + tombol Resume Medis. Hanya mode edit. --}}
                    @if ($formMode === 'edit' && !empty($regNo))
                        <div x-show="activeTab === 'rekam-medis'" x-cloak x-transition.opacity.duration.200ms>
                            <livewire:pages::components.rekam-medis.rekam-medis-display.rekam-medis-display
                                :regNo="$regNo"
                                wire:key="master-pasien-rekam-medis-display-{{ $regNo }}" />
                        </div>
                    @endif

                </div>
            </div>

            {{-- FOOTER --}}
            @include('pages.master.master-pasien.master-pasien-actions-footer')

        </x-dirty-modal-content>
    </x-modal>
</div>
