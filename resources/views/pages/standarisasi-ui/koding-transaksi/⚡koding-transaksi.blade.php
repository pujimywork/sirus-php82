<?php

use Livewire\Component;

// Tutorial standarisasi koding domain TRANSAKSI (RJ/UGD/RI: pendaftaran → pelayanan →
// kasir + lintas-modul EMR / modul dokumen / administrasi).
// Gaya sama dgn koding-master: sidebar per-submenu, snippet = nowdoc (aman compiler Blade).
new class extends Component {
    public function snippets(): array
    {
        return [

'clob-read' => <<<'TXT'
// Detail transaksi disimpan sebagai SATU kolom JSON CLOB di tabel header
// (rstxn_rjhdrs.datadaftarpolirj_json, dst.) — bukan puluhan tabel normalized.

// Cara baca yang benar — lewat trait jalur:
$data = $this->findDataRJ($rjNo);        // EmrRJTrait  → rsview_rjkasir
$data = $this->findDataUGD($ugdNo);      // EmrUGDTrait → rstxn_ugdhdrs
$data = $this->findDataRI($riHdrNo);     // EmrRITrait  → rsview_rihdrs

// Di balik trait: App\Support\OracleLob::read(raw, table, keyCol, keyVal, lobCol)
// → baca locator CLOB dgn aman; kalau kena ORA-01555/ORA-22924 (snapshot too old
//   setelah save-all), otomatis re-fetch lewat statement segar.

// JANGAN: TO_CHAR(kolom_json) / DBMS_LOB.SUBSTR di query —
//         JSON > 32.767 byte TERPOTONG diam-diam → data korup saat disimpan balik.
TXT,

'rmw' => <<<'TXT'
// Pola tulis JSON yang WAJIB: read-modify-write dalam transaksi + row lock.
DB::transaction(function () use ($rjNo) {
    $this->lockRJRow($rjNo);                     // SELECT ... FOR UPDATE — wajib duluan
    $data = $this->findDataRJ($rjNo);            // baca JSON TERKINI (setelah lock)

    $data['anamnesa']['keluhanUtama'] = '...';   // mutasi array di PHP

    $this->updateJsonRJ($rjNo, $data);           // tulis balik (validasi rjNo payload cocok)
});

// JANGAN: findData di awal request → update di akhir TANPA lock.
// Dua user menyimpan bersamaan = perubahan salah satunya HILANG (last write wins).
// Catatan RI: findDataRI membaca VIEW (rsview_rihdrs) — lock tetap ke tabel aslinya.
TXT,

'list-query' => <<<'TXT'
#[Computed]
public function baseQuery()
{
    // Subquery penunjang (lab/rad) di-SCOPE ke rentang tanggal via JOIN ke header.
    // Tanpa scope ini Oracle full-scan jutaan baris riwayat → list lemot.
    $lab = DB::table('lbtxn_checkuphdrs as l')
        ->join('rstxn_rjhdrs as a', 'a.rj_no', '=', 'l.rj_no')
        ->whereBetween(DB::raw('trunc(a.rj_date)'), [$start, $end])
        ->select('l.rj_no', DB::raw('count(*) as jml_lab'))
        ->groupBy('l.rj_no');

    return DB::table('rstxn_rjhdrs as a')
        ->leftJoinSub($lab, 'lab', 'lab.rj_no', '=', 'a.rj_no')
        ->whereBetween(DB::raw('trunc(a.rj_date)'), [$start, $end])
        ->orderByDesc('a.rj_no');
}

#[Computed]
public function rows()
{
    // Paginate di DB — lalu transform HANYA page aktif (±10 baris).
    $p = $this->baseQuery()->paginate($this->itemsPerPage);
    $p->getCollection()->transform(fn ($r) => $this->transformRjRow($r));
    return $p;
}

// transformRjRow(): DI SINILAH OracleLob::read + json_decode dilakukan —
// decode CLOB hanya untuk baris yang tampil, bukan seluruh hasil query.
TXT,

'emr-host' => <<<'TXT'
// EMR = MODAL full-screen (bukan route), di-embed sebagai sibling di pelayanan.
// Host dibuka via event dari list, lalu MENYEBARKAN event open ke tiap section:

#[On('emr-rj.rekam-medis.open')]
public function openRekamMedisPerawat(int $rjNo): void
{
    $this->resetForm();
    $this->rjNo = $rjNo;
    $this->dataDaftarPoliRJ = $this->findDataRJ($rjNo);

    if ($this->checkEmrRJStatus($rjNo)) {
        $this->isFormLocked = true;              // EMR terkunci → semua section read-only
    }

    $this->dispatch('open-modal', name: 'rm-perawat-actions');
    $this->dispatch('open-rm-anamnesa-rj', $rjNo);       // S
    $this->dispatch('open-rm-pemeriksaan-rj', $rjNo);    // O
    $this->dispatch('open-rm-penilaian-rj', $rjNo);      // A
    $this->dispatch('open-rm-diagnosa-rj', $rjNo);       // A
    $this->dispatch('open-rm-perencanaan-rj', $rjNo);    // P
}
TXT,

'emr-section' => <<<'TXT'
{{-- Host me-mount tiap section sebagai CHILD livewire — selalu :rjNo + wire:key --}}
<livewire:pages::transaksi.rj.emr-rj.anamnesa.rm-anamnesa-rj-actions
    :rjNo="$rjNo" wire:key="anamnesa-rj-{{ $rjNo }}" />

{{-- Save-all: dirty-modal mem-broadcast event save ke tiap section --}}
<x-dirty-modal-content name="rm-perawat-actions" event="refresh-after-rj.saved"
    :save-events="[
        'save-rm-anamnesa-rj',
        'save-rm-pemeriksaan-rj',
        'save-rm-diagnosa-rj',
        'save-rm-perencanaan-rj',
    ]" :wireKey="$this->renderKey('modal-emr-rj', [$rjNo ?? 'new'])">

{{-- Tombol "Simpan Semua" — tiap section menyimpan dgn toast SILENT
     (satu toast gabungan, bukan 5 toast beruntun) --}}
events.forEach(e => Livewire.dispatch(e, { silent: true }))
TXT,

'emr-save' => <<<'TXT'
// Di dalam SECTION (mis. anamnesa): listener save menerima flag silent.
#[On('save-rm-anamnesa-rj')]
public function save(bool $silent = false): void
{
    // validateWithToast() = validate() + auto-toast error (WithValidationToastTrait)
    $this->validateWithToast($rules, $messages, $attributes);

    DB::transaction(function () {
        $this->lockRJRow($this->rjNo);
        $data = $this->findDataRJ($this->rjNo);
        $data['anamnesa'] = array_replace($data['anamnesa'] ?? [], $this->formAnamnesa);
        $this->updateJsonRJ($this->rjNo, $data);
    });

    if (! $silent) {
        $this->dispatch('toast', type: 'success', message: 'Anamnesa tersimpan.');
    }
}
TXT,

'dokumen-flow' => <<<'TXT'
// Modul dokumen bertanda tangan = dua tahap: DRAFT → TTD-KUNCI.

// 1) Draft — boleh simpan sebagian; ikut dipanggil save-all EMR (silent):
#[On('save-rm-general-consent-rj')]
public function save(bool $silent = false): void
{
    // simpan apa adanya ke JSON (belum divalidasi lengkap, belum dikunci)
}

// 2) Finalize — TTD petugas = validasi LENGKAP + kunci form:
public function setPetugasPemeriksa(): void
{
    if ($this->isFormLocked) return;            // EMR locked / sudah TTD → tolak

    $this->validateWithToast($rulesLengkap, ...);
    // stempel: nama + myuser_code (ttdCode) + tanggal → isFormLocked = true

    $this->dispatch('refresh-modul-dokumen-rj-data', rjNo: $this->rjNo);
}

// Teks klausul dokumen legal = VERSIONING (App\Support\*Clause) —
// cetak ulang record lama memakai redaksi SAAT DITANDATANGANI.
// Baca docs/clause-versioning.md sebelum mengubah teks klausul apa pun.
TXT,

'administrasi' => <<<'TXT'
// Administrasi = modal rekap biaya per kunjungan. Tiap POS = file partial sendiri
// (jasa-dokter, jasa-medis, jasa-karyawan, obat, laboratorium, radiologi, lain-lain...).

public int $sumTotalRJ = 0;

public function sumAll(): void
{
    $this->sumTotalRJ =
        $this->sumRsAdmin + $this->sumRjAdmin + $this->sumPoliPrice
        + $this->sumJasaKaryawan + $this->sumJasaDokter + $this->sumJasaMedis
        + $this->sumObat + $this->sumLaboratorium + $this->sumRadiologi
        + $this->sumLainLain;
}

// Selesai administrasi → setSelesaiAdministrasiStatus()
// → pasien naik ke atas di antrian kasir (wire:poll.30s).
// RI: pos lebih banyak (visit, konsul, room, pindah-kamar, OK, obat-pinjam,
//     bon-resep, transfer UGD/RJ) dan billing per-item ke imtxn_slshdrs/slsdtls.
TXT,

'kasir-post' => <<<'TXT'
// Posting bayar (contoh kasir RI) — selalu dalam transaksi + lock:
public function postTransaksi(): void
{
    $this->validateWithToast(['bayar' => 'required|numeric|min:0'], ...);

    DB::transaction(function () {
        DB::table('imtxn_slshdrs')->where('sls_no', $this->slsNo)
            ->lockForUpdate()->first();

        // bayar < total → jadi BON (sls_bon) + insert rstxn_ribonobats
        // bayar ≥ total → lunas, hitung kembalian
        DB::table('imtxn_slshdrs')->where('sls_no', $this->slsNo)->update([...]);
    });
}
TXT,

'role-audit' => <<<'TXT'
{{-- Guard aksi per role (Spatie) — contoh posting bayar kasir --}}
@hasanyrole('Admin|Tu|Manager Umum|Supervisor Tu')
    <x-primary-button type="button" wire:click="postTransaksi">Posting Bayar</x-primary-button>
@endhasanyrole
TXT,

'audit-log' => <<<'TXT'
// Audit log terpadu — setiap aksi admin/MR yang mengubah data pasien
// dicatat ke JSON (AdministrasiRJ.userLogs) dgn kategori ADMIN atau MR:
$this->appendAdminLogRJ($rjNo, 'Ubah tanggal kunjungan 01-07 → 02-07', 'ADMIN');
$this->appendAdminLogUGD($ugdNo, 'Koreksi diagnosa oleh Casemix', 'MR');

// Ditampilkan di tab "Log Aktivitas" EMR. Teks lewat App\Support\LogText::sanitize.
TXT,

'adopsi-tree' => <<<'TXT'
transaksi/<jalur>/
├── daftar-<jalur>/            # pendaftaran + list harian (list + actions modal)
├── daftar-<jalur>-bulanan/    # rekap bulanan
├── pelayanan-<jalur>/         # antrian dokter/perawat (RI: TIDAK ADA — dari daftar-ri)
├── emr-<jalur>/               # host modal + section per-folder + modul-dokumen/
│   ├── erm-<jalur>.blade.php
│   ├── anamnesa/  pemeriksaan/  penilaian/  diagnosa/  perencanaan/
│   └── modul-dokumen/         # form dokumen bertanda tangan
├── administrasi-<jalur>/      # pos biaya (modal, dibuka dari EMR & antrian kasir)
├── antrian-kasir-<jalur>/     # antrian kasir (wire:poll.30s)
├── antrian-apotek-<jalur>/    # antrian apotek / e-resep
├── display-pasien-<jalur>/    # kartu identitas pasien (header EMR, administrasi, dll)
├── eresep-<jalur>/            # e-resep racikan + non-racikan
└── idrg/                      # bridging casemix iDRG
TXT,

        ];
    }
};
?>

