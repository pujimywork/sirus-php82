<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/pasca-anestesi-ri/rm-pasca-anestesi-ri-actions.blade.php

use Livewire\Component;
use Livewire\Attributes\Computed;
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

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-pasca-anestesi-ri'];

    // ── Form entri baru (Monitoring Pasca Anestesi — PAB 6.1 / RM 55) ──
    public array $newForm = [
        'jamMasuk' => '',
        'jamKeluar' => '',
        'keadaanUmum' => '',
        'td' => '',
        'nadi' => '',
        'rr' => '',
        'suhu' => '',
        'spo2' => '',
        'jenisAnestesi' => 'Umum',
        'aldrete' => [
            'kesadaran' => '',
            'pernafasan' => '',
            'sirkulasi' => '',
            'aktivitas' => '',
            'warnaKulit' => '',
        ],
        'bromage' => '',
        'skalaNyeri' => '',
        'rekomendasi' => '',
        'keteranganRekomendasi' => '',
        // TTD Perawat RR
        'ttd' => '',
        'ttdCode' => '',
        'ttdDate' => '',
    ];

    public array $pascaList = [];

    public array $jenisAnestesiOptions = ['Umum', 'Regional / Spinal'];
    public array $rekomendasiOptions = ['Kembali ke ruangan rawat inap', 'Pindah ke ICU/HCU', 'Pulang (ODC)', 'Lain-lain'];

    // Aldrete: skor 0–2 per item
    public array $aldreteItems = [
        'kesadaran' => ['label' => 'Kesadaran', 'opsi' => ['2' => 'Sadar penuh', '1' => 'Bangun bila dipanggil', '0' => 'Tidak ada respon']],
        'pernafasan' => ['label' => 'Pernafasan', 'opsi' => ['2' => 'Nafas dalam & batuk bebas', '1' => 'Dangkal / sesak', '0' => 'Apnea / nafas dibantu']],
        'sirkulasi' => ['label' => 'Sirkulasi (TD)', 'opsi' => ['2' => '±20% nilai pra-op', '1' => '±20–50% nilai pra-op', '0' => '>50% nilai pra-op']],
        'aktivitas' => ['label' => 'Aktivitas / Pergerakan', 'opsi' => ['2' => 'Gerak 4 ekstremitas', '1' => 'Gerak 2 ekstremitas', '0' => 'Tidak dapat bergerak']],
        'warnaKulit' => ['label' => 'Warna Kulit / SpO2', 'opsi' => ['2' => 'Merah muda / SpO2 >92% udara kamar', '1' => 'Pucat / perlu O2', '0' => 'Sianosis']],
    ];

    // Bromage Score (anestesi regional/spinal) — sesuai form RS
    public array $bromageOptions = [
        '3' => 'Gerakan penuh tungkai',
        '2' => 'Mampu memfleksikan lutut',
        '1' => 'Tidak mampu memfleksikan pergelangan kaki',
        '0' => 'Tidak mampu menggerakkan tungkai',
    ];

    #[Computed]
    public function totalAldrete(): int
    {
        return collect($this->newForm['aldrete'] ?? [])
            ->filter(fn($nilai) => $nilai !== '' && $nilai !== null)
            ->sum(fn($nilai) => (int) $nilai);
    }

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(?string $riHdrNo = null, bool $disabled = false): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-pasca-anestesi-ri']);

        if ($this->riHdrNo) {
            $data = $this->findDataRI($this->riHdrNo);
            if ($data) {
                $this->dataDaftarRi = $data;
                $this->regNo = $data['regNo'] ?? null;
                $this->pascaList = $data['pascaAnestesiRI'] ?? [];
                $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $disabled;
            }
        }
    }

    /* ===============================
     | OPEN MODAL
     =============================== */
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
        if (!isset($this->dataDaftarRi['pascaAnestesiRI']) || !is_array($this->dataDaftarRi['pascaAnestesiRI'])) {
            $this->dataDaftarRi['pascaAnestesiRI'] = [];
        }
        $this->pascaList = $this->dataDaftarRi['pascaAnestesiRI'];
        $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $this->disabled;
        $this->incrementVersion('modal-pasca-anestesi-ri');

        $this->dispatch('open-modal', name: "rm-pasca-anestesi-ri-{$this->riHdrNo}");
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: "rm-pasca-anestesi-ri-{$this->riHdrNo}");
    }

    /* ===============================
     | VALIDATION
     =============================== */
    protected function rules(): array
    {
        return [
            'newForm.jamMasuk' => 'required|string|max:20',
            'newForm.jamKeluar' => 'nullable|string|max:20',
            'newForm.keadaanUmum' => 'nullable|string|max:300',
            'newForm.jenisAnestesi' => 'required|string',
            'newForm.aldrete.kesadaran' => 'required|in:0,1,2',
            'newForm.aldrete.pernafasan' => 'required|in:0,1,2',
            'newForm.aldrete.sirkulasi' => 'required|in:0,1,2',
            'newForm.aldrete.aktivitas' => 'required|in:0,1,2',
            'newForm.aldrete.warnaKulit' => 'required|in:0,1,2',
            'newForm.bromage' => 'required_if:newForm.jenisAnestesi,Regional / Spinal|nullable|in:0,1,2,3',
            'newForm.skalaNyeri' => 'nullable|integer|min:0|max:10',
            'newForm.rekomendasi' => 'required|string',
            'newForm.keteranganRekomendasi' => 'nullable|string|max:300',
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'required_if' => ':attribute wajib diisi untuk anestesi regional/spinal.',
            'in' => ':attribute tidak valid.',
            'integer' => ':attribute harus angka.',
            'max' => ':attribute maksimal :max.',
            'min' => ':attribute minimal :min.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'newForm.jamMasuk' => 'Jam masuk RR',
            'newForm.jenisAnestesi' => 'Jenis anestesi',
            'newForm.aldrete.kesadaran' => 'Aldrete — Kesadaran',
            'newForm.aldrete.pernafasan' => 'Aldrete — Pernafasan',
            'newForm.aldrete.sirkulasi' => 'Aldrete — Sirkulasi',
            'newForm.aldrete.aktivitas' => 'Aldrete — Aktivitas',
            'newForm.aldrete.warnaKulit' => 'Aldrete — Warna kulit',
            'newForm.bromage' => 'Bromage score',
            'newForm.skalaNyeri' => 'Skala nyeri',
            'newForm.rekomendasi' => 'Rekomendasi',
        ];
    }

    /* ===============================
     | SET JAM SEKARANG
     =============================== */
    public function setJamMasukSekarang(): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->newForm['jamMasuk'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    public function setJamKeluarSekarang(): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->newForm['jamKeluar'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /* ===============================
     | TTD PERAWAT RR (auto user login)
     =============================== */
    public function setTtd(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        if (!empty($this->newForm['ttd'])) {
            $this->dispatch('toast', type: 'warning', message: 'Tanda tangan sudah ada.');
            return;
        }
        $this->newForm['ttd'] = auth()->user()->myuser_name ?? '';
        $this->newForm['ttdCode'] = auth()->user()->myuser_code ?? '';
        $this->newForm['ttdDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        $this->dispatch('toast', type: 'success', message: 'Tanda tangan berhasil ditambahkan.');
    }

    public function clearTtd(): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->newForm['ttd'] = '';
        $this->newForm['ttdCode'] = '';
        $this->newForm['ttdDate'] = '';
    }

    /* ===============================
     | SIMPAN ENTRI BARU
     =============================== */
    public function addEntry(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only, tidak dapat menyimpan.');
            return;
        }

        if (empty($this->newForm['ttd'])) {
            $this->dispatch('toast', type: 'error', message: 'Tanda tangan perawat RR belum diisi.');
            return;
        }

        $this->validateWithToast();

        $now = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        $entry = $this->newForm;
        $entry['totalAldrete'] = $this->totalAldrete();
        $entry['createdAt'] = $now;

        try {
            DB::transaction(function () use ($entry) {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?: [];
                if (!isset($fresh['pascaAnestesiRI']) || !is_array($fresh['pascaAnestesiRI'])) {
                    $fresh['pascaAnestesiRI'] = [];
                }

                $fresh['pascaAnestesiRI'][] = $entry;

                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
                $this->pascaList = $fresh['pascaAnestesiRI'];

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Tambah Monitoring Pasca Anestesi — Aldrete ' . ($entry['totalAldrete'] ?? '-') . ' — ' . ($entry['createdAt'] ?? '-'), 'MR');
            });

            $this->incrementVersion('modal-pasca-anestesi-ri');
            $this->dispatch('toast', type: 'success', message: 'Monitoring pasca anestesi berhasil disimpan.');

            $this->resetNewForm();
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    /* ===============================
     | CETAK (inline stream PDF)
     =============================== */
    public function cetak(string $createdAt)
    {
        $entry = collect($this->pascaList)->firstWhere('createdAt', $createdAt);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Data monitoring tidak ditemukan.');
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

            $ttdPath = null;
            $ttdCode = $entry['ttdCode'] ?? null;
            if ($ttdCode) {
                $path = DB::table('users')->where('myuser_code', $ttdCode)->value('myuser_ttd_image');
                if (!empty($path) && file_exists(public_path('storage/' . $path))) {
                    $ttdPath = public_path('storage/' . $path);
                }
            }

            $data = array_merge($pasien, [
                'dataRi' => $this->dataDaftarRi,
                'form' => $entry,
                'identitasRs' => $identitasRs,
                'ttdPath' => $ttdPath,
                'aldreteItems' => $this->aldreteItems,
                'bromageOptions' => $this->bromageOptions,
                'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
            ]);

            set_time_limit(300);

            $pdf = Pdf::loadView('pages.components.modul-dokumen.r-i.pasca-anestesi-ri.cetak-pasca-anestesi-ri-print', ['data' => $data])->setPaper('A4');

            $this->dispatch('toast', type: 'success', message: 'Berhasil mencetak monitoring pasca anestesi.');
            return response()->streamDownload(fn() => print $pdf->output(), 'pasca-anestesi-ri-' . ($pasien['regNo'] ?? $this->riHdrNo) . '.pdf');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal cetak: ' . $e->getMessage());
        }
    }

    /* ===============================
     | HAPUS
     =============================== */
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
                if (!isset($fresh['pascaAnestesiRI'])) {
                    throw new \RuntimeException('Data monitoring tidak ditemukan.');
                }

                $fresh['pascaAnestesiRI'] = collect($fresh['pascaAnestesiRI'])
                    ->reject(fn($item) => ($item['createdAt'] ?? '') === $createdAt)
                    ->values()
                    ->toArray();

                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
                $this->pascaList = $fresh['pascaAnestesiRI'];

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Monitoring Pasca Anestesi — ' . $createdAt, 'MR');
            });

            $this->incrementVersion('modal-pasca-anestesi-ri');
            $this->dispatch('toast', type: 'success', message: 'Monitoring pasca anestesi berhasil dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    /* ===============================
     | RESET
     =============================== */
    private function resetNewForm(): void
    {
        $this->newForm = [
            'jamMasuk' => '',
            'jamKeluar' => '',
            'keadaanUmum' => '',
            'td' => '',
            'nadi' => '',
            'rr' => '',
            'suhu' => '',
            'spo2' => '',
            'jenisAnestesi' => 'Umum',
            'aldrete' => [
                'kesadaran' => '',
                'pernafasan' => '',
                'sirkulasi' => '',
                'aktivitas' => '',
                'warnaKulit' => '',
            ],
            'bromage' => '',
            'skalaNyeri' => '',
            'rekomendasi' => '',
            'keteranganRekomendasi' => '',
            'ttd' => '',
            'ttdCode' => '',
            'ttdDate' => '',
        ];
    }
};
?>

