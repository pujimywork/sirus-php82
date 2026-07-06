<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/indikator-sc-ri/rm-indikator-sc-ri-actions.blade.php
// Dokumen VK/Kebidanan — Indikator Proses SC (audit sectio caesaria).
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
    protected array $renderAreas = ['modal-indikator-sc-ri'];

    /** Key penyimpanan di datadaftarri_json */
    private string $jsonKey = 'indikatorScRI';

    public array $newForm = [
        'indikator'            => [], // array 15 item: '' | 'Ya' | 'Tidak'
        'diagnosisKlasifikasi' => '', // radio a-j
        'indikasiSc'           => [], // checkbox multi
        'indikasiScLain'       => '',
        'ttd'                  => '',   // nama penanda-tangan (myuser_name)
        'ttdDate'              => '',   // tgl/jam TTD (d/m/Y H:i:s)
        'ttdCode'              => '',   // myuser_code penanda-tangan
    ];

    public array $entriList = [];

    /** 15 pertanyaan indikator proses SC */
    public array $indikatorPertanyaan = [
        'Pasien melakukan ANC minimal 3x di RS tersebut',
        'Pasien memiliki & membawa buku pink KIA sebelum SC',
        'Pasien datang dengan KU baik sebelum tindakan SC',
        'Pasien datang dengan GCS normal (14-15) sebelum SC',
        'Perubahan TD sistolik >30 mnt sebelum & sesudah SC disertai gejala syok',
        'Diperiksa darah lengkap sebelum SC (Hb, Leukosit, Trombosit, Ht)',
        'Diperiksa darah lengkap setelah SC (Hb, Leukosit, Trombosit, Ht)',
        'Diperiksa PT/APTT atau CT/BT sebelum SC',
        'Dicatat golongan darah sebelum SC',
        'Dilakukan transfusi sesuai indikasi dan/atau Hb <8 g/dl sebelum SC',
        'Diperiksa urinalisis sebelum SC',
        'Memiliki data USG sebelum SC',
        'Memiliki data laboratorium HIV sebelum SC',
        'Memiliki data laboratorium Hepatitis sebelum SC',
        'Asesmen persalinan menggunakan partograf ditulis lengkap sebelum SC',
    ];

    /** Klasifikasi Robson (ringkas) a-j */
    public array $klasifikasiOptions = [
        'a' => 'Nulipara tunggal presentasi kepala >=37mgg spontan',
        'b' => 'Nulipara tunggal presentasi kepala >=37mgg induksi',
        'c' => 'Multipara tanpa riwayat perlukaan uterus tunggal presentasi kepala >=37mgg spontan',
        'd' => 'Multipara tanpa riwayat perlukaan uterus tunggal presentasi kepala >=37mgg induksi/SC',
        'e' => 'Multipara riwayat perlukaan uterus tunggal presentasi kepala >=37mgg',
        'f' => 'Nulipara tunggal sungsang',
        'g' => 'Multipara tunggal sungsang riwayat perlukaan uterus',
        'h' => 'Kehamilan multipel riwayat perlukaan uterus',
        'i' => 'Tunggal oblik/melintang riwayat perlukaan uterus',
        'j' => 'Tunggal presentasi kepala <36mgg riwayat perlukaan uterus',
    ];

    public array $indikasiScOptions = [
        'PEB', 'Ketuban pecah dini', 'Bekas Sectio', 'Kelainan Letak Janin',
        'Gagal Induksi', 'Kelainan Letak Plasenta', 'Persalinan Tidak Maju',
        'Disproporsi Kepala Panggul', 'Lain-lain',
    ];

    /** LOV Dokter aktif untuk pengisi */
    public function getDokterListProperty()
    {
        return DB::table('rsmst_doctors')
            ->where('active_status', '1')
            ->select('dr_id', 'dr_name')
            ->orderBy('dr_name')
            ->get();
    }

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
        $this->registerAreas(['modal-indikator-sc-ri']);
        $this->resetNewForm();

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

        $this->incrementVersion('modal-indikator-sc-ri');
        $this->dispatch('open-modal', name: 'indikator-sc-ri');
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: 'indikator-sc-ri');
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

    public function toggleIndikasiSc(string $opt): void
    {
        $list = $this->newForm['indikasiSc'] ?? [];
        if (($k = array_search($opt, $list, true)) !== false) {
            unset($list[$k]);
        } else {
            $list[] = $opt;
        }
        $this->newForm['indikasiSc'] = array_values($list);
    }

    private function resetNewForm(): void
    {
        foreach ($this->newForm as $k => $v) {
            $this->newForm[$k] = is_array($v) ? [] : '';
        }
        // indikator: 15 slot kosong
        $this->newForm['indikator'] = array_fill(0, count($this->indikatorPertanyaan), '');
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

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Tambah Indikator Proses SC — ' . ($entry['ttd'] ?? '-') . ' — ' . $entry['createdAt'], 'MR');
            });

            $this->incrementVersion('modal-indikator-sc-ri');
            $this->dispatch('toast', type: 'success', message: 'Indikator Proses SC berhasil disimpan.');
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

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Indikator Proses SC — ' . $createdAt, 'MR');
            });

            $this->incrementVersion('modal-indikator-sc-ri');
            $this->dispatch('toast', type: 'success', message: 'Entri dihapus.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    public function cetak(string $createdAt)
    {
        $entry = collect($this->entriList)->firstWhere('createdAt', $createdAt);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Data indikator SC tidak ditemukan.');
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
                'dataRi'               => $this->dataDaftarRi,
                'form'                 => $entry,
                'indikatorPertanyaan'  => $this->indikatorPertanyaan,
                'klasifikasiOptions'   => $this->klasifikasiOptions,
                'identitasRs'          => $identitasRs,
                'tglCetak'             => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
            ]);

            set_time_limit(300);
            $pdf = Pdf::loadView('pages.components.modul-dokumen.r-i.indikator-sc-ri.cetak-indikator-sc-ri-print', ['data' => $data])->setPaper('A4');

            return response()->streamDownload(fn() => print $pdf->output(), 'indikator-sc-' . ($pasien['regNo'] ?? $this->riHdrNo) . '.pdf');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cetak: ' . $e->getMessage());
        }
    }
};
?>

