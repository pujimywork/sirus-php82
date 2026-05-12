<?php
// resources/views/pages/components/rekam-medis/u-g-d/cetak-rekam-medis/cetak-rekam-medis-open.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use EmrUGDTrait, MasterPasienTrait;

    public ?int $rjNo = null;
    public array $dataDaftarUGD = [];
    public bool $isLoading = false;

    /* ═══════════════════════════════════════
     | OPEN → load ke property, buka modal preview
    ═══════════════════════════════════════ */
    #[On('cetak-rekam-medis-ugd.open')]
    public function open(int $rjNo): void
    {
        $this->rjNo = $rjNo;
        $this->isLoading = true;
        $this->dataDaftarUGD = [];

        $dataUGD = $this->findDataUGD($rjNo);
        if (empty($dataUGD)) {
            $this->isLoading = false;
            $this->dispatch('toast', type: 'error', message: 'Data UGD tidak ditemukan.');
            return;
        }

        $pasienData = $this->findDataMasterPasien($dataUGD['regNo'] ?? '');
        if (empty($pasienData)) {
            $this->isLoading = false;
            $this->dispatch('toast', type: 'error', message: 'Data pasien tidak ditemukan.');
            return;
        }

        $pasien = $pasienData['pasien'];

        if (!empty($pasien['tglLahir'])) {
            $pasien['thn'] = Carbon::createFromFormat('d/m/Y', $pasien['tglLahir'])
                ->diff(Carbon::now(env('APP_TIMEZONE')))
                ->format('%y Thn, %m Bln %d Hr');
        }

        $dokter = DB::table('rsmst_doctors')
            ->where('dr_id', $dataUGD['drId'] ?? '')
            ->select('dr_name')
            ->first();

        $this->dataDaftarUGD = array_merge($pasien, [
            'dataDaftarTxn' => $dataUGD,
            'namaDokter' => $dokter->dr_name ?? null,
            'strDokter' => $dokter->dr_str ?? null,
            'tglCetak' => $dataUGD['rjDate'] ?? Carbon::now()->format('d/m/Y'),
        ]);

        $this->isLoading = false;

        $this->dispatch('open-modal', name: 'preview-rekam-medis-ugd');
    }

    /* ═══════════════════════════════════════
     | CETAK → generate PDF dari data yang sudah ada
    ═══════════════════════════════════════ */
    public function cetakPdf(): mixed
    {
        if (empty($this->dataDaftarUGD)) {
            $this->dispatch('toast', type: 'error', message: 'Tidak ada data untuk dicetak.');
            return null;
        }

        $data = $this->dataDaftarUGD;
        $regNo = $data['regNo'] ?? $this->rjNo;

        $pdf = Pdf::loadView('pages.components.rekam-medis.u-g-d.cetak-rekam-medis.cetak-rekam-medis-print', ['data' => $data])->setPaper('A4');

        return response()->streamDownload(fn() => print $pdf->output(), 'rekam-medis-ugd-' . $regNo . '.pdf');
    }

    /* ═══════════════════════════════════════
     | CLOSE
    ═══════════════════════════════════════ */
    public function closeModal(): void
    {
        $this->dataDaftarUGD = [];
        $this->rjNo = null;
        $this->dispatch('close-modal', name: 'preview-rekam-medis-ugd');
    }
};
?>

