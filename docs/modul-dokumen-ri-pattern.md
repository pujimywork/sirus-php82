# Pola Modul-Dokumen RI (formulir bertanda tangan, multi-entri)

Formulir EMR Rawat Inap yang **ditandatangani** dan bisa berulang: General Consent,
Inform Consent, Permintaan Kerohanian, Edukasi Terintegrasi, Penundaan Pelayanan,
Pengkajian Akhir Hayat, dst. Semua tampil sebagai tab di
`transaksi/ri/emr-ri/modul-dokumen/modul-dokumen-ri.blade.php`.

Acuan kanonik: **Inform Consent RI** (paling lengkap) dan **Pengkajian Akhir Hayat**
(paling baru; contoh gabungan formulir KARS + RM.RI).

> Beda dengan `docs/emr-multi-entry-document-pattern.md` (CPPT/SBAR): di sana entri
> ditulis banyak PPA per-profesi & di-review DPJP. Di sini entri **ditandatangani
> pasien/keluarga + saksi + petugas**, lalu **terkunci** dan bisa dicetak.

---

## 1. Struktur file (3 titik sentuh)

```
resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/<nama>/rm-<nama>-actions.blade.php   ← komponen Volt
resources/views/pages/components/modul-dokumen/r-i/<nama>/cetak-<nama>-print.blade.php       ← cetak PDF
resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/modul-dokumen-ri.blade.php           ← daftarkan tab
```

Pendaftaran tab = 2 tempat di file yang sama: `<x-tab …>` + panel `<div x-show="activeTab === '<key>'">`
berisi `<livewire:… :riHdrNo="$riHdrNo" :disabled="$isFormLocked" wire:key="…" />`.

## 2. Kerangka komponen

- Traits: `EmrRITrait, MasterPasienTrait, WithRenderVersioningTrait, WithValidationToastTrait`.
- Kartu ringkas + tombol → `openModal()` → `x-modal size=full height=full`.
- Data disimpan di **`datadaftarri_json`** dengan key khusus modul (mis. `pengkajianAkhirHayatRI`),
  berbentuk LIST entri: `['id', 'created_at', 'created_by', 'form' => [...], 'finalized' => bool]`.
- Tulis SELALU lewat `DB::transaction` + `lockRIRow()` + `updateJsonRI()` + `appendAdminLogRI(..., 'MR')`.
- `array_replace_recursive(defaultForm(), $entri['form'])` saat memuat entri lama —
  record lama yang belum punya key baru tetap aman (lihat feedback normalisasi JSON legacy).

## 3. Siklus hidup entri (BAKU)

```
Simpan Draft (tanpa validasi)  →  pasien/keluarga TTD (+saksi, opsional)
   →  PETUGAS TTD  =  validasi penuh + stempel + KUNCI entri
   →  entri read-only: hanya Lihat / Cetak
   →  [Admin | Manager Umum | Manager Medis]  Buka Kunci  →  kembali draft, TTD petugas dicabut
```

Aturan yang mengikat:
- **TTD petugas adalah aksi terakhir dan sekaligus pengunci** (`setDokterPenjelas` di Inform
  Consent, `ttdPetugas()` di Akhir Hayat). JANGAN sediakan tombol "Simpan & Kunci" terpisah —
  dua jalan mengunci = perilaku bercabang.
- Tombol footer cukup **Simpan Draft** (jadi "Simpan Perubahan" saat melanjutkan draft).
- `entryIsFinal()` baca flag `finalized`; fallback record lama = ada TTD pasien/keluarga.
- Entri final tak boleh ditimpa: `persistEntry()` melempar RuntimeException bila targetnya final.
- **Buka kunci** hanya mencabut `finalized` + **TTD petugas**; TTD pasien/keluarga & saksi
  DIPERTAHANKAN (tak boleh dihapus sepihak oleh staf) + audit log wajib menyebut pelakunya.
  Gate dua lapis: `@hasanyrole` di tombol **dan** `bolehBukaKunci()` di server.

## 4. Tanda tangan

| Pihak | Cara | Wajib? |
|---|---|---|
| Pasien / keluarga | `x-signature.signature-pad` → `signature-result` bila sudah ada | wajib |
| Saksi | idem | opsional (`nullable`) — tampilkan langsung, jangan sembunyikan di balik tombol |
| Petugas | `x-signature.ttd-petugas` (`:framed=false`, `:allowClear=false`) | wajib; menstempel nama+kode+jam user login |

