<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/laporan-persalinan-ri/rm-laporan-persalinan-ri-actions.blade.php
// Dokumen VK/Kebidanan — Laporan Tindakan Persalinan (RM 44.c).
// Pola sama dgn Pengkajian Awal Obstetri: single-entri besar, multi-entri list, simpan ke datadaftarri_json.
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

    protected function rules(): array
    {
        return [
        ];
    }

    protected function messages(): array
    {
        return [
        ];
    }

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

        $this->incrementVersion('modal-laporan-persalinan-ri');
        $this->dispatch('open-modal', name: 'laporan-persalinan-ri');
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: 'laporan-persalinan-ri');
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

    public function setTglJamSekarang(string $field): void
    {
        $this->newForm[$field] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
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

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Tambah Laporan Persalinan — ' . ($entry['ttd'] ?? '-') . ' — ' . $entry['createdAt'], 'MR');
            });

            $this->incrementVersion('modal-laporan-persalinan-ri');
            $this->dispatch('toast', type: 'success', message: 'Laporan persalinan berhasil disimpan.');
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

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Laporan Persalinan — ' . $createdAt, 'MR');
            });

            $this->incrementVersion('modal-laporan-persalinan-ri');
            $this->dispatch('toast', type: 'success', message: 'Entri dihapus.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

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
                    Laporan tindakan persalinan (RM 44.c) — jenis partus, data bayi & APGAR, plasenta, tali pusat,
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
                            <p class="mt-0.5 text-sm text-muted dark:text-gray-400">RM 44.c — kebidanan (VK). Diisi Dokter.</p>
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
                        wire:key="laporan-persalinan-display-pasien-{{ $riHdrNo }}" />

                    @if ($isFormLocked)
                        <div class="px-4 py-2 text-sm border rounded-lg text-amber-800 bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-300">
                            Mode tampilan saja (read-only) — pasien sudah pulang / form terkunci.
                        </div>
                    @endif

                    {{-- ── FORM ENTRI ── --}}
                    <fieldset @disabled($isFormLocked) class="space-y-4">

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
                                            <x-now-button wire:click="setTglJamSekarang('bayiLahirTgl')" />
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
                                        <x-now-button wire:click="setTglJamSekarang('plasentaLahirTgl')" />
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

                        <x-signature.ttd-petugas :ttd="$newForm['ttd']" :date="$newForm['ttdDate'] ?? ''" :locked="$isFormLocked" />

                        <div class="flex justify-end">
                            <x-primary-button type="button" wire:click="addEntry" wire:loading.attr="disabled" wire:target="addEntry">
                                <span wire:loading.remove wire:target="addEntry">Simpan Laporan</span>
                                <span wire:loading wire:target="addEntry">Menyimpan…</span>
                            </x-primary-button>
                        </div>
                    </fieldset>

                    {{-- ── DAFTAR ENTRI TERSIMPAN ── --}}
                    <x-border-form title="Riwayat Laporan Persalinan Tersimpan">
                        @forelse ($entriList as $e)
                            <div wire:key="entri-{{ $e['createdAt'] }}" class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 border rounded-lg border-hairline dark:border-gray-700">
                                <div class="text-sm">
                                    <span class="font-semibold text-ink dark:text-gray-100">{{ $e['createdAt'] }}</span>
                                    <span class="ml-2 text-muted">· {{ $e['jenisPartus'] ?? '-' }}</span>
                                    <div class="text-xs text-muted dark:text-gray-400">
                                        Bayi: {{ $e['bayiJenisKelamin'] ?? '-' }}, {{ $e['bayiBb'] ?? '-' }} gr, APGAR {{ $e['bayiApgar'] ?? '-' }} — {{ $e['bayiKeadaan'] ?? '-' }}
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <x-secondary-button type="button" wire:click="cetak('{{ $e['createdAt'] }}')" class="px-3 py-1.5 text-sm">Cetak</x-secondary-button>
                                    @unless ($isFormLocked)
                                        <x-danger-button type="button" wire:click="hapus('{{ $e['createdAt'] }}')"
                                            wire:confirm="Hapus entri laporan persalinan ini?" class="px-3 py-1.5 text-sm">Hapus</x-danger-button>
                                    @endunless
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-muted dark:text-gray-400">Belum ada laporan persalinan tersimpan.</p>
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
