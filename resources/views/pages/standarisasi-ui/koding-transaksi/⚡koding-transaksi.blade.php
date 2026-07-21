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

'pendaftaran-save' => <<<'TXT'
// KERANGKA SAVE PENDAFTARAN — ⚡daftar-rj-actions.blade.php (dipadatkan).
// Modal actions terpisah (event daftar-rj.create.open / daftar-rj.edit.open),
// pola sama dgn form master — bedanya: nomor transaksi, no antrian, dan
// sederet guard jadwal.

#[On('lov.selected.rjFormPasien')]   // LOV pasien → isi regNo + identitas
#[On('lov.selected.rjFormDokter')]   // LOV dokter → poli, shift, jadwal

public function save(): void
{
    $this->validateDataRJ();                    // validasi Indonesia paling atas

    // GUARD kuota (warning, TIDAK memblok simpan): terdaftar (rjhdrs)
    //   + booking MJKN status 'Belum' >= kuota jadwal → toast "Kuota penuh".
    // GUARD shift: shiftMismatchMessage() — jam daftar vs shift jadwal dokter.

    DB::transaction(function () {
        // CREATE — nomor transaksi dihitung DI DALAM transaksi:
        $rjNo = (string) ((int) DB::table('rstxn_rjhdrs')->max('rj_no') + 1);

        DB::table('rstxn_rjhdrs')->insert($this->buildPayload($rjNo));
        // payload = kolom HEADER saja: rj_no, rj_date (to_date), reg_no,
        // no_antrian, klaim_id, poli_id, dr_id, shift + STATUS AWAL:
        // txn_status 'A' · rj_status 'A' · erm_status 'A' · pass_status 'O'
    });
    // EDIT — update by rj_no; nomor & no antrian TIDAK dihitung ulang.
}

// No antrian = max gabungan rjhdrs + booking MJKN per dokter/poli/tanggal.
// Kolom booking bertipe VARCHAR2 → wajib to_number() supaya max-nya numeric
// (urutan leksikal membuat '9' > '10').
private function hitungNoAntrian(string $drId, Carbon $tgl): int { ... }

// PENTING: kolom JSON (datadaftarpolirj_json) TIDAK diisi saat pendaftaran —
// baru terbentuk saat modul lain menulis (EMR, task-id, administrasi).
// Karena itu semua pembaca JSON wajib toleran kosong (findDataRJ ?? []) —
// juga demi entry lama dari Oracle Dev 6i (dual-system).

// Setelah tersimpan, aksi lanjutan = komponen SIBLING terpisah:
// vclaim-rj-actions (SEP) · satu-sehat-rj-actions · cetak etiket
// (print-agent localhost) · task-id antrean BPJS (AntrianTrait).
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

'emr-section-skeleton' => <<<'TXT'
// ⚡rm-<section>-rj-actions.blade.php — KERANGKA UTUH satu section EMR
// (disarikan dari rm-perencanaan-rj-actions, section acuan paling ramping).
// ── BAGIAN 1: blok PHP kelas Volt (di antara tag php pembuka & penutup) ──

use Livewire\Component;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\WithValidationToast\WithValidationToastTrait;

new class extends Component {
    use EmrRJTrait, WithRenderVersioningTrait, WithValidationToastTrait;

    public ?int  $rjNo = null;
    public bool  $isFormLocked = false;
    public array $dataDaftarPoliRJ = [];              // cache JSON CLOB kunjungan

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-<section>-rj'];

    public function mount(): void
    {
        $this->registerAreas(['modal-<section>-rj']);
    }

    // 1) OPEN — host menyebarkan open-rm-<section>-rj saat EMR dibuka
    #[On('open-rm-<section>-rj')]
    public function openSection($rjNo): void
    {
        if (empty($rjNo)) return;
        $this->rjNo = $rjNo;
        $this->resetValidation();

        $data = $this->findDataRJ($rjNo);                     // baca CLOB (OracleLob)
        if (! $data) {
            $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.');
            return;
        }

        $this->dataDaftarPoliRJ = $data;
        $this->dataDaftarPoliRJ['<keySection>'] ??= $this->getDefault(); // backfill record lama
        $this->incrementVersion('modal-<section>-rj');        // remount area → state segar
        $this->isFormLocked = $this->checkEmrRJStatus($rjNo); // EMR sudah dikunci?
    }

    // 2) DEFAULT — struktur key JSON milik section ini
    //    (record dari SIMRS lama / entry lama belum punya key ini)
    private function getDefault(): array
    {
        return ['field1' => '', 'field2' => ''];
    }

    // 3) SAVE — dipicu tombol sendiri ATAU broadcast save-all (silent=true)
    #[On('save-rm-<section>-rj')]
    public function save(bool $silent = false): void
    {
        if ($this->isFormLocked) return;
        $this->validateWithToast($rules, $messages, $attributes);

        DB::transaction(function () {
            $this->lockRJRow($this->rjNo);                    // row-lock anti race
            $data = $this->findDataRJ($this->rjNo) ?? [];
            // set HANYA key milik section ini — key section lain tak tersentuh:
            $data['<keySection>'] = $this->dataDaftarPoliRJ['<keySection>'] ?? [];
            $this->updateJsonRJ($this->rjNo, $data);
            $this->dataDaftarPoliRJ = $data;
        });

        $this->afterSave('<Section> tersimpan.', $silent);    // lihat kartu berikutnya
    }
};

// ── BAGIAN 2: MARKUP (setelah tag php penutup) ──
{{-- container ber-wire:key renderKey; SEMUA input hormati $isFormLocked,
     numerik pakai wire:model.blur (bukan .live) --}}
<div>
    <div class="flex flex-col w-full"
         wire:key="{{ $this->renderKey('modal-<section>-rj', [$rjNo ?? 'new']) }}">
        {{-- field-field section --}}
    </div>
</div>
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

'emr-after-save' => <<<'TXT'
// SETELAH save — tiap section menutup save()-nya dgn helper afterSave():
private function afterSave(string $message, bool $silent = false): void
{
    $this->incrementVersion('modal-anamnesa-rj');   // remount area → state segar
    $this->dispatch('refresh-after-rj.saved');      // kabari halaman list

    if (! $silent) {                                // silent saat dipanggil save-all
        $this->dispatch('toast', type: 'success', message: $message);
    }
}

// ...dan halaman list (pelayanan-rj) mendengarkan utk refresh presisi:
#[On('refresh-after-rj.saved')]
public function refreshAfterSaved(): void
{
    $this->incrementVersion('pelayanan-rj-toolbar');
    $this->resetPage();   // computed baseQuery re-run → status & % EMR ikut segar
}

// Padanan jalur: refresh-after-ugd.saved → pelayanan-ugd,
//                refresh-after-ri.saved  → daftar-ri (+ display-pasien-ri).
TXT,

'emr-eresep' => <<<'TXT'
// E-RESEP (diisi dokter) — modal SIBLING di atas EMR, bukan section SOAP.
// pages/transaksi/rj/eresep-rj/: host + tab NonRacikan + tab Racikan.
//
// Pemicu (2 tombol): header EMR (erm-rj) & tab Terapi di section Perencanaan
//   → dispatch('emr-rj.eresep.open', rjNo) → host buka modal 2 tab
//   → host menyebarkan open-eresep-non-racikan-rj / open-eresep-racikan-rj.

// NON-RACIKAN — obat dipilih via LOV product, target unik per tab:
#[On('lov.selected.eresepRjObatNonRacikan')]
public function eresepRjObatNonRacikan(string $target, array $payload): void { ... }

// insertProduct = DUAL-WRITE dalam SATU transaksi + lockRJRow:
public function insertProduct(): void
{
    DB::transaction(function () {
        $this->lockRJRow($this->rjNo);

        // 1) baris BILLING — dibaca apotek & administrasi:
        DB::table('rstxn_rjobats')->insert([ /* rjobat_dtl, product_id, qty, harga */ ]);

        // 2) key JSON 'eresep' — tampilan EMR (qty, signaX/signaHari, catatanKhusus):
        $data['eresep'][] = [ /* payload LOV + signa */ ];
        $this->updateJsonRJ($this->rjNo, $data);
    });
}
// update/remove obat juga sinkron DUA tempat itu dalam transaksi yang sama.
// RACIKAN sama polanya — key JSON 'eresepRacikan' + noRacikan (R1, R2…) + dosis/takar.