- TTD ikut `rules()` (`'signature' => 'required|string'`) supaya error tampil **merah di
  kolomnya** + toast — jangan cek manual yang cuma memunculkan toast.
- Nama/waktu petugas TIDAK divalidasi: di-stempel oleh aksinya sendiri.
- Tiga kolom TTD dibuat **sama tinggi** (`items-stretch` + `h-full flex flex-col`, area TTD `flex-1`).
- Cetak: gambar TTD pasien dari base64 di JSON; TTD petugas dari `users.myuser_ttd_image`
  via `petugasCode`. Layout TTD di PDF wajib `<table>` (lihat `docs/ttd-pattern-pdf-print.md`).

## 5. Validasi — seminimal mungkin

Formulir klinis diisi bertahap. Mewajibkan banyak field hanya membuat petugas mengetik asal
supaya bisa mengunci. Wajibkan hanya: **tanggal**, **nama + hubungan penanda tangan**, **TTD**.
Aturan bersyarat dipakai hanya bila tanpa isian itu datanya tak bermakna
(mis. pilih "Donasi organ" → organnya wajib disebut).

Tanggal SELALU `date_format:d/m/Y H:i:s` (standar repo, ±95 tempat).

## 6. Teks legal → clause-versioning

Kalimat pernyataan/persetujuan TIDAK ditulis inline di blade. Taruh di
`App\Support\<Nama>Clause`, stempel `clauseVersion` per entri, cetak pakai versi tersimpan.
Lihat `docs/clause-versioning.md` + skill `clause-versioning`.

## 7. Rancangan isi form (pelajaran dari Akhir Hayat)

- Pecah jadi **panel bernomor sama dengan formulir kertas** (`x-border-form :collapsible`),
  hanya panel awal `:open="true"`. Satu panel raksasa = melelahkan.
- Sub-kelompok di dalam panel diberi **bingkai** + judul kecil uppercase, dipasang kanan-kiri.
  Untuk pasien vs keluarga: dua kartu sejajar, isi & urutan field dibuat cermin.
- Skala keparahan (Tidak ada/Ringan/Sedang/Berat) lebih ringkas daripada belasan checkbox
  gejala — beri **warna gradasi** (hijau→kuning→oranye→merah) dan klik ulang = batal pilih.
- Gabungkan opsi bersinonim, TAPI **jangan gabungkan diagnosis keperawatan (SDKI) atau
  tindakan medis** — tiap butir keputusan klinis harus berdiri sendiri.
