<?php
// resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/site-marking-ri/rm-site-marking-ri-actions.blade.php

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

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-site-marking-ri'];

    // ── Form entri baru (Penandaan Lokasi Operasi — SKP 4 / RM 49) ──
    public array $newForm = [
        'tanggal' => '',
        'rencanaTindakan' => '',
        'perluPenandaan' => 'Ya',
        'alasanTidakPerlu' => '',
        'regionAnatomi' => '',
        'sisi' => '',
        'detailLokasi' => '',
        'metodePenandaan' => 'Spidol permanen — inisial/tanda operator',
        'pasienDilibatkan' => false,
        'namaPerawatRuangan' => '',
        'namaPerawatKamarBedah' => '',
        // TTD operator (auto user login)
        'operatorTtd' => '',
        'operatorTtdCode' => '',
        'operatorTtdDate' => '',
    ];

    public string $signaturePerawatRuangan = '';
    public string $signaturePerawatKamarBedah = '';

    // Tanda titik pada diagram tubuh: [['view'=>'anterior'|'posterior','x'=>float,'y'=>float], ...]
    public array $marks = [];

    public array $markingList = [];

    public array $perluOptions = ['Ya', 'Tidak diperlukan'];
    public array $sisiOptions = ['Kiri', 'Kanan', 'Bilateral', 'Garis tengah', 'Multipel level'];
    public array $regionOptions = [
        'Kepala & Leher', 'Mata', 'THT', 'Gigi & Mulut', 'Dada / Thoraks', 'Payudara',
        'Abdomen', 'Punggung / Spinal', 'Panggul', 'Genitalia',
        'Ekstremitas Atas', 'Ekstremitas Bawah', 'Tangan / Jari Tangan', 'Kaki / Jari Kaki', 'Lainnya',
    ];

    /* ===============================
     | MOUNT
     =============================== */
    public function mount(?string $riHdrNo = null, bool $disabled = false): void
    {
        $this->riHdrNo = $riHdrNo ?: null;
        $this->disabled = $disabled;
        $this->registerAreas(['modal-site-marking-ri']);

        if ($this->riHdrNo) {
            $data = $this->findDataRI($this->riHdrNo);
            if ($data) {
                $this->dataDaftarRi = $data;
                $this->regNo = $data['regNo'] ?? null;
                $this->markingList = $data['siteMarkingRI'] ?? [];
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
        $this->signaturePerawatRuangan = '';
        $this->signaturePerawatKamarBedah = '';
        $this->marks = [];
        $this->resetValidation();

        $data = $this->findDataRI($this->riHdrNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }

        $this->dataDaftarRi = $data;
        $this->regNo = $data['regNo'] ?? null;
        if (!isset($this->dataDaftarRi['siteMarkingRI']) || !is_array($this->dataDaftarRi['siteMarkingRI'])) {
            $this->dataDaftarRi['siteMarkingRI'] = [];
        }
        $this->markingList = $this->dataDaftarRi['siteMarkingRI'];
        $this->isFormLocked = $this->checkEmrRIStatus($this->riHdrNo) || $this->disabled;
        $this->incrementVersion('modal-site-marking-ri');

        $this->dispatch('open-modal', name: "rm-site-marking-ri-{$this->riHdrNo}");
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: "rm-site-marking-ri-{$this->riHdrNo}");
    }

    /* ===============================
     | VALIDATION
     =============================== */
    protected function rules(): array
    {
        return [
            'newForm.tanggal' => 'required|date_format:d/m/Y H:i:s',
            'newForm.rencanaTindakan' => 'required|string|max:500',
            'newForm.perluPenandaan' => 'required|string',
            'newForm.alasanTidakPerlu' => 'required_if:newForm.perluPenandaan,Tidak diperlukan|nullable|string|max:500',
            'newForm.regionAnatomi' => 'required_if:newForm.perluPenandaan,Ya|nullable|string|max:100',
            'newForm.sisi' => 'required_if:newForm.perluPenandaan,Ya|nullable|string|max:50',
            'newForm.detailLokasi' => 'nullable|string|max:300',
            'newForm.metodePenandaan' => 'nullable|string|max:300',
            'newForm.namaPerawatRuangan' => 'required_if:newForm.perluPenandaan,Ya|nullable|string|max:200',
            'newForm.namaPerawatKamarBedah' => 'required_if:newForm.perluPenandaan,Ya|nullable|string|max:200',
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'required_if' => ':attribute wajib diisi.',
            'date_format' => 'Format :attribute harus dd/mm/yyyy HH:mm:ss (cth: 26/06/2026 02:00:00).',
            'max' => ':attribute maksimal :max karakter.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'newForm.tanggal' => 'Tanggal/jam penandaan',
            'newForm.rencanaTindakan' => 'Rencana tindakan operasi',
            'newForm.perluPenandaan' => 'Perlu penandaan',
            'newForm.alasanTidakPerlu' => 'Alasan tidak perlu penandaan',
            'newForm.regionAnatomi' => 'Region anatomi',
            'newForm.sisi' => 'Sisi/lateralitas',
            'newForm.namaPerawatRuangan' => 'Nama perawat ruangan',
            'newForm.namaPerawatKamarBedah' => 'Nama perawat kamar bedah',
        ];
    }

    /* ===============================
     | SET TANGGAL SEKARANG
     =============================== */
    public function setTanggalSekarang(): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->newForm['tanggal'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    }

    /* ===============================
     | TTD OPERATOR (auto user login)
     =============================== */
    public function setOperatorTtd(): void
    {
        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Form read-only.');
            return;
        }
        if (!empty($this->newForm['operatorTtd'])) {
            $this->dispatch('toast', type: 'warning', message: 'Tanda tangan operator sudah ada.');
            return;
        }
        $this->newForm['operatorTtd'] = auth()->user()->myuser_name ?? '';
        $this->newForm['operatorTtdCode'] = auth()->user()->myuser_code ?? '';
        $this->newForm['operatorTtdDate'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
        $this->dispatch('toast', type: 'success', message: 'Tanda tangan operator berhasil ditambahkan.');
    }

    public function clearOperatorTtd(): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->newForm['operatorTtd'] = '';
        $this->newForm['operatorTtdCode'] = '';
        $this->newForm['operatorTtdDate'] = '';
    }

    /* ===============================
     | TANDA DIAGRAM TUBUH (klik SVG)
     =============================== */
    public array $validViews = [
        'priaFront', 'priaBack', 'wanitaFront', 'wanitaBack',
        'handPalmKiri', 'handPalmKanan', 'handDorsumKiri', 'handDorsumKanan',
        'footPalmKanan', 'footPalmKiri', 'footDorsumKiri', 'footDorsumKanan',
        'headFront', 'headBack', 'headProfileKiri', 'headProfileKanan',
    ];

    public function addMark(string $view, $x, $y): void
    {
        if ($this->isFormLocked) {
            return;
        }
        if (!in_array($view, $this->validViews, true)) {
            return;
        }
        // koordinat persen (0..100) relatif panel
        $x = max(0, min(100, (float) $x));
        $y = max(0, min(100, (float) $y));
        $this->marks[] = ['view' => $view, 'x' => round($x, 2), 'y' => round($y, 2)];
    }

    public function undoMark(): void
    {
        if ($this->isFormLocked) {
            return;
        }
        array_pop($this->marks);
    }

    public function clearMarks(): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->marks = [];
    }

    /* ===============================
     | SIGNATURE PERAWAT (drawn)
     =============================== */
    public function setSignaturePerawatRuangan(string $dataUrl): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->signaturePerawatRuangan = $dataUrl;
        $this->incrementVersion('modal-site-marking-ri');
    }

    public function clearSignaturePerawatRuangan(): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->signaturePerawatRuangan = '';
        $this->incrementVersion('modal-site-marking-ri');
    }

    public function setSignaturePerawatKamarBedah(string $dataUrl): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->signaturePerawatKamarBedah = $dataUrl;
        $this->incrementVersion('modal-site-marking-ri');
    }

    public function clearSignaturePerawatKamarBedah(): void
    {
        if ($this->isFormLocked) {
            return;
        }
        $this->signaturePerawatKamarBedah = '';
        $this->incrementVersion('modal-site-marking-ri');
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

        if (empty($this->newForm['operatorTtd'])) {
            $this->dispatch('toast', type: 'error', message: 'Tanda tangan dokter operator belum diisi.');
            return;
        }

        $perlu = ($this->newForm['perluPenandaan'] ?? '') === 'Ya';
        if ($perlu && (empty($this->signaturePerawatRuangan) || empty($this->signaturePerawatKamarBedah))) {
            $this->dispatch('toast', type: 'error', message: 'Tanda tangan perawat ruangan & perawat kamar bedah wajib diisi.');
            return;
        }

        $this->validateWithToast();

        $now = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

        $entry = $this->newForm;
        $entry['signaturePerawatRuangan'] = $perlu ? $this->signaturePerawatRuangan : '';
        $entry['signaturePerawatKamarBedah'] = $perlu ? $this->signaturePerawatKamarBedah : '';
        $entry['marks'] = $perlu ? $this->marks : [];
        $entry['createdAt'] = $now;

        try {
            DB::transaction(function () use ($entry) {
                $this->lockRIRow($this->riHdrNo);

                $fresh = $this->findDataRI($this->riHdrNo) ?: [];
                if (!isset($fresh['siteMarkingRI']) || !is_array($fresh['siteMarkingRI'])) {
                    $fresh['siteMarkingRI'] = [];
                }

                $fresh['siteMarkingRI'][] = $entry;

                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
                $this->markingList = $fresh['siteMarkingRI'];

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Tambah Penandaan Lokasi Operasi — ' . ($entry['regionAnatomi'] ?? '-') . ' ' . ($entry['sisi'] ?? '') . ' — ' . ($entry['createdAt'] ?? '-'), 'MR');
            });

            $this->incrementVersion('modal-site-marking-ri');
            $this->dispatch('toast', type: 'success', message: 'Penandaan lokasi operasi berhasil disimpan.');

            $this->resetNewForm();
            $this->signaturePerawatRuangan = '';
            $this->signaturePerawatKamarBedah = '';
            $this->marks = [];
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
        $entry = collect($this->markingList)->firstWhere('createdAt', $createdAt);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Data penandaan tidak ditemukan.');
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

            $ttdOperatorPath = null;
            $operatorCode = $entry['operatorTtdCode'] ?? null;
            if ($operatorCode) {
                $path = DB::table('users')->where('myuser_code', $operatorCode)->value('myuser_ttd_image');
                if (!empty($path) && file_exists(public_path('storage/' . $path))) {
                    $ttdOperatorPath = public_path('storage/' . $path);
                }
            }

            $data = array_merge($pasien, [
                'dataRi' => $this->dataDaftarRi,
                'form' => $entry,
                'identitasRs' => $identitasRs,
                'ttdOperatorPath' => $ttdOperatorPath,
                'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
            ]);

            set_time_limit(300);

            $pdf = Pdf::loadView('pages.components.modul-dokumen.r-i.site-marking-ri.cetak-site-marking-ri-print', ['data' => $data])->setPaper('A4');

            $this->dispatch('toast', type: 'success', message: 'Berhasil mencetak penandaan lokasi operasi.');
            return response()->streamDownload(fn() => print $pdf->output(), 'site-marking-ri-' . ($pasien['regNo'] ?? $this->riHdrNo) . '.pdf');
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
                if (!isset($fresh['siteMarkingRI'])) {
                    throw new \RuntimeException('Data penandaan tidak ditemukan.');
                }

                $fresh['siteMarkingRI'] = collect($fresh['siteMarkingRI'])
                    ->reject(fn($item) => ($item['createdAt'] ?? '') === $createdAt)
                    ->values()
                    ->toArray();

                $this->updateJsonRI((int) $this->riHdrNo, $fresh);
                $this->dataDaftarRi = $fresh;
                $this->markingList = $fresh['siteMarkingRI'];

                $this->appendAdminLogRI((int) $this->riHdrNo, 'Hapus Penandaan Lokasi Operasi — ' . $createdAt, 'MR');
            });

            $this->incrementVersion('modal-site-marking-ri');
            $this->dispatch('toast', type: 'success', message: 'Penandaan lokasi operasi berhasil dihapus.');
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
            'tanggal' => '',
            'rencanaTindakan' => '',
            'perluPenandaan' => 'Ya',
            'alasanTidakPerlu' => '',
            'regionAnatomi' => '',
            'sisi' => '',
            'detailLokasi' => '',
            'metodePenandaan' => 'Spidol permanen — inisial/tanda operator',
            'pasienDilibatkan' => false,
            'namaPerawatRuangan' => '',
            'namaPerawatKamarBedah' => '',
            'operatorTtd' => '',
            'operatorTtdCode' => '',
            'operatorTtdDate' => '',
        ];
    }
};
?>

