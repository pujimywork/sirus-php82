<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/edukasi-terintegrasi-ri/rm-edukasi-terintegrasi-ri-actions.blade.php

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

    // Signature dari <x-signature.signature-pad />
    public string $sasaranEdukasiSignature = '';

    public array $form = [];

    // Static option lists (key => label) untuk render checkbox / radio
    public array $tujuanList = [
        'penyakit'        => 'Pemahaman penyakit/diagnosis',
        'obat'            => 'Penggunaan obat yang aman',
        'nutrisi'         => 'Nutrisi & diet',
        'aktivitas'       => 'Aktivitas & latihan',
        'perawatanRumah'  => 'Perawatan di rumah',
        'pencegahan'      => 'Pencegahan komplikasi',
        'lainnya'         => 'Lainnya',
    ];

    public array $kebutuhanList = [
        'penyakitHasil' => 'Penjelasan penyakit & hasil pemeriksaan',
        'prosedur'      => 'Prosedur / tindakan medis',
        'rencanaAsuhan' => 'Rencana asuhan & tindak lanjut',
        'obatEfek'      => 'Penggunaan obat & efek samping',
        'cuciTangan'    => 'Cuci tangan & pencegahan infeksi',
        'alatRumah'     => 'Penggunaan alat medis di rumah',
        'warningSign'   => 'Tanda bahaya yang perlu diwaspadai',
        'lainnya'       => 'Lainnya',
    ];

    public array $metodeList = [
        'lisan'        => 'Penjelasan lisan',
        'demonstrasi'  => 'Demonstrasi / praktik langsung',
        'leaflet'      => 'Leaflet / brosur',
        'video'        => 'Video edukasi',
        'poster'       => 'Poster / peraga',
        'lainnya'      => 'Lainnya',
    ];

    public array $prefList = [
        'lisan'        => 'Lisan',
        'tulisan'      => 'Tulisan',
        'demonstrasi'  => 'Demonstrasi',
        'video'        => 'Video',
        'poster'       => 'Poster',
        'lainnya'      => 'Lainnya',
    ];

    public array $hasilList = [
        'paham'             => 'Pasien/keluarga memahami informasi',
        'mampuMengulang'    => 'Dapat mengulang kembali informasi',
        'tunjukkanSkill'    => 'Menunjukkan keterampilan yang diajarkan',
        'sesuaiNilai'       => 'Edukasi sesuai nilai & keyakinan pasien',
        'perluEdukasiUlang' => 'Diperlukan edukasi ulang',
    ];

    public array $rujukList = [
        'dietisien'    => 'Dietisien',
        'farmasi'      => 'Farmasi',
        'rehabilitasi' => 'Rehabilitasi',
        'psikologi'    => 'Psikologi',
        'lainnya'      => 'Lainnya',
    ];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-edukasi-terintegrasi-ri'];

    public function mount(?string $riHdrNo = null, bool $disabled = false): void
    {
        $this->riHdrNo  = $riHdrNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-edukasi-terintegrasi-ri']);

        $this->form = $this->defaultForm();
        $this->prefillHeader();

        if ($this->riHdrNo) {
            $data = $this->findDataRI($this->riHdrNo);
            if ($data) {
                $this->dataDaftarRi = $data;
                $this->regNo = $data['regNo'] ?? null;
                $this->dataDaftarRi['edukasiPasienTerintegrasi'] ??= [];
                $this->form['ttd']['pasienKeluargaNama'] = $data['regName'] ?? '';
                $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $disabled;
            }
        }
    }

    private function defaultForm(): array
    {
        return [
            'tglEdukasi'       => '',
            'pemberiInformasi' => ['petugasCode' => '', 'petugasName' => ''],

            'tujuan'      => ['opsi' => [], 'lainnya' => ''],

            'evaluasiAwal' => [
                'literasi'              => null,
                'bahasaAtauPendidikan'  => '',
                'hambatanEmosional'         => ['ada' => null, 'keterangan' => ''],
                'keterbatasanFisikKognitif' => ['ada' => null, 'keterangan' => ''],
                'nilaiKeyakinanBudaya'      => ['ada' => null, 'deskripsi' => ''],
                'preferensiInformasi'       => ['opsi' => [], 'lainnya' => ''],
            ],

            'kebutuhan'   => ['opsi' => [], 'lainnya' => ''],
            'metodeMedia' => ['opsi' => [], 'lainnya' => ''],

            'hasil' => [
                'paham'             => ['ya' => null, 'keterangan' => ''],
                'mampuMengulang'    => ['ya' => null, 'keterangan' => ''],
                'tunjukkanSkill'    => ['ya' => null, 'keterangan' => ''],
                'sesuaiNilai'       => ['ya' => null, 'keterangan' => ''],
                'perluEdukasiUlang' => ['ya' => null, 'keterangan' => ''],
            ],

            'tindakLanjut' => [
                'edukasiLanjutanTanggal' => '',
                'dirujukKe'              => [],
                'tidakPerluTL'           => false,
            ],

            'ttd' => [
                'pasienKeluargaNama' => '',
                'pasienKeluargaHubungan' => 'pasien',
                'pasienKeluargaTTD'  => '',
            ],
        ];
    }

    private function prefillHeader(): void
    {
        $this->form['pemberiInformasi']['petugasName'] = auth()->user()->myuser_name ?? '';
        $this->form['pemberiInformasi']['petugasCode'] = auth()->user()->myuser_code ?? '';
        if (empty($this->form['tglEdukasi'])) {
            $this->form['tglEdukasi'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        }
    }

    public function setTglEdukasi(): void
    {
        $this->form['tglEdukasi'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    public function setEdukasiLanjutanToday(): void
    {
        $this->form['tindakLanjut']['edukasiLanjutanTanggal']
            = Carbon::now(config('app.timezone'))->format('d/m/Y');
    }

    public function setSasaranSignature(string $dataUrl): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->sasaranEdukasiSignature = $dataUrl;
        $this->incrementVersion('modal-edukasi-terintegrasi-ri');
    }

    public function clearSasaranSignature(): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->sasaranEdukasiSignature = '';
        $this->incrementVersion('modal-edukasi-terintegrasi-ri');
    }

    public function addEdukasiTerintegrasi(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }

        // Pastikan header terisi (autofill terakhir sebelum validasi)
        if (empty($this->form['tglEdukasi'])) {
            $this->form['tglEdukasi'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        }
        $this->form['pemberiInformasi']['petugasName'] = auth()->user()->myuser_name ?? '';
        $this->form['pemberiInformasi']['petugasCode'] = auth()->user()->myuser_code ?? '';

        // Normalisasi radio/checkbox string → boolean
        $this->normalizeBooleansOnForm();

        $rules = [
            // HEADER
            'form.tglEdukasi'                   => 'required|date_format:d/m/Y H:i:s',
            'form.pemberiInformasi.petugasCode' => 'required|string|max:50',
            'form.pemberiInformasi.petugasName' => 'required|string|max:250',

            // 1) Tujuan
            'form.tujuan.opsi'    => 'nullable|array',
            'form.tujuan.opsi.*'  => 'in:penyakit,obat,nutrisi,aktivitas,perawatanRumah,pencegahan,lainnya',
            'form.tujuan.lainnya' => 'nullable|string|max:200',

            // 2) Evaluasi Awal
            'form.evaluasiAwal.literasi'                              => 'nullable|in:Baik,Cukup,Kurang',
            'form.evaluasiAwal.bahasaAtauPendidikan'                  => 'nullable|string|max:200',
            'form.evaluasiAwal.hambatanEmosional.ada'                 => 'nullable|boolean',
            'form.evaluasiAwal.hambatanEmosional.keterangan'          => 'nullable|string|max:300',
            'form.evaluasiAwal.keterbatasanFisikKognitif.ada'         => 'nullable|boolean',
            'form.evaluasiAwal.keterbatasanFisikKognitif.keterangan'  => 'nullable|string|max:300',
            'form.evaluasiAwal.nilaiKeyakinanBudaya.ada'              => 'nullable|boolean',
            'form.evaluasiAwal.nilaiKeyakinanBudaya.deskripsi'        => 'nullable|string|max:500',
            'form.evaluasiAwal.preferensiInformasi.opsi'              => 'nullable|array',
            'form.evaluasiAwal.preferensiInformasi.opsi.*'            => 'in:lisan,tulisan,demonstrasi,video,poster,lainnya',
            'form.evaluasiAwal.preferensiInformasi.lainnya'           => 'nullable|string|max:200',

            // 3) Kebutuhan
            'form.kebutuhan.opsi'    => 'nullable|array',
            'form.kebutuhan.opsi.*'  => 'in:penyakitHasil,prosedur,rencanaAsuhan,obatEfek,cuciTangan,alatRumah,warningSign,lainnya',
            'form.kebutuhan.lainnya' => 'nullable|string|max:200',

            // 4) Metode & Media
            'form.metodeMedia.opsi'    => 'nullable|array',
            'form.metodeMedia.opsi.*'  => 'in:lisan,demonstrasi,leaflet,video,poster,lainnya',
            'form.metodeMedia.lainnya' => 'nullable|string|max:200',

            // 5) Hasil
            'form.hasil.*.ya'         => 'nullable|boolean',
            'form.hasil.*.keterangan' => 'nullable|string|max:300',

            // 6) Tindak Lanjut
            'form.tindakLanjut.edukasiLanjutanTanggal' => 'nullable|date_format:d/m/Y',
            'form.tindakLanjut.dirujukKe'              => 'nullable|array',
            'form.tindakLanjut.dirujukKe.*'            => 'string|max:50',
            'form.tindakLanjut.tidakPerluTL'           => 'boolean',

            // 7) TTD
            'form.ttd.pasienKeluargaNama' => 'required|string|max:150',
            'form.ttd.pasienKeluargaHubungan' => 'required|string|max:50',
        ];

        // Conditional: "lainnya" wajib diisi kalau di-check
        if (in_array('lainnya', $this->form['tujuan']['opsi'] ?? [], true)) {
            $rules['form.tujuan.lainnya'] = 'required|string|max:200';
        }
        if (in_array('lainnya', $this->form['kebutuhan']['opsi'] ?? [], true)) {
            $rules['form.kebutuhan.lainnya'] = 'required|string|max:200';
        }
        if (in_array('lainnya', $this->form['metodeMedia']['opsi'] ?? [], true)) {
            $rules['form.metodeMedia.lainnya'] = 'required|string|max:200';
        }
        if (in_array('lainnya', $this->form['evaluasiAwal']['preferensiInformasi']['opsi'] ?? [], true)) {
            $rules['form.evaluasiAwal.preferensiInformasi.lainnya'] = 'required|string|max:200';
        }

        $attributes = [
            'form.tglEdukasi'                  => 'Tanggal edukasi',
            'form.pemberiInformasi.petugasCode' => 'Kode petugas',
            'form.pemberiInformasi.petugasName' => 'Nama petugas',
            'form.tujuan.lainnya'              => 'Tujuan (lainnya)',
            'form.kebutuhan.lainnya'           => 'Kebutuhan (lainnya)',
            'form.metodeMedia.lainnya'         => 'Metode/media (lainnya)',
            'form.evaluasiAwal.preferensiInformasi.lainnya' => 'Preferensi (lainnya)',
            'form.ttd.pasienKeluargaNama'      => 'Nama pasien/keluarga',
            'form.ttd.pasienKeluargaHubungan'  => 'Hubungan dengan pasien',
        ];

        $messages = [
            'required'    => ':attribute wajib diisi.',
            'string'      => ':attribute harus berupa teks.',
            'array'       => ':attribute harus berupa daftar.',
            'boolean'     => ':attribute harus bernilai ya/tidak.',
            'in'          => ':attribute berisi nilai yang tidak valid.',
            'max.string'  => ':attribute maksimal :max karakter.',
            'date_format' => 'Format :attribute tidak sesuai (harus :format).',
        ];

        $this->validateWithToast($rules, $messages, $attributes);

        try {
            DB::transaction(function () {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                $fresh['edukasiPasienTerintegrasi'] ??= [];

                // inject TTD base64 kalau ada
                if (!empty($this->sasaranEdukasiSignature)) {
                    $this->form['ttd']['pasienKeluargaTTD'] = $this->sasaranEdukasiSignature;
                }

                $entry = [
                    'id'         => (string) Str::uuid(),
                    'created_at' => Carbon::now(config('app.timezone'))->format('Y-m-d H:i:s'),
                    'created_by' => [
                        'code' => auth()->user()->myuser_code ?? '',
                        'name' => auth()->user()->myuser_name ?? '',
                    ],
                    'form'       => $this->form,
                ];

                $fresh['edukasiPasienTerintegrasi'][] = $entry;

                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Tambah Edukasi Terintegrasi — entri ' . ($entry['form']['tglEdukasi'] ?? '-'), 'MR');
            });

            $this->resetFormEdukasi();
            $this->afterSave('Edukasi terintegrasi berhasil disimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    public function removeEdukasiTerintegrasiById(string $id): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }

        try {
            DB::transaction(function () use ($id) {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                $list  = $fresh['edukasiPasienTerintegrasi'] ?? [];

                $deletedRow = collect($list)->firstWhere('id', $id);
                $newList = array_values(array_filter($list, fn($e) => ($e['id'] ?? null) !== $id));
                if (count($newList) === count($list)) {
                    throw new \RuntimeException('Data tidak ditemukan atau sudah dihapus.');
                }

                $fresh['edukasiPasienTerintegrasi'] = $newList;
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Edukasi Terintegrasi — entri ' . ($deletedRow['form']['tglEdukasi'] ?? '-'), 'MR');
            });

            $this->afterSave('Data edukasi berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    public function cetak(string $id)
    {
        $list = $this->dataDaftarRi['edukasiPasienTerintegrasi'] ?? [];
        $entry = collect($list)->firstWhere('id', $id);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Data edukasi tidak ditemukan.');
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

            // TTD petugas pemberi edukasi (dari created_by.code -> users.myuser_ttd_image)
            $ttdPetugasPath = null;
            $petugasCode = $entry['form']['pemberiInformasi']['petugasCode'] ?? ($entry['created_by']['code'] ?? null);
            if ($petugasCode) {
                $ttdPath = DB::table('users')->where('myuser_code', $petugasCode)->value('myuser_ttd_image');
                if (!empty($ttdPath) && file_exists(public_path('storage/' . $ttdPath))) {
                    $ttdPetugasPath = public_path('storage/' . $ttdPath);
                }
            }

            $data = array_merge($pasien, [
                'dataRi' => $this->dataDaftarRi,
                'entry' => $entry,
                'identitasRs' => $identitasRs,
                'ttdPetugasPath' => $ttdPetugasPath,
                'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
            ]);

            set_time_limit(300);

            $pdf = Pdf::loadView('pages.components.modul-dokumen.r-i.edukasi-terintegrasi.cetak-edukasi-terintegrasi-ri-print', ['data' => $data])->setPaper('A4');

            $this->dispatch('toast', type: 'success', message: 'Berhasil mencetak Edukasi Terintegrasi.');
            return response()->streamDownload(fn() => print $pdf->output(), 'edukasi-terintegrasi-ri-' . ($pasien['regNo'] ?? $this->riHdrNo) . '.pdf');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cetak: ' . $e->getMessage());
        }
    }

    /**
     * Toggle membership di array multi-pilih (untuk x-toggle group).
     * $fullPath: path lengkap mulai dari property root, mis. "form.tujuan.opsi".
     */
    public function toggleArrayOpt(string $fullPath, string $opt): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $current = (array) data_get($this, $fullPath, []);
        if (in_array($opt, $current, true)) {
            $current = array_values(array_filter($current, fn($nilai) => $nilai !== $opt));
        } else {
            $current[] = $opt;
        }
        data_set($this, $fullPath, $current);
    }

    public function resetFormEdukasi(): void
    {
        $this->form = $this->defaultForm();
        $this->form['ttd']['pasienKeluargaNama'] = $this->dataDaftarRi['regName'] ?? '';
        $this->prefillHeader();
        $this->sasaranEdukasiSignature = '';
        $this->resetValidation();
        $this->incrementVersion('modal-edukasi-terintegrasi-ri');
    }

    private function afterSave(string $msg): void
    {
        $this->incrementVersion('modal-edukasi-terintegrasi-ri');
        $this->dispatch('toast', type: 'success', message: $msg);
    }

    private function normalizeBooleansOnForm(): void
    {
        $formData = &$this->form;

        foreach (['hambatanEmosional', 'keterbatasanFisikKognitif', 'nilaiKeyakinanBudaya'] as $key) {
            if (array_key_exists('ada', $formData['evaluasiAwal'][$key] ?? [])) {
                $formData['evaluasiAwal'][$key]['ada'] = filter_var(
                    $formData['evaluasiAwal'][$key]['ada'],
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE
                );
            }
        }

        if (isset($formData['hasil'])) {
            foreach ($formData['hasil'] as &$hasilItem) {
                if (array_key_exists('ya', $hasilItem)) {
                    $hasilItem['ya'] = filter_var($hasilItem['ya'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                }
            }
            unset($hasilItem);
        }

        if (isset($formData['tindakLanjut']['tidakPerluTL'])) {
            $formData['tindakLanjut']['tidakPerluTL'] = (bool) $formData['tindakLanjut']['tidakPerluTL'];
        }
    }
};
?>

<div class="space-y-4"
    wire:key="{{ $this->renderKey('modal-edukasi-terintegrasi-ri', [$riHdrNo ?? 'new']) }}">

    @if ($isFormLocked)
        <div class="flex items-center gap-2 px-4 py-2.5 mb-2 rounded-lg
                    bg-amber-50 border border-amber-200 text-amber-800
                    dark:bg-amber-900/20 dark:border-amber-700 dark:text-amber-300 text-sm">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
            </svg>
            Pasien sudah pulang — form dalam mode <strong>read-only</strong>.
        </div>
    @endif

    {{-- ═══════════════ FORM ENTRY ═══════════════ --}}
    @if (!$isFormLocked)
        <x-border-form title="Formulir Edukasi Terintegrasi Pasien & Keluarga" align="start" bgcolor="bg-surface-soft">
            <div class="mt-3 space-y-5">

                {{-- ─── HEADER: Waktu & Petugas ─── --}}
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div>
                        <x-input-label value="Tanggal Edukasi *" />
                        <div class="flex items-end gap-2 mt-1">
                            <x-text-input wire:model="form.tglEdukasi" class="flex-1 font-mono"
                                placeholder="dd/mm/yyyy hh:ii:ss" readonly
                                :error="$errors->has('form.tglEdukasi')" />
                            <x-now-button wire:click="setTglEdukasi" />
                        </div>
                        <x-input-error :messages="$errors->get('form.tglEdukasi')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label value="Nama Petugas (Pemberi Informasi) *" />
                        <x-text-input wire:model="form.pemberiInformasi.petugasName" class="w-full mt-1"
                            :error="$errors->has('form.pemberiInformasi.petugasName')" />
                        <x-input-error :messages="$errors->get('form.pemberiInformasi.petugasName')" class="mt-1" />
                    </div>
                </div>

                <hr class="border-hairline dark:border-gray-700">

                {{-- ─── 1) TUJUAN EDUKASI ─── --}}
                <div>
                    <h4 class="text-sm font-semibold text-ink dark:text-gray-100 mb-2">
                        1) Tujuan Edukasi <span class="text-xs font-normal text-muted">(boleh lebih dari satu)</span>
                    </h4>
                    <div class="grid grid-cols-1 gap-2 md:grid-cols-3">
                        @foreach ($tujuanList as $key => $label)
                            <div wire:key="tujuan-{{ $key }}">
                                <x-toggle
                                    :current="in_array($key, $form['tujuan']['opsi'] ?? []) ? '1' : '0'"
                                    trueValue="1" falseValue="0"
                                    wireClick="toggleArrayOpt('form.tujuan.opsi', '{{ $key }}')"
                                    :label="$label" :disabled="$isFormLocked" />
                            </div>
                        @endforeach
                    </div>
                    @if (in_array('lainnya', $form['tujuan']['opsi'] ?? []))
                        <x-text-input wire:model.blur="form.tujuan.lainnya" class="w-full mt-2"
                            placeholder="Sebutkan tujuan lainnya"
                            :error="$errors->has('form.tujuan.lainnya')" />
                        <x-input-error :messages="$errors->get('form.tujuan.lainnya')" class="mt-1" />
                    @endif
                </div>

                <hr class="border-hairline dark:border-gray-700">

                {{-- ─── 2) EVALUASI AWAL & NILAI ─── --}}
                <div class="space-y-3">
                    <h4 class="text-sm font-semibold text-ink dark:text-gray-100">
                        2) Evaluasi Awal Kemampuan & Nilai
                    </h4>

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div>
                            <x-input-label value="Kemampuan membaca / menulis" />
                            <div class="flex gap-2 mt-1">
                                @foreach (['Baik', 'Cukup', 'Kurang'] as $opt)
                                    <x-radio-button :label="$opt" :value="$opt" name="literasi"
                                        wire:model.live="form.evaluasiAwal.literasi" :disabled="$isFormLocked" />
                                @endforeach
                            </div>
                        </div>
                        <div>
                            <x-input-label value="Bahasa yang digunakan / tingkat pendidikan" />
                            <x-text-input wire:model.blur="form.evaluasiAwal.bahasaAtauPendidikan" :error="$errors->has('form.evaluasiAwal.bahasaAtauPendidikan')"
                                class="w-full mt-1" placeholder="Contoh: Indonesia / SMA"
                                :disabled="$isFormLocked" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div>
                            <x-input-label value="Hambatan emosional / motivasi" />
                            <div class="flex gap-2 mt-1">
                                <x-radio-button label="Ada" value="1" name="hambatanEmo"
                                    wire:model.live="form.evaluasiAwal.hambatanEmosional.ada"
                                    :disabled="$isFormLocked" />
                                <x-radio-button label="Tidak ada" value="0" name="hambatanEmo"
                                    wire:model.live="form.evaluasiAwal.hambatanEmosional.ada"
                                    :disabled="$isFormLocked" />
                            </div>
                            <x-text-input wire:model.blur="form.evaluasiAwal.hambatanEmosional.keterangan" :error="$errors->has('form.evaluasiAwal.hambatanEmosional.keterangan')"
                                class="w-full mt-2" placeholder="Keterangan jika ada hambatan"
                                :disabled="$isFormLocked" />
                        </div>
                        <div>
                            <x-input-label value="Keterbatasan fisik / kognitif" />
                            <div class="flex gap-2 mt-1">
                                <x-radio-button label="Ada" value="1" name="keterbatasanFk"
                                    wire:model.live="form.evaluasiAwal.keterbatasanFisikKognitif.ada"
                                    :disabled="$isFormLocked" />
                                <x-radio-button label="Tidak ada" value="0" name="keterbatasanFk"
                                    wire:model.live="form.evaluasiAwal.keterbatasanFisikKognitif.ada"
                                    :disabled="$isFormLocked" />
                            </div>
                            <x-text-input wire:model.blur="form.evaluasiAwal.keterbatasanFisikKognitif.keterangan" :error="$errors->has('form.evaluasiAwal.keterbatasanFisikKognitif.keterangan')"
                                class="w-full mt-2" placeholder="Keterangan jika ada keterbatasan"
                                :disabled="$isFormLocked" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div>
                            <x-input-label value="Nilai, keyakinan, dan budaya yang dianut" />
                            <div class="flex gap-2 mt-1">
                                <x-radio-button label="Ada" value="1" name="nilaiBudaya"
                                    wire:model.live="form.evaluasiAwal.nilaiKeyakinanBudaya.ada"
                                    :disabled="$isFormLocked" />
                                <x-radio-button label="Tidak ada" value="0" name="nilaiBudaya"
                                    wire:model.live="form.evaluasiAwal.nilaiKeyakinanBudaya.ada"
                                    :disabled="$isFormLocked" />
                            </div>
                            <x-textarea wire:model.blur="form.evaluasiAwal.nilaiKeyakinanBudaya.deskripsi" :error="$errors->has('form.evaluasiAwal.nilaiKeyakinanBudaya.deskripsi')"
                                class="w-full mt-2" rows="2"
                                placeholder="Jelaskan nilai/kepercayaan/budaya yang relevan"
                                :disabled="$isFormLocked" />
                        </div>
                        <div>
                            <x-input-label value="Preferensi menerima informasi" />
                            <div class="flex flex-wrap gap-3 mt-1">
                                @foreach ($prefList as $key => $label)
                                    <div wire:key="pref-{{ $key }}">
                                        <x-toggle
                                            :current="in_array($key, $form['evaluasiAwal']['preferensiInformasi']['opsi'] ?? []) ? '1' : '0'"
                                            trueValue="1" falseValue="0"
                                            wireClick="toggleArrayOpt('form.evaluasiAwal.preferensiInformasi.opsi', '{{ $key }}')"
                                            :label="$label" :disabled="$isFormLocked" />
                                    </div>
                                @endforeach
                            </div>
                            @if (in_array('lainnya', $form['evaluasiAwal']['preferensiInformasi']['opsi'] ?? []))
                                <x-text-input wire:model.blur="form.evaluasiAwal.preferensiInformasi.lainnya"
                                    class="w-full mt-2" placeholder="Sebutkan preferensi lainnya"
                                    :error="$errors->has('form.evaluasiAwal.preferensiInformasi.lainnya')"
                                    :disabled="$isFormLocked" />
                                <x-input-error :messages="$errors->get('form.evaluasiAwal.preferensiInformasi.lainnya')" class="mt-1" />
                            @endif
                        </div>
                    </div>
                </div>

                <hr class="border-hairline dark:border-gray-700">

                {{-- ─── 3) KEBUTUHAN EDUKASI ─── --}}
                <div>
                    <h4 class="text-sm font-semibold text-ink dark:text-gray-100 mb-2">
                        3) Kebutuhan Edukasi <span class="text-xs font-normal text-muted">(boleh lebih dari satu)</span>
                    </h4>
                    <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                        @foreach ($kebutuhanList as $key => $label)
                            <div wire:key="need-{{ $key }}">
                                <x-toggle
                                    :current="in_array($key, $form['kebutuhan']['opsi'] ?? []) ? '1' : '0'"
                                    trueValue="1" falseValue="0"
                                    wireClick="toggleArrayOpt('form.kebutuhan.opsi', '{{ $key }}')"
                                    :label="$label" :disabled="$isFormLocked" />
                            </div>
                        @endforeach
                    </div>
                    @if (in_array('lainnya', $form['kebutuhan']['opsi'] ?? []))
                        <x-text-input wire:model.blur="form.kebutuhan.lainnya" class="w-full mt-2"
                            placeholder="Sebutkan kebutuhan lainnya"
                            :error="$errors->has('form.kebutuhan.lainnya')" />
                        <x-input-error :messages="$errors->get('form.kebutuhan.lainnya')" class="mt-1" />
                    @endif
                </div>

                <hr class="border-hairline dark:border-gray-700">

                {{-- ─── 4) METODE & MEDIA ─── --}}
                <div>
                    <h4 class="text-sm font-semibold text-ink dark:text-gray-100 mb-2">
                        4) Metode & Media Edukasi
                    </h4>
                    <div class="grid grid-cols-1 gap-2 md:grid-cols-3">
                        @foreach ($metodeList as $key => $label)
                            <div wire:key="metode-{{ $key }}">
                                <x-toggle
                                    :current="in_array($key, $form['metodeMedia']['opsi'] ?? []) ? '1' : '0'"
                                    trueValue="1" falseValue="0"
                                    wireClick="toggleArrayOpt('form.metodeMedia.opsi', '{{ $key }}')"
                                    :label="$label" :disabled="$isFormLocked" />
                            </div>
                        @endforeach
                    </div>
                    @if (in_array('lainnya', $form['metodeMedia']['opsi'] ?? []))
                        <x-text-input wire:model.blur="form.metodeMedia.lainnya" class="w-full mt-2"
                            placeholder="Sebutkan metode/media lainnya"
                            :error="$errors->has('form.metodeMedia.lainnya')" />
                        <x-input-error :messages="$errors->get('form.metodeMedia.lainnya')" class="mt-1" />
                    @endif
                </div>

                <hr class="border-hairline dark:border-gray-700">

                {{-- ─── 5) HASIL EDUKASI ─── --}}
                <div class="space-y-2">
                    <h4 class="text-sm font-semibold text-ink dark:text-gray-100">5) Hasil Edukasi</h4>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        @foreach ($hasilList as $key => $label)
                            <div class="p-3 border border-hairline rounded-lg bg-canvas dark:bg-gray-800 dark:border-gray-700"
                                wire:key="hasil-{{ $key }}">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-sm">{{ $label }}</span>
                                    <div class="flex gap-2">
                                        <x-radio-button label="Ya" value="1" name="hasil-{{ $key }}"
                                            wire:model.live="form.hasil.{{ $key }}.ya"
                                            :disabled="$isFormLocked" />
                                        <x-radio-button label="Tidak" value="0" name="hasil-{{ $key }}"
                                            wire:model.live="form.hasil.{{ $key }}.ya"
                                            :disabled="$isFormLocked" />
                                    </div>
                                </div>
                                <x-text-input wire:model.blur="form.hasil.{{ $key }}.keterangan"
                                    class="w-full mt-2" placeholder="Keterangan"
                                    :disabled="$isFormLocked" />
                            </div>
                        @endforeach
                    </div>
                </div>

                <hr class="border-hairline dark:border-gray-700">

                {{-- ─── 6) TINDAK LANJUT ─── --}}
                <div class="space-y-2">
                    <h4 class="text-sm font-semibold text-ink dark:text-gray-100">6) Tindak Lanjut</h4>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                        <div>
                            <x-input-label value="Edukasi lanjutan (dd/mm/yyyy)" />
                            <div class="flex items-end gap-2 mt-1">
                                <x-text-input wire:model="form.tindakLanjut.edukasiLanjutanTanggal"
                                    class="flex-1 font-mono" placeholder="dd/mm/yyyy"
                                    :error="$errors->has('form.tindakLanjut.edukasiLanjutanTanggal')"
                                    :disabled="$isFormLocked" />
                                <x-secondary-button wire:click="setEdukasiLanjutanToday" type="button"
                                    :disabled="$isFormLocked">Hari Ini</x-secondary-button>
                            </div>
                        </div>
                        <div class="md:col-span-2">
                            <x-input-label value="Rujuk ke (boleh lebih dari satu)" />
                            <div class="flex flex-wrap gap-3 mt-1">
                                @foreach ($rujukList as $key => $label)
                                    <div wire:key="rujuk-{{ $key }}">
                                        <x-toggle
                                            :current="in_array($key, $form['tindakLanjut']['dirujukKe'] ?? []) ? '1' : '0'"
                                            trueValue="1" falseValue="0"
                                            wireClick="toggleArrayOpt('form.tindakLanjut.dirujukKe', '{{ $key }}')"
                                            :label="$label" :disabled="$isFormLocked" />
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="md:col-span-3">
                            <x-toggle wire:model.live="form.tindakLanjut.tidakPerluTL"
                                :trueValue="true" :falseValue="false"
                                label="Tidak diperlukan tindak lanjut"
                                :disabled="$isFormLocked" />
                        </div>
                    </div>
                </div>

                <hr class="border-hairline dark:border-gray-700">

                {{-- ─── 7) TANDA TANGAN ─── --}}
                <div class="space-y-3">
                    <h4 class="text-sm font-semibold text-ink dark:text-gray-100">7) Tanda Tangan Pasien / Keluarga</h4>
                    @if (!$isFormLocked)
                        <div>
                            <x-input-label value="Tanda Tangan" />
                            <div class="mt-1">
                                <x-signature.signature-pad wireMethod="setSasaranSignature" />
                            </div>
                            @if (!empty($sasaranEdukasiSignature))
                                <div class="flex items-center gap-3 mt-2">
                                    <img src="{{ $sasaranEdukasiSignature }}" alt="TTD"
                                        class="object-contain w-32 h-16 bg-canvas border border-gray-300 rounded" />
                                    <x-secondary-button wire:click="clearSasaranSignature" type="button"
                                        class="text-xs">Hapus TTD</x-secondary-button>
                                </div>
                            @endif
                        </div>
                    @endif

                    <div>
                        <x-input-label value="Nama Pasien / Keluarga *" />
                        <x-text-input wire:model.blur="form.ttd.pasienKeluargaNama" class="w-full mt-1"
                            placeholder="Nama yang menandatangani"
                            :error="$errors->has('form.ttd.pasienKeluargaNama')"
                            :disabled="$isFormLocked" />
                        <x-input-error :messages="$errors->get('form.ttd.pasienKeluargaNama')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label value="Hubungan dengan Pasien *" />
                        <x-select-input wire:model.blur="form.ttd.pasienKeluargaHubungan"
                            :error="$errors->has('form.ttd.pasienKeluargaHubungan')" :disabled="$isFormLocked"
                            class="w-full mt-1">
                            <option value="">— Pilih hubungan —</option>
                            <option value="pasien">Pasien Sendiri</option>
                            <option value="suami">Suami</option>
                            <option value="istri">Istri</option>
                            <option value="ayah">Ayah</option>
                            <option value="ibu">Ibu</option>
                            <option value="anak">Anak</option>
                            <option value="saudara">Saudara</option>
                            <option value="wali_hukum">Wali Hukum</option>
                            <option value="lainnya">Lainnya</option>
                        </x-select-input>
                        <x-input-error :messages="$errors->get('form.ttd.pasienKeluargaHubungan')" class="mt-1" />
                    </div>
                </div>

                {{-- ─── ACTION FOOTER ─── --}}
                <div class="flex justify-end gap-2 pt-2">
                    <x-secondary-button wire:click="resetFormEdukasi" type="button" :disabled="$isFormLocked">
                        Reset
                    </x-secondary-button>
                    <x-primary-button wire:click="addEdukasiTerintegrasi" type="button"
                        wire:loading.attr="disabled" :disabled="$isFormLocked">
                        <span wire:loading.remove wire:target="addEdukasiTerintegrasi">+ Simpan Edukasi</span>
                        <span wire:loading wire:target="addEdukasiTerintegrasi">Menyimpan...</span>
                    </x-primary-button>
                </div>
            </div>
        </x-border-form>
    @endif

    {{-- ═══════════════ LIST RIWAYAT ═══════════════ --}}
    <x-border-form title="Riwayat Edukasi Terintegrasi" align="start" bgcolor="bg-surface-soft">
        <div class="mt-3 overflow-x-auto bg-canvas border border-hairline rounded-xl dark:border-gray-700 dark:bg-gray-900">
            @php $list = $dataDaftarRi['edukasiPasienTerintegrasi'] ?? []; @endphp
            <table class="min-w-full text-sm">
                <thead class="bg-surface-soft dark:bg-gray-800">
                    <tr class="text-left">
                        <th class="px-4 py-3 text-sm font-medium text-muted dark:text-gray-400 w-12">No</th>
                        <th class="px-4 py-3 text-sm font-medium text-muted dark:text-gray-400">Tanggal</th>
                        <th class="px-4 py-3 text-sm font-medium text-muted dark:text-gray-400">Pasien / Keluarga</th>
                        <th class="px-4 py-3 text-sm font-medium text-muted dark:text-gray-400">Petugas</th>
                        <th class="px-4 py-3 text-sm font-medium text-muted dark:text-gray-400">Status</th>
                        <th class="px-4 py-3 text-sm font-medium text-center text-muted dark:text-gray-400 w-40">Aksi</th>
                    </tr>
                </thead>
                <tbody class="text-muted divide-y divide-hairline dark:divide-gray-700 dark:text-gray-400">
            @forelse ($list as $entri)
                @php
                    $form  = $entri['form'] ?? [];
                    $id    = $entri['id'] ?? null;
                    $tgl   = $form['tglEdukasi'] ?? '-';
                    $petugasName = data_get($form, 'pemberiInformasi.petugasName', '-');
                    $pasienNama  = data_get($form, 'ttd.pasienKeluargaNama', '-');

                    $hambatanEmo = data_get($form, 'evaluasiAwal.hambatanEmosional.ada');
                    $hambatanFk  = data_get($form, 'evaluasiAwal.keterbatasanFisikKognitif.ada');
                    $isEmo       = in_array($hambatanEmo, [true, 1, '1'], true);
                    $isFk        = in_array($hambatanFk, [true, 1, '1'], true);
                    $isPahamTidak = in_array(data_get($form, 'hasil.paham.ya'), [false, 0, '0'], true);
                    $alertRow    = $isPahamTidak || $isEmo || $isFk;
                @endphp

                <tr wire:key="edu-terint-{{ $id ?: $loop->index }}"
                    class="align-top hover:bg-surface-soft dark:hover:bg-gray-800/60 {{ $alertRow ? 'bg-red-50/50 dark:bg-red-900/10' : '' }}">
                    <td class="px-4 py-3 font-mono text-sm text-muted dark:text-gray-300">{{ $loop->iteration }}</td>
                    <td class="px-4 py-3 font-mono text-muted whitespace-nowrap dark:text-gray-300">{{ $tgl }}</td>
                    <td class="px-4 py-3 font-medium text-ink dark:text-white">{{ $pasienNama }}</td>
                    <td class="px-4 py-3">{{ $petugasName }}</td>
                    <td class="px-4 py-3">
                        @if ($alertRow)
                            <x-badge variant="danger">⚠ Risiko</x-badge>
                        @else
                            <x-badge variant="success">OK</x-badge>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex justify-center gap-2">
                            @if ($id)
                                <x-secondary-button wire:click="cetak('{{ $id }}')"
                                    wire:loading.attr="disabled" wire:target="cetak('{{ $id }}')"
                                    class="px-2 py-1 text-sm">
                                    <span wire:loading.remove wire:target="cetak('{{ $id }}')" class="flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                        </svg>
                                        Cetak
                                    </span>
                                    <span wire:loading wire:target="cetak('{{ $id }}')" class="flex items-center gap-1">
                                        <x-loading /> Mencetak...
                                    </span>
                                </x-secondary-button>
                            @endif
                            @if (!$isFormLocked && $id)
                                <x-outline-button type="button"
                                    wire:click.prevent="removeEdukasiTerintegrasiById('{{ $id }}')"
                                    wire:confirm="Hapus data edukasi terintegrasi ini?"
                                    wire:loading.attr="disabled"
                                    class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300 !px-2 !py-1"
                                    title="Hapus">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </x-outline-button>
                            @endif
                        </div>
                    </td>
                </tr>

            @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-muted-soft">Belum ada data edukasi terintegrasi.</td>
                </tr>
            @endforelse
                </tbody>
            </table>
        </div>
    </x-border-form>

</div>
