<?php
// resources/views/pages/components/rekam-medis/r-i/cetak-rekam-medis/cetak-rekam-medis-open.blade.php
//
// Preview dokumen kepulangan RI (read-only) — buka via event:
//   $dispatch('cetak-rekam-medis-ri.open', { riHdrNo: <int> })
//
// Tab pertama memuat DUA dokumen kepulangan yang berpasangan:
//   - Resume Medis (dokter)
//   - Ringkasan Pemulangan Pasien (perawat/bidan)
// Masing-masing punya tombol cetak sendiri di footer.
//
// Pola mirror UGD/RJ `cetak-rekam-medis-open` — bukan editor TinyMCE,
// tapi preview formatted untuk view history dari rekam-medis-display.
//
// Cetak PDF re-use template existing `resume-medis-ri-print.blade.php`
// supaya output PDF konsisten dgn yg dari editor modal.

use Livewire\Component;
use Livewire\Attributes\On;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use EmrRITrait, MasterPasienTrait;

    public ?int $riHdrNo = null;
    public array $dataDaftarRi = [];
    public array $dataPasien = [];
    public bool $isLoading = false;

    // Navigasi antar-kunjungan (Prev/Next) — diisi rekam-medis-display saat open.
    public int $navPos = 0;
    public int $navTotal = 0;

    #[On('cetak-rekam-medis-ri.open')]
    public function open(int $riHdrNo, int $navPos = 0, int $navTotal = 0): void
    {
        $this->riHdrNo = $riHdrNo;
        $this->navPos = $navPos;
        $this->navTotal = $navTotal;
        $this->isLoading = true;
        $this->dataDaftarRi = [];
        $this->dataPasien = [];

        $dataRI = $this->findDataRI($riHdrNo);
        if (empty($dataRI)) {
            $this->isLoading = false;
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }
        $this->dataDaftarRi = $dataRI;

        $pasienData = $this->findDataMasterPasien($dataRI['regNo'] ?? '');
        if (empty($pasienData)) {
            $this->isLoading = false;
            $this->dispatch('toast', type: 'error', message: 'Data pasien tidak ditemukan.');
            return;
        }
        $this->dataPasien = $pasienData;

        $this->isLoading = false;
        $this->dispatch('open-modal', name: 'preview-rekam-medis-ri');
    }

    /** Pindah ke kunjungan sebelum/berikutnya (dihandle rekam-medis-display). */
    public function navPrev(): void
    {
        $this->dispatch('rm-display-nav', dir: 'prev');
    }

    public function navNext(): void
    {
        $this->dispatch('rm-display-nav', dir: 'next');
    }

    public function cetakPdf(): mixed
    {
        if (empty($this->dataDaftarRi) || empty($this->dataPasien)) {
            $this->dispatch('toast', type: 'error', message: 'Tidak ada data untuk dicetak.');
            return null;
        }

        $regNo = $this->dataDaftarRi['regNo'] ?? $this->riHdrNo;

        $pdf = Pdf::loadView(
            'pages.components.rekam-medis.r-i.resume-medis-ri.resume-medis-ri-print',
            [
                'dataDaftarRi' => $this->dataDaftarRi,
                'dataPasien' => $this->dataPasien,
                'resumeMedis' => (string) ($this->dataDaftarRi['resumeMedis'] ?? ''),
            ],
        )->setPaper('A4');

        return response()->streamDownload(fn() => print $pdf->output(), 'resume-medis-ri-' . $regNo . '.pdf');
    }

    /**
     * Cetak Ringkasan Pemulangan Pasien (diisi perawat/bidan) — dokumen kepulangan
     * kedua di samping Resume Medis (dokter). Pakai template print yang sama dengan
     * editornya supaya output PDF konsisten.
     */
    public function cetakPdfRingkasanPulang(): mixed
    {
        if (empty($this->dataDaftarRi) || empty($this->dataPasien)) {
            $this->dispatch('toast', type: 'error', message: 'Tidak ada data untuk dicetak.');
            return null;
        }

        $regNo = $this->dataDaftarRi['regNo'] ?? $this->riHdrNo;

        $pdf = Pdf::loadView(
            'pages.components.rekam-medis.r-i.ringkasan-pulang-ri.ringkasan-pulang-ri-print',
            [
                'dataDaftarRi' => $this->dataDaftarRi,
                'dataPasien' => $this->dataPasien,
                'ringkasanPulang' => (string) ($this->dataDaftarRi['ringkasanPulang'] ?? ''),
            ],
        )->setPaper('A4');

        return response()->streamDownload(fn() => print $pdf->output(), 'ringkasan-pulang-ri-' . $regNo . '.pdf');
    }

    /* ═══════════════════════════════════════
     | CETAK DOKUMEN (mandiri — reuse print partial modul-dokumen RI)
     | Komponen/aksi cetak asli ada di dalam modal modul-dokumen yang
     | tak selalu hadir (mis. daftar bulanan), jadi PDF digenerate di sini.
    ═══════════════════════════════════════ */
    public function cetakCaseManagerFormA(string $id): mixed
    {
        $formA = collect($this->dataDaftarRi['formMPP']['formA'] ?? [])->firstWhere('formA_id', $id);
        if (!$formA) {
            $this->dispatch('toast', type: 'error', message: 'Data Form A tidak ditemukan.');
            return null;
        }
        $identitasRs = DB::table('rsmst_identitases')->select('int_name', 'int_phone1', 'int_phone2', 'int_fax', 'int_address', 'int_city')->first();
        $dataPasien = $this->findDataMasterPasien($this->dataDaftarRi['regNo'] ?? '');
        $pdf = Pdf::loadView('livewire.cetak.cetak-form-a-print', [
            'identitasRs' => $identitasRs,
            'dataPasien' => $dataPasien,
            'dataDaftarRi' => $this->dataDaftarRi,
            'dataFormA' => $formA,
        ])->output();
        return response()->streamDownload(fn() => print $pdf, 'form-a-' . $id . '.pdf');
    }

    public function cetakCaseManagerFormB(string $id): mixed
    {
        $formB = collect($this->dataDaftarRi['formMPP']['formB'] ?? [])->firstWhere('formB_id', $id);
        if (!$formB) {
            $this->dispatch('toast', type: 'error', message: 'Data Form B tidak ditemukan.');
            return null;
        }
        $identitasRs = DB::table('rsmst_identitases')->select('int_name', 'int_phone1', 'int_phone2', 'int_fax', 'int_address', 'int_city')->first();
        $dataPasien = $this->findDataMasterPasien($this->dataDaftarRi['regNo'] ?? '');
        $pdf = Pdf::loadView('livewire.cetak.cetak-form-b-print', [
            'identitasRs' => $identitasRs,
            'dataPasien' => $dataPasien,
            'dataDaftarRi' => $this->dataDaftarRi,
            'dataFormB' => $formB,
        ])->output();
        return response()->streamDownload(fn() => print $pdf, 'form-b-' . $id . '.pdf');
    }

    /** Ambil pasien (findDataMasterPasien) + hitung umur — basis $data cetak dokumen. */
    protected function pasienUntukCetak(): array
    {
        $pasienData = $this->findDataMasterPasien($this->dataDaftarRi['regNo'] ?? '');
        $pasien = $pasienData['pasien'] ?? [];
        $this->hitungUmur($pasien);
        return $pasien;
    }

    /** Hitung umur (mutasi $pasien['thn']). */
    protected function hitungUmur(array &$pasien): void
    {
        if (!empty($pasien['tglLahir'])) {
            try {
                $pasien['thn'] = Carbon::createFromFormat('d/m/Y', $pasien['tglLahir'])
                    ->diff(Carbon::now(config('app.timezone')))
                    ->format('%y Thn, %m Bln %d Hr');
            } catch (\Throwable) {
                $pasien['thn'] = '-';
            }
        }
    }

    /** Path TTD dari myuser_code (null bila tak ada / file hilang). */
    protected function ttdPathDari(?string $code): ?string
    {
        if (empty($code)) {
            return null;
        }
        $ttdPath = DB::table('users')->where('myuser_code', $code)->value('myuser_ttd_image');
        return (!empty($ttdPath) && file_exists(public_path('storage/' . $ttdPath)))
            ? public_path('storage/' . $ttdPath)
            : null;
    }

    public function closeModal(): void
    {
        $this->dataDaftarRi = [];
        $this->dataPasien = [];
        $this->riHdrNo = null;
        $this->dispatch('close-modal', name: 'preview-rekam-medis-ri');
    }
};
?>