<div>
    {{-- ══ SUMMARY CARD (inline di tab) ══ --}}
    @php $scCount = count($entriList ?? []); @endphp
    <div class="p-5 border shadow-sm bg-canvas border-hairline rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex-1 space-y-2">
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">Indikator Proses SC</h3>
                    @if ($scCount > 0)
                        <x-badge variant="success">{{ $scCount }} entri</x-badge>
                    @else
                        <x-badge variant="warning">Belum ada</x-badge>
                    @endif
                </div>
                <p class="text-sm text-muted dark:text-gray-400">
                    Audit sectio caesaria — 15 indikator proses (Ya/Tidak), klasifikasi Robson,
                    dan indikasi SC. Diisi Dokter.
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
    <x-modal name="indikator-sc-ri" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
             wire:key="{{ $this->renderKey('modal-indikator-sc-ri', [$riHdrNo ?? 'new']) }}">

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
                            <h2 class="text-lg font-semibold text-ink dark:text-gray-100">Indikator Proses SC</h2>
                            <p class="mt-0.5 text-sm text-muted dark:text-gray-400">Audit sectio caesaria (VK). Diisi Dokter.</p>
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
                        wire:key="indikator-sc-display-pasien-{{ $riHdrNo }}" />

                    @if ($isFormLocked)
                        <div class="px-4 py-2 text-sm border rounded-lg text-amber-800 bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-300">
                            Mode tampilan saja (read-only) — pasien sudah pulang / form terkunci.
                        </div>
                    @endif

                    {{-- ── FORM ENTRI ── --}}
                    <fieldset @disabled($isFormLocked) class="space-y-4">

                        {{-- 1. Indikator Proses SC (15 item) --}}
                        <x-border-form title="1. Indikator Proses SC">
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="text-left border-b border-hairline dark:border-gray-700">
                                            <th class="w-8 px-2 py-2 text-muted">No</th>
                                            <th class="px-2 py-2 text-muted">Pertanyaan</th>
                                            <th class="w-16 px-2 py-2 text-center text-muted">Ya</th>
                                            <th class="w-16 px-2 py-2 text-center text-muted">Tidak</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($indikatorPertanyaan as $i => $pertanyaan)
                                            <tr class="border-b border-hairline/60 dark:border-gray-800">
                                                <td class="px-2 py-2 align-top text-muted">{{ $i + 1 }}</td>
                                                <td class="px-2 py-2 align-top text-ink dark:text-gray-200">{{ $pertanyaan }}</td>
                                                <td class="px-2 py-2 text-center align-top">
                                                    <input type="radio" value="Ya" wire:model="newForm.indikator.{{ $i }}"
                                                        class="text-brand-green focus:ring-brand-green">
                                                </td>
                                                <td class="px-2 py-2 text-center align-top">
                                                    <input type="radio" value="Tidak" wire:model="newForm.indikator.{{ $i }}"
                                                        class="text-brand-green focus:ring-brand-green">
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </x-border-form>

                        {{-- 2. Klasifikasi Diagnosis (Robson) --}}
                        <x-border-form title="2. Klasifikasi Diagnosis (Robson)">
                            <div class="space-y-2">
                                @foreach ($klasifikasiOptions as $kode => $label)
                                    <x-radio-button name="newForm.diagnosisKlasifikasi" value="{{ $kode }}"
                                        wire="newForm.diagnosisKlasifikasi"
                                        :checked="($newForm['diagnosisKlasifikasi'] ?? '') === (string) $kode"
                                        :disabled="$isFormLocked"
                                        label="{{ $kode }}. {{ $label }}" />
                                @endforeach
                            </div>
                        </x-border-form>

                        {{-- 3. Indikasi SC --}}
                        <x-border-form title="3. Indikasi SC">
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($indikasiScOptions as $opt)
                                    <x-toggle :current="in_array($opt, $newForm['indikasiSc'] ?? [], true) ? 1 : 0"
                                        trueValue="1" falseValue="0"
                                        wireClick="toggleIndikasiSc('{{ $opt }}')"
                                        :disabled="$isFormLocked">{{ $opt }}</x-toggle>
                                @endforeach
                            </div>
                            <x-text-input wire:model="newForm.indikasiScLain" class="w-full mt-2" placeholder="Indikasi lain (bila Lain-lain)" />
                        </x-border-form>

                        <x-signature.ttd-petugas :ttd="$newForm['ttd']" :date="$newForm['ttdDate'] ?? ''" :locked="$isFormLocked" />

                        <div class="flex justify-end">
                            <x-primary-button type="button" wire:click="addEntry" wire:loading.attr="disabled" wire:target="addEntry">
                                <span wire:loading.remove wire:target="addEntry">Simpan Indikator SC</span>
                                <span wire:loading wire:target="addEntry">Menyimpan…</span>
                            </x-primary-button>
                        </div>
                    </fieldset>

                    {{-- ── DAFTAR ENTRI TERSIMPAN ── --}}
                    <x-border-form title="Riwayat Indikator SC Tersimpan">
                        @forelse ($entriList as $e)
                            <div wire:key="entri-{{ $e['createdAt'] }}" class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 border rounded-lg border-hairline dark:border-gray-700">
                                <div class="text-sm">
                                    <span class="font-semibold text-ink dark:text-gray-100">{{ $e['createdAt'] }}</span>
                                    @php $ya = collect($e['indikator'] ?? [])->filter(fn($x) => $x === 'Ya')->count(); @endphp
                                    <span class="ml-2 text-muted">· {{ $ya }}/{{ count($indikatorPertanyaan) }} Ya</span>
                                    <div class="text-xs text-muted dark:text-gray-400">Indikasi: {{ \Illuminate\Support\Str::limit(collect($e['indikasiSc'] ?? [])->filter()->implode(', '), 80) ?: '-' }}</div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <x-secondary-button type="button" wire:click="cetak('{{ $e['createdAt'] }}')" class="px-3 py-1.5 text-sm">Cetak</x-secondary-button>
                                    @unless ($isFormLocked)
                                        <x-danger-button type="button" wire:click="hapus('{{ $e['createdAt'] }}')"
                                            wire:confirm="Hapus entri indikator SC ini?" class="px-3 py-1.5 text-sm">Hapus</x-danger-button>
                                    @endunless
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-muted dark:text-gray-400">Belum ada indikator SC tersimpan.</p>
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
