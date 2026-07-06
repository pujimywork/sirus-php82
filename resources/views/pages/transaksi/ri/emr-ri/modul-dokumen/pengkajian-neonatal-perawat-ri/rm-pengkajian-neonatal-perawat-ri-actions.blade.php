<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/pengkajian-neonatal-perawat-ri/rm-pengkajian-neonatal-perawat-ri-actions.blade.php
// Dokumen VK/Kebidanan — Pengkajian Keperawatan Neonatal (RM 14 c.1/c.2), diisi Perawat/Bidan.
// Pola sama dgn Pengkajian Awal Obstetri (modul dokumen RI): multi-entri, simpan ke datadaftarri_json.

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
    protected array $renderAreas = ['modal-pengkajian-neonatal-perawat-ri'];

    /** Key penyimpanan di datadaftarri_json */
    private string $jsonKey = 'pengkajianNeonatalPerawatRI';

    public array $newForm = [
        // Riwayat Penyakit
        'keluhanUtama'        => '',
        // Antenatal
        'anc'                 => '',
        'ancTempat'           => '',
        'tt'                  => '',
        'ttKali'              => '',
        'penyulitKehamilan'   => [],  // checkbox
        'penyakitMenyertai'   => [],  // checkbox
        // Intranatal
        'umurKehamilan'       => '',
        'kondisiKelahiran'    => '',
        'jenisPersalinan'     => '',
        'penolong'            => '',
        'penyulitPersalinan'  => [],  // checkbox
        'komplikasi'          => [],  // checkbox
        'kpdLamaJam'          => '',
        // Postnatal Antropometri
        'bbl'                 => '',
        'pb'                  => '',
        'lk'                  => '',
        'ld'                  => '',
        'lila'                => '',
        'lingkarPerut'        => '',
        'traumaLahir'         => '',
        'traumaKet'           => '',
        'apgar1'              => '',
        'apgar5'              => '',
        'usahaNafas'          => '',
        'imunisasi'           => '',
        'imunisasiKet'        => '',
        // Pemeriksaan Fisik
        'kepalaBentuk'        => '',
        'mataKonjungtiva'     => '',
        'mataSklera'          => '',
        'telinga'             => '',
        'hidung'              => '',
        'mulutReflekIsap'     => '',
        'mulutBentuk'         => '',
        'dada'                => '',
        'perutBentuk'         => '',
        'taliPusat'           => '',
        'anus'                => '',
        'ekstremitas'         => '',
        // Review Sistem — B1 Pernafasan
        'b1Pernafasan'        => '',
        'b1FrekuensiNafas'    => '',
        'b1SuaraNafas'        => [],  // checkbox
        // B2 Kardiovaskuler
        'b2Bunyi'             => '',
        'b2CRT'               => '',
        'b2Akral'             => '',
        'b2Nadi'              => '',
        'b2Suhu'              => '',
        // B3 Persyarafan
        'b3Kesadaran'         => '',
        'b3Reflek'            => [],  // checkbox
        // B4 Perkemihan
        'b4Bak'               => '',
        'b4Warna'             => '',
        // B5 Pencernaan
        'b5Bab'               => [],  // checkbox
        'b5Minum'             => [],  // checkbox
        'b5JenisSusu'         => '',
        // B6 Muskuloskeletal & Integumen
        'b6Pergerakan'        => '',
        'b6Kulit'             => [],  // checkbox
        'b6Turgor'            => '',
        // Skala Nyeri NIPS
        'nipsTotal'           => '',
        'nipsInterpretasi'    => '',
        // Diagnosa Keperawatan
        'diagnosaKeperawatan' => [],  // checkbox
        // Penunjang
        'labPenunjang'        => '',
        'lainPenunjang'       => '',
        // Pengisi
        'ttd'                 => '',   // nama penanda-tangan (myuser_name)
        'ttdDate'             => '',   // tgl/jam TTD (d/m/Y H:i:s)
        'ttdCode'             => '',   // myuser_code penanda-tangan
    ];

    public array $entriList = [];

    public array $penyulitKehamilanOptions = ['Hiperemesis Gravidarum', 'Pendarahan', 'Lain'];
    public array $penyakitMenyertaiOptions = ['Hipertensi', 'TORCH', 'HIV', 'DM', 'Lain'];
    public array $penyulitPersalinanOptions = ['Letak Lintang', 'Letsu', 'Gemelly', 'Lain'];
    public array $komplikasiOptions = ['KPD', 'Eklamsia / Pre eklamsia', 'Lain'];
    public array $b1SuaraNafasOptions = ['Vesikuler', 'Stridor', 'Wheezing', 'Ronchi'];
    public array $b3ReflekOptions = ['Moro', 'Palmer Graps', 'Menggenggam'];
    public array $b5BabKonsistensiOptions = ['Cair', 'Lunak'];
    public array $b5BabWarnaOptions = ['Kuning', 'Hijau', 'Merah', 'Hitam'];
    public array $b5MinumOptions = ['Oral', 'Netek', 'NGT'];
    public array $b6KulitOptions = ['Ikterik', 'Cyanosis', 'Pucat', 'Kemerahan'];
    public array $diagnosaKeperawatanOptions = [
        'Bersihan jalan nafas tidak efektif',
        'Pola nafas tidak efektif',
        'Hipotermi',
        'Hipertermi',
        'Ketidakseimbangan glukosa darah',
        'Nutrisi kurang',
        'Ikterik neonatus',
        'Risiko aspirasi',
        'Kekurangan volume cairan',
        'Diare',
        'Konstipasi',
        'Risiko infeksi',
        'Risiko kerusakan integritas kulit',
        'Gangguan pertukaran gas',
        'Risiko perfusi jaringan otak',
        'Defisiensi pengetahuan',
        'Ketidakcukupan ASI',
        'Keterlambatan tumbuh kembang',
    ];

    protected function rules(): array
    {
        return [];
    }

    protected function messages(): array
    {
        return [];
    }

    public function mount(?string $riHdrNo = null, bool $disabled = false): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-pengkajian-neonatal-perawat-ri']);

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

        $this->incrementVersion('modal-pengkajian-neonatal-perawat-ri');
        $this->dispatch('open-modal', name: 'pengkajian-neonatal-perawat-ri');
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: 'pengkajian-neonatal-perawat-ri');
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

    /** Toggle keanggotaan $opt pada array multi-pilih newForm[$field]. */
    public function toggleArr(string $field, string $opt): void
    {
        $list = $this->newForm[$field] ?? [];
        if (($k = array_search($opt, $list, true)) !== false) {
            unset($list[$k]);
        } else {
            $list[] = $opt;
        }
        $this->newForm[$field] = array_values($list);
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

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Tambah Pengkajian Keperawatan Neonatal — ' . ($entry['ttd'] ?? '-') . ' — ' . $entry['createdAt'], 'MR');
            });

            $this->incrementVersion('modal-pengkajian-neonatal-perawat-ri');
            $this->dispatch('toast', type: 'success', message: 'Pengkajian keperawatan neonatal berhasil disimpan.');
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

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Pengkajian Keperawatan Neonatal — ' . $createdAt, 'MR');
            });

            $this->incrementVersion('modal-pengkajian-neonatal-perawat-ri');
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
            $pdf = Pdf::loadView('pages.components.modul-dokumen.r-i.pengkajian-neonatal-perawat-ri.cetak-pengkajian-neonatal-perawat-ri-print', ['data' => $data])->setPaper('A4');

            return response()->streamDownload(fn() => print $pdf->output(), 'pengkajian-neonatal-perawat-' . ($pasien['regNo'] ?? $this->riHdrNo) . '.pdf');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cetak: ' . $e->getMessage());
        }
    }
};
?>