<div>
    <x-modal name="preview-rekam-medis-ri" size="full" height="full" focusable>

        @php
            $pasien = $dataPasien['pasien'] ?? [];
            $ri = $dataDaftarRi ?? [];

            $rm = (string) data_get($pasien, 'regNo', '');
            $nama = (string) data_get($pasien, 'regName', '');
            // MasterPasienTrait output: pasien.jenisKelamin.jenisKelaminDesc (objek struct, bukan field 'sex' flat).
            $sexLabel = (string) data_get($pasien, 'jenisKelamin.jenisKelaminDesc', '-') ?: '-';
            $tglLahir = (string) data_get($pasien, 'tglLahir', '');
            $tempatLahir = (string) data_get($pasien, 'tempatLahir', '');
            // Alamat di trait nested: pasien.identitas.alamat
            $alamat = (string) data_get($pasien, 'identitas.alamat', '');
            $rt = (string) data_get($pasien, 'identitas.rt', '');
            $rw = (string) data_get($pasien, 'identitas.rw', '');
            $desaName = (string) data_get($pasien, 'identitas.desaName', '');
            $kecamatanName = (string) data_get($pasien, 'identitas.kecamatanName', '');
            $alamatFull = trim($alamat . ($rt ? ' RT ' . $rt : '') . ($rw ? '/RW ' . $rw : '') . ($desaName ? ', ' . $desaName : '') . ($kecamatanName ? ', ' . $kecamatanName : ''));

            $umurStr = '-';
            try {
                if ($tglLahir) {
                    $birth = Carbon::createFromFormat('d/m/Y', trim($tglLahir));
                    $diff = $birth->diff(Carbon::now());
                    $umurStr = sprintf('%d Thn / %d Bln / %d Hr', $diff->y, $diff->m, $diff->d);
                }
            } catch (\Throwable) {
            }

            $bangsalDesc = (string) data_get($ri, 'bangsalDesc', '');
            $roomDesc = (string) data_get($ri, 'roomDesc', '');
            $ruangKelas = trim($bangsalDesc . ($roomDesc ? ' / ' . $roomDesc : ''));
            $tglMasuk = (string) data_get($ri, 'entryDate', '');
            $tglKeluar = (string) data_get($ri, 'exitDate', '');

            $resumeHtml = (string) data_get($ri, 'resumeMedis', '');
            $hasResume = trim(strip_tags($resumeHtml)) !== '';
            // Ringkasan Pemulangan Pasien (perawat/bidan) — dokumen kepulangan kedua.
            $ringkasanPulangHtml = (string) data_get($ri, 'ringkasanPulang', '');
            $hasRingkasanPulang = trim(strip_tags($ringkasanPulangHtml)) !== '';
        @endphp

        <div class="flex flex-col min-h-[calc(100vh-4rem)]" wire:key="preview-rekam-medis-ri-{{ $riHdrNo }}"
            x-data="{ tab: 'resume' }">

            {{-- ── HEADER ── --}}
            <div class="relative px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image:radial-gradient(currentColor 1px,transparent 1px);background-size:14px 14px">
                </div>
                <div class="relative flex items-start justify-between gap-4">
                    {{-- Identitas pasien jadi header (pola EMR — sama dgn RJ/UGD) --}}
                    <div class="flex-1 min-w-0">
                        <livewire:pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo="(string) $riHdrNo"
                            wire:key="preview-rm-ri-display-pasien-{{ $riHdrNo }}" />
                    </div>
                    <x-secondary-button type="button" wire:click="closeModal" class="!p-2 shrink-0">
                        <span class="sr-only">Tutup</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-secondary-button>
                </div>
            </div>

            {{-- ── TAB NAV ── --}}
            <div class="px-6 border-b bg-canvas border-hairline shrink-0 overflow-x-auto dark:bg-gray-900 dark:border-gray-700">
                <nav class="flex gap-1 -mb-px">
                    <button type="button" x-on:click="tab = 'resume'"
                        :class="tab === 'resume' ? 'border-brand-green text-brand-green' :
                            'border-transparent text-muted hover:text-ink'"
                        class="px-4 py-3 text-base font-semibold transition-colors border-b-2 whitespace-nowrap">Resume Medis &amp; Ringkasan Pemulangan Pasien</button>
                    <button type="button" x-on:click="tab = 'dokumen'"
                        :class="tab === 'dokumen' ? 'border-brand-green text-brand-green' :
                            'border-transparent text-muted hover:text-ink'"
                        class="px-4 py-3 text-base font-semibold transition-colors border-b-2 whitespace-nowrap">Modul Dokumen</button>
                    <button type="button" x-on:click="tab = 'penunjang'"
                        :class="tab === 'penunjang' ? 'border-brand-green text-brand-green' :
                            'border-transparent text-muted hover:text-ink'"
                        class="px-4 py-3 text-base font-semibold transition-colors border-b-2 whitespace-nowrap">Hasil Penunjang</button>
                </nav>
            </div>

            {{-- ── BODY (scroll) ── --}}
            <div class="flex-1 overflow-y-auto bg-surface-soft/70 dark:bg-gray-950/20">

                {{-- ════ TAB: RESUME ════ --}}
                <div x-show="tab === 'resume'" class="px-6 py-5">

                {{-- RESUME MEDIS --}}
                <x-border-form title="Resume Medis" class="mb-4">
                    @if ($hasResume)
                        <style>
                            /* Tailwind text-base ≈ 16px + leading-relaxed (1.625) */
                            .resume-medis-preview table {
                                border-collapse: collapse;
                                width: 100%;
                            }

                            .resume-medis-preview table th,
                            .resume-medis-preview table td {
                                border: 1px solid #d1d5db;
                                padding: 8px 10px;
                                vertical-align: top;
                            }

                            .resume-medis-preview p {
                                margin: 0 0 6px 0;
                            }

                            .resume-medis-preview ol,
                            .resume-medis-preview ul {
                                padding-left: 24px;
                                margin: 4px 0;
                            }

                            .resume-medis-preview strong,
                            .resume-medis-preview b {
                                font-weight: 600;
                            }
                        </style>
                        <div class="resume-medis-preview text-base leading-relaxed text-ink dark:text-gray-200"
                            style="overflow-x:auto;">
                            {!! $resumeHtml !!}
                        </div>
                    @else
                        <p class="text-sm italic text-muted-soft py-4 text-center">
                            Resume Medis belum dibuat untuk kunjungan ini.
                        </p>
                    @endif
                </x-border-form>

                {{-- RINGKASAN PEMULANGAN PASIEN (perawat/bidan) — dokumen kepulangan kedua,
                     ditaruh di tab yang sama karena satu konteks dgn Resume Medis (dokter).
                     Gaya tabel meniru .resume-medis-preview supaya konsisten. --}}
                <x-border-form title="Ringkasan Pemulangan Pasien" class="mb-4">
                    @if ($hasRingkasanPulang)
                        <div class="resume-medis-preview text-base leading-relaxed text-ink dark:text-gray-200"
                            style="overflow-x:auto;">
                            {!! $ringkasanPulangHtml !!}
                        </div>
                    @else
                        <p class="text-sm italic text-muted-soft py-4 text-center">
                            Ringkasan Pemulangan Pasien belum dibuat untuk kunjungan ini.
                        </p>
                    @endif
                </x-border-form>

                </div>{{-- /tab resume --}}

                {{-- ════ TAB: MODUL DOKUMEN (view-only — data + cetak) ════ --}}
                @php
                    $printSvg = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>';
                    // Label tujuan edukasi terintegrasi (cerminan $tujuanList di komponen edukasi-terintegrasi)
                    $tujuanLabels = [
                        'penyakit' => 'Pemahaman penyakit/diagnosis',
                        'obat' => 'Penggunaan obat yang aman',
                        'nutrisi' => 'Nutrisi & diet',
                        'aktivitas' => 'Aktivitas & latihan',
                        'perawatanRumah' => 'Perawatan di rumah',
                        'pencegahan' => 'Pencegahan komplikasi',
                        'lainnya' => 'Lainnya',
                    ];
                    // Chip tanggal (hijau brand) — kosong → tidak tampil
                    $dateChip = fn($d) => filled($d)
                        ? '<span class="inline-flex items-center px-2 py-0.5 text-xs font-semibold rounded-full bg-brand-green/10 text-brand-green dark:bg-brand-green/20 dark:text-brand-lime">' . e($d) . '</span>'
                        : '';
                    // Pill empty-state netral + ikon tanya
                    $emptyPill = fn($txt) => '<div class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm rounded-lg text-muted-soft bg-surface-soft dark:bg-gray-800/60"><svg class="w-4 h-4 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" /></svg>' . e($txt) . '</div>';
                @endphp
                <div x-show="tab === 'dokumen'" x-cloak class="px-6 py-5 space-y-5">

                    {{-- ── General Consent — viewer (Lihat + Cetak dalam modal) ── --}}
                    <livewire:pages::components.rekam-medis.r-i.dokumen-view.general-consent-view-ri :riHdrNo="(string) $riHdrNo"
                        :consent="$ri['generalConsentPasienRI'] ?? []" wire:key="rm-view-gc-ri-{{ $riHdrNo }}" />

                    {{-- ── Inform Consent — viewer (Lihat + Cetak dalam modal) ── --}}
                    <livewire:pages::components.rekam-medis.r-i.dokumen-view.inform-consent-view-ri :riHdrNo="(string) $riHdrNo"
                        :entries="$ri['informConsentPasienRI'] ?? []" wire:key="rm-view-ic-ri-{{ $riHdrNo }}" />

                    {{-- ── Case Manager (MPP) — pola tampilan sama dgn tab MPP di CPPT ── --}}
                    {{-- Form A dikelompokkan; Form B miliknya jadi accordion tabel. --}}
                    @php
                        // Newest-first (samakan CPPT: array_reverse).
                        $formAList = collect($ri['formMPP']['formA'] ?? [])->filter(fn($x) => filled(data_get($x, 'formA_id')))->reverse()->values();
                        $mppColors = [
                            'blue' => ['wrap' => 'border-l-4 border-blue-500 bg-blue-50/40 dark:bg-blue-900/10', 'text' => 'text-blue-700 dark:text-blue-400'],
                            'amber' => ['wrap' => 'border-l-4 border-amber-500 bg-amber-50/40 dark:bg-amber-900/10', 'text' => 'text-amber-700 dark:text-amber-400'],
                            'rose' => ['wrap' => 'border-l-4 border-rose-500 bg-rose-50/40 dark:bg-rose-900/10', 'text' => 'text-error dark:text-rose-400'],
                        ];
                    @endphp
                    <x-border-form title="Manajer Pelayanan Pasien (Case Manager)">
                        <div class="space-y-3">
                            @forelse ($formAList as $entriFormA)
                                <div wire:key="rm-mpp-fa-{{ data_get($entriFormA, 'formA_id') }}"
                                    class="border rounded-lg overflow-hidden bg-canvas dark:bg-gray-800 border-hairline dark:border-gray-700">
                                    {{-- Header Form A --}}
                                    <div class="flex flex-wrap items-center gap-2 px-4 py-2.5 bg-surface-soft dark:bg-gray-700/60 border-b border-hairline-soft dark:border-gray-700 text-sm">
                                        <span class="px-2 py-0.5 rounded-full text-sm font-bold bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300">MPP</span>
                                        <span class="px-2 py-0.5 rounded-full text-sm font-medium bg-surface-soft text-muted border border-hairline dark:border-gray-600">Skrining Awal (Form A)</span>
                                        <span class="font-semibold text-body dark:text-gray-200">{{ data_get($entriFormA, 'tandaTanganPetugas.petugasName') ?: '-' }}</span>
                                        {!! $dateChip(data_get($entriFormA, 'tanggal')) !!}
                                        <x-secondary-button type="button" class="gap-1.5 shrink-0 ml-auto !py-1 !px-2 text-sm"
                                            wire:click="cetakCaseManagerFormA('{{ data_get($entriFormA, 'formA_id') }}')" wire:loading.attr="disabled">{!! $printSvg !!} Cetak</x-secondary-button>
                                    </div>
                                    {{-- Field Form A (box berwarna) --}}
                                    <div class="px-4 py-3 space-y-3">
                                        @php
                                            $faFields = [
                                                ['label' => 'Identifikasi Kasus', 'color' => 'blue', 'val' => (string) data_get($entriFormA, 'indentifikasiKasus', '')],
                                                ['label' => 'Assessment', 'color' => 'amber', 'val' => (string) data_get($entriFormA, 'assessment', '')],
                                                ['label' => 'Perencanaan', 'color' => 'rose', 'val' => (string) data_get($entriFormA, 'perencanaan', '')],
                                            ];
                                        @endphp
                                        <div class="grid grid-cols-1 gap-3 text-sm md:grid-cols-3">
                                            @foreach ($faFields as $f)
                                                @if (trim($f['val']) !== '')
                                                    @php $s = $mppColors[$f['color']]; @endphp
                                                    <div class="{{ $s['wrap'] }} pl-3 py-1 rounded-r-md">
                                                        <span class="font-bold {{ $s['text'] }}">{{ $f['label'] }}</span>
                                                        <div class="text-body dark:text-gray-300 whitespace-pre-wrap leading-snug">{{ $f['val'] }}</div>
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>

                                        {{-- Form B milik Form A ini — accordion tabel --}}
                                        @php
                                            $formBList = collect($ri['formMPP']['formB'] ?? [])->where('formA_id', data_get($entriFormA, 'formA_id'))->values();
                                        @endphp
                                        @if ($formBList->count() > 0)
                                            <div x-data="{ openB: false }"
                                                class="rounded-lg border border-hairline dark:border-gray-700 overflow-hidden">
                                                <button type="button" x-on:click="openB = !openB"
                                                    class="flex w-full items-center justify-between gap-2 px-3 py-2 text-sm font-semibold text-body dark:text-gray-200 bg-surface-soft dark:bg-gray-700/60 hover:bg-surface dark:hover:bg-gray-700">
                                                    <span class="flex items-center gap-2">
                                                        <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-brand/10 text-brand">Form B</span>
                                                        Pelaksanaan MPP
                                                        <span class="font-normal text-muted-soft">({{ $formBList->count() }})</span>
                                                    </span>
                                                    <svg class="w-4 h-4 shrink-0 transition-transform" x-bind:class="openB && 'rotate-180'"
                                                        fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                                    </svg>
                                                </button>
                                                <div x-show="openB" x-collapse x-cloak class="overflow-x-auto">
                                                    <table class="w-full text-sm text-left border-t border-hairline-soft dark:border-gray-700">
                                                        <thead class="bg-surface-soft/60 dark:bg-gray-800 text-muted dark:text-gray-300">
                                                            <tr>
                                                                <th class="px-3 py-2 whitespace-nowrap">Tanggal</th>
                                                                <th class="px-3 py-2">Petugas</th>
                                                                <th class="px-3 py-2">Pelaksanaan & Monitoring</th>
                                                                <th class="px-3 py-2">Advokasi / Kolaborasi</th>
                                                                <th class="px-3 py-2">Terminasi</th>
                                                                <th class="px-3 py-2 text-center whitespace-nowrap">Aksi</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="divide-y divide-hairline-soft dark:divide-gray-700">
                                                            @foreach ($formBList as $entriFormB)
                                                                <tr class="align-top bg-canvas dark:bg-gray-800">
                                                                    <td class="px-3 py-2 whitespace-nowrap font-mono text-muted-soft">{{ data_get($entriFormB, 'tanggal') ?: '-' }}</td>
                                                                    <td class="px-3 py-2 whitespace-nowrap text-body dark:text-gray-200">{{ data_get($entriFormB, 'tandaTanganPetugas.petugasName') ?: '-' }}</td>
                                                                    <td class="px-3 py-2 whitespace-pre-wrap text-body dark:text-gray-300">{{ trim((string) data_get($entriFormB, 'pelaksanaanMonitoring', '')) ?: '-' }}</td>
                                                                    <td class="px-3 py-2 whitespace-pre-wrap text-body dark:text-gray-300">{{ trim((string) data_get($entriFormB, 'advokasiKolaborasi', '')) ?: '-' }}</td>
                                                                    <td class="px-3 py-2 whitespace-pre-wrap text-body dark:text-gray-300">{{ trim((string) data_get($entriFormB, 'terminasi', '')) ?: '-' }}</td>
                                                                    <td class="px-3 py-2 text-center whitespace-nowrap">
                                                                        <x-secondary-button type="button" class="gap-1.5 !py-1 !px-2 text-sm"
                                                                            wire:click="cetakCaseManagerFormB('{{ data_get($entriFormB, 'formB_id') }}')" wire:loading.attr="disabled">{!! $printSvg !!} Cetak</x-secondary-button>
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                {!! $emptyPill('Belum ada catatan MPP') !!}
                            @endforelse
                        </div>
                    </x-border-form>

                    {{-- ── Edukasi Pasien — viewer (Lihat + Cetak dalam modal) ── --}}
                    <livewire:pages::components.rekam-medis.r-i.dokumen-view.edukasi-pasien-view-ri :riHdrNo="(string) $riHdrNo"
                        :entries="$ri['edukasiPasien'] ?? []" wire:key="rm-view-edukasi-pasien-{{ $riHdrNo }}" />

                    {{-- ── Edukasi Terintegrasi — viewer (Lihat + Cetak dalam modal) ── --}}
                    <livewire:pages::components.rekam-medis.r-i.dokumen-view.edukasi-terintegrasi-view-ri :riHdrNo="(string) $riHdrNo"
                        :entries="$ri['edukasiPasienTerintegrasi'] ?? []" wire:key="rm-view-edukasi-terintegrasi-{{ $riHdrNo }}" />

                    {{-- ── Form Pindah Antar Ruang — viewer (Lihat + Cetak dalam modal) ── --}}
                    <livewire:pages::components.rekam-medis.r-i.dokumen-view.form-pindah-view-ri :riHdrNo="(string) $riHdrNo"
                        :entries="$ri['formPindahAntarRuangRI'] ?? []" wire:key="rm-view-form-pindah-{{ $riHdrNo }}" />

                    {{-- ════ Dokumen Bedah & Anestesi (PAB) — viewer per-modul (Lihat + Cetak dalam modal) ════ --}}
                    <div class="pt-2 mt-2 text-xs font-semibold tracking-wide uppercase text-muted-soft border-t border-hairline dark:border-gray-700">
                        Dokumen Bedah &amp; Anestesi (PAB)
                    </div>

                    <livewire:pages::components.rekam-medis.r-i.dokumen-view.pengkajian-pre-op-view-ri :riHdrNo="(string) $riHdrNo"
                        :entries="$ri['pengkajianPreOpRI'] ?? []" wire:key="rm-view-pengkajian-pre-op-{{ $riHdrNo }}" />

                    <livewire:pages::components.rekam-medis.r-i.dokumen-view.pra-anestesi-view-ri :riHdrNo="(string) $riHdrNo"
                        :entries="$ri['praAnestesiRI'] ?? []" wire:key="rm-view-pra-anestesi-{{ $riHdrNo }}" />

                    <livewire:pages::components.rekam-medis.r-i.dokumen-view.site-marking-view-ri :riHdrNo="(string) $riHdrNo"
                        :entries="$ri['siteMarkingRI'] ?? []" wire:key="rm-view-site-marking-{{ $riHdrNo }}" />

                    <livewire:pages::components.rekam-medis.r-i.dokumen-view.pra-induksi-view-ri :riHdrNo="(string) $riHdrNo"
                        :entries="$ri['praInduksiRI'] ?? []" wire:key="rm-view-pra-induksi-{{ $riHdrNo }}" />

                    <livewire:pages::components.rekam-medis.r-i.dokumen-view.laporan-operasi-view-ri :riHdrNo="(string) $riHdrNo"
                        :entries="$ri['laporanOperasiRI'] ?? []" wire:key="rm-view-laporan-operasi-{{ $riHdrNo }}" />

                    <livewire:pages::components.rekam-medis.r-i.dokumen-view.laporan-anestesi-view-ri :riHdrNo="(string) $riHdrNo"
                        :entries="$ri['laporanAnestesiRI'] ?? []" wire:key="rm-view-laporan-anestesi-{{ $riHdrNo }}" />

                    <livewire:pages::components.rekam-medis.r-i.dokumen-view.pasca-anestesi-view-ri :riHdrNo="(string) $riHdrNo"
                        :entries="$ri['pascaAnestesiRI'] ?? []" wire:key="rm-view-pasca-anestesi-{{ $riHdrNo }}" />

                    <livewire:pages::components.rekam-medis.r-i.dokumen-view.instruksi-pasca-bedah-view-ri :riHdrNo="(string) $riHdrNo"
                        :entries="$ri['instruksiPascaBedahRI'] ?? []" wire:key="rm-view-instruksi-pasca-bedah-{{ $riHdrNo }}" />

                    <livewire:pages::components.rekam-medis.r-i.dokumen-view.penundaan-pelayanan-view-ri :riHdrNo="(string) $riHdrNo"
                        :entries="$ri['penundaanPelayananRI'] ?? []" wire:key="rm-view-penundaan-pelayanan-{{ $riHdrNo }}" />

                    <livewire:pages::components.rekam-medis.r-i.dokumen-view.permintaan-kerohanian-view-ri :riHdrNo="(string) $riHdrNo"
                        :entries="$ri['permintaanKerohanianRI'] ?? []" wire:key="rm-view-permintaan-kerohanian-{{ $riHdrNo }}" />
                </div>

                {{-- ════ TAB: HASIL PENUNJANG (lab / radiologi / upload — view-only) ════ --}}
                @php $regNoPenunjang = (string) ($ri['regNo'] ?? $rm ?? ''); @endphp
                <div x-show="tab === 'penunjang'" x-cloak class="px-6 py-5" x-data="{ sub: 'laboratorium' }">
                    {{-- Sub-tab nav --}}
                    <div class="flex flex-wrap gap-1 mb-4 border-b border-hairline dark:border-gray-700">
                        <button type="button" x-on:click="sub = 'laboratorium'"
                            :class="sub === 'laboratorium' ? 'border-brand-green text-brand-green' : 'border-transparent text-muted hover:text-ink'"
                            class="px-4 py-2 -mb-px text-sm font-semibold transition-colors border-b-2">Laboratorium</button>
                        <button type="button" x-on:click="sub = 'radiologi'"
                            :class="sub === 'radiologi' ? 'border-brand-green text-brand-green' : 'border-transparent text-muted hover:text-ink'"
                            class="px-4 py-2 -mb-px text-sm font-semibold transition-colors border-b-2">Radiologi</button>
                        <button type="button" x-on:click="sub = 'upload'"
                            :class="sub === 'upload' ? 'border-brand-green text-brand-green' : 'border-transparent text-muted hover:text-ink'"
                            class="px-4 py-2 -mb-px text-sm font-semibold transition-colors border-b-2">Upload Penunjang</button>
                    </div>

                    <div x-show="sub === 'laboratorium'" x-cloak class="space-y-4">
                        <livewire:pages::components.rekam-medis.penunjang.laboratorium-display.laboratorium-display
                            :regNo="$regNoPenunjang" wire:key="rm-ri-penunjang-lab-{{ $regNoPenunjang }}" />
                        <livewire:pages::components.rekam-medis.penunjang.lab-luar-display.lab-luar-display
                            :regNo="$regNoPenunjang" wire:key="rm-ri-penunjang-lab-luar-{{ $regNoPenunjang }}" />
                    </div>

                    <div x-show="sub === 'radiologi'" x-cloak>
                        <livewire:pages::components.rekam-medis.penunjang.radiologi-display.radiologi-display
                            :regNo="$regNoPenunjang" wire:key="rm-ri-penunjang-rad-{{ $regNoPenunjang }}" />
                    </div>

                    <div x-show="sub === 'upload'" x-cloak>
                        <livewire:pages::components.rekam-medis.penunjang.upload-penunjang-display.upload-penunjang-display
                            :regNo="$regNoPenunjang" wire:key="rm-ri-penunjang-upload-{{ $regNoPenunjang }}" />
                    </div>
                </div>

            </div>

            {{-- ── FOOTER ── --}}
            <div class="sticky bottom-0 z-10 px-6 py-3 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-2">
                    <x-rm.record-nav :pos="$navPos" :total="$navTotal" />
                    <div class="flex items-center gap-2">
                    <x-secondary-button type="button" wire:click="closeModal">Tutup</x-secondary-button>
                    {{-- Cetak Ringkasan Pulang pakai x-info-button, BUKAN primary: standar
                         membatasi satu x-primary-button per modal (aksi utama = Cetak Resume). --}}
                    @if ($hasRingkasanPulang)
                        <x-info-button type="button" wire:click="cetakPdfRingkasanPulang" wire:loading.attr="disabled"
                            wire:target="cetakPdfRingkasanPulang">
                            <span wire:loading.remove wire:target="cetakPdfRingkasanPulang" class="inline-flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                </svg>
                                Cetak Ringkasan Pulang
                            </span>
                            <span wire:loading wire:target="cetakPdfRingkasanPulang" class="inline-flex items-center gap-1">
                                <x-loading /> Memuat...
                            </span>
                        </x-info-button>
                    @endif
                    @if ($hasResume)
                        <x-primary-button type="button" wire:click="cetakPdf" wire:loading.attr="disabled" wire:target="cetakPdf">
                            <span wire:loading.remove wire:target="cetakPdf" class="inline-flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                </svg>
                                Cetak PDF
                            </span>
                            <span wire:loading wire:target="cetakPdf" class="inline-flex items-center gap-1">
                                <x-loading /> Memuat...
                            </span>
                        </x-primary-button>
                    @endif
                    </div>
                </div>
            </div>

        </div>

    </x-modal>
</div>
