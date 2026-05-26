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
            $bedNo = (string) data_get($ri, 'bedNo', '');
            $ruangKelas = trim($bangsalDesc . ($roomDesc ? ' / ' . $roomDesc : '') . ($bedNo ? ' / Bed ' . $bedNo : ''));
            $tglMasuk = (string) data_get($ri, 'entryDate', '');
            $tglKeluar = (string) data_get($ri, 'exitDate', '');

            $resumeHtml = (string) data_get($ri, 'resumeMedis', '');
            $hasResume = trim(strip_tags($resumeHtml)) !== '';
        @endphp

        <div class="flex flex-col min-h-[calc(100vh-4rem)]" wire:key="preview-rekam-medis-ri-{{ $riHdrNo }}">

            {{-- ── HEADER ── --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image:radial-gradient(currentColor 1px,transparent 1px);background-size:14px 14px">
                </div>
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div
                                class="flex items-center justify-center w-10 h-10 rounded-xl bg-purple-500/10 dark:bg-purple-400/15">
                                <img src="{{ asset('images/Logogram black solid.png') }}"
                                    class="block w-6 h-6 dark:hidden" />
                                <img src="{{ asset('images/Logogram white solid.png') }}"
                                    class="hidden w-6 h-6 dark:block" />
                            </div>
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                    Preview Rekam Medis
                                </h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Resume Medis RM 41 (Rawat Inap) &mdash;
                                    <span class="font-medium">{{ strtoupper($nama ?: '-') }}</span>
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 mt-3">
                            <x-badge variant="info">No. RM: {{ $rm ?: '-' }}</x-badge>
                            <x-badge variant="neutral">{{ $tglMasuk ?: '-' }}{{ $tglKeluar ? ' — ' . $tglKeluar : '' }}</x-badge>
                            <x-badge variant="brand">RAWAT INAP</x-badge>
                        </div>
                    </div>
                    <x-secondary-button type="button" wire:click="closeModal" class="!p-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-secondary-button>
                </div>
            </div>

            {{-- ── BODY ── --}}
            <div class="flex-1 px-6 py-5 overflow-y-auto bg-gray-50/70 dark:bg-gray-950/20">

                {{-- IDENTITAS PASIEN --}}
                <x-border-form title="Identitas Pasien" class="mb-4">
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-2">
                        <p class="text-sm"><span class="text-gray-400">No. Rekam Medis : </span><span
                                class="font-semibold text-gray-900 dark:text-gray-100">{{ $rm ?: '-' }}</span></p>
                        <p class="text-sm"><span class="text-gray-400">Nama Pasien : </span><span
                                class="font-semibold text-gray-900 dark:text-gray-100">{{ strtoupper($nama ?: '-') }}</span></p>
                        <p class="text-sm"><span class="text-gray-400">Jenis Kelamin : </span><span
                                class="text-gray-700 dark:text-gray-300">{{ $sexLabel }}</span></p>
                        <p class="text-sm"><span class="text-gray-400">Tempat, Tgl Lahir : </span><span
                                class="text-gray-700 dark:text-gray-300">{{ ($tempatLahir ?: '-') . ', ' . ($tglLahir ?: '-') . ' (' . $umurStr . ')' }}</span></p>
                        <p class="col-span-2 sm:col-span-1 text-sm"><span class="text-gray-400">Ruang/Kelas : </span><span
                                class="text-gray-700 dark:text-gray-300">{{ $ruangKelas ?: '-' }}</span></p>
                        <p class="text-sm"><span class="text-gray-400">Tgl Masuk : </span><span
                                class="text-gray-700 dark:text-gray-300">{{ $tglMasuk ?: '-' }}</span></p>
                        <p class="text-sm"><span class="text-gray-400">Tgl Pulang : </span><span
                                class="text-gray-700 dark:text-gray-300">{{ $tglKeluar ?: '-' }}</span></p>
                        <p class="col-span-2 sm:col-span-3 text-sm"><span class="text-gray-400">Alamat : </span><span
                                class="text-gray-700 dark:text-gray-300">{{ $alamatFull ?: '-' }}</span></p>
                    </div>
                </x-border-form>

                {{-- RESUME MEDIS (RM 41) --}}
                <x-border-form title="Resume Medis (RM 41)" class="mb-4">
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
                        <div class="resume-medis-preview text-base leading-relaxed text-gray-800 dark:text-gray-200"
                            style="overflow-x:auto;">
                            {!! $resumeHtml !!}
                        </div>
                    @else
                        <p class="text-sm italic text-gray-400 py-4 text-center">
                            Resume Medis (RM 41) belum dibuat untuk kunjungan ini.
                        </p>
                    @endif
                </x-border-form>

            </div>

            {{-- ── FOOTER ── --}}
            <div class="sticky bottom-0 z-10 px-6 py-3 bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
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
