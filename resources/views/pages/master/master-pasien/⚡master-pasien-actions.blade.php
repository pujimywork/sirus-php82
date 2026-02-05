<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

new class extends Component {
    public string $formMode = 'create';

    // Data Utama
    public string $regName = '';
    public ?string $regNo = null;
    public ?string $sex = 'L';
    public ?string $birthDate = null;
    public ?string $birthPlace = null;
    public ?string $address = null;
    public ?string $phone = null;
    public ?string $blood = null;
    public ?string $maritalStatus = 'S';
    public ?string $kk = null;
    public ?string $noKk = null;
    public ?string $noJkn = null;
    public ?string $nikBpjs = null;
    public ?string $alergyDesc = null;

    // Data Registrasi
    public ?string $regDate = null;
    public ?string $lockstatus = '0';

    // Data Alamat Detail
    public ?string $rt = null;
    public ?string $rw = null;
    public ?string $desId = null;
    public ?string $kecId = null;
    public ?string $kabId = null;
    public ?string $propId = null;

    // Data Lainnya
    public ?string $jobId = null;
    public ?string $relId = null;
    public ?string $eduId = null;
    public ?string $thn = null;
    public ?string $bln = null;
    public ?string $hari = null;
    public ?string $nyonya = null;
    public ?string $nokartuBpjs = null;
    public ?string $idTb03 = null;
    public ?string $cetakKartu = '0';

    // Data Emergency
    public ?string $enregNo = null;
    public ?string $enregName = null;
    public ?string $ennoHp = null;
    public ?string $enaddress = null;
    public ?string $endnokartuBpjs = null;

    // Meta Data
    public ?string $metaDataPasienJson = null;
    public ?string $metaDataPasienXml = null;
    public ?string $patientUuid = null;

    /* -------------------------
     | Open modal handlers
     * ------------------------- */
    #[On('master.pasien.openCreate')]
    public function openCreate(): void
    {
        $this->resetFormFields();
        $this->formMode = 'create';
        $this->resetValidation();
        $this->regDate = date('Y-m-d');

        $this->dispatch('open-modal', name: 'master-pasien-actions');
    }

    #[On('master.pasien.openEdit')]
    public function openEdit(string $regNo): void
    {
        $row = DB::table('rsmst_pasiens')->where('reg_no', $regNo)->first();
        if (!$row) {
            return;
        }

        $this->resetFormFields();
        $this->formMode = 'edit';
        $this->fillFormFromRow($row);
        $this->resetValidation();

        $this->dispatch('open-modal', name: 'master-pasien-actions');
    }

    public function closeModal(): void
    {
        $this->resetValidation();
        $this->dispatch('close-modal', name: 'master-pasien-actions');
    }

    /* -------------------------
     | Helpers
     * ------------------------- */
    protected function resetFormFields(): void
    {
        $this->reset(['regNo', 'regName', 'regNo', 'sex', 'birthDate', 'birthPlace', 'address', 'phone', 'blood', 'maritalStatus', 'kk', 'noKk', 'noJkn', 'nikBpjs', 'alergyDesc', 'regDate', 'lockstatus', 'rt', 'rw', 'desId', 'kecId', 'kabId', 'propId', 'jobId', 'relId', 'eduId', 'thn', 'bln', 'hari', 'nyonya', 'nokartuBpjs', 'idTb03', 'cetakKartu', 'enregNo', 'enregName', 'ennoHp', 'enaddress', 'endnokartuBpjs', 'metaDataPasienJson', 'metaDataPasienXml', 'patientUuid']);

        // default values
        $this->formMode = 'create';
        $this->sex = 'L';
        $this->maritalStatus = 'S';
        $this->lockstatus = '0';
        $this->cetakKartu = '0';
        $this->regDate = date('Y-m-d');
    }

    protected function fillFormFromRow(object $row): void
    {
        $this->regNo = (string) $row->reg_no;
        $this->regName = (string) ($row->reg_name ?? '');
        $this->regNo = $row->reg_no;
        $this->sex = $row->sex ?? 'L';
        $this->birthDate = $row->birth_date ? date('Y-m-d', strtotime($row->birth_date)) : null;
        $this->birthPlace = $row->birth_place;
        $this->address = $row->address;
        $this->phone = $row->phone;
        $this->blood = $row->blood;
        $this->maritalStatus = $row->marital_status ?? 'S';
        $this->kk = $row->kk;
        $this->noKk = $row->no_kk;
        $this->noJkn = $row->no_jkn;
        $this->nikBpjs = $row->nik_bpjs;
        $this->alergyDesc = $row->alergy_desc;
        $this->regDate = $row->reg_date ? date('Y-m-d', strtotime($row->reg_date)) : null;
        $this->lockstatus = (string) ($row->lockstatus ?? '0');
        $this->rt = $row->rt;
        $this->rw = $row->rw;
        $this->desId = $row->des_id;
        $this->kecId = $row->kec_id;
        $this->kabId = $row->kab_id;
        $this->propId = $row->prop_id;
        $this->jobId = $row->job_id;
        $this->relId = $row->rel_id;
        $this->eduId = $row->edu_id;
        $this->thn = $row->thn;
        $this->bln = $row->bln;
        $this->hari = $row->hari;
        $this->nyonya = $row->nyonya;
        $this->nokartuBpjs = $row->nokartu_bpjs;
        $this->idTb03 = $row->id_tb_03;
        $this->cetakKartu = (string) ($row->cetak_kartu ?? '0');
        $this->enregNo = $row->enreg_no;
        $this->enregName = $row->enreg_name;
        $this->ennoHp = $row->enno_hp;
        $this->enaddress = $row->enaddress;
        $this->endnokartuBpjs = $row->endnokartu_bpjs;
        $this->metaDataPasienJson = $row->meta_data_pasien_json;
        $this->metaDataPasienXml = $row->meta_data_pasien_xml;
        $this->patientUuid = $row->patient_uuid;
    }

    /* -------------------------
     | Validation
     * ------------------------- */
    protected function rules(): array
    {
        return [
            // Data Utama
            'regNo' => $this->formMode === 'create' ? 'required|string|max:50|unique:rsmst_pasiens,reg_no' : 'required|string|max:50',
            'regName' => 'required|string|max:255',
            'regNo' => 'nullable|string|max:50',
            'sex' => 'required|in:L,P',
            'birthDate' => 'nullable|date',
            'birthPlace' => 'nullable|string|max:100',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'blood' => 'nullable|string|max:2',
            'maritalStatus' => 'required|in:S,M,K,D,J',
            'kk' => 'nullable|string|max:255',
            'noKk' => 'nullable|string|max:50',
            'noJkn' => 'nullable|string|max:50',
            'nikBpjs' => 'nullable|string|max:50',
            'alergyDesc' => 'nullable|string',

            // Data Registrasi
            'regDate' => 'nullable|date',
            'lockstatus' => 'required|in:0,1',

            // Data Alamat Detail
            'rt' => 'nullable|string|max:10',
            'rw' => 'nullable|string|max:10',
            'desId' => 'nullable|string|max:10',
            'kecId' => 'nullable|string|max:10',
            'kabId' => 'nullable|string|max:10',
            'propId' => 'nullable|string|max:10',

            // Data Lainnya
            'jobId' => 'nullable|string|max:10',
            'relId' => 'nullable|string|max:10',
            'eduId' => 'nullable|string|max:10',
            'thn' => 'nullable|string|max:10',
            'bln' => 'nullable|string|max:10',
            'hari' => 'nullable|string|max:10',
            'nyonya' => 'nullable|string|max:100',
            'nokartuBpjs' => 'nullable|string|max:50',
            'idTb03' => 'nullable|string|max:50',
            'cetakKartu' => 'required|in:0,1',

            // Data Emergency
            'enregNo' => 'nullable|string|max:50',
            'enregName' => 'nullable|string|max:255',
            'ennoHp' => 'nullable|string|max:20',
            'enaddress' => 'nullable|string',
            'endnokartuBpjs' => 'nullable|string|max:50',

            // Meta Data
            'metaDataPasienJson' => 'nullable|string',
            'metaDataPasienXml' => 'nullable|string',
            'patientUuid' => 'nullable|string|max:100',
        ];
    }

    protected function messages(): array
    {
        return [
            '*.required' => ':attribute wajib diisi.',
            '*.unique' => ':attribute sudah digunakan.',
            '*.max' => ':attribute maksimal :max karakter.',
            '*.in' => ':attribute tidak valid.',
            '*.date' => ':attribute harus tanggal yang valid.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'regNo' => 'ID Pasien',
            'regName' => 'Nama Pasien',
            'regNo' => 'Nomor Registrasi',
            'sex' => 'Jenis Kelamin',
            'birthDate' => 'Tanggal Lahir',
            'birthPlace' => 'Tempat Lahir',
            'address' => 'Alamat',
            'phone' => 'Telepon',
            'blood' => 'Golongan Darah',
            'maritalStatus' => 'Status Perkawinan',
            'kk' => 'Kepala Keluarga',
            'noKk' => 'Nomor KK',
            'noJkn' => 'Nomor JKN',
            'nikBpjs' => 'NIK BPJS',
            'alergyDesc' => 'Alergi',
            'regDate' => 'Tanggal Registrasi',
            'lockstatus' => 'Status Kunci',
            'rt' => 'RT',
            'rw' => 'RW',
            'desId' => 'Desa',
            'kecId' => 'Kecamatan',
            'kabId' => 'Kabupaten',
            'propId' => 'Provinsi',
            'jobId' => 'Pekerjaan',
            'relId' => 'Agama',
            'eduId' => 'Pendidikan',
        ];
    }

    /* -------------------------
     | Save
     * ------------------------- */
    public function save(): void
    {
        $data = $this->validate();

        $payload = [
            // Data Utama
            'reg_no' => $data['regNo'],
            'reg_name' => $data['regName'],
            'sex' => $data['sex'],
            'birth_date' => $data['birthDate'],
            'birth_place' => $data['birthPlace'],
            'address' => $data['address'],
            'phone' => $data['phone'],
            'blood' => $data['blood'],
            'marital_status' => $data['maritalStatus'],
            'kk' => $data['kk'],
            'no_kk' => $data['noKk'],
            'no_jkn' => $data['noJkn'],
            'nik_bpjs' => $data['nikBpjs'],
            'alergy_desc' => $data['alergyDesc'],

            // Data Registrasi
            'reg_date' => $data['regDate'],
            'lockstatus' => $data['lockstatus'],

            // Data Alamat Detail
            'rt' => $data['rt'],
            'rw' => $data['rw'],
            'des_id' => $data['desId'],
            'kec_id' => $data['kecId'],
            'kab_id' => $data['kabId'],
            'prop_id' => $data['propId'],

            // Data Lainnya
            'job_id' => $data['jobId'],
            'rel_id' => $data['relId'],
            'edu_id' => $data['eduId'],
            'thn' => $data['thn'],
            'bln' => $data['bln'],
            'hari' => $data['hari'],
            'nyonya' => $data['nyonya'],
            'nokartu_bpjs' => $data['nokartuBpjs'],
            'id_tb_03' => $data['idTb03'],
            'cetak_kartu' => $data['cetakKartu'],

            // Data Emergency
            'enreg_no' => $data['enregNo'],
            'enreg_name' => $data['enregName'],
            'enno_hp' => $data['ennoHp'],
            'enaddress' => $data['enaddress'],
            'endnokartu_bpjs' => $data['endnokartuBpjs'],

            // Meta Data
            'meta_data_pasien_json' => $data['metaDataPasienJson'],
            'meta_data_pasien_xml' => $data['metaDataPasienXml'],
            'patient_uuid' => $data['patientUuid'],
        ];

        try {
            if ($this->formMode === 'create') {
                DB::table('rsmst_pasiens')->insert($payload);
            } else {
                DB::table('rsmst_pasiens')->where('reg_no', $this->regNo)->update($payload);
            }

            $this->dispatch('toast', type: 'success', message: 'Data pasien berhasil disimpan.');
            $this->closeModal();
            $this->dispatch('master.pasien.saved');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan data: ' . $e->getMessage());
        }
    }

    /* -------------------------
     | Delete
     * ------------------------- */
    #[On('master.pasien.requestDelete')]
    public function deleteFromGrid(string $regNo): void
    {
        try {
            // Cek apakah pasien sudah punya transaksi
            $isUsed = DB::table('rstxn_rjhdrs')->where('reg_no', $regNo)->exists() || DB::table('rstxn_igdhdrs')->where('reg_no', $regNo)->exists();

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
            $this->dispatch('toast', type: 'error', message: 'Pasien tidak bisa dihapus karena masih dipakai di data lain.');
        }
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
                                    {{ $formMode === 'edit' ? 'Ubah Data Pasien' : 'Tambah Data Pasien' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Lengkapi informasi pasien untuk kebutuhan aplikasi.
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
                <div class="max-w-full mx-auto">
                    <div
                        class="bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
                        <div class="p-5 space-y-5">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">

                                {{-- ID Pasien --}}
                                <div>
                                    <x-input-label value="ID Pasien" />
                                    <x-text-input wire:model.defer="regNo" :disabled="$formMode === 'edit'" :error="$errors->has('regNo')"
                                        class="w-full mt-1" />
                                    <x-input-error :messages="$errors->get('regNo')" class="mt-1" />
                                </div>

                                {{-- Nama Pasien --}}
                                <div class="sm:col-span-2">
                                    <x-input-label value="Nama Pasien" />
                                    <x-text-input wire:model.defer="regName" :error="$errors->has('regName')" class="w-full mt-1" />
                                    <x-input-error :messages="$errors->get('regName')" class="mt-1" />
                                </div>

                                {{-- Nomor Registrasi --}}
                                <div>
                                    <x-input-label value="NRM" />
                                    <x-text-input wire:model.defer="regNo" :error="$errors->has('regNo')" class="w-full mt-1" />
                                    <x-input-error :messages="$errors->get('regNo')" class="mt-1" />
                                </div>

                                {{-- Jenis Kelamin --}}
                                <div>
                                    <x-input-label value="Jenis Kelamin" />
                                    <x-select-input wire:model.defer="sex" :error="$errors->has('sex')" class="w-full mt-1">
                                        <option value="L">Laki-laki</option>
                                        <option value="P">Perempuan</option>
                                    </x-select-input>
                                    <x-input-error :messages="$errors->get('sex')" class="mt-1" />
                                </div>

                                {{-- Status Perkawinan --}}
                                <div>
                                    <x-input-label value="Status Perkawinan" />
                                    <x-select-input wire:model.defer="maritalStatus" :error="$errors->has('maritalStatus')"
                                        class="w-full mt-1">
                                        <option value="S">Single</option>
                                        <option value="M">Menikah</option>
                                        <option value="K">Kawin</option>
                                        <option value="D">Duda</option>
                                        <option value="J">Janda</option>
                                    </x-select-input>
                                    <x-input-error :messages="$errors->get('maritalStatus')" class="mt-1" />
                                </div>

                                {{-- Tanggal Lahir --}}
                                <div>
                                    <x-input-label value="Tanggal Lahir" />
                                    <x-text-input type="date" wire:model.defer="birthDate" :error="$errors->has('birthDate')"
                                        class="w-full mt-1" />
                                    <x-input-error :messages="$errors->get('birthDate')" class="mt-1" />
                                </div>

                                {{-- Tempat Lahir --}}
                                <div>
                                    <x-input-label value="Tempat Lahir" />
                                    <x-text-input wire:model.defer="birthPlace" :error="$errors->has('birthPlace')"
                                        class="w-full mt-1" />
                                    <x-input-error :messages="$errors->get('birthPlace')" class="mt-1" />
                                </div>

                                {{-- Golongan Darah --}}
                                <div>
                                    <x-input-label value="Golongan Darah" />
                                    <x-select-input wire:model.defer="blood" :error="$errors->has('blood')" class="w-full mt-1">
                                        <option value="">- Pilih -</option>
                                        <option value="A">A</option>
                                        <option value="B">B</option>
                                        <option value="AB">AB</option>
                                        <option value="O">O</option>
                                    </x-select-input>
                                    <x-input-error :messages="$errors->get('blood')" class="mt-1" />
                                </div>

                                {{-- Telepon --}}
                                <div>
                                    <x-input-label value="Telepon" />
                                    <x-text-input wire:model.defer="phone" :error="$errors->has('phone')" class="w-full mt-1" />
                                    <x-input-error :messages="$errors->get('phone')" class="mt-1" />
                                </div>

                                {{-- Alamat --}}
                                <div class="lg:col-span-3">
                                    <x-input-label value="Alamat" />
                                    <x-text-input wire:model.defer="address" :error="$errors->has('address')" class="w-full mt-1" />
                                    <x-input-error :messages="$errors->get('address')" class="mt-1" />
                                </div>

                                {{-- RT --}}
                                <div>
                                    <x-input-label value="RT" />
                                    <x-text-input wire:model.defer="rt" :error="$errors->has('rt')" class="w-full mt-1" />
                                    <x-input-error :messages="$errors->get('rt')" class="mt-1" />
                                </div>

                                {{-- RW --}}
                                <div>
                                    <x-input-label value="RW" />
                                    <x-text-input wire:model.defer="rw" :error="$errors->has('rw')" class="w-full mt-1" />
                                    <x-input-error :messages="$errors->get('rw')" class="mt-1" />
                                </div>

                                {{-- Tanggal Registrasi --}}
                                <div>
                                    <x-input-label value="Tanggal Registrasi" />
                                    <x-text-input type="date" wire:model.defer="regDate" :error="$errors->has('regDate')"
                                        class="w-full mt-1" />
                                    <x-input-error :messages="$errors->get('regDate')" class="mt-1" />
                                </div>

                                {{-- NIK BPJS --}}
                                <div>
                                    <x-input-label value="NIK BPJS" />
                                    <x-text-input wire:model.defer="nikBpjs" :error="$errors->has('nikBpjs')" class="w-full mt-1" />
                                    <x-input-error :messages="$errors->get('nikBpjs')" class="mt-1" />
                                </div>

                                {{-- Nomor JKN --}}
                                <div>
                                    <x-input-label value="Nomor JKN" />
                                    <x-text-input wire:model.defer="noJkn" :error="$errors->has('noJkn')" class="w-full mt-1" />
                                    <x-input-error :messages="$errors->get('noJkn')" class="mt-1" />
                                </div>

                                {{-- Alergi --}}
                                <div class="lg:col-span-3">
                                    <x-input-label value="Alergi" />
                                    <x-textarea wire:model.defer="alergyDesc" :error="$errors->has('alergyDesc')" class="w-full mt-1"
                                        rows="2" />
                                    <x-input-error :messages="$errors->get('alergyDesc')" class="mt-1" />
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        Pastikan data sudah benar sebelum menyimpan.
                    </div>

                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" wire:click="closeModal">
                            Batal
                        </x-secondary-button>

                        <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled">
                            <span wire:loading.remove>Simpan</span>
                            <span wire:loading>Saving...</span>
                        </x-primary-button>
                    </div>
                </div>
            </div>

        </div>
    </x-modal>
</div>
