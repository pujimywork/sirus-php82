<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/pengkajian-awal-ginekologi-ri/rm-pengkajian-awal-ginekologi-ri-actions.blade.php
// Dokumen VK/Kebidanan #2 — Pengkajian Awal Ginekologi (gabungan RM 45 + 45.a).
// Pola sama dgn Pengkajian Awal Obstetri (modul dokumen RI): multi-entri, simpan ke datadaftarri_json.
// [scan] = field dari form fisik; [akr] = tambahan akreditasi (PP 1.2 / asesmen awal).

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
    protected array $renderAreas = ['modal-pengkajian-awal-ginekologi-ri'];

    /** Key penyimpanan di datadaftarri_json */
    private string $jsonKey = 'pengkajianAwalGinekologiRI';

    public array $newForm = [
        // 1. Data pengkajian
        'jamPengkajian'      => '',
        'caraMasuk'          => '',   // Datang sendiri | Rujukan
        'caraMasukRujukan'   => '',
        // 2. Sosial pasien
        'pekerjaan'          => '',
        'pendidikan'         => '',
        'agama'              => '',
        'suku'               => '',
        'psikososial'        => '',   // [akr]
        'ekonomi'            => '',   // [akr]
        // 3. Suami / Penanggung Jawab
        'namaSuami'          => '',
        'umurSuami'          => '',
        'pekerjaanSuami'     => '',
        'pendidikanSuami'    => '',
        'agamaSuami'         => '',
        'sukuSuami'          => '',
        // 4. Riwayat
        'alergiObat'         => '',
        'riwayatObat'        => '',   // [akr]
        'penyakitPenting'    => [],   // checkbox multi
        'penyakitLain'       => '',
        // 5. Ginekologi
        'hpht'               => '',
        'menarcheUmur'       => '',
        'menopause'          => '',
        'menikahKali'        => '',
        'menikahLama'        => '',
        'anakHidup'          => '',
        'anakMati'           => '',
        'anakTerkecilUmur'   => '',
        'kontrasepsi'        => '',
        'riwayatHaid'        => '',
        'riwayatKeputihan'   => '',
        'riwayatPersalinanLalu' => '',
        // 6. Keluhan
        'keluhanUtama'       => '',
        'riwayatPenyakitSekarang' => '',
        // 7. Status Umum / TTV
        'keadaanUmum'        => '',
        'td'                 => '',
        'nadi'               => '',
        'respirasi'          => '',
        'suhuRectal'         => '',
        'suhuAxiler'         => '',
        'conjungtiva'        => '',
        'edema'              => '',
        'cor'                => '',
        'pulmo'              => '',
        // 8. Pemeriksaan Dalam
        'jenisPemeriksaan'   => '',   // VT | RT | Inspeculo
        'vulvaVagina'        => '',
        'corpusUteri'        => '',
        'portio'             => '',
        'adnexaKanan'        => '',
        'adnexaKiri'         => '',
        'cavumDouglasi'      => '',
        // 9. Skrining
        'skalaNyeri'         => '',
        'risikoJatuh'        => '',
        'skriningGizi'       => '',   // [akr]
        'pengkajianFungsional' => '', // [akr]
        'kebutuhanEdukasi'   => '',   // [akr]
        // 10. Status Lokalis (Dokter)
        'abdomen'            => '',
        'genitalia'          => '',
        // 11. Diagnosa & Rencana
        'diagnosa'           => '',
        'rencanaTindakan'    => '',
        'dischargePlanning'  => '',   // [akr]
        // 12. Penutup
        'ttd'                => '',   // nama penanda-tangan (myuser_name)
        'ttdDate'            => '',   // tgl/jam TTD (d/m/Y H:i:s)
        'ttdCode'            => '',   // myuser_code penanda-tangan
    ];

    public array $entriList = [];

    public array $penyakitOptions = [
        'Jantung', 'Diabetes', 'Hypertensi', 'Ginjal', 'Tuberculosis',
        'Asthma Bronchiale', 'Anemia', 'Penyakit Kelamin', 'Tumor Kandungan',
    ];
    public array $pekerjaanOptions = ['Tani', 'PNS', 'Swasta', 'ABRI', 'IRT', 'Lainnya'];
    public array $pendidikanOptions = ['TK', 'SD', 'SLTP', 'SLTA', 'Sarjana', 'Lainnya'];

    protected function rules(): array
    {
        return [
            'newForm.diagnosa' => 'required|string',
        ];
    }

    protected function messages(): array
    {
        return [
            'newForm.diagnosa.required' => 'Diagnosa harus diisi.',
        ];
    }

    public function mount(?string $riHdrNo = null, bool $disabled = false): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-pengkajian-awal-ginekologi-ri']);

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

    public function openModal(): void
    {
        if (!$this->riHdrNo || $this->disabled) {
            return;
        }

        $this->resetNewForm();
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

        $this->incrementVersion('modal-pengkajian-awal-ginekologi-ri');
        $this->dispatch('open-modal', name: 'pengkajian-awal-ginekologi-ri');
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: 'pengkajian-awal-ginekologi-ri');
    }

    /** TTD: stamp nama user login + tgl/jam ke entri saat ini. */
    public function ttdSaya(): void
    {
        if ($this->isFormLocked) {
            return;
        }
        if (!empty($this->newForm['ttd'])) {
            $this->dispatch('toast', type: 'warning', message: 'Tanda tangan sudah ada.');
            return;
        }
        $this->newForm['ttd']     = auth()->user()->myuser_name ?? '';
        $this->newForm['ttdCode'] = auth()->user()->myuser_code ?? '';
        $this->newForm['ttdDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /** Batalkan TTD (untuk tanda tangan ulang). */
    public function hapusTtd(): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->newForm['ttd']     = '';
        $this->newForm['ttdCode'] = '';
        $this->newForm['ttdDate'] = '';
    }

    public function setJamSekarang(string $field): void
    {
        $this->newForm[$field] = Carbon::now(config('app.timezone'))->format('H:i');
    }

    public function setTglSekarang(string $field): void
    {
        $this->newForm[$field] = Carbon::now(config('app.timezone'))->format('Y-m-d');
    }

    public function togglePenyakit(string $opt): void
    {
        $list = $this->newForm['penyakitPenting'] ?? [];
        if (($k = array_search($opt, $list, true)) !== false) {
            unset($list[$k]);
        } else {
            $list[] = $opt;
        }
        $this->newForm['penyakitPenting'] = array_values($list);
    }

    private function resetNewForm(): void
    {
        foreach ($this->newForm as $k => $v) {
            $this->newForm[$k] = is_array($v) ? [] : '';
        }
    }

    public function addEntry(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }

        $this->validateWithToast();

        $entry = $this->newForm;
        $entry['createdAt'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        try {
            DB::transaction(function () use ($entry) {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?: [];
                if (!isset($fresh[$this->jsonKey]) || !is_array($fresh[$this->jsonKey])) {
                    $fresh[$this->jsonKey] = [];
                }
                $fresh[$this->jsonKey][] = $entry;

                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
                $this->entriList = $fresh[$this->jsonKey];

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Tambah Pengkajian Awal Ginekologi — ' . ($entry['ttd'] ?? '-') . ' — ' . $entry['createdAt'], 'MR');
            });

            $this->incrementVersion('modal-pengkajian-awal-ginekologi-ri');
            $this->dispatch('toast', type: 'success', message: 'Pengkajian awal ginekologi berhasil disimpan.');
            $this->resetNewForm();
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

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
                    ->reject(fn($e) => ($e['createdAt'] ?? null) === $createdAt)
                    ->values()
                    ->all();

                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
                $this->entriList = $fresh[$this->jsonKey];

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Pengkajian Awal Ginekologi — ' . $createdAt, 'MR');
            });

            $this->incrementVersion('modal-pengkajian-awal-ginekologi-ri');
            $this->dispatch('toast', type: 'success', message: 'Entri dihapus.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    public function cetak(string $createdAt)
    {
        $entry = collect($this->entriList)->firstWhere('createdAt', $createdAt);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Data pengkajian tidak ditemukan.');
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
            $pdf = Pdf::loadView('pages.components.modul-dokumen.r-i.pengkajian-awal-ginekologi-ri.cetak-pengkajian-awal-ginekologi-ri-print', ['data' => $data])->setPaper('A4');

            return response()->streamDownload(fn() => print $pdf->output(), 'pengkajian-awal-ginekologi-' . ($pasien['regNo'] ?? $this->riHdrNo) . '.pdf');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cetak: ' . $e->getMessage());
        }
    }
};
?>