<div>
    <x-modal name="preview-rekam-medis-ugd" size="full" height="full" focusable>

        @php
            $d = $this->dataDaftarUGD;
            $txn = $d['dataDaftarTxn'] ?? [];

            $lastNyeri = !empty($txn['penilaian']['nyeri']) ? end($txn['penilaian']['nyeri']) : null;
            $lastResikoJatuh = !empty($txn['penilaian']['resikoJatuh']) ? end($txn['penilaian']['resikoJatuh']) : null;
            $lastDekubitus = !empty($txn['penilaian']['dekubitus']) ? end($txn['penilaian']['dekubitus']) : null;
            $lastGizi = !empty($txn['penilaian']['gizi']) ? end($txn['penilaian']['gizi']) : null;
        @endphp

        <div class="flex flex-col min-h-[calc(100vh-4rem)]" wire:key="preview-rekam-medis-ugd-{{ $rjNo }}">

            {{-- ── HEADER ── --}}
            <div class="relative px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image:radial-gradient(currentColor 1px,transparent 1px);background-size:14px 14px">
                </div>
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div
                                class="flex items-center justify-center w-10 h-10 rounded-xl bg-red-500/10 dark:bg-red-400/15">
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
                                    Assesment Awal UGD &mdash;
                                    <span class="font-medium">{{ strtoupper($d['regName'] ?? '-') }}</span>
                                </p>
                            </div>
                        </div>
                        <div class="flex gap-2 mt-3">
                            <x-badge variant="info">No. RM: {{ $d['regNo'] ?? '-' }}</x-badge>
                            <x-badge variant="neutral">{{ $txn['rjDate'] ?? '-' }}</x-badge>
                            <x-badge variant="danger">UGD</x-badge>
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
                    @php
                        $id = $d['identitas'] ?? [];
                        $alamatFull = trim(
                            ($id['alamat'] ?? '-') .
                                (!empty($id['rt']) ? ' RT ' . $id['rt'] : '') .
                                (!empty($id['rw']) ? '/RW ' . $id['rw'] : '') .
                                (!empty($id['desaName']) ? ', ' . $id['desaName'] : '') .
                                (!empty($id['kecamatanName']) ? ', ' . $id['kecamatanName'] : ''),
                        );
                    @endphp
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-2">
                        <p class="text-sm"><span class="text-gray-400">No. Rekam Medis : </span><span
                                class="font-semibold text-gray-900 dark:text-gray-100">{{ $d['regNo'] ?? '-' }}</span>
                        </p>
                        <p class="text-sm"><span class="text-gray-400">Nama Pasien : </span><span
                                class="font-semibold text-gray-900 dark:text-gray-100">{{ strtoupper($d['regName'] ?? '-') }}</span>
                        </p>
                        <p class="text-sm"><span class="text-gray-400">Tanggal Masuk : </span><span
                                class="text-gray-700 dark:text-gray-300">{{ $txn['rjDate'] ?? '-' }}</span></p>
                        <p class="text-sm"><span class="text-gray-400">Jenis Kelamin : </span><span
                                class="text-gray-700 dark:text-gray-300">{{ $d['jenisKelamin']['jenisKelaminDesc'] ?? '-' }}</span>
                        </p>
                        <p class="text-sm"><span class="text-gray-400">Tempat, Tgl. Lahir : </span><span
                                class="text-gray-700 dark:text-gray-300">{{ ($d['tempatLahir'] ?? '-') . ', ' . ($d['tglLahir'] ?? '-') . ' (' . ($d['thn'] ?? '-') . ')' }}</span>
                        </p>
                        <p class="col-span-2 text-sm"><span class="text-gray-400">Alamat : </span><span
                                class="text-gray-700 dark:text-gray-300">{{ $alamatFull }}</span></p>
                    </div>
                </x-border-form>

                {{-- TRIASE --}}
                @php
                    $kajian = $txn['anamnesa']['pengkajianPerawatan'] ?? [];
                    $tk = $kajian['tingkatKegawatan'] ?? '-';
                    $tkLabel = match ($tk) {
                        'P1' => 'P1 — Kritis',
                        'P2' => 'P2 — Urgent',
                        'P3' => 'P3 — Minor',
                        'P0' => 'P0 — Death',
                        default => '-',
                    };
                    $tkBg = match ($tk) {
                        'P1' => 'bg-red-50 border-red-300 dark:bg-red-900/20 dark:border-red-700',
                        'P2' => 'bg-yellow-50 border-yellow-300 dark:bg-yellow-900/20 dark:border-yellow-700',
                        'P3' => 'bg-green-50 border-green-300 dark:bg-green-900/20 dark:border-green-700',
                        'P0' => 'bg-gray-100 border-gray-300 dark:bg-gray-800 dark:border-gray-600',
                        default => 'bg-white border-gray-200 dark:bg-gray-900 dark:border-gray-700',
                    };
                    $tkBadge = match ($tk) {
                        'P1' => 'bg-red-500 text-white',
                        'P2' => 'bg-yellow-400 text-gray-900',
                        'P3' => 'bg-green-500 text-white',
                        'P0' => 'bg-gray-700 text-white',
                        default => 'bg-gray-300 text-gray-700',
                    };
                @endphp
                <div class="mb-4 p-3 border-l-4 rounded-lg shadow-sm {{ $tkBg }}">
                    <div class="flex items-start gap-3">
                        <span
                            class="inline-flex items-center px-3 py-1 text-sm font-bold rounded-full shrink-0 {{ $tkBadge }}">
                            {{ $tkLabel }}
                        </span>
                        <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1 text-sm">
                            <p><span class="text-gray-500">Cara Masuk IGD : </span><span
                                    class="font-medium text-gray-800 dark:text-gray-200">{{ $kajian['caraMasukIgd'] ?? '-' }}</span>
                            </p>
                            <p><span class="text-gray-500">Jam Datang : </span><span
                                    class="font-medium text-gray-800 dark:text-gray-200">{{ $kajian['jamDatang'] ?? '-' }}</span>
                            </p>
                            <p class="sm:col-span-2"><span class="text-gray-500">Perawat Penerima : </span><span
                                    class="font-medium text-gray-800 dark:text-gray-200">
                                    {{ !empty($kajian['perawatPenerima']) ? strtoupper($kajian['perawatPenerima']) : '-' }}
                                    @if (!empty($kajian['perawatPenerimaCode']))
                                        <span class="text-xs text-gray-500">({{ $kajian['perawatPenerimaCode'] }})</span>
                                    @endif
                                </span></p>
                        </div>
                    </div>
                </div>

                {{-- PERAWAT --}}
                <x-border-form title="Perawat" class="mb-4">
                    @php
                        $sp = $txn['anamnesa']['statusPsikologis'] ?? [];
                        $statPsiko = collect([
                            $sp['tidakAdaKelainan'] ?? false ? 'Tidak Ada Kelainan' : null,
                            $sp['marah'] ?? false ? 'Marah' : null,
                            $sp['ccemas'] ?? false ? 'Cemas' : null,
                            $sp['takut'] ?? false ? 'Takut' : null,
                            $sp['sedih'] ?? false ? 'Sedih' : null,
                            $sp['cenderungBunuhDiri'] ?? false ? 'Resiko Bunuh Diri' : null,
                        ])
                            ->filter()
                            ->implode(' / ');
                        $sm = $txn['anamnesa']['statusMental'] ?? [];
                    @endphp
                    <div class="space-y-2">
                        <p class="text-sm"><span class="text-gray-400">Status Psikologis : </span><span
                                class="text-gray-700 dark:text-gray-300">{{ $statPsiko . (!empty($sp['sebutstatusPsikologis']) ? ' — ' . $sp['sebutstatusPsikologis'] : '') ?: '-' }}</span>
                        </p>
                        <p class="text-sm"><span class="text-gray-400">Status Mental : </span><span
                                class="text-gray-700 dark:text-gray-300">{{ ($sm['statusMental'] ?? '-') . (!empty($sm['keteranganStatusMental']) ? ' — ' . $sm['keteranganStatusMental'] : '') }}</span>
                        </p>
                    </div>
                </x-border-form>

                {{-- ANAMNESA + TANDA VITAL + NUTRISI --}}
                <div class="grid grid-cols-3 gap-4 mb-4">
                    <x-border-form title="Anamnesa" class="col-span-2">
                        <div class="space-y-2.5">
                            <p class="text-sm"><span class="text-gray-400">Keluhan Utama : </span><span
                                    class="text-gray-700 dark:text-gray-300">{{ $txn['anamnesa']['keluhanUtama']['keluhanUtama'] ?? '-' }}</span>
                            </p>
                            <p class="text-sm"><span class="text-gray-400">Screening Batuk : </span><span
                                    class="text-gray-700 dark:text-gray-300">{{ $txn['anamnesa']['screeningBatuk'] ?? '-' }}</span>
                            </p>
                            <p class="text-sm"><span class="text-gray-400">Skala Nyeri : </span><span
                                    class="text-gray-700 dark:text-gray-300">Metode:
                                    {{ $lastNyeri['nyeri']['nyeriMetode']['nyeriMetode'] ?? '-' }} / Skor:
                                    {{ $lastNyeri['nyeri']['nyeriMetode']['nyeriMetodeScore'] ?? '-' }} /
                                    {{ $lastNyeri['nyeri']['nyeriKet'] ?? '-' }} / Pencetus:
                                    {{ $lastNyeri['nyeri']['pencetus'] ?? '-' }} / Durasi:
                                    {{ $lastNyeri['nyeri']['durasi'] ?? '-' }} / Lokasi:
                                    {{ $lastNyeri['nyeri']['lokasi'] ?? '-' }}</span></p>
                            <p class="text-sm"><span class="text-gray-400">Resiko Jatuh : </span><span
                                    class="text-gray-700 dark:text-gray-300">Metode:
                                    {{ $lastResikoJatuh['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetode'] ?? '-' }}
                                    / Skor:
                                    {{ $lastResikoJatuh['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetodeScore'] ?? '-' }}
                                    / {{ $lastResikoJatuh['resikoJatuh']['kategoriResiko'] ?? '-' }}</span></p>
                            @if ($lastDekubitus)
                                <p class="text-sm"><span class="text-gray-400">Dekubitus : </span><span
                                        class="text-gray-700 dark:text-gray-300">{{ $lastDekubitus['dekubitus']['dekubitus'] ?? '-' }}
                                        / Skor Braden: {{ $lastDekubitus['dekubitus']['bradenScore'] ?? '-' }} /
                                        {{ $lastDekubitus['dekubitus']['kategoriResiko'] ?? '-' }}{{ !empty($lastDekubitus['dekubitus']['rekomendasi']) ? ' / ' . $lastDekubitus['dekubitus']['rekomendasi'] : '' }}</span>
                                </p>
                            @endif
                            @if ($lastGizi)
                                <p class="text-sm"><span class="text-gray-400">Gizi : </span><span
                                        class="text-gray-700 dark:text-gray-300">BB:
                                        {{ $lastGizi['gizi']['beratBadan'] ?? '-' }} kg / TB:
                                        {{ $lastGizi['gizi']['tinggiBadan'] ?? '-' }} cm / IMT:
                                        {{ $lastGizi['gizi']['imt'] ?? '-' }} / Skor:
                                        {{ $lastGizi['gizi']['skorSkrining'] ?? '-' }} /
                                        {{ $lastGizi['gizi']['kategoriGizi'] ?? '-' }}{{ !empty($lastGizi['gizi']['catatan']) ? ' / ' . $lastGizi['gizi']['catatan'] : '' }}</span>
                                </p>
                            @endif
                            <p class="text-sm"><span class="text-gray-400">Riwayat Penyakit Sekarang : </span><span
                                    class="text-gray-700 dark:text-gray-300">{{ $txn['anamnesa']['riwayatPenyakitSekarangUmum']['riwayatPenyakitSekarangUmum'] ?? '-' }}</span>
                            </p>
                            <p class="text-sm"><span class="text-gray-400">Riwayat Penyakit Dahulu : </span><span
                                    class="text-gray-700 dark:text-gray-300">{{ $txn['anamnesa']['riwayatPenyakitDahulu']['riwayatPenyakitDahulu'] ?? '-' }}</span>
                            </p>
                            <p class="text-sm"><span class="text-gray-400">Alergi : </span><span
                                    class="text-gray-700 dark:text-gray-300">{{ $txn['anamnesa']['alergi']['alergi'] ?? '-' }}</span>
                            </p>
                            <div>
                                <p class="mb-1.5 text-sm text-gray-400">Rekonsiliasi Obat :</p>
                                <table class="w-full text-sm border-collapse">
                                    <thead>
                                        <tr class="bg-gray-50 dark:bg-gray-800">
                                            <th
                                                class="px-2.5 py-1.5 text-left text-sm font-medium text-gray-500 border border-gray-200 dark:border-gray-700">
                                                Nama Obat</th>
                                            <th
                                                class="px-2.5 py-1.5 text-left text-sm font-medium text-gray-500 border border-gray-200 dark:border-gray-700">
                                                Dosis</th>
                                            <th
                                                class="px-2.5 py-1.5 text-left text-sm font-medium text-gray-500 border border-gray-200 dark:border-gray-700">
                                                Rute</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($txn['anamnesa']['rekonsiliasiObat'] ?? [] as $obat)
                                            <tr>
                                                <td
                                                    class="px-2.5 py-1.5 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700">
                                                    {{ $obat['namaObat'] ?? '-' }}</td>
                                                <td
                                                    class="px-2.5 py-1.5 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700">
                                                    {{ $obat['dosis'] ?? '-' }}</td>
                                                <td
                                                    class="px-2.5 py-1.5 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700">
                                                    {{ $obat['rute'] ?? '-' }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="3"
                                                    class="px-2.5 py-1.5 text-center text-gray-400 border border-gray-200 dark:border-gray-700">
                                                    Tidak ada data</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </x-border-form>

                    <div class="space-y-4">
                        <x-border-form title="Tanda Vital">
                            @php $tv = $txn['pemeriksaan']['tandaVital'] ?? []; @endphp
                            <div class="space-y-2">
                                @foreach ([['TD', ($tv['sistolik'] ?? '-') . ' / ' . ($tv['distolik'] ?? '-'), 'mmHg'], ['Nadi', $tv['frekuensiNadi'] ?? '-', 'x/mnt'], ['Suhu', $tv['suhu'] ?? '-', '°C'], ['Pernafasan', $tv['frekuensiNafas'] ?? '-', 'x/mnt'], ['SPO2', $tv['spo2'] ?? '-', '%'], ['GDA', $tv['gda'] ?? '-', 'mg/dL']] as [$label, $val, $unit])
                                    <p
                                        class="flex justify-between pb-1.5 border-b border-gray-100 dark:border-gray-800 last:border-0">
                                        <span
                                            class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</span>
                                        <span
                                            class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $val }}
                                            <span class="font-normal text-gray-400">{{ $unit }}</span></span>
                                    </p>
                                @endforeach
                            </div>
                        </x-border-form>

                        <x-border-form title="Nutrisi">
                            @php $nut = $txn['pemeriksaan']['nutrisi'] ?? []; @endphp
                            <div class="space-y-2">
                                @foreach ([['Berat Badan', $nut['bb'] ?? '-', 'Kg'], ['Tinggi Badan', $nut['tb'] ?? '-', 'cm'], ['IMT', $nut['imt'] ?? '-', 'Kg/M²'], ['Lingkar Kepala', $nut['lk'] ?? '-', 'cm'], ['Lingkar Lengan', $nut['lila'] ?? '-', 'cm']] as [$label, $val, $unit])
                                    <p
                                        class="flex justify-between pb-1.5 border-b border-gray-100 dark:border-gray-800 last:border-0">
                                        <span
                                            class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</span>
                                        <span
                                            class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $val }}
                                            <span class="font-normal text-gray-400">{{ $unit }}</span></span>
                                    </p>
                                @endforeach
                            </div>
                        </x-border-form>
                    </div>
                </div>

                {{-- KEADAAN UMUM + FUNGSIONAL --}}
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <x-border-form title="Keadaan Umum">
                        <p class="text-sm text-gray-800 dark:text-gray-200">
                            {{ $txn['pemeriksaan']['tandaVital']['keadaanUmum'] ?? 'BAIK' }} &nbsp;/&nbsp; <span
                                class="font-medium">{{ $txn['pemeriksaan']['tandaVital']['tingkatKesadaran'] ?? '-' }}</span>
                        </p>
                    </x-border-form>
                    <x-border-form title="Fungsional">
                        @php $fn = $txn['pemeriksaan']['fungsional'] ?? []; @endphp
                        <div class="space-y-2">
                            <p class="text-sm"><span class="text-gray-400">Alat Bantu : </span><span
                                    class="text-gray-700 dark:text-gray-300">{{ $fn['alatBantu'] ?? '-' }}</span></p>
                            <p class="text-sm"><span class="text-gray-400">Prothesa : </span><span
                                    class="text-gray-700 dark:text-gray-300">{{ $fn['prothesa'] ?? '-' }}</span></p>
                            <p class="text-sm"><span class="text-gray-400">Cacat Tubuh : </span><span
                                    class="text-gray-700 dark:text-gray-300">{{ $fn['cacatTubuh'] ?? '-' }}</span></p>
                            <p class="text-sm"><span class="text-gray-400">Suspek Kecelakaan Kerja : </span><span
                                    class="text-gray-700 dark:text-gray-300">{{ $fn['suspekKecelakaanKerja'] ?? '-' }}</span>
                            </p>
                        </div>
                    </x-border-form>
                </div>

                {{-- PEMERIKSAAN --}}
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <x-border-form title="Pemeriksaan Fisik & Uji Fungsi">
                        <p class="text-sm text-gray-700 whitespace-pre-line dark:text-gray-300">
                            {{ $txn['pemeriksaan']['fisik'] ?? '-' }}
                            {{ $txn['pemeriksaan']['FisikujiFungsi']['FisikujiFungsi'] ?? '' }}</p>
                    </x-border-form>
                    <x-border-form title="Anatomi">
                        @if (!empty($txn['pemeriksaan']['anatomi']))
                            @foreach ($txn['pemeriksaan']['anatomi'] as $key => $pAnatomi)
                                @if (!empty($pAnatomi['kelainan']) && $pAnatomi['kelainan'] !== 'Tidak Diperiksa')
                                    <p class="text-sm text-gray-700 dark:text-gray-300"><span
                                            class="font-semibold">{{ strtoupper($key) }}</span>:
                                        {{ $pAnatomi['kelainan'] }} — {{ $pAnatomi['desc'] ?? '-' }}</p>
                                @endif
                            @endforeach
                        @else
                            <p class="text-sm text-gray-400">-</p>
                        @endif
                    </x-border-form>
                </div>

                {{-- PENUNJANG + DIAGNOSIS + PROSEDUR --}}
                <x-border-form class="mb-4">
                    <div class="space-y-2">
                        <p class="text-sm"><span class="text-gray-400">Penunjang : </span><span
                                class="text-gray-700 dark:text-gray-300">{{ $txn['pemeriksaan']['penunjang'] ?? '-' }}</span>
                        </p>
                        <p class="text-sm"><span class="text-gray-400">Diagnosis : </span><span
                                class="font-semibold text-gray-900 dark:text-gray-100">{{ $txn['diagnosisFreeText'] ?? '-' }}</span>
                        </p>
                        <p class="text-sm"><span class="text-gray-400">Prosedur : </span><span
                                class="text-gray-700 dark:text-gray-300">{{ $txn['procedureFreeText'] ?? '-' }}</span>
                        </p>
                    </div>
                </x-border-form>

                {{-- TINDAK LANJUT + TERAPI --}}
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <x-border-form title="Tindak Lanjut">
                        <p class="text-sm text-gray-800 dark:text-gray-200">
                            {{ $txn['perencanaan']['tindakLanjut']['tindakLanjut'] ?? '-' }}@if (!empty($txn['perencanaan']['tindakLanjut']['keteranganTindakLanjut']))
                                / {{ $txn['perencanaan']['tindakLanjut']['keteranganTindakLanjut'] }}
                            @endif
                        </p>
                    </x-border-form>
                    <x-border-form title="Terapi">
                        <p class="text-sm text-gray-800 whitespace-pre-line dark:text-gray-200">
                            {{ $txn['perencanaan']['terapi']['terapi'] ?? '-' }}</p>
                    </x-border-form>
                </div>

                {{-- TTD --}}
                <x-border-form>
                    <div class="flex items-end justify-between">
                        <div class="text-center min-w-[160px]">
                            <p class="mb-2 text-sm text-gray-400">Perawat / Terapis</p>
                            <div class="flex items-center justify-center h-20">
                                @isset($txn['anamnesa']['pengkajianPerawatan']['perawatPenerimaCode'])
                                    @if ($txn['anamnesa']['pengkajianPerawatan']['perawatPenerimaCode'])
                                        @php $ttdPerawat = App\Models\User::where('myuser_code', $txn['anamnesa']['pengkajianPerawatan']['perawatPenerimaCode'])->value('myuser_ttd_image'); @endphp
                                        @if (!empty($ttdPerawat))
                                            <img class="object-contain h-16 mx-auto"
                                                src="{{ asset('storage/' . $ttdPerawat) }}" alt="TTD Perawat">
                                        @endif
                                    @endif
                                @endisset
                            </div>
                            <div class="pt-1 border-t border-gray-400">
                                <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                                    {{ isset($txn['anamnesa']['pengkajianPerawatan']['perawatPenerima']) ? strtoupper($txn['anamnesa']['pengkajianPerawatan']['perawatPenerima']) : '.................................' }}
                                </p>
                            </div>
                        </div>
                        <div class="text-center min-w-[160px]">
                            <p class="mb-2 text-sm text-gray-400">Tulungagung, {{ $d['tglCetak'] ?? '-' }}</p>
                            <div class="flex items-center justify-center h-20">
                                @isset($txn['perencanaan']['pengkajianMedis']['drPemeriksa'])
                                    @if ($txn['perencanaan']['pengkajianMedis']['drPemeriksa'])
                                        @php $ttdDokter = App\Models\User::where('myuser_code', $txn['drId'] ?? '')->value('myuser_ttd_image'); @endphp
                                        @if (!empty($ttdDokter))
                                            <img class="object-contain h-16 mx-auto"
                                                src="{{ asset('storage/' . $ttdDokter) }}" alt="TTD Dokter">
                                        @endif
                                    @endif
                                @endisset
                            </div>
                            <div class="pt-1 border-t border-gray-400">
                                <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                                    {{ $d['namaDokter'] ?? 'dr. .................' }}</p>
                                @if (!empty($d['strDokter']))
                                    <p class="text-sm text-gray-400">STR: {{ $d['strDokter'] }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </x-border-form>

            </div>

            {{-- ── FOOTER ── --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Preview rekam medis UGD — data belum dicetak.
                    </p>
                    <div class="flex gap-2">
                        <x-secondary-button type="button" wire:click="closeModal">Tutup</x-secondary-button>
                        <x-primary-button type="button" wire:click="cetakPdf" wire:loading.attr="disabled">
                            <svg wire:loading.remove class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <svg wire:loading class="w-4 h-4 mr-1.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10"
                                    stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                            </svg>
                            <span wire:loading.remove>Cetak PDF</span>
                            <span wire:loading>Menyiapkan PDF...</span>
                        </x-primary-button>
                    </div>
                </div>
            </div>

        </div>
    </x-modal>
</div>