<div>
    {{-- ══ SUMMARY CARD (inline) ══ --}}
    @php $paCount = count($pascaList ?? []); @endphp

    <div class="p-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex-1 space-y-3">
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">Monitoring Pasca Anestesi (RR)</h3>
                    @if ($paCount > 0)
                        <x-badge variant="success">{{ $paCount }} catatan</x-badge>
                    @else
                        <x-badge variant="warning">Belum ada</x-badge>
                    @endif
                </div>
                <p class="text-base text-muted dark:text-gray-400">
                    Pemulihan di Recovery Room (PAB 6.1 / RM 55): skor <span class="font-medium">Aldrete</span> (anestesi
                    umum) &amp; <span class="font-medium">Bromage</span> (regional/spinal), skala nyeri, rekomendasi
                    pemindahan pasien.
                </p>
                @if ($paCount > 0)
                    <ul class="space-y-1 text-base text-muted dark:text-gray-300 list-disc pl-5">
                        @foreach (array_slice($pascaList, 0, 3) as $pa)
                            <li>
                                <span class="font-medium">Aldrete {{ $pa['totalAldrete'] ?? '-' }}/10</span>
                                @if (!empty($pa['rekomendasi']))
                                    <span class="text-sm text-muted-soft">— {{ $pa['rekomendasi'] }}</span>
                                @endif
                                @if (!empty($pa['jamMasuk']))
                                    <span class="text-sm text-muted-soft">({{ $pa['jamMasuk'] }})</span>
                                @endif
                            </li>
                        @endforeach
                        @if ($paCount > 3)
                            <li class="text-sm italic text-muted-soft">+{{ $paCount - 3 }} lainnya…</li>
                        @endif
                    </ul>
                @endif
            </div>

            <div class="flex shrink-0">
                <x-primary-button type="button" wire:click="openModal" wire:loading.attr="disabled"
                    wire:target="openModal" :disabled="$disabled || !$riHdrNo" class="gap-2">
                    <span wire:loading.remove wire:target="openModal" class="flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M14 5l7 7m0 0l-7 7m7-7H3" />
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
    <x-modal name="rm-pasca-anestesi-ri-{{ $riHdrNo ?? 'init' }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal-pasca-anestesi-ri', [$riHdrNo ?? 'new']) }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-sky-500/10">
                                <svg class="w-6 h-6 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M3 12h4l2 5 4-10 2 5h6" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="font-semibold text-2xl text-ink dark:text-gray-100">Monitoring Pasca Anestesi
                                </h2>
                                <p class="mt-0.5 text-base text-muted dark:text-gray-400">
                                    PAB 6.1 / RM 55 — Aldrete &amp; Bromage di Recovery Room
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 mt-3">
                            <x-badge variant="brand">Rawat Inap</x-badge>
                            @if (count($pascaList) > 0)
                                <x-badge variant="info">{{ count($pascaList) }} tersimpan</x-badge>
                            @endif
                            @if ($isFormLocked)
                                <x-badge variant="danger">Read Only</x-badge>
                            @endif
                        </div>
                    </div>

                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <span class="sr-only">Close</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-surface-soft/70 dark:bg-gray-950/20">
                <div class="max-w-full mx-auto space-y-4">

                    <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="$riHdrNo"
                        wire:key="pa-ri-display-pasien-{{ $riHdrNo ?? 'init' }}" />

                    <div
                        class="p-6 space-y-6 bg-canvas border border-hairline shadow-sm sm:p-8 rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                        @if ($isFormLocked)
                            <div
                                class="flex items-center gap-2 px-4 py-2.5 text-base font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-900/20 dark:border-amber-600 dark:text-amber-300">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                                EMR terkunci — data tidak dapat diubah.
                            </div>
                        @endif

                        {{-- ══ JAM & KEADAAN ══ --}}
                        <section class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <x-input-label value="Jam Masuk RR *" class="mb-1" />
                                <div class="flex items-center gap-2">
                                    <x-text-input wire:model.live="newForm.jamMasuk" placeholder="dd/mm/yyyy HH:mm:ss"
                                        :error="$errors->has('newForm.jamMasuk')" :disabled="$isFormLocked"
                                        class="w-full" />
                                    @if (!$isFormLocked)
                                        <x-now-button wire:click="setJamMasukSekarang" />
                                    @endif
                                </div>
                                <x-input-error :messages="$errors->get('newForm.jamMasuk')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Jam Keluar RR" class="mb-1" />
                                <div class="flex items-center gap-2">
                                    <x-text-input wire:model.live="newForm.jamKeluar" :error="$errors->has('newForm.jamKeluar')" placeholder="dd/mm/yyyy HH:mm:ss"
                                        :disabled="$isFormLocked" class="w-full" />
                                    @if (!$isFormLocked)
                                        <x-now-button wire:click="setJamKeluarSekarang" />
                                    @endif
                                </div>
                            </div>
                        </section>

                        <section>
                            <x-input-label value="Keadaan Umum" class="mb-1" />
                            <x-text-input wire:model.live="newForm.keadaanUmum" :error="$errors->has('newForm.keadaanUmum')"
                                placeholder="cth: Sadar penuh, nafas spontan adekuat" :disabled="$isFormLocked"
                                class="w-full" />
                        </section>

                        {{-- ══ TTV SAAT MASUK RR ══ --}}
                        <section class="pt-6 border-t border-hairline dark:border-gray-700">
                            <h3 class="mb-3 text-base font-semibold text-ink dark:text-gray-200">Tanda Vital (saat masuk
                                RR)</h3>
                            <div class="grid grid-cols-2 gap-4 md:grid-cols-5">
                                <div>
                                    <x-input-label value="TD (mmHg)" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.td" :error="$errors->has('newForm.td')" placeholder="120/80"
                                        :disabled="$isFormLocked" class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Nadi" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.nadi" :error="$errors->has('newForm.nadi')" :disabled="$isFormLocked"
                                        class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="RR" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.rr" :error="$errors->has('newForm.rr')" :disabled="$isFormLocked"
                                        class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Suhu (°C)" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.suhu" :error="$errors->has('newForm.suhu')" :disabled="$isFormLocked"
                                        class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="SpO2 (%)" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.spo2" :error="$errors->has('newForm.spo2')" :disabled="$isFormLocked"
                                        class="w-full" />
                                </div>
                            </div>
                        </section>

                        {{-- ══ JENIS ANESTESI ══ --}}
                        <section class="pt-6 space-y-3 border-t border-hairline dark:border-gray-700">
                            <x-input-label value="Jenis Anestesi *" class="mb-1" />
                            <div class="flex flex-wrap gap-2">
                                @foreach ($jenisAnestesiOptions as $opt)
                                    <x-radio-button :label="$opt" :value="$opt" name="jenisAnestesi"
                                        wire:model.live="newForm.jenisAnestesi" :disabled="$isFormLocked" />
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('newForm.jenisAnestesi')" class="mt-1" />
                        </section>

                        {{-- ══ ALDRETE SCORE ══ --}}
                        <section class="pt-6 space-y-3 border-t border-hairline dark:border-gray-700">
                            <div class="flex items-center justify-between">
                                <h3 class="text-base font-semibold text-ink dark:text-gray-200">Aldrete Score</h3>
                                <span
                                    class="px-3 py-1 text-base font-bold rounded-lg {{ $this->totalAldrete >= 8 ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' }}">
                                    Total: {{ $this->totalAldrete }}/10
                                </span>
                            </div>
                            <div class="space-y-4">
                                @foreach ($aldreteItems as $key => $item)
                                    <div>
                                        <x-input-label :value="$item['label'] . ' *'" class="mb-1" />
                                        <div class="flex flex-wrap gap-2">
                                            @foreach ($item['opsi'] as $skor => $teks)
                                                <x-radio-button :label="$skor . ' — ' . $teks" :value="$skor"
                                                    name="aldrete_{{ $key }}"
                                                    wire:model.live="newForm.aldrete.{{ $key }}"
                                                    :disabled="$isFormLocked" />
                                            @endforeach
                                        </div>
                                        <x-input-error :messages="$errors->get('newForm.aldrete.' . $key)" class="mt-1" />
                                    </div>
                                @endforeach
                            </div>
                            <p class="text-sm text-muted-soft dark:text-gray-500">
                                Kriteria: total Aldrete ≥ 8 → boleh pindah ke ruangan rawat inap (anestesi umum).
                            </p>
                        </section>

                        {{-- ══ BROMAGE SCORE (regional/spinal) ══ --}}
                        @if (($newForm['jenisAnestesi'] ?? '') === 'Regional / Spinal')
                            <section class="pt-6 space-y-3 border-t border-hairline dark:border-gray-700">
                                <h3 class="text-base font-semibold text-ink dark:text-gray-200">Bromage Score
                                    (regional/spinal)</h3>
                                <div class="flex flex-col gap-2">
                                    @foreach ($bromageOptions as $skor => $teks)
                                        <x-radio-button :label="$skor . ' — ' . $teks" :value="$skor" name="bromage"
                                            wire:model.live="newForm.bromage" :disabled="$isFormLocked" />
                                    @endforeach
                                </div>
                                <x-input-error :messages="$errors->get('newForm.bromage')" class="mt-1" />
                                <p class="text-sm text-muted-soft dark:text-gray-500">
                                    Kriteria pemindahan pasien regional mengikuti pemulihan blok motorik (Bromage) sesuai
                                    kebijakan RS.
                                </p>
                            </section>
                        @endif

                        {{-- ══ SKALA NYERI & REKOMENDASI ══ --}}
                        <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                            <div class="max-w-xs">
                                <x-input-label value="Skala Nyeri (0–10)" class="mb-1" />
                                <x-text-input type="number" wire:model.live="newForm.skalaNyeri" :error="$errors->has('newForm.skalaNyeri')"
                                    :disabled="$isFormLocked" class="w-full" />
                                <x-input-error :messages="$errors->get('newForm.skalaNyeri')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Rekomendasi *" class="mb-1" />
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($rekomendasiOptions as $opt)
                                        <x-radio-button :label="$opt" :value="$opt" name="rekomendasi"
                                            wire:model.live="newForm.rekomendasi" :disabled="$isFormLocked" />
                                    @endforeach
                                </div>
                                <x-input-error :messages="$errors->get('newForm.rekomendasi')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Keterangan Rekomendasi" class="mb-1" />
                                <x-text-input wire:model.live="newForm.keteranganRekomendasi" :error="$errors->has('newForm.keteranganRekomendasi')" :disabled="$isFormLocked"
                                    class="w-full" />
                            </div>
                        </section>

                        {{-- ══ TTD PERAWAT RR ══ --}}
                        <x-signature.ttd-petugas :ttd="$newForm['ttd']" :date="$newForm['ttdDate'] ?? ''"
                            :code="$newForm['ttdCode'] ?? ''" :locked="$isFormLocked" sign="setTtd" clear="clearTtd"
                            title="Tanda Tangan Petugas Recovery Room" label="" signLabel="TTD sebagai Petugas RR" clearLabel="Hapus TTD" />

                        {{-- ══ DAFTAR TERSIMPAN ══ --}}
                        @if (count($pascaList) > 0)
                            <div class="mt-6 overflow-x-auto">
                                <h3
                                    class="text-base font-semibold text-body dark:text-gray-300 pb-2 border-b border-hairline-soft dark:border-gray-800 mb-3">
                                    Daftar Monitoring Tersimpan
                                </h3>
                                <table
                                    class="min-w-full text-base border border-hairline rounded-lg dark:border-gray-700">
                                    <thead class="bg-surface-soft dark:bg-gray-800">
                                        <tr class="text-left text-muted dark:text-gray-300">
                                            <th class="px-4 py-2 border-b">Jam Masuk</th>
                                            <th class="px-4 py-2 border-b">Aldrete</th>
                                            <th class="px-4 py-2 border-b">Rekomendasi</th>
                                            <th class="px-4 py-2 border-b text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($pascaList as $pa)
                                            <tr
                                                class="border-b border-hairline dark:border-gray-700 hover:bg-surface-soft dark:hover:bg-gray-800">
                                                <td class="px-4 py-2 text-muted dark:text-gray-400">
                                                    {{ $pa['jamMasuk'] ?? '-' }}</td>
                                                <td class="px-4 py-2 font-medium text-ink dark:text-gray-200">
                                                    {{ $pa['totalAldrete'] ?? '-' }}/10
                                                    @if (($pa['jenisAnestesi'] ?? '') === 'Regional / Spinal')
                                                        <span class="text-sm text-muted-soft">· Bromage
                                                            {{ $pa['bromage'] ?? '-' }}</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-2 text-muted dark:text-gray-400">
                                                    {{ $pa['rekomendasi'] ?? '-' }}</td>
                                                <td class="px-4 py-2 text-center space-x-2 whitespace-nowrap">
                                                    <x-secondary-button wire:click="cetak('{{ $pa['createdAt'] }}')"
                                                        wire:loading.attr="disabled"
                                                        wire:target="cetak('{{ $pa['createdAt'] }}')"
                                                        class="text-sm py-1 px-2">
                                                        <span wire:loading.remove
                                                            wire:target="cetak('{{ $pa['createdAt'] }}')"
                                                            class="flex items-center gap-1">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24" stroke-width="2">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                            </svg>
                                                            Cetak
                                                        </span>
                                                        <span wire:loading
                                                            wire:target="cetak('{{ $pa['createdAt'] }}')"
                                                            class="flex items-center gap-1"><x-loading />
                                                            Mencetak...</span>
                                                    </x-secondary-button>
                                                    @if (!$isFormLocked)
                                                        <x-outline-button type="button"
                                                            wire:click.prevent="hapus('{{ $pa['createdAt'] }}')"
                                                            wire:confirm="Yakin hapus monitoring ini?"
                                                            wire:loading.attr="disabled"
                                                            class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300 !px-2 !py-1"
                                                            title="Hapus">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2"
                                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                            </svg>
                                                        </x-outline-button>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-center justify-end gap-3">
                    <x-secondary-button wire:click="closeModal">Tutup</x-secondary-button>

                    @if ($riHdrNo && !$isFormLocked)
                        <x-primary-button wire:click.prevent="addEntry" wire:loading.attr="disabled"
                            wire:target="addEntry" class="gap-2 min-w-[180px] justify-center">
                            <span wire:loading.remove wire:target="addEntry">Simpan Monitoring</span>
                            <span wire:loading wire:target="addEntry"><x-loading class="w-4 h-4" />
                                Menyimpan...</span>
                        </x-primary-button>
                    @endif
                </div>
            </div>

        </div>
    </x-modal>
</div>
