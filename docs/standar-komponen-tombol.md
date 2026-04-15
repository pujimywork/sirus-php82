# Standar Komponen Tombol

> Bagian dari [Standar UI & Komponen](standar-ui-komponen.md) — lihat juga standar modal, form section, tabel, dan input number.

Panduan penggunaan komponen tombol (`resources/views/components/*-button.blade.php`) agar konsisten di seluruh aplikasi.

---

## Daftar Komponen

### 1. `<x-primary-button>` — Aksi Utama

Solid hijau (brand-green). Hanya untuk **satu aksi utama** per modal/form.

```blade
<x-primary-button wire:click.prevent="save()" class="min-w-[120px]"
    wire:loading.attr="disabled" :disabled="$isFormLocked">
    <span wire:loading.remove>Simpan</span>
    <span wire:loading><x-loading /> Menyimpan...</span>
</x-primary-button>
```

**Kapan pakai:** Simpan, Submit, Tambah (aksi utama)
**Jangan pakai untuk:** Navigasi, link ke halaman lain

---

### 2. `<x-secondary-button>` — Aksi Sekunder

Abu-abu muda (`bg-gray-100`) + border abu (`border-gray-300`).

```blade
<x-secondary-button wire:click="closeModal">Batal</x-secondary-button>
```

**Kapan pakai:** Batal, Reset, Close (di footer modal)
**Jangan pakai untuk:** Close X di header (pakai `icon-button`), aksi hapus

---

### 3. `<x-outline-button>` — Aksi Alternatif / Tab

Tint hijau (`bg-brand-green/10`) + border hijau (`border-brand-green/30`). Hover berubah solid hijau.

```blade
<x-outline-button wire:click="switchTab('gizi')">Gizi</x-outline-button>
<x-outline-button wire:click="switchTab('nyeri')">Nyeri</x-outline-button>
```

**Kapan pakai:** Tab navigasi, pilihan alternatif, toggle view
**Jangan pakai untuk:** Aksi utama (pakai `primary-button`)

---

### 4. `<x-ghost-button>` — Navigasi / Link

Tint hijau sangat tipis (`bg-brand-green/5`) + border tipis (`border-brand-green/20`). Paling ringan secara visual.

```blade
<a href="{{ route('master.pasien') }}" wire:navigate>
    <x-ghost-button type="button">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
        </svg>
        Master Pasien
    </x-ghost-button>
</a>
```

**Kapan pakai:** Link ke halaman lain (Master Pasien), navigasi sekunder, riwayat
**Jangan pakai untuk:** Aksi yang mengubah data

---

### 5. `<x-icon-button>` — Tombol Ikon

Kotak kecil (`p-2`) transparan + border. Mendukung warna: `green`, `blue`, `red`, `yellow`, `gray`.

```blade
{{-- Close X di header modal --}}
<x-icon-button color="gray" type="button" wire:click="closeModal">
    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd"
            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
            clip-rule="evenodd" />
    </svg>
</x-icon-button>

{{-- Cetak SEP --}}
<x-icon-button color="blue" type="button" wire:click="cetakSEP" title="Cetak SEP">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
    </svg>
</x-icon-button>
```

**Referensi warna:**

| Color | Kegunaan |
|-------|----------|
| `gray` | Close X, aksi netral |
| `blue` | Cetak, info |
| `green` | Aksi positif |
| `red` | Hapus (ikon kecil di dalam form) |
| `yellow` | Peringatan |

**Kapan pakai:** Tombol yang hanya berisi ikon (Close X, Cetak, Edit, Hapus item kecil)
**Jangan pakai untuk:** Tombol dengan teks (pakai komponen lain)

---

### 6. `<x-info-button>` — Aksi BPJS / SEP

Solid biru (`bg-blue-600`).

```blade
<x-info-button type="button" wire:click="openVclaimModal" class="gap-2 text-xs">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
    </svg>
    Kelola SEP BPJS
</x-info-button>
```

