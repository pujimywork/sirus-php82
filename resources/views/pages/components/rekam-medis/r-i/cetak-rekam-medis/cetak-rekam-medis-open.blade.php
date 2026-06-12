<?php
// resources/views/pages/components/rekam-medis/r-i/cetak-rekam-medis/cetak-rekam-medis-open.blade.php
//
// Preview Resume Medis RI (read-only) — buka via event:
//   $dispatch('cetak-rekam-medis-ri.open', { riHdrNo: <int> })
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

    #[On('cetak-rekam-medis-ri.open')]
    public function open(int $riHdrNo): void
    {
        $this->riHdrNo = $riHdrNo;
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

    /* ═══════════════════════════════════════
     | CETAK DOKUMEN (mandiri — reuse print partial modul-dokumen RI)
     | Komponen/aksi cetak asli ada di dalam modal modul-dokumen yang
     | tak selalu hadir (mis. daftar bulanan), jadi PDF digenerate di sini.
    ═══════════════════════════════════════ */
    public function cetakGeneralConsentRi(): mixed
    {
        $consent = $this->dataDaftarRi['generalConsentPasienRI'] ?? null;
        if (!$consent || !is_array($consent) || empty($consent['signature'])) {
            $this->dispatch('toast', type: 'error', message: 'Data General Consent belum tersedia.');
            return null;
        }
        $pasien = $this->pasienUntukCetak();
        $identitasRs = DB::table('rsmst_identitases')->select('int_name', 'int_phone1', 'int_phone2', 'int_fax', 'int_address', 'int_city')->first();
        $ttdPetugasPath = $this->ttdPathDari($consent['petugasPemeriksaCode'] ?? null);

        $data = array_merge($pasien, [
            'dataRi' => $this->dataDaftarRi,
            'consent' => $consent,
            'identitasRs' => $identitasRs,
            'ttdPetugasPath' => $ttdPetugasPath,
            'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
        ]);
        set_time_limit(300);
        $pdf = Pdf::loadView('pages.components.modul-dokumen.r-i.general-consent.cetak-general-consent-ri-print', ['data' => $data])->setPaper('A4');
        return response()->streamDownload(fn() => print $pdf->output(), 'general-consent-ri-' . ($pasien['regNo'] ?? $this->riHdrNo) . '.pdf');
    }

    public function cetakInformConsentRi(string $signatureDate): mixed
    {
        $consent = collect($this->dataDaftarRi['informConsentPasienRI'] ?? [])->firstWhere('signatureDate', $signatureDate);
        if (!$consent) {
            $this->dispatch('toast', type: 'error', message: 'Data consent tidak ditemukan.');
            return null;
        }
        $pasien = $this->pasienUntukCetak();
        $identitasRs = DB::table('rsmst_identitases')->select('int_name', 'int_phone1', 'int_phone2', 'int_fax', 'int_address', 'int_city')->first();
        $ttdDokterPath = $this->ttdPathDari($consent['dokterCode'] ?? null);

        $dokterTindakanName = null;
        if (!empty($consent['petugasPemeriksaCode'])) {
            $userRow = DB::table('users')->where('myuser_code', $consent['petugasPemeriksaCode'])->first(['myuser_name']);
            $dokterTindakanName = $userRow->myuser_name ?? null;
            if (empty($dokterTindakanName)) {
                $dokterTindakanName = DB::table('rsmst_doctors')->where('dr_id', $consent['petugasPemeriksaCode'])->value('dr_name');
            }
        }

        $data = array_merge($pasien, [
            'dataRi' => $this->dataDaftarRi,
            'consent' => $consent,
            'identitasRs' => $identitasRs,
            'ttdDokterPath' => $ttdDokterPath,
            'dokterTindakanName' => $dokterTindakanName ?? ($consent['petugasPemeriksa'] ?? null),
            'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
        ]);
        set_time_limit(300);
        $pdf = Pdf::loadView('pages.components.modul-dokumen.r-i.inform-consent.cetak-inform-consent-ri-print', ['data' => $data])->setPaper('A4');
        return response()->streamDownload(fn() => print $pdf->output(), 'inform-consent-ri-' . ($pasien['regNo'] ?? $this->riHdrNo) . '.pdf');
    }

    public function cetakEdukasiPasienRi(int $index): mixed
    {
        $entry = ($this->dataDaftarRi['edukasiPasien'] ?? [])[$index] ?? null;
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Data edukasi tidak ditemukan.');
            return null;
        }
        $pasien = $this->pasienUntukCetak();
        $identitasRs = DB::table('rsmst_identitases')->select('int_name', 'int_phone1', 'int_phone2', 'int_fax', 'int_address', 'int_city')->first();
        $ttdPetugasPath = $this->ttdPathDari($entry['petugasEdukasiCode'] ?? null);

        $data = array_merge($pasien, [
            'dataRi' => $this->dataDaftarRi,
            'entry' => $entry,
            'identitasRs' => $identitasRs,
            'ttdPetugasPath' => $ttdPetugasPath,
            'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
        ]);
        set_time_limit(300);
        $pdf = Pdf::loadView('pages.components.modul-dokumen.r-i.edukasi-pasien.cetak-edukasi-pasien-ri-print', ['data' => $data])->setPaper('A4');
        return response()->streamDownload(fn() => print $pdf->output(), 'edukasi-pasien-ri-' . ($pasien['regNo'] ?? $this->riHdrNo) . '.pdf');
    }

    public function cetakEdukasiTerintegrasiRi(string $id): mixed
    {
        $entry = collect($this->dataDaftarRi['edukasiPasienTerintegrasi'] ?? [])->firstWhere('id', $id);
        if (!$entry) {
            $this->dispatch('toast', type: 'error', message: 'Data edukasi tidak ditemukan.');
            return null;
        }
        $pasien = $this->pasienUntukCetak();
        $identitasRs = DB::table('rsmst_identitases')->select('int_name', 'int_phone1', 'int_phone2', 'int_fax', 'int_address', 'int_city')->first();
        $petugasCode = $entry['form']['pemberiInformasi']['petugasCode'] ?? ($entry['created_by']['code'] ?? null);
        $ttdPetugasPath = $this->ttdPathDari($petugasCode);

        $data = array_merge($pasien, [
            'dataRi' => $this->dataDaftarRi,
            'entry' => $entry,
            'identitasRs' => $identitasRs,
            'ttdPetugasPath' => $ttdPetugasPath,
            'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
        ]);
        set_time_limit(300);
        $pdf = Pdf::loadView('pages.components.modul-dokumen.r-i.edukasi-terintegrasi.cetak-edukasi-terintegrasi-ri-print', ['data' => $data])->setPaper('A4');
        return response()->streamDownload(fn() => print $pdf->output(), 'edukasi-terintegrasi-ri-' . ($pasien['regNo'] ?? $this->riHdrNo) . '.pdf');
    }

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

    public function cetakFormPindahRi(string $tglPindah): mixed
    {
        $pindah = collect($this->dataDaftarRi['formPindahAntarRuangRI'] ?? [])->firstWhere('tglPindah', $tglPindah);
        if (empty($pindah)) {
            $this->dispatch('toast', type: 'error', message: 'Catatan pindah tidak ditemukan.');
            return null;
        }
        $pasien = $this->pasienUntukCetak();
        $identitasRs = DB::table('rsmst_identitases')->select('int_name', 'int_phone1', 'int_address', 'int_city')->first();

        $data = array_merge($pasien, [
            'pindah' => $pindah,
            'dataRI' => $this->dataDaftarRi,
            'identitasRs' => $identitasRs,
            'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
        ]);
        set_time_limit(300);
        $pdf = Pdf::loadView('pages.components.modul-dokumen.r-i.form-pindah-antar-ruang-ri.cetak-form-pindah-antar-ruang-ri-print', ['data' => $data])->setPaper('A4');
        return response()->streamDownload(fn() => print $pdf->output(), 'form-pindah-ri-' . ($pasien['regNo'] ?? $this->riHdrNo) . '.pdf');
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
            <div class="px-6 border-b bg-canvas border-hairline shrink-0 dark:bg-gray-900 dark:border-gray-700">
                <nav class="flex gap-1 -mb-px">
                    <button type="button" x-on:click="tab = 'resume'"
                        :class="tab === 'resume' ? 'border-brand-green text-brand-green' :
                            'border-transparent text-muted hover:text-ink'"
                        class="px-4 py-3 text-base font-semibold transition-colors border-b-2">Resume Medis</button>
                    <button type="button" x-on:click="tab = 'dokumen'"
                        :class="tab === 'dokumen' ? 'border-brand-green text-brand-green' :
                            'border-transparent text-muted hover:text-ink'"
                        class="px-4 py-3 text-base font-semibold transition-colors border-b-2">Modul Dokumen</button>
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

                    {{-- ── General Consent ── --}}
                    @php $gc = $ri['generalConsentPasienRI'] ?? []; @endphp
                    <x-border-form title="General Consent">
                        @if (filled($gc))
                            <div class="space-y-2 text-base">
                                <div class="flex gap-3 pb-2 border-b border-hairline-soft dark:border-gray-800">
                                    <span class="text-right w-44 shrink-0 text-muted">Petugas Pemeriksa :</span>
                                    <span class="font-semibold text-ink dark:text-gray-200">{{ data_get($gc, 'petugasPemeriksa') ?: '-' }}</span>
                                </div>
                                <div class="flex gap-3 pb-2 border-b border-hairline-soft dark:border-gray-800">
                                    <span class="text-right w-44 shrink-0 text-muted">Wali / Penanggung Jawab :</span>
                                    <span class="font-semibold text-ink dark:text-gray-200">{{ data_get($gc, 'wali') ?: '-' }}
                                        @if (filled(data_get($gc, 'waliHubungan')))
                                            <span class="font-normal text-muted-soft">({{ data_get($gc, 'waliHubungan') }})</span>
                                        @endif
                                    </span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-right w-44 shrink-0 text-muted">Tanda Tangan :</span>
                                    <span class="inline-flex items-center gap-2">
                                        @if (filled(data_get($gc, 'signature')))
                                            <x-badge variant="success">Sudah ditandatangani</x-badge>
                                        @else
                                            <x-badge variant="gray">Belum</x-badge>
                                        @endif
                                        {!! $dateChip(data_get($gc, 'signatureDate')) !!}
                                    </span>
                                </div>
                            </div>
                            <div class="pt-3 mt-1 border-t border-hairline dark:border-gray-700">
                                <x-secondary-button type="button" class="gap-1.5"
                                    wire:click="cetakGeneralConsentRi" wire:loading.attr="disabled">{!! $printSvg !!} Cetak</x-secondary-button>
                            </div>
                        @else
                            {!! $emptyPill('Belum diisi') !!}
                        @endif
                    </x-border-form>

                    {{-- ── Inform Consent (per tindakan) ── --}}
                    @php $icList = collect($ri['informConsentPasienRI'] ?? [])->filter(fn($x) => filled(data_get($x, 'signatureDate')) || filled(data_get($x, 'tindakan'))); @endphp
                    <x-border-form title="Inform Consent">
                        @forelse ($icList as $ic)
                            <div class="flex items-center justify-between gap-3 py-2.5 border-b border-hairline-soft last:border-0 dark:border-gray-800">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <span class="text-base font-semibold text-ink dark:text-gray-200">{{ data_get($ic, 'tindakan') ?: '(Tanpa nama tindakan)' }}</span>
                                        {!! $dateChip(data_get($ic, 'signatureDate')) !!}
                                    </div>
                                    <div class="mt-0.5 text-sm text-muted">
                                        @if (filled(data_get($ic, 'diagnosa')))
                                            <span class="text-body">Diagnosa:</span> {{ data_get($ic, 'diagnosa') }} ·
                                        @endif
                                        <span class="text-body">Dokter:</span> {{ data_get($ic, 'dokter') ?: '-' }}
                                    </div>
                                </div>
                                @if (filled(data_get($ic, 'signatureDate')))
                                    <x-secondary-button type="button" class="gap-1.5 shrink-0"
                                        wire:click="cetakInformConsentRi('{{ data_get($ic, 'signatureDate') }}')" wire:loading.attr="disabled">{!! $printSvg !!} Cetak</x-secondary-button>
                                @endif
                            </div>
                        @empty
                            {!! $emptyPill('Belum diisi') !!}
                        @endforelse
                    </x-border-form>

                    {{-- ── Case Manager (MPP) — Form A & B ── --}}
                    @php
                        $formAList = collect($ri['formMPP']['formA'] ?? [])->filter(fn($x) => filled(data_get($x, 'formA_id')));
                        $formBList = collect($ri['formMPP']['formB'] ?? [])->filter(fn($x) => filled(data_get($x, 'formB_id')));
                    @endphp
                    <x-border-form title="Manajer Pelayanan Pasien (Case Manager)">
                        <div class="mb-2 text-xs font-semibold tracking-wide uppercase text-brand-green dark:text-brand-lime">Form A — Evaluasi Awal</div>
                        @forelse ($formAList as $fa)
                            <div class="flex items-center justify-between gap-3 py-2.5 border-b border-hairline-soft last:border-0 dark:border-gray-800">
                                <span class="inline-flex items-center gap-2 text-base text-ink dark:text-gray-200">Form A {!! $dateChip(data_get($fa, 'tanggal')) !!}</span>
                                <x-secondary-button type="button" class="gap-1.5 shrink-0"
                                    wire:click="cetakCaseManagerFormA('{{ data_get($fa, 'formA_id') }}')" wire:loading.attr="disabled">{!! $printSvg !!} Cetak</x-secondary-button>
                            </div>
                        @empty
                            <div class="mb-2">{!! $emptyPill('Belum ada Form A') !!}</div>
                        @endforelse

                        <div class="mt-4 mb-2 text-xs font-semibold tracking-wide uppercase text-brand-green dark:text-brand-lime">Form B — Catatan Implementasi</div>
                        @forelse ($formBList as $fb)
                            <div class="flex items-center justify-between gap-3 py-2.5 border-b border-hairline-soft last:border-0 dark:border-gray-800">
                                <span class="inline-flex items-center gap-2 text-base text-ink dark:text-gray-200">Form B {!! $dateChip(data_get($fb, 'tanggal')) !!}</span>
                                <x-secondary-button type="button" class="gap-1.5 shrink-0"
                                    wire:click="cetakCaseManagerFormB('{{ data_get($fb, 'formB_id') }}')" wire:loading.attr="disabled">{!! $printSvg !!} Cetak</x-secondary-button>
                            </div>
                        @empty
                            {!! $emptyPill('Belum ada Form B') !!}
                        @endforelse
                    </x-border-form>

                    {{-- ── Edukasi Pasien ── --}}
                    <x-border-form title="Edukasi Pasien">
                        @php $eduAda = false; @endphp
                        @foreach ($ri['edukasiPasien'] ?? [] as $idx => $edu)
                            @if (filled(data_get($edu, 'tglEdukasi')) || filled(data_get($edu, 'sasaranEdukasi')))
                                @php $eduAda = true; @endphp
                                @php
                                    $eduMateri = (string) data_get($edu, 'edukasi.materiTopikEdukasi', '');
                                    $eduKategori = collect(data_get($edu, 'edukasi.kategoriEdukasi', []))->filter()->implode(', ');
                                    $eduKet = (string) data_get($edu, 'edukasi.keteranganEdukasi', '');
                                @endphp
                                <div class="flex items-center justify-between gap-3 py-2.5 border-b border-hairline-soft last:border-0 dark:border-gray-800">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                            <span class="text-base font-semibold text-ink dark:text-gray-200">{{ $eduMateri ?: ($eduKategori ?: 'Edukasi') }}</span>
                                            {!! $dateChip(data_get($edu, 'tglEdukasi')) !!}
                                        </div>
                                        <div class="mt-0.5 text-sm text-muted">
                                            @if (filled($eduKategori))<span class="text-body">{{ $eduKategori }}</span> · @endif
                                            Sasaran: {{ data_get($edu, 'sasaranEdukasi') ?: '-' }} · Petugas: {{ data_get($edu, 'petugasEdukasi') ?: '-' }}
                                        </div>
                                        @if (filled($eduKet))
                                            <div class="text-sm truncate text-muted-soft">{{ \Illuminate\Support\Str::limit($eduKet, 90) }}</div>
                                        @endif
                                    </div>
                                    <x-secondary-button type="button" class="gap-1.5 shrink-0"
                                        wire:click="cetakEdukasiPasienRi({{ $idx }})" wire:loading.attr="disabled">{!! $printSvg !!} Cetak</x-secondary-button>
                                </div>
                            @endif
                        @endforeach
                        @unless ($eduAda)
                            {!! $emptyPill('Belum diisi') !!}
                        @endunless
                    </x-border-form>

                    {{-- ── Edukasi Terintegrasi ── --}}
                    @php $eduTList = collect($ri['edukasiPasienTerintegrasi'] ?? [])->filter(fn($x) => filled(data_get($x, 'id'))); @endphp
                    <x-border-form title="Edukasi Terintegrasi">
                        @forelse ($eduTList as $et)
                            @php
                                $tujuanKeys = (array) data_get($et, 'form.tujuan.opsi', []);
                                $tujuanText = collect($tujuanKeys)->map(fn($k) => $tujuanLabels[$k] ?? $k)->filter()->implode(', ');
                                $tujuanLainnya = trim((string) data_get($et, 'form.tujuan.lainnya', ''));
                                if ($tujuanLainnya) {
                                    $tujuanText = trim($tujuanText . ($tujuanText ? ', ' : '') . $tujuanLainnya);
                                }
                            @endphp
                            <div class="flex items-center justify-between gap-3 py-2.5 border-b border-hairline-soft last:border-0 dark:border-gray-800">
                                <div class="flex flex-wrap items-center min-w-0 gap-x-2 gap-y-1">
                                    <span class="text-base font-semibold text-ink dark:text-gray-200">{{ $tujuanText ?: 'Edukasi Terintegrasi' }}</span>
                                    {!! $dateChip(data_get($et, 'tglEdukasi')) !!}
                                </div>
                                <x-secondary-button type="button" class="gap-1.5 shrink-0"
                                    wire:click="cetakEdukasiTerintegrasiRi('{{ data_get($et, 'id') }}')" wire:loading.attr="disabled">{!! $printSvg !!} Cetak</x-secondary-button>
                            </div>
                        @empty
                            {!! $emptyPill('Belum diisi') !!}
                        @endforelse
                    </x-border-form>

                    {{-- ── Form Pindah Antar Ruang ── --}}
                    @php $pindahList = collect($ri['formPindahAntarRuangRI'] ?? [])->filter(fn($x) => filled(data_get($x, 'tglPindah'))); @endphp
                    <x-border-form title="Form Pindah Antar Ruang">
                        @forelse ($pindahList as $pn)
                            <div class="flex items-center justify-between gap-3 py-2.5 border-b border-hairline-soft last:border-0 dark:border-gray-800">
                                <div class="flex flex-wrap items-center min-w-0 gap-x-2 gap-y-1">
                                    <span class="text-base font-semibold text-ink dark:text-gray-200">{{ data_get($pn, 'dariRoomDesc') ?: '-' }}
                                        <span class="font-normal text-muted-soft">&rarr;</span> {{ data_get($pn, 'keRoomDesc') ?: '-' }}</span>
                                    {!! $dateChip(data_get($pn, 'tglPindah')) !!}
                                </div>
                                <x-secondary-button type="button" class="gap-1.5 shrink-0"
                                    wire:click="cetakFormPindahRi('{{ data_get($pn, 'tglPindah') }}')" wire:loading.attr="disabled">{!! $printSvg !!} Cetak</x-secondary-button>
                            </div>
                        @empty
                            {!! $emptyPill('Belum diisi') !!}
                        @endforelse
                    </x-border-form>
                </div>

            </div>

            {{-- ── FOOTER ── --}}
            <div class="sticky bottom-0 z-10 px-6 py-3 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-end gap-2">
                    <x-secondary-button type="button" wire:click="closeModal">Tutup</x-secondary-button>
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

    </x-modal>
</div>