// Tombol "Simpan ke Terapi" di host — generate teks resep ke section Perencanaan:
public function saveAllEreseptoTerapi(): void
{
    // guard: pasien sudah pulang = terkunci (checkRJStatus)
    // format per baris: "R/ {nama} | No. {qty} | S {X}dd{hari} ({catatan})"
    $data['perencanaan']['terapi']['terapi'] = $eresepText . PHP_EOL . $eresepRacikanText;
    $this->updateJsonRJ($this->rjNo, $data);
    $this->dispatch('emr-rj.rekam-medis.open', $this->rjNo);   // reopen EMR, tanpa toast
}
// Setelah tersimpan, resep tampil di antrian-apotek-rj utk dilayani apoteker.

// Padanan jalur: eresep-ugd → dual-write ke rstxn_ugdobats;
// eresep-ri  → JSON saja: eresepHdr[n].eresep (multi-resep per rawatan) —
//              billing RI menyusul per-item via imtxn_sls* saat apotek memproses.
TXT,

'emr-penunjang' => <<<'TXT'
// KIRIM LAB & KIRIM RADIOLOGI — order penunjang dari SECTION PEMERIKSAAN.
// Komponen: emr-rj/pemeriksaan/penunjang/{laborat,radiologi}/rm-*-rj-actions
// (+ rm-daftar-* utk tampil hasil, + laborat LUAR utk hasil dari luar RS).

// Modal picker: pilih item dari master (multi-select, cari + paginate).
// Diagnosis/Keterangan Klinis WAJIB — order tanpa indikasi klinis ditolak:
public array  $selectedItems = [];        // [clabitem_id => item]
public string $klinisDesc    = '';        // rules: required

// KIRIM LAB — kirimLaboratorium():
public function kirimLaboratorium(): void
{
    // guard: minimal 1 item + pasien belum pulang (checkRJStatus)
    DB::transaction(function () use ($rjData) {
        $checkupNo = DB::scalar('SELECT NVL(MAX(TO_NUMBER(checkup_no)) + 1, 1) FROM lbtxn_checkuphdrs');

        DB::table('lbtxn_checkuphdrs')->insert([
            'checkup_no'     => $checkupNo,
            'dr_id'          => $rjData->dr_id,   // dokter PENGIRIM — dari rstxn_rjhdrs
            'checkup_status' => 'P',              // P = baru masuk antrian lab
            'klinis_desc'    => $this->klinisDesc,
            /* reg_no, checkup_date, shift, ... */
        ]);

        foreach ($this->selectedItems as $item) {
            $this->insertItemAndChildren($checkupNo, $item);  // item PAKET → child ikut
        }
    });

    $this->appendAdminLogRJ((int) $this->rjNo, 'Order Lab — ...', 'MR');
    $this->dispatch('laborat-order-terkirim');    // section Pemeriksaan refresh daftar
}
// → order muncul di modul Penunjang Laborat (siklus status P → C → H → F).

// KIRIM RADIOLOGI — kirimRadiologi(): pola sama, target rstxn_rjrads
// (rad_dtl max+1, klinis_desc juga wajib) → modul Radiologi (upload-based,
// TIDAK punya siklus status P/C/H/F seperti lab).

