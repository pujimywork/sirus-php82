# Arsitektur Diagnosa (ICD-10) — sirus-php82

Peta lengkap bagaimana diagnosa mengalir di aplikasi: master → LOV → konsumen
(EMR, SEP/VClaim, iDRG/INACBG). Baca ini SEBELUM mengubah apa pun yang
menyentuh pemilihan/penyimpanan diagnosa.

Terakhir diupdate: 2026-06-04.

---

## 1. Master: `RSMST_MSTDIAGS`

| Kolom | Tipe | Arti |
|---|---|---|
| `DIAG_ID` | VARCHAR2(10) PK | Kode internal (lihat konvensi di bawah) |
| `DIAG_DESC` | VARCHAR2(300) | Nama diagnosa (boleh translasi custom admin) |
| `ICDX` | VARCHAR2(15) | Kode ICD-10 tampilan (dotted, mis. `A00.1`, atau 3-char `K20`) |
| `VALID_CODE` | NUMBER | `1` = leaf/boleh dipakai koding; `0` = parent/category → **diblok di LOV** |
| `ACCPDX` | VARCHAR2(1) | `Y` = boleh jadi diagnosa **Primary**; `N` = hanya Secondary (`!PDX`) |
| `ASTERISK` | NUMBER | `1` = kode asterisk ★ (manifestasi, wajib pair dagger †) |
| `IM` | NUMBER | `1` = kode spesifik iDRG/INACBG Indonesian Modification |

4 kolom flag ditambahkan 2026-05-20 (`database/sql/2026_05_20_alter_idrg_add_validation_columns.sql`,
default `0/'N'`) dan diisi dari seed E-Klaim iDRG TSV 20260331
(`database/sql/seed_rsmst_mstdiags_idrg_20260331.sql`, MERGE **by diag_id**, 40.815 baris,
SAB=ICD10_2010_IM). Kelola via UI **/master/diagnosa** (list badge Status Koding + form 4 toggle).

### Konvensi `DIAG_ID` — PENTING, ada 3 generasi

| Bentuk | Contoh | Asal |
|---|---|---|
| No-dot | `A001` (=A00.1), `K20`, `M4780` | Seed E-Klaim 20260331 (format iDRG) |
| Padding-X | `K20X`, `A99X` | Legacy Oracle Dev 6i utk kode 3-char |
| Dotted | `M47.80`, `R65.9` | Legacy lain |

### ⚠️ 288 icdx kembar (gotcha terbesar)

Karena MERGE seed match by `diag_id`, baris legacy (`K20X`, `M47.80`) **tidak ter-match**
→ hanya dapat default DDL `0/'N'`, lalu seed menambah baris BARU (`K20`, `M4780`) dengan
flag asli E-Klaim. Hasil: **288 kode punya 2 baris dengan `icdx` sama tapi flag bertolak
belakang** (1 hijau valid + 1 merah invalid di dropdown LOV) → perilaku pilih kode terasa acak
(kasus nyata: K20 "terblok" di iDRG tapi lolos di SEP — padahal K20 memang VALID per E-Klaim).

- Baris legacy **TIDAK BOLEH dihapus**: direferensikan >130rb baris transaksi
  (`rstxn_rjdtls`/`ridtls`/`ugddtls`/`rjdtlks`/`oks` kolom `diag_id`).
- Fix data: `database/sql/2026_06_04_sync_dup_icdx_validation_flags.sql`
  (samakan flag antar baris kembar ke MAX grup = nilai seed; idempotent).
- Fix kode: semua guard accpdx pakai pola **exists-Y** (lihat §4), bukan `value()`
  yang ambil baris sembarang.

---

## 2. LOV: `livewire/lov/diagnosa/lov-diagnosa.blade.php`

Satu-satunya picker diagnosa standar. **Selalu pakai ini** untuk field diagnosa baru —
jangan bikin autocomplete sendiri.

```blade
<livewire:lov.diagnosa.lov-diagnosa
    label="Diagnosa *"
    target="rjFormDiagnosaVclaim"      {{-- bedakan per form --}}
    :initialDiagnosaId="$diagnosaId"   {{-- mode edit (reactive; cari by diag_id lalu icdx) --}}
    :primaryOnly="true"                {{-- opsional: blok accpdx='N' (utk field DXP) --}}
    :disabled="$isFormLocked" />
```

Perilaku:
- Search min 2 char, limit 50, match `diag_id`/`icdx`/`diag_desc`.
- Dropdown menampilkan **SEMUA** baris termasuk invalid — baris invalid merah + badge
  (`!PDX` amber, `★` ungu, `iM` emerald), sama dengan badge di /master/diagnosa.
- Guard di `choose()` (berlaku di SEMUA pemakai LOV, bukan cuma iDRG):
  1. `valid_code !== 1` → toast error, batal pilih.
  2. `primaryOnly && accpdx !== 'Y'` → toast error, batal pilih.
- Sukses → dispatch `lov.selected.{target}` dengan payload:
  ```php
  ['diag_id' => ..., 'diag_desc' => ..., 'icdx' => ...,
   'valid_code' => int, 'accpdx' => 'Y|N', 'asterisk' => int, 'im' => int]
  ```

