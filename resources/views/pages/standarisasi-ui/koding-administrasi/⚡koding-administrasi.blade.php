<?php

use Livewire\Component;

// Tutorial konsep ADMINISTRASI (kasir) RJ/UGD/RI sampai pasien pulang, plus konsep
// TRANSFER (UGD→RI) & MODEL BATAL (batal transaksi / batal transfer / batal inap).
// Gaya sama koding-satusehat: sidebar per-submenu, snippet = nowdoc (aman compiler Blade).
new class extends Component {
    public function snippets(): array
    {
        return [

'status' => <<<'TXT'
// STATUS TRANSAKSI per jalur — dipakai untuk gating tombol & filter list.

// RJ & UGD — kolom rj_status / txn_status (rstxn_rjhdrs / rstxn_ugdhdrs)
//   'A' = Aktif / Antri     (default saat pendaftaran)
//   'I' = Transfer Inap     (UGD/RJ dipindah ke RI — terkunci)
//   status pulang/closed dihitung dari task-id di JSON, bukan kolom tersendiri.

// RI — kolom ri_status (rstxn_rihdrs)
//   'I' = Dirawat   (default saat admisi)
//   'P' = Pulang    (sudah diproses pulang + bayar)
//   'F' = Batal     (admisi dibatalkan)

// Peta status RI (daftar-ri-bulanan):
//   ['I' => 'Dirawat', 'P' => 'Pulang', 'F' => 'Batal']
// PENTING: laporan SIRS/manajemen MENGECUALIKAN ri_status='F' (dianggap tak terjadi).
TXT,

'biaya' => <<<'TXT'
// TOTAL TAGIHAN = jumlah SEMUA pos biaya. Dihitung reusable (dipakai kasir & transfer).
protected function calculateRJCosts(int $rjNo): array
{
    return [
        'rsAdmin'   => header rs_admin,
        'rjAdmin'   => header rj_admin,
        'poliPrice' => header poli_price,
        'actePrice' => sum rstxn_rjactemps.acte_price,      // tindakan medis
        'actdPrice' => sum rstxn_rjaccdocs.accdoc_price,    // jasa dokter
        'actpPrice' => sum rstxn_rjactparams.pact_price,    // tindakan penunjang
        'obat'      => sum(qty * price) rstxn_rjobats,
        'lab'       => sum rstxn_rjlabs.lab_price,
        'rad'       => sum rstxn_rjrads.rad_price,
        'other'     => sum rstxn_rjothers.other_price,
    ];
}
// UGD: pola sama di rstxn_ugd* (rstxn_ugdobats/ugdrads/…).
// RI : pos serupa di rstxn_ri* (rivisits/rikonsuls/riobats/rilabs/riradiologs/rioks/…)
//      + biaya carry-over dari UGD/RJ lewat rstxn_ritempadmins (flag 'UGD'/'RJ').
TXT,

'kasir' => <<<'TXT'
// ALUR KASIR SAMPAI PULANG (kasir-ri, pola serupa kasir-rj/kasir-ugd):
//
// 1. Set Tanggal Pulang    → updateTglPulang()   (exit_date; tglPulangSudahDiproses=true)
// 2. Input Nominal Bayar   → wire:model bayar
// 3. Proses Pulang         → postTransaksi()     (satu transaksi + lock):
//      - hitung sisaTagihan = totalSetelahDiskon - angsuran
//      - status_pulang = 'L' (LUNAS) bila bayar >= sisa, else 'H' (BON/HUTANG)
//      - insert payment (rstxn_ripaymentpdtls / *paymentdtls) + kembalian
//      - ri_status → 'P' (Pulang); tutup end_date kamar; lockstatus pasien lepas
//
// Form terkunci (isFormLocked) setelah pulang → hanya tombol Batal yang muncul.
// Guard umum: role Admin|Tu; lockRIRow/lockUGDRow/lockRJRow sebelum tulis.
TXT,

'transfer' => <<<'TXT'
// TRANSFER UGD → RI (transfer-ri-ugd-actions). SATU transaksi:
DB::transaction(function () {
    // 1. Buat header RI (rstxn_rihdrs, ri_status 'I') + kamar (rsmst_trfrooms)
    // 2. Biaya UGD sendiri → rstxn_ritempadmins
    //      tempadm_flag = 'UGD', tempadm_ref = rj_no (UGD), rihdr_no = RI baru
    // 3. Cascade biaya RJ: rstxn_ugdtempadmins → rstxn_ritempadmins (rihdr_no),
    //      lalu HAPUS rstxn_ugdtempadmins (sudah dipindah)
    // 4. Link tambahan: rstxn_ribiayaselamadugds (rj_no, ugd_no_rsri = rihdr_no, total_biayaugd)
    // 5. UGD status → 'I'; lockstatus pasien → RI
});

// LINK UTAMA UGD ↔ RI = baris rstxn_ritempadmins flag 'UGD'
//   (tempadm_ref = rj_no  →  rihdr_no).
// rstxn_ribiayaselamadugds = link tambahan — BISA KOSONG untuk transfer lama
//   (Oracle Dev 6i / dual-system) → jangan jadikan satu-satunya sumber.
TXT,

'batal-transfer' => <<<'TXT'
// BATAL TRANSFER (kasir-ugd::batalTransferRI). Cari RI hasil transfer BERLAPIS:
$riHdrNo = DB::table('rstxn_ritempadmins')                 // 1) link UTAMA
    ->where('tempadm_flag', 'UGD')
    ->where('tempadm_ref', $this->rjNo)
    ->value('rihdr_no');

if (!$riHdrNo) {                                           // 2) fallback legacy
    $riHdrNo = DB::table('rstxn_ribiayaselamadugds')
        ->where('rj_no', $this->rjNo)->value('ugd_no_rsri');
}
if (!$riHdrNo) { toast('Tidak ada data transfer untuk UGD ini.'); return; }

// GUARD sebelum batal:
//   - RI masih status 'I' (belum diproses)
//   - RI belum ada transaksi: rivisits/rikonsuls/riactparams/riactdocs/rilabs/
//                             riradiologs/rioks/riobats/riothers/ripaymentdtls
//   - lab UGD tidak pending (checkLabPendingUGD)
// AKSI (satu transaksi + lockUGDRow):
//   - restore rstxn_ritempadmins (flag != 'UGD') → rstxn_ugdtempadmins
//   - hapus RI: ritempadmins / trfrooms / ribiayaselamadugds / rihdrs
//   - UGD → 'A'; lockstatus pasien → 'UGD'
TXT,

'batal-transaksi' => <<<'TXT'
// BATAL TRANSAKSI (batalTransaksi) — membatalkan PEMBAYARAN/PULANG, BUKAN admisi.
// Ada di kasir-rj / kasir-ugd / kasir-ri. Contoh RI:
DB::transaction(function () {
    $this->lockRIRow($riHdrNo);
    DB::table('rstxn_ripaymentpdtls')->where('rihdr_no', $riHdrNo)->delete();  // hapus payment
    DB::table('rstxn_rihdrs')->where('rihdr_no', $riHdrNo)->update([
        'ri_bayar' => 0, 'ri_diskon' => 0, 'status_pulang' => null,
        'payment_date' => null, 'exit_date' => null,
        'ri_status' => 'I',                                 // Pulang → kembali DIRAWAT
    ]);
    // buka end_date kamar terakhir (pasien 'kembali' menempati bed); lock pasien lagi
});
// Hasil: status balik ke Dirawat ('I'). Role: Admin | Supervisor Tu.
TXT,

'batal-inap' => <<<'TXT'
// BATAL INAP → status 'F' (kasir-ri::batalInap). SOFT-cancel admisi RI (record TETAP).
// Hanya boleh: status 'I' (Dirawat) + BUKAN dari transfer + BELUM ada transaksi apa pun.
DB::transaction(function () {
    $this->lockRIRow($riHdrNo);

    // guard 1: ri_status harus 'I' (bukan 'P'/'F')
    // guard 2: bukan RI hasil transfer — cek rstxn_ritempadmins flag 'UGD'/'RJ'
    //          (kalau ya → arahkan pakai "Batal Transfer" di kasir asal)
    // guard 3: belum ada rivisits/rikonsuls/riactparams/riactdocs/rilabs/
    //          riradiologs/rioks/riobats/riothers/ripaymentdtls

    DB::table('rstxn_rihdrs')->where('rihdr_no', $riHdrNo)->update(['ri_status' => 'F']);
    // bebaskan bed: trfroom end_date = SYSDATE
    // unlock pasien: lockstatus = '1'
    // appendAdminLogRI(...) — audit
});
// Beda dari: Batal Transaksi (Pulang→Dirawat 'I') & Batal Transfer (hapus RI, UGD→'A').
// Role: Admin | Supervisor Tu.
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

        // Model 2 sisi: Sisi 1 = Konsep & Alur Visual, Sisi 2 = Coding.
        $sides = [
            'konsep' => [
                'label' => 'Konsep & Alur',
                'desc'  => 'Untuk siapa saja — konsep administrasi, status, biaya & alur visual',
                'groups' => [
                    'Pengantar' => [
                        'pendahuluan' => 'Pendahuluan',
                        'status'      => 'Model Status Transaksi',
                        'biaya'       => 'Struktur Biaya & Total',
                    ],
                    'Alur Visual' => [
                        'flow' => 'Alur Visual (Flow)',
                    ],
                ],
            ],
            'coding' => [
                'label' => 'Coding',
                'desc'  => 'Untuk programmer — kasir, transfer, model batal & guard (kode nyata)',
                'groups' => [
                    'Administrasi' => [
                        'kasir' => 'Alur Kasir sampai Pulang',
                    ],
                    'Transfer & Batal' => [
                        'transfer'        => 'Transfer UGD → RI',
                        'batal-transfer'  => 'Batal Transfer',
                        'batal-transaksi' => 'Batal Transaksi (Pulang)',
                        'batal-inap'      => 'Batal Inap → F',
                        'matriks'         => 'Matriks Batal',
                        'guard-transfer'  => 'Guard & Konsistensi Transfer',
                    ],
                    'Referensi' => [
                        'ranjau'    => 'Ranjau Umum',
                        'glosarium' => 'Glosarium',
                    ],
                ],
            ],
        ];

        // Turunan untuk Alpine.
        $labels = [];
        $sideKeys = [];       // { konsep: [key,...], coding: [key,...] } — urutan prev/next per sisi
        $sectionSide = [];    // { key: side }
        foreach ($sides as $sideKey => $side) {
            $sideKeys[$sideKey] = [];
            foreach ($side['groups'] as $items) {
                foreach ($items as $k => $lbl) {
                    $labels[$k] = $lbl;
                    $sideKeys[$sideKey][] = $k;
                    $sectionSide[$k] = $sideKey;
                }
            }
        }
    @endphp

    <div class="ds" style="min-height:100vh"
        x-data='{
            side: "konsep",
            sides: @json($sideKeys),
            labels: @json($labels),
            sectionSide: @json($sectionSide),
            section: "pendahuluan",
            curOrder() { return this.sides[this.side] || [] },
            idx() { return this.curOrder().indexOf(this.section) },
            go(s) {
                this.section = s;
                this.side = this.sectionSide[s] || this.side;
                history.replaceState(null, "", "#" + s);
                window.scrollTo({ top: 0, behavior: "smooth" });
            },
            switchSide(sd) {
                if (this.side === sd) return;
                this.side = sd;
                this.section = this.sides[sd][0];
                history.replaceState(null, "", "#" + this.section);
                window.scrollTo({ top: 0, behavior: "smooth" });
            },
            init() {
                const h = window.location.hash.slice(1);
                if (this.labels[h]) { this.section = h; this.side = this.sectionSide[h] || "konsep"; }
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
                    <span class="ds-body-sm" style="color:var(--muted-soft)">/ Koding Administrasi</span>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('standarisasi-ui.koding-transaksi') }}" wire:navigate
                        class="ds-btn ds-btn-secondary" style="height:34px; padding:6px 12px; font-size:13px">← Tutorial Transaksi</a>
                    <x-theme-toggle />
                </div>
            </div>

            {{-- ============ TOGGLE 2 SISI ============ --}}
            <div class="mt-8">
                <div class="inline-flex p-1 rounded-2xl" style="background:var(--surface-card); border:1px solid var(--hairline)">
                    @foreach ($sides as $sideKey => $side)
                        <button type="button" x-on:click="switchSide('{{ $sideKey }}')"
                            class="flex items-center gap-2 px-5 py-2.5 rounded-xl transition-colors"
                            :class="side === '{{ $sideKey }}' ? 'font-semibold' : 'font-medium'"
                            :style="side === '{{ $sideKey }}' ? 'background:var(--primary); color:#fff' : 'color:var(--body)'">
                            <span class="text-xs font-bold" :style="side === '{{ $sideKey }}' ? 'opacity:.85' : 'opacity:.5'">Sisi {{ $loop->iteration }}</span>
                            <span class="text-sm">{{ $side['label'] }}</span>
                        </button>
                    @endforeach
                </div>
                <div class="mt-2">
                    @foreach ($sides as $sideKey => $side)
                        <p class="ds-caption" style="color:var(--muted)" x-show="side === '{{ $sideKey }}'" x-cloak>{{ $side['desc'] }}</p>
                    @endforeach
                </div>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-10 lg:grid-cols-[240px_1fr]">

                {{-- ============ SIDEBAR ============ --}}
                <aside class="self-start lg:sticky lg:top-24">
                    @foreach ($sides as $sideKey => $side)
                        <div x-show="side === '{{ $sideKey }}'" x-cloak>
                            @foreach ($side['groups'] as $group => $items)
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
                        </div>
                    @endforeach

                    <div class="px-3 pt-4" style="border-top:1px solid var(--hairline)">
                        <div class="ds-caption" style="color:var(--muted-soft)">
                            Acuan kode: <span class="ds-code">transaksi/{rj,ugd,ri}/administrasi-*</span><br>
                            Prasyarat: <a href="{{ route('standarisasi-ui.koding-transaksi') }}" wire:navigate
                                class="hover:underline" style="color:var(--primary)">Tutorial Koding Transaksi</a>
                        </div>
                    </div>
                </aside>

                {{-- ============ KONTEN ============ --}}
                <main style="min-width:0">

                    {{-- ====== 01 PENDAHULUAN ====== --}}
                    <section x-show="section === 'pendahuluan'" x-cloak>
                        <div class="ds-eyebrow mb-3">01 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Konsep Administrasi &amp; Batal</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            <strong>Administrasi (kasir)</strong> adalah tahap akhir perjalanan pasien:
                            menghitung seluruh pos biaya, memproses pembayaran, dan memulangkan pasien.
                            Tiga jalur — <strong>RJ</strong>, <strong>UGD</strong>, <strong>RI</strong> — polanya mirip
                            tapi tak identik; RI paling kaya (billing per-item, transfer kamar, transfer masuk dari UGD/RJ).
                        </p>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Bab-bab di sini merangkum <strong>model status</strong>, <strong>struktur biaya</strong>,
                            <strong>alur kasir sampai pulang</strong>, serta <strong>tiga model pembatalan</strong>
                            yang sering tertukar: <em>Batal Transaksi</em>, <em>Batal Transfer</em>, dan <em>Batal Inap</em>.
                        </p>

                        <div class="grid grid-cols-1 gap-4 mt-8 sm:grid-cols-3">
                            <div class="ds-card" style="padding:20px">
                                <div class="ds-title-sm mb-1">Batal Transaksi</div>
                                <div class="ds-body-sm">Batalkan <strong>pembayaran/pulang</strong>. Status kembali ke sebelum-bayar (RI: Pulang→Dirawat).</div>
                            </div>
                            <div class="ds-card" style="padding:20px">
                                <div class="ds-title-sm mb-1">Batal Transfer</div>
                                <div class="ds-body-sm">Batalkan <strong>transfer UGD→RI</strong>. RI dihapus, UGD kembali Aktif ('A').</div>
                            </div>
                            <div class="ds-card" style="padding:20px">
                                <div class="ds-title-sm mb-1">Batal Inap</div>
                                <div class="ds-body-sm">Batalkan <strong>admisi RI</strong> → status <span class="ds-code">'F'</span> (soft, record tetap). Hanya bila belum ada transaksi.</div>
                            </div>
                        </div>

                        <div class="ds-card-outline mt-8" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Prinsip semua batal:</strong> jalankan dalam <span class="ds-code">DB::transaction</span> +
                                <span class="ds-code">lock*Row</span>, verifikasi status &amp; guard dulu, tulis audit
                                (<span class="ds-code">appendAdminLog*</span>), dan gate role (Admin / Supervisor Tu).
                            </span>
                        </div>
                    </section>

                    {{-- ====== 02 STATUS ====== --}}
                    <section x-show="section === 'status'" x-cloak>
                        <div class="ds-eyebrow mb-3">02 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Model Status Transaksi</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Batal = memindahkan status. Kenali dulu kode status tiap jalur.
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Jalur</th><th>Kolom</th><th>Nilai</th><th>Arti</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">RJ / UGD</td><td class="ds-td-class">rj_status / txn_status</td><td class="ds-td-class">A</td><td class="ds-body-sm">Aktif / Antri</td></tr>
                                    <tr><td class="ds-body-sm">RJ / UGD</td><td class="ds-td-class">rj_status / txn_status</td><td class="ds-td-class">I</td><td class="ds-body-sm">Transfer Inap (terkunci)</td></tr>
                                    <tr><td class="ds-td-strong">RI</td><td class="ds-td-class">ri_status</td><td class="ds-td-class" style="color:var(--primary)">I</td><td class="ds-body-sm"><strong>Dirawat</strong> (default admisi)</td></tr>
                                    <tr><td class="ds-body-sm">RI</td><td class="ds-td-class">ri_status</td><td class="ds-td-class">P</td><td class="ds-body-sm">Pulang (sudah bayar)</td></tr>
                                    <tr><td class="ds-body-sm">RI</td><td class="ds-td-class">ri_status</td><td class="ds-td-class" style="color:#dc2626">F</td><td class="ds-body-sm"><strong>Batal</strong> (dikecualikan laporan)</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-dark" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Ringkasan kode status</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['status'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>ri_status='F' hanya DIBACA</strong> oleh laporan (SIRS RL, manajemen) yang
                                mengecualikannya. Menandai batal = <em>menulis</em> 'F' (soft), bukan menghapus baris —
                                agar jejak audit &amp; statistik tetap konsisten.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 03 BIAYA ====== --}}
                    <section x-show="section === 'biaya'" x-cloak>
                        <div class="ds-eyebrow mb-3">03 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Struktur Biaya &amp; Total</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Total tagihan = penjumlahan pos biaya dari tabel-tabel transaksi per jalur.
                            Perhitungan dibuat <strong>reusable</strong> supaya kasir &amp; transfer memakai angka yang sama.
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Pola perhitungan biaya (calculateRJCosts)</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['biaya'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Tabel jembatan biaya transfer = <span class="ds-code">rstxn_ritempadmins</span></strong>
                                (kolom <span class="ds-code">tempadm_flag</span>). Saat UGD/RJ transfer ke RI, biaya asalnya
                                ikut disalin ke sini (flag 'UGD'/'RJ') supaya total RI mencakup biaya sebelum masuk inap.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 04 ALUR VISUAL (FLOW) ====== --}}
                    <section x-show="section === 'flow'" x-cloak>
                        <div class="ds-eyebrow mb-3">04 — Alur Visual</div>
                        <h1 class="ds-display-md mb-4">Alur Visual (Flowchart)</h1>
                        <p class="ds-body-md mb-2" style="max-width:64ch">
                            Peta perjalanan pasien dari <strong>masuk sampai pulang</strong>, cabang penunjang
                            (lab/radiologi) &amp; resep, skenario <strong>eskalasi/transfer</strong>, dan
                            <strong>titik-titik pembatalan</strong>.
                        </p>
                        <p class="ds-caption mb-8" style="color:var(--muted)">
                            <strong>Bagian 1 (Visual)</strong> di bawah ini untuk siapa saja — cukup gambar &amp; alur.
                            <strong>Bagian 2 (Detail Teknis / Coding)</strong> di bagian bawah halaman untuk programmer.
                        </p>

                        @php
                            $flowBox = function ($tone) {
                                return match ($tone) {
                                    'entry' => 'padding:10px 14px; border-color:var(--primary)',
                                    'opt'   => 'padding:10px 14px; border-style:dashed; border-color:#d97706',
                                    'cash'  => 'padding:10px 14px; border-color:#059669',
                                    'done'  => 'padding:10px 14px; border-color:#059669; background:rgba(5,150,105,0.06)',
                                    default => 'padding:10px 14px',
                                };
                            };
                            $arrow = '<span class="ds-code" style="color:var(--primary); font-size:16px">▶</span>';
                        @endphp

                        {{-- =================================================================== --}}
                        {{-- ============ BAGIAN 1 · VISUAL (untuk siapa saja) ================ --}}
                        {{-- =================================================================== --}}
                        <div class="ds-eyebrow mb-3" style="color:var(--primary)">Bagian 1 — Visual</div>

                        {{-- ===== FLOW 1: ALUR NORMAL (MASUK → PULANG) ===== --}}
                        <div class="ds-caption-up mb-2">Alur normal — masuk sampai pulang (RJ / UGD)</div>
                        <div class="flex flex-wrap items-center gap-2 mb-3">
                            @foreach ([
                                ['Pendaftaran', 'pasien didaftarkan', 'entry'],
                                ['Pelayanan / EMR', 'diperiksa &amp; didiagnosa dokter', 'main'],
                                ['Penunjang', 'Lab / Radiologi — bila perlu', 'opt'],
                                ['E-Resep → Apotek', 'obat diresepkan &amp; dilayani', 'main'],
                                ['Kasir', 'hitung total → bayar', 'cash'],
                                ['Pulang', 'Lunas atau Bon', 'done'],
                            ] as $i => [$judul, $ket, $tone])
                                @if ($i > 0) {!! $arrow !!} @endif
                                <span class="ds-card-outline" style="{{ $flowBox($tone) }}">
                                    <span class="block text-sm font-semibold" style="color:var(--ink)">{{ $judul }}</span>
                                    <span class="block text-xs" style="color:var(--muted)">{!! $ket !!}</span>
                                </span>
                            @endforeach
                        </div>
                        <p class="ds-caption mb-8" style="color:var(--muted)">
                            <span style="color:#d97706">▦ garis putus-putus</span> = langkah opsional (hanya bila pasien butuh penunjang / resep).
                        </p>

                        {{-- ===== FLOW 2: ESKALASI / TRANSFER ===== --}}
                        <div class="ds-caption-up mb-2">Eskalasi &amp; transfer (kondisi memburuk)</div>
                        <div class="flex flex-wrap items-center gap-2 mb-3">
                            @foreach ([
                                ['UGD', 'pasien gawat darurat', 'entry'],
                                ['Transfer ke Rawat Inap', 'biaya UGD ikut pindah', 'main'],
                                ['Dirawat', 'pelayanan rawat inap', 'main'],
                                ['Kasir RI', 'total (termasuk biaya UGD)', 'cash'],
                                ['Pulang', '—', 'done'],
                            ] as $i => [$judul, $ket, $tone])
                                @if ($i > 0) {!! $arrow !!} @endif
                                <span class="ds-card-outline" style="{{ $flowBox($tone) }}">
                                    <span class="block text-sm font-semibold" style="color:var(--ink)">{{ $judul }}</span>
                                    <span class="block text-xs" style="color:var(--muted)">{!! $ket !!}</span>
                                </span>
                            @endforeach
                        </div>
                        <p class="ds-caption mb-8" style="color:var(--muted)">
                            Pasien bisa <strong>RJ → UGD</strong> lalu <strong>UGD → Rawat Inap</strong>. Biaya dari tahap
                            sebelumnya selalu <strong>ikut terbawa</strong> &amp; ditagih di tahap berikutnya (tidak hilang).
                        </p>

                        {{-- ===== FLOW 3: TITIK BATAL (REVERSE) ===== --}}
                        <h2 class="ds-title-lg mb-3">Titik pembatalan (mundur)</h2>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            @foreach ([
                                ['Batal Transaksi', 'Sudah bayar', 'Aktif lagi', 'Undo pembayaran — pasien kembali bisa diproses.', 'RJ · UGD · RI'],
                                ['Batal (Kunjungan/Inap)', 'Aktif', 'Batal', 'Batalkan kunjungan/admisi — hanya bila BELUM ada transaksi apa pun.', 'RJ · UGD · RI'],
                                ['Batal Transfer', 'Target dibatalkan', 'Sumber aktif lagi', 'Undo transfer — biaya dikembalikan ke asal; hanya bila target belum ada transaksi.', 'RJ→UGD · UGD→RI'],
                            ] as [$judul, $dari, $ke, $ket, $jalur])
                                <div class="ds-card-outline" style="padding:16px 18px; border-color:#dc2626">
                                    <div class="ds-title-sm mb-2" style="color:#dc2626">{{ $judul }}</div>
                                    <div class="flex items-center gap-2 mb-2">
                                        <span class="ds-code" style="padding:2px 8px; border-radius:6px; background:var(--surface-card)">{{ $dari }}</span>
                                        <span style="color:#dc2626; font-weight:700">→</span>
                                        <span class="ds-code" style="padding:2px 8px; border-radius:6px; background:var(--surface-card)">{{ $ke }}</span>
                                    </div>
                                    <div class="ds-body-sm mb-1">{!! $ket !!}</div>
                                    <div class="ds-caption" style="color:var(--muted)">Jalur: {{ $jalur }}</div>
                                </div>
                            @endforeach
                        </div>

                        <div class="ds-card-outline mt-6 mb-4" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Kasus campur (sudah pulang lalu ingin batal total):</strong>
                                {!! $arrow !!} Batal Transaksi (kembali aktif) {!! $arrow !!} Batal Kunjungan/Inap (jadi Batal).
                                Pasien asal transfer {!! $arrow !!} pakai <strong>Batal Transfer</strong>, bukan Batal Kunjungan.
                            </span>
                        </div>

                        {{-- =================================================================== --}}
                        {{-- ============ BAGIAN 2 · DETAIL TEKNIS (Coding) =================== --}}
                        {{-- =================================================================== --}}
                        <div class="mt-12 pt-8" style="border-top:2px solid var(--hairline)">
                            <div class="ds-eyebrow mb-3" style="color:var(--muted)">Bagian 2 — Detail Teknis · Coding</div>
                            <h2 class="ds-title-lg mb-3">Status &amp; guard per transisi</h2>
                            <p class="ds-body-md mb-4" style="max-width:64ch">
                                Kode status: <span class="ds-code">A</span> Aktif · <span class="ds-code">L</span> Lunas ·
                                <span class="ds-code">I</span> Transfer/Dirawat · <span class="ds-code">P</span> Pulang ·
                                <span class="ds-code">F</span> Batal. Guard yang <strong>benar-benar berjalan sekarang</strong>:
                            </p>

                            <div class="ds-card-outline mb-4" style="padding:0; overflow-x:auto">
                                <table class="ds-table">
                                    <thead><tr><th>Transisi</th><th>Yang terjadi</th><th>Guard aktif</th></tr></thead>
                                    <tbody>
                                        <tr><td class="ds-td-strong">Pelayanan → Penunjang</td><td class="ds-body-sm">order lab / radiologi</td><td class="ds-body-sm">Diagnosis &amp; Ket. Klinis wajib diisi</td></tr>
                                        <tr><td class="ds-td-strong">Kasir → Pulang<br><span class="ds-caption" style="color:var(--muted)">postTransaksi · MAJU</span></td><td class="ds-body-sm">proses bayar &amp; pulang</td><td class="ds-body-sm">role <strong>Admin|Tu</strong> · <strong>lab tidak pending</strong> (RJ/UGD/RI) · tgl pulang diproses</td></tr>
                                        <tr><td class="ds-td-strong">Transfer (create)<br><span class="ds-caption" style="color:var(--muted)">MAJU</span></td><td class="ds-body-sm">buat target + pindah biaya (<span class="ds-code">tempadmins</span>)</td><td class="ds-body-sm">sumber 'A' · <strong>lab tidak pending</strong> · belum pernah transfer · (UGD→RI: pilih room+bed) · anti-race · lockstatus</td></tr>
                                        <tr><td class="ds-td-strong" style="color:#dc2626">Batal Transaksi<br><span class="ds-caption" style="color:var(--muted)">L→A / P→I · MUNDUR</span></td><td class="ds-body-sm">hapus payment (<span class="ds-code">*cashins</span>/<span class="ds-code">ripaymentpdtls</span>)</td><td class="ds-body-sm">role Admin|Sup.Tu · <span style="color:var(--primary)">lab-pending TIDAK memblok</span></td></tr>
                                        <tr><td class="ds-td-strong" style="color:#dc2626">Batal Kunjungan / Inap<br><span class="ds-caption" style="color:var(--muted)">A→F / I→F · MUNDUR</span></td><td class="ds-body-sm">set <span class="ds-code">rj_status/ri_status='F'</span> (soft)</td><td class="ds-body-sm">role Admin|Sup.Tu · status aktif · bukan dari transfer · <strong>semua child table kosong</strong> (layanan+bayar) · <span style="color:var(--primary)">lab-pending TIDAK memblok</span></td></tr>
                                        <tr><td class="ds-td-strong" style="color:#dc2626">Batal Transfer<br><span class="ds-caption" style="color:var(--muted)">target→F, sumber→A · MUNDUR</span></td><td class="ds-body-sm">soft-cancel target 'F', restore biaya ke sumber</td><td class="ds-body-sm">role Admin|Tu · lookup berlapis · target masih aktif · target belum ada transaksi · <span style="color:var(--primary)">lab-pending TIDAK memblok</span> · sumber 'I'</td></tr>
                                    </tbody>
                                </table>
                            </div>
                            <p class="ds-caption mb-6" style="color:var(--muted)">
                                <span style="color:var(--primary)">Hijau</span> = perilaku terbaru (guard lab-pending hanya di MAJU, dilepas dari MUNDUR).
                                Rincian guard per-arah di bab <button type="button" class="hover:underline font-semibold"
                                    style="color:var(--primary)" x-on:click="go('guard-transfer')">Guard &amp; Konsistensi Transfer</button>.
                            </p>

                            <div class="ds-card-outline" style="padding:16px 20px; border-color:var(--primary)">
                                <span class="ds-spike" style="vertical-align:middle"></span>
                                <span class="ds-body-sm" style="color:var(--body-strong)">
                                    <strong>Kenapa lab-pending dilepas dari batal (MUNDUR)?</strong>
                                    Guard <strong>MAJU</strong> (transfer &amp; proses pulang) sudah menjamin lab selesai sebelum finalisasi.
                                    Jadi di alur normal, saat batal <strong>pasti tak ada lab pending</strong> — guard lab di batal jadi
                                    <strong>redundant</strong>. Satu-satunya kondisi ia menyala = <strong>data anomali/lama</strong>
                                    (transfer via Oracle Dev 6i yang bypass guard maju, atau lab yatim). Karena membatalkan tak menyentuh
                                    lab (tetap <span class="ds-code">status_rjri</span> asal, <span class="ds-code">ref_no</span>, tetap bisa
                                    diproses), melepasnya membuat transaksi/transfer yang <em>nyangkut</em> tetap bisa dibatalkan —
                                    tanpa mengorbankan disiplin lab di jalur maju.
                                </span>
                            </div>
                        </div>
                    </section>

                    {{-- ====== 05 KASIR ====== --}}
                    <section x-show="section === 'kasir'" x-cloak>
                        <div class="ds-eyebrow mb-3">05 — Administrasi</div>
                        <h1 class="ds-display-md mb-4">Alur Kasir sampai Pasien Pulang</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Urutan baku administrasi: set tanggal pulang → input bayar → proses pulang.
                            Setelah pulang, form terkunci dan hanya menyisakan tombol batal.
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Alur postTransaksi (proses pulang)</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['kasir'] }}</pre>
                        </div>

                        <div class="grid grid-cols-1 gap-4 mt-8 sm:grid-cols-2">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">LUNAS vs BON</div>
                                <div class="ds-body-sm"><span class="ds-code">status_pulang</span>: <strong>'L'</strong> (LUNAS) bila bayar ≥ sisa tagihan; <strong>'H'</strong> (BON/Hutang) bila kurang — sisa jadi piutang pasien.</div>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Terkunci setelah pulang</div>
                                <div class="ds-body-sm"><span class="ds-code">isFormLocked</span> = true saat status Pulang → input disable, muncul banner + tombol <strong>Batal Transaksi</strong>.</div>
                            </div>
                        </div>
                    </section>

                    {{-- ====== 06 TRANSFER ====== --}}
                    <section x-show="section === 'transfer'" x-cloak>
                        <div class="ds-eyebrow mb-3">06 — Transfer &amp; Batal</div>
                        <h1 class="ds-display-md mb-4">Transfer UGD → RI</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Pasien UGD yang perlu dirawat inap di-<em>transfer</em>: sistem membuat header RI baru,
                            memindahkan biaya UGD/RJ ke RI, dan mengunci UGD. Komponen:
                            <span class="ds-code">transfer-ri-ugd-actions</span>.
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Transfer — tabel & tautan yang ditulis</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['transfer'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Tautan UGD↔RI yang andal = baris <span class="ds-code">rstxn_ritempadmins</span> flag 'UGD'</strong>
                                (<span class="ds-code">tempadm_ref=rj_no → rihdr_no</span>), bukan <span class="ds-code">rstxn_ribiayaselamadugds</span>
                                yang bisa kosong untuk data lama Oracle Dev 6i. (Lihat bab Batal Transfer.)
                            </span>
                        </div>
                    </section>

                    {{-- ====== 07 BATAL TRANSFER ====== --}}
                    <section x-show="section === 'batal-transfer'" x-cloak>
                        <div class="ds-eyebrow mb-3">07 — Transfer &amp; Batal</div>
                        <h1 class="ds-display-md mb-4">Batal Transfer</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Membatalkan transfer UGD→RI: menghapus RI yang baru dibuat &amp; mengembalikan UGD ke Aktif.
                            Hanya boleh bila RI <strong>belum diproses</strong> &amp; <strong>belum ada transaksi</strong>.
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">batalTransferRI — cari RI berlapis + guard + aksi</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['batal-transfer'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Bug yang diperbaiki:</strong> dulu pengecekan hanya melihat
                                <span class="ds-code">rstxn_ribiayaselamadugds</span> → transfer lama (tanpa baris itu)
                                salah dianggap "Tidak ada data transfer". Fix: cari <span class="ds-code">rihdr_no</span>
                                dari <span class="ds-code">rstxn_ritempadmins</span> (link utama) dulu, baru fallback.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 08 BATAL TRANSAKSI ====== --}}
                    <section x-show="section === 'batal-transaksi'" x-cloak>
                        <div class="ds-eyebrow mb-3">08 — Transfer &amp; Batal</div>
                        <h1 class="ds-display-md mb-4">Batal Transaksi (Pembayaran / Pulang)</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Membatalkan <strong>pembayaran</strong>, bukan admisi. Menghapus payment &amp; mengembalikan
                            status ke sebelum-bayar. Ada di ketiga jalur (kasir-rj / kasir-ugd / kasir-ri).
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">batalTransaksi (contoh RI)</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['batal-transaksi'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                RI: Pulang ('P') → Dirawat ('I'). RJ/UGD: reset field pembayaran &amp; buka kembali status.
                                Ini <strong>bukan</strong> pembatalan admisi — untuk itu lihat bab <em>Batal Inap</em>.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 09 BATAL INAP ====== --}}
                    <section x-show="section === 'batal-inap'" x-cloak>
                        <div class="ds-eyebrow mb-3">09 — Transfer &amp; Batal</div>
                        <h1 class="ds-display-md mb-4">Batal Inap → status F</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Membatalkan <strong>pendaftaran inap</strong> yang salah/tak jadi. Bersifat
                            <strong>soft</strong> (set <span class="ds-code">ri_status='F'</span>, record tetap),
                            hanya boleh saat masih Dirawat, bukan dari transfer, dan belum ada transaksi apa pun.
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">batalInap — guard bertingkat + set 'F'</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['batal-inap'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Kenapa soft (set 'F'), bukan hapus? Karena laporan sudah mengecualikan 'F' &amp; jejak
                                audit harus terjaga. Bed dibebaskan (<span class="ds-code">trfroom end_date=SYSDATE</span>)
                                &amp; pasien di-unlock agar bisa didaftar ulang.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 10 MATRIKS ====== --}}
                    <section x-show="section === 'matriks'" x-cloak>
                        <div class="ds-eyebrow mb-3">10 — Transfer &amp; Batal</div>
                        <h1 class="ds-display-md mb-4">Matriks Model Batal</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Tiga model batal sering tertukar. Bedakan dari <strong>apa yang dibatalkan</strong> &amp;
                            <strong>status akhirnya</strong>.
                        </p>

                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Model</th><th>Membatalkan</th><th>Status: dari → ke</th><th>Guard utama</th><th>Role</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">Batal Transaksi</td><td class="ds-body-sm">Pembayaran / pulang</td><td class="ds-td-class">P → I (RI) · reset (RJ/UGD)</td><td class="ds-body-sm">Sudah dibayar/pulang</td><td class="ds-body-sm">Admin / Supervisor Tu</td></tr>
                                    <tr><td class="ds-td-strong">Batal Transfer</td><td class="ds-body-sm">Transfer UGD→RI</td><td class="ds-td-class">UGD: I → A · RI dihapus</td><td class="ds-body-sm">RI belum ada transaksi; lab UGD tak pending</td><td class="ds-body-sm">Admin / Tu</td></tr>
                                    <tr><td class="ds-td-strong">Batal Inap</td><td class="ds-body-sm">Admisi RI</td><td class="ds-td-class">I → F (soft)</td><td class="ds-body-sm">Dirawat, bukan transfer, belum ada transaksi</td><td class="ds-body-sm">Admin / Supervisor Tu</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Urutan bila kasus campur:</strong> pasien sudah pulang lalu ingin dibatalkan total →
                                (1) Batal Transaksi (P→I), lalu (2) Batal Inap (I→F). Pasien dari UGD → gunakan
                                Batal Transfer, bukan Batal Inap.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 11 GUARD & KONSISTENSI TRANSFER ====== --}}
                    <section x-show="section === 'guard-transfer'" x-cloak>
                        <div class="ds-eyebrow mb-3">11 — Transfer &amp; Batal</div>
                        <h1 class="ds-display-md mb-4">Guard &amp; Konsistensi Transfer</h1>
                        <p class="ds-body-md mb-6" style="max-width:64ch">
                            Checklist semua <strong>guard</strong> di dua alur transfer
                            (<span class="ds-code">RJ→UGD</span> &amp; <span class="ds-code">UGD→RI</span>),
                            saat <strong>create (maju)</strong> maupun <strong>batal (mundur)</strong>,
                            plus status <strong>konsistensi</strong> antar-arah.
                        </p>

                        {{-- ===== GUARD CREATE ===== --}}
                        <h2 class="ds-title-lg mb-3">A. Guard saat CREATE (maju)</h2>
                        <div class="ds-card-outline mb-3" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Guard</th><th>Pesan / arti</th><th>Berlaku</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">rjNo ada</td><td class="ds-body-sm">"Data transaksi tidak ditemukan"</td><td class="ds-body-sm">keduanya</td></tr>
                                    <tr><td class="ds-td-strong">Sumber status 'A'</td><td class="ds-body-sm">"sudah diproses, tidak bisa ditransfer"</td><td class="ds-body-sm">keduanya</td></tr>
                                    <tr><td class="ds-td-strong">Lab tidak pending</td><td class="ds-body-sm">"Hasil Laborat belum selesai, transfer tidak bisa dilakukan"</td><td class="ds-body-sm">keduanya</td></tr>
                                    <tr><td class="ds-td-strong">Belum pernah transfer</td><td class="ds-body-sm">idempoten (cek <span class="ds-code">*biayaselamadi*</span>) — "sudah pernah dilakukan"</td><td class="ds-body-sm">keduanya</td></tr>
                                    <tr><td class="ds-td-strong">Anti-race</td><td class="ds-body-sm">"Data sudah diproses oleh user lain" (dalam transaksi)</td><td class="ds-body-sm">keduanya</td></tr>
                                    <tr><td class="ds-td-strong">Data sumber ada</td><td class="ds-body-sm">"Data UGD/RJ tidak ditemukan" (dalam transaksi)</td><td class="ds-body-sm">keduanya</td></tr>
                                    <tr><td class="ds-td-strong">Pasien lockstatus</td><td class="ds-body-sm">"Pasien sedang dalam status X, tidak bisa transfer" (cegah dobel jalur)</td><td class="ds-body-sm">keduanya</td></tr>
                                    <tr><td class="ds-td-strong">Ruangan dipilih</td><td class="ds-body-sm">wajib pilih room</td><td class="ds-body-sm" style="color:var(--primary)">UGD→RI saja</td></tr>
                                    <tr><td class="ds-td-strong">Bed dipilih</td><td class="ds-body-sm">"Pilih ruangan dan bed terlebih dahulu"</td><td class="ds-body-sm" style="color:var(--primary)">UGD→RI saja</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="ds-caption mb-8" style="color:var(--muted)">
                            CREATE <strong>sudah konsisten</strong> di kedua arah — kecuali UGD→RI menambah pilih room/bed (memang butuh tempat tidur).
                        </p>

                        {{-- ===== GUARD BATAL ===== --}}
                        <h2 class="ds-title-lg mb-3">B. Guard saat BATAL (mundur)</h2>
                        <div class="ds-card-outline mb-3" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Guard</th><th>Arti</th><th>Berlaku</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">Role Admin | Tu</td><td class="ds-body-sm">hanya Admin/TU boleh batal transfer</td><td class="ds-body-sm">keduanya</td></tr>
                                    <tr><td class="ds-td-strong">rjNo ada</td><td class="ds-body-sm">data transaksi ditemukan</td><td class="ds-body-sm">keduanya</td></tr>
                                    <tr><td class="ds-td-strong">Lookup target</td><td class="ds-body-sm">cari header hasil transfer</td><td class="ds-body-sm">keduanya</td></tr>
                                    <tr><td class="ds-td-strong">Target bisa dibatalkan</td><td class="ds-body-sm">UGD→RI: RI harus 'I'; RJ→UGD: UGD harus 'A'</td><td class="ds-body-sm">keduanya</td></tr>
                                    <tr><td class="ds-td-strong">Target belum ada transaksi</td><td class="ds-body-sm">obat/lab/rad/tindakan/jasa/lain-lain + pembayaran</td><td class="ds-body-sm">keduanya</td></tr>
                                    <tr><td class="ds-td-strong">Sumber status 'I'</td><td class="ds-body-sm">memang tertransfer (dalam transaksi)</td><td class="ds-body-sm">keduanya</td></tr>
                                    <tr><td class="ds-td-strong">Lab-pending DILEPAS</td><td class="ds-body-sm" style="color:var(--primary)">batal (mundur) TIDAK diblok lab pending</td><td class="ds-body-sm">keduanya ✅</td></tr>
                                </tbody>
                            </table>
                        </div>

                        {{-- ===== KONSISTENSI ===== --}}
                        <h2 class="ds-title-lg mt-8 mb-3">C. Konsistensi antar-arah (batal)</h2>
                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Aspek</th><th>UGD→RI (kuat)</th><th>RJ→UGD (tertinggal)</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">Lookup transfer</td><td class="ds-body-sm" style="color:var(--primary)">berlapis (ritempadmins + fallback)</td><td class="ds-body-sm" style="color:#d97706">1 sumber (ugdbiayaselamadirjs) ⚠️</td></tr>
                                    <tr><td class="ds-td-strong">Not-found → recovery</td><td class="ds-body-sm" style="color:var(--primary)">✅ UGD 'I'→'A'</td><td class="ds-body-sm" style="color:#dc2626">❌ tak ada — RJ bisa nyangkut 'I'</td></tr>
                                    <tr><td class="ds-td-strong">Header target saat batal</td><td class="ds-body-sm" style="color:var(--primary)">soft ri_status='F'</td><td class="ds-body-sm" style="color:#d97706">hard delete ugdhdrs (rawan ORA-02292) ⚠️</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Verdict:</strong> guard CREATE &amp; guard inti BATAL sudah konsisten.
                                Yang <strong>belum</strong>: batal <span class="ds-code">RJ→UGD</span> perlu (1) lookup berlapis via
                                <span class="ds-code">ugdtempadmins</span> flag 'RJ', (2) recovery RJ 'I'→'A' saat data tak ketemu.
                                Poin (3) hard-delete <strong>tak bisa 100% sama</strong> karena UGD tak punya status 'F' seperti RI —
                                opsi: buat delete berpanduan child atau biarkan.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 12 RANJAU ====== --}}
                    <section x-show="section === 'ranjau'" x-cloak>
                        <div class="ds-eyebrow mb-3">12 — Referensi</div>
                        <h1 class="ds-display-md mb-4">Ranjau Umum</h1>
                        <div class="space-y-3">
                            @foreach ([
                                ['Sumber tautan transfer', 'Jangan andalkan hanya rstxn_ribiayaselamadugds — bisa kosong (data Oracle Dev 6i). Link utama = rstxn_ritempadmins flag UGD.'],
                                ['Selalu lock sebelum tulis', 'lockRJRow/lockUGDRow/lockRIRow di dalam DB::transaction; tanpa lock, dua kasir bisa bentrok (last write wins).'],
                                ['Batal ≠ hapus', 'Batal Inap = SET ri_status F (soft), bukan DELETE. Laporan sudah mengecualikan F; hapus akan merusak audit & nomor.'],
                                ['Guard transaksi sebelum batal', 'Selalu cek RI/UGD belum punya transaksi (visit/obat/lab/dll.) sebelum batal transfer/inap, demi integritas billing.'],
                                ['Bebaskan bed & unlock pasien', 'Batal inap/transfer wajib menutup end_date kamar & mengembalikan lockstatus pasien, agar bed & pasien bisa dipakai lagi.'],
                                ['Audit setiap batal', 'appendAdminLog{RI,RJ,UGD} untuk tiap pembatalan — jejak siapa & kapan.'],
                            ] as $i => [$judul, $isi])
                                <div class="ds-card-outline" style="padding:16px 20px">
                                    <div class="flex items-start gap-3">
                                        <span style="display:inline-flex;align-items:center;justify-content:center;min-width:24px;height:24px;border-radius:9999px;background:var(--primary);color:#fff;font-size:12px;font-weight:700;flex:none">{{ $i + 1 }}</span>
                                        <div>
                                            <div class="ds-title-sm mb-1">{{ $judul }}</div>
                                            <div class="ds-body-sm">{{ $isi }}</div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>

                    {{-- ====== 13 GLOSARIUM ====== --}}
                    <section x-show="section === 'glosarium'" x-cloak>
                        <div class="ds-eyebrow mb-3">13 — Referensi</div>
                        <h1 class="ds-display-md mb-4">Glosarium</h1>
                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Istilah</th><th>Arti</th></tr></thead>
                                <tbody>
                                    @foreach ([
                                        ['ri_status', 'Status RI: I=Dirawat, P=Pulang, F=Batal'],
                                        ['rj_status / txn_status', 'Status RJ/UGD: A=Aktif, I=Transfer Inap'],
                                        ['status_pulang', "Cara pulang: 'L'=Lunas, 'H'=Bon/Hutang"],
                                        ['rstxn_ritempadmins', 'Jembatan biaya RI — carry-over biaya UGD/RJ (kolom tempadm_flag). Link utama transfer UGD↔RI'],
                                        ['rstxn_ugdtempadmins', 'Jembatan biaya sementara UGD sebelum transfer'],
                                        ['rstxn_ribiayaselamadugds', 'Tabel link tambahan UGD→RI (rj_no ↔ ugd_no_rsri) — bisa kosong utk data lama'],
                                        ['rsmst_trfrooms', 'Riwayat kamar RI (start_date/end_date) — end_date kosong = bed sedang ditempati'],
                                        ['tempadm_flag', "Penanda asal biaya di ritempadmins: 'UGD' / 'RJ'"],
                                        ['lockstatus', 'Penanda pasien sedang dikunci di satu jalur (UGD/RI) agar tak dobel'],
                                        ['Batal Transaksi', 'Batalkan pembayaran/pulang → status kembali sebelum-bayar'],
                                        ['Batal Transfer', 'Batalkan transfer UGD→RI → RI dihapus, UGD kembali Aktif'],
                                        ['Batal Inap', 'Batalkan admisi RI → ri_status F (soft)'],
                                        ['Bon', 'Pembayaran kurang dari tagihan — sisa jadi piutang pasien'],
                                    ] as [$istilah, $arti])
                                        <tr>
                                            <td class="ds-td-strong" style="white-space:nowrap">{{ $istilah }}</td>
                                            <td class="ds-body-sm">{{ $arti }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>

                    {{-- ============ PREV / NEXT ============ --}}
                    <div class="flex items-center justify-between gap-3 mt-12 pt-6" style="border-top:1px solid var(--hairline)">
                        <button type="button" class="ds-btn ds-btn-secondary"
                            x-show="idx() > 0" x-cloak
                            x-on:click="go(curOrder()[idx() - 1])">
                            ← <span x-text="labels[curOrder()[idx() - 1]]"></span>
                        </button>
                        <span x-show="idx() === 0"></span>
                        {{-- di akhir sisi Konsep: ajak lanjut ke sisi Coding --}}
                        <button type="button" class="ds-btn ds-btn-primary"
                            x-show="idx() < curOrder().length - 1" x-cloak
                            x-on:click="go(curOrder()[idx() + 1])">
                            <span x-text="labels[curOrder()[idx() + 1]]"></span> →
                        </button>
                        <button type="button" class="ds-btn ds-btn-primary"
                            x-show="side === 'konsep' && idx() === curOrder().length - 1" x-cloak
                            x-on:click="switchSide('coding')">
                            Lanjut ke Sisi 2 — Coding →
                        </button>
                    </div>

                </main>
            </div>
        </div>
    </div>
</div>