<div>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=source-sans-3:400,500,600,700|jetbrains-mono:400,500&display=swap" rel="stylesheet" />
    <style>[x-cloak] { display: none !important; }</style>

    @php
        $snip = $this->snippets();

        $menuGroups = [
            'Mulai' => [
                'pendahuluan' => 'Pendahuluan',
                'alur'        => 'Alur Pasien & Routing',
                'data'        => 'Data Inti & JSON CLOB',
            ],
            'Tahapan' => [
                'pendaftaran'  => 'Pendaftaran',
                'list'         => 'List Transaksi & Performa',
                'emr'          => 'EMR (Rekam Medis)',
                'dokumen'      => 'Modul Dokumen',
                'administrasi' => 'Administrasi & Kasir',
            ],
            'Adopsi' => [
                'adopsi'    => 'Checklist Adopsi',
                'referensi' => 'Trait & Referensi',
            ],
        ];

        $labels = array_merge(...array_values($menuGroups));
    @endphp

    <div class="ds" style="min-height:100vh"
        x-data='{
            section: "pendahuluan",
            order: @json(array_keys($labels)),
            labels: @json($labels),
            idx() { return this.order.indexOf(this.section) },
            go(s) {
                this.section = s;
                history.replaceState(null, "", "#" + s);
                window.scrollTo({ top: 0, behavior: "smooth" });
            },
            init() {
                const h = window.location.hash.slice(1);
                if (this.order.includes(h)) this.section = h;
            }
        }'>
        <div class="ds-section" style="padding-top:32px; padding-bottom:96px">

            {{-- ============ HEADER ============ --}}
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <span class="ds-spike"></span>
                    <span class="ds-title-sm" style="color:var(--ink)">RSI&nbsp;Madinah</span>
                    <a href="{{ route('standarisasi-ui') }}" wire:navigate
                        class="ds-body-sm hover:underline" style="color:var(--muted-soft)">/ Standarisasi UI</a>
                    <span class="ds-body-sm" style="color:var(--muted-soft)">/ Koding Transaksi</span>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('standarisasi-ui.koding-master') }}" wire:navigate
                        class="ds-btn ds-btn-secondary" style="height:34px; padding:6px 12px; font-size:13px">← Tutorial Master</a>
                    <x-theme-toggle />
                </div>
            </div>

            <div class="mt-8 grid grid-cols-1 gap-10 lg:grid-cols-[240px_1fr]">

                {{-- ============ SIDEBAR ============ --}}
                <aside class="self-start lg:sticky lg:top-24">
                    @foreach ($menuGroups as $group => $items)
                        <div class="mb-6">
                            <div class="ds-caption-up mb-2 px-3">{{ $group }}</div>
                            <div class="space-y-0.5">
                                @foreach ($items as $key => $label)
                                    <button type="button" x-on:click="go('{{ $key }}')"
                                        class="block w-full px-3 py-1.5 text-sm text-left rounded-lg transition-colors"
                                        :class="section === '{{ $key }}' ? 'font-semibold' : 'font-normal'"
                                        :style="section === '{{ $key }}'
                                            ? 'background:var(--surface-card); color:var(--ink)'
                                            : 'color:var(--body)'">
                                        {{ $label }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    <div class="px-3 pt-4" style="border-top:1px solid var(--hairline)">
                        <div class="ds-caption" style="color:var(--muted-soft)">
                            Prasyarat: <a href="{{ route('standarisasi-ui.koding-master') }}" wire:navigate
                                class="hover:underline" style="color:var(--primary)">Tutorial Koding Master</a><br>
                            Acuan jalur terlengkap: <span class="ds-code">transaksi/rj</span>
                        </div>
                    </div>
                </aside>

                {{-- ============ KONTEN ============ --}}
                <main style="min-width:0">

                    {{-- ====== 01 PENDAHULUAN ====== --}}
                    <section x-show="section === 'pendahuluan'" x-cloak>
                        <div class="ds-eyebrow mb-3">01 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Standarisasi Koding Modul Transaksi</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Domain transaksi adalah jantung SIRUS: perjalanan pasien dari
                            <strong>pendaftaran → pelayanan → kasir</strong> pada tiga jalur
                            (<strong>RJ</strong> rawat jalan, <strong>UGD</strong>, <strong>RI</strong> rawat inap),
                            ditambah tiga lintas-modul yang menempel di setiap jalur:
                            <strong>EMR</strong>, <strong>Modul Dokumen</strong>, dan <strong>Administrasi</strong>.
                            Tutorial ini merangkum pola yang WAJIB ditiru bila kita mengadopsi /
                            membangun modul transaksi baru.
                        </p>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Skala-nya jauh di atas master: EMR RJ saja 51 file (±13.700 baris) —
                            maka disiplin pola menjadi lebih penting, bukan lebih longgar.
                            Semua aturan dari <em>Tutorial Koding Master</em> (2-file, kontrak event,
                            komponen, LOV) tetap berlaku; bab-bab di sini menambahkan pola
                            khas transaksi di atasnya.
                        </p>

                        <div class="grid grid-cols-1 gap-4 mt-8 sm:grid-cols-3">
                            <div class="ds-card" style="padding:20px">
                                <div class="ds-title-sm mb-1">1 Header + JSON CLOB</div>
                                <div class="ds-body-sm">Detail kunjungan hidup di satu kolom JSON (CLOB) pada tabel header — dibaca via <span class="ds-code">OracleLob</span>, ditulis dgn row-lock.</div>
                            </div>
                            <div class="ds-card" style="padding:20px">
                                <div class="ds-title-sm mb-1">Modal-first</div>
                                <div class="ds-body-sm">EMR, administrasi, dokumen = modal full-screen yang dibuka via event dari list — bukan halaman ber-route sendiri.</div>
                            </div>
                            <div class="ds-card" style="padding:20px">
                                <div class="ds-title-sm mb-1">Event-driven save</div>
                                <div class="ds-body-sm">Section EMR menyimpan lewat broadcast <span class="ds-code">save-*</span> — satu tombol menyimpan banyak section (silent toast).</div>
                            </div>
                        </div>

                        <div class="ds-card-outline mt-8" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Peringatan dual-system:</strong> DB Oracle yang sama masih dipakai
                                Oracle Dev 6i (SIMRS lama). Entry dari sistem lama <em>tidak mengisi JSON cache</em> —
                                selalu pertimbangkan data yang JSON-nya kosong/parsial saat menulis fitur.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 02 ALUR ====== --}}
                    <section x-show="section === 'alur'" x-cloak>
                        <div class="ds-eyebrow mb-3">02 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Alur Pasien &amp; Routing</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Tiga jalur, pola tahapan mirip tapi <strong>tidak identik</strong> —
                            jangan blind-copy antar jalur (UGD punya triase &amp; transfer; RI tanpa
                            halaman pelayanan dan billing-nya per-item).
                        </p>

                        {{-- flow RJ --}}
                        <div class="ds-caption-up mb-2">Rawat Jalan (RJ)</div>
                        <div class="flex flex-wrap items-center gap-2 mb-6">
                            @foreach ([['Daftar RJ', '/rawat-jalan/daftar'], ['Pelayanan', '/rawat-jalan/pelayanan'], ['Antrian Kasir', 'poll 30s'], ['Administrasi', 'modal'], ['Apotek', 'antrian-apotek-rj']] as $i => [$tahap, $ket])
                                @if ($i > 0)<span class="ds-code" style="color:var(--primary)">▶</span>@endif
                                <span class="ds-card-outline" style="padding:8px 14px">
                                    <span class="block text-sm font-semibold" style="color:var(--ink)">{{ $tahap }}</span>
                                    <span class="block text-xs" style="color:var(--muted)">{{ $ket }}</span>
                                </span>
                            @endforeach
                        </div>

                        {{-- flow UGD --}}
                        <div class="ds-caption-up mb-2">UGD</div>
                        <div class="flex flex-wrap items-center gap-2 mb-2">
                            @foreach ([['Daftar UGD', 'triase P0–P3'], ['Pelayanan', '/ugd/pelayanan'], ['Antrian Kasir', 'poll 30s'], ['Administrasi', 'modal'], ['Apotek', 'antrian-apotek-ugd']] as $i => [$tahap, $ket])
                                @if ($i > 0)<span class="ds-code" style="color:var(--primary)">▶</span>@endif
                                <span class="ds-card-outline" style="padding:8px 14px">
                                    <span class="block text-sm font-semibold" style="color:var(--ink)">{{ $tahap }}</span>
                                    <span class="block text-xs" style="color:var(--muted)">{{ $ket }}</span>
                                </span>
                            @endforeach
                        </div>
                        <p class="ds-caption mb-6" style="color:var(--muted)">+ cabang: Transfer ke RI (modal terpisah, default cara masuk "MELALUI IGD").</p>

                        {{-- flow RI --}}
                        <div class="ds-caption-up mb-2">Rawat Inap (RI)</div>
                        <div class="flex flex-wrap items-center gap-2 mb-2">
                            @foreach ([['Daftar RI', 'EMR/Adm/Pindah Kamar dari list'], ['Billing per-item', 'imtxn_slshdrs/dtls'], ['Antrian Kasir RI', 'per resep/sls'], ['Daftar Kasir RI', 'per rihdr'], ['Posting Bayar', 'bon/kembalian']] as $i => [$tahap, $ket])
                                @if ($i > 0)<span class="ds-code" style="color:var(--primary)">▶</span>@endif
                                <span class="ds-card-outline" style="padding:8px 14px; {{ $i === 0 ? 'border-color:var(--primary)' : '' }}">
                                    <span class="block text-sm font-semibold" style="color:var(--ink)">{{ $tahap }}</span>
                                    <span class="block text-xs" style="color:var(--muted)">{{ $ket }}</span>
                                </span>
                            @endforeach
                        </div>
                        <p class="ds-caption mb-8" style="color:var(--muted)">RI TIDAK punya halaman pelayanan — EMR dibuka langsung dari daftar-ri.</p>

                        {{-- matrix --}}
                        <h2 class="ds-title-lg mb-3">Matrix jalur × tahap (route)</h2>
                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead>
                                    <tr><th>Tahap</th><th>RJ</th><th>UGD</th><th>RI</th></tr>
                                </thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">Pendaftaran</td><td class="ds-td-class">/rawat-jalan/daftar</td><td class="ds-td-class">/ugd/daftar</td><td class="ds-td-class">/ri/daftar</td></tr>
                                    <tr><td class="ds-td-strong">Pelayanan</td><td class="ds-td-class">/rawat-jalan/pelayanan</td><td class="ds-td-class">/ugd/pelayanan</td><td class="ds-body-sm">— (dari daftar-ri)</td></tr>
                                    <tr><td class="ds-td-strong">EMR (host)</td><td class="ds-td-class">emr-rj/erm-rj (modal)</td><td class="ds-td-class">emr-ugd (modal)</td><td class="ds-td-class">emr-ri (modal)</td></tr>
                                    <tr><td class="ds-td-strong">Modul Dokumen</td><td class="ds-td-class">emr-rj/modul-dokumen (4 form)</td><td class="ds-td-class">emr-ugd/modul-dokumen</td><td class="ds-td-class">emr-ri/modul-dokumen (±28 form)</td></tr>
                                    <tr><td class="ds-td-strong">Administrasi</td><td class="ds-td-class">administrasi-rj (modal)</td><td class="ds-td-class">administrasi-ugd</td><td class="ds-td-class">administrasi-ri</td></tr>
                                    <tr><td class="ds-td-strong">Antrian Kasir</td><td class="ds-td-class">/transaksi/rj/antrian-kasir-rj</td><td class="ds-td-class">/transaksi/ugd/antrian-kasir-ugd</td><td class="ds-td-class">/transaksi/kasir/antrian-kasir-ri + daftar-kasir-ri</td></tr>
                                    <tr><td class="ds-td-strong">Apotek</td><td class="ds-td-class">antrian-apotek-rj</td><td class="ds-td-class">antrian-apotek-ugd</td><td class="ds-td-class">ri-resep/antrian-ri-resep + PTO</td></tr>
                                    <tr><td class="ds-td-strong">Rekap bulanan</td><td class="ds-td-class">/rawat-jalan/daftar-bulanan</td><td class="ds-td-class">/ugd/daftar-bulanan</td><td class="ds-td-class">/ri/daftar-bulanan</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="ds-caption mt-3" style="color:var(--muted)">
                            + halaman tab gabungan lintas jalur: /transaksi/kasir · /transaksi/apotek · /transaksi/casemix (wrapper tab RJ+UGD+RI).
                        </p>
                    </section>

                    {{-- ====== 03 DATA ====== --}}
                    <section x-show="section === 'data'" x-cloak>
                        <div class="ds-eyebrow mb-3">03 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Data Inti &amp; JSON CLOB</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Satu kunjungan = satu baris di tabel <strong>header</strong> per jalur —
                            <span class="ds-code">rstxn_rjhdrs</span> (PK <span class="ds-code">rj_no</span>),
                            <span class="ds-code">rstxn_ugdhdrs</span>, <span class="ds-code">rstxn_rihdrs</span>
                            (PK <span class="ds-code">rihdr_no</span>, dibaca via view <span class="ds-code">rsview_rihdrs</span>).
                            Seluruh detail klinis &amp; administrasi (anamnesa, diagnosa, pos biaya, log)
                            hidup di <strong>satu kolom JSON CLOB</strong> di baris itu.
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Membaca — findData* + OracleLob</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['clob-read'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Menulis — read-modify-write + row lock (WAJIB)</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['rmw'] }}</pre>
                        </div>

                        <div class="grid grid-cols-1 gap-4 mt-8 sm:grid-cols-2">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Kenapa JSON, bukan tabel normalized?</div>
                                <ul class="ds-body-sm space-y-1.5" style="list-style:disc; padding-left:18px">
                                    <li>EMR = ratusan field lintas section — satu dokumen JSON per kunjungan jauh lebih sederhana dari puluhan tabel</li>
                                    <li>Satu <span class="ds-code">findData()</span> = seluruh konteks kunjungan</li>
                                    <li>Konsekuensi: WAJIB disiplin lock + merge (jangan replace state yang belum tersimpan — pakai <span class="ds-code">array_replace</span>)</li>
                                </ul>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Jebakan Oracle yang sering kena</div>
                                <ul class="ds-body-sm space-y-1.5" style="list-style:disc; padding-left:18px">
                                    <li><span class="ds-code">''</span> = NULL di Oracle — jangan <span class="ds-code">&lt;&gt; ''</span>, pakai <span class="ds-code">IS NOT NULL</span></li>
                                    <li>Kolom mixed-case → <span class="ds-code">DB::raw('"namaKolom" as alias')</span></li>
                                    <li>JSON_VALUE tidak didukung — filter via INSTR atau decode di PHP</li>
                                    <li>Detail lengkap: skill <span class="ds-code">oracle-quirks</span></li>
                                </ul>
                            </div>
                        </div>
                    </section>

                    {{-- ====== 04 PENDAFTARAN ====== --}}
                    <section x-show="section === 'pendaftaran'" x-cloak>
                        <div class="ds-eyebrow mb-3">04 — Tahapan</div>
                        <h1 class="ds-display-md mb-4">Pendaftaran</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Halaman <span class="ds-code">daftar-&lt;jalur&gt;</span> = list harian +
                            form pendaftaran sebagai <strong>modal actions terpisah</strong>
                            (pola sama dengan master, tapi jauh lebih kaya). Acuan:
                            <span class="ds-code">transaksi/rj/daftar-rj/⚡daftar-rj-actions.blade.php</span>.
                        </p>

                        <div class="ds-card-outline" style="padding:0; overflow:hidden">
                            <table class="ds-table">
                                <thead><tr><th>Bagian form</th><th>Pola yang dipakai</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">Pilih pasien</td><td class="ds-body-sm"><span class="ds-code">lov-pasien</span> (target unik) + <span class="ds-code">MasterPasienTrait::findDataMasterPasien(regNo)</span>; link ke Master Pasien utk pasien baru</td></tr>
                                    <tr><td class="ds-td-strong">Pilih dokter/poli</td><td class="ds-body-sm">LOV dokter-poli — jadwal &amp; kuota</td></tr>
                                    <tr><td class="ds-td-strong">Klaim / penjamin</td><td class="ds-body-sm">BPJS vs UMUM (<span class="ds-code">klaim_status</span>); SEP via modal VClaim terpisah (<span class="ds-code">vclaim-rj-actions</span>)</td></tr>
                                    <tr><td class="ds-td-strong">Antrean BPJS</td><td class="ds-body-sm">no antrian + task-id (AntrianTrait) disimpan di JSON <span class="ds-code">taskIdPelayanan</span>; booking MJKN dijemput dari <span class="ds-code">referensi_mobilejkn_bpjs</span></td></tr>
                                    <tr><td class="ds-td-strong">Cetak</td><td class="ds-body-sm">etiket pasien (print-agent localhost:9999), SEP, berkas BPJS</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Status baris list dihitung dari task-id di JSON</strong>
                                (taskId 3–7, 99=batal), bukan kolom status tersendiri —
                                jadi mengubah status = menulis JSON (lewat pola lock bab 03), bukan UPDATE kolom.
                            </span>
                        </div>

                        <p class="ds-body-md mt-6" style="max-width:62ch">
                            Panggilan API BPJS (SEP, antrean, dsb) <strong>wajib ber-timeout</strong>
                            (<span class="ds-code">timeout(8)-&gt;connectTimeout(3)</span>) — panggilan sinkron
                            tanpa timeout pernah membekukan worker seluruh aplikasi.
                        </p>
                    </section>

                    {{-- ====== 05 LIST & PERFORMA ====== --}}
                    <section x-show="section === 'list'" x-cloak>
                        <div class="ds-eyebrow mb-3">05 — Tahapan</div>
                        <h1 class="ds-display-md mb-4">List Transaksi &amp; Performa</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            List transaksi berbeda dari list master: datanya jutaan baris riwayat,
                            tiap baris membawa CLOB JSON besar, dan ada subquery penunjang (lab/rad).
                            Tiga aturan performa di bawah ini <strong>tidak boleh dilewati</strong> —
                            semuanya lahir dari list yang pernah lemot di produksi.
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Pola query list — ⚡daftar-rj.blade.php</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['list-query'] }}</pre>
                        </div>

                        <div class="grid grid-cols-1 gap-4 mt-6 sm:grid-cols-3">
                            <div class="ds-card" style="padding:20px">
                                <div class="ds-title-sm mb-1">Scope subquery</div>
                                <div class="ds-body-sm">Subquery lab/rad JOIN ke header + filter tanggal — jangan biarkan full-scan.</div>
                            </div>
                            <div class="ds-card" style="padding:20px">
                                <div class="ds-title-sm mb-1">Decode per-page</div>
                                <div class="ds-body-sm">OracleLob + json_decode hanya di <span class="ds-code">transform()</span> page aktif (±10 baris), bukan di query.</div>
                            </div>
                            <div class="ds-card" style="padding:20px">
                                <div class="ds-title-sm mb-1">Poll seperlunya</div>
                                <div class="ds-body-sm">Antrian (kasir/apotek) <span class="ds-code">wire:poll.30s</span>; halaman pendaftaran TIDAK perlu poll.</div>
                            </div>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Render versioning granular:</strong> pakai
                                <span class="ds-code">WithRenderVersioningTrait</span> per-area (toolbar, modal) supaya
                                ganti filter tidak me-remount seluruh halaman — dan JANGAN
                                <span class="ds-code">incrementVersion</span> saat user mengetik di search (fokus hilang).
                                Lookup list (dokter/poli) buat stabil: hanya depend tanggal, bukan semua filter
                                (<span class="ds-code">docs/stable-lookup-list-pattern.md</span>).
                            </span>
                        </div>

                        <p class="ds-body-md mt-6" style="max-width:62ch">
                            Aksi per baris berbentuk <strong>dropdown</strong> dengan guard role per item
                            (Ubah Pendaftaran = Mr|Admin|Supervisor Tu; Hapus = Admin|Manager Medis|…;
                            Diagnosa EMR = +Casemix|Dokter). Tombol utama (EMR, Dokumen, Administrasi)
                            men-dispatch event ke modal host — list tidak pernah menulis data sendiri.
                        </p>
                    </section>

                    {{-- ====== 06 EMR ====== --}}
                    <section x-show="section === 'emr'" x-cloak>
                        <div class="ds-eyebrow mb-3">06 — Tahapan</div>
                        <h1 class="ds-display-md mb-4">EMR (Rekam Medis)</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            EMR = <strong>modal full-screen</strong> (bukan route) yang di-embed sebagai sibling
                            di halaman pelayanan. Layout-nya mengikuti <strong>SOAP</strong>:
                            tiap huruf = satu/dua <em>section</em>, dan tiap section =
                            <strong>child Livewire mandiri</strong> yang menerima <span class="ds-code">:rjNo</span>.
                        </p>

                        {{-- visual SOAP grid --}}
                        <div class="ds-frame mt-2 mb-6">
                            <div class="ds-frame-label">Tata letak host EMR (erm-rj)</div>
                            <div class="grid grid-cols-2 gap-2 mt-3">
                                @foreach ([
                                    ['S', 'Subjective — Anamnesa', 'var(--info)'],
                                    ['O', 'Objective — Pemeriksaan', 'var(--success)'],
                                    ['A', 'Assessment — Diagnosa + Penilaian', 'var(--warning)'],
                                    ['P', 'Plan — Perencanaan', 'var(--error)'],
                                ] as [$huruf, $nama, $warna])
                                    <div class="ds-card-outline" style="padding:14px">
                                        <span class="inline-flex items-center justify-center w-8 h-8 mr-2 text-base font-bold rounded-full"
                                            style="background:color-mix(in srgb, {{ $warna }} 15%, transparent); color:{{ $warna }}">{{ $huruf }}</span>
                                        <span class="text-sm font-semibold" style="color:var(--ink)">{{ $nama }}</span>
                                        <p class="ds-caption mt-2" style="color:var(--muted)">child livewire · :rjNo · wire:key per rjNo</p>
                                    </div>
                                @endforeach
                            </div>
                            <p class="ds-caption mt-3" style="color:var(--muted)">
                                Header modal = <span class="ds-code">display-pasien-rj</span> (kartu identitas).
                                Screening, Modul Dokumen, Administrasi, E-Resep, Log Aktivitas = tombol yang membuka MODAL LAIN via event.
                            </p>
                        </div>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Host — buka EMR &amp; sebarkan event open</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['emr-host'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Section mounting + save-all broadcast</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['emr-section'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Di dalam section — save dgn flag silent</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['emr-save'] }}</pre>
                        </div>

                        <div class="grid grid-cols-1 gap-4 mt-8 sm:grid-cols-2">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Aturan section EMR</div>
                                <ul class="ds-body-sm space-y-1.5" style="list-style:disc; padding-left:18px">
                                    <li>Satu section = satu folder + satu file actions; state tidak bocor antar section</li>
                                    <li>Jangan ada method senama antar trait EMR di satu kelas (tabrakan trait) — helper lintas section = class statis (<span class="ds-code">App\Support\LogText</span>)</li>
                                    <li>Input numerik pakai <span class="ds-code">wire:model.blur</span> (bukan .live) — digit hilang saat race</li>
                                    <li><span class="ds-code">isFormLocked</span> dihormati SEMUA section (read-only penuh)</li>
                                </ul>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Kelengkapan EMR</div>
                                <ul class="ds-body-sm space-y-1.5" style="list-style:disc; padding-left:18px">
                                    <li><span class="ds-code">EmrCompletenessRJTrait::calculateEmrPercentRJ()</span> — bobot S15/O25/A25/P25/N10</li>
                                    <li>Ditampilkan sebagai progress di list (info-kelengkapan-emr)</li>
                                    <li>RI bobotnya beda (+CPPT &amp; keperawatan) — jangan samakan lintas jalur</li>
                                </ul>
                            </div>
                        </div>
                    </section>

                    {{-- ====== 07 MODUL DOKUMEN ====== --}}
                    <section x-show="section === 'dokumen'" x-cloak>
                        <div class="ds-eyebrow mb-3">07 — Tahapan</div>
                        <h1 class="ds-display-md mb-4">Modul Dokumen</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Form dokumen resmi bertanda tangan (consent, surat keterangan, laporan operasi…).
                            RJ punya 4 form; RI ±28 form (obstetri, bedah, anestesi). Satu form = pola
                            <strong>kartu + tombol Buka → modal</strong> dengan siklus hidup
                            <strong>Draft → TTD → terkunci → Lihat</strong>.
                        </p>

                        {{-- visual siklus --}}
                        <div class="flex flex-wrap items-center gap-2 mb-6">
                            @foreach ([['Draft', 'simpan sebagian, bebas edit'], ['TTD petugas/pasien', 'validasi lengkap + stempel'], ['Terkunci', 'isFormLocked — read only'], ['Lihat / Cetak', 'viewer iframe render blade cetak']] as $i => [$fase, $ket])
                                @if ($i > 0)<span class="ds-code" style="color:var(--primary)">▶</span>@endif
                                <span class="ds-card-outline" style="padding:8px 14px; {{ $i === 2 ? 'border-color:var(--warning)' : '' }}">
                                    <span class="block text-sm font-semibold" style="color:var(--ink)">{{ $fase }}</span>
                                    <span class="block text-xs" style="color:var(--muted)">{{ $ket }}</span>
                                </span>
                            @endforeach
                        </div>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Dua tahap: draft vs finalize — rm-general-consent-rj-actions</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['dokumen-flow'] }}</pre>
                        </div>

                        <div class="grid grid-cols-1 gap-4 mt-8 sm:grid-cols-2">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Komponen &amp; pola pendukung</div>
                                <ul class="ds-body-sm space-y-1.5" style="list-style:disc; padding-left:18px">
                                    <li>TTD petugas di layar: <span class="ds-code">x-signature.ttd-petugas</span> (guard server-side + simpan ttdCode)</li>
                                    <li>TTD pasien: signature-pad (dataURL) — bisa "TTD menyusul" (staged)</li>
                                    <li>Multi-entri (form berulang per kunjungan): tabel record expandable + Draft/Edit/TTD-kunci/Lihat</li>
                                    <li>Lihat = viewer iframe merender blade cetak (docs/dokumen-view-pattern.md)</li>
                                </ul>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Dua aturan keras</div>
                                <ul class="ds-body-sm space-y-1.5" style="list-style:disc; padding-left:18px">
                                    <li><strong>Teks klausul = versioning</strong> (<span class="ds-code">App\Support\*Clause</span>) — cetak ulang record lama WAJIB memakai redaksi saat ditandatangani. Baca <span class="ds-code">docs/clause-versioning.md</span> sebelum mengubah teks apa pun</li>
                                    <li><strong>Pre-fill wajib di-sync di save()</strong> — nilai prop yang tidak diedit user tidak otomatis masuk array form (hilang di cetak)</li>
                                </ul>
                            </div>
                        </div>
                    </section>

                    {{-- ====== 08 ADMINISTRASI & KASIR ====== --}}
                    <section x-show="section === 'administrasi'" x-cloak>
                        <div class="ds-eyebrow mb-3">08 — Tahapan</div>
                        <h1 class="ds-display-md mb-4">Administrasi &amp; Kasir</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Administrasi = modal rekap biaya kunjungan; tiap <strong>pos</strong> biaya
                            adalah file partial sendiri di folder <span class="ds-code">administrasi-&lt;jalur&gt;</span>.
                            Setelah petugas menandai selesai, pasien masuk antrian kasir untuk posting bayar.
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Pos biaya &amp; total — administrasi-rj</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['administrasi'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Posting bayar — administrasi-kasir-ri</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['kasir-post'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Guard role + audit log</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['role-audit'] }}
{{ $snip['audit-log'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Uang &amp; kunci:</strong> semua mutasi finansial dalam
                                <span class="ds-code">DB::transaction</span> + <span class="ds-code">lockForUpdate</span>;
                                posting bayar hanya role kasir (<span class="ds-code">Admin|Tu|Manager Umum|Supervisor Tu</span>);
                                bayar kurang dari total = otomatis <strong>bon</strong>, bukan error.
                                Semua nominal di UI pakai <span class="ds-code">x-text-input-number</span>.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 09 CHECKLIST ADOPSI ====== --}}
                    <section x-show="section === 'adopsi'" x-cloak>
                        <div class="ds-eyebrow mb-3">09 — Adopsi</div>
                        <h1 class="ds-display-md mb-4">Checklist Adopsi</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Mau menambah tahap di jalur yang ada, atau mengadopsi pola transaksi
                            untuk jalur/layanan baru? Ikuti kerangka folder ini lalu centang checklist-nya.
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Kerangka folder satu jalur</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['adopsi-tree'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:24px">
                            <ul class="ds-body-sm space-y-2.5">
                                @foreach ([
                                    'Tabel header + kolom JSON CLOB dirancang dulu (PK, kolom datadaftar*_json) — sepakati struktur JSON per section',
                                    'Trait jalur dibuat mengikuti EmrRJTrait: findData / lockRow / updateJson / appendAdminLog / checkStatus',
                                    'Baca CLOB SELALU via OracleLob::read; tulis SELALU transaction + lock (bab 03)',
                                    'List: baseQuery subquery ter-scope tanggal + paginate DB + transform page aktif; poll hanya di antrian',
                                    'EMR host = modal + section child ber-:id + save-events broadcast + silent toast',
                                    'Setiap form dokumen: Draft → TTD-kunci; teks klausul via *Clause versioning',
                                    'Administrasi: satu file per pos + sumAll(); selesai → status utk antrian kasir',
                                    'Mutasi uang: transaction + lockForUpdate + guard role kasir + dukung bon',
                                    'Semua aksi admin/MR tercatat appendAdminLog* (kategori ADMIN/MR)',
                                    'API eksternal (BPJS dkk): trait per-API pola VclaimTrait + timeout wajib',
                                    'Jangan blind-copy antar jalur: UGD punya triase/transfer; RI tanpa pelayanan & billing per-item',
                                    'Ikuti juga seluruh checklist Tutorial Koding Master (komponen, event, validasi, LOV)',
                                ] as $item)
                                    <li class="flex items-start gap-2.5">
                                        <svg class="w-4 h-4 mt-0.5 shrink-0" style="color:var(--primary)" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                        <span>{{ $item }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </section>

                    {{-- ====== 10 TRAIT & REFERENSI ====== --}}
                    <section x-show="section === 'referensi'" x-cloak>
                        <div class="ds-eyebrow mb-3">10 — Adopsi</div>
                        <h1 class="ds-display-md mb-4">Trait &amp; Referensi</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Peta trait di <span class="ds-code">app/Http/Traits/</span> yang menopang transaksi —
                            kenali dulu sebelum menulis helper baru (kemungkinan besar sudah ada).
                        </p>

                        <div class="ds-card-outline" style="padding:0; overflow:hidden">
                            <table class="ds-table">
                                <thead><tr><th>Trait / helper</th><th>Peran</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-class">Txn/{Rj,Ugd,Ri}/Emr*Trait</td><td class="ds-body-sm">inti jalur: findData, lockRow, updateJson, appendAdminLog, cek lock</td></tr>
                                    <tr><td class="ds-td-class">Txn/*/EmrCompleteness*Trait</td><td class="ds-body-sm">% kelengkapan EMR (bobot SOAP per jalur) utk progress list</td></tr>
                                    <tr><td class="ds-td-class">App\Support\OracleLob</td><td class="ds-body-sm">baca CLOB aman (anti ORA-01555 / truncate 32k) — helper statis</td></tr>
                                    <tr><td class="ds-td-class">Master/MasterPasien/MasterPasienTrait</td><td class="ds-body-sm">findDataMasterPasien(regNo) — identitas, BPJS, alamat</td></tr>
                                    <tr><td class="ds-td-class">BPJS/{Vclaim,Antrian,Aplicares,iCare}Trait</td><td class="ds-body-sm">SEP/rujukan · antrean+task-id · ketersediaan TT · riwayat i-Care</td></tr>
                                    <tr><td class="ds-td-class">iDRG/iDrgTrait</td><td class="ds-body-sm">grouping casemix iDRG (klaim)</td></tr>
                                    <tr><td class="ds-td-class">SATUSEHAT/*</td><td class="ds-body-sm">kirim EMR ke Satu Sehat (FHIR: Encounter, Condition, dst.)</td></tr>
                                    <tr><td class="ds-td-class">Dokumen/DokumenViewSupportTrait</td><td class="ds-body-sm">viewer/cetak dokumen RM</td></tr>
                                    <tr><td class="ds-td-class">WithValidationToast/WithValidationToastTrait</td><td class="ds-body-sm">validateWithToast() — toast otomatis saat validasi gagal</td></tr>
                                    <tr><td class="ds-td-class">WithRenderVersioning/WithRenderVersioningTrait</td><td class="ds-body-sm">remount granular per-area (toolbar/modal)</td></tr>
                                    <tr><td class="ds-td-class">Stock/StockBalanceTrait</td><td class="ds-body-sm">saldo stok obat (apotek/administrasi)</td></tr>
                                    <tr><td class="ds-td-class">App\Support\LogText</td><td class="ds-body-sm">sanitasi teks log (helper statis — anti tabrakan trait)</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <h2 class="ds-title-lg mt-10 mb-4">Dokumen terkait</h2>
                        <div class="ds-card-outline" style="padding:0; overflow:hidden">
                            <table class="ds-table">
                                <thead><tr><th>Topik</th><th>Baca</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">Standar modul master (prasyarat)</td><td class="ds-td-class">docs/standar-master-module.md + /standarisasi-ui/koding-master</td></tr>
                                    <tr><td class="ds-td-strong">Trait API eksternal</td><td class="ds-td-class">docs/trait-template-api-eksternal.md</td></tr>
                                    <tr><td class="ds-td-strong">Bridging iDRG</td><td class="ds-td-class">docs/idrg-bridging.md</td></tr>
                                    <tr><td class="ds-td-strong">Diagnosa ICD-10</td><td class="ds-td-class">docs/diagnosa-architecture.md (+ skill diagnosa-flow)</td></tr>
                                    <tr><td class="ds-td-strong">Clause versioning dokumen</td><td class="ds-td-class">docs/clause-versioning.md (+ skill clause-versioning)</td></tr>
                                    <tr><td class="ds-td-strong">Viewer dokumen (Lihat)</td><td class="ds-td-class">docs/dokumen-view-pattern.md</td></tr>
                                    <tr><td class="ds-td-strong">TTD cetak PDF / TTD petugas</td><td class="ds-td-class">docs/ttd-pattern-pdf-print.md · docs/ttd-petugas-component.md</td></tr>
                                    <tr><td class="ds-td-strong">Lookup list stabil</td><td class="ds-td-class">docs/stable-lookup-list-pattern.md</td></tr>
                                    <tr><td class="ds-td-strong">Jebakan Oracle & input Livewire</td><td class="ds-td-class">skill oracle-quirks · skill livewire-input-patterns</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    {{-- ============ PREV / NEXT ============ --}}
                    <div class="flex items-center justify-between gap-3 mt-12 pt-6" style="border-top:1px solid var(--hairline)">
                        <button type="button" class="ds-btn ds-btn-secondary"
                            x-show="idx() > 0" x-cloak
                            x-on:click="go(order[idx() - 1])">
                            ← <span x-text="labels[order[idx() - 1]]"></span>
                        </button>
                        <span x-show="idx() === 0"></span>
                        <button type="button" class="ds-btn ds-btn-primary"
                            x-show="idx() < order.length - 1" x-cloak
                            x-on:click="go(order[idx() + 1])">
                            <span x-text="labels[order[idx() + 1]]"></span> →
                        </button>
                    </div>

                </main>
            </div>
        </div>
    </div>
</div>