- Cek tumpang tindih antar bagian sebelum rilis (mis. "rencana rawat di rumah" vs "instruksi
  perawatan di rumah"; "dukungan/kunjungan" vs "intervensi keperawatan").
- Isi otomatis dari data yang sudah ada (diagnosis & TTV dari Pengkajian Awal), tetap bisa dikoreksi.

## 8. Jebakan Blade yang SUDAH pernah menggigit di modul ini

1. **Escape ganda pada prop yang di-echo ulang komponen.**
   `value="A &amp; B"` / `title=` / `nameLabel=` / `signLabel=` → komponen meng-echo `{{ }}`
   lagi → layar menampilkan `&amp;`. Tulis `&` polos di atribut komponen; `&amp;` hanya untuk
   teks HTML biasa. Untuk nilai dinamis pakai **`:value="$x"`**, bukan `value="{{ $x }}"`.
2. **Tag `x-...` di dalam komentar PHP tetap dikompilasi** → ParseError/`Undefined $component`.
   Sebut nama komponennya tanpa angle-bracket.
3. `x-radio-button` props aslinya `label/value/name` + `wire:model.live` (tidak ada
   `current`/`wireClick`); `signature-pad` hanya punya `wireMethod`. Jangan mengarang prop.
4. Verifikasi sebelum lapor: compile via `Blade::compileString` + `php -l`, hitung
   keseimbangan `<div>`/`<fieldset>`/`<x-border-form>`, dan render dengan data contoh
   lalu cek `Unexpected end tag` via DOMDocument.

> Catatan `Blade::compileString`: sebagian file host (mis. `cetak-rekam-medis-open`)
> **tidak** standalone-compilable (pakai `@if` yang terentang via `@include`/slot) —
> hasilnya "unexpected endif". Bandingkan dgn versi `git HEAD`: kalau HEAD gagal identik,
> itu pre-existing, bukan salahmu. Validasi final yang sahih = **`php artisan view:cache`**
> (EXIT 0 = semua view kompilasi lewat pipeline Blade asli), lalu `view:clear`.

---

## 9. Port ke jalur lain (RI ⇄ UGD ⇄ RJ)

Satu form dokumen sering harus tersedia di lebih dari satu jalur. **Jangan tulis ulang** —
salin file actions + cetak dari jalur acuan, lalu ganti token berikut (per-string, bukan
`RI→UGD` global karena banyak identifier mengandung "RI"):

| RI | UGD | RJ |
|----|-----|----|
| `Txn\Ri\EmrRITrait` | `Txn\Ugd\EmrUGDTrait` | `Txn\Rj\EmrRJTrait` |
| `?string $riHdrNo` | `?int $rjNo` | `?int $rjNo` |
| `$dataDaftarRi` | `$dataDaftarUGD` | `$dataDaftarRj` |
| `findDataRI` / `checkEmrRIStatus` | `findDataUGD` / `checkEmrUGDStatus` | `findDataRJ` / … |
| `updateJsonRI` / `appendAdminLogRI` / `lockRIRow` | `…UGD` | `…RJ` |
| key JSON `pengkajian<Dok>RI` | `pengkajian<Dok>UGD` | `…RJ` |
| modal `rm-<dok>-ri-` · area `modal-<dok>-ri` | `…-ugd` | `…-rj` |
| `display-pasien-ri :riHdrNo` | `display-pasien-ugd :rjNo` | `display-pasien-rj :rjNo` |
| prefill `pengkajianAwalPasienRawatInap…` | path EMR UGD (mis. `diagnosisFreeText`) | path EMR RJ |
| loadView `…r-i.<dok>.cetak-<dok>-ri-print` | `…u-g-d.<dok>.cetak-<dok>-print` | `…r-j.…` |

Konvensi nama: folder/file **UGD/RJ membuang sufiks** `-ri` (`…/akhir-hayat/rm-akhir-hayat-actions`),
tapi **modal-name / renderArea / nama file PDF tetap** memakai `-ugd`/`-rj`. `regNo`/`regName`
tersedia di data UGD/RJ juga, jadi cetak inline + `MasterPasienTrait` tetap jalan.
`App\Support\*Clause` & `App\Support\*Options` (peta label cetak) **dipakai bersama** semua jalur —
jangan diduplikasi. Verifikasi: `view:cache` EXIT 0 + grep tidak ada token RI nyasar.

## 10. Viewer di display Rekam Medis (WAJIB saat menambah dokumen)

Menambah form baru **belum selesai** sampai dokumennya bisa dilihat di **display Rekam Medis**.
Pola viewer read-only (Lihat = render blade cetak ke iframe) ada di
`docs/dokumen-view-pattern.md`. Untuk tiap jalur yang dipasang:

1. Buat komponen viewer `resources/views/pages/components/rekam-medis/<jalur>/dokumen-view/<dok>-view-<jalur>.blade.php`.
2. **Daftarkan** di `…/rekam-medis/<jalur>/cetak-rekam-medis/cetak-rekam-medis-open.blade.php`
   (RI pakai var `$ri` + `:riHdrNo`; UGD pakai `$txn` + `:rjNo`), oper `:entries="$rec['pengkajian<Dok><Jalur>'] ?? []"`.
3. Dokumen dgn cetak **payload seragam** (dataRi/form/ttd) → pakai
   `DokumenViewSupportTrait::previewDokumenRi()/streamCetakDokumenRi()` langsung.
4. Dokumen dgn cetak **payload bespoke** (butuh `entry` + `opsiLabel` + `clause`, mis. Akhir Hayat)
   → viewer **self-contained**: pakai `dvPasien/dvTtdPath/dvIdentitasRs/renderDokumenPreview`
   + `buildData()` yang meniru `cetak()` komponen EMR. Taruh peta label di
   `App\Support\<Dok>Options::labels()` supaya satu sumber untuk semua jalur.
