<?php

use Livewire\Component;

// Katalog SKILL repo (.claude/skills/*) — versi web dari docs/skills-index.md.
// Halaman referensi: daftar skill dikelompokkan per domain + "baca saat".
new class extends Component {
    // [domain, nomor, [ [nama, cakupan, bacaSaat], ... ] ]
    public function groups(): array
    {
        return [
            [
                'no' => '01',
                'domain' => 'Keselamatan edit & konvensi kode',
                'items' => [
                    ['blade-safe-edit', 'Aturan aman edit *.blade.php / Volt: dilarang regex multiline, verifikasi balance tag, jebakan compiler Volt (tag penutup PHP / kata "use" / "reuse" di string), escape ganda prop komponen.', 'Sebelum edit bulk / sed / regex pada Blade, atau menyunting banyak file sekaligus.'],
                    ['naming-conventions', 'Penamaan variable/method (camelCase Indonesia, hindari singkatan), singkatan modul yang "dipesan" (rj/ri/ugd/rm), aturan use vs FQCN di Volt.', 'Sebelum menulis kode PHP/Livewire/Volt baru atau menamai variable domain.'],
                ],
            ],
            [
                'no' => '02',
                'domain' => 'Database & query',
                'items' => [
                    ['oracle-quirks', "Gotcha Oracle (Laravel + Oracle Dev 6i): '' = NULL, JSON_VALUE tak didukung, kolom mixed-case, active_status '1'/'0', lookup shift, filter wilayah (kab_id).", 'Sebelum menulis/men-debug query DB, hasil kosong tak terduga, ORA-00904.'],
                ],
            ],
            [
                'no' => '03',
                'domain' => 'Livewire / UI',
                'items' => [
                    ['livewire-input-patterns', 'Pola input Livewire/Alpine teruji: wire:model.blur, x-text-input-number, Enter→$wire race, x-now-button, search input "mental", persist filter antar tab (#[Session]).', 'Menambah/men-debug input numerik EMR, aksi Enter, filter list ber-tab.'],
                    ['ui-pattern-docs', 'Indeks pola UI di docs/ (tombol, modal, tab, page-frame, cetak PDF/TTD, editor, list stabil, wrapper hub).', 'Sebelum membuat komponen UI baru — cek dulu polanya sudah ada.'],
                ],
            ],
            [
                'no' => '04',
                'domain' => 'EMR — dokumen & teks legal',
                'items' => [
                    ['modul-dokumen', 'Membuat/mem-port modul dokumen bertanda tangan (consent, suket, laporan, Akhir Hayat): kartu→modal, Draft→TTD→Kunci→Lihat/Cetak, role Gate terpusat, penanda tab, viewer Rekam Medis, porting RI⇄UGD⇄RJ.', 'Membuat form dokumen baru, memasang di jalur lain, atau viewer rekam-medisnya.'],
                    ['emr-multi-entry-document', 'Dokumen multi-entri EMR RI (CPPT & SBAR): banyak entri per pasien, tab per-profesi, Edit=pemilik/Hapus=supervisor/Review=DPJP, copy-ke-form, cetak per-entri.', 'Membuat dokumen EMR RI mirip CPPT/SBAR atau fitur Edit/Review/Copy-nya.'],
                    ['clause-versioning', 'Versioning teks klausul dokumen legal agar cetak ulang record lama tetap memakai redaksi SAAT DITANDATANGANI walau kebijakan berubah.', 'Sebelum mengubah teks klausul consent/pernyataan atau menambah versi klausul.'],
                ],
            ],
            [
                'no' => '05',
                'domain' => 'EMR — domain data & modul',
                'items' => [
                    ['diagnosa-flow', 'Arsitektur & jebakan diagnosa ICD-10 (RSMST_MSTDIAGS, LOV, EMR, SEP/VClaim, iDRG); 288 icdx kembar → lookup flag naive salah baris; aturan icdx vs diag_id per konsumen.', 'Sebelum mengubah/menambah pemilihan atau penyimpanan diagnosa.'],
                    ['master-pasien', 'Field path & jebakan data pasien (rsmst_pasiens / MasterPasienTrait): mapping L/P, *Desc tak sync, kolom salah nama, umur dari birth_date.', 'Membaca/menyimpan data pasien, menampilkan gender & umur, mengisi Master Pasien.'],
                    ['laborat', 'Arsitektur & jebakan modul Laboratorium (lbtxn_/lbmst_, hasil, nilai rujukan & kritis per-gender, Mindray, status P/C/H/F, biaya ke induk RJ/UGD/RI).', 'Menambah/mengubah item master lab, input/tampilan/cetak hasil, ambang, laporan, batal.'],
                    ['administrasi-inline-edit', 'Sel tabel yang diedit langsung ke DB di Administrasi/transaksi (tarif, hari, tanggal Riwayat Kamar/Visit/Konsul); jebakan kolom turunan (hari/subtotal) + audit log.', 'Sebelum menambah/mengubah kolom editable pada tabel transaksi.'],
                ],
            ],
        ];
    }
};
?>