**Kapan pakai:** Kelola SEP, SPRI, Cetak SEP (dengan teks), aksi terkait BPJS/VClaim
**Jangan pakai untuk:** Aksi umum yang tidak terkait BPJS

---

### 7. `<x-success-button>` — Aksi Positif / Selesai

Solid lime (`bg-brand-lime`), teks gelap.

```blade
<x-success-button wire:click="serah">Serah Obat</x-success-button>
```

**Kapan pakai:** Serah obat (apotek), konfirmasi selesai, aksi final positif

---

### 8. `<x-warning-button>` — Aksi Peringatan

Solid kuning (`bg-yellow-500`).

```blade
<x-warning-button wire:click="forceUpdate">Update Paksa</x-warning-button>
```

**Kapan pakai:** Aksi yang perlu perhatian ekstra tapi bukan destruktif

---

### 9. `<x-danger-button>` — Aksi Destruktif

Solid merah (`bg-red-600`). **Disarankan pakai `confirm-button` sebagai gantinya** untuk aksi hapus data penting.

```blade
<x-danger-button wire:click="hapus">Hapus</x-danger-button>
```

**Kapan pakai:** Aksi destruktif ringan (hapus item dalam form yang belum disimpan)
**Untuk hapus data penting:** Pakai `<x-confirm-button variant="danger">` (lihat di bawah)

---

### 10. `<x-confirm-button>` — Aksi dengan Konfirmasi

Tombol yang memunculkan dialog konfirmasi styled sebelum eksekusi. Mendukung variant: `danger`, `primary`, `secondary`, `outline`.

```blade
{{-- Hapus data penting --}}
<x-confirm-button variant="danger" :action="'requestDelete(\'' . $id . '\')'"
    title="Hapus Data" message="Yakin ingin menghapus? Tindakan ini tidak dapat dibatalkan."
    confirmText="Ya, hapus" cancelText="Batal">
    Hapus
</x-confirm-button>

{{-- Batal transaksi --}}
<x-confirm-button variant="danger" action="batalTransaksi()"
    title="Batal Transaksi"
    message="Yakin ingin membatalkan transaksi? Semua data pembayaran akan dihapus."
    confirmText="Ya, batalkan" cancelText="Batal">
    Batal Transaksi
</x-confirm-button>

{{-- Transfer dengan peringatan --}}
<x-confirm-button variant="warning" action="transferKeUGD()"
    title="Transfer ke UGD"
    message="Yakin ingin mentransfer biaya RJ ini ke UGD?"
    confirmText="Ya, transfer" cancelText="Batal">
    Transfer ke UGD
</x-confirm-button>
```

**Props:**

| Prop | Default | Keterangan |
|------|---------|------------|
| `variant` | `danger` | `danger` / `primary` / `secondary` / `outline` |
| `action` | (wajib) | Method Livewire yang dieksekusi, contoh: `"delete('10')"` |
| `title` | `Konfirmasi` | Judul dialog |
| `message` | `Apakah Anda yakin?` | Pesan dialog |
| `confirmText` | `Ya` | Teks tombol konfirmasi |
| `cancelText` | `Batal` | Teks tombol batal |
| `disabled` | `false` | Nonaktifkan tombol |

**WAJIB pakai untuk:**
- Hapus data dari database (master, transaksi)
- Hapus SEP dari server BPJS
- Batal transaksi (kasir)
- Hapus dokumen bertanda tangan (inform consent, form penjaminan)

**Jangan pakai `wire:confirm`** (browser native) — selalu pakai `<x-confirm-button>` agar dialog styled dan konsisten.

---

### 11. `<x-radio-button>` — Input Radio Bergaya Tombol

Bukan tombol aksi, melainkan input radio yang ditampilkan sebagai tombol.

```blade
<x-radio-button :label="$klaim['klaimDesc']" :value="$klaim['klaimId']"
    name="klaimId" wire:model.live="klaimId" :disabled="$isFormLocked" />
```