<div>
    {{-- ══ SUMMARY CARD (inline di tab) ══ --}}
    @php $pnCount = count($entriList ?? []); @endphp
    <div class="p-5 border shadow-sm bg-canvas border-hairline rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex-1 space-y-2">
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">Pengkajian Keperawatan Neonatal</h3>
                    @if ($pnCount > 0)
                        <x-badge variant="success">{{ $pnCount }} entri</x-badge>
                    @else
                        <x-badge variant="warning">Belum ada</x-badge>
                    @endif
                </div>
                <p class="text-sm text-muted dark:text-gray-400">
                    Pengkajian keperawatan bayi baru lahir (RM 14 c.1/c.2) — antenatal, intranatal, postnatal (antropometri),
                    pemeriksaan fisik, review sistem (B1–B6), skala nyeri NIPS, diagnosa keperawatan. Diisi Perawat/Bidan.
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
    <x-modal name="pengkajian-neonatal-perawat-ri" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
             wire:key="{{ $this->renderKey('modal-pengkajian-neonatal-perawat-ri', [$riHdrNo ?? 'new']) }}">

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
                            <h2 class="text-lg font-semibold text-ink dark:text-gray-100">Pengkajian Keperawatan Neonatal</h2>
                            <p class="mt-0.5 text-sm text-muted dark:text-gray-400">RM 14 c.1/c.2 — bayi baru lahir. Diisi Perawat / Bidan.</p>
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
                        wire:key="pengkajian-neonatal-display-pasien-{{ $riHdrNo }}" />

                    @if ($isFormLocked)
                        <div class="px-4 py-2 text-sm border rounded-lg text-amber-800 bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-300">
                            Mode tampilan saja (read-only) — pasien sudah pulang / form terkunci.
                        </div>
                    @endif

                    {{-- ── FORM ENTRI ── --}}
                    <fieldset @disabled($isFormLocked) class="space-y-4">

                        {{-- 1. Riwayat Penyakit --}}
                        <x-border-form title="1. Riwayat Penyakit">
                            <div>
                                <x-input-label value="Keluhan Utama" />
                                <x-textarea wire:model="newForm.keluhanUtama" rows="2" class="w-full mt-1" />
                            </div>
                        </x-border-form>

                        {{-- 2. Antenatal --}}
                        <x-border-form title="2. Antenatal">
                            <div class="space-y-4">
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                    <div>
                                        <x-input-label value="ANC" />
                                        <x-select-input wire:model="newForm.anc" class="w-full mt-1">
                                            <option value="">—</option>
                                            <option value="Tidak Pernah">Tidak Pernah</option>
                                            <option value="Pernah">Pernah</option>
                                        </x-select-input>
                                    </div>
                                    <div><x-input-label value="ANC di" /><x-text-input wire:model="newForm.ancTempat" class="w-full mt-1" placeholder="Bidan / Dokter / Puskesmas" /></div>
                                    <div>
                                        <x-input-label value="TT" />
                                        <x-select-input wire:model="newForm.tt" class="w-full mt-1">
                                            <option value="">—</option>
                                            <option value="Tidak Pernah">Tidak Pernah</option>
                                            <option value="Pernah">Pernah</option>
                                        </x-select-input>
                                    </div>
                                    <div><x-input-label value="TT (kali)" /><x-text-input type="number" wire:model="newForm.ttKali" class="w-full mt-1" /></div>
                                </div>
                                <div>
                                    <x-input-label value="Penyulit Kehamilan" />
                                    <div class="grid grid-cols-2 gap-2 mt-1 sm:grid-cols-3">
                                        @foreach ($penyulitKehamilanOptions as $opt)
                                            <x-toggle :current="in_array($opt, $newForm['penyulitKehamilan'] ?? [], true) ? 1 : 0"
                                                trueValue="1" falseValue="0"
                                                wireClick="toggleArr('penyulitKehamilan','{{ $opt }}')"
                                                :disabled="$isFormLocked">{{ $opt }}</x-toggle>
                                        @endforeach
                                    </div>
                                </div>
                                <div>
                                    <x-input-label value="Penyakit yang Menyertai" />
                                    <div class="grid grid-cols-2 gap-2 mt-1 sm:grid-cols-3 lg:grid-cols-5">
                                        @foreach ($penyakitMenyertaiOptions as $opt)
                                            <x-toggle :current="in_array($opt, $newForm['penyakitMenyertai'] ?? [], true) ? 1 : 0"
                                                trueValue="1" falseValue="0"
                                                wireClick="toggleArr('penyakitMenyertai','{{ $opt }}')"
                                                :disabled="$isFormLocked">{{ $opt }}</x-toggle>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </x-border-form>

                        {{-- 3. Intranatal --}}
                        <x-border-form title="3. Intranatal">
                            <div class="space-y-4">
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    <div><x-input-label value="Umur Kehamilan (minggu)" /><x-text-input type="number" wire:model="newForm.umurKehamilan" class="w-full mt-1" /></div>
                                    <div>
                                        <x-input-label value="Kondisi Kelahiran" />
                                        <x-select-input wire:model="newForm.kondisiKelahiran" class="w-full mt-1">
                                            <option value="">—</option>
                                            <option value="Kurang Bulan">Kurang Bulan</option>
                                            <option value="Cukup Bulan">Cukup Bulan</option>
                                            <option value="Serotinus">Serotinus</option>
                                        </x-select-input>
                                    </div>
                                    <div>
                                        <x-input-label value="Jenis Persalinan" />
                                        <x-select-input wire:model="newForm.jenisPersalinan" class="w-full mt-1">
                                            <option value="">—</option>
                                            <option value="Spontan">Spontan</option>
                                            <option value="VE">VE</option>
                                            <option value="SC">SC</option>
                                            <option value="Forceps">Forceps</option>
                                        </x-select-input>
                                    </div>
                                    <div><x-input-label value="Penolong" /><x-text-input wire:model="newForm.penolong" class="w-full mt-1" placeholder="Bidan / Dokter" /></div>
                                    <div><x-input-label value="KPD — Lama (jam)" /><x-text-input type="number" wire:model="newForm.kpdLamaJam" class="w-full mt-1" /></div>
                                </div>
                                <div>
                                    <x-input-label value="Penyulit Persalinan" />
                                    <div class="grid grid-cols-2 gap-2 mt-1 sm:grid-cols-4">
                                        @foreach ($penyulitPersalinanOptions as $opt)
                                            <x-toggle :current="in_array($opt, $newForm['penyulitPersalinan'] ?? [], true) ? 1 : 0"
                                                trueValue="1" falseValue="0"
                                                wireClick="toggleArr('penyulitPersalinan','{{ $opt }}')"
                                                :disabled="$isFormLocked">{{ $opt }}</x-toggle>
                                        @endforeach
                                    </div>
                                </div>
                                <div>
                                    <x-input-label value="Komplikasi" />
                                    <div class="grid grid-cols-2 gap-2 mt-1 sm:grid-cols-3">
                                        @foreach ($komplikasiOptions as $opt)
                                            <x-toggle :current="in_array($opt, $newForm['komplikasi'] ?? [], true) ? 1 : 0"
                                                trueValue="1" falseValue="0"
                                                wireClick="toggleArr('komplikasi','{{ $opt }}')"
                                                :disabled="$isFormLocked">{{ $opt }}</x-toggle>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </x-border-form>

                        {{-- 4. Postnatal — Antropometri --}}
                        <x-border-form title="4. Postnatal — Antropometri">
                            <div class="space-y-4">
                                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                                    <div><x-input-label value="BBL (gr)" /><x-text-input type="number" wire:model="newForm.bbl" class="w-full mt-1" /></div>
                                    <div><x-input-label value="PB (cm)" /><x-text-input type="number" wire:model="newForm.pb" class="w-full mt-1" /></div>
                                    <div><x-input-label value="LK (cm)" /><x-text-input type="number" wire:model="newForm.lk" class="w-full mt-1" /></div>
                                    <div><x-input-label value="LD (cm)" /><x-text-input type="number" wire:model="newForm.ld" class="w-full mt-1" /></div>
                                    <div><x-input-label value="LILA (cm)" /><x-text-input type="number" wire:model="newForm.lila" class="w-full mt-1" /></div>
                                    <div><x-input-label value="Lingkar Perut (cm)" /><x-text-input type="number" wire:model="newForm.lingkarPerut" class="w-full mt-1" /></div>
                                    <div><x-input-label value="APGAR menit 1" /><x-text-input type="number" wire:model="newForm.apgar1" class="w-full mt-1" /></div>
                                    <div><x-input-label value="APGAR menit 5" /><x-text-input type="number" wire:model="newForm.apgar5" class="w-full mt-1" /></div>
                                </div>
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    <div>
                                        <x-input-label value="Trauma Lahir" />
                                        <x-select-input wire:model="newForm.traumaLahir" class="w-full mt-1">
                                            <option value="">—</option>
                                            <option value="Tidak ada">Tidak ada</option>
                                            <option value="Ada">Ada</option>
                                        </x-select-input>
                                    </div>
                                    <div><x-input-label value="Trauma — Keterangan" /><x-text-input wire:model="newForm.traumaKet" class="w-full mt-1" /></div>
                                    <div>
                                        <x-input-label value="Usaha Nafas" />
                                        <x-select-input wire:model="newForm.usahaNafas" class="w-full mt-1">
                                            <option value="">—</option>
                                            <option value="Spontan">Spontan</option>
                                            <option value="Dengan Bantuan">Dengan Bantuan</option>
                                        </x-select-input>
                                    </div>
                                    <div>
                                        <x-input-label value="Imunisasi" />
                                        <x-select-input wire:model="newForm.imunisasi" class="w-full mt-1">
                                            <option value="">—</option>
                                            <option value="Belum">Belum</option>
                                            <option value="Sudah">Sudah</option>
                                        </x-select-input>
                                    </div>
                                    <div><x-input-label value="Imunisasi — Keterangan" /><x-text-input wire:model="newForm.imunisasiKet" class="w-full mt-1" placeholder="mis. HB0, Vit K" /></div>
                                </div>
                            </div>
                        </x-border-form>

                        {{-- 5. Pemeriksaan Fisik --}}
                        <x-border-form title="5. Pemeriksaan Fisik">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <div>
                                    <x-input-label value="Kepala — Bentuk" />
                                    <x-select-input wire:model="newForm.kepalaBentuk" class="w-full mt-1">
                                        <option value="">—</option>
                                        <option value="Normal">Normal</option>
                                        <option value="Kelainan">Kelainan</option>
                                    </x-select-input>
                                </div>
                                <div>
                                    <x-input-label value="Mata — Konjungtiva" />
                                    <x-select-input wire:model="newForm.mataKonjungtiva" class="w-full mt-1">
                                        <option value="">—</option>
                                        <option value="Anemis">Anemis</option>
                                        <option value="Tidak Anemis">Tidak Anemis</option>
                                        <option value="Sekret">Sekret</option>
                                    </x-select-input>
                                </div>
                                <div>
                                    <x-input-label value="Mata — Sklera" />
                                    <x-select-input wire:model="newForm.mataSklera" class="w-full mt-1">
                                        <option value="">—</option>
                                        <option value="Ikterus">Ikterus</option>
                                        <option value="Tidak Ikterus">Tidak Ikterus</option>
                                    </x-select-input>
                                </div>
                                <div>
                                    <x-input-label value="Telinga" />
                                    <x-select-input wire:model="newForm.telinga" class="w-full mt-1">
                                        <option value="">—</option>
                                        <option value="Normal">Normal</option>
                                        <option value="Tidak Normal">Tidak Normal</option>
                                    </x-select-input>
                                </div>
                                <div>
                                    <x-input-label value="Hidung" />
                                    <x-select-input wire:model="newForm.hidung" class="w-full mt-1">
                                        <option value="">—</option>
                                        <option value="Normal">Normal</option>
                                        <option value="Tidak Normal">Tidak Normal</option>
                                        <option value="Cuping Hidung">Cuping Hidung</option>
                                    </x-select-input>
                                </div>
                                <div>
                                    <x-input-label value="Mulut — Reflek Isap" />
                                    <x-select-input wire:model="newForm.mulutReflekIsap" class="w-full mt-1">
                                        <option value="">—</option>
                                        <option value="Lemah">Lemah</option>
                                        <option value="Kuat">Kuat</option>
                                    </x-select-input>
                                </div>
                                <div>
                                    <x-input-label value="Mulut — Bentuk" />
                                    <x-select-input wire:model="newForm.mulutBentuk" class="w-full mt-1">
                                        <option value="">—</option>
                                        <option value="Normal">Normal</option>
                                        <option value="Labioskizis">Labioskizis</option>
                                        <option value="Palatoskizis">Palatoskizis</option>
                                        <option value="Labiopalatoskizis">Labiopalatoskizis</option>
                                    </x-select-input>
                                </div>
                                <div>
                                    <x-input-label value="Dada" />
                                    <x-select-input wire:model="newForm.dada" class="w-full mt-1">
                                        <option value="">—</option>
                                        <option value="Simetris">Simetris</option>
                                        <option value="Asimetris">Asimetris</option>
                                    </x-select-input>
                                </div>
                                <div>
                                    <x-input-label value="Perut — Bentuk" />
                                    <x-select-input wire:model="newForm.perutBentuk" class="w-full mt-1">
                                        <option value="">—</option>
                                        <option value="Normal">Normal</option>
                                        <option value="Distensi">Distensi</option>
                                        <option value="Kelainan">Kelainan</option>
                                    </x-select-input>
                                </div>
                                <div>
                                    <x-input-label value="Tali Pusat" />
                                    <x-select-input wire:model="newForm.taliPusat" class="w-full mt-1">
                                        <option value="">—</option>
                                        <option value="Segar">Segar</option>
                                        <option value="Layu">Layu</option>
                                        <option value="Kemerahan">Kemerahan</option>
                                        <option value="Bau">Bau</option>
                                    </x-select-input>
                                </div>
                                <div>
                                    <x-input-label value="Anus" />
                                    <x-select-input wire:model="newForm.anus" class="w-full mt-1">
                                        <option value="">—</option>
                                        <option value="Paten">Paten</option>
                                        <option value="Kelainan">Kelainan</option>
                                    </x-select-input>
                                </div>
                                <div>
                                    <x-input-label value="Ekstremitas" />
                                    <x-select-input wire:model="newForm.ekstremitas" class="w-full mt-1">
                                        <option value="">—</option>
                                        <option value="Normal">Normal</option>
                                        <option value="Kelainan">Kelainan</option>
                                    </x-select-input>
                                </div>
                            </div>
                        </x-border-form>

                        {{-- 6. Review Sistem --}}
                        <x-border-form title="6. Review Sistem (B1–B6)">
                            <div class="space-y-4">
                                {{-- B1 --}}
                                <div class="p-3 border rounded-lg border-hairline dark:border-gray-700">
                                    <p class="mb-2 text-sm font-semibold text-ink dark:text-gray-200">B1 — Pernafasan</p>
                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                        <div>
                                            <x-input-label value="Pernafasan" />
                                            <x-select-input wire:model="newForm.b1Pernafasan" class="w-full mt-1">
                                                <option value="">—</option>
                                                <option value="Spontan">Spontan</option>
                                                <option value="Dengan Bantuan">Dengan Bantuan</option>
                                            </x-select-input>
                                        </div>
                                        <div><x-input-label value="Frekuensi Nafas (x/mnt)" /><x-text-input type="number" wire:model="newForm.b1FrekuensiNafas" class="w-full mt-1" /></div>
                                        <div>
                                            <x-input-label value="Suara Nafas" />
                                            <div class="grid grid-cols-2 gap-2 mt-1">
                                                @foreach ($b1SuaraNafasOptions as $opt)
                                                    <x-toggle :current="in_array($opt, $newForm['b1SuaraNafas'] ?? [], true) ? 1 : 0"
                                                        trueValue="1" falseValue="0"
                                                        wireClick="toggleArr('b1SuaraNafas','{{ $opt }}')"
                                                        :disabled="$isFormLocked">{{ $opt }}</x-toggle>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                {{-- B2 --}}
                                <div class="p-3 border rounded-lg border-hairline dark:border-gray-700">
                                    <p class="mb-2 text-sm font-semibold text-ink dark:text-gray-200">B2 — Kardiovaskuler</p>
                                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                                        <div>
                                            <x-input-label value="Bunyi Jantung" />
                                            <x-select-input wire:model="newForm.b2Bunyi" class="w-full mt-1">
                                                <option value="">—</option>
                                                <option value="Normal">Normal</option>
                                                <option value="Murmur">Murmur</option>
                                            </x-select-input>
                                        </div>
                                        <div>
                                            <x-input-label value="CRT" />
                                            <x-select-input wire:model="newForm.b2CRT" class="w-full mt-1">
                                                <option value="">—</option>
                                                <option value="&lt;3 detik">&lt;3 detik</option>
                                                <option value="&gt;3 detik">&gt;3 detik</option>
                                            </x-select-input>
                                        </div>
                                        <div>
                                            <x-input-label value="Akral" />
                                            <x-select-input wire:model="newForm.b2Akral" class="w-full mt-1">
                                                <option value="">—</option>
                                                <option value="Hangat">Hangat</option>
                                                <option value="Dingin">Dingin</option>
                                            </x-select-input>
                                        </div>
                                        <div><x-input-label value="Nadi (x/mnt)" /><x-text-input type="number" wire:model="newForm.b2Nadi" class="w-full mt-1" /></div>
                                        <div><x-input-label value="Suhu (°C)" /><x-text-input type="number" step="0.1" wire:model="newForm.b2Suhu" class="w-full mt-1" /></div>
                                    </div>
                                </div>
                                {{-- B3 --}}
                                <div class="p-3 border rounded-lg border-hairline dark:border-gray-700">
                                    <p class="mb-2 text-sm font-semibold text-ink dark:text-gray-200">B3 — Persyarafan</p>
                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <div>
                                            <x-input-label value="Kesadaran" />
                                            <x-select-input wire:model="newForm.b3Kesadaran" class="w-full mt-1">
                                                <option value="">—</option>
                                                <option value="Compos Mentis">Compos Mentis</option>
                                                <option value="Somnolen">Somnolen</option>
                                                <option value="Coma">Coma</option>
                                                <option value="Apatis">Apatis</option>
                                            </x-select-input>
                                        </div>
                                        <div>
                                            <x-input-label value="Reflek" />
                                            <div class="grid grid-cols-2 gap-2 mt-1 sm:grid-cols-3">
                                                @foreach ($b3ReflekOptions as $opt)
                                                    <x-toggle :current="in_array($opt, $newForm['b3Reflek'] ?? [], true) ? 1 : 0"
                                                        trueValue="1" falseValue="0"
                                                        wireClick="toggleArr('b3Reflek','{{ $opt }}')"
                                                        :disabled="$isFormLocked">{{ $opt }}</x-toggle>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                {{-- B4 --}}
                                <div class="p-3 border rounded-lg border-hairline dark:border-gray-700">
                                    <p class="mb-2 text-sm font-semibold text-ink dark:text-gray-200">B4 — Perkemihan</p>
                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <div>
                                            <x-input-label value="BAK" />
                                            <x-select-input wire:model="newForm.b4Bak" class="w-full mt-1">
                                                <option value="">—</option>
                                                <option value="Lancar">Lancar</option>
                                                <option value="Retensi">Retensi</option>
                                                <option value="Inkontinensia">Inkontinensia</option>
                                                <option value="Dower Chateter">Dower Chateter</option>
                                            </x-select-input>
                                        </div>
                                        <div>
                                            <x-input-label value="Warna" />
                                            <x-select-input wire:model="newForm.b4Warna" class="w-full mt-1">
                                                <option value="">—</option>
                                                <option value="Jernih">Jernih</option>
                                                <option value="Keruh">Keruh</option>
                                                <option value="Merah">Merah</option>
                                            </x-select-input>
                                        </div>
                                    </div>
                                </div>
                                {{-- B5 --}}
                                <div class="p-3 border rounded-lg border-hairline dark:border-gray-700">
                                    <p class="mb-2 text-sm font-semibold text-ink dark:text-gray-200">B5 — Pencernaan</p>
                                    <div class="space-y-3">
                                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                            <div>
                                                <x-input-label value="BAB — Konsistensi" />
                                                <div class="flex flex-wrap gap-3 mt-1">
                                                    @foreach ($b5BabKonsistensiOptions as $opt)
                                                        <x-toggle :current="in_array($opt, $newForm['b5Bab'] ?? [], true) ? 1 : 0"
                                                            trueValue="1" falseValue="0"
                                                            wireClick="toggleArr('b5Bab','{{ $opt }}')"
                                                            :disabled="$isFormLocked">{{ $opt }}</x-toggle>
                                                    @endforeach
                                                    @foreach ($b5BabWarnaOptions as $opt)
                                                        <x-toggle :current="in_array($opt, $newForm['b5Bab'] ?? [], true) ? 1 : 0"
                                                            trueValue="1" falseValue="0"
                                                            wireClick="toggleArr('b5Bab','{{ $opt }}')"
                                                            :disabled="$isFormLocked">{{ $opt }}</x-toggle>
                                                    @endforeach
                                                </div>
                                            </div>
                                            <div>
                                                <x-input-label value="Minum" />
                                                <div class="flex flex-wrap gap-3 mt-1">
                                                    @foreach ($b5MinumOptions as $opt)
                                                        <x-toggle :current="in_array($opt, $newForm['b5Minum'] ?? [], true) ? 1 : 0"
                                                            trueValue="1" falseValue="0"
                                                            wireClick="toggleArr('b5Minum','{{ $opt }}')"
                                                            :disabled="$isFormLocked">{{ $opt }}</x-toggle>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                        <div class="sm:w-1/2">
                                            <x-input-label value="Jenis Susu" />
                                            <x-select-input wire:model="newForm.b5JenisSusu" class="w-full mt-1">
                                                <option value="">—</option>
                                                <option value="ASI">ASI</option>
                                                <option value="PASI">PASI</option>
                                            </x-select-input>
                                        </div>
                                    </div>
                                </div>
                                {{-- B6 --}}
                                <div class="p-3 border rounded-lg border-hairline dark:border-gray-700">
                                    <p class="mb-2 text-sm font-semibold text-ink dark:text-gray-200">B6 — Muskuloskeletal & Integumen</p>
                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                        <div>
                                            <x-input-label value="Pergerakan" />
                                            <x-select-input wire:model="newForm.b6Pergerakan" class="w-full mt-1">
                                                <option value="">—</option>
                                                <option value="Bebas">Bebas</option>
                                                <option value="Terbatas">Terbatas</option>
                                            </x-select-input>
                                        </div>
                                        <div>
                                            <x-input-label value="Kulit" />
                                            <div class="grid grid-cols-2 gap-2 mt-1">
                                                @foreach ($b6KulitOptions as $opt)
                                                    <x-toggle :current="in_array($opt, $newForm['b6Kulit'] ?? [], true) ? 1 : 0"
                                                        trueValue="1" falseValue="0"
                                                        wireClick="toggleArr('b6Kulit','{{ $opt }}')"
                                                        :disabled="$isFormLocked">{{ $opt }}</x-toggle>
                                                @endforeach
                                            </div>
                                        </div>
                                        <div>
                                            <x-input-label value="Turgor" />
                                            <x-select-input wire:model="newForm.b6Turgor" class="w-full mt-1">
                                                <option value="">—</option>
                                                <option value="Baik">Baik</option>
                                                <option value="Cukup">Cukup</option>
                                                <option value="Jelek">Jelek</option>
                                            </x-select-input>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </x-border-form>

                        {{-- 7. Skala Nyeri NIPS --}}
                        <x-border-form title="7. Skala Nyeri (NIPS)">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div><x-input-label value="Total Skor NIPS" /><x-text-input type="number" min="0" max="7" wire:model="newForm.nipsTotal" class="w-full mt-1" /></div>
                                <div class="sm:col-span-2">
                                    <x-input-label value="Interpretasi" />
                                    <x-text-input wire:model="newForm.nipsInterpretasi" class="w-full mt-1" placeholder="1–3 ringan, 4–5 sedang, 6–7 berat" />
                                </div>
                            </div>
                        </x-border-form>

                        {{-- 8. Diagnosa Keperawatan --}}
                        <x-border-form title="8. Diagnosa Keperawatan">
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($diagnosaKeperawatanOptions as $opt)
                                    <x-toggle :current="in_array($opt, $newForm['diagnosaKeperawatan'] ?? [], true) ? 1 : 0"
                                        trueValue="1" falseValue="0"
                                        wireClick="toggleArr('diagnosaKeperawatan','{{ $opt }}')"
                                        :disabled="$isFormLocked" class="items-start">{{ $opt }}</x-toggle>
                                @endforeach
                            </div>
                        </x-border-form>

                        {{-- 9. Penunjang --}}
                        <x-border-form title="9. Penunjang">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div><x-input-label value="Laboratorium" /><x-textarea wire:model="newForm.labPenunjang" rows="2" class="w-full mt-1" /></div>
                                <div><x-input-label value="Lain-lain" /><x-textarea wire:model="newForm.lainPenunjang" rows="2" class="w-full mt-1" /></div>
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
                                    <span class="ml-2 text-muted">· BBL {{ $e['bbl'] ?? '-' }} gr</span>
                                    <div class="text-xs text-muted dark:text-gray-400">{{ \Illuminate\Support\Str::limit(collect($e['diagnosaKeperawatan'] ?? [])->filter()->implode(', '), 80) }}</div>
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
