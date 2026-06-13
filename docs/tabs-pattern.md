# Standar Komponen Tab (`x-tabs` / `x-tab`)

> Bagian dari [Standar UI & Komponen](standar-ui-komponen.md) — lihat juga standar tombol, modal, form section, dan tabel.

Tab bar reusable sesuai **Standar UI siRUS v2**. Mengganti pola lama yang verbose (`<ul><li><button @class([...])>` atau `x-bind:class="..."` di-copy per tab).

File: `resources/views/components/tabs.blade.php` (track) + `resources/views/components/tab.blade.php` (item).

---

## Anatomi

- `<x-tabs>` — kontainer/track. Menyimpan prop `variant` dan diwariskan ke tiap `<x-tab>` di dalamnya via `@aware` (variant cukup ditulis sekali di induk).
- `<x-tab>` — satu item (di-render jadi `<button type="button">`). Atribut apa pun (`wire:click`, `x-on:click`, `title`, dst.) diteruskan ke `<button>`.

```blade
<x-tabs variant="underline">
    <x-tab :active="$activeTab === 'rj'"  color="emerald" wire:click="setTab('rj')">Rawat Jalan</x-tab>
    <x-tab :active="$activeTab === 'ugd'" color="rose"    wire:click="setTab('ugd')">UGD</x-tab>
    <x-tab :active="$activeTab === 'ri'"  color="blue"    wire:click="setTab('ri')">Rawat Inap</x-tab>
</x-tabs>
```

---

## Dua mode aktivasi

| Mode | Kapan | Cara |
|------|-------|------|
| **Server** | Aktif ditentukan Livewire/PHP (re-render saat klik) | `:active="$activeTab === 'rj'"` + `wire:click="..."` |
| **Alpine** | Aktif dihitung client-side, instan tanpa round-trip | `active-expr="tab === 'RiVisit'"` + `x-on:click="tab = 'RiVisit'"` |

**Mode Alpine** dipakai untuk tab yang dulunya `x-bind:class` + `@entangle`. Tetap switch seketika di browser:

```blade
<div x-data="{ tab: @entangle('activeTab') }">
    <x-tabs variant="underline" class="flex-wrap p-2">
        @foreach ($menus as $m)
            <x-tab active-expr="tab === '{{ $m['id'] }}'" x-on:click="tab = '{{ $m['id'] }}'">
                {{ $m['name'] }}
            </x-tab>
        @endforeach
    </x-tabs>
</div>
```

> Kalau `active-expr` diisi, komponen memakai `x-bind:class` reaktif dan **mengabaikan** `:active`. Jangan pasang `:class` sendiri di `<x-tab>` mode Alpine.

---

## Props

### `<x-tabs>`
| Prop | Default | Nilai |
|------|---------|-------|
| `variant` | `underline` | `pill` · `underline` · `card` · `chip` |

### `<x-tab>`
| Prop | Default | Catatan |
|------|---------|---------|
| `variant` | (warisan dari `<x-tabs>`) | Boleh dioverride per item |
| `active` | `false` | Mode server (boolean) |
| `active-expr` | `null` | Ekspresi JS Alpine; mengaktifkan mode Alpine |
| `color` | `brand` | `brand` · `emerald` · `rose` · `blue` · `purple` · `violet` — hanya berlaku di variant `underline` & `pill` |

---

## Varian

| Varian | Tampilan aktif | Pakai untuk |
|--------|----------------|-------------|
| `underline` | Garis bawah + teks berwarna | **Default** — tab section konten lebar (pengganti panel) |
| `pill` | Pill solid di track abu-abu | Filter/toggle ringkas |
| `card` | Tab kartu/folder naik menyatu panel bawah | — |
| `chip` | Chip `rounded-full` lepas, bisa wrap | — |

---

## Aturan & gotcha

- **Warna per modul dipertahankan.** Tab top-level (kasir/casemix/apotek) pakai `color=emerald` (RJ), `rose` (UGD), `blue` (RI), `violet` (rekap) sebagai penanda konteks. Tab EMR/administrasi tetap `brand` (hijau).
- **Ikon & badge** masuk ke slot `<x-tab>`. Untuk ikon + teks, tambahkan `class="inline-flex items-center gap-2"`.
- **Di dalam `<x-scrollable-tabs>`** (tab banyak, perlu scroll horizontal): JANGAN bungkus lagi dengan `<x-tabs>` (border-bottom dobel). Pakai `<x-tab variant="underline">` langsung di dalam `<div class="flex flex-nowrap gap-2 -mb-px">`; border-bottom ada di `<x-scrollable-tabs>`.
- **`color` hanya untuk `underline` & `pill`.** `card`/`chip` selalu brand.
- **Warna pakai map statis** (bukan `text-{$color}-700` dinamis) — Tailwind JIT tidak men-scan kelas yang disusun runtime. Kalau butuh warna baru, tambah entri di map `$palette` pada `tab.blade.php`.
- Padding baku `<x-tab>` = `px-4 py-2`, font `text-title-sm` (15px). Jangan override paksa `p-4` (konflik dengan `py-2`).

---

## Migrasi dari pola lama

Acuan migrasi transaksi (merged main `1dee65ef`): 12 tab bar dikonversi, −161 baris. Validasi wajib: `php artisan view:cache` (compile semua Blade dengan resolver komponen asli) lalu `view:clear`.

Lihat juga skill `blade-safe-edit` (edit Blade presisi) dan `livewire-input-patterns`.
