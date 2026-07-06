# Komponen TTD di Layar — `<x-signature.ttd-petugas>`

Komponen reusable untuk **tanda tangan petugas di form entry** (bukan cetak): tombol
"TTD Saya" yang men-stamp nama user login + timestamp, kartu hasil TTD, dan tombol
Hapus/Ganti. Gaya mengikuti general-consent.

> Ini pola **layar/entry**. Untuk TTD di **cetakan PDF** (gambar `myuser_ttd_image`,
> underline, dll.) lihat `docs/ttd-pattern-pdf-print.md`.

File: `resources/views/components/signature/ttd-petugas.blade.php`

## Kapan dipakai
Setiap dokumen EMR yang butuh "stamp nama user login + tgl" sebagai penanda-tangan
(bukan gambar tanda tangan pasien via `signature-pad`). Sudah dipakai 11 dokumen VK
+ 8 form bedah/operasi (modul-dokumen RI). **Jangan tulis blok TTD inline lagi —
pakai komponen ini.**

## Prasyarat di komponen Livewire induk
Komponen hanya UI; logika stamp ada di induk. Sediakan:

```php
public array $newForm = [ /* ... */ 'ttd' => '', 'ttdCode' => '', 'ttdDate' => '' ];
public bool $isFormLocked = false;

public function ttdSaya(): void {
    if ($this->isFormLocked) return;                    // guard server-side (wajib)
    if (!empty($this->newForm['ttd'])) return;          // cegah double-sign
    $this->newForm['ttd']     = auth()->user()->myuser_name ?? '';
    $this->newForm['ttdCode'] = auth()->user()->myuser_code ?? '';   // utk gambar TTD di cetak
    $this->newForm['ttdDate'] = \Carbon\Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
}
public function hapusTtd(): void {
    if ($this->isFormLocked) return;                    // guard server-side (wajib)
    $this->newForm['ttd'] = $this->newForm['ttdCode'] = $this->newForm['ttdDate'] = '';
}
```

`wire:click` di komponen menempel ke Livewire induk (anonymous component render inline),
jadi method cukup ada di induk.

## Props

| Prop | Default | Guna |
|---|---|---|
| `:ttd` | `''` | nilai nama TTD (lempar `$newForm['ttd']`) |
| `:date` | `''` | tgl/jam TTD (`$newForm['ttdDate']`) |
| `:code` | `''` | kode penanda-tangan; kalau diisi tampil "Kode: xxx" |
| `:locked` | `false` | sembunyikan tombol saat form terkunci (`$isFormLocked`) |
| `sign` | `ttdSaya` | nama method Livewire stamp TTD |
| `clear` | `hapusTtd` | nama method Livewire hapus TTD |
| `:allowClear` | `true` | `false` → sekali TTD tak bisa diubah (tombol Ganti/Hapus disembunyikan); mis. form serah-terima |
| `:framed` | `true` | `true`=dibungkus border-form (kartu, kolom sempit tengah); `false`=tanpa bingkai, rata kiri, `flex-1` (utk grid-cell) |
| `title` | `Tanda Tangan` | judul border-form (hanya saat framed) |
| `label` | `Petugas (Penanda-tangan)` | subtitle kecil; kirim `""` untuk sembunyikan |
| `signLabel` | `TTD Saya` | teks tombol stamp |
| `clearLabel` | `Ganti / Hapus TTD` | teks tombol hapus |
| `emptyText` | `Belum ditandatangani.` | teks saat terkunci & belum TTD |

## Contoh

**VK (framed, default subtitle):**
```blade
<x-signature.ttd-petugas :ttd="$newForm['ttd']" :date="$newForm['ttdDate'] ?? ''"
    :locked="$isFormLocked" />
```

**Bedah single-section (framed, judul spesifik, tanpa subtitle, simpan kode):**
```blade
<x-signature.ttd-petugas :ttd="$newForm['operatorTtd']" :date="$newForm['operatorTtdDate'] ?? ''"
    :code="$newForm['operatorTtdCode'] ?? ''" :locked="$isFormLocked"
    sign="setOperatorTtd" clear="clearOperatorTtd"
    title="Tanda Tangan Operator" label="" signLabel="TTD sebagai Operator" clearLabel="Hapus TTD" />
```

**Unframed dalam grid dua-kolom (mis. pra-anestesi kolom dokter):**
```blade
<x-signature.ttd-petugas :framed="false" :ttd="$newForm['ttd']" :date="$newForm['ttdDate'] ?? ''"
    :code="$newForm['ttdCode'] ?? ''" :locked="$isFormLocked" sign="setTtd" clear="clearTtd"
    label="Dokter Anestesi" signLabel="TTD Dokter Anestesi" clearLabel="Hapus TTD" />
```

## Struktur internal
Komponen = wrapper tipis; isi diekstrak ke `ttd-petugas-body.blade.php`. Wrapper
memilih dibungkus `<x-border-form>` (framed) atau tidak, dengan memanggil body
**utuh** di tiap cabang `@if`. Ini disengaja — lihat gotcha di bawah.

## Gotcha
- **JANGAN split satu tag komponen antar `@if`** (`@if($x)<x-border-form>@endif ...isi...
  @if($x)</x-border-form>@endif`). Saat cabang skip, **seluruh isi hilang** (output kosong)
  karena buffer `startComponent`/`renderComponent` tak seimbang. Ekstrak isi ke sub-komponen
  dan bungkus utuh per cabang (pola file ini).
- **JANGAN tulis `<x-...>` di komentar** file komponen (`@props`/`@php`). Blade tetap
  mengkompilasinya jadi tag → runtime `Undefined variable $component` (padahal `php -l` &
  `view:cache` lolos). Sebut tanpa angle-x.
- **Verifikasi via render, bukan HTTP status.** `curl` bisa dapat 302 (redirect login)
  sebelum komponen ter-render → bug render tak muncul. Uji tiap kombinasi prop:
  `php artisan tinker --execute="echo strlen(Blade::render('<x-signature.ttd-petugas :framed=\"false\" ... />'));"`
  (LEN=0 = bug). Lihat skill `blade-safe-edit`.
- Untuk render gambar TTD di cetakan, simpan `ttdCode` (myuser_code) dan resolve
  `myuser_ttd_image` di method `cetak()`; lihat `docs/ttd-pattern-pdf-print.md`.
