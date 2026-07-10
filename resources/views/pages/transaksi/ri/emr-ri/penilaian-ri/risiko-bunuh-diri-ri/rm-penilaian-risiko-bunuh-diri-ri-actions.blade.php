<?php
// resources/views/pages/transaksi/ri/emr-ri/penilaian-ri/risiko-bunuh-diri-ri/rm-penilaian-risiko-bunuh-diri-ri-actions.blade.php
// Skrining C-SSRS (Columbia Suicide Severity Rating Scale) — sub-tab Penilaian RI.

use Livewire\Component;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\WithValidationToast\WithValidationToastTrait;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Livewire\Attributes\On;

new class extends Component {
    use EmrRITrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public bool $isFormLocked = false;
    public ?string $riHdrNo = null;
    public array $dataDaftarRi = [];

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-penilaian-risiko-bunuh-diri-ri'];

    public array $formEntryResikoBunuhDiri = [
        'tglPenilaian' => '',
        'petugasPenilai' => '',
        'petugasPenilaiCode' => '',
        'resikoBunuhDiri' => 'Tidak',
        'ideBunuhDiri' => [
            'inginMati' => 'Tidak',
            'ideAktifTanpaCara' => 'Tidak',
            'ideAktifDenganCara' => 'Tidak',
            'ideAktifDenganNiat' => 'Tidak',
            'ideAktifNiatRencana' => 'Tidak',
        ],
        'perilakuBunuhDiri' => [
            'pernahMencoba' => 'Tidak',
            'hampirMencoba' => 'Tidak',
            'memulaiLaluBerhenti' => 'Tidak',
            'persiapanSerius' => 'Tidak',
            'kapanTerakhir' => '',
        ],
        'skorKeparahan' => 0,
        'kategoriResiko' => 'Tidak Ada',
        'tindakLanjut' => [],
        'catatanKlinis' => '',
    ];

    // Pertanyaan A (ide bunuh diri, 1 bulan terakhir) — URUTAN = bobot skor keparahan 1-5.
    public array $ideBunuhDiriPertanyaan = [
        'inginMati' => 'Berharap tidak bangun lagi atau sudah meninggal (wish to be dead)',
        'ideAktifTanpaCara' => 'Berpikir untuk mengakhiri hidup, meskipun tanpa memikirkan caranya',
        'ideAktifDenganCara' => 'Memikirkan cara tertentu untuk bunuh diri, namun tanpa niat melakukannya',
        'ideAktifDenganNiat' => 'Berniat bunuh diri, meskipun belum ada rencana yang jelas',
        'ideAktifNiatRencana' => 'Memiliki rencana yang jelas untuk bunuh diri dan berniat melakukannya',
    ];

    // Pertanyaan B (perilaku bunuh diri — pernah, sepanjang hidup).
    public array $perilakuBunuhDiriPertanyaan = [
        'pernahMencoba' => 'Pernah mencoba bunuh diri',
        'hampirMencoba' => 'Hampir mencoba, dihentikan orang lain',
        'memulaiLaluBerhenti' => 'Memulai tetapi menghentikan diri sendiri',
        'persiapanSerius' => 'Melakukan persiapan serius (mengumpulkan obat, menulis pesan perpisahan)',
    ];

    public array $tindakLanjutBunuhDiriOptions = ['Edukasi & monitoring', 'Safety plan', 'Observasi ketat', 'Rujukan segera / rawat inap'];

    public function mount(): void
    {
        $this->registerAreas(['modal-penilaian-risiko-bunuh-diri-ri']);
    }

    #[On('open-rm-penilaian-risiko-bunuh-diri-ri')]
    public function open(string $riHdrNo): void
    {
        if (empty($riHdrNo)) {
            return;
        }

        $this->riHdrNo = $riHdrNo;
        $this->resetForm();
        $this->resetValidation();

        $data = $this->findDataRI($riHdrNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }

        $this->dataDaftarRi = $data;
        $this->dataDaftarRi['penilaian']['resikoBunuhDiri'] ??= [];

        $this->isFormLocked = $this->checkEmrRIStatus($riHdrNo);

        $this->incrementVersion('modal-penilaian-risiko-bunuh-diri-ri');
    }

    public function setTglPenilaianResikoBunuhDiri(): void
    {
        $this->formEntryResikoBunuhDiri['tglPenilaian'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    public function updated(string $property): void
    {
        if ($property === 'formEntryResikoBunuhDiri.resikoBunuhDiri' || str_starts_with($property, 'formEntryResikoBunuhDiri.ideBunuhDiri') || str_starts_with($property, 'formEntryResikoBunuhDiri.perilakuBunuhDiri')) {
            $this->hitungSkorResikoBunuhDiri();
        }
    }

    /**
     * Skor keparahan = nomor pertanyaan ide TERTINGGI yang dijawab "Ya" (1-5, 0 = tidak ada).
     * Stratifikasi C-SSRS: Tinggi = skor 5 ATAU riwayat percobaan;
     * Sedang = skor 3-4 atau ada perilaku lain (persiapan dsb.);
     * Rendah = skor 1-2 tanpa perilaku; selain itu "Tidak Ada".
     */
    public function hitungSkorResikoBunuhDiri(): void
    {
        // Gate "Tidak" → tidak ada risiko yang dinilai
        if (($this->formEntryResikoBunuhDiri['resikoBunuhDiri'] ?? 'Tidak') !== 'Ya') {
            $this->formEntryResikoBunuhDiri['skorKeparahan'] = 0;
            $this->formEntryResikoBunuhDiri['kategoriResiko'] = 'Tidak Ada';
            return;
        }

        $ide = $this->formEntryResikoBunuhDiri['ideBunuhDiri'] ?? [];
        $skor = 0;
        $nomor = 0;
        foreach ($this->ideBunuhDiriPertanyaan as $key => $pertanyaan) {
            $nomor++;
            if (($ide[$key] ?? '') === 'Ya') {
                $skor = $nomor;
            }
        }

        $perilaku = $this->formEntryResikoBunuhDiri['perilakuBunuhDiri'] ?? [];
        $pernahMencoba = ($perilaku['pernahMencoba'] ?? '') === 'Ya';
        $adaPerilakuLain = collect(['hampirMencoba', 'memulaiLaluBerhenti', 'persiapanSerius'])
            ->contains(fn($key) => ($perilaku[$key] ?? '') === 'Ya');

        $this->formEntryResikoBunuhDiri['skorKeparahan'] = $skor;
        $this->formEntryResikoBunuhDiri['kategoriResiko'] = match (true) {
            $skor === 5 || $pernahMencoba => 'Tinggi',
            $skor >= 3 || $adaPerilakuLain => 'Sedang',
            $skor >= 1 => 'Rendah',
            default => 'Tidak Ada',
        };
    }

    public function toggleTindakLanjutBunuhDiri(string $opsi): void
    {
        if ($this->isFormLocked) {
            return;
        }

        $terpilih = $this->formEntryResikoBunuhDiri['tindakLanjut'] ?? [];
        if (in_array($opsi, $terpilih, true)) {
            $terpilih = array_values(array_diff($terpilih, [$opsi]));
        } else {
            $terpilih[] = $opsi;
        }
        $this->formEntryResikoBunuhDiri['tindakLanjut'] = $terpilih;
    }

    #[On('save-rm-penilaian-risiko-bunuh-diri-ri')]
    public function addAssessmentResikoBunuhDiri(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang.');
            return;
        }

        // Gate "Tidak" → entri bersih; jawaban detail yang sempat terisi tidak ikut tersimpan
        if (($this->formEntryResikoBunuhDiri['resikoBunuhDiri'] ?? 'Tidak') !== 'Ya') {
            $this->formEntryResikoBunuhDiri['ideBunuhDiri'] = ['inginMati' => 'Tidak', 'ideAktifTanpaCara' => 'Tidak', 'ideAktifDenganCara' => 'Tidak', 'ideAktifDenganNiat' => 'Tidak', 'ideAktifNiatRencana' => 'Tidak'];
            $this->formEntryResikoBunuhDiri['perilakuBunuhDiri'] = ['pernahMencoba' => 'Tidak', 'hampirMencoba' => 'Tidak', 'memulaiLaluBerhenti' => 'Tidak', 'persiapanSerius' => 'Tidak', 'kapanTerakhir' => ''];
            $this->formEntryResikoBunuhDiri['tindakLanjut'] = [];
        }

        $this->hitungSkorResikoBunuhDiri();
        $this->formEntryResikoBunuhDiri['petugasPenilai'] = auth()->user()->myuser_name;
        $this->formEntryResikoBunuhDiri['petugasPenilaiCode'] = auth()->user()->myuser_code;

        if (empty($this->formEntryResikoBunuhDiri['tglPenilaian'])) {
            $this->setTglPenilaianResikoBunuhDiri();
        }

        $this->validateWithToast(
            [
                'formEntryResikoBunuhDiri.resikoBunuhDiri' => 'required|in:Ya,Tidak',
                'formEntryResikoBunuhDiri.tglPenilaian' => 'required|date_format:d/m/Y H:i:s',
                'formEntryResikoBunuhDiri.ideBunuhDiri.*' => 'required|in:Ya,Tidak',
                'formEntryResikoBunuhDiri.perilakuBunuhDiri.pernahMencoba' => 'required|in:Ya,Tidak',
                'formEntryResikoBunuhDiri.perilakuBunuhDiri.hampirMencoba' => 'required|in:Ya,Tidak',
                'formEntryResikoBunuhDiri.perilakuBunuhDiri.memulaiLaluBerhenti' => 'required|in:Ya,Tidak',
                'formEntryResikoBunuhDiri.perilakuBunuhDiri.persiapanSerius' => 'required|in:Ya,Tidak',
                'formEntryResikoBunuhDiri.perilakuBunuhDiri.kapanTerakhir' => 'nullable|string|max:100',
                'formEntryResikoBunuhDiri.tindakLanjut' => 'array',
                'formEntryResikoBunuhDiri.catatanKlinis' => 'nullable|string|max:1000',
            ],
            [
                'required' => ':attribute wajib diisi.',
                'in' => ':attribute harus salah satu dari: :values.',
                'date_format' => ':attribute harus format dd/mm/yyyy hh:mm:ss.',
            ],
            [
                'formEntryResikoBunuhDiri.tglPenilaian' => 'Tanggal Penilaian',
                'formEntryResikoBunuhDiri.ideBunuhDiri.*' => 'Jawaban ide bunuh diri',
                'formEntryResikoBunuhDiri.perilakuBunuhDiri.kapanTerakhir' => 'Kapan terakhir',
                'formEntryResikoBunuhDiri.catatanKlinis' => 'Catatan klinis',
            ],
        );

        try {
            DB::transaction(function () {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                $fresh['penilaian']['resikoBunuhDiri'][] = $this->formEntryResikoBunuhDiri;
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->appendAdminLogRI((int) $this->riHdrNo, 'Tambah Skrining Risiko Bunuh Diri (C-SSRS) — kategori ' . ($this->formEntryResikoBunuhDiri['kategoriResiko'] ?? '-') . ', entri ' . ($this->formEntryResikoBunuhDiri['tglPenilaian'] ?? '-'), 'MR');
                $this->dataDaftarRi = $fresh;
            });
            $this->reset(['formEntryResikoBunuhDiri']);
            $this->afterSave('Skrining Risiko Bunuh Diri berhasil disimpan.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    public function removeAssessmentResikoBunuhDiri(int $index): void
    {
        if ($this->isFormLocked) {
            return;
        }

        try {
            DB::transaction(function () use ($index) {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?? [];
                $tglHapus = $fresh['penilaian']['resikoBunuhDiri'][$index]['tglPenilaian'] ?? '-';
                array_splice($fresh['penilaian']['resikoBunuhDiri'], $index, 1);
                $fresh['penilaian']['resikoBunuhDiri'] = array_values($fresh['penilaian']['resikoBunuhDiri']);
                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Skrining Risiko Bunuh Diri (C-SSRS) — entri ' . $tglHapus, 'MR');
                $this->dataDaftarRi = $fresh;
            });
            $this->afterSave('Skrining Risiko Bunuh Diri dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    private function afterSave(string $msg): void
    {
        $this->incrementVersion('modal-penilaian-risiko-bunuh-diri-ri');
        $this->dispatch('penilaian-ri-saved', riHdrNo: $this->riHdrNo);
        $this->dispatch('refresh-after-ri.saved', tab: 'penilaian', subTab: 'resikoBunuhDiri');
        $this->dispatch('toast', type: 'success', message: $msg);
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->isFormLocked = false;
        $this->reset(['formEntryResikoBunuhDiri']);
    }
};
?>

<div wire:key="{{ $this->renderKey('modal-penilaian-risiko-bunuh-diri-ri', [$riHdrNo ?? 'new']) }}" class="space-y-4">

    @if (!$isFormLocked)
        <div class="space-y-4">

            {{-- panduan singkat (default tertutup) --}}
            <details class="p-3 text-sm border rounded-2xl bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-800/40">
                <summary class="font-semibold text-blue-800 cursor-pointer dark:text-blue-300">Cara pengisian &amp; catatan penting</summary>
                <ul class="mt-2 space-y-1 text-blue-900/80 dark:text-blue-200/80" style="list-style: disc; padding-left: 18px">
                    <li>Ajukan pertanyaan <strong>berurutan</strong>; periode <strong>1 bulan terakhir</strong> (bagian perilaku: sepanjang hidup).</li>
                    <li>Ini alat skrining — <strong>bukan pengganti wawancara klinis</strong>.</li>
                    <li>Bertanya tentang bunuh diri <strong>tidak meningkatkan risiko</strong> — gunakan bahasa empatik &amp; tidak menghakimi.</li>
                </ul>
            </details>

            {{-- GATE: ada risiko bunuh diri? --}}
            <div class="sm:max-w-xs">
                <x-input-label value="Risiko Bunuh Diri *" />
                <x-select-input wire:model.live="formEntryResikoBunuhDiri.resikoBunuhDiri" class="w-full mt-1">
                    <option value="Tidak">Tidak</option>
                    <option value="Ya">Ya</option>
                </x-select-input>
                <x-input-error :messages="$errors->get('formEntryResikoBunuhDiri.resikoBunuhDiri')" class="mt-1" />
            </div>

            @if (($formEntryResikoBunuhDiri['resikoBunuhDiri'] ?? 'Tidak') === 'Ya')
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label value="Tanggal Penilaian *" />
                    <div class="flex gap-2 mt-1">
                        <x-text-input wire:model="formEntryResikoBunuhDiri.tglPenilaian" placeholder="dd/mm/yyyy hh:ii:ss"
                            :error="$errors->has('formEntryResikoBunuhDiri.tglPenilaian')" class="w-full" />
                        <x-now-button wire:click="setTglPenilaianResikoBunuhDiri" />
                    </div>
                    <x-input-error :messages="$errors->get('formEntryResikoBunuhDiri.tglPenilaian')" class="mt-1" />
                </div>

                {{-- hasil live --}}
                <div class="flex flex-wrap items-end gap-2 pb-1">
                    <span class="px-2 py-0.5 text-sm font-bold text-white rounded-full bg-brand">
                        Skor: {{ $formEntryResikoBunuhDiri['skorKeparahan'] }}
                    </span>
                    @php $kategoriLive = $formEntryResikoBunuhDiri['kategoriResiko'] ?? 'Tidak Ada'; @endphp
                    <span
                        class="px-2 py-0.5 text-sm font-bold rounded-full
                        {{ $kategoriLive === 'Tinggi'
                            ? 'bg-red-100 text-red-700'
                            : ($kategoriLive === 'Sedang'
                                ? 'bg-yellow-100 text-yellow-700'
                                : ($kategoriLive === 'Rendah'
                                    ? 'bg-orange-100 text-orange-700'
                                    : 'bg-green-100 text-green-700')) }}">
                        {{ $kategoriLive }}
                    </span>
                    <span class="text-sm text-muted-soft">Rendah 1–2 | Sedang 3–4 ± persiapan | Tinggi 5 / riwayat percobaan</span>
                </div>
            </div>

            {{-- A. IDE BUNUH DIRI --}}
            <x-border-form title="A · Ide Bunuh Diri (1 bulan terakhir)" align="start" bgcolor="bg-canvas">
                <div class="grid grid-cols-1 gap-3 mt-3">
                    @foreach ($ideBunuhDiriPertanyaan as $key => $pertanyaan)
                        <div class="grid items-center grid-cols-1 gap-2 sm:grid-cols-[1fr_230px]">
                            <x-input-label :value="$loop->iteration . '. ' . $pertanyaan" />
                            <div class="grid grid-cols-2 gap-2">
                                <x-radio-button label="Ya" value="Ya" name="ideBunuhDiriRi{{ $key }}"
                                    wire:model.live="formEntryResikoBunuhDiri.ideBunuhDiri.{{ $key }}" />
                                <x-radio-button label="Tidak" value="Tidak" name="ideBunuhDiriRi{{ $key }}"
                                    wire:model.live="formEntryResikoBunuhDiri.ideBunuhDiri.{{ $key }}" />
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-border-form>

            {{-- B. PERILAKU BUNUH DIRI --}}
            <x-border-form title="B · Perilaku Bunuh Diri (sepanjang hidup)" align="start" bgcolor="bg-canvas">
                <div class="grid grid-cols-1 gap-3 mt-3">
                    @foreach ($perilakuBunuhDiriPertanyaan as $key => $pertanyaan)
                        <div class="grid items-center grid-cols-1 gap-2 sm:grid-cols-[1fr_230px]">
                            <x-input-label :value="$loop->iteration . '. ' . $pertanyaan" />
                            <div class="grid grid-cols-2 gap-2">
                                <x-radio-button label="Ya" value="Ya" name="perilakuBunuhDiriRi{{ $key }}"
                                    wire:model.live="formEntryResikoBunuhDiri.perilakuBunuhDiri.{{ $key }}" />
                                <x-radio-button label="Tidak" value="Tidak" name="perilakuBunuhDiriRi{{ $key }}"
                                    wire:model.live="formEntryResikoBunuhDiri.perilakuBunuhDiri.{{ $key }}" />
                            </div>
                        </div>
                    @endforeach

                    @if (collect(['pernahMencoba', 'hampirMencoba', 'memulaiLaluBerhenti', 'persiapanSerius'])->contains(fn($key) => ($formEntryResikoBunuhDiri['perilakuBunuhDiri'][$key] ?? '') === 'Ya'))
                        <div>
                            <x-input-label value="Jika Ya — kapan terakhir?" />
                            <x-text-input wire:model="formEntryResikoBunuhDiri.perilakuBunuhDiri.kapanTerakhir"
                                placeholder="mis. 2 bulan yang lalu" class="w-full mt-1" />
                            <x-input-error :messages="$errors->get('formEntryResikoBunuhDiri.perilakuBunuhDiri.kapanTerakhir')" class="mt-1" />
                        </div>
                    @endif
                </div>
            </x-border-form>

            {{-- C. TINDAK LANJUT --}}
            <x-border-form title="C · Tindak Lanjut" align="start" bgcolor="bg-canvas">
                <div class="flex flex-wrap gap-x-6 gap-y-2 mt-3">
                    @foreach ($tindakLanjutBunuhDiriOptions as $opsi)
                        <x-toggle :current="in_array($opsi, $formEntryResikoBunuhDiri['tindakLanjut'] ?? [], true) ? '1' : '0'"
                            trueValue="1" falseValue="0"
                            wireClick="toggleTindakLanjutBunuhDiri('{{ $opsi }}')">
                            {{ $opsi }}
                        </x-toggle>
                    @endforeach
                </div>
                <div class="mt-3">
                    <x-input-label value="Catatan klinis singkat" />
                    <x-textarea wire:model="formEntryResikoBunuhDiri.catatanKlinis" class="w-full mt-1" rows="2" />
                    <x-input-error :messages="$errors->get('formEntryResikoBunuhDiri.catatanKlinis')" class="mt-1" />
                </div>
            </x-border-form>
            @endif

            <div class="flex justify-end pt-2">
                <x-primary-button wire:click="addAssessmentResikoBunuhDiri" wire:loading.attr="disabled"
                    wire:target="addAssessmentResikoBunuhDiri">
                    <span wire:loading.remove wire:target="addAssessmentResikoBunuhDiri">Simpan Skrining Risiko Bunuh
                        Diri</span>
                    <span wire:loading wire:target="addAssessmentResikoBunuhDiri">Menyimpan...</span>
                </x-primary-button>
            </div>
        </div>
    @endif

    @if (collect($dataDaftarRi['penilaian']['resikoBunuhDiri'] ?? [])->filter(fn($entri) => filled(data_get($entri, 'tglPenilaian')))->isNotEmpty())
        <x-border-form title="Riwayat Skrining Risiko Bunuh Diri" align="start" bgcolor="bg-canvas">
            <div class="mt-3 overflow-x-auto rounded-lg border border-hairline dark:border-gray-700">
                <table class="w-full text-sm text-left text-body dark:text-gray-300">
                    <thead class="bg-surface-soft dark:bg-gray-700 text-muted dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-2">Tgl Penilaian</th>
                            <th class="px-3 py-2">Petugas</th>
                            <th class="px-3 py-2">Risiko</th>
                            <th class="px-3 py-2">Skor</th>
                            <th class="px-3 py-2">Kategori</th>
                            <th class="px-3 py-2">Tindak Lanjut</th>
                            <th class="px-3 py-2">Catatan</th>
                            @if (!$isFormLocked)
                                <th class="px-3 py-2"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-hairline-soft dark:divide-gray-700">
                        @foreach (array_reverse(array_filter($dataDaftarRi['penilaian']['resikoBunuhDiri'] ?? [], fn($entri) => filled(data_get($entri, 'tglPenilaian'))), true) as $i => $row)
                            @php
                                $kat = $row['kategoriResiko'] ?? '-';
                                $rowBg =
                                    $kat === 'Tinggi'
                                        ? 'bg-red-50 hover:bg-red-100'
                                        : ($kat === 'Sedang'
                                            ? 'bg-yellow-50 hover:bg-yellow-100'
                                            : ($kat === 'Rendah'
                                                ? 'bg-orange-50 hover:bg-orange-100'
                                                : 'hover:bg-surface-soft'));
                            @endphp
                            <tr class="{{ $rowBg }}">
                                <td class="px-3 py-2 whitespace-nowrap">{{ $row['tglPenilaian'] ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $row['petugasPenilai'] ?? '-' }}</td>
                                <td class="px-3 py-2">
                                    <span
                                        class="px-2 py-0.5 rounded-full text-sm font-medium
                                        {{ ($row['resikoBunuhDiri'] ?? '') === 'Ya' ? 'bg-orange-100 text-orange-700' : 'bg-green-100 text-green-700' }}">
                                        {{ $row['resikoBunuhDiri'] ?? '-' }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 font-bold">{{ $row['skorKeparahan'] ?? '-' }}</td>
                                <td class="px-3 py-2">
                                    <span
                                        class="px-2 py-0.5 rounded-full text-sm font-medium
                                        {{ $kat === 'Tinggi'
                                            ? 'bg-red-100 text-red-700'
                                            : ($kat === 'Sedang'
                                                ? 'bg-yellow-100 text-yellow-700'
                                                : ($kat === 'Rendah'
                                                    ? 'bg-orange-100 text-orange-700'
                                                    : 'bg-green-100 text-green-700')) }}">
                                        {{ $kat }}
                                    </span>
                                </td>
                                <td class="px-3 py-2">{{ implode(', ', $row['tindakLanjut'] ?? []) ?: '-' }}</td>
                                <td class="px-3 py-2 text-body dark:text-gray-300">{{ $row['catatanKlinis'] ?: '-' }}</td>
                                @if (!$isFormLocked)
                                    <td class="px-3 py-2">
                                        <x-outline-button type="button"
                                            wire:click="removeAssessmentResikoBunuhDiri({{ $i }})"
                                            wire:confirm="Hapus data skrining risiko bunuh diri ini?"
                                            wire:loading.attr="disabled"
                                            class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300"
                                            title="Hapus">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </x-outline-button>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-border-form>
    @else
        <p class="text-xs text-center text-muted-soft py-6">Belum ada data skrining risiko bunuh diri.</p>
    @endif
</div>