<div>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=source-sans-3:400,500,600,700|jetbrains-mono:400,500&display=swap" rel="stylesheet" />

    @php $groups = $this->groups(); $total = collect($groups)->sum(fn ($g) => count($g['items'])); @endphp

    <div class="ds">
        <div class="ds-section">

            {{-- ============ HERO ============ --}}
            <header class="ds-band">
                <div class="flex items-center justify-between gap-2 mb-5">
                    <div class="flex items-center gap-2">
                        <span class="ds-spike"></span>
                        <span class="ds-title-sm" style="color:var(--ink)">RSI&nbsp;Madinah</span>
                        <span class="ds-body-sm" style="color:var(--muted-soft)">/ Standarisasi UI / Skill</span>
                    </div>
                    <x-theme-toggle />
                </div>

                <a href="{{ route('panduan-dev') }}" wire:navigate class="ds-btn ds-btn-secondary mb-6" style="display:inline-flex;align-items:center;gap:6px">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                    Kembali ke Standarisasi UI
                </a>

                <div class="ds-eyebrow mb-4">Design System Internal · Skill</div>
                <h1 class="ds-display-xl">Katalog Skill.</h1>
                <p class="ds-body-md mt-6" style="max-width:60ch; color:var(--body-strong)">
                    <strong>{{ $total }} skill</strong> repo ini — instruksi terpaket "baca-dulu-sebelum-X" yang
                    menjaga kode konsisten dengan pola &amp; jebakan yang sudah dipetakan. File tiap skill:
                    <code class="ds-code">.claude/skills/&lt;nama&gt;/SKILL.md</code>. Sumber:
                    <code class="ds-code">docs/skills-index.md</code>.
                </p>
                <div class="ds-card-outline mt-6" style="padding:14px 18px">
                    <span class="ds-spike" style="vertical-align:middle"></span>
                    <span class="ds-body-sm" style="color:var(--body-strong)">
                        <strong>Skill</strong> = pembungkus aturan + kapan-wajib-dibaca (sering menunjuk ke
                        <code class="ds-code">docs/</code>). <strong>docs/</strong> = referensi pola/arsitektur.
                    </span>
                </div>
            </header>

            {{-- ============ GRUP SKILL ============ --}}
            @foreach ($groups as $g)
                <section class="ds-band">
                    <div class="ds-eyebrow mb-3">{{ $g['no'] }} — {{ count($g['items']) }} skill</div>
                    <h2 class="ds-display-lg mb-8">{{ $g['domain'] }}</h2>

                    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                        @foreach ($g['items'] as [$nama, $cakupan, $baca])
                            <div class="ds-card-outline" style="display:flex;flex-direction:column;gap:10px">
                                <div class="flex items-center gap-2">
                                    <span class="ds-spike"></span>
                                    <code class="ds-code" style="font-size:15px;font-weight:600;color:var(--primary)">/{{ $nama }}</code>
                                </div>
                                <p class="ds-body-md" style="color:var(--body)">{{ $cakupan }}</p>
                                <div class="ds-body-sm" style="color:var(--muted);border-top:1px solid var(--hairline);padding-top:10px">
                                    <span class="ds-caption-up" style="color:var(--muted-soft)">Baca saat</span><br>
                                    {{ $baca }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach

            {{-- ============ CARA PAKAI ============ --}}
            <section class="ds-band">
                <div class="ds-eyebrow mb-3">Cara pakai</div>
                <h2 class="ds-display-lg mb-8">Memakai &amp; menambah skill</h2>
                <div class="ds-card-dark" style="padding:22px">
<pre class="ds-code" style="margin:0;color:var(--on-dark-soft);overflow-x:auto;font-size:13px"><span style="color:var(--muted-soft)"># AI (Claude Code): panggil skill → instruksi termuat ke konteks</span>
/modul-dokumen        <span style="color:var(--muted-soft)"># mis. sebelum bikin form dokumen baru</span>

<span style="color:var(--muted-soft)"># Manusia: buka file-nya</span>
.claude/skills/&lt;nama&gt;/SKILL.md

<span style="color:var(--muted-soft)"># Menambah skill baru:</span>
<span style="color:var(--muted-soft)"># 1. buat .claude/skills/&lt;nama&gt;/SKILL.md (frontmatter: name + description yg</span>
<span style="color:var(--muted-soft)">#    menyebut KAPAN wajib dibaca)</span>
<span style="color:var(--muted-soft)"># 2. tambah barisnya ke docs/skills-index.md + kartu di halaman ini</span></pre>
                </div>
                <p class="ds-body-sm mt-4" style="color:var(--muted-soft)">
                    Referensi lengkap: <code class="ds-code">docs/skills-index.md</code> ·
                    indeks pola UI: <code class="ds-code">docs/standar-ui-komponen.md</code>.
                </p>
            </section>

        </div>
    </div>
</div>
