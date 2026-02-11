<?php

namespace App\Http\Livewire\Pages\Master\MasterPasien;

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Database\QueryException;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\SATUSEHAT\PatientTrait;

new class extends Component {
    use MasterPasienTrait, PatientTrait;

    public string $formMode = 'create'; // create|edit
    public string $regNo = '';

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
        $this->resetForm();
        $this->formMode = 'create';

        // Isi data default dari template trait
        $this->dataPasien = $this->getDefaultPasienTemplate();
        // Generate regNo baru
        $jmlPasien = DB::table('rsmst_pasiens')->count();
        $this->dataPasien['pasien']['regNo'] = sprintf('%07s', $jmlPasien + 1) . 'Z';
        $this->regNo = $this->dataPasien['pasien']['regNo'];
        $this->dispatch('open-modal', name: 'master-pasien-actions');
    }

    #[On('master.pasien.openEdit')]
    public function openEdit(string $regNo): void
    {
        $this->resetForm();
        $this->formMode = 'edit';
        $this->regNo = $regNo;

        // Menggunakan trait untuk mendapatkan data pasien
        $this->dataPasien = $this->findDataMasterPasien($regNo);
        $this->dispatch('open-modal', name: 'master-pasien-actions');
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'master-pasien-actions');
    }

    protected function resetForm(): void
    {
        $this->reset(['dataPasien', 'regNo', 'bpjspasienCode', 'pasienUuid']);

        $this->resetValidation();
    }

    // Validation Rules - SUDAH BENAR
    protected function rules(): array
    {
        return [
            'dataPasien.pasien.regNo' => ['required', 'string', 'max:50', $this->formMode === 'create' ? Rule::unique('rsmst_pasiens', 'reg_no') : Rule::unique('rsmst_pasiens', 'reg_no')->ignore($this->dataPasien['pasien']['regNo'] ?? '', 'reg_no')],
            'dataPasien.pasien.regName' => ['required', 'string', 'min:3', 'max:200'],
            'dataPasien.pasien.tempatLahir' => ['required', 'string', 'max:100'],
            'dataPasien.pasien.tglLahir' => ['required', 'date_format:d/m/Y'],
            'dataPasien.pasien.jenisKelamin.jenisKelaminId' => ['required', 'numeric'],
            'dataPasien.pasien.agama.agamaId' => ['required', 'numeric'],
            'dataPasien.pasien.statusPerkawinan.statusPerkawinanId' => ['required', 'numeric'],
            'dataPasien.pasien.pendidikan.pendidikanId' => ['required', 'numeric'],
            'dataPasien.pasien.pekerjaan.pekerjaanId' => ['required', 'numeric'],
            'dataPasien.pasien.identitas.nik' => ['required', 'digits:16'],
            'dataPasien.pasien.identitas.alamat' => ['required', 'string', 'max:500'],
            'dataPasien.pasien.identitas.rt' => ['required', 'string', 'max:10'],
            'dataPasien.pasien.identitas.rw' => ['required', 'string', 'max:10'],
            'dataPasien.pasien.kontak.nomerTelponSelulerPasien' => ['required', 'digits_between:6,15'],
            'dataPasien.pasien.hubungan.namaPenanggungJawab' => ['required', 'string', 'min:3', 'max:200'],
            'dataPasien.pasien.hubungan.nomerTelponSelulerPenanggungJawab' => ['required', 'digits_between:6,15'],
            'dataPasien.pasien.hubungan.namaAyah' => ['required', 'string', 'min:3', 'max:200'],
            'dataPasien.pasien.hubungan.nomerTelponSelulerAyah' => ['required', 'digits_between:6,15'],
            'dataPasien.pasien.hubungan.namaIbu' => ['required', 'string', 'min:3', 'max:200'],
            'dataPasien.pasien.hubungan.nomerTelponSelulerIbu' => ['required', 'digits_between:6,15'],
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
            'dataPasien.pasien.identitas.nik.digits' => 'NIK harus 16 digit.',

            // Identitas - Alamat
            'dataPasien.pasien.identitas.alamat.required' => 'Alamat wajib diisi.',
            'dataPasien.pasien.identitas.alamat.max' => 'Alamat maksimal :max karakter.',

            // Identitas - RT
            'dataPasien.pasien.identitas.rt.required' => 'RT wajib diisi.',
            'dataPasien.pasien.identitas.rt.max' => 'RT maksimal :max karakter.',

            // Identitas - RW
            'dataPasien.pasien.identitas.rw.required' => 'RW wajib diisi.',
            'dataPasien.pasien.identitas.rw.max' => 'RW maksimal :max karakter.',

            // Kontak - No HP Pasien
            'dataPasien.pasien.kontak.nomerTelponSelulerPasien.required' => 'No. HP Pasien wajib diisi.',
            'dataPasien.pasien.kontak.nomerTelponSelulerPasien.digits_between' => 'No. HP Pasien harus antara :min sampai :max digit.',

            // Hubungan - Nama Penanggung Jawab
            'dataPasien.pasien.hubungan.namaPenanggungJawab.required' => 'Nama Penanggung Jawab wajib diisi.',
            'dataPasien.pasien.hubungan.namaPenanggungJawab.min' => 'Nama Penanggung Jawab minimal :min karakter.',
            'dataPasien.pasien.hubungan.namaPenanggungJawab.max' => 'Nama Penanggung Jawab maksimal :max karakter.',

            // Hubungan - No HP Penanggung Jawab
            'dataPasien.pasien.hubungan.nomerTelponSelulerPenanggungJawab.required' => 'No. HP Penanggung Jawab wajib diisi.',
            'dataPasien.pasien.hubungan.nomerTelponSelulerPenanggungJawab.digits_between' => 'No. HP Penanggung Jawab harus antara :min sampai :max digit.',

            // Hubungan - Nama Ayah
            'dataPasien.pasien.hubungan.namaAyah.required' => 'Nama Ayah wajib diisi.',
            'dataPasien.pasien.hubungan.namaAyah.min' => 'Nama Ayah minimal :min karakter.',
            'dataPasien.pasien.hubungan.namaAyah.max' => 'Nama Ayah maksimal :max karakter.',

            // Hubungan - No HP Ayah
            'dataPasien.pasien.hubungan.nomerTelponSelulerAyah.required' => 'No. HP Ayah wajib diisi.',
            'dataPasien.pasien.hubungan.nomerTelponSelulerAyah.digits_between' => 'No. HP Ayah harus antara :min sampai :max digit.',

            // Hubungan - Nama Ibu
            'dataPasien.pasien.hubungan.namaIbu.required' => 'Nama Ibu wajib diisi.',
            'dataPasien.pasien.hubungan.namaIbu.min' => 'Nama Ibu minimal :min karakter.',
            'dataPasien.pasien.hubungan.namaIbu.max' => 'Nama Ibu maksimal :max karakter.',

            // Hubungan - No HP Ibu
            'dataPasien.pasien.hubungan.nomerTelponSelulerIbu.required' => 'No. HP Ibu wajib diisi.',
            'dataPasien.pasien.hubungan.nomerTelponSelulerIbu.digits_between' => 'No. HP Ibu harus antara :min sampai :max digit.',
        ];
    }

    // Save Data - SUDAH DIPERBAIKI
    public function save(): void
    {
        $this->validate();
        try {
            // Prepare data for database - SUDAH DIPERBAIKI
            $saveData = [
                'reg_no' => $this->dataPasien['pasien']['regNo'],
                'reg_name' => strtoupper($this->dataPasien['pasien']['regName']),
                'sex' => ($this->dataPasien['pasien']['jenisKelamin']['jenisKelaminId'] ?? 0) == 1 ? 'L' : 'P',
                'birth_date' => DB::raw("to_date('" . ($this->dataPasien['pasien']['tglLahir'] ?? '') . "','dd/mm/yyyy')"),
                'birth_place' => strtoupper($this->dataPasien['pasien']['tempatLahir'] ?? ''),
                'nik_bpjs' => $this->dataPasien['pasien']['identitas']['nik'] ?? '',
                'nokartu_bpjs' => $this->dataPasien['pasien']['identitas']['idbpjs'] ?? null,
                'blood' => $this->dataPasien['pasien']['golonganDarah']['golonganDarahId'] ?? null,
                'marital_status' => ($this->dataPasien['pasien']['statusPerkawinan']['statusPerkawinanId'] ?? 1) == 1 ? 'S' : (($this->dataPasien['pasien']['statusPerkawinan']['statusPerkawinanId'] ?? 1) == 2 ? 'M' : (($this->dataPasien['pasien']['statusPerkawinan']['statusPerkawinanId'] ?? 1) == 3 ? 'D' : 'W')),
                'rel_id' => $this->dataPasien['pasien']['agama']['agamaId'] ?? '1',
                'edu_id' => $this->dataPasien['pasien']['pendidikan']['pendidikanId'] ?? '3',
                'job_id' => $this->dataPasien['pasien']['pekerjaan']['pekerjaanId'] ?? '4',
                'kk' => strtoupper($this->dataPasien['pasien']['hubungan']['namaPenanggungJawab'] ?? ''),
                'nyonya' => strtoupper($this->dataPasien['pasien']['hubungan']['namaIbu'] ?? ''),
                'address' => $this->dataPasien['pasien']['identitas']['alamat'] ?? '',
                'phone' => $this->dataPasien['pasien']['kontak']['nomerTelponSelulerPasien'] ?? '',
                'rt' => $this->dataPasien['pasien']['identitas']['rt'] ?? '',
                'rw' => $this->dataPasien['pasien']['identitas']['rw'] ?? '',
                'kab_id' => $this->dataPasien['pasien']['identitas']['kotaId'] ?? '3504',
                'prop_id' => $this->dataPasien['pasien']['identitas']['propinsiId'] ?? '35',
            ];

            // Tambahkan field tambahan jika ada
            if (!empty($this->bpjspasienCode)) {
                $saveData['bpjspasien_code'] = $this->bpjspasienCode;
            }

            if (!empty($this->pasienUuid)) {
                $saveData['pasien_uuid'] = $this->pasienUuid;
            }

            if ($this->formMode === 'create') {
                $saveData['reg_date'] = DB::raw('SYSDATE');
                DB::table('rsmst_pasiens')->insert($saveData);

                // Langsung auto-save JSON menggunakan trait
                $pasienData = $this->findDataMasterPasien($this->dataPasien['pasien']['regNo']);
                if (!isset($pasienData['errorMessages'])) {
                    $this->autoSaveToJson($this->dataPasien['pasien']['regNo'], $pasienData);
                }

                $this->dispatch('toast', type: 'success', message: 'Data pasien berhasil disimpan.');
            } else {
                DB::table('rsmst_pasiens')
                    ->where('reg_no', $this->dataPasien['pasien']['regNo'])
                    ->update($saveData);

                // Langsung update JSON menggunakan trait
                $pasienData = $this->findDataMasterPasien($this->dataPasien['pasien']['regNo']);
                if (!isset($pasienData['errorMessages'])) {
                    $this->updateJsonMasterPasien($this->dataPasien['pasien']['regNo'], $pasienData);
                }

                $this->dispatch('toast', type: 'success', message: 'Data pasien berhasil diupdate.');
            }

            $this->closeModal();
            $this->dispatch('master.pasien.saved');
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
            $isUsed = DB::table('rstxn_rjhdrs')->where('reg_no', $regNo)->exists() || DB::table('rstxn_igdhdrs')->where('reg_no', $regNo)->exists() || DB::table('rstxn_rihdrs')->where('reg_no', $regNo)->exists();

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
        if ($name !== 'dataPasien.pasien.domisil.samadgnidentitas') {
            return;
        }

        $checked = !empty($value) && in_array('1', $value);

        if ($checked) {
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

        $this->domisilSyncTick++;
    }

    #[On('lov.selected')]
    public function handleLovSelected(string $target, array $payload): void
    {
        // Handle DESA IDENTITAS
        if ($target === 'desa_identitas') {
            $this->dataPasien['pasien']['identitas']['desaId'] = $payload['des_id'] ?? '';
            $this->dataPasien['pasien']['identitas']['desaName'] = $payload['des_name'] ?? '';
            $this->dataPasien['pasien']['identitas']['kecamatanId'] = $payload['kec_id'] ?? '';
            $this->dataPasien['pasien']['identitas']['kecamatanName'] = $payload['kec_name'] ?? '';
            return;
        }

        // Handle DESA DOMISILI
        if ($target === 'desa_domisil') {
            $this->dataPasien['pasien']['domisil']['desaId'] = $payload['des_id'] ?? '';
            $this->dataPasien['pasien']['domisil']['desaName'] = $payload['des_name'] ?? '';
            $this->dataPasien['pasien']['domisil']['kecamatanId'] = $payload['kec_id'] ?? '';
            $this->dataPasien['pasien']['domisil']['kecamatanName'] = $payload['kec_name'] ?? '';
            return;
        }
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
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="master-pasien-actions-{{ $formMode }}-{{ $regNo ?? 'new' }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>

                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div
                                class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                                <img src="{{ asset('images/Logogram black solid.png') }}" alt="RSI Madinah"
                                    class="block w-6 h-6 dark:hidden" />
                                <img src="{{ asset('images/Logogram white solid.png') }}" alt="RSI Madinah"
                                    class="hidden w-6 h-6 dark:block" />
                            </div>

                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $formMode === 'edit' ? 'Ubah Data pasien' : 'Tambah Data pasien' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Lengkapi informasi pasien untuk kebutuhan
                                    aplikasi.{{ $this->dataPasien['pasien']['regNo'] ?? '' }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-3">
                            <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
                                {{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Tambah' }}
                            </x-badge>
                        </div>
                    </div>

                    <x-secondary-button type="button" wire:click="closeModal" class="!p-2">
                        <span class="sr-only">Close</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-secondary-button>
                </div>
            </div>


            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20">
                <div class="w-full">
                    <div
                        class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                        <div class="p-5 space-y-5">

                            {{-- CONTENT AREA --}}
                            <div class="flex-1 overflow-y-auto">
                                <div class="px-4 py-4 bg-gray-50/70 dark:bg-gray-950/20">
                                    <div class="w-full mx-auto space-y-4">

                                        {{-- SECTION: TIDAK DIKENAL CHECKBOX --}}
                                        @if (isset($dataPasien['pasien']['pasientidakdikenal']))
                                            <div class="flex justify-end mb-4">
                                                <x-check-box value='1' :label="__('Pasien Tidak Dikenal')"
                                                    wire:model.live="dataPasien.pasien.pasientidakdikenal" />
                                            </div>
                                        @endif

                                        {{-- DATA DASAR PASIEN --}}
                                        @include('pages.master.master-pasien.master-pasien-actions-data-dasar-pasien')

                                        {{-- DATA SOSIAL --}}
                                        @include('pages.master.master-pasien.master-pasien-actions-data-sosial')

                                        {{-- DATA BUDAYA --}}
                                        @include('pages.master.master-pasien.master-pasien-actions-data-budaya')

                                        {{-- IDENTITAS --}}
                                        @include('pages.master.master-pasien.master-pasien-actions-identitas')

                                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                            {{-- ALAMAT IDENTITAS --}}
                                            @include('pages.master.master-pasien.master-pasien-actions-alamat-identitas')

                                            {{-- ALAMAT DOMISILI --}}
                                            @include('pages.master.master-pasien.master-pasien-actions-alamat-domisili')
                                        </div>

                                        {{-- KONTAK --}}
                                        @include('pages.master.master-pasien.master-pasien-actions-kontak')

                                        {{-- HUBUNGAN KELUARGA --}}
                                        @include('pages.master.master-pasien.master-pasien-actions-hubungan-keluarga')

                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            @include('pages.master.master-pasien.master-pasien-actions-footer')

        </div>
    </x-modal>
</div>
