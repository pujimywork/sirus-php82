# Clause Versioning — Dokumen Legal (Consent/Pernyataan)

Pola untuk **dokumen bertanda tangan** yang teks klausulnya bisa berubah karena
kebijakan baru (mis. transisi **INA-CBG → iDRG**), sehingga **cetak ulang record lama
harus tetap memakai redaksi SAAT DITANDATANGANI**, bukan redaksi terbaru.

Diterapkan pertama di **General Consent** (RJ/UGD/RI). Bisa diperluas ke klausul lain
(penjaminan BPJS, selisih biaya, inform consent, dll) dengan pola yang sama.

## Prinsip

1. **Teks klausul per-versi disimpan di satu class registry** (bukan hardcoded di komponen/cetak).
2. **Record menstempel `clauseVersion`** = versi yang berlaku saat record dibuat.
3. **Tampilan & cetak merender versi yang tersimpan di record** — bukan versi terkini.
4. Saat kebijakan berubah: **tambah versi baru, jangan ubah versi lama.** Record lama tetap redaksi lamanya.

## Komponen

### 1. Registry teks — `app/Support/GeneralConsentClause.php`

```php
class GeneralConsentClause
{
    public const CURRENT = 'v1';        // versi yang distempel utk record BARU

    public static function get(string $context, ?string $version = null): array
    {
        $reg = self::registry();
        $ver = $version && isset($reg[$version]) ? $version : self::CURRENT;   // fallback aman
        return $reg[$ver][$context] ?? ($reg[self::CURRENT][$context] ?? []);
    }

    private static function registry(): array
    {
        return [
            'v1' => [
                'rj'  => ['subtitle'=>..., 'introTemplate'=>..., 'agreePre'=>..., 'agreePost'=>..., 'points'=>[...]],
                'ugd' => [...],
                'ri'  => [...],
            ],
            // 'v2' => [...]  ← tambah saat kebijakan berubah
        ];
    }
}
```

- `introTemplate` memakai placeholder `%WALI%`, `%HUB%`, `%RS%` (diisi & di-escape komponen via `strtr`/`e()`).
- Bagian dinamis (nama wali, RS, pilihan SETUJU/TIDAK) **tidak** disimpan di registry — diinterpolasi komponen dari data record.

### 2. Komponen render — `resources/views/components/consent/general-consent-{rj,ugd,ri}.blade.php`

```blade
@props(['mode' => 'print', 'consent' => [], 'rsName' => '', 'version' => null])

@use('App\Support\GeneralConsentClause')

@php
    $clause = GeneralConsentClause::get('ri', $version);   // context di-hardcode per komponen
    $introHtml = strtr($clause['introTemplate'] ?? '', ['%WALI%'=>e($wali), '%HUB%'=>e($hub), '%RS%'=>e($rs)]);
    $points = $clause['points'] ?? [];
    // ...
@endphp

@if ($mode === 'print') ... {{-- HTML PDF --}} @else ... {{-- Tailwind layar --}} @endif
```

- Prop `mode` = `screen` (form/tampilan, Tailwind) vs `print` (PDF). **Teks sama** (single-source dari class), styling beda.
- Prop `version` = null → pakai `CURRENT`. Record lama → versi tersimpan.

### 3. Form — stempel `clauseVersion`

Di `defaultConsent()`/`getDefaultGeneralConsent()`:

```php
'clauseVersion' => \App\Support\GeneralConsentClause::CURRENT,
```

Ikut tersimpan saat `save()` (merge default→record). Record lama yang sudah punya `clauseVersion`
dipertahankan lewat `array_replace($fresh, $state)` (state induk menang, tapi versi tersimpan tak diubah
karena hanya di-set saat default). Tag komponen layar meneruskan versi tersimpan:

```blade
<x-consent.general-consent-ri mode="screen" :consent="[...]"
    :version="$dataDaftarRi['generalConsentPasienRI']['clauseVersion'] ?? null" />
```

### 4. Cetak — render versi tersimpan (fallback legacy)

```blade
<x-consent.general-consent-ri mode="print" :consent="$consent" :rsName="$rsName"
    :version="$consent['clauseVersion'] ?? 'v1'" />
```

**Fallback `?? 'v1'`** (versi tertua), BUKAN `null` (yang jatuh ke `CURRENT`). Record **legacy**
(dibuat sebelum fitur versioning) tak punya `clauseVersion` → memang era `v1`, jadi harus render `v1`
meski `CURRENT` sudah naik ke `v2` kelak.

## Cara menambah versi baru (saat kebijakan berubah)

1. Di `GeneralConsentClause::registry()`: **TAMBAH** key `'v2' => [...]` dengan teks baru. **JANGAN ubah/hapus `'v1'`** (arsip legal record yang sudah TTD).
2. Naikkan `const CURRENT = 'v2'`.
3. Selesai. Record baru stempel `v2`; record lama tetap render `v1`.

## Gotcha

- **Fallback cetak `'v1'` bukan `null`** — kalau `null`, record legacy ikut render `CURRENT` (salah setelah ada v2).
- **`@use` bukan FQN** di komponen Blade (`@use('App\Support\GeneralConsentClause')`); `use` biasa di `@php` tidak sah (blok @php dikompilasi di dalam method render). Laravel ≥10.4 punya `@use`.
- **Jangan simpan bagian dinamis** (nama, RS, SETUJU/TIDAK) di registry — hanya teks klausul statis + placeholder.
- **Verifikasi form==print**: strip tags + normalisasi nomor list/`:` lalu banding string (nomor `<li>` dari CSS di layar vs `1.` literal di PDF itu artefak format, bukan beda teks).

## Perluasan

Klausul lain (ketentuan BPJS, selisih biaya, inform consent) belum di-versioning — ikut pola yang sama
bila diperlukan: registry class + stempel `*Version` di record + render versi tersimpan.
