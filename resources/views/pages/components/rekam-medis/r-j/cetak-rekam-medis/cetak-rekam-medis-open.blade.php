<?php
use Livewire\Component;
use Livewire\Attributes\On;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use EmrRJTrait, MasterPasienTrait;

    public ?int $rjNo = null;
    public array $dataDaftarPoliRJ = [];
    public bool $isLoading = false;

    // Navigasi antar-kunjungan (Prev/Next) — diisi rekam-medis-display saat open.
    public int $navPos = 0;
    public int $navTotal = 0;

    /* =====================================================
     | OPEN → load ke property, buka modal preview
     ===================================================== */
    #[On('cetak-rekam-medis.open')]
    public function open(int $rjNo, int $navPos = 0, int $navTotal = 0): void
    {
        $this->rjNo = $rjNo;
        $this->navPos = $navPos;
        $this->navTotal = $navTotal;
        $this->isLoading = true;
        $this->dataDaftarPoliRJ = [];

        $dataRJ = $this->findDataRJ($rjNo);
        if (empty($dataRJ)) {
            $this->isLoading = false;
            $this->dispatch('toast', type: 'error', message: 'Data Rawat Jalan tidak ditemukan.');
            return;
        }

        $pasienData = $this->findDataMasterPasien($dataRJ['regNo'] ?? '');
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
            ->where('dr_id', $dataRJ['drId'] ?? '')
            ->select('dr_name')
            ->first();

        $this->dataDaftarPoliRJ = array_merge($pasien, [
            'dataDaftarTxn' => $dataRJ,
            'namaDokter' => $dokter->dr_name ?? null,
            'tglCetak' => $dataRJ['rjDate'] ?? Carbon::now()->format('d/m/Y'),
        ]);

        $this->isLoading = false;

        $this->dispatch('open-modal', name: 'preview-rekam-medis');
    }

    /* =====================================================
     | CETAK → generate PDF dari data yang sudah ada
     ===================================================== */
    public function cetakPdf(): mixed
    {
        if (empty($this->dataDaftarPoliRJ)) {
            $this->dispatch('toast', type: 'error', message: 'Tidak ada data untuk dicetak.');
            return null;
        }

        $data = $this->dataDaftarPoliRJ;
        $regNo = $data['regNo'] ?? $this->rjNo;

        $pdf = Pdf::loadView('pages.components.rekam-medis.r-j.cetak-rekam-medis.cetak-rekam-medis-print', ['data' => $data])->setPaper('A4');

        return response()->streamDownload(fn() => print $pdf->output(), 'rekam-medis-' . $regNo . '.pdf');
    }

    /* =====================================================
     | CLOSE
     ===================================================== */
    public function closeModal(): void
    {
        $this->dataDaftarPoliRJ = [];
        $this->rjNo = null;
        $this->dispatch('close-modal', name: 'preview-rekam-medis');
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
};
?>