<div>
    {{-- ══ SUMMARY CARD (inline) ══ --}}
    @php $smCount = count($markingList ?? []); @endphp

    <div class="p-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex-1 space-y-3">
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">Penandaan Lokasi Operasi</h3>
                    @if ($smCount > 0)
                        <x-badge variant="success">{{ $smCount }} penandaan</x-badge>
                    @else
                        <x-badge variant="warning">Belum ada</x-badge>
                    @endif
                </div>
                <p class="text-base text-muted dark:text-gray-400">
                    Site marking (SKP 4): penandaan sisi/lokasi operasi sebelum tindakan, melibatkan pasien, diverifikasi
                    Perawat Ruangan, Perawat Kamar Bedah &amp; Dokter Operator.
                </p>
                @if ($smCount > 0)
                    <ul class="space-y-1 text-base text-muted dark:text-gray-300 list-disc pl-5">
                        @foreach (array_slice($markingList, 0, 3) as $sm)
                            <li>
                                <span class="font-medium">{{ ($sm['regionAnatomi'] ?? '-') . ' ' . ($sm['sisi'] ?? '') }}</span>
                                @if (!empty($sm['tanggal']))
                                    <span class="text-sm text-muted-soft">— {{ $sm['tanggal'] }}</span>
                                @endif
                            </li>
                        @endforeach
                        @if ($smCount > 3)
                            <li class="text-sm italic text-muted-soft">+{{ $smCount - 3 }} lainnya…</li>
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
    <x-modal name="rm-site-marking-ri-{{ $riHdrNo ?? 'init' }}" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal-site-marking-ri', [$riHdrNo ?? 'new']) }}">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-indigo-500/10">
                                <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="font-semibold text-2xl text-ink dark:text-gray-100">Penandaan Lokasi Operasi
                                </h2>
                                <p class="mt-0.5 text-base text-muted dark:text-gray-400">
                                    SKP 4 — site marking sebelum operasi, verifikasi 3 pihak
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 mt-3">
                            <x-badge variant="brand">Rawat Inap</x-badge>
                            @if (count($markingList) > 0)
                                <x-badge variant="info">{{ count($markingList) }} tersimpan</x-badge>
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
                        wire:key="sm-ri-display-pasien-{{ $riHdrNo ?? 'init' }}" />

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

                        {{-- ══ TANGGAL & RENCANA ══ --}}
                        <section class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <x-input-label value="Tanggal / Jam Penandaan *" class="mb-1" />
                                <div class="flex items-center gap-2">
                                    <x-text-input wire:model.live="newForm.tanggal" placeholder="dd/mm/yyyy HH:mm:ss"
                                        :error="$errors->has('newForm.tanggal')" :disabled="$isFormLocked"
                                        class="w-full" />
                                    @if (!$isFormLocked)
                                        <x-now-button wire:click="setTanggalSekarang" />
                                    @endif
                                </div>
                                <x-input-error :messages="$errors->get('newForm.tanggal')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Rencana Tindakan Operasi *" class="mb-1" />
                                <x-text-input wire:model.live="newForm.rencanaTindakan" :error="$errors->has('newForm.rencanaTindakan')" :disabled="$isFormLocked"
                                    class="w-full" />
                                <x-input-error :messages="$errors->get('newForm.rencanaTindakan')" class="mt-1" />
                            </div>
                        </section>

                        {{-- ══ PERLU PENANDAAN? ══ --}}
                        <section class="pt-6 space-y-3 border-t border-hairline dark:border-gray-700">
                            <x-input-label value="Penandaan Lokasi *" class="mb-1" />
                            <div class="flex flex-wrap gap-2">
                                @foreach ($perluOptions as $opt)
                                    <x-radio-button :label="$opt" :value="$opt" name="perluPenandaan"
                                        wire:model.live="newForm.perluPenandaan" :disabled="$isFormLocked" />
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('newForm.perluPenandaan')" class="mt-1" />

                            @if (($newForm['perluPenandaan'] ?? '') === 'Tidak diperlukan')
                                <div class="mt-2">
                                    <x-input-label value="Alasan Tidak Diperlukan *" class="mb-1" />
                                    <x-textarea wire:model.live="newForm.alasanTidakPerlu" :error="$errors->has('newForm.alasanTidakPerlu')" rows="2"
                                        placeholder="cth: organ tunggal / garis tengah / kasus tidak melibatkan lateralitas"
                                        :disabled="$isFormLocked" class="w-full" />
                                    <x-input-error :messages="$errors->get('newForm.alasanTidakPerlu')" class="mt-1" />
                                </div>
                            @endif
                        </section>

                        {{-- ══ DETAIL LOKASI (bila perlu) ══ --}}
                        @if (($newForm['perluPenandaan'] ?? '') === 'Ya')
                            <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <x-input-label value="Region Anatomi *" class="mb-1" />
                                        <x-select-input wire:model.live="newForm.regionAnatomi" :error="$errors->has('newForm.regionAnatomi')" :disabled="$isFormLocked"
                                            class="w-full">
                                            <option value="">— pilih —</option>
                                            @foreach ($regionOptions as $opt)
                                                <option value="{{ $opt }}">{{ $opt }}</option>
                                            @endforeach
                                        </x-select-input>
                                        <x-input-error :messages="$errors->get('newForm.regionAnatomi')" class="mt-1" />
                                    </div>
                                    <div>
                                        <x-input-label value="Sisi / Lateralitas *" class="mb-1" />
                                        <x-select-input wire:model.live="newForm.sisi" :error="$errors->has('newForm.sisi')" :disabled="$isFormLocked"
                                            class="w-full">
                                            <option value="">— pilih —</option>
                                            @foreach ($sisiOptions as $opt)
                                                <option value="{{ $opt }}">{{ $opt }}</option>
                                            @endforeach
                                        </x-select-input>
                                        <x-input-error :messages="$errors->get('newForm.sisi')" class="mt-1" />
                                    </div>
                                </div>
                                <div>
                                    <x-input-label value="Detail Lokasi" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.detailLokasi" :error="$errors->has('newForm.detailLokasi')"
                                        placeholder="cth: digiti III pedis (D)" :disabled="$isFormLocked"
                                        class="w-full" />
                                </div>
                                <div>
                                    <x-input-label value="Metode Penandaan" class="mb-1" />
                                    <x-text-input wire:model.live="newForm.metodePenandaan" :error="$errors->has('newForm.metodePenandaan')" :disabled="$isFormLocked"
                                        class="w-full" />
                                </div>
                                <x-toggle wire:model.live="newForm.pasienDilibatkan" :trueValue="true"
                                    :falseValue="false" label="Pasien dilibatkan saat penandaan" :disabled="$isFormLocked" />
                            </section>

                            {{-- ══ DIAGRAM PENANDAAN (klik tubuh) ══ --}}
                            <section class="pt-6 space-y-3 border-t border-hairline dark:border-gray-700" x-data="{}">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <h3 class="text-base font-semibold text-ink dark:text-gray-200">Diagram Penandaan Lokasi</h3>
                                    @if (!$isFormLocked)
                                        <div class="flex gap-2">
                                            <x-secondary-button type="button" wire:click="undoMark" class="text-sm py-1 px-2">Hapus tanda terakhir</x-secondary-button>
                                            <x-outline-button type="button" wire:click="clearMarks" wire:confirm="Bersihkan semua tanda?" class="!px-2 !py-1 text-sm">Bersihkan</x-outline-button>
                                        </div>
                                    @endif
                                </div>
                                <p class="text-sm text-muted-soft dark:text-gray-500">
                                    Klik pada panel (tubuh / kepala / tangan / kaki) untuk menandai lokasi operasi. Tanda bernomor urut per panel & tersimpan untuk dicetak.
                                </p>

                                <x-site-marking-diagram :marks="$marks" :editable="!$isFormLocked"
                                    wire-add-mark="addMark" />

                                @if (count($marks) > 0)
                                    <p class="text-sm text-center text-muted dark:text-gray-400">{{ count($marks) }} tanda ditempatkan.</p>
                                @endif
                            </section>

                            {{-- ══ TTD PERAWAT (verifikasi) ══ --}}
                            <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                                <h3 class="text-base font-semibold text-ink dark:text-gray-200">Verifikasi Perawat</h3>
                                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                    {{-- Perawat Ruangan --}}
                                    <div class="flex flex-col">
                                        <div class="mb-2 text-sm font-semibold tracking-wide text-center text-muted uppercase dark:text-gray-400">
                                            Perawat Ruangan</div>
                                        @if (!empty($signaturePerawatRuangan))
                                            <x-signature.signature-result :signature="$signaturePerawatRuangan" :date="''"
                                                :disabled="$isFormLocked" wireMethod="clearSignaturePerawatRuangan" />
                                        @elseif (!$isFormLocked)
                                            <x-signature.signature-pad wireMethod="setSignaturePerawatRuangan" />
                                        @else
                                            <p class="py-8 text-base italic text-center text-muted-soft">Belum ditandatangani.</p>
                                        @endif
                                        <div class="mt-3">
                                            <x-input-label value="Nama Perawat Ruangan *" class="mb-1" />
                                            <x-text-input wire:model.live="newForm.namaPerawatRuangan" :error="$errors->has('newForm.namaPerawatRuangan')"
                                                :disabled="$isFormLocked" class="w-full" />
                                            <x-input-error :messages="$errors->get('newForm.namaPerawatRuangan')" class="mt-1" />
                                        </div>
                                    </div>

                                    {{-- Perawat Kamar Bedah --}}
                                    <div class="flex flex-col">
                                        <div class="mb-2 text-sm font-semibold tracking-wide text-center text-muted uppercase dark:text-gray-400">
                                            Perawat Kamar Bedah</div>
                                        @if (!empty($signaturePerawatKamarBedah))
                                            <x-signature.signature-result :signature="$signaturePerawatKamarBedah" :date="''"
                                                :disabled="$isFormLocked" wireMethod="clearSignaturePerawatKamarBedah" />
                                        @elseif (!$isFormLocked)
                                            <x-signature.signature-pad wireMethod="setSignaturePerawatKamarBedah" />
                                        @else
                                            <p class="py-8 text-base italic text-center text-muted-soft">Belum ditandatangani.</p>
                                        @endif
                                        <div class="mt-3">
                                            <x-input-label value="Nama Perawat Kamar Bedah *" class="mb-1" />
                                            <x-text-input wire:model.live="newForm.namaPerawatKamarBedah" :error="$errors->has('newForm.namaPerawatKamarBedah')"
                                                :disabled="$isFormLocked" class="w-full" />
                                            <x-input-error :messages="$errors->get('newForm.namaPerawatKamarBedah')" class="mt-1" />
                                        </div>
                                    </div>
                                </div>
                            </section>
                        @endif

                        {{-- ══ TTD DOKTER OPERATOR ══ --}}
                        <section class="pt-6 space-y-4 border-t border-hairline dark:border-gray-700">
                            <h3 class="text-base font-semibold text-ink dark:text-gray-200">Tanda Tangan Dokter Operator
                            </h3>
                            <div class="max-w-md">
                                @if (empty($newForm['operatorTtd']))
                                    @if (!$isFormLocked)
                                        <div
                                            class="flex items-center justify-center p-6 border-2 border-gray-300 border-dashed rounded-xl dark:border-gray-700">
                                            <x-primary-button wire:click.prevent="setOperatorTtd"
                                                wire:loading.attr="disabled" wire:target="setOperatorTtd" class="gap-2">
                                                <span wire:loading.remove wire:target="setOperatorTtd"
                                                    class="flex items-center gap-1.5">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 012.828 2.828L11.828 15.828a4 4 0 01-2.828 1.172H7v-2a4 4 0 011.172-2.828z" />
                                                    </svg>
                                                    TTD sebagai Dokter Operator
                                                </span>
                                                <span wire:loading wire:target="setOperatorTtd">
                                                    <x-loading class="w-4 h-4" /> Menyimpan...
                                                </span>
                                            </x-primary-button>
                                        </div>
                                    @else
                                        <p class="py-8 text-base italic text-center text-muted-soft">Belum
                                            ditandatangani.</p>
                                    @endif
                                @else
                                    <div
                                        class="flex flex-col items-center justify-center p-4 border border-hairline bg-surface-soft rounded-xl dark:bg-gray-800 dark:border-gray-700">
                                        <div class="font-semibold text-center text-ink dark:text-gray-200">
                                            {{ $newForm['operatorTtd'] }}</div>
                                        @if (!empty($newForm['operatorTtdCode']))
                                            <div class="text-sm text-muted mt-0.5">Kode:
                                                {{ $newForm['operatorTtdCode'] }}</div>
                                        @endif
                                        <div class="mt-1 text-sm text-muted">{{ $newForm['operatorTtdDate'] ?? '-' }}
                                        </div>
                                        @if (!$isFormLocked)
                                            <x-outline-button type="button" wire:click.prevent="clearOperatorTtd"
                                                class="mt-2 !px-2 !py-1 text-sm">Hapus TTD</x-outline-button>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </section>

                        {{-- ══ DAFTAR TERSIMPAN ══ --}}
                        @if (count($markingList) > 0)
                            <div class="mt-6 overflow-x-auto">
                                <h3
                                    class="text-base font-semibold text-body dark:text-gray-300 pb-2 border-b border-hairline-soft dark:border-gray-800 mb-3">
                                    Daftar Penandaan Tersimpan
                                </h3>
                                <table
                                    class="min-w-full text-base border border-hairline rounded-lg dark:border-gray-700">
                                    <thead class="bg-surface-soft dark:bg-gray-800">
                                        <tr class="text-left text-muted dark:text-gray-300">
                                            <th class="px-4 py-2 border-b">Tanggal</th>
                                            <th class="px-4 py-2 border-b">Lokasi</th>
                                            <th class="px-4 py-2 border-b">Operator</th>
                                            <th class="px-4 py-2 border-b text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($markingList as $sm)
                                            <tr
                                                class="border-b border-hairline dark:border-gray-700 hover:bg-surface-soft dark:hover:bg-gray-800">
                                                <td class="px-4 py-2 text-muted dark:text-gray-400">
                                                    {{ $sm['tanggal'] ?? '-' }}</td>
                                                <td class="px-4 py-2 font-medium text-ink dark:text-gray-200">
                                                    {{ ($sm['perluPenandaan'] ?? '') === 'Ya' ? trim(($sm['regionAnatomi'] ?? '') . ' ' . ($sm['sisi'] ?? '')) : 'Tidak diperlukan' }}
                                                </td>
                                                <td class="px-4 py-2 text-muted dark:text-gray-400">
                                                    {{ $sm['operatorTtd'] ?? '-' }}</td>
                                                <td class="px-4 py-2 text-center space-x-2 whitespace-nowrap">
                                                    <x-secondary-button wire:click="cetak('{{ $sm['createdAt'] }}')"
                                                        wire:loading.attr="disabled"
                                                        wire:target="cetak('{{ $sm['createdAt'] }}')"
                                                        class="text-sm py-1 px-2">
                                                        <span wire:loading.remove
                                                            wire:target="cetak('{{ $sm['createdAt'] }}')"
                                                            class="flex items-center gap-1">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24" stroke-width="2">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                            </svg>
                                                            Cetak
                                                        </span>
                                                        <span wire:loading
                                                            wire:target="cetak('{{ $sm['createdAt'] }}')"
                                                            class="flex items-center gap-1"><x-loading />
                                                            Mencetak...</span>
                                                    </x-secondary-button>
                                                    @if (!$isFormLocked)
                                                        <x-outline-button type="button"
                                                            wire:click.prevent="hapus('{{ $sm['createdAt'] }}')"
                                                            wire:confirm="Yakin hapus penandaan ini?"
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
                            <span wire:loading.remove wire:target="addEntry">Simpan Penandaan</span>
                            <span wire:loading wire:target="addEntry"><x-loading class="w-4 h-4" />
                                Menyimpan...</span>
                        </x-primary-button>
                    @endif
                </div>
            </div>

        </div>
    </x-modal>
</div>