<div>
    {{-- ══ SUMMARY CARD (inline di tab) ══ --}}
    @php $paCount = count($entriList ?? []); @endphp
    <div class="p-5 border shadow-sm bg-canvas border-hairline rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex-1 space-y-2">
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">Pengkajian Awal Ginekologi</h3>
                    @if ($paCount > 0)
                        <x-badge variant="success">{{ $paCount }} entri</x-badge>
                    @else
                        <x-badge variant="warning">Belum ada</x-badge>
                    @endif
                </div>
                <p class="text-sm text-muted dark:text-gray-400">
                    Pengkajian awal pasien ginekologi (RM 45/45.a) — identitas & sosial, riwayat ginekologi & haid,
                    keluhan, TTV, pemeriksaan dalam & status lokalis, skrining (PP 1.2), diagnosa & rencana. Diisi Bidan/Dokter.
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
    </div>

    {{-- ══ MODAL FORM ══ --}}
    <x-modal name="pengkajian-awal-ginekologi-ri" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
             wire:key="{{ $this->renderKey('modal-pengkajian-awal-ginekologi-ri', [$riHdrNo ?? 'new']) }}">

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
                            <h2 class="text-lg font-semibold text-ink dark:text-gray-100">Pengkajian Awal Ginekologi</h2>
                            <p class="mt-0.5 text-sm text-muted dark:text-gray-400">RM 45 / 45.a — kebidanan (VK). Diisi Bidan / Dokter.</p>
                        </div>
                    </div>
                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <span class="sr-only">Tutup</span>
                        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 overflow-y-auto bg-surface-soft dark:bg-gray-950/20">
                <div class="max-w-5xl mx-auto space-y-4">

                    <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                        wire:key="pengkajian-ginekologi-display-pasien-{{ $riHdrNo }}" />

                    @if ($isFormLocked)
                        <div class="px-4 py-2 text-sm border rounded-lg text-amber-800 bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-300">
                            Mode tampilan saja (read-only) — pasien sudah pulang / form terkunci.
                        </div>
                    @endif

                    {{-- ── FORM ENTRI ── --}}
                    <fieldset @disabled($isFormLocked) class="space-y-4">

                        {{-- 1. Data Pengkajian --}}
                        <x-border-form title="1. Data Pengkajian">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div>
                                    <x-input-label value="Jam Pengkajian" />
                                    <div class="flex gap-1 mt-1">
                                        <x-text-input type="time" wire:model="newForm.jamPengkajian" class="w-full" />
                                        <x-now-button wire:click="setJamSekarang('jamPengkajian')" />
                                    </div>
                                </div>
                                <div>
                                    <x-input-label value="Cara Masuk" />
                                    <x-select-input wire:model.live="newForm.caraMasuk" class="w-full mt-1">
                                        <option value="">—</option>
                                        <option value="Datang sendiri">Datang sendiri</option>
                                        <option value="Rujukan">Rujukan</option>
                                    </x-select-input>
                                </div>
                                <div>
                                    <x-input-label value="Perujuk (bila rujukan)" />
                                    <x-text-input wire:model="newForm.caraMasukRujukan" class="w-full mt-1" placeholder="Faskes / bidan perujuk" />
                                </div>
                            </div>
                        </x-border-form>

                        {{-- 2. Data Sosial Pasien --}}
                        <x-border-form title="2. Data Sosial Pasien">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <div>
                                    <x-input-label value="Pekerjaan" />
                                    <x-select-input wire:model="newForm.pekerjaan" class="w-full mt-1">
                                        <option value="">—</option>
                                        @foreach ($pekerjaanOptions as $opt)<option value="{{ $opt }}">{{ $opt }}</option>@endforeach
                                    </x-select-input>
                                </div>
                                <div>
                                    <x-input-label value="Pendidikan" />
                                    <x-select-input wire:model="newForm.pendidikan" class="w-full mt-1">
                                        <option value="">—</option>
                                        @foreach ($pendidikanOptions as $opt)<option value="{{ $opt }}">{{ $opt }}</option>@endforeach
                                    </x-select-input>
                                </div>
                                <div>
                                    <x-input-label value="Agama" />
                                    <x-text-input wire:model="newForm.agama" class="w-full mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Suku Bangsa" />
                                    <x-text-input wire:model="newForm.suku" class="w-full mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Psiko-sosio-spiritual" />
                                    <x-text-input wire:model="newForm.psikososial" class="w-full mt-1" placeholder="mis. tenang / cemas; dukungan keluarga; ibadah" />
                                </div>
                                <div>
                                    <x-input-label value="Ekonomi" />
                                    <x-text-input wire:model="newForm.ekonomi" class="w-full mt-1" placeholder="mis. cukup / kurang; penjamin" />
                                </div>
                            </div>
                        </x-border-form>

                        {{-- 3. Suami / Penanggung Jawab --}}
                        <x-border-form title="3. Suami / Penanggung Jawab">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <div><x-input-label value="Nama" /><x-text-input wire:model="newForm.namaSuami" class="w-full mt-1" /></div>
                                <div><x-input-label value="Umur (th)" /><x-text-input type="number" wire:model="newForm.umurSuami" class="w-full mt-1" /></div>
                                <div>
                                    <x-input-label value="Pekerjaan" />
                                    <x-select-input wire:model="newForm.pekerjaanSuami" class="w-full mt-1">
                                        <option value="">—</option>
                                        @foreach ($pekerjaanOptions as $opt)<option value="{{ $opt }}">{{ $opt }}</option>@endforeach
                                    </x-select-input>
                                </div>
                                <div>
                                    <x-input-label value="Pendidikan" />
                                    <x-select-input wire:model="newForm.pendidikanSuami" class="w-full mt-1">
                                        <option value="">—</option>
                                        @foreach ($pendidikanOptions as $opt)<option value="{{ $opt }}">{{ $opt }}</option>@endforeach
                                    </x-select-input>
                                </div>
                                <div><x-input-label value="Agama" /><x-text-input wire:model="newForm.agamaSuami" class="w-full mt-1" /></div>
                                <div><x-input-label value="Suku Bangsa" /><x-text-input wire:model="newForm.sukuSuami" class="w-full mt-1" /></div>
                            </div>
                        </x-border-form>

                        {{-- 4. Riwayat --}}
                        <x-border-form title="4. Riwayat">
                            <div class="space-y-4">
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div><x-input-label value="Alergi Obat" /><x-text-input wire:model="newForm.alergiObat" class="w-full mt-1" placeholder="Tidak ada / sebutkan" /></div>
                                    <div><x-input-label value="Riwayat Penggunaan Obat" /><x-text-input wire:model="newForm.riwayatObat" class="w-full mt-1" placeholder="Obat rutin yang dikonsumsi" /></div>
                                </div>
                                <div>
                                    <x-input-label value="Penyakit Penting yang Pernah Diderita" />
                                    <div class="grid grid-cols-2 gap-2 mt-1 sm:grid-cols-3">
                                        @foreach ($penyakitOptions as $opt)
                                            <x-toggle :current="in_array($opt, $newForm['penyakitPenting'] ?? [], true) ? 1 : 0"
                                                trueValue="1" falseValue="0"
                                                wireClick="togglePenyakit('{{ $opt }}')"
                                                :disabled="$isFormLocked">{{ $opt }}</x-toggle>
                                        @endforeach
                                    </div>
                                    <x-text-input wire:model="newForm.penyakitLain" class="w-full mt-2" placeholder="Penyakit lain (bila ada)" />
                                </div>
                            </div>
                        </x-border-form>

                        {{-- 5. Riwayat Ginekologi --}}
                        <x-border-form title="5. Riwayat Ginekologi">
                            <div class="space-y-4">
                                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                                    <div><x-input-label value="HPHT" /><div class="flex gap-1 mt-1"><x-text-input type="date" wire:model="newForm.hpht" class="w-full" /><x-now-button wire:click="setTglSekarang('hpht')" /></div></div>
                                    <div><x-input-label value="Menarche (umur th)" /><x-text-input type="number" wire:model="newForm.menarcheUmur" class="w-full mt-1" /></div>
                                    <div><x-input-label value="Menopause" /><x-text-input wire:model="newForm.menopause" class="w-full mt-1" placeholder="Ya/Tidak; umur" /></div>
                                    <div><x-input-label value="Kontrasepsi" /><x-text-input wire:model="newForm.kontrasepsi" class="w-full mt-1" placeholder="Suntik/Pil/IUD/…" /></div>
                                    <div><x-input-label value="Menikah (kali)" /><x-text-input type="number" wire:model="newForm.menikahKali" class="w-full mt-1" /></div>
                                    <div><x-input-label value="Lama Menikah (th)" /><x-text-input type="number" wire:model="newForm.menikahLama" class="w-full mt-1" /></div>
                                    <div><x-input-label value="Anak Hidup" /><x-text-input type="number" wire:model="newForm.anakHidup" class="w-full mt-1" /></div>
                                    <div><x-input-label value="Anak Mati" /><x-text-input type="number" wire:model="newForm.anakMati" class="w-full mt-1" /></div>
                                    <div><x-input-label value="Umur Anak Terkecil" /><x-text-input wire:model="newForm.anakTerkecilUmur" class="w-full mt-1" /></div>
                                </div>
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div><x-input-label value="Riwayat Haid" /><x-text-input wire:model="newForm.riwayatHaid" class="w-full mt-1" placeholder="Siklus/lama/banyak/nyeri" /></div>
                                    <div><x-input-label value="Riwayat Keputihan" /><x-text-input wire:model="newForm.riwayatKeputihan" class="w-full mt-1" placeholder="Warna/bau/gatal" /></div>
                                </div>
                                <div>
                                    <x-input-label value="Riwayat Persalinan yang Lalu" />
                                    <x-textarea wire:model="newForm.riwayatPersalinanLalu" rows="2" class="w-full mt-1" />
                                </div>
                            </div>
                        </x-border-form>

                        {{-- 6. Keluhan --}}
                        <x-border-form title="6. Keluhan">
                            <div class="space-y-4">
                                <div>
                                    <x-input-label value="Keluhan Utama" />
                                    <x-textarea wire:model="newForm.keluhanUtama" rows="2" class="w-full mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Riwayat Penyakit Sekarang" />
                                    <x-textarea wire:model="newForm.riwayatPenyakitSekarang" rows="2" class="w-full mt-1" />
                                </div>
                            </div>
                        </x-border-form>

                        {{-- 7. Status Umum / TTV --}}
                        <x-border-form title="7. Status Umum & Tanda Vital">
                            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                                <div class="col-span-2 sm:col-span-3 lg:col-span-1"><x-input-label value="Keadaan Umum" /><x-text-input wire:model="newForm.keadaanUmum" class="w-full mt-1" /></div>
                                <div><x-input-label value="TD (mmHg)" /><x-text-input wire:model="newForm.td" class="w-full mt-1" placeholder="120/80" /></div>
                                <div><x-input-label value="Nadi (x/mnt)" /><x-text-input type="number" wire:model="newForm.nadi" class="w-full mt-1" /></div>
                                <div><x-input-label value="RR (x/mnt)" /><x-text-input type="number" wire:model="newForm.respirasi" class="w-full mt-1" /></div>
                                <div><x-input-label value="Suhu Rectal (°C)" /><x-text-input wire:model="newForm.suhuRectal" class="w-full mt-1" /></div>
                                <div><x-input-label value="Suhu Axiler (°C)" /><x-text-input wire:model="newForm.suhuAxiler" class="w-full mt-1" /></div>
                                <div><x-input-label value="Conjungtiva" /><x-text-input wire:model="newForm.conjungtiva" class="w-full mt-1" /></div>
                                <div><x-input-label value="Edema" /><x-text-input wire:model="newForm.edema" class="w-full mt-1" /></div>
                                <div><x-input-label value="Cor" /><x-text-input wire:model="newForm.cor" class="w-full mt-1" /></div>
                                <div><x-input-label value="Pulmo" /><x-text-input wire:model="newForm.pulmo" class="w-full mt-1" /></div>
                            </div>
                        </x-border-form>

                        {{-- 8. Pemeriksaan Dalam --}}
                        <x-border-form title="8. Pemeriksaan Dalam">
                            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                                <div>
                                    <x-input-label value="Jenis Pemeriksaan" />
                                    <x-select-input wire:model="newForm.jenisPemeriksaan" class="w-full mt-1">
                                        <option value="">—</option>
                                        <option value="VT">VT</option>
                                        <option value="RT">RT</option>
                                        <option value="Inspeculo">Inspeculo</option>
                                    </x-select-input>
                                </div>
                                <div><x-input-label value="Vulva / Vagina" /><x-text-input wire:model="newForm.vulvaVagina" class="w-full mt-1" /></div>
                                <div><x-input-label value="Corpus Uteri" /><x-text-input wire:model="newForm.corpusUteri" class="w-full mt-1" /></div>
                                <div><x-input-label value="Portio" /><x-text-input wire:model="newForm.portio" class="w-full mt-1" /></div>
                                <div><x-input-label value="Adnexa Kanan" /><x-text-input wire:model="newForm.adnexaKanan" class="w-full mt-1" /></div>
                                <div><x-input-label value="Adnexa Kiri" /><x-text-input wire:model="newForm.adnexaKiri" class="w-full mt-1" /></div>
                                <div><x-input-label value="Cavum Douglasi" /><x-text-input wire:model="newForm.cavumDouglasi" class="w-full mt-1" /></div>
                            </div>
                        </x-border-form>

                        {{-- 9. Skrining --}}
                        <x-border-form title="9. Skrining (PP 1.2)">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <div><x-input-label value="Skala Nyeri (0–10)" /><x-text-input type="number" min="0" max="10" wire:model="newForm.skalaNyeri" class="w-full mt-1" /></div>
                                <div><x-input-label value="Risiko Jatuh" /><x-text-input wire:model="newForm.risikoJatuh" class="w-full mt-1" placeholder="Rendah/Sedang/Tinggi" /></div>
                                <div><x-input-label value="Skrining Gizi/Nutrisi" /><x-text-input wire:model="newForm.skriningGizi" class="w-full mt-1" placeholder="Risiko / tidak berisiko" /></div>
                                <div><x-input-label value="Pengkajian Fungsional" /><x-text-input wire:model="newForm.pengkajianFungsional" class="w-full mt-1" placeholder="Mandiri / dibantu" /></div>
                                <div class="lg:col-span-2"><x-input-label value="Kebutuhan Edukasi" /><x-text-input wire:model="newForm.kebutuhanEdukasi" class="w-full mt-1" placeholder="mis. perawatan, tindakan, obat" /></div>
                            </div>
                        </x-border-form>

                        {{-- 10. Status Lokalis (Dokter) --}}
                        <x-border-form title="10. Status Lokalis (Dokter)">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div><x-input-label value="Abdomen" /><x-textarea wire:model="newForm.abdomen" rows="2" class="w-full mt-1" /></div>
                                <div><x-input-label value="Genitalia" /><x-textarea wire:model="newForm.genitalia" rows="2" class="w-full mt-1" /></div>
                            </div>
                        </x-border-form>

                        {{-- 11. Diagnosa & Rencana --}}
                        <x-border-form title="11. Diagnosa & Rencana">
                            <div class="space-y-4">
                                <div>
                                    <x-input-label value="Diagnosa" />
                                    <x-textarea wire:model="newForm.diagnosa" rows="2" class="w-full mt-1" :error="$errors->has('newForm.diagnosa')" />
                                    <x-input-error :messages="$errors->get('newForm.diagnosa')" class="mt-1" />
                                </div>
                                <div><x-input-label value="Rencana Tindakan / Terapi" /><x-textarea wire:model="newForm.rencanaTindakan" rows="2" class="w-full mt-1" /></div>
                                <div><x-input-label value="Discharge Planning" /><x-textarea wire:model="newForm.dischargePlanning" rows="2" class="w-full mt-1" placeholder="Rencana pemulangan / kebutuhan pasca-rawat" /></div>
                            </div>
                        </x-border-form>

                        <x-signature.ttd-petugas :ttd="$newForm['ttd']" :date="$newForm['ttdDate'] ?? ''" :locked="$isFormLocked" />

                        <div class="flex justify-end">
                            <x-primary-button type="button" wire:click="addEntry" wire:loading.attr="disabled" wire:target="addEntry">
                                <span wire:loading.remove wire:target="addEntry">Simpan Pengkajian</span>
                                <span wire:loading wire:target="addEntry">Menyimpan…</span>
                            </x-primary-button>
                        </div>
                    </fieldset>

                    {{-- ── DAFTAR ENTRI TERSIMPAN ── --}}
                    <x-border-form title="Riwayat Pengkajian Tersimpan">
                        @forelse ($entriList as $e)
                            <div wire:key="entri-{{ $e['createdAt'] }}" class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 border rounded-lg border-hairline dark:border-gray-700">
                                <div class="text-sm">
                                    <span class="font-semibold text-ink dark:text-gray-100">{{ $e['createdAt'] }}</span>
                                    <span class="ml-2 text-muted">· {{ $e['jenisPemeriksaan'] ?? '-' }}</span>
                                    <div class="text-xs text-muted dark:text-gray-400">{{ \Illuminate\Support\Str::limit($e['diagnosa'] ?? '', 80) }}</div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <x-secondary-button type="button" wire:click="cetak('{{ $e['createdAt'] }}')" class="px-3 py-1.5 text-sm">Cetak</x-secondary-button>
                                    @unless ($isFormLocked)
                                        <x-danger-button type="button" wire:click="hapus('{{ $e['createdAt'] }}')"
                                            wire:confirm="Hapus entri pengkajian ini?" class="px-3 py-1.5 text-sm">Hapus</x-danger-button>
                                    @endunless
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-muted dark:text-gray-400">Belum ada pengkajian tersimpan.</p>
                        @endforelse
                    </x-border-form>

                </div>
            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 px-6 py-3 bg-surface-soft border-t shrink-0 border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-end">
                    <x-secondary-button type="button" wire:click="closeModal">Tutup</x-secondary-button>
                </div>
            </div>

        </div>
    </x-modal>
</div>