Handler di parent:
```php
#[On('lov.selected.rjFormDiagnosaVclaim')]
public function onDiagnosa(string $target, array $payload): void { ... }
```

---

## 3. Konsumen & format simpan

### a. EMR Diagnosis (RJ/UGD/RI)
File: `pages/transaksi/{rj,ugd}/emr-*/diagnosa/rm-diagnosa-*-actions.blade.php`,
`ri/emr-ri/diagnosa-ri/rm-diagnosa-ri-actions.blade.php`.

**Dual-write**:
1. Tabel transaksi legacy: `rstxn_rjdtls` / `rstxn_ugddtls` / `rstxn_ridtls`
   (`diag_id` + nomor detail) + update flag header (mis. `rstxn_rjhdrs.rj_diagnosa='D'`).
2. JSON EMR `diagnosis[]`:
   ```php
   ['diagId' => ..., 'diagDesc' => ..., 'icdX' => ..., 'ketdiagnosa' => ...,
    'kategoriDiagnosa' => 'Primary|Secondary', 'rjDtlDtl' => ..., 'rjNo' => ...]
   ```
   Auto-kategori: Primary pertama hanya jika `accpdx='Y'` (lookup by `diag_id` payload —
   aman, `diag_id` unik). Single-Primary invariant dijaga `setKategoriDiagnosa()`.

### b. SEP / VClaim (pembuatan SEP BPJS)
File: `pages/transaksi/{rj,ugd,ri}/daftar-*/vclaim-*-actions.blade.php`.

- LOV target `*FormDiagnosaVclaim` → handler set `SEPForm['diagAwal'] = payload['icdx']`.
- **Yang dikirim ke BPJS = `icdx`** (kode ICD-10 dotted), BUKAN `diag_id`.
- Validitas master tetap berlaku (guard LOV) — kode `valid_code=0` tak bisa dipilih utk SEP.

### c. iDRG / INACBG coder (klaim E-Klaim Kemenkes)
File: `pages/transaksi/{rj,ugd,ri}/idrg/kirim-diagnosa-{idrg,inacbg}.blade.php`.
Dok detail: `docs/idrg-bridging.md`.

- Editor coder terpisah dari EMR: JSON `idrg.coderDiagnosa[]` / `idrg.coderInacbgDiagnosa[]`
  `['code' => icdx, 'desc' => ..., 'kategori' => 'Primary|Secondary', 'validcode' => ?, 'validInfo' => ?]`.
- Sumber: LOV (guard penuh) atau **Sync dari EMR** (`diagnosis[]` → tanpa guard LOV;
  validcode diisi dari master, lihat pola keyBy §4).
- Kirim ke E-Klaim: `buildString()` → `"PRIMARY#SECONDARY#..."` (kode = icdx).
- Respons `expanded[]` E-Klaim meng-update `validcode` per baris → badge Valid/Tidak Valid
  di tabel coder (kebenaran final = E-Klaim, master hanya pre-filter).

---

## 4. Pola wajib saat menulis guard/lookup flag diagnosa

Karena ada icdx kembar (§1), **JANGAN** lookup flag dengan `value()`:

```php
// ❌ SALAH — baris yang kena bisa baris legacy ber-flag default
$accpdx = DB::table('rsmst_mstdiags')
    ->where('icdx', $code)->orWhere('diag_id', $code)->value('accpdx');

// ✅ BENAR (cek "boleh primer") — true jika ADA baris kode tsb dengan accpdx='Y'
$isAllowedAsPrimary = DB::table('rsmst_mstdiags')
    ->where(fn($q) => $q->where('icdx', $code)->orWhere('diag_id', $code))
    ->where('accpdx', 'Y')
    ->exists();

// ✅ BENAR (lookup banyak kode sekaligus utk keyBy) — baris terbaik menang
$masters = DB::table('rsmst_mstdiags')
    ->whereIn('icdx', $codes)->orWhereIn('diag_id', $codes)
    ->select('diag_id', 'icdx', 'valid_code', 'accpdx', 'im')
    ->orderBy('valid_code')->orderBy('accpdx')   // keyBy keep TERAKHIR → asc = terbaik menang
    ->get();
$byIcdx = $masters->keyBy('icdx');
```

Pengecualian: lookup **by `diag_id` saja** (PK, unik — mis. dari payload LOV yang membawa
`diag_id` baris yang benar-benar diklik) boleh pakai `value()`.

## 5. Checklist menambah field/fitur diagnosa baru

1. Pakai `<livewire:lov.diagnosa.lov-diagnosa>` dengan `target` unik per form.
2. Butuh kode utk sistem eksternal? Kirim **`icdx`** (BPJS/E-Klaim), bukan `diag_id`.
3. Butuh referensi internal/join transaksi? Simpan **`diag_id`**.
4. Field diagnosa primer? Set `:primaryOnly="true"` ATAU guard exists-Y (§4) di server.
5. Auto-kategori Primary/Secondary: cek dulu sudah ada Primary, lalu guard accpdx.
6. Jangan hapus baris master yang direferensikan transaksi — nonaktifkan via `valid_code=0`.
