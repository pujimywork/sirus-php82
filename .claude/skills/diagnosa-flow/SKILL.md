---
name: diagnosa-flow
description: Arsitektur & jebakan diagnosa ICD-10 (RSMST_MSTDIAGS, LOV diagnosa, EMR, SEP/VClaim, iDRG/INACBG). Baca sebelum mengubah/menambah apa pun yang memilih atau menyimpan diagnosa — ada 288 icdx kembar di master yang bikin lookup flag naive (value/first) salah baris, plus aturan icdx vs diag_id per konsumen.
---

# Diagnosa Flow (sirus-php82)

Dok lengkap: **`docs/diagnosa-architecture.md`** — baca itu untuk detail.
Ringkasan keputusan cepat:

## Peta komponen

| Lapisan | Lokasi |
|---|---|
| Master + 4 flag iDRG (`valid_code/accpdx/asterisk/im`) | `RSMST_MSTDIAGS`, UI `/master/diagnosa` |
| Picker standar (SATU-satunya) | `livewire/lov/diagnosa/lov-diagnosa.blade.php` — guard `valid_code!==1` & `primaryOnly` ada di `choose()`, berlaku utk SEMUA pemakai |
| EMR diagnosis (RJ/UGD/RI) | `rm-diagnosa-*-actions.blade.php` — dual-write `rstxn_*dtls.diag_id` + JSON `diagnosis[]` (`diagId/icdX/kategoriDiagnosa`) |
| SEP / VClaim | `vclaim-*-actions.blade.php` — `SEPForm.diagAwal` = **icdx** |
| iDRG/INACBG coder | `kirim-diagnosa-{idrg,inacbg}.blade.php` — JSON `idrg.coderDiagnosa[]` (`code`=icdx), kirim `"PRI#SEC#..."`, validcode final dari `expanded[]` E-Klaim |

## Jebakan utama

1. **288 icdx kembar**: baris seed E-Klaim (`K20` vc=1) + baris legacy (`K20X`/`M47.80`
   dapat default `0/'N'`). JANGAN lookup flag via `value()`/`first()` by icdx — pakai:
   - cek boleh-primer: `->where(fn($q)=>$q->where('icdx',$code)->orWhere('diag_id',$code))->where('accpdx','Y')->exists()`
   - lookup massal + `keyBy('icdx')`: tambah `->orderBy('valid_code')->orderBy('accpdx')` (baris terbaik menang).
   - Lookup **by diag_id saja** (PK unik, mis. dari payload LOV) aman pakai `value()`.
2. **icdx vs diag_id**: ke sistem eksternal (BPJS SEP, E-Klaim) kirim `icdx`;
   utk join/simpan internal pakai `diag_id`.
3. **Jangan hapus baris master** — legacy `diag_id` direferensikan >130rb baris
   `rstxn_*dtls`. Nonaktifkan via `valid_code=0` (toggle di /master/diagnosa).
4. Field diagnosa **primer** → `:primaryOnly="true"` di LOV atau guard exists-Y server-side;
   jaga single-Primary invariant saat auto-kategori.
5. Fix data baris kembar: `database/sql/2026_06_04_sync_dup_icdx_validation_flags.sql`
   (cek dulu sudah dieksekusi atau belum sebelum menyimpulkan bug LOV).