<div>
    <x-modal name="preview-rekam-medis" size="full" height="full" focusable>

        @php
            $d = $this->dataDaftarPoliRJ;
            $txn = $d['dataDaftarTxn'] ?? [];

            $lastNyeri = !empty($txn['penilaian']['nyeri']) ? end($txn['penilaian']['nyeri']) : null;
            $lastResikoJatuh = !empty($txn['penilaian']['resikoJatuh']) ? end($txn['penilaian']['resikoJatuh']) : null;
            $lastDekubitus = !empty($txn['penilaian']['dekubitus']) ? end($txn['penilaian']['dekubitus']) : null;
            $lastGizi = !empty($txn['penilaian']['gizi']) ? end($txn['penilaian']['gizi']) : null;
        @endphp

        <div class="flex flex-col min-h-[calc(100vh-4rem)]" wire:key="preview-rekam-medis-{{ $rjNo }}"
            x-data="{ tab: 'resume' }">

            {{-- ── HEADER ──────────────────────────────────────────────── --}}
            <div class="relative px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image:radial-gradient(currentColor 1px,transparent 1px);background-size:14px 14px">
                </div>

                <div class="relative flex items-start justify-between gap-4">
                    {{-- Identitas pasien jadi header (pola EMR) --}}
                    <div class="flex-1 min-w-0">
                        <livewire:pages::transaksi.rj.display-pasien-rj.display-pasien-rj :rjNo="$rjNo"
                            wire:key="preview-rm-rj-display-pasien-{{ $rjNo }}" />
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
                        class="px-4 py-3 text-base font-semibold transition-colors border-b-2">Assesment Awal Rawat Jalan</button>
                    <button type="button" x-on:click="tab = 'dokumen'"
                        :class="tab === 'dokumen' ? 'border-brand-green text-brand-green' :
                            'border-transparent text-muted hover:text-ink'"
                        class="px-4 py-3 text-base font-semibold transition-colors border-b-2">Modul Dokumen</button>
                    <button type="button" x-on:click="tab = 'penunjang'"
                        :class="tab === 'penunjang' ? 'border-brand-green text-brand-green' :
                            'border-transparent text-muted hover:text-ink'"
                        class="px-4 py-3 text-base font-semibold transition-colors border-b-2">Hasil Penunjang</button>
                </nav>
            </div>

            {{-- ── BODY (scroll) ── --}}
            <div class="flex-1 overflow-y-auto bg-surface-soft/70 dark:bg-gray-950/20">

                {{-- ════ TAB: RESUME ════ --}}
                <div x-show="tab === 'resume'" class="px-6 py-5">

                {{-- SCREENING RJ — berbasis skor + keputusan (beda konsep dgn triase P0-P3 di UGD) --}}
                @php
                    $scr = $txn['screening'] ?? [];
                    $scrGawatLain = ($scr['gawatLain'] ?? '') ?: '-';
                    if (($scr['gawatLain'] ?? '') === 'Ya' && !empty($scr['gawatLainKet'])) {
                        $scrGawatLain .= ' — ' . $scr['gawatLainKet'];
                    }
                    $scrKeputusan = ($scr['keputusan'] ?? '') ?: '-';
                    $scrKeputusanClass = match ($scrKeputusan) {
                        'IGD' => 'bg-red-50 border-red-200 text-red-700 dark:bg-red-900/20 dark:border-red-800 dark:text-red-300',
                        'Disegerakan' => 'bg-yellow-50 border-yellow-200 text-yellow-700 dark:bg-yellow-900/20 dark:border-yellow-800 dark:text-yellow-300',
                        'Aman' => 'bg-green-50 border-green-200 text-green-700 dark:bg-green-900/20 dark:border-green-800 dark:text-green-300',
                        default => 'bg-surface-soft border-hairline text-body',
                    };
                    $scrFlags = collect([
                        ($scr['flagJatuh'] ?? false) ? 'Risiko Jatuh' : null,
                        ($scr['flagInfeksius'] ?? false) ? 'Infeksius (batuk > 2 minggu)' : null,
                    ])->filter()->implode(' / ');
                @endphp
                <x-border-form title="Screening Rawat Jalan" class="mb-4">
                    <div class="grid grid-cols-2 gap-x-6">
                        <div class="space-y-2.5">
                            <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Kesadaran :</span><span
                                    class="text-body dark:text-gray-300">{{ ($scr['kesadaran'] ?? '') ?: '-' }}</span>
                            </p>
                            <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Pernafasan :</span><span
                                    class="text-body dark:text-gray-300">{{ ($scr['pernafasan'] ?? '') ?: '-' }}</span>
                            </p>
                            <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Nyeri Dada :</span><span
                                    class="text-body dark:text-gray-300">{{ ($scr['nyeriDada'] ?? '') ?: '-' }}</span>
                            </p>
                            <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Kegawatan Lain :</span><span
                                    class="text-body dark:text-gray-300">{{ $scrGawatLain }}</span>
                            </p>
                        </div>
                        <div class="space-y-2.5">
                            <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Alat Bantu :</span><span
                                    class="text-body dark:text-gray-300">{{ ($scr['alatBantu'] ?? '') ?: '-' }}</span>
                            </p>
                            <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Batuk :</span><span
                                    class="text-body dark:text-gray-300">{{ ($scr['batuk'] ?? '') ?: '-' }}</span>
                            </p>
                            <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Skor Tertinggi :</span><span
                                    class="text-body dark:text-gray-300">{{ $scr['skorMaks'] ?? '-' }}</span>
                            </p>
                            <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Petugas :</span><span
                                    class="text-body dark:text-gray-300">{{ ($scr['petugasScreening'] ?? '') ?: '-' }}{{ !empty($scr['tanggalScreening']) ? ' — ' . $scr['tanggalScreening'] : '' }}</span>
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 mt-3">
                        <span class="px-3 py-2 text-base font-medium border rounded-lg {{ $scrKeputusanClass }}">
                            Keputusan Screening: <strong>{{ $scrKeputusan }}</strong>
                        </span>
                        @if ($scrFlags !== '')
                            <span class="px-3 py-2 text-base font-medium border rounded-lg bg-surface-soft border-hairline text-body">
                                Perhatian: {{ $scrFlags }}
                            </span>
                        @endif
                    </div>
                </x-border-form>

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
                    <div class="space-y-2.5">
                        <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Status Psikologis :</span>
                            <span class="text-body dark:text-gray-300">
                                {{ $statPsiko . (!empty($sp['sebutstatusPsikologis']) ? ' — ' . $sp['sebutstatusPsikologis'] : '') ?: '-' }}
                            </span>
                        </p>
                        <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Status Mental :</span>
                            <span class="text-body dark:text-gray-300">
                                {{ ($sm['statusMental'] ?? '-') . (!empty($sm['keteranganStatusMental']) ? ' — ' . $sm['keteranganStatusMental'] : '') }}
                            </span>
                        </p>
                    </div>
                </x-border-form>

                {{-- ANAMNESA + TANDA VITAL + NUTRISI --}}
                <div class="grid grid-cols-3 gap-4 mb-4">

                    {{-- Anamnesa (2/3) --}}
                    <x-border-form title="Anamnesa" class="col-span-2">
                        <div class="space-y-2.5">
                            <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Keluhan Utama :</span>
                                <span
                                    class="text-body dark:text-gray-300">{{ $txn['anamnesa']['keluhanUtama']['keluhanUtama'] ?? '-' }}</span>
                            </p>
                            <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Screening Batuk :</span>
                                <span
                                    class="text-body dark:text-gray-300">{{ $txn['anamnesa']['screeningBatuk'] ?? '-' }}</span>
                            </p>

                            {{-- Skala Nyeri --}}
                            {{-- PENILAIAN — tampilkan SEMUA record asesmen (array) + waktu --}}
                            @php $nyeriRec = collect($txn['penilaian']['nyeri'] ?? [])->filter(fn($x) => filled(data_get($x, 'nyeri.nyeriMetode.nyeriMetode'))); @endphp
                                                            <div class="flex gap-3 pb-1.5 border-b border-hairline-soft dark:border-gray-800">
                                    <span class="w-56 shrink-0 text-right text-muted">Skala Nyeri :</span>
                                    <div class="space-y-1 text-body dark:text-gray-300">
                                        @forelse ($nyeriRec as $n)
                                            <div><span class="text-sm font-medium text-muted-soft">{{ $n['tglPenilaian'] ?? '-' }}</span> — Metode: {{ $n['nyeri']['nyeriMetode']['nyeriMetode'] ?? '-' }} / Skor: {{ $n['nyeri']['nyeriMetode']['nyeriMetodeScore'] ?? '-' }} / {{ $n['nyeri']['nyeriKet'] ?? '-' }} / Pencetus: {{ $n['nyeri']['pencetus'] ?? '-' }} / Durasi: {{ $n['nyeri']['durasi'] ?? '-' }} / Lokasi: {{ $n['nyeri']['lokasi'] ?? '-' }}</div>
                                        @empty
                                            <span class="italic text-muted-soft">Belum dinilai</span>
                                        @endforelse
                                    </div>
                                </div>
                            @php $rjRec = collect($txn['penilaian']['resikoJatuh'] ?? [])->filter(fn($x) => filled(data_get($x, 'resikoJatuh.resikoJatuhMetode.resikoJatuhMetode'))); @endphp
                                                            <div class="flex gap-3 pb-1.5 border-b border-hairline-soft dark:border-gray-800">
                                    <span class="w-56 shrink-0 text-right text-muted">Resiko Jatuh :</span>
                                    <div class="space-y-1 text-body dark:text-gray-300">
                                        @forelse ($rjRec as $r)
                                            <div><span class="text-sm font-medium text-muted-soft">{{ $r['tglPenilaian'] ?? '-' }}</span> — Metode: {{ $r['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetode'] ?? '-' }} / Skor: {{ $r['resikoJatuh']['resikoJatuhMetode']['resikoJatuhMetodeScore'] ?? '-' }} / {{ $r['resikoJatuh']['kategoriResiko'] ?? '-' }}</div>
                                        @empty
                                            <span class="italic text-muted-soft">Belum dinilai</span>
                                        @endforelse
                                    </div>
                                </div>
                            @php $bunuhDiriRec = collect($txn['penilaian']['resikoBunuhDiri'] ?? [])->filter(fn($x) => filled(data_get($x, 'tglPenilaian'))); @endphp
                            @if ($bunuhDiriRec->isNotEmpty())
                                <div class="flex gap-3 pb-1.5 border-b border-hairline-soft dark:border-gray-800">
                                    <span class="w-56 shrink-0 text-right text-muted">Risiko Bunuh Diri (C-SSRS) :</span>
                                    <div class="space-y-1 text-body dark:text-gray-300">
                                        @foreach ($bunuhDiriRec as $entriBunuhDiri)
                                            <div><span class="text-sm font-medium text-muted-soft">{{ $entriBunuhDiri['tglPenilaian'] ?? '-' }}</span> — Skor keparahan: {{ $entriBunuhDiri['skorKeparahan'] ?? '-' }} / {{ $entriBunuhDiri['kategoriResiko'] ?? '-' }}{{ !empty($entriBunuhDiri['tindakLanjut']) ? ' / Tindak lanjut: ' . implode(', ', $entriBunuhDiri['tindakLanjut']) : '' }}</div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                            @php $dkRec = collect($txn['penilaian']['dekubitus'] ?? [])->filter(fn($x) => filled(data_get($x, 'dekubitus.dekubitus'))); @endphp
                                                            <div class="flex gap-3 pb-1.5 border-b border-hairline-soft dark:border-gray-800">
                                    <span class="w-56 shrink-0 text-right text-muted">Dekubitus :</span>
                                    <div class="space-y-1 text-body dark:text-gray-300">
                                        @forelse ($dkRec as $dk)
                                            <div><span class="text-sm font-medium text-muted-soft">{{ $dk['tglPenilaian'] ?? '-' }}</span> — {{ $dk['dekubitus']['dekubitus'] ?? '-' }} / Skor Braden: {{ $dk['dekubitus']['bradenScore'] ?? '-' }} / {{ $dk['dekubitus']['kategoriResiko'] ?? '-' }}{{ !empty($dk['dekubitus']['rekomendasi']) ? ' / ' . $dk['dekubitus']['rekomendasi'] : '' }}</div>
                                        @empty
                                            <span class="italic text-muted-soft">Belum dinilai</span>
                                        @endforelse
                                    </div>
                                </div>
                            @php $giziRec = collect($txn['penilaian']['gizi'] ?? [])->filter(fn($x) => filled(data_get($x, 'gizi.imt')) || filled(data_get($x, 'gizi.beratBadan'))); @endphp
                                                            <div class="flex gap-3 pb-1.5 border-b border-hairline-soft dark:border-gray-800">
                                    <span class="w-56 shrink-0 text-right text-muted">Gizi :</span>
                                    <div class="space-y-1 text-body dark:text-gray-300">
                                        @forelse ($giziRec as $g)
                                            <div><span class="text-sm font-medium text-muted-soft">{{ $g['tglPenilaian'] ?? '-' }}</span> — BB: {{ $g['gizi']['beratBadan'] ?? '-' }} kg / TB: {{ $g['gizi']['tinggiBadan'] ?? '-' }} cm / IMT: {{ $g['gizi']['imt'] ?? '-' }} / Skor Skrining: {{ $g['gizi']['skorSkrining'] ?? '-' }} / {{ $g['gizi']['kategoriGizi'] ?? '-' }}{{ !empty($g['gizi']['catatan']) ? ' / ' . $g['gizi']['catatan'] : '' }}</div>
                                        @empty
                                            <span class="italic text-muted-soft">Belum dinilai</span>
                                        @endforelse
                                    </div>
                                </div>

                            <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Riwayat Penyakit Sekarang :</span>
                                <span
                                    class="text-body dark:text-gray-300">{{ $txn['anamnesa']['riwayatPenyakitSekarangUmum']['riwayatPenyakitSekarangUmum'] ?? '-' }}</span>
                            </p>
                            <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Riwayat Penyakit Dahulu :</span>
                                <span
                                    class="text-body dark:text-gray-300">{{ $txn['anamnesa']['riwayatPenyakitDahulu']['riwayatPenyakitDahulu'] ?? '-' }}</span>
                            </p>
                            <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Alergi :</span>
                                <span
                                    class="text-body dark:text-gray-300">{{ \App\Support\AlergiSnomed::untukCetak($txn['anamnesa']['alergi'] ?? []) }}</span>
                            </p>

                            {{-- Rekonsiliasi Obat — TIDAK ditampilkan di RJ: entry-nya memang
                                 tidak ada di EMR RJ (skema rekonsiliasiObat dikomentari di
                                 rm-anamnesa-rj-actions.blade.php), jadi tabel ini selalu kosong.
                                 Fitur ini hidup di UGD (tab Rekonsiliasi Obat) & RI (Pengkajian Dokter).
                            <div>
                                <p class="mb-1.5 text-base text-muted">Rekonsiliasi Obat :</p>
                                <table class="w-full text-sm border-collapse">
                                    <thead>
                                        <tr class="bg-surface-soft dark:bg-gray-800">
                                            <th
                                                class="px-2.5 py-1.5 text-left text-sm font-medium text-muted border border-hairline dark:border-gray-700">
                                                Nama Obat</th>
                                            <th
                                                class="px-2.5 py-1.5 text-left text-sm font-medium text-muted border border-hairline dark:border-gray-700">
                                                Dosis</th>
                                            <th
                                                class="px-2.5 py-1.5 text-left text-sm font-medium text-muted border border-hairline dark:border-gray-700">
                                                Rute</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($txn['anamnesa']['rekonsiliasiObat'] ?? [] as $obat)
                                            <tr>
                                                <td
                                                    class="px-2.5 py-1.5 text-body dark:text-gray-300 border border-hairline dark:border-gray-700">
                                                    {{ $obat['namaObat'] ?? '-' }}</td>
                                                <td
                                                    class="px-2.5 py-1.5 text-body dark:text-gray-300 border border-hairline dark:border-gray-700">
                                                    {{ $obat['dosis'] ?? '-' }}</td>
                                                <td
                                                    class="px-2.5 py-1.5 text-body dark:text-gray-300 border border-hairline dark:border-gray-700">
                                                    {{ $obat['rute'] ?? '-' }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="3"
                                                    class="px-2.5 py-1.5 text-center text-muted-soft border border-hairline dark:border-gray-700">
                                                    Tidak ada data</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            --}}
                        </div>
                    </x-border-form>

                    {{-- Tanda Vital + Nutrisi (1/3) --}}
                    <div class="space-y-4">
                        <x-border-form title="Tanda Vital">
                            @php $tv = $txn['pemeriksaan']['tandaVital'] ?? []; @endphp
                            <div class="space-y-2.5">
                                <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0">
                                    <span class="w-56 shrink-0 text-right text-muted">Tekanan Darah :</span>
                                    <span class="text-base font-semibold text-ink dark:text-gray-200">
                                        {{ ($tv['sistolik'] ?? '-') . ' / ' . ($tv['distolik'] ?? '-') }}
                                        <span class="text-sm font-normal text-muted-soft">mmHg</span>
                                    </span>
                                </p>
                                <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0">
                                    <span class="w-56 shrink-0 text-right text-muted">Nadi :</span>
                                    <span class="text-base font-semibold text-ink dark:text-gray-200">
                                        {{ $tv['frekuensiNadi'] ?? '-' }}
                                        <span class="text-sm font-normal text-muted-soft">x/mnt</span>
                                    </span>
                                </p>
                                <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0">
                                    <span class="w-56 shrink-0 text-right text-muted">Suhu :</span>
                                    <span class="text-base font-semibold text-ink dark:text-gray-200">
                                        {{ $tv['suhu'] ?? '-' }}
                                        <span class="text-sm font-normal text-muted-soft">°C</span>
                                    </span>
                                </p>
                                <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0">
                                    <span class="w-56 shrink-0 text-right text-muted">Pernafasan :</span>
                                    <span class="text-base font-semibold text-ink dark:text-gray-200">
                                        {{ $tv['frekuensiNafas'] ?? '-' }}
                                        <span class="text-sm font-normal text-muted-soft">x/mnt</span>
                                    </span>
                                </p>
                                <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0">
                                    <span class="w-56 shrink-0 text-right text-muted">SPO2 :</span>
                                    <span class="text-base font-semibold text-ink dark:text-gray-200">
                                        {{ $tv['spo2'] ?? '-' }}
                                        <span class="text-sm font-normal text-muted-soft">%</span>
                                    </span>
                                </p>
                                <p class="flex justify-between">
                                    <span class="w-56 shrink-0 text-right text-muted">GDA :</span>
                                    <span class="text-base font-semibold text-ink dark:text-gray-200">
                                        {{ $tv['gda'] ?? '-' }}
                                        <span class="text-sm font-normal text-muted-soft">mg/dL</span>
                                    </span>
                                </p>
                            </div>
                        </x-border-form>

                        <x-border-form title="Nutrisi">
                            @php $nut = $txn['pemeriksaan']['nutrisi'] ?? []; @endphp
                            <div class="space-y-2.5">
                                <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0">
                                    <span class="w-56 shrink-0 text-right text-muted">Berat Badan :</span>
                                    <span class="text-base font-semibold text-ink dark:text-gray-200">
                                        {{ $nut['bb'] ?? '-' }}
                                        <span class="text-sm font-normal text-muted-soft">Kg</span>
                                    </span>
                                </p>
                                <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0">
                                    <span class="w-56 shrink-0 text-right text-muted">Tinggi Badan :</span>
                                    <span class="text-base font-semibold text-ink dark:text-gray-200">
                                        {{ $nut['tb'] ?? '-' }}
                                        <span class="text-sm font-normal text-muted-soft">cm</span>
                                    </span>
                                </p>
                                <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0">
                                    <span class="w-56 shrink-0 text-right text-muted">Index Masa Tubuh :</span>
                                    <span class="text-base font-semibold text-ink dark:text-gray-200">
                                        {{ $nut['imt'] ?? '-' }}
                                        <span class="text-sm font-normal text-muted-soft">Kg/M²</span>
                                    </span>
                                </p>
                                <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0">
                                    <span class="w-56 shrink-0 text-right text-muted">Lingkar Kepala :</span>
                                    <span class="text-base font-semibold text-ink dark:text-gray-200">
                                        {{ $nut['lk'] ?? '-' }}
                                        <span class="text-sm font-normal text-muted-soft">cm</span>
                                    </span>
                                </p>
                                <p class="flex justify-between">
                                    <span class="w-56 shrink-0 text-right text-muted">Lingkar Lengan Atas :</span>
                                    <span class="text-base font-semibold text-ink dark:text-gray-200">
                                        {{ $nut['lila'] ?? '-' }}
                                        <span class="text-sm font-normal text-muted-soft">cm</span>
                                    </span>
                                </p>
                            </div>
                        </x-border-form>
                    </div>
                </div>

                {{-- KEADAAN UMUM + FUNGSIONAL + PEMERIKSAAN FISIK + ANATOMI — 1 baris --}}
                <div class="grid grid-cols-1 gap-4 mb-4 sm:grid-cols-2 lg:grid-cols-4">
                    <x-border-form title="Keadaan Umum">
                        <p class="text-base text-ink dark:text-gray-200">
                            {{ $txn['pemeriksaan']['tandaVital']['keadaanUmum'] ?? 'BAIK' }}
                            &nbsp;/&nbsp;
                            <span
                                class="font-medium">{{ $txn['pemeriksaan']['tandaVital']['tingkatKesadaran'] ?? '-' }}</span>
                        </p>
                    </x-border-form>

                    <x-border-form title="Fungsional">
                        @php $fn = $txn['pemeriksaan']['fungsional'] ?? []; @endphp
                        <div class="space-y-2.5">
                            <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Alat Bantu :</span>
                                <span class="text-body dark:text-gray-300">{{ $fn['alatBantu'] ?? '-' }}</span>
                            </p>
                            <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Prothesa :</span>
                                <span class="text-body dark:text-gray-300">{{ $fn['prothesa'] ?? '-' }}</span>
                            </p>
                            <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Cacat Tubuh :</span>
                                <span class="text-body dark:text-gray-300">{{ $fn['cacatTubuh'] ?? '-' }}</span>
                            </p>
                            @php
                                $suspekAK = $txn['pemeriksaan']['suspekAkibatKerja']['suspekAkibatKerja'] ?? '-';
                                $ketAK = trim($txn['pemeriksaan']['suspekAkibatKerja']['keteranganSuspekAkibatKerja'] ?? '');
                            @endphp
                            <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Suspek Kecelakaan Kerja :</span>
                                <span
                                    class="text-body dark:text-gray-300">{{ $suspekAK }}@if ($suspekAK === 'Ya' && $ketAK !== '') &nbsp;({{ $ketAK }})@endif</span>
                            </p>
                        </div>
                    </x-border-form>
                    <x-border-form title="Pemeriksaan Fisik & Uji Fungsi">
                        <p class="text-base text-body whitespace-pre-line dark:text-gray-300">
                            {{ $txn['pemeriksaan']['fisik'] ?? '-' }}
                            {{ $txn['pemeriksaan']['FisikujiFungsi']['FisikujiFungsi'] ?? '' }}
                        </p>
                    </x-border-form>

                    <x-border-form title="Anatomi">
                        @if (!empty($txn['pemeriksaan']['anatomi']))
                            @foreach ($txn['pemeriksaan']['anatomi'] as $key => $pAnatomi)
                                @if (!empty($pAnatomi['kelainan']) && $pAnatomi['kelainan'] !== 'Tidak Diperiksa')
                                    <p class="text-base text-body dark:text-gray-300">
                                        <span class="font-semibold">{{ strtoupper($key) }}</span>:
                                        {{ $pAnatomi['kelainan'] }} — {{ $pAnatomi['desc'] ?? '-' }}
                                    </p>
                                @endif
                            @endforeach
                        @else
                            <p class="text-base text-muted">-</p>
                        @endif
                    </x-border-form>
                </div>

                {{-- PENUNJANG + DIAGNOSIS + PROSEDUR --}}
                @php
                    // Prioritas freetext dari dokter; fallback ke keterangan ICD-10 (kode disembunyikan).
                    $diagnosisDisplay = trim((string) ($txn['diagnosisFreeText'] ?? ''));
                    if ($diagnosisDisplay === '') {
                        $diagnosisDescriptions = collect($txn['diagnosis'] ?? [])
                            ->pluck('diagDesc')
                            ->map(fn($desc) => trim((string) $desc))
                            ->filter()
                            ->values()
                            ->all();
                        $diagnosisDisplay = $diagnosisDescriptions ? implode("\n", $diagnosisDescriptions) : '-';
                    }

                    // Prioritas freetext dari dokter; fallback ke keterangan ICD-9-CM (kode disembunyikan).
                    $prosedurDisplay = trim((string) ($txn['procedureFreeText'] ?? ''));
                    if ($prosedurDisplay === '') {
                        $procedureDescriptions = collect($txn['procedure'] ?? [])
                            ->pluck('procedureDesc')
                            ->map(fn($desc) => trim((string) $desc))
                            ->filter()
                            ->values()
                            ->all();
                        $prosedurDisplay = $procedureDescriptions ? implode("\n", $procedureDescriptions) : '-';
                    }
                @endphp
                <x-border-form class="mb-4">
                    <div class="space-y-2.5">
                        <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Penunjang :</span>
                            <span
                                class="text-body dark:text-gray-300">{{ $txn['pemeriksaan']['penunjang'] ?? '-' }}</span>
                        </p>
                        <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Diagnosis :</span>
                            <span
                                class="font-semibold text-ink dark:text-gray-100">{!! nl2br(e($diagnosisDisplay)) !!}</span>
                        </p>
                        <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Prosedur :</span>
                            <span
                                class="text-body dark:text-gray-300">{!! nl2br(e($prosedurDisplay)) !!}</span>
                        </p>
                    </div>
                </x-border-form>

                {{-- TINDAK LANJUT + TERAPI --}}
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <x-border-form title="Tindak Lanjut">
                        <p class="text-base text-ink dark:text-gray-200">
                            {{ $txn['perencanaan']['tindakLanjut']['tindakLanjut'] ?? '-' }}
                            @if (!empty($txn['perencanaan']['tindakLanjut']['keteranganTindakLanjut']))
                                / {{ $txn['perencanaan']['tindakLanjut']['keteranganTindakLanjut'] }}
                            @endif
                        </p>
                    </x-border-form>

                    <x-border-form title="Terapi">
                        <p class="text-base text-ink whitespace-pre-line dark:text-gray-200">
                            {{ $txn['perencanaan']['terapi']['terapi'] ?? '-' }}
                        </p>
                    </x-border-form>
                </div>

                {{-- TTD PERAWAT + DOKTER --}}
                <x-border-form>
                    <div class="flex items-end justify-between">

                        {{-- Perawat --}}
                        <div class="text-center min-w-[160px]">
                            <p class="mb-2 text-base text-muted">Perawat / Terapis</p>
                            <div class="flex items-center justify-center h-20">
                                @isset($txn['anamnesa']['pengkajianPerawatan']['perawatPenerima'])
                                    @if ($txn['anamnesa']['pengkajianPerawatan']['perawatPenerima'])
                                        @isset($txn['anamnesa']['pengkajianPerawatan']['perawatPenerimaCode'])
                                            @if ($txn['anamnesa']['pengkajianPerawatan']['perawatPenerimaCode'])
                                                @php
                                                    $ttdPerawat = App\Models\User::where(
                                                        'myuser_code',
                                                        $txn['anamnesa']['pengkajianPerawatan']['perawatPenerimaCode'],
                                                    )->value('myuser_ttd_image');
                                                @endphp
                                                @if (!empty($ttdPerawat))
                                                    <img class="object-contain h-16 mx-auto"
                                                        src="{{ asset('storage/' . $ttdPerawat) }}" alt="TTD Perawat">
                                                @endif
                                            @endif
                                        @endisset
                                    @endif
                                @endisset
                            </div>
                            <div class="pt-1 border-t border-hairline dark:border-gray-700">
                                <p class="text-base font-semibold text-ink dark:text-gray-200">
                                    {{ isset($txn['anamnesa']['pengkajianPerawatan']['perawatPenerima'])
                                        ? strtoupper($txn['anamnesa']['pengkajianPerawatan']['perawatPenerima'])
                                        : '.................................' }}
                                </p>
                            </div>
                        </div>

                        {{-- Dokter --}}
                        <div class="text-center min-w-[160px]">
                            <p class="mb-2 text-base text-muted">Tulungagung, {{ $d['tglCetak'] ?? '-' }}</p>
                            <div class="flex items-center justify-center h-20">
                                @isset($txn['perencanaan']['pengkajianMedis']['drPemeriksa'])
                                    @if ($txn['perencanaan']['pengkajianMedis']['drPemeriksa'])
                                        @php
                                            $ttdDokter = App\Models\User::where(
                                                'myuser_code',
                                                $txn['drId'] ?? '',
                                            )->value('myuser_ttd_image');
                                        @endphp
                                        @if (!empty($ttdDokter))
                                            <img class="object-contain h-16 mx-auto"
                                                src="{{ asset('storage/' . $ttdDokter) }}" alt="TTD Dokter">
                                        @endif
                                    @endif
                                @endisset
                            </div>
                            <div class="pt-1 border-t border-hairline dark:border-gray-700">
                                <p class="text-base font-semibold text-ink dark:text-gray-200">
                                    {{ $d['namaDokter'] ?? 'dr. .................' }}
                                </p>
                                @if (!empty($d['strDokter']))
                                    <p class="text-base text-muted">STR: {{ $d['strDokter'] }}</p>
                                @endif
                            </div>
                        </div>

                    </div>
                </x-border-form>

                </div>{{-- /tab resume --}}

                {{-- ════ TAB: MODUL DOKUMEN (view-only — data + cetak) ════ --}}
                @php
                    // Chip tanggal (hijau brand) — kosong → tidak tampil
                    $dateChip = fn($d) => filled($d)
                        ? '<span class="inline-flex items-center px-2 py-0.5 text-xs font-semibold rounded-full bg-brand-green/10 text-brand-green dark:bg-brand-green/20 dark:text-brand-lime">' . e($d) . '</span>'
                        : '';
                    // Pill empty-state netral + ikon tanya
                    $emptyPill = fn($txt) => '<div class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm rounded-lg text-muted-soft bg-surface-soft dark:bg-gray-800/60"><svg class="w-4 h-4 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" /></svg>' . e($txt) . '</div>';
                @endphp
                <div x-show="tab === 'dokumen'" x-cloak class="px-6 py-5 space-y-5">

                    {{-- ── General Consent — viewer (Lihat + Cetak dalam modal) ── --}}
                    <livewire:pages::components.rekam-medis.r-j.dokumen-view.general-consent-view-rj :rjNo="$rjNo"
                        :consent="$txn['generalConsentPasienRJ'] ?? []" wire:key="rm-view-gc-rj-{{ $rjNo }}" />

                    {{-- ── Inform Consent — viewer (Lihat + Cetak dalam modal) ── --}}
                    <livewire:pages::components.rekam-medis.r-j.dokumen-view.inform-consent-view-rj :rjNo="$rjNo"
                        :entries="$txn['informConsentPasienRJ'] ?? []" wire:key="rm-view-ic-rj-{{ $rjNo }}" />
                </div>

                {{-- ════ TAB: HASIL PENUNJANG (lab / radiologi / upload — view-only) ════ --}}
                @php $regNoPenunjang = (string) ($txn['regNo'] ?? ''); @endphp
                <div x-show="tab === 'penunjang'" x-cloak class="px-6 py-5" x-data="{ sub: 'laboratorium' }">
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
                            :regNo="$regNoPenunjang" wire:key="rm-rj-penunjang-lab-{{ $regNoPenunjang }}" />
                        <livewire:pages::components.rekam-medis.penunjang.lab-luar-display.lab-luar-display
                            :regNo="$regNoPenunjang" wire:key="rm-rj-penunjang-lab-luar-{{ $regNoPenunjang }}" />
                    </div>

                    <div x-show="sub === 'radiologi'" x-cloak>
                        <livewire:pages::components.rekam-medis.penunjang.radiologi-display.radiologi-display
                            :regNo="$regNoPenunjang" wire:key="rm-rj-penunjang-rad-{{ $regNoPenunjang }}" />
                    </div>

                    <div x-show="sub === 'upload'" x-cloak>
                        <livewire:pages::components.rekam-medis.penunjang.upload-penunjang-display.upload-penunjang-display
                            :regNo="$regNoPenunjang" wire:key="rm-rj-penunjang-upload-{{ $regNoPenunjang }}" />
                    </div>
                </div>

            </div>{{-- end body --}}

            {{-- ── FOOTER ──────────────────────────────────────────────── --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <x-rm.record-nav :pos="$navPos" :total="$navTotal" />
                    <div class="flex gap-2">
                        <x-secondary-button type="button" wire:click="closeModal">
                            Tutup
                        </x-secondary-button>

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
