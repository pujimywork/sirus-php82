<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/pengkajian-awal-bayi-ri/rm-pengkajian-awal-bayi-ri-actions.blade.php
// Dokumen VK/Kebidanan — Pengkajian Awal Bayi (RM 14 e.3), diisi Dokter.
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
    protected array $renderAreas = ['modal-pengkajian-awal-bayi-ri'];

    /** Key penyimpanan di datadaftarri_json */
    private string $jsonKey = 'pengkajianAwalBayiRI';

    public array $newForm = [
        // 1. Identitas Bayi
        'tglLahir'          => '',
        'jamLahir'          => '',
        'caraPersalinan'    => '',
        'namaAyah'          => '',
        'namaIbu'           => '',
        'ruanganIbu'        => '',
        'noRmIbu'           => '',
        // 2. APGAR — 5 komponen x 3 menit (0-2)
        'warnaKulit1'       => '', 'warnaKulit5'   => '', 'warnaKulit10'   => '',
        'reflek1'           => '', 'reflek5'       => '', 'reflek10'       => '',
        'denyutJantung1'    => '', 'denyutJantung5'=> '', 'denyutJantung10'=> '',
        'tonus1'            => '', 'tonus5'        => '', 'tonus10'        => '',
        'usahaNafas1'       => '', 'usahaNafas5'   => '', 'usahaNafas10'   => '',
        // 3. Pemeriksaan Fisik
        'keadaanTaliPusat'  => '',
        'jantung'           => '',
        'paru'              => '',
        'abdomenHati'       => '',
        'limpa'             => '',
        'anus'              => '',
        'ekstremitas'       => '',
        'imunisasi'         => '',
        // 4. Antropometri
        'lingkarKepala'     => '',
        'beratBadan'        => '',
        'tinggiBadan'       => '',
        'lingkarDada'       => '',
        'jenisKelamin'      => '',
        // 5. Keadaan Bayi Waktu Lahir
        'sianosis'          => '', 'sianosisKet'    => '',
        'asphyxia'          => '', 'asphyxiaKet'    => '',
        'traumaLahir'       => '', 'traumaLahirKet' => '',
        // 6. Diagnosa
        'diagnosaUtama'     => '',
        // 7. Rencana
        'rencanaDiagnosa'   => '',
        'terapi'            => '',
        'diet'              => '',
        'edukasi'           => '',
        'monitoring'        => '',
        'dischargePlanning' => '',
        // 8. Tanda Tangan
        'ttd'                => '',   // nama penanda-tangan (myuser_name)
        'ttdDate'            => '',   // tgl/jam TTD (d/m/Y H:i:s)
        'ttdCode'            => '',   // myuser_code penanda-tangan
    ];

    public array $entriList = [];

    protected function rules(): array
    {
        return [
            'newForm.diagnosaUtama' => 'required|string',
        ];
    }

    protected function messages(): array
    {
        return [
            'newForm.diagnosaUtama.required' => 'Diagnosa Utama harus diisi.',
        ];
    }

    public function mount(?string $riHdrNo = null, bool $disabled = false): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-pengkajian-awal-bayi-ri']);

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

        $this->incrementVersion('modal-pengkajian-awal-bayi-ri');
        $this->dispatch('open-modal', name: 'pengkajian-awal-bayi-ri');
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: 'pengkajian-awal-bayi-ri');
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

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Tambah Pengkajian Awal Bayi — ' . ($entry['ttd'] ?? '-') . ' — ' . $entry['createdAt'], 'MR');
            });

            $this->incrementVersion('modal-pengkajian-awal-bayi-ri');
            $this->dispatch('toast', type: 'success', message: 'Pengkajian awal bayi berhasil disimpan.');
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

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Pengkajian Awal Bayi — ' . $createdAt, 'MR');
            });

            $this->incrementVersion('modal-pengkajian-awal-bayi-ri');
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
            $pdf = Pdf::loadView('pages.components.modul-dokumen.r-i.pengkajian-awal-bayi-ri.cetak-pengkajian-awal-bayi-ri-print', ['data' => $data])->setPaper('A4');

            return response()->streamDownload(fn() => print $pdf->output(), 'pengkajian-awal-bayi-' . ($pasien['regNo'] ?? $this->riHdrNo) . '.pdf');
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
                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">Pengkajian Awal Bayi</h3>
                    @if ($paCount > 0)
                        <x-badge variant="success">{{ $paCount }} entri</x-badge>
                    @else
                        <x-badge variant="warning">Belum ada</x-badge>
                    @endif
                </div>
                <p class="text-sm text-muted dark:text-gray-400">
                    Pengkajian awal bayi baru lahir (RM 14 e.3) — identitas bayi, nilai APGAR (1'/5'/10'),
                    pemeriksaan fisik, antropometri, keadaan waktu lahir, diagnosa & rencana. Diisi Dokter.
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
    <x-modal name="pengkajian-awal-bayi-ri" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
             wire:key="{{ $this->renderKey('modal-pengkajian-awal-bayi-ri', [$riHdrNo ?? 'new']) }}">

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
                            <h2 class="text-lg font-semibold text-ink dark:text-gray-100">Pengkajian Awal Bayi</h2>
                            <p class="mt-0.5 text-sm text-muted dark:text-gray-400">RM 14 e.3 — kebidanan (VK). Diisi Dokter.</p>
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
                        wire:key="pengkajian-bayi-display-pasien-{{ $riHdrNo }}" />

                    @if ($isFormLocked)
                        <div class="px-4 py-2 text-sm border rounded-lg text-amber-800 bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-300">
                            Mode tampilan saja (read-only) — pasien sudah pulang / form terkunci.
                        </div>
                    @endif

                    {{-- ── FORM ENTRI ── --}}
                    <fieldset @disabled($isFormLocked) class="space-y-4">

                        {{-- 1. Identitas Bayi --}}
                        <x-border-form title="1. Identitas Bayi">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <div class="sm:col-span-2">
                                    <x-input-label value="Tgl / Jam Lahir" />
                                    <div class="flex gap-1 mt-1">
                                        <x-text-input wire:model="newForm.tglLahir" class="w-full" placeholder="dd/mm/yyyy HH:mm:ss" />
                                        <x-now-button wire:click="setTglJamSekarang('tglLahir')" />
                                    </div>
                                </div>
                                <div><x-input-label value="Cara Persalinan" /><x-text-input wire:model="newForm.caraPersalinan" class="w-full mt-1" placeholder="Spontan/SC/…" /></div>
                                <div><x-input-label value="Nama Ayah" /><x-text-input wire:model="newForm.namaAyah" class="w-full mt-1" /></div>
                                <div><x-input-label value="Nama Ibu" /><x-text-input wire:model="newForm.namaIbu" class="w-full mt-1" /></div>
                                <div><x-input-label value="Ruangan Ibu" /><x-text-input wire:model="newForm.ruanganIbu" class="w-full mt-1" /></div>
                                <div><x-input-label value="No. RM Ibu" /><x-text-input wire:model="newForm.noRmIbu" class="w-full mt-1" /></div>
                            </div>
                        </x-border-form>

                        {{-- 2. Nilai APGAR --}}
                        <x-border-form title="2. Nilai APGAR (0–2 per komponen)">
                            @php
                                $apgarRows = [
                                    ['Warna Kulit', 'warnaKulit'],
                                    ['Reflek', 'reflek'],
                                    ['Denyut Jantung', 'denyutJantung'],
                                    ['Tonus', 'tonus'],
                                    ['Usaha Bernafas', 'usahaNafas'],
                                ];
                                $apgarMenit = ['1' => "1'", '5' => "5'", '10' => "10'"];
                                $sumMenit = fn($m) => collect($apgarRows)->sum(fn($r) => (int) ($newForm[$r[1] . $m] ?? 0));
                            @endphp
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm border-collapse">
                                    <thead>
                                        <tr class="text-left border-b border-hairline dark:border-gray-700">
                                            <th class="py-2 pr-3 font-medium text-muted">Komponen</th>
                                            @foreach ($apgarMenit as $mk => $ml)
                                                <th class="px-2 py-2 font-medium text-center text-muted">Menit {{ $ml }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($apgarRows as $row)
                                            <tr class="border-b border-hairline/60 dark:border-gray-800">
                                                <td class="py-1.5 pr-3 text-ink dark:text-gray-200">{{ $row[0] }}</td>
                                                @foreach ($apgarMenit as $mk => $ml)
                                                    <td class="px-2 py-1.5 text-center">
                                                        <x-text-input type="number" min="0" max="2" wire:model="newForm.{{ $row[1] . $mk }}" class="w-16 mx-auto text-center" />
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                        <tr class="font-semibold border-t-2 border-hairline dark:border-gray-600">
                                            <td class="py-2 pr-3 text-ink dark:text-gray-100">Jumlah</td>
                                            @foreach ($apgarMenit as $mk => $ml)
                                                <td class="px-2 py-2 text-center text-brand-green dark:text-brand-lime">{{ $sumMenit($mk) }}</td>
                                            @endforeach
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </x-border-form>

                        {{-- 3. Pemeriksaan Fisik --}}
                        <x-border-form title="3. Pemeriksaan Fisik">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <div><x-input-label value="Keadaan Tali Pusat" /><x-text-input wire:model="newForm.keadaanTaliPusat" class="w-full mt-1" /></div>
                                <div><x-input-label value="Jantung" /><x-text-input wire:model="newForm.jantung" class="w-full mt-1" /></div>
                                <div><x-input-label value="Paru" /><x-text-input wire:model="newForm.paru" class="w-full mt-1" /></div>
                                <div><x-input-label value="Abdomen / Hati" /><x-text-input wire:model="newForm.abdomenHati" class="w-full mt-1" /></div>
                                <div><x-input-label value="Limpa" /><x-text-input wire:model="newForm.limpa" class="w-full mt-1" /></div>
                                <div><x-input-label value="Anus" /><x-text-input wire:model="newForm.anus" class="w-full mt-1" /></div>
                                <div><x-input-label value="Ekstremitas" /><x-text-input wire:model="newForm.ekstremitas" class="w-full mt-1" /></div>
                                <div><x-input-label value="Imunisasi" /><x-text-input wire:model="newForm.imunisasi" class="w-full mt-1" /></div>
                            </div>
                        </x-border-form>

                        {{-- 4. Antropometri --}}
                        <x-border-form title="4. Antropometri">
                            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                                <div><x-input-label value="Lingkar Kepala (cm)" /><x-text-input type="number" wire:model="newForm.lingkarKepala" class="w-full mt-1" /></div>
                                <div><x-input-label value="Berat Badan (gr)" /><x-text-input type="number" wire:model="newForm.beratBadan" class="w-full mt-1" /></div>
                                <div><x-input-label value="Tinggi Badan (cm)" /><x-text-input type="number" wire:model="newForm.tinggiBadan" class="w-full mt-1" /></div>
                                <div><x-input-label value="Lingkar Dada (cm)" /><x-text-input type="number" wire:model="newForm.lingkarDada" class="w-full mt-1" /></div>
                                <div>
                                    <x-input-label value="Jenis Kelamin" />
                                    <x-select-input wire:model="newForm.jenisKelamin" class="w-full mt-1">
                                        <option value="">—</option>
                                        <option value="Laki-laki">Laki-laki</option>
                                        <option value="Perempuan">Perempuan</option>
                                    </x-select-input>
                                </div>
                            </div>
                        </x-border-form>

                        {{-- 5. Keadaan Bayi Waktu Lahir --}}
                        <x-border-form title="5. Keadaan Bayi Waktu Lahir">
                            <div class="space-y-3">
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                    <div>
                                        <x-input-label value="Sianosis" />
                                        <x-select-input wire:model="newForm.sianosis" class="w-full mt-1">
                                            <option value="">—</option>
                                            <option value="Ada">Ada</option>
                                            <option value="Tidak Ada">Tidak Ada</option>
                                        </x-select-input>
                                    </div>
                                    <div class="sm:col-span-2"><x-input-label value="Keterangan Sianosis" /><x-text-input wire:model="newForm.sianosisKet" class="w-full mt-1" /></div>
                                    <div>
                                        <x-input-label value="Asphyxia" />
                                        <x-select-input wire:model="newForm.asphyxia" class="w-full mt-1">
                                            <option value="">—</option>
                                            <option value="Ada">Ada</option>
                                            <option value="Tidak Ada">Tidak Ada</option>
                                        </x-select-input>
                                    </div>
                                    <div class="sm:col-span-2"><x-input-label value="Keterangan Asphyxia" /><x-text-input wire:model="newForm.asphyxiaKet" class="w-full mt-1" /></div>
                                    <div>
                                        <x-input-label value="Trauma Lahir" />
                                        <x-select-input wire:model="newForm.traumaLahir" class="w-full mt-1">
                                            <option value="">—</option>
                                            <option value="Ada">Ada</option>
                                            <option value="Tidak Ada">Tidak Ada</option>
                                        </x-select-input>
                                    </div>
                                    <div class="sm:col-span-2"><x-input-label value="Keterangan Trauma Lahir" /><x-text-input wire:model="newForm.traumaLahirKet" class="w-full mt-1" /></div>
                                </div>
                            </div>
                        </x-border-form>

                        {{-- 6. Diagnosa --}}
                        <x-border-form title="6. Diagnosa">
                            <div>
                                <x-input-label value="Diagnosa Utama" />
                                <x-textarea wire:model="newForm.diagnosaUtama" rows="2" class="w-full mt-1" :error="$errors->has('newForm.diagnosaUtama')" />
                                <x-input-error :messages="$errors->get('newForm.diagnosaUtama')" class="mt-1" />
                            </div>
                        </x-border-form>

                        {{-- 7. Rencana --}}
                        <x-border-form title="7. Rencana">
                            <div class="space-y-4">
                                <div><x-input-label value="Rencana Diagnosa" /><x-textarea wire:model="newForm.rencanaDiagnosa" rows="2" class="w-full mt-1" /></div>
                                <div><x-input-label value="Terapi" /><x-textarea wire:model="newForm.terapi" rows="2" class="w-full mt-1" /></div>
                                <div><x-input-label value="Diet" /><x-textarea wire:model="newForm.diet" rows="2" class="w-full mt-1" /></div>
                                <div><x-input-label value="Edukasi" /><x-textarea wire:model="newForm.edukasi" rows="2" class="w-full mt-1" /></div>
                                <div><x-input-label value="Monitoring" /><x-textarea wire:model="newForm.monitoring" rows="2" class="w-full mt-1" /></div>
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
                                    <span class="ml-2 text-muted">· {{ $e['jenisKelamin'] ?? '-' }}</span>
                                    <span class="ml-2 text-muted">· BB {{ $e['beratBadan'] ?? '-' }} gr</span>
                                    <div class="text-xs text-muted dark:text-gray-400">{{ \Illuminate\Support\Str::limit($e['diagnosaUtama'] ?? '', 80) }}</div>
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