---

## Pola Standar per Konteks

### Footer Modal

```blade
<div class="sticky bottom-0 z-10 px-6 py-4 bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
    <div class="flex justify-between gap-3">
        {{-- Kiri: navigasi --}}
        <a href="{{ route('master.pasien') }}" wire:navigate>
            <x-ghost-button type="button">
                <svg>{{-- ikon user --}}</svg>
                Master Pasien
            </x-ghost-button>
        </a>

        {{-- Kanan: batal + simpan --}}
        <div class="flex gap-3">
            <x-secondary-button wire:click="closeModal">Batal</x-secondary-button>
            <x-primary-button wire:click.prevent="save()" class="min-w-[120px]"
                wire:loading.attr="disabled" :disabled="$isFormLocked">
                <span wire:loading.remove>Simpan</span>
                <span wire:loading><x-loading /> Menyimpan...</span>
            </x-primary-button>
        </div>
    </div>
</div>
```

### Header Modal (Close X)

```blade
<x-icon-button color="gray" type="button" wire:click="closeModal">
    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd"
            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
            clip-rule="evenodd" />
    </svg>
</x-icon-button>
```

### Area SEP / BPJS

```blade
<div class="flex flex-wrap items-center gap-2">
    {{-- Kelola SEP --}}
    <x-info-button type="button" wire:click="openVclaimModal" class="gap-2 text-xs">
        <svg>{{-- ikon dokumen --}}</svg>
        Kelola SEP BPJS
    </x-info-button>

    {{-- Cetak SEP (ikon saja) --}}
    @if (!empty($data['sep']['noSep']))
        <x-icon-button color="blue" type="button" wire:click="cetakSEP" title="Cetak SEP">
            <svg>{{-- ikon printer --}}</svg>
        </x-icon-button>
    @endif
</div>
```

### Tabel: Tombol Hapus di Baris Data

```blade
{{-- Data penting (master, transaksi) → confirm-button --}}
<x-confirm-button variant="danger" :action="'requestDelete(\'' . $row->id . '\')'"
    title="Hapus Data" :message="'Yakin hapus ' . $row->name . '?'"
    confirmText="Ya, hapus" cancelText="Batal"
    class="px-2 py-1 text-xs">
    Hapus
</x-confirm-button>

{{-- Item ringan dalam form (belum disimpan) → icon-button --}}
<x-icon-button color="red" wire:click="removeItem({{ $idx }})">
    <svg>{{-- ikon X --}}</svg>
</x-icon-button>
```

---

## Aturan

1. **Satu `<x-primary-button>` per modal/form** — hanya untuk aksi utama (Simpan/Submit)
2. **Jangan pakai primary untuk navigasi** — pakai `ghost-button` atau `outline-button`
3. **Close X selalu `<x-icon-button color="gray">`** — bukan secondary
4. **Aksi BPJS/SEP selalu `<x-info-button>`** — biru, langsung terlihat beda
5. **Hapus data penting selalu lewat `<x-confirm-button>`** — jangan langsung eksekusi
6. **Jangan pakai `wire:confirm`** (browser native) untuk hapus — pakai `<x-confirm-button>`
7. **Jangan pakai `!important` override** — pilih komponen yang tepat

---

## Hierarki Visual

Dari paling menonjol ke paling ringan:

```
primary-button    → Solid hijau       → Aksi UTAMA
info-button       → Solid biru        → BPJS / SEP
success-button    → Solid lime        → Serah obat, selesai
warning-button    → Solid kuning      → Peringatan
danger-button     → Solid merah       → Destruktif ringan
secondary-button  → Abu-abu + border  → Batal, Reset
outline-button    → Tint hijau        → Tab, alternatif
ghost-button      → Tint tipis        → Navigasi, link
icon-button       → Transparan kotak  → Ikon (Close, Cetak)
confirm-button    → Multi-variant     → Aksi butuh konfirmasi
```