// HASIL KEMBALI ke EMR — petugas lab menekan kirim hasil, Pemeriksaan menerima:
#[On('laborat-kirim-penunjang')]
public function terimaPenunjangLaborat(string $text): void
{
    // teks hasil masuk key JSON penunjang milik section Pemeriksaan
    // + appendAdminLogRJ + afterSave(...) → tampil di EMR & ikut cetakan
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

'dokumen-skeleton' => <<<'TXT'
// KERANGKA FORM MULTI-ENTRI — pola TERBARU (penundaan-pelayanan-ri /
// permintaan-kerohanian-ri). Satu kunjungan bisa punya BANYAK entri;
// tiap entri hidup sendiri: Draft (bebas edit) → TTD (terkunci selamanya).

public bool   $isFormLocked = false;   // EMR terkunci / prop disabled
public bool   $viewOnly     = false;   // mode "Lihat" dari tabel entri
public string $signature    = '';      // TTD pasien/keluarga — dataURL signature-pad
public string $editingKey   = '';      // signatureDate entri = KUNCI STABIL
public array  $newForm      = [
    /* field-field form..., */
    'clauseVersion' => PenundaanClause::CURRENT,   // stempel versi klausul
];

public function mount(?string $riHdrNo = null, bool $disabled = false): void
{
    // muat list entri dari JSON; isFormLocked = checkEmrRIStatus() || $disabled
}

// DRAFT — boleh sebagian; entri yang sama terus di-update (tidak duplikat):
public function saveDraft(): void
{
    if ($this->isFormLocked || $this->viewOnly) return;

    $key = $this->editingKey ?: Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
    $this->persistEntry($key, false, 'Simpan draft');
    $this->editingKey = $key;              // lanjut edit entri yang sama
}

// TTD PETUGAS — validasi LENGKAP + TTD pasien wajib, lalu kunci permanen:
public function setPemberiInfo(): void
{
    // validate() penuh; guard signature pasien tidak boleh kosong
    $this->persistEntry($key, true, 'Kunci (TTD Petugas)');
}

// SATU pintu tulis — semua guard hidup di sini:
private function persistEntry(string $key, bool $finalized, string $logVerb): void
{
    DB::transaction(function () use ($key, $finalized, $logVerb) {
        $this->lockRIRow($this->riHdrNo);
        $data = $this->findDataRI($this->riHdrNo);

        $list = $data['penundaanPelayananRI'] ?? [];
        $idx  = collect($list)->search(fn($it) => ($it['signatureDate'] ?? '') === $key);

        if ($idx === false) {
            $list[] = $entry;                                  // entri baru
        } elseif ($this->entryIsFinal($list[$idx])) {
            throw new \RuntimeException('Entri sudah terkunci, tidak dapat diubah.');
        } else {
            $list[$idx] = $entry;                              // update draft
        }

        $data['penundaanPelayananRI'] = array_values($list);
        $this->updateJsonRI((int) $this->riHdrNo, $data);
        $this->appendAdminLogRI((int) $this->riHdrNo, $logVerb . ' ...', 'MR');
    });
}

// clauseVersion distempel saat entri DIBUAT dan dipertahankan saat edit —
// cetak ulang selalu memakai redaksi klausul SAAT DITANDATANGANI
// (registry App\Support\*Clause; baca docs/clause-versioning.md).
TXT,

'dokumen-clause' => <<<'TXT'
// CLAUSE VERSIONING — kenapa: teks klausul dokumen legal bisa berubah karena
// kebijakan (contoh nyata: transisi INA-CBG → iDRG). Cetak ulang record LAMA
// wajib memakai redaksi SAAT DITANDATANGANI, bukan redaksi terbaru.

// 1) Teks hidup di CLASS REGISTRY per-versi — bukan hardcoded di komponen/cetak:
//    app/Support/GeneralConsentClause.php (juga: PenjaminanClause, dst.)
class GeneralConsentClause
{
    public const CURRENT = 'v1';

    public static function get(string $context, ?string $version = null): array
    {
        $reg = self::registry();
        $ver = $version && isset($reg[$version]) ? $version : self::CURRENT;
        return $reg[$ver][$context] ?? [];
    }

    private static function registry(): array
    {
        return [
            'v1' => [ 'rj' => [...], 'ugd' => [...], 'ri' => [...] ],
            // versi baru = TAMBAH 'v2' + naikkan CURRENT — 'v1' JANGAN diubah
            // (versi lama = arsip legal). Bagian dinamis (%WALI% %HUB% %RS%)
            // diinterpolasi komponen via strtr, bukan disimpan di registry.
        ];
    }
}

// 2) Record MENSTEMPEL versi saat dibuat (di defaultConsent()/buildEntry()):
'clauseVersion' => GeneralConsentClause::CURRENT,

// 3) Cetak & Lihat me-render versi TERSIMPAN — fallback 'v1', BUKAN null:
//    record legacy pra-versioning tak punya stempel → wajib redaksi TERTUA
//    (?? null berarti CURRENT — salah utk cetak!). Di blade cetak, komponen
//    consent (x-consent.general-consent-rj dkk.) menerima prop version:
//      :version="$consent['clauseVersion'] ?? 'v1'"

// 4) Form entri BARU boleh ?? null (→ CURRENT); entri lama teruskan versi tersimpan.

// VERSIONING vs SNAPSHOT — jangan salah pilih:
//   Versioning (registry) → TEKS KLAUSUL, jarang berubah.
//   Snapshot (salin nilai ke entri) → DATA sering berubah per record,
//   mis. tarif/fasilitas kelas kamar: simpan salinan nama+tarif+fasilitas
//   saat buildEntry(); cetak prefer snapshot, fallback master utk legacy.

// WAJIB baca docs/clause-versioning.md (+ skill clause-versioning) SEBELUM
// mengubah teks klausul apa pun atau membuat dokumen ber-TTD baru.
TXT,

'dokumen-cetak' => <<<'TXT'
// POLA CETAK / PDF — berlaku utk SEMUA cetakan (dokumen, kwitansi, e-resep,
// hasil penunjang), bukan hanya modul dokumen.

// 1) Header identitas pasien = SATU komponen standar (x-pdf.identitas-pasien):
//    No RM · nama (gender) · tgl lahir (umur) · alamat · NIK.
//    Umur SELALU dihitung dari birth_date — kolom thn/bln/hari di master
//    adalah snapshot lama yang tidak pernah di-refresh.
//    Gender: mapping eksplisit L/P/- (JANGAN binary ==1 ? 'L' : 'P').

// 2) Blok TTD: pola h-16 + text-center (+ &nbsp; penahan tinggi) —
//    JANGAN flex / mx-auto / <br>; layout flex bergeser di PDF renderer.
//    Detail: docs/ttd-pattern-pdf-print.md.

// 3) Kelas Tailwind ARBITRARY tidak dirender di PDF:
//    text-[10px] / mt-[3mm] hilang DIAM-DIAM → utk ukuran cetak pakai
//    inline style (style="font-size:10px").

// 4) Viewer "Lihat" = iframe me-render blade cetak yang SAMA
//    (docs/dokumen-view-pattern.md) — satu sumber utk layar & kertas,
//    plus navigasi antar-record di dalam viewer.

// 5) Teks klausul di cetak = versi TERSIMPAN (lihat kartu clause versioning);
//    lokasi file cetak: pages/components/modul-dokumen/<jalur>/<form>/.
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

'administrasi-pos' => <<<'TXT'
// KERANGKA SATU POS — lain-lain-rj (pos paling generik).
// Satu pos = satu child Livewire ber-:rjNo yang baca-tulis TABEL BILLING
// (rstxn_rjothers dst.) — BUKAN JSON CLOB; JSON hanya utk stempel AdministrasiRj.

// Muat baris pos: tabel billing join master-nya
$this->rjLainLain = DB::table('rstxn_rjothers')
    ->join('rsmst_others', 'rsmst_others.other_id', 'rstxn_rjothers.other_id')
    ->where('rstxn_rjothers.rj_no', $rjNo)
    ->orderBy('rstxn_rjothers.rjo_dtl')->get();

// Tambah item: LOV target unik per pos + nomor detail max+1
#[On('lov.selected.lain-lain-rj')]
public function onLainLainSelected(?array $payload): void { /* isi form dari payload */ }

public function insertLainLain(): void
{
    // guard rj_status 'A' — lihat kartu "Model pengunci" di bawah
    DB::transaction(function () {
        $last = DB::table('rstxn_rjothers')
            ->select(DB::raw('nvl(max(rjo_dtl)+1,1) as rjo_dtl_max'))->first();
        DB::table('rstxn_rjothers')->insert([ /* rjo_dtl, rj_no, other_id, other_price */ ]);
    });

    $this->dispatch('administrasi-rj.updated');   // ← host menghitung ulang sumAll()
}
// Edit inline: startEdit / saveEdit / cancelEdit — SETIAP mutasi dispatch
// administrasi-rj.updated; listener rj.administrasi-selesai men-disable pos.

// SISI HOST — embed tiap pos sebagai tab + dengarkan mutasi:
<livewire:pages::transaksi.rj.administrasi-rj.lain-lain-rj :rjNo="$rjNo" />

#[On('administrasi-rj.updated')]
public function onPosUpdated(): void
{
    $this->sumAll();          // grand total & breakdown selalu segar
}
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

'lock-model' => <<<'TXT'
// MODEL PENGUNCI — E-Resep, Kirim Lab, dan Kirim Radiologi semuanya menulis
// baris TAGIHAN yang dibaca Administrasi, maka ketiganya tunduk pada lapisan
// kunci yang sama. Sumber kebenaran: rstxn_rjhdrs.rj_status.

// L1 · KUNCI FINANSIAL — rj_status (checkRJStatus = rj_status !== 'A'):
//   'A' aktif    → resep / order / pos administrasi masih boleh berubah
//   'L' lunas    → di-set kasir saat posting bayar (txn_status 'L', bon = 'H')
//   'I' transfer → kasir memindahkan biaya RJ ke transaksi UGD
//   batal posting → kembali 'A' (tagihan bisa diubah lagi)
// SEMUA pintu mutasi tagihan memeriksa ini: insertProduct e-resep,
// kirimLaboratorium / kirimRadiologi, pos administrasi, posting kasir (idempoten).

// L2 · KUNCI URUTAN — order lab pending MENAHAN kasir:
if ($this->checkLabPendingRJ($this->rjNo)) {      // ada checkup_status = 'P'
    // 'Hasil Laborat belum selesai, pembayaran tidak bisa diproses.'
    // → tagihan belum final; posting ditolak sampai order lab selesai.
}

// L3 · KUNCI KEPEMILIKAN — administrasi yang sudah disimpan petugas lain:
//   JSON AdministrasiRj.userLog terisi → 'Administrasi sudah tersimpan oleh X'.

// L4 · KUNCI KONKURENSI — setiap tulis: DB::transaction + lockRJRow /
//   lockForUpdate (bab 03) — dua user tidak saling menimpa.

// L5 · KUNCI KLINIS (longgar, beda dgn finansial):
//   - erm_status: checkEmrRJStatus saat ini SENGAJA selalu false — kebijakan:
//     EMR tetap bisa diedit, cukup terjejak appendAdminLog (tab Log Aktivitas)
//   - dokumen ber-TTD: isFormLocked per-form setelah tanda tangan (bab 07)

// + KUNCI LINTAS JALUR: transfer RJ→UGD juga men-set
//   rsmst_pasiens.lockstatus = 'UGD' — pasien dipegang SATU jalur aktif.
TXT,

'api-trait' => <<<'TXT'
// SATU TRAIT PER API EKSTERNAL — pola sama utk semua: VclaimTrait, AntrianTrait,
// AplicaresTrait, iCareTrait, SirsTrait, iDrgTrait, SatuSehatTrait.
// Template + checklist lengkap: docs/trait-template-api-eksternal.md
// (ikuti polanya → log otomatis tampil di /database-monitor/log-bpjs).
//
// Tiga grup method di tiap trait:
//   Response helpers : sendResponse() / sendError()  — bentuk seragam + logging
//   Auth & crypto    : signature() / stringDecrypt() — BPJS: HMAC-SHA256 +
//                      AES + LZString; iDRG: AES-CBC; SATU SEHAT: OAuth2
//   API methods      : SATU method statis per endpoint

// CONTOH 1 — VClaim: buat SEP (VclaimTrait::sep_insert):
$signature = self::signature();                    // cons_id + timestamp + HMAC
$response  = Http::timeout(8)->connectTimeout(3)   // WAJIB — tanpa ini worker freeze
    ->withHeaders($signature)
    ->post($url, $SEPJsonReq);
return self::response_decrypt($response, $signature, $url,
    $response->transferStats->getTransferTime());  // decrypt AES+LZString + log

// CONTOH 2 — Antrean BPJS: lapor task-id tiap tahap pelayanan:
AntrianTrait::update_antrean($kodebooking, $taskid, $waktu, $jenisresep);
// taskId 3–7 = tahapan pelayanan (tiba di poli → obat diserahkan), 99 = batal.
// Stempelnya juga disimpan di JSON taskIdPelayanan — dipakai badge status list.
// Guard idempoten: taskId N butuh taskId N-1 sudah ada.

// CONTOH 3 — SATU SEHAT: token OAuth2 di-CACHE, bukan login tiap request:
Cache::remember('satusehat_access_token', 3500, function () {
    // POST accesstoken?grant_type=client_credentials → access_token
});
$client = Http::timeout(8)->connectTimeout(3)->withToken($token);

// Aturan memanggil dari Livewire:
//   - selalu try/catch → toast; kegagalan API tidak boleh jadi error 500;
//   - respons penting DISIMPAN (JSON kunjungan / tabel log) utk audit & retry;
//   - jangan panggil API di computed/render — hanya dari aksi user.
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
                'tambah-fitur' => 'Alur: Tambah Fitur',
                'ranjau'       => 'Ranjau Umum',
                'adopsi'       => 'Checklist Adopsi',
                'referensi'    => 'Trait & Referensi',
                'glosarium'    => 'Glosarium Istilah',
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

                        <div class="ds-card-outline mt-4" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Dapat tugas menambah fitur?</strong> Langsung ke bab
                                <button type="button" class="hover:underline font-semibold" style="color:var(--primary)"
                                    x-on:click="go('tambah-fitur')">Alur: Tambah Fitur</button>
                                — step-by-step tiga skenario paling umum (section EMR baru, form modul
                                dokumen baru, pos administrasi baru); bab lain jadi referensi detailnya.
                                Menemukan singkatan asing (SEP, CPPT, PRB...)? Buka bab
                                <button type="button" class="hover:underline font-semibold" style="color:var(--primary)"
                                    x-on:click="go('glosarium')">Glosarium Istilah</button>.
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

                        <div class="ds-card-dark mt-6" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Kerangka save pendaftaran — nomor, antrian, guard, status awal</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['pendaftaran-save'] }}</pre>
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

                        @php
                            $listBadge = 'display:inline-flex;align-items:center;justify-content:center;min-width:20px;height:20px;border-radius:9999px;background:var(--primary);color:#fff;font-size:11px;font-weight:700;line-height:1;flex:none';
                        @endphp

                        {{-- visual anatomi list transaksi --}}
                        <div class="ds-frame mt-2 mb-2">
                            <div class="ds-frame-label">Tata letak list transaksi (daftar-rj / antrian-*)</div>
                            <div class="mt-3" style="border:1px solid var(--hairline); border-radius:14px; overflow:hidden; background:var(--canvas)">

                                {{-- toolbar --}}
                                <div class="flex flex-wrap items-center gap-2 px-4 py-3" style="position:relative; background:var(--surface-soft); border-bottom:1px solid var(--hairline)">
                                    <div style="height:34px;padding:8px 12px;border-radius:8px;border:1px solid var(--hairline);background:var(--canvas);color:var(--muted-soft);font-size:13px;display:flex;align-items:center;font-family:var(--mono)">10/07/2026 📅</div>
                                    <div style="height:34px;padding:8px 12px;border-radius:8px;border:1px solid var(--hairline);background:var(--canvas);color:var(--muted-soft);font-size:13px;display:flex;align-items:center;width:160px">Cari pasien...</div>
                                    <span class="ds-btn ds-btn-primary" style="height:34px; padding:6px 12px; font-size:12px">+ Daftar Baru</span>
                                    <span style="{{ $listBadge }};position:absolute;top:8px;right:8px">1</span>
                                </div>

                                {{-- baris pasien --}}
                                <div class="px-4 py-3" style="position:relative; border-bottom:1px solid var(--hairline)">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <span>
                                            <span class="block text-sm"><span style="font-family:var(--mono); color:var(--primary)">012345</span> · <strong style="color:var(--ink)">FULANAH</strong> <span style="color:var(--muted)">(P)</span></span>
                                            <span class="block text-xs" style="color:var(--muted)">01/01/1990 (36 th) · JL. MAWAR NO. 1, TULUNGAGUNG</span>
                                        </span>
                                        <span class="flex items-center gap-1.5">
                                            <span class="px-2 py-0.5 text-xs font-medium rounded-full" style="background:var(--success-tint); color:var(--success-deep)">Task 5 · Dilayani</span>
                                            <span class="ds-caption" style="color:var(--muted)">EMR · SEP · Adm</span>
                                        </span>
                                    </div>
                                    <span style="{{ $listBadge }};position:absolute;top:8px;right:8px;background:var(--info)">2</span>
                                </div>

                                {{-- pagination + poll --}}
                                <div class="flex items-center justify-between px-4 py-2.5" style="position:relative; background:var(--surface-soft)">
                                    <span class="ds-caption" style="color:var(--muted)">Menampilkan 1–10 dari 128 kunjungan</span>
                                    <span class="ds-caption" style="color:var(--muted)">‹ 1 2 3 ›</span>
                                    <span style="{{ $listBadge }};position:absolute;top:8px;right:8px">3</span>
                                </div>
                            </div>
                        </div>

                        {{-- legenda list transaksi --}}
                        <div class="grid grid-cols-1 gap-2 mb-6 sm:grid-cols-2">
                            @foreach ([
                                ['1', 'Toolbar — filter TANGGAL (default hari ini; kunci utama scope query) + cari + tombol Daftar; antrian kasir/apotek menambah wire:poll.30s', ''],
                                ['2', 'Baris pasien — identitas standar list: No RM · nama (gender) · tgl lahir (umur, dihitung dari birth_date) · alamat; badge status DIHITUNG dari task-id di JSON (3–7, 99 = batal); tombol aksi membuka modal (EMR/SEP/Administrasi)', 'background:var(--info)'],
                                ['3', 'Pagination DB-level — paginate() di query, decode CLOB hanya utk page aktif via transform()', ''],
                            ] as [$num, $ket, $extra])
                                <div class="flex items-start gap-2.5">
                                    <span style="{{ $listBadge }}; margin-top:2px; {{ $extra }}">{{ $num }}</span>
                                    <span class="ds-body-sm">{{ $ket }}</span>
                                </div>
                            @endforeach
                        </div>

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
                                Cara MEMBUAT satu section dari nol: lihat kartu "Membuat section — kerangka utuh" di bawah.
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
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Membuat section — kerangka utuh rm-&lt;section&gt;-rj-actions.blade.php</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['emr-section-skeleton'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Di dalam section — save dgn flag silent</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['emr-save'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Setelah save — afterSave() &amp; refresh list (refresh-after-&lt;jalur&gt;.saved)</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['emr-after-save'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">E-Resep dokter — modal sibling, tab Racikan / Non-Racikan, dual-write</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['emr-eresep'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Kirim Lab &amp; Kirim Radiologi — order penunjang dari section Pemeriksaan</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['emr-penunjang'] }}</pre>
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

                        @php
                            $dokBadge = 'display:inline-flex;align-items:center;justify-content:center;min-width:20px;height:20px;border-radius:9999px;background:var(--primary);color:#fff;font-size:11px;font-weight:700;line-height:1;flex:none';
                        @endphp

                        {{-- visual anatomi form dokumen multi-entri --}}
                        <div class="ds-frame mt-2 mb-2">
                            <div class="ds-frame-label">Tata letak form dokumen multi-entri (modal)</div>
                            <div class="mt-3" style="border:1px solid var(--hairline); border-radius:14px; overflow:hidden; background:var(--canvas)">

                                {{-- header --}}
                                <div class="flex items-center justify-between gap-3 px-4 py-3" style="background:var(--surface-soft); border-bottom:1px solid var(--hairline)">
                                    <span class="flex items-center gap-2">
                                        <span class="ds-title-sm">Penundaan Pelayanan</span>
                                        <span class="px-2 py-0.5 text-xs font-medium rounded-full" style="background:var(--info-tint); color:var(--info-deep)">2 entri</span>
                                    </span>
                                    <span class="flex items-center gap-2">
                                        <span style="color:var(--muted)">✕</span>
                                        <span style="{{ $dokBadge }}">1</span>
                                    </span>
                                </div>

                                {{-- tabel entri multi-record --}}
                                <div class="px-4 py-3" style="position:relative; border-bottom:1px solid var(--hairline)">
                                    <div class="flex flex-wrap items-center gap-2 py-1.5" style="border-bottom:1px solid var(--hairline-soft)">
                                        <span class="ds-body-sm" style="font-family:var(--mono)">08/07/2026 10:12</span>
                                        <span class="px-2 py-0.5 text-xs font-medium rounded-full" style="background:var(--success-tint); color:var(--success-deep)">Terkunci</span>
                                        <span class="ds-caption" style="color:var(--muted)">aksi: Lihat</span>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2 py-1.5">
                                        <span class="ds-body-sm" style="font-family:var(--mono)">10/07/2026 08:45</span>
                                        <span class="px-2 py-0.5 text-xs font-medium rounded-full" style="background:var(--warning-tint); color:var(--warning-deep)">Draft</span>
                                        <span class="ds-caption" style="color:var(--muted)">aksi: Edit · TTD &amp; Kunci</span>
                                    </div>
                                    <span style="{{ $dokBadge }};position:absolute;top:10px;right:12px;background:var(--info)">2</span>
                                </div>

                                {{-- form entri aktif --}}
                                <div class="px-4 py-3" style="position:relative; border-bottom:1px solid var(--hairline)">
                                    <span class="block mb-1 text-xs font-medium" style="color:var(--body)">Alasan penundaan</span>
                                    <div class="flex items-center gap-2">
                                        <div style="height:34px;padding:8px 12px;border-radius:8px;border:1px solid var(--hairline);background:var(--canvas);color:var(--muted-soft);font-size:13px;flex:1;display:flex;align-items:center">Menunggu hasil laboratorium...</div>
                                        <span class="px-2 py-1.5 text-xs rounded-lg" style="border:1px solid var(--hairline); color:var(--muted)" title="x-now-button">🕐</span>
                                    </div>
                                    <span style="{{ $dokBadge }};position:absolute;top:10px;right:12px">3</span>
                                </div>

                                {{-- area TTD --}}
                                <div class="grid grid-cols-1 gap-3 px-4 py-3 sm:grid-cols-2" style="position:relative; border-bottom:1px solid var(--hairline)">
                                    <div class="p-3 text-center" style="border:1px dashed var(--hairline); border-radius:10px">
                                        <span class="ds-caption" style="color:var(--muted)">TTD Pasien / Keluarga</span>
                                        <div class="mt-4 mb-1 mx-8" style="border-bottom:1px solid var(--muted-soft)"></div>
                                        <span class="ds-caption" style="color:var(--muted-soft)">signature-pad (dataURL) — bisa menyusul</span>
                                    </div>
                                    <div class="p-3 text-center" style="border:1px dashed var(--hairline); border-radius:10px">
                                        <span class="ds-caption" style="color:var(--muted)">TTD Petugas</span>
                                        <div class="mt-3 mb-1 text-sm font-semibold" style="color:var(--ink)">Ns. FULAN, S.Kep</div>
                                        <span class="ds-caption" style="color:var(--muted-soft)">komponen ttd-petugas — klik = stempel nama + kode</span>
                                    </div>
                                    <span style="{{ $dokBadge }};position:absolute;top:10px;right:12px">4</span>
                                </div>

                                {{-- footer --}}
                                <div class="flex items-center justify-end gap-2 px-4 py-3" style="background:var(--surface-soft)">
                                    <span class="ds-btn ds-btn-secondary" style="height:32px; padding:6px 12px; font-size:12px">Simpan Draft</span>
                                    <span class="ds-btn ds-btn-primary" style="height:32px; padding:6px 12px; font-size:12px">TTD &amp; Kunci</span>
                                    <span style="{{ $dokBadge }}">5</span>
                                </div>
                            </div>
                        </div>

                        {{-- legenda anatomi dokumen --}}
                        <div class="grid grid-cols-1 gap-2 mb-6 sm:grid-cols-2">
                            @foreach ([
                                ['1', 'Header form + jumlah entri — dibuka dari host: RI = tab per form (modul-dokumen-ri), RJ = kartu + tombol Buka', ''],
                                ['2', 'Tabel entri multi-record — Draft (kuning: bisa Edit) vs Terkunci (hijau: hanya Lihat = viewer iframe render blade cetak); kunci stabil entri = signatureDate', 'background:var(--info)'],
                                ['3', 'Form entri aktif — semua input di-guard isFormLocked / viewOnly; tombol jam = x-now-button', ''],
                                ['4', 'TTD pasien = signature-pad (bisa "TTD menyusul"/staged) · TTD petugas = komponen ttd-petugas (stempel nama + ttdCode, guard server-side)', ''],
                                ['5', 'Simpan Draft (validasi minimal) vs TTD & Kunci (validasi lengkap → entri terkunci permanen)', ''],
                            ] as [$num, $ket, $extra])
                                <div class="flex items-start gap-2.5">
                                    <span style="{{ $dokBadge }}; margin-top:2px; {{ $extra }}">{{ $num }}</span>
                                    <span class="ds-body-sm">{{ $ket }}</span>
                                </div>
                            @endforeach
                        </div>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Dua tahap: draft vs finalize — rm-general-consent-rj-actions</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['dokumen-flow'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Membuat form — kerangka utuh multi-entri (penundaan-pelayanan-ri)</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['dokumen-skeleton'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Clause versioning — registry per-versi (GeneralConsentClause) + snapshot</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['dokumen-clause'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Pola cetak / PDF — header identitas, TTD, jebakan Tailwind arbitrary</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['dokumen-cetak'] }}</pre>
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

                        {{-- Tanda tangan & buka kunci (baku sejak Inform Consent / Akhir Hayat) --}}
                        <div class="grid grid-cols-1 gap-4 mt-4 sm:grid-cols-2">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Tanda tangan: 3 pihak, petugas TERAKHIR</div>
                                <div class="ds-body-sm">
                                    Pasien/keluarga (wajib) &middot; saksi (opsional, tampil langsung) &middot; petugas.
                                    <strong>TTD petugas = aksi terakhir yang sekaligus MENGUNCI</strong> entri
                                    (<span class="ds-code">setDokterPenjelas</span> / <span class="ds-code">ttdPetugas</span>) —
                                    JANGAN bikin tombol &ldquo;Simpan &amp; Kunci&rdquo; terpisah; footer cukup Simpan Draft.
                                    TTD masuk <span class="ds-code">rules()</span> supaya errornya merah di kolomnya, bukan cek manual.
                                </div>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Buka kunci (unlock)</div>
                                <div class="ds-body-sm">
                                    Hanya <span class="ds-code">Admin | Manager Umum | Manager Medis</span>, gate DUA lapis
                                    (<span class="ds-code">&#64;hasanyrole</span> di tombol + cek role di server). Mencabut
                                    <span class="ds-code">finalized</span> + <strong>TTD petugas saja</strong>; TTD pasien &amp;
                                    saksi DIPERTAHANKAN. Wajib <span class="ds-code">appendAdminLogRI(&hellip;, 'MR')</span>
                                    yang menyebut pelakunya.
                                </div>
                            </div>
                        </div>

                        <div class="ds-card-outline mt-4" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Rincian pola (struktur file, siklus entri, rancangan panel &amp; opsi, jebakan Blade
                                escape-ganda <span class="ds-code">&amp;amp;</span> pada prop komponen) ada di
                                <span class="ds-code">docs/modul-dokumen-ri-pattern.md</span>.
                            </span>
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

                        @php
                            $admBadge = 'display:inline-flex;align-items:center;justify-content:center;min-width:20px;height:20px;border-radius:9999px;background:var(--primary);color:#fff;font-size:11px;font-weight:700;line-height:1;flex:none';
                        @endphp

                        {{-- visual anatomi modal administrasi --}}
                        <div class="ds-frame mt-2 mb-2">
                            <div class="ds-frame-label">Tata letak modal Administrasi (administrasi-rj)</div>
                            <div class="mt-3" style="border:1px solid var(--hairline); border-radius:14px; overflow:hidden; background:var(--canvas)">

                                {{-- row 1: identitas + total + close --}}
                                <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3" style="position:relative; background:var(--surface-soft); border-bottom:1px solid var(--hairline)">
                                    <span class="ds-body-sm" style="color:var(--muted)">Identitas pasien (display-pasien-rj)</span>
                                    <span class="px-3 py-1.5 rounded-xl" style="border:1px solid var(--hairline); background:var(--canvas)">
                                        <span class="block ds-caption" style="color:var(--muted)">Total Tagihan</span>
                                        <span class="block text-sm font-bold" style="color:var(--ink)">Rp 385.000 ▾</span>
                                    </span>
                                    <span class="flex items-center gap-2">
                                        <span style="color:var(--muted)">✕</span>
                                        <span style="{{ $admBadge }}">1</span>
                                    </span>
                                </div>

                                {{-- row 2: breakdown pos --}}
                                <div class="flex flex-wrap items-center gap-1.5 px-4 py-2" style="position:relative; border-bottom:1px solid var(--hairline); background:var(--surface-soft)">
                                    @foreach (['Adm RS', 'Adm RJ', 'Poli', 'Js Karyawan', 'Js Dokter', 'Js Medis', 'Obat', 'Lab', 'Rad', 'Lain-lain'] as $pos)
                                        <span class="px-2 py-0.5 text-xs rounded-full" style="border:1px solid var(--hairline); background:var(--canvas); color:var(--body)">{{ $pos }}</span>
                                    @endforeach
                                    <span style="{{ $admBadge }};background:var(--info)">2</span>
                                </div>

                                {{-- row 3: tab pos --}}
                                <div class="flex flex-wrap items-center gap-3 px-4 py-2" style="position:relative; border-bottom:1px solid var(--hairline)">
                                    @foreach (['Jasa Dokter', 'Obat', 'Laboratorium', 'Lain-lain', 'Kasir'] as $i => $tabPos)
                                        <span class="text-sm {{ $i === 3 ? 'font-bold' : '' }}"
                                            style="color:{{ $i === 3 ? 'var(--primary)' : 'var(--muted)' }}; {{ $i === 3 ? 'border-bottom:2px solid var(--primary); padding-bottom:2px' : '' }}">{{ $tabPos }}</span>
                                    @endforeach
                                    <span class="ds-caption" style="color:var(--muted)">…</span>
                                    <span style="{{ $admBadge }}">3</span>
                                </div>

                                {{-- panel pos aktif --}}
                                <div class="px-4 py-3" style="position:relative">
                                    <p class="ds-body-sm" style="color:var(--muted)">
                                        Tabel baris pos aktif (child Livewire ber-<span class="ds-code">:rjNo</span>) —
                                        tambah item via LOV, edit inline per baris, hapus dgn konfirmasi.
                                    </p>
                                    <span style="{{ $admBadge }};position:absolute;top:10px;right:12px">4</span>
                                </div>

                                {{-- footer --}}
                                <div class="flex items-center justify-end gap-2 px-4 py-3" style="position:relative; border-top:1px solid var(--hairline); background:var(--surface-soft)">
                                    <span class="ds-btn ds-btn-primary" style="height:32px; padding:6px 12px; font-size:12px">Selesai Administrasi</span>
                                    <span style="{{ $admBadge }}">5</span>
                                </div>
                            </div>
                        </div>

                        {{-- legenda anatomi --}}
                        <div class="grid grid-cols-1 gap-2 mb-6 sm:grid-cols-2">
                            @foreach ([
                                ['1', 'Header — identitas pasien + kartu Total Tagihan (klik = buka/tutup rincian) + tutup modal', ''],
                                ['2', 'Rincian breakdown 10 pos hasil sumAll() — 3 dari kolom header (adm RS/RJ/poli), 7 dari SUM tabel billing per pos', 'background:var(--info)'],
                                ['3', 'Tab per pos + tab Kasir — tiap tab adalah child Livewire sendiri ber-:rjNo (file per pos)', ''],
                                ['4', 'Panel pos aktif — mutasi apa pun dispatch administrasi-rj.updated → host re-sumAll()', ''],
                                ['5', 'Selesai Administrasi — stempel userLog ke JSON + set status → pasien masuk antrian kasir (poll 30s)', ''],
                            ] as [$num, $ket, $extra])
                                <div class="flex items-start gap-2.5">
                                    <span style="{{ $admBadge }}; margin-top:2px; {{ $extra }}">{{ $num }}</span>
                                    <span class="ds-body-sm">{{ $ket }}</span>
                                </div>
                            @endforeach
                        </div>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Pos biaya &amp; total — administrasi-rj</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['administrasi'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Membuat pos — kerangka utuh satu pos + wiring host (lain-lain-rj)</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['administrasi-pos'] }}</pre>
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

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Model pengunci — rj_status · lab pending · userLog · row-lock · TTD</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['lock-model'] }}</pre>
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

                    {{-- ====== 09 ALUR TAMBAH FITUR ====== --}}
                    <section x-show="section === 'tambah-fitur'" x-cloak>
                        <div class="ds-eyebrow mb-3">09 — Adopsi</div>
                        <h1 class="ds-display-md mb-4">Alur: Tambah Fitur</h1>
                        <p class="ds-body-md mb-8" style="max-width:62ch">
                            Pekerjaan paling sering di modul transaksi <strong>bukan membuat jalur baru</strong>,
                            melainkan menambah fitur di jalur yang sudah ada. Tiga skenario paling umum
                            di bawah — prinsipnya sama dengan modul master: <strong>salin acuan terdekat,
                            jangan menulis dari nol</strong>. Contoh path memakai satu jalur;
                            sesuaikan untuk jalur lain (ingat: RJ / UGD / RI tidak identik).
                        </p>

                        @php
                            $fiturCircle = 'display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:9999px;background:var(--primary);color:#fff;font-weight:700;font-size:13px;flex:none';
                            $fiturSkenario = [
                                [
                                    'judul' => 'A · Section EMR baru (contoh: RJ)',
                                    'acuan' => 'pages/transaksi/rj/emr-rj/anamnesa/',
                                    'steps' => [
                                        'Buat folder section + satu file actions: <span class="ds-code">emr-rj/&lt;section&gt;/rm-&lt;section&gt;-rj-actions.blade.php</span> — salin section acuan yang paling mirip; kerangka utuhnya (open → default → save → markup) ada di Bab 06. Section = child Livewire mandiri yang menerima <span class="ds-code">:rjNo</span>.',
                                        'Sepakati <strong>key JSON</strong> section di CLOB — bukan kolom baru. Simpan lewat trait jalur: <span class="ds-code">lockRJRow → findDataRJ → array_replace → updateJsonRJ</span> di dalam <span class="ds-code">DB::transaction</span> (Bab 03).',
                                        'Mount di host <span class="ds-code">erm-rj.blade.php</span> dengan <span class="ds-code">:rjNo</span> + <span class="ds-code">wire:key</span>, lalu daftarkan event <span class="ds-code">save-rm-&lt;section&gt;-rj</span> ke daftar <span class="ds-code">save-events</span> supaya ikut tombol Simpan Semua — save menerima flag <span class="ds-code">silent</span> (Bab 06).',
                                        'Tutup save() dengan helper <span class="ds-code">afterSave()</span>: incrementVersion area modal + dispatch <span class="ds-code">refresh-after-rj.saved</span> + toast (hormati flag silent). Halaman list mendengarkan event itu untuk me-refresh status &amp; persen kelengkapan — tanpa ini, data tersimpan tapi layar basi (Bab 06).',
                                        'Hormati <span class="ds-code">isFormLocked</span> (read-only penuh) dan pakai <span class="ds-code">wire:model.blur</span> untuk input numerik. Method jangan senama dengan trait EMR lain — helper lintas section = class statis.',
                                        'Bila section masuk hitungan kelengkapan EMR → tambah bobotnya di <span class="ds-code">EmrCompletenessRJTrait</span>; bila datanya tampil di display / cetakan lain (resume medis dsb.) → update konsumennya sekalian.',
                                        '<strong>Uji</strong>: buka EMR pasien uji → isi &amp; Simpan (toast muncul) → tombol Simpan Semua (satu toast gabungan, bukan beruntun) → list ter-refresh (status / persen kelengkapan berubah) → buka kunjungan LAMA yang JSON-nya belum punya key section — tidak boleh error.',
                                    ],
                                ],
                                [
                                    'judul' => 'B · Form Modul Dokumen baru (contoh: RI)',
                                    'acuan' => 'pages/transaksi/ri/emr-ri/modul-dokumen/penundaan-pelayanan-ri/ (template pola terbaru)',
                                    'steps' => [
                                        'Salin folder form acuan → <span class="ds-code">modul-dokumen/&lt;form&gt;-ri/rm-&lt;form&gt;-ri-actions.blade.php</span>. Siklus Draft → TTD → terkunci → Lihat sudah terbawa dari template; tinggal ganti field &amp; label.',
                                        'Buat blade cetak: <span class="ds-code">pages/components/modul-dokumen/r-i/&lt;form&gt;-ri/cetak-&lt;form&gt;-ri-print.blade.php</span> — header identitas pasien standar (komponen x-pdf.identitas-pasien) + pola TTD cetak standar.',
                                        'Buat viewer Lihat: <span class="ds-code">pages/components/rekam-medis/r-i/dokumen-view/&lt;form&gt;-view-ri.blade.php</span> — iframe yang merender blade cetak (docs/dokumen-view-pattern.md).',
                                        'Registrasi di <strong>dua tempat</strong> pada host <span class="ds-code">modul-dokumen-ri.blade.php</span>: tab / tombol pembuka + embed komponen actions dengan <span class="ds-code">wire:key</span> per <span class="ds-code">riHdrNo</span>.',
                                        'Teks klausul legal <strong>wajib versioning</strong> (<span class="ds-code">App\Support\*Clause</span> — baca <span class="ds-code">docs/clause-versioning.md</span> dulu), dan nilai pre-fill di-sync ulang di save()/finalize supaya tidak kosong di cetak (Bab 07).',
                                        '<strong>Uji</strong>: buat entri → Simpan Draft → Edit lagi (harus entri yang SAMA, bukan duplikat) → TTD &amp; Kunci → coba edit lagi (harus tertolak) → Lihat &amp; cetak: identitas pasien, TTD, dan teks klausul tampil benar.',
                                    ],
                                ],
                                [
                                    'judul' => 'C · Pos administrasi baru (contoh: RJ)',
                                    'acuan' => 'pages/transaksi/rj/administrasi-rj/lain-lain-rj.blade.php',
                                    'steps' => [
                                        'Buat file pos: <span class="ds-code">administrasi-rj/&lt;pos&gt;-rj.blade.php</span> — satu pos = satu file partial; salin pos acuan yang paling mirip.',
                                        'Include pos di host <span class="ds-code">administrasi-rj.blade.php</span> dan tambahkan <span class="ds-code">sum&lt;Pos&gt;</span> ke <span class="ds-code">sumAll()</span> — kalau lupa, grand total &amp; tagihan kasir salah diam-diam (Bab 08).',
                                        'Mutasi finansial selalu <span class="ds-code">DB::transaction</span> + <span class="ds-code">lockForUpdate</span>; nominal di UI pakai <span class="ds-code">x-text-input-number</span>.',
                                        'Catat aksi admin lewat <span class="ds-code">appendAdminLogRJ()</span> (muncul di tab Log Aktivitas); aksi sensitif — hapus / ubah tarif / posting — di-guard role.',
                                        '<strong>Uji</strong>: tambah item pos → grand total &amp; breakdown berubah → Selesai Administrasi → pasien muncul di antrian kasir → posting bayar (rj_status jadi L; coba tambah item — harus tertolak) → batal posting → kembali A dan bisa diubah lagi.',
                                    ],
                                ],
                            ];
                        @endphp

                        @foreach ($fiturSkenario as $sk)
                            <h2 class="ds-title-lg {{ $loop->first ? '' : 'mt-10' }} mb-1">{{ $sk['judul'] }}</h2>
                            <p class="ds-caption mb-4" style="color:var(--muted)">Acuan / template: <span class="ds-code">{{ $sk['acuan'] }}</span></p>
                            <div>
                                @foreach ($sk['steps'] as $step)
                                    <div class="flex gap-4">
                                        <div class="flex flex-col items-center">
                                            <span style="{{ $fiturCircle }}">{{ $loop->iteration }}</span>
                                            @if (! $loop->last)
                                                <span class="flex-1" style="width:2px; background:var(--hairline); margin-top:4px"></span>
                                            @endif
                                        </div>
                                        <div class="flex-1 {{ $loop->last ? '' : 'pb-5' }}" style="min-width:0">
                                            <p class="ds-body-sm" style="max-width:62ch; padding-top:5px">{!! $step !!}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            @if ($loop->first)
                                <h3 class="ds-title-sm mt-6 mb-2">Padanan per jalur — langkahnya sama, namanya yang beda</h3>
                                <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                                    <table class="ds-table">
                                        <thead>
                                            <tr><th>Hal</th><th>RJ</th><th>UGD</th><th>RI</th></tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td class="ds-td-strong">Folder section</td>
                                                <td class="ds-td-class">rj/emr-rj/&lt;section&gt;/</td>
                                                <td class="ds-td-class">ugd/emr-ugd/&lt;section&gt;/</td>
                                                <td class="ds-td-class">ri/emr-ri/&lt;section&gt;-ri/</td>
                                            </tr>
                                            <tr>
                                                <td class="ds-td-strong">Host EMR</td>
                                                <td class="ds-td-class">erm-rj.blade.php</td>
                                                <td class="ds-td-class">erm-ugd.blade.php</td>
                                                <td class="ds-td-class">erm-ri.blade.php <span class="ds-body-sm">(section terdaftar di array key/label/saveEvent)</span></td>
                                            </tr>
                                            <tr>
                                                <td class="ds-td-strong">Prop kunci</td>
                                                <td class="ds-td-class">:rjNo (rstxn_rjhdrs)</td>
                                                <td class="ds-td-class">:rjNo (rstxn_ugdhdrs)</td>
                                                <td class="ds-td-class">:riHdrNo (rstxn_rihdrs)</td>
                                            </tr>
                                            <tr>
                                                <td class="ds-td-strong">Trait &amp; method</td>
                                                <td class="ds-td-class">EmrRJTrait<br>lockRJRow · findDataRJ · updateJsonRJ</td>
                                                <td class="ds-td-class">EmrUGDTrait<br>lockUGDRow · findDataUGD · updateJsonUGD</td>
                                                <td class="ds-td-class">EmrRITrait<br>lockRIRow · findDataRI · updateJsonRI</td>
                                            </tr>
                                            <tr>
                                                <td class="ds-td-strong">Event save</td>
                                                <td class="ds-td-class">save-rm-&lt;section&gt;-rj</td>
                                                <td class="ds-td-class">save-rm-&lt;section&gt;-ugd</td>
                                                <td class="ds-td-class">save-rm-&lt;section&gt;-ri <span class="ds-body-sm">(multi-record aktif: save-active-rm-*-ri)</span></td>
                                            </tr>
                                            <tr>
                                                <td class="ds-td-strong">Refresh after-save</td>
                                                <td class="ds-td-class">refresh-after-rj.saved<br><span class="ds-body-sm">→ pelayanan-rj</span></td>
                                                <td class="ds-td-class">refresh-after-ugd.saved<br><span class="ds-body-sm">→ pelayanan-ugd</span></td>
                                                <td class="ds-td-class">refresh-after-ri.saved<br><span class="ds-body-sm">→ daftar-ri + display-pasien-ri</span></td>
                                            </tr>
                                            <tr>
                                                <td class="ds-td-strong">Kelengkapan</td>
                                                <td class="ds-td-class">EmrCompletenessRJTrait<br><span class="ds-body-sm">S15 / O25 / A25 / P25 / N10</span></td>
                                                <td class="ds-td-class">EmrCompletenessUGDTrait</td>
                                                <td class="ds-td-class">EmrCompletenessRITrait <span class="ds-body-sm">(bobot beda: + CPPT &amp; keperawatan)</span></td>
                                            </tr>
                                            <tr>
                                                <td class="ds-td-strong">EMR dibuka dari</td>
                                                <td class="ds-body-sm">Pelayanan RJ</td>
                                                <td class="ds-body-sm">Pelayanan UGD</td>
                                                <td class="ds-body-sm">langsung dari Daftar RI (RI tanpa halaman pelayanan)</td>
                                            </tr>
                                            <tr>
                                                <td class="ds-td-strong">Section khas jalur</td>
                                                <td class="ds-body-sm">Screening · SKDP · PRB</td>
                                                <td class="ds-body-sm">Triase P0–P3 (anamnesa) · Obat &amp; Cairan · Observasi · Rujukan antar RS</td>
                                                <td class="ds-body-sm">Pengkajian Awal / Dokter · CPPT · SBAR · Asuhan Keperawatan (multi-entry)</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <p class="ds-caption mt-2 mb-2" style="color:var(--muted)">
                                    Awas dua jebakan: UGD juga memakai nama kolom <span class="ds-code">rj_no</span>
                                    (tapi tabelnya <span class="ds-code">rstxn_ugdhdrs</span>, bukan rjhdrs) — jangan tertukar;
                                    dan di RI seluruh folder/file/event <strong>bersuffix -ri</strong> serta section
                                    baru harus didaftarkan ke array section di host <span class="ds-code">erm-ri</span>.
                                </p>
                            @endif
                        @endforeach

                        <div class="ds-card-outline mt-10" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Selesai menambah fitur? Jalankan
                                <button type="button" class="hover:underline font-semibold" style="color:var(--primary)"
                                    x-on:click="go('adopsi')">Checklist Adopsi</button>
                                — plus checklist Tutorial Koding Master untuk urusan komponen, validasi, dan LOV.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 10 RANJAU UMUM ====== --}}
                    <section x-show="section === 'ranjau'" x-cloak>
                        <div class="ds-eyebrow mb-3">10 — Adopsi</div>
                        <h1 class="ds-display-md mb-4">Ranjau Umum (Livewire + Oracle + Blade)</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Jebakan yang <strong>sudah pernah menggigit</strong> di repo ini — masing-masing
                            pernah jadi bug produksi atau debugging berjam-jam. Kenali gejalanya;
                            penangkalnya sudah terstandar.
                        </p>

                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead>
                                    <tr><th>Ranjau</th><th>Gejala</th><th>Penangkal</th></tr>
                                </thead>
                                <tbody>
                                    @foreach ([
                                        ['wire:model.live di input numerik', 'digit hilang saat mengetik cepat (race roundtrip)', 'wire:model.blur utk numerik EMR; auto-calc di updated()'],
                                        ['keyup.enter + aksi $wire', 'insert dobel / nilai belum tersinkron saat Enter', 'keydown.enter.prevent + $el.blur() lalu $wire.aksi() (+ .then() refocus)'],
                                        ['Reload DB lalu $this->state = $data', 'ketikan yang belum di-Simpan ikut terhapus', 'array_replace(state lama, data DB) — jangan replace mentah'],
                                        ["Oracle: string kosong = NULL", "where col <> '' selalu 0 baris", "IS NOT NULL / LENGTH(TRIM(x)) > 0"],
                                        ['Kolom mixed-case (dari API)', 'ORA-00904 padahal kolom ada', 'DB::raw(\'"requestTransferTime" as alias_snake\')'],
                                        ['JSON_VALUE di query', 'ORA-00904 — fungsi tak dikenal di Oracle versi ini', 'INSTR utk filter kasar, atau json_decode di PHP'],
                                        ["active_status master lama", "filter 'Y'/'N' tidak mengembalikan apa pun", "nilai sebenarnya '1'/'0'"],
                                        ['Carbon 3: diffInSeconds(x, false)', 'tanda +/- kebalik dari Carbon 2', '$end->getTimestamp() - $start->getTimestamp()'],
                                        [chr(64) . 'if di dalam atribut komponen x-*', 'ParseError saat compile', 'rakit string di blok php, lalu render via kurung kurawal ganda di atribut'],
                                        ['Tag komponen dipecah antar ' . chr(64) . 'if', 'konten hilang diam-diam saat cabang skip', 'ekstrak jadi sub-komponen utuh per cabang'],
                                        ['Literal tag penutup php di string/nowdoc', 'kelas Volt terpotong → ParseError 500', 'tandai batas dgn komentar; pastikan grep tag penutup = 1'],
                                        ['Kata "re-use"/"reuse" di komentar //', 'Volt salah strip komentar → ParseError', 'hindari kata itu di komentar file Volt'],
                                        ['Call API BPJS sinkron tanpa timeout', 'seluruh worker app membeku', 'Http::timeout(8)->connectTimeout(3)'],
                                        ['Umur dari kolom thn/bln/hari', 'umur pasien basi (snapshot lama)', 'selalu hitung dari birth_date'],
                                    ] as [$ranjau, $gejala, $obat])
                                        <tr>
                                            <td class="ds-td-strong">{{ $ranjau }}</td>
                                            <td class="ds-body-sm">{{ $gejala }}</td>
                                            <td class="ds-body-sm"><span class="ds-code">{{ $obat }}</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Daftar hidupnya ada di skill repo: <span class="ds-code">oracle-quirks</span> ·
                                <span class="ds-code">livewire-input-patterns</span> ·
                                <span class="ds-code">blade-safe-edit</span> ·
                                <span class="ds-code">master-pasien</span> — plus
                                <span class="ds-code">docs/*.md</span> per pola. Kalau menemukan ranjau baru,
                                tambahkan ke sini &amp; ke skill-nya.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 11 CHECKLIST ADOPSI ====== --}}
                    <section x-show="section === 'adopsi'" x-cloak>
                        <div class="ds-eyebrow mb-3">11 — Adopsi</div>
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
                                    'Git: kerjakan di branch develop / feature branch → PR; branch main menolak merge commit (fast-forward only)',
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

                    {{-- ====== 12 TRAIT & REFERENSI ====== --}}
                    <section x-show="section === 'referensi'" x-cloak>
                        <div class="ds-eyebrow mb-3">12 — Adopsi</div>
                        <h1 class="ds-display-md mb-4">Trait &amp; Referensi</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Peta trait di <span class="ds-code">app/Http/Traits/</span> yang menopang transaksi —
                            kenali dulu sebelum menulis helper baru (kemungkinan besar sudah ada).
                        </p>

                        <div class="ds-card-dark mb-6" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Pola trait API eksternal — SEP · task-id antrean · SATU SEHAT</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['api-trait'] }}</pre>
                        </div>

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

                    {{-- ====== 13 GLOSARIUM ====== --}}
                    <section x-show="section === 'glosarium'" x-cloak>
                        <div class="ds-eyebrow mb-3">13 — Adopsi</div>
                        <h1 class="ds-display-md mb-4">Glosarium Istilah</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Domain rumah sakit penuh singkatan. Kalau menemukan istilah asing di
                            tutorial, kode, atau rapat — cari di sini dulu.
                        </p>

                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Istilah</th><th>Arti</th></tr></thead>
                                <tbody>
                                    @foreach ([
                                        ['RJ · UGD · RI', 'Tiga jalur pelayanan: Rawat Jalan, Unit Gawat Darurat, Rawat Inap'],
                                        ['No RM / reg_no', 'Nomor rekam medis — identitas pasien seumur hidup (satu per orang)'],
                                        ['rj_no / rihdr_no', 'Nomor transaksi kunjungan — satu per kedatangan (bukan per pasien)'],
                                        ['DPJP', 'Dokter Penanggung Jawab Pelayanan — dokter utama pasien'],
                                        ['EMR / ERM', 'Rekam medis elektronik — modal SOAP di halaman pelayanan'],
                                        ['SOAP', 'Subjective · Objective · Assessment · Plan — struktur pemeriksaan klinis'],
                                        ['CPPT', 'Catatan Perkembangan Pasien Terintegrasi — catatan harian multi-profesi di RI'],
                                        ['SBAR', 'Situation Background Assessment Recommendation — format komunikasi perawat→dokter'],
                                        ['Askep', 'Asuhan Keperawatan — diagnosis & intervensi perawat (standar SDKI/SLKI/SIKI)'],
                                        ['Triase P0–P3', 'Prioritas kegawatan pasien UGD (P0 resusitasi ... P3 ringan)'],
                                        ['SEP', 'Surat Eligibilitas Peserta — dokumen BPJS wajib agar kunjungan bisa diklaim'],
                                        ['VClaim', 'Web-service BPJS utk SEP, rujukan, surat kontrol'],
                                        ['MJKN', 'Mobile JKN — aplikasi booking antrean online BPJS'],
                                        ['Task-id', 'Stempel waktu tahapan pelayanan yang dilaporkan ke antrean BPJS (taskId 3–7; 99 = batal)'],
                                        ['PRB', 'Program Rujuk Balik — pasien kronis stabil ambil obat rutin di faskes 1'],
                                        ['SKDP', 'Surat Keterangan Dalam Perawatan — surat kontrol utk kunjungan berikutnya'],
                                        ['iDRG / INA-CBG', 'Sistem grouping tarif klaim Kemenkes (aplikasi E-Klaim); iDRG menggantikan INA-CBG'],
                                        ['Casemix', 'Unit pengelola koding & klaim (jembatan medis ↔ administrasi)'],
                                        ['SATU SEHAT', 'Platform interoperabilitas data kesehatan Kemenkes (standar FHIR)'],
                                        ['SIRS / Aplicares', 'Pelaporan RS Online Kemenkes / ketersediaan tempat tidur ke BPJS'],
                                        ['Klaim ID', 'Kode penjamin kunjungan (UMUM, BPJS, karyawan, dst.) — kolom klaim_id'],
                                        ['Bon', 'Pembayaran kurang dari total tagihan — sisa jadi piutang pasien'],
                                        ['Etiket', 'Label cetak kecil — identitas pasien (gelang/sampel) atau aturan pakai obat'],
                                        ['PTO', 'Pemantauan Terapi Obat — telaah apoteker utk resep RI'],
                                        ['Bangsal · Kamar · Bed', 'Hierarki tempat tidur RI (bangsal → kamar → bed)'],
                                        ['Shift', 'Pembagian waktu jaga; lookup tabel rstxn_shiftctls berdasar jam sekarang'],
                                        ['CLOB', 'Kolom teks besar Oracle — tempat JSON detail kunjungan disimpan'],
                                        ['LOV', 'List of Values — komponen pencarian data master (ketik → pilih)'],
                                    ] as [$istilah, $arti])
                                        <tr>
                                            <td class="ds-td-strong" style="white-space:nowrap">{{ $istilah }}</td>
                                            <td class="ds-body-sm">{{ $arti }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Menemukan istilah lain yang membingungkan? Tambahkan ke tabel ini —
                                glosarium hidup dari kontribusi tiap programmer baru.
                            </span>
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
