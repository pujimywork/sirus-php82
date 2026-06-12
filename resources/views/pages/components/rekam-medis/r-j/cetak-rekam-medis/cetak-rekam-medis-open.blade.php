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

    /* =====================================================
     | OPEN → load ke property, buka modal preview
     ===================================================== */
    #[On('cetak-rekam-medis.open')]
    public function open(int $rjNo): void
    {
        $this->rjNo = $rjNo;
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
     | CETAK DOKUMEN (mandiri — reuse print partial modul-dokumen)
     | Komponen cetak asli tidak selalu ada di halaman (mis. daftar
     | bulanan), jadi PDF digenerate langsung dari sini.
     ===================================================== */
    public function cetakGeneralConsentRj(): mixed
    {
        $rjNo = $this->rjNo;
        $dataRJ = $this->findDataRJ($rjNo);
        if (empty($dataRJ)) {
            $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.');
            return null;
        }

        $consent = $dataRJ['generalConsentPasienRJ'] ?? null;
        if (empty($consent) || empty($consent['signature'])) {
            $this->dispatch('toast', type: 'error', message: 'Data General Consent belum tersedia.');
            return null;
        }

        $pasienData = $this->findDataMasterPasien($dataRJ['regNo'] ?? '');
        if (empty($pasienData)) {
            $this->dispatch('toast', type: 'error', message: 'Data pasien tidak ditemukan.');
            return null;
        }

        $pasien = $pasienData['pasien'];
        $this->hitungUmur($pasien);

        $identitasRs = DB::table('rsmst_identitases')->select('int_name', 'int_phone1', 'int_phone2', 'int_fax', 'int_address', 'int_city')->first();
        $ttdPetugasPath = $this->ttdPathDari($consent['petugasPemeriksaCode'] ?? null);

        $data = array_merge($pasien, [
            'dataRJ' => $dataRJ,
            'consent' => $consent,
            'identitasRs' => $identitasRs,
            'ttdPetugasPath' => $ttdPetugasPath,
            'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
        ]);

        set_time_limit(300);
        $pdf = Pdf::loadView('pages.components.modul-dokumen.r-j.general-consent.cetak-general-consent-rj-print', ['data' => $data])->setPaper('A4');
        return response()->streamDownload(fn() => print $pdf->output(), 'general-consent-rj-' . ($pasien['regNo'] ?? $rjNo) . '.pdf');
    }

    public function cetakInformConsentRj(?string $signatureDate = null): mixed
    {
        $rjNo = $this->rjNo;
        $dataRJ = $this->findDataRJ($rjNo);
        if (empty($dataRJ)) {
            $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.');
            return null;
        }

        $consentList = $dataRJ['informConsentPasienRJ'] ?? [];
        if (empty($consentList)) {
            $this->dispatch('toast', type: 'error', message: 'Belum ada Inform Consent yang tersimpan.');
            return null;
        }

        $consent = empty($signatureDate)
            ? collect($consentList)->sortByDesc('signatureDate')->first()
            : collect($consentList)->firstWhere('signatureDate', $signatureDate);
        if (empty($consent)) {
            $this->dispatch('toast', type: 'error', message: 'Data Inform Consent yang dipilih tidak ditemukan.');
            return null;
        }

        $pasienData = $this->findDataMasterPasien($dataRJ['regNo'] ?? '');
        if (empty($pasienData)) {
            $this->dispatch('toast', type: 'error', message: 'Data pasien tidak ditemukan.');
            return null;
        }

        $pasien = $pasienData['pasien'];
        $this->hitungUmur($pasien);

        $ttdDokterPath = $this->ttdPathDari($consent['dokterCode'] ?? null);

        $ttdDokterTindakanPath = null;
        $dokterTindakanName = null;
        if (!empty($consent['petugasPemeriksaCode'])) {
            $userRow = DB::table('users')->where('myuser_code', $consent['petugasPemeriksaCode'])->first(['myuser_ttd_image', 'myuser_name']);
            if ($userRow) {
                $dokterTindakanName = $userRow->myuser_name ?? null;
                if (!empty($userRow->myuser_ttd_image) && file_exists(public_path('storage/' . $userRow->myuser_ttd_image))) {
                    $ttdDokterTindakanPath = public_path('storage/' . $userRow->myuser_ttd_image);
                }
            }
            if (empty($dokterTindakanName)) {
                $dokterTindakanName = DB::table('rsmst_doctors')->where('dr_id', $consent['petugasPemeriksaCode'])->value('dr_name');
            }
        }

        $identitasRs = DB::table('rsmst_identitases')->select('int_name', 'int_phone1', 'int_address', 'int_city')->first();

        $data = array_merge($pasien, [
            'dataRJ' => $dataRJ,
            'consent' => $consent,
            'identitasRs' => $identitasRs,
            'ttdDokterPath' => $ttdDokterPath,
            'ttdDokterTindakanPath' => $ttdDokterTindakanPath,
            'dokterTindakanName' => $dokterTindakanName ?? ($consent['petugasPemeriksa'] ?? null),
            'tglCetak' => Carbon::now(config('app.timezone'))->translatedFormat('d F Y'),
        ]);

        set_time_limit(300);
        $pdf = Pdf::loadView('pages.components.modul-dokumen.r-j.inform-consent.cetak-inform-consent-rj-print', ['data' => $data])->setPaper('A4');
        return response()->streamDownload(fn() => print $pdf->output(), 'inform-consent-rj-' . ($pasien['regNo'] ?? $rjNo) . '.pdf');
    }

    /** Hitung umur (mutasi $pasien['thn']) — pola sama dengan komponen cetak modul-dokumen. */
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

    /* =====================================================
     | CLOSE
     ===================================================== */
    public function closeModal(): void
    {
        $this->dataDaftarPoliRJ = [];
        $this->rjNo = null;
        $this->dispatch('close-modal', name: 'preview-rekam-medis');
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
                </nav>
            </div>

            {{-- ── BODY (scroll) ── --}}
            <div class="flex-1 overflow-y-auto bg-surface-soft/70 dark:bg-gray-950/20">

                {{-- ════ TAB: RESUME ════ --}}
                <div x-show="tab === 'resume'" class="px-6 py-5">

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
                                    class="text-body dark:text-gray-300">{{ $txn['anamnesa']['alergi']['alergi'] ?? '-' }}</span>
                            </p>

                            {{-- Riwayat Pemakaian Obat (dh. Rekonsiliasi Obat) --}}
                            <div>
                                <p class="mb-1.5 text-base text-muted">Riwayat Pemakaian Obat :</p>
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
                <x-border-form class="mb-4">
                    <div class="space-y-2.5">
                        <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Penunjang :</span>
                            <span
                                class="text-body dark:text-gray-300">{{ $txn['pemeriksaan']['penunjang'] ?? '-' }}</span>
                        </p>
                        <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Diagnosis :</span>
                            <span
                                class="font-semibold text-ink dark:text-gray-100">{{ $txn['diagnosisFreeText'] ?? '-' }}</span>
                        </p>
                        <p class="flex gap-3 text-base leading-relaxed pb-1.5 border-b border-hairline-soft dark:border-gray-800 last:border-0"><span class="w-56 shrink-0 text-right text-muted">Prosedur :</span>
                            <span
                                class="text-body dark:text-gray-300">{{ $txn['procedureFreeText'] ?? '-' }}</span>
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

                    {{-- ── General Consent ── --}}
                    @php $gc = $txn['generalConsentPasienRJ'] ?? []; @endphp
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
                                    wire:click="cetakGeneralConsentRj" wire:loading.attr="disabled">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                    Cetak
                                </x-secondary-button>
                            </div>
                        @else
                            {!! $emptyPill('Belum diisi') !!}
                        @endif
                    </x-border-form>

                    {{-- ── Inform Consent (per tindakan) ── --}}
                    @php $icList = collect($txn['informConsentPasienRJ'] ?? [])->filter(fn($x) => filled(data_get($x, 'signatureDate')) || filled(data_get($x, 'tindakan'))); @endphp
                    <x-border-form title="Inform Consent">
                        @forelse ($icList as $ic)
                            <div class="flex items-center justify-between gap-3 py-2.5 border-b border-hairline-soft last:border-0 dark:border-gray-800">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <span class="text-base font-semibold text-ink dark:text-gray-200">{{ data_get($ic, 'tindakan') ?: '(Tanpa nama tindakan)' }}</span>
                                        {!! $dateChip(data_get($ic, 'signatureDate')) !!}
                                    </div>
                                    <div class="mt-0.5 text-sm text-muted"><span class="text-body">Dokter:</span> {{ data_get($ic, 'dokter') ?: '-' }}</div>
                                </div>
                                @if (filled(data_get($ic, 'signatureDate')))
                                    <x-secondary-button type="button" class="gap-1.5 shrink-0"
                                        wire:click="cetakInformConsentRj('{{ data_get($ic, 'signatureDate') }}')" wire:loading.attr="disabled">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                        Cetak
                                    </x-secondary-button>
                                @endif
                            </div>
                        @empty
                            {!! $emptyPill('Belum diisi') !!}
                        @endforelse
                    </x-border-form>
                </div>

            </div>{{-- end body --}}

            {{-- ── FOOTER ──────────────────────────────────────────────── --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-end gap-3">
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
